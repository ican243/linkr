<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Controllers\Link;
use App\Models\ApiKeyModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Links extends BaseController
{
    // 로그인 세션 없이, API 키(Authorization: Bearer ...)만으로 링크를 생성하는 엔드포인트.
    // 웹 화면(Link::create)과 커스텀 URL 검증 규칙(예약어/형식)은 최대한 동일하게 맞춤.
    // API 자체는 요금제 페이지 안내대로 엔터프라이즈 요금제 전용(requireEnterprisePlan 참고).
    public function create()
    {
        $user = $this->authenticate();

        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'API 키가 없거나 올바르지 않습니다. Authorization: Bearer <키> 헤더를 확인해주세요.',
            ]);
        }

        if ($blocked = $this->requireEnterprisePlan($user)) {
            return $blocked;
        }

        $url         = $this->request->getPost('original_url');
        $title       = trim((string) $this->request->getPost('title'));
        $customAlias = trim((string) $this->request->getPost('custom_alias'));

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        $model = new LinkModel();

        if ($customAlias !== '') {
            if (! preg_match('/^[A-Za-z0-9_-]{3,30}$/', $customAlias)) {
                return $this->response->setStatusCode(400)->setJSON(['error' => '커스텀 URL은 영문, 숫자, -, _ 만 사용해서 3~30자로 입력해주세요.']);
            }

            if (in_array(strtolower($customAlias), Link::RESERVED_ALIASES, true)) {
                return $this->response->setStatusCode(400)->setJSON(['error' => '이 커스텀 URL은 시스템에서 사용 중이라 선택할 수 없습니다.']);
            }

            if ($model->where('short_code', $customAlias)->first()) {
                return $this->response->setStatusCode(409)->setJSON(['error' => '이미 사용 중인 커스텀 URL입니다.']);
            }

            $code = $customAlias;
        } else {
            $code = $model->generateUniqueShortCode();
        }

        $model->insert([
            'user_id'      => $user['id'],
            'original_url' => $url,
            'title'        => $title !== '' ? $title : null,
            'short_code'   => $code,
        ]);

        helper('link');

        return $this->response->setStatusCode(201)->setJSON([
            'short_url'  => short_url(['user_id' => $user['id'], 'short_code' => $code]),
            'short_code' => $code,
        ]);
    }

    // 내가 만든 링크 목록 조회 (최신순, 20개씩 페이지네이션)
    public function index()
    {
        $user = $this->authenticate();

        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'API 키가 없거나 올바르지 않습니다. Authorization: Bearer <키> 헤더를 확인해주세요.',
            ]);
        }

        if ($blocked = $this->requireEnterprisePlan($user)) {
            return $blocked;
        }

        $model = (new LinkModel())->where('user_id', $user['id'])->orderBy('created_at', 'DESC');
        $links = $model->paginate(20);
        $pager = $model->pager;

        helper('link');
        $data = array_map(static fn (array $link): array => [
            'short_code'   => $link['short_code'],
            'short_url'    => short_url($link),
            'original_url' => $link['original_url'],
            'title'        => $link['title'],
            'click_count'  => (int) $link['click_count'],
            'created_at'   => $link['created_at'],
        ], $links);

        return $this->response->setJSON([
            'data' => $data,
            'meta' => [
                'current_page' => $pager->getCurrentPage(),
                'last_page'    => $pager->getLastPage(),
                'total'        => $pager->getTotal(),
            ],
        ]);
    }

    // 링크 삭제 (내 소유 링크만 가능 — 다른 사람 API 키로 남의 링크를 지울 수 없게 소유자 확인)
    public function delete(string $code)
    {
        $user = $this->authenticate();

        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'API 키가 없거나 올바르지 않습니다. Authorization: Bearer <키> 헤더를 확인해주세요.',
            ]);
        }

        if ($blocked = $this->requireEnterprisePlan($user)) {
            return $blocked;
        }

        $model = new LinkModel();
        $link  = $model->where('short_code', $code)->first();

        if (! $link || (int) $link['user_id'] !== (int) $user['id']) {
            return $this->response->setStatusCode(404)->setJSON(['error' => '해당 링크를 찾을 수 없습니다.']);
        }

        $model->delete($link['id']); // click_logs는 FK CASCADE로 자동 같이 삭제됨

        return $this->response->setJSON(['message' => '링크가 삭제되었습니다.']);
    }

    // 요금제 페이지에서 "API 액세스는 엔터프라이즈 전용"이라고 안내한 것과 실제 동작을 맞추는 검사.
    // 키 자체는 유효해도(authenticate 통과) 발급 당시와 달리 플랜이 강등됐을 수도 있어서,
    // 매 API 호출마다 지금 시점의 plan을 다시 확인한다. 문제없으면 null, 막혔으면 응답을 바로 반환.
    private function requireEnterprisePlan(array $user): ?ResponseInterface
    {
        if ($user['plan'] === 'enterprise') {
            return null;
        }

        return $this->response->setStatusCode(403)->setJSON([
            'error' => 'API 이용은 엔터프라이즈 요금제 전용 기능입니다. 요금제를 업그레이드해주세요.',
        ]);
    }

    // Authorization 헤더의 API 키를 검사해서, 주인 회원 정보를 반환한다. 실패하면 null.
    private function authenticate(): ?array
    {
        $header = $this->request->getHeaderLine('Authorization');

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $keyModel = new ApiKeyModel();
        $apiKey   = $keyModel->where('key_hash', hash('sha256', trim($matches[1])))->first();

        if (! $apiKey) {
            return null;
        }

        $keyModel->update($apiKey['id'], ['last_used_at' => date('Y-m-d H:i:s')]);

        return (new UserModel())->find($apiKey['user_id']);
    }
}
