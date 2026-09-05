<?php

//단축링크의 생성, 조회, 수정, 삭제, 리다이렉트를 전부 담당하는 컨트롤러 이 서버의 핵심 기능이 여기 다 들어있음.!!
namespace App\Controllers;

use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Link extends BaseController
{
    // 무료 요금제 월별 링크 생성 한도. 예전엔 고정 상수(100)였는데, 이제 관리자가
    // /admin/settings 화면에서 바꿀 수 있도록 settings 테이블에서 읽어옴.
    // User\Dashboard에서도 "이번 달 사용량" 표시할 때 이 메서드를 그대로 씀.
    public static function freePlanMonthlyLimit(): int
    {
        return (int) (new SettingModel())->getValue('free_plan_link_limit', '100');
    }

    // 커스텀 URL로 못 쓰게 막아야 하는 이름들. Routes.php에 등록된 1단계 경로들과 겹치면
    // 그 커스텀 링크는 절대 작동하지 않는 "죽은 링크"가 되어버리기 때문에 미리 차단함.
    // API 컨트롤러(Api\Links)에서도 커스텀 URL 검증에 그대로 재사용하므로 public으로 둠.
    public const RESERVED_ALIASES = [
        'auth', 'register', 'login', 'logout', 'admin', 'shorten',
        'dashboard', 'account', 'custom-domain', 'links', 'qr', 'pricing', 'stats',
        'api', 'api-keys', 'payment',
    ];

    public function form()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        return view('link/form');
    }

    public function create()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $url         = $this->request->getPost('original_url');
        $title       = trim((string) $this->request->getPost('title'));
        $customAlias = trim((string) $this->request->getPost('custom_alias'));
        $password    = $this->request->getPost('password');
        $expiresAt   = $this->request->getPost('expires_at');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return view('link/form', ['error' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        $model = new LinkModel();

        $user = (new UserModel())->find(session()->get('user_id'));

        if ($user['plan'] === 'free') {
            $usedThisMonth = $model
                ->where('user_id', $user['id'])
                ->where('created_at >=', date('Y-m-01 00:00:00'))
                ->countAllResults();

            $monthlyLimit = self::freePlanMonthlyLimit();

            if ($usedThisMonth >= $monthlyLimit) {
                return view('link/form', [
                    'error' => '무료 요금제는 월 ' . $monthlyLimit . '개까지만 링크를 만들 수 있습니다. (이번 달 ' . $usedThisMonth . '개 생성함) 프로 요금제는 무제한입니다.',
                ]);
            }

            // 비밀번호 보호/만료일은 pricing.php에서 "프로 요금제부터"라고 안내하는 기능이라,
            // 무료 회원이 둘 중 하나라도 채워서 보내면 여기서 막는다.
            if (($password !== null && $password !== '') || ($expiresAt !== null && $expiresAt !== '')) {
                return view('link/form', [
                    'error' => '비밀번호 보호와 만료일 설정은 프로 요금제부터 사용할 수 있습니다.',
                ]);
            }
        }

        if ($customAlias !== '') {
            if (! preg_match('/^[A-Za-z0-9_-]{3,30}$/', $customAlias)) {
                return view('link/form', ['error' => '커스텀 URL은 영문, 숫자, -, _ 만 사용해서 3~30자로 입력해주세요.']);
            }

            if (in_array(strtolower($customAlias), self::RESERVED_ALIASES, true)) {
                return view('link/form', ['error' => '이 커스텀 URL은 시스템에서 사용 중이라 선택할 수 없습니다. 다른 이름을 입력해주세요.']);
            }

            if ($model->where('short_code', $customAlias)->first()) {
                return view('link/form', ['error' => '이미 사용 중인 커스텀 URL입니다. 다른 이름을 입력해주세요.']);
            }

            $code = $customAlias;
        } else {
            $code = $model->generateUniqueShortCode();
        }

        $model->insert([
            'user_id'      => session()->get('user_id'),
            'original_url' => $url,
            'title'        => $title !== '' ? $title : null,
            'short_code'   => $code,
            // 비밀번호/만료일을 입력 안 했으면 null로 저장 -> 보호/만료 없는 기본 링크가 됨
            'password'     => ($password !== null && $password !== '') ? password_hash($password, PASSWORD_DEFAULT) : null,
            // <input type="datetime-local">는 "2026-09-01T15:30" 형식으로 오므로 DB용 형식으로 변환
            'expires_at'   => ($expiresAt !== null && $expiresAt !== '') ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null,
        ]);

        return view('link/form', ['shortUrl' => base_url($code), 'shortCode' => $code]);
    }

    public function editForm(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);

        return view('link/edit', ['link' => $link]);
    }

    public function update(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);

        $url          = $this->request->getPost('original_url');
        $title        = trim((string) $this->request->getPost('title'));
        $password     = $this->request->getPost('password');
        $removePw     = (bool) $this->request->getPost('remove_password');
        $expiresAt    = $this->request->getPost('expires_at');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return view('link/edit', ['link' => $link, 'error' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        // 비밀번호 제거/만료일 비우기(둘 다 "보호를 없애는" 방향)는 요금제와 상관없이 항상 허용.
        // 새로 비밀번호를 걸거나 만료일을 채우는 것만 무료 회원한테 막는다.
        if ($user['plan'] === 'free' && (($password !== null && $password !== '') || ($expiresAt !== null && $expiresAt !== ''))) {
            return view('link/edit', [
                'link'  => $link,
                'error' => '비밀번호 보호와 만료일 설정은 프로 요금제부터 사용할 수 있습니다.',
            ]);
        }

        $data = [
            'original_url' => $url,
            'title'        => $title !== '' ? $title : null,
            // datetime-local 칸에는 기존 값이 미리 채워져 있으므로, 비워서 제출하면 "만료일 제거"라는 뜻으로 처리
            'expires_at'   => ($expiresAt !== null && $expiresAt !== '') ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null,
        ];

        if ($removePw) {
            $data['password'] = null;
        } elseif ($password !== null && $password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // 체크박스도 안 누르고 비밀번호 칸도 비워뒀으면 -> $data에 'password' 키 자체를 안 넣어서 기존 값 그대로 유지

        $model = new LinkModel();
        $model->update($link['id'], $data);

        return redirect()->to('/dashboard')->with('message', '링크가 수정되었습니다.');
    }

    public function delete(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);

        $model = new LinkModel();
        $model->delete($link['id']); // click_logs는 FK CASCADE로 자동 같이 삭제됨

        return redirect()->to('/dashboard')->with('message', '링크가 삭제되었습니다.');
    }

    public function redirect(string $code)
    {
        $link = $this->findLinkOrFail($code);

        if ($this->isExpired($link)) {
            return view('link/expired', ['layout' => $this->visitorLayoutFor($link)]);
        }

        if (! empty($link['password'])) {
            return view('link/password', ['code' => $code, 'layout' => $this->visitorLayoutFor($link)]);
        }

        return $this->doRedirect($link);
    }

    public function verifyPassword(string $code)
    {
        $link = $this->findLinkOrFail($code);

        if ($this->isExpired($link)) {
            return view('link/expired', ['layout' => $this->visitorLayoutFor($link)]);
        }

        $input = $this->request->getPost('password');

        if (empty($link['password']) || ! password_verify((string) $input, $link['password'])) {
            return view('link/password', [
                'code'   => $code,
                'error'  => '비밀번호가 올바르지 않습니다.',
                'layout' => $this->visitorLayoutFor($link),
            ]);
        }

        return $this->doRedirect($link);
    }

    // 화이트라벨링(엔터프라이즈 전용): 링크를 만든 사람이 엔터프라이즈 회원이면, 그 링크를
    // 클릭한 방문자(회원이 아닐 수도 있음)에게 king 로고/네비게이션/광고 문구가 안 보이는
    // 화면(minimal_layout)을 보여준다. 그 외 요금제는 지금처럼 king 브랜딩이 그대로 보임.
    private function visitorLayoutFor(array $link): string
    {
        $owner = (new UserModel())->find($link['user_id']);

        return ($owner !== null && $owner['plan'] === 'enterprise') ? 'minimal_layout' : 'layout';
    }

    private function isExpired(array $link): bool
    {
        return ! empty($link['expires_at']) && strtotime($link['expires_at']) < time();
    }

    private function findLinkOrFail(string $code): array
    {
        $model = new LinkModel();
        $link  = $model->where('short_code', $code)->first();

        if (! $link) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $link;
    }

    // 수정/삭제 전용: 이 링크가 지금 로그인한 사람 것이 맞는지 확인 (통계 화면과 같은 원칙)
    private function findOwnedLinkOrFail(string $code): array
    {
        $link = $this->findLinkOrFail($code);

        if ((int) $link['user_id'] !== (int) session()->get('user_id')) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $link;
    }

    private function doRedirect(array $link)
    {
        $model = new LinkModel();
        $model->update($link['id'], ['click_count' => $link['click_count'] + 1]);

        $logModel = new ClickLogModel();
        $logModel->insert([
            'link_id'    => $link['id'],
            'clicked_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => (string) $this->request->getUserAgent(),
        ]);

        return redirect()->to($link['original_url']);
    }

}
