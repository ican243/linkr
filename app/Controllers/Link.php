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

    // 무료 요금제 만료일 상한(일). 무료는 만료일을 아예 안 걸 수는 없고(생성 시 기본값도 이만큼
    // 뒤로 자동 설정됨), 최대로 늘려도 이 일수까지만 됨.
    public const FREE_PLAN_MAX_EXPIRY_DAYS = 30;

    public function form()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        helper('link');

        return view('link/form', ['user' => $user]);
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
        $fallbackUrl = trim((string) $this->request->getPost('fallback_url'));
        $maxClicks   = trim((string) $this->request->getPost('max_clicks'));

        $model = new LinkModel();

        $user = (new UserModel())->find(session()->get('user_id'));

        helper('link');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return view('link/form', ['user' => $user, 'error' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        if ($user['plan'] === 'free') {
            $usedThisMonth = $model
                ->where('user_id', $user['id'])
                ->where('created_at >=', date('Y-m-01 00:00:00'))
                ->countAllResults();

            $monthlyLimit = self::freePlanMonthlyLimit();

            if ($usedThisMonth >= $monthlyLimit) {
                return view('link/form', [
                    'user'  => $user,
                    'error' => '무료 요금제는 월 ' . $monthlyLimit . '개까지만 링크를 만들 수 있습니다. (이번 달 ' . $usedThisMonth . '개 생성함) 프로 요금제는 무제한입니다.',
                ]);
            }

            // 비밀번호 보호는 pricing.php에서 "프로 요금제부터"라고 안내하는 기능이라 여기서 막는다.
            // (만료일은 이제 무료도 사용함 — 대신 최대 기간이 제한됨, 아래에서 따로 처리)
            if ($password !== null && $password !== '') {
                return view('link/form', [
                    'user'  => $user,
                    'error' => '비밀번호 보호는 프로 요금제부터 사용할 수 있습니다.',
                ]);
            }
        }

        $expiryResult = $this->resolveExpiresAt($user['plan'], $expiresAt);

        if ($expiryResult['error'] !== null) {
            return view('link/form', ['user' => $user, 'error' => $expiryResult['error']]);
        }

        if ($customAlias !== '') {
            if (! preg_match('/^[A-Za-z0-9_-]{3,30}$/', $customAlias)) {
                return view('link/form', ['user' => $user, 'error' => '커스텀 URL은 영문, 숫자, -, _ 만 사용해서 3~30자로 입력해주세요.']);
            }

            if (in_array(strtolower($customAlias), self::RESERVED_ALIASES, true)) {
                return view('link/form', ['user' => $user, 'error' => '이 커스텀 URL은 시스템에서 사용 중이라 선택할 수 없습니다. 다른 이름을 입력해주세요.']);
            }

            if ($model->where('short_code', $customAlias)->first()) {
                return view('link/form', ['user' => $user, 'error' => '이미 사용 중인 커스텀 URL입니다. 다른 이름을 입력해주세요.']);
            }

            $code = $customAlias;
        } else {
            $code = $model->generateUniqueShortCode();
        }

        // fallback_url/max_clicks는 그 요금제에서 못 쓰는 값이면 그냥 조용히 무시함(저장 안 함) —
        // 화면에서 애초에 그 요금제엔 입력칸 자체를 안 보여주므로, 직접 POST로 우회해도 저장은 안 되게.
        $data = [
            'user_id'      => session()->get('user_id'),
            'original_url' => $url,
            'title'        => $title !== '' ? $title : null,
            'short_code'   => $code,
            // 비밀번호를 입력 안 했으면 null로 저장 -> 보호 없는 기본 링크가 됨
            'password'     => ($password !== null && $password !== '') ? password_hash($password, PASSWORD_DEFAULT) : null,
            'expires_at'   => $expiryResult['value'],
            'fallback_url' => in_array($user['plan'], ['pro', 'enterprise'], true) && $fallbackUrl !== '' ? $fallbackUrl : null,
            'max_clicks'   => $user['plan'] === 'enterprise' && $maxClicks !== '' && ctype_digit($maxClicks) ? (int) $maxClicks : null,
        ];

        $model->insert($data);

        $shortUrl = short_url(['user_id' => $user['id'], 'short_code' => $code]);

        return view('link/form', ['user' => $user, 'shortUrl' => $shortUrl, 'shortCode' => $code]);
    }

    // 만료일 입력을 요금제 정책에 맞게 검증/변환한다.
    // - 무료: 안 입력하면 지금부터 30일 후로 자동 설정, 입력해도 30일 이내로만 허용
    // - 프로/엔터프라이즈: 안 입력하면 무제한(null), 입력하면 그대로(상한 없음)
    // 반환값의 'value'는 DB 저장용 UTC 문자열(또는 null), 'error'는 실패 시 화면에 보여줄 메시지.
    private function resolveExpiresAt(string $plan, ?string $rawInput): array
    {
        $rawInput = trim((string) $rawInput);

        if ($plan === 'free') {
            if ($rawInput === '') {
                $defaultUtc = date('Y-m-d H:i:s', strtotime('+' . self::FREE_PLAN_MAX_EXPIRY_DAYS . ' days'));

                return ['value' => $defaultUtc, 'error' => null];
            }

            $utc = kst_input_to_utc($rawInput);

            if ($utc === null) {
                return ['value' => null, 'error' => '만료일 형식이 올바르지 않습니다.'];
            }

            $capUtc = strtotime('+' . self::FREE_PLAN_MAX_EXPIRY_DAYS . ' days');

            if (strtotime($utc) > $capUtc) {
                return ['value' => null, 'error' => '무료 요금제는 만료일을 최대 ' . self::FREE_PLAN_MAX_EXPIRY_DAYS . '일 이내로만 설정할 수 있습니다.'];
            }

            return ['value' => $utc, 'error' => null];
        }

        // 프로/엔터프라이즈: 상한 없음, 안 입력하면 무제한
        if ($rawInput === '') {
            return ['value' => null, 'error' => null];
        }

        $utc = kst_input_to_utc($rawInput);

        if ($utc === null) {
            return ['value' => null, 'error' => '만료일 형식이 올바르지 않습니다.'];
        }

        return ['value' => $utc, 'error' => null];
    }

    public function editForm(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);
        $user = (new UserModel())->find(session()->get('user_id'));

        helper('link');

        return view('link/edit', ['link' => $link, 'user' => $user]);
    }

    public function update(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);

        $url         = $this->request->getPost('original_url');
        $title       = trim((string) $this->request->getPost('title'));
        $password    = $this->request->getPost('password');
        $removePw    = (bool) $this->request->getPost('remove_password');
        $expiresAt   = $this->request->getPost('expires_at');
        $fallbackUrl = trim((string) $this->request->getPost('fallback_url'));
        $maxClicks   = trim((string) $this->request->getPost('max_clicks'));

        $user = (new UserModel())->find(session()->get('user_id'));

        helper('link');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return view('link/edit', ['link' => $link, 'user' => $user, 'error' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        // 비밀번호를 새로 거는 것만 무료 회원한테 막는다(제거는 요금제 상관없이 항상 허용).
        if ($user['plan'] === 'free' && $password !== null && $password !== '') {
            return view('link/edit', [
                'link'  => $link,
                'user'  => $user,
                'error' => '비밀번호 보호는 프로 요금제부터 사용할 수 있습니다.',
            ]);
        }

        $expiryResult = $this->resolveExpiresAt($user['plan'], $expiresAt);

        if ($expiryResult['error'] !== null) {
            return view('link/edit', ['link' => $link, 'user' => $user, 'error' => $expiryResult['error']]);
        }

        $data = [
            'original_url' => $url,
            'title'        => $title !== '' ? $title : null,
            'expires_at'   => $expiryResult['value'],
        ];

        if (in_array($user['plan'], ['pro', 'enterprise'], true)) {
            $data['fallback_url'] = $fallbackUrl !== '' ? $fallbackUrl : null;
        }

        if ($user['plan'] === 'enterprise') {
            $data['max_clicks'] = $maxClicks !== '' && ctype_digit($maxClicks) ? (int) $maxClicks : null;
        }

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

    // 엔터프라이즈 전용: 만료(시간 경과 또는 클릭 한도 도달)된 링크를 다시 살린다.
    // 시간 제한과 클릭 제한을 둘 다 없애는 방식(무제한으로 되돌림) — 다시 제한을 걸고
    // 싶으면 수정 화면에서 새로 설정하면 됨.
    public function reactivate(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $link = $this->findOwnedLinkOrFail($code);
        $user = (new UserModel())->find(session()->get('user_id'));

        if ($user['plan'] !== 'enterprise') {
            return redirect()->to('/links')->with('error', '링크 재활성화는 엔터프라이즈 요금제부터 사용할 수 있습니다.');
        }

        (new LinkModel())->update($link['id'], ['expires_at' => null, 'max_clicks' => null]);

        return redirect()->to('/links')->with('message', '링크가 재활성화되었습니다.');
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

        if ($this->isUnavailable($link)) {
            return $this->handleUnavailable($link);
        }

        if (! empty($link['password'])) {
            return view('link/password', ['code' => $code, 'layout' => $this->visitorLayoutFor($link)]);
        }

        return $this->doRedirect($link);
    }

    public function verifyPassword(string $code)
    {
        $link = $this->findLinkOrFail($code);

        if ($this->isUnavailable($link)) {
            return $this->handleUnavailable($link);
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

    // 만료(시간 경과) 또는 클릭 한도 도달 시 요금제별로 다르게 처리한다.
    // - 무료: 안내 페이지를 HTTP 404로 보여줌(원래 상태값 그대로 "없는 페이지" 취급)
    // - 프로/엔터프라이즈: fallback_url이 있으면 그쪽으로 리다이렉트, 없으면 안내 페이지(200)
    private function handleUnavailable(array $link)
    {
        $owner = (new UserModel())->find($link['user_id']);
        $plan  = $owner['plan'] ?? 'free';

        if ($plan !== 'free' && ! empty($link['fallback_url'])) {
            return redirect()->to($link['fallback_url']);
        }

        $body = view('link/expired', [
            'layout' => $this->visitorLayoutFor($link),
            'title'  => $link['title'] ?? null,
        ]);

        if ($plan === 'free') {
            return $this->response->setStatusCode(404)->setBody($body);
        }

        return $body;
    }

    // 화이트라벨링(엔터프라이즈 전용): 링크를 만든 사람이 엔터프라이즈 회원이면, 그 링크를
    // 클릭한 방문자(회원이 아닐 수도 있음)에게 king 로고/네비게이션/광고 문구가 안 보이는
    // 화면(minimal_layout)을 보여준다. 그 외 요금제는 지금처럼 king 브랜딩이 그대로 보임.
    private function visitorLayoutFor(array $link): string
    {
        $owner = (new UserModel())->find($link['user_id']);

        return ($owner !== null && $owner['plan'] === 'enterprise') ? 'minimal_layout' : 'layout';
    }

    private function isUnavailable(array $link): bool
    {
        return $this->isExpired($link) || $this->isClickLimitReached($link);
    }

    private function isExpired(array $link): bool
    {
        // expires_at은 항상 UTC로 저장되고, 서버 time()도 UTC 기준이라 그냥 비교하면 됨
        // (KST->UTC 변환은 저장하는 시점(Link::resolveExpiresAt)에서 이미 끝남).
        return ! empty($link['expires_at']) && strtotime($link['expires_at']) < time();
    }

    // 엔터프라이즈 전용 기능(max_clicks)이지만, 필드 자체가 없는 링크(다른 요금제)는
    // 그냥 항상 false이므로 여기서 요금제를 따로 체크할 필요는 없음.
    private function isClickLimitReached(array $link): bool
    {
        return ! empty($link['max_clicks']) && (int) $link['click_count'] >= (int) $link['max_clicks'];
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
