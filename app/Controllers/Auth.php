<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public function registerForm()
    {
        return view('auth/register');
    }

    public function register()
    {
        $rules = [
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'name'             => 'permit_empty|max_length[100]',
        ];

        $messages = [
            'email' => [
                'required'    => '이메일을 입력해주세요.',
                'valid_email' => '올바른 이메일 형식이 아닙니다.',
                'is_unique'   => '이미 가입된 이메일입니다.',
            ],
            'password' => [
                'required'   => '비밀번호를 입력해주세요.',
                'min_length' => '비밀번호는 8자 이상이어야 합니다.',
            ],
            'password_confirm' => [
                'required' => '비밀번호 확인을 입력해주세요.',
                'matches'  => '비밀번호가 일치하지 않습니다.',
            ],
            'name' => [
                'max_length' => '이름은 100자를 넘을 수 없습니다.',
            ],
        ];

        // 원본 비밀번호 길이를 여기서 먼저 검사한다. 해시(password_hash) 이후 값으로
        // min_length를 검사하면 해시 문자열은 항상 60자 이상이라 검증이 무의미해진다.
        if (! $this->validate($rules, $messages)) {
            return view('auth/register', ['errors' => $this->validator->getErrors()]);
        }

        $model = new UserModel();
        $model->insert([
            'email'    => $this->request->getPost('email'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            'name'     => $this->request->getPost('name'),
        ]);

        return redirect()->to('/auth/login')->with('message', '회원가입이 완료되었습니다. 로그인해주세요.');
    }

    public function loginForm()
    {
        return view('auth/login');
    }

    public function login()
    {
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // 같은 이메일 기준, 300초(5분) 동안 5번까지만 로그인 시도를 허용.
        // 초과하면 비밀번호 대조(무거운 연산)까지 가지 않고 바로 차단해서 무차별 대입 공격을 방어함.
        $throttler = service('throttler');

        // 캐시 키에는 @, /, \, :, (), {} 같은 특수문자를 쓸 수 없어서, 이메일을 그대로 쓰지 않고 해시로 변환.
        if ($throttler->check('login_' . md5((string) $email), 5, 300) === false) {
            return view('auth/login', ['error' => '로그인 시도가 너무 많습니다. 잠시 후 다시 시도해주세요.']);
        }

        $model = new UserModel();
        $user  = $model->where('email', $email)->first();

        if (! $user || ! password_verify($password, $user['password'])) {
            return view('auth/login', ['error' => '이메일 또는 비밀번호가 올바르지 않습니다.']);
        }

        $this->logInUser($user);

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/');
    }

    // 구글 로그인 버튼 -> 구글 인증 화면으로 이동
    public function googleLogin()
    {
        // state: 이 요청이 방금 우리가 시작한 게 맞는지 나중에 콜백에서 확인하기 위한 값
        // (없으면 남이 만든 인증 코드를 우리 콜백에 억지로 밀어넣는 공격에 취약해짐)
        $state = bin2hex(random_bytes(16));
        session()->set('google_oauth_state', $state);

        $params = http_build_query([
            'client_id'     => env('google.clientId'),
            'redirect_uri'  => env('google.redirectUri'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
        ]);

        return redirect()->to('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    // 구글에서 로그인 마치고 돌아오는 곳
    public function googleCallback()
    {
        $state = $this->request->getGet('state');

        if ($state === null || $state !== session()->get('google_oauth_state')) {
            return redirect()->to('/auth/login')->with('error', '로그인 요청이 올바르지 않습니다. 다시 시도해주세요.');
        }
        session()->remove('google_oauth_state');

        $code = $this->request->getGet('code');

        if ($code === null) {
            return redirect()->to('/auth/login')->with('error', '구글 로그인이 취소되었습니다.');
        }

        $client = service('curlrequest');

        // 구글 서버가 4xx/5xx로 응답하면 CURLRequest가 예외를 던진다(정상 반환이 아님).
        // 여기서 안 잡으면 사용자 화면에 디버그 에러(Whoops)가 그대로 노출되므로 반드시 감싼다.
        try {
            $tokenResponse = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'code'          => $code,
                    'client_id'     => env('google.clientId'),
                    'client_secret' => env('google.clientSecret'),
                    'redirect_uri'  => env('google.redirectUri'),
                    'grant_type'    => 'authorization_code',
                ],
            ]);

            $tokenData = json_decode($tokenResponse->getBody(), true);

            if (empty($tokenData['access_token'])) {
                return redirect()->to('/auth/login')->with('error', '구글 인증에 실패했습니다.');
            }

            $userInfoResponse = $client->get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
            ]);
        } catch (\Throwable $e) {
            log_message('error', '구글 로그인 통신 실패: ' . $e->getMessage());

            return redirect()->to('/auth/login')->with('error', '구글 인증에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }

        $googleUser = json_decode($userInfoResponse->getBody(), true);

        if (empty($googleUser['email'])) {
            return redirect()->to('/auth/login')->with('error', '구글 계정에서 이메일 정보를 가져오지 못했습니다.');
        }

        // 구글은 userinfo에 email_verified(true/false)를 같이 내려줌
        $user = $this->findOrCreateSocialUser('google_id', (string) $googleUser['sub'], $googleUser['email'], $googleUser['name'] ?? null, ! empty($googleUser['email_verified']));

        if (is_string($user)) {
            return redirect()->to('/auth/login')->with('error', $user);
        }

        $this->logInUser($user);

        return redirect()->to('/dashboard');
    }

    // 네이버 로그인 버튼 -> 네이버 인증 화면으로 이동
    public function naverLogin()
    {
        $state = bin2hex(random_bytes(16));
        session()->set('naver_oauth_state', $state);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => env('naver.clientId'),
            'redirect_uri'  => env('naver.redirectUri'),
            'state'         => $state,
        ]);

        return redirect()->to('https://nid.naver.com/oauth2.0/authorize?' . $params);
    }

    // 네이버에서 로그인 마치고 돌아오는 곳
    public function naverCallback()
    {
        $state = $this->request->getGet('state');

        if ($state === null || $state !== session()->get('naver_oauth_state')) {
            return redirect()->to('/auth/login')->with('error', '로그인 요청이 올바르지 않습니다. 다시 시도해주세요.');
        }
        session()->remove('naver_oauth_state');

        $code = $this->request->getGet('code');

        if ($code === null) {
            return redirect()->to('/auth/login')->with('error', '네이버 로그인이 취소되었습니다.');
        }

        $client = service('curlrequest');

        // 네이버 서버가 4xx/5xx로 응답하면 CURLRequest가 예외를 던진다 -> 반드시 감싸서 처리
        try {
            $tokenResponse = $client->get('https://nid.naver.com/oauth2.0/token', [
                'query' => [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => env('naver.clientId'),
                    'client_secret' => env('naver.clientSecret'),
                    'code'          => $code,
                    'state'         => $state,
                ],
            ]);

            $tokenData = json_decode($tokenResponse->getBody(), true);

            if (empty($tokenData['access_token'])) {
                return redirect()->to('/auth/login')->with('error', '네이버 인증에 실패했습니다.');
            }

            $userInfoResponse = $client->get('https://openapi.naver.com/v1/nid/me', [
                'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
            ]);
        } catch (\Throwable $e) {
            log_message('error', '네이버 로그인 통신 실패: ' . $e->getMessage());

            return redirect()->to('/auth/login')->with('error', '네이버 인증에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }

        // 네이버는 구글이랑 다르게, 실제 회원 정보가 response라는 키 안쪽에 한 번 더 감싸져서 옴
        // 예) { "resultcode": "00", "response": { "id": "...", "email": "...", "name": "..." } }
        $body      = json_decode($userInfoResponse->getBody(), true);
        $naverUser = $body['response'] ?? null;

        if (empty($naverUser['email'])) {
            return redirect()->to('/auth/login')->with('error', '네이버 계정에서 이메일 정보를 가져오지 못했습니다.');
        }

        // 네이버는 인증 여부 플래그를 따로 주지 않지만, 네이버 계정 이메일은 가입 시 네이버가 확인한 값이라 인증된 것으로 봄
        $user = $this->findOrCreateSocialUser('naver_id', (string) $naverUser['id'], $naverUser['email'], $naverUser['name'] ?? null, true);

        if (is_string($user)) {
            return redirect()->to('/auth/login')->with('error', $user);
        }

        $this->logInUser($user);

        return redirect()->to('/dashboard');
    }

    // 카카오 로그인 버튼 -> 카카오 인증 화면으로 이동
    public function kakaoLogin()
    {
        $state = bin2hex(random_bytes(16));
        session()->set('kakao_oauth_state', $state);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => env('kakao.clientId'),
            'redirect_uri'  => env('kakao.redirectUri'),
            'state'         => $state,
            // account_email: 이메일은 카카오에서 "선택 동의" 항목이라, scope로 명시하지 않으면
            // 동의 화면에 아예 안 나타나서 이메일이 계속 비어있는 상태로 넘어옴
            'scope'         => 'account_email',
        ]);

        return redirect()->to('https://kauth.kakao.com/oauth/authorize?' . $params);
    }

    // 카카오에서 로그인 마치고 돌아오는 곳
    public function kakaoCallback()
    {
        $state = $this->request->getGet('state');

        if ($state === null || $state !== session()->get('kakao_oauth_state')) {
            return redirect()->to('/auth/login')->with('error', '로그인 요청이 올바르지 않습니다. 다시 시도해주세요.');
        }
        session()->remove('kakao_oauth_state');

        $code = $this->request->getGet('code');

        if ($code === null) {
            return redirect()->to('/auth/login')->with('error', '카카오 로그인이 취소되었습니다.');
        }

        $client = service('curlrequest');

        $formParams = [
            'grant_type'   => 'authorization_code',
            'client_id'    => env('kakao.clientId'),
            'redirect_uri' => env('kakao.redirectUri'),
            'code'         => $code,
        ];

        // Client Secret은 카카오 콘솔에서 활성화했을 때만 존재. 없으면 아예 안 보냄
        // (활성화 안 했는데 빈 값이라도 보내면 요청이 거부될 수 있어서, 있을 때만 추가)
        if (! empty(env('kakao.clientSecret'))) {
            $formParams['client_secret'] = env('kakao.clientSecret');
        }

        // 카카오 서버가 4xx/5xx로 응답해도 예외를 던지지 말고(http_errors => false) 그대로 받아서,
        // 실패 시 카카오가 보내주는 진짜 이유(error/error_description)까지 로그에 남긴다.
        // (예전엔 예외를 던지게 둬서 "400 났다"는 사실만 알고 왜 났는지는 알 수 없었음)
        try {
            $tokenResponse = $client->post('https://kauth.kakao.com/oauth/token', [
                'form_params' => $formParams,
                'http_errors' => false,
            ]);

            if ($tokenResponse->getStatusCode() !== 200) {
                log_message('error', '카카오 토큰 교환 실패 (' . $tokenResponse->getStatusCode() . '): ' . $tokenResponse->getBody());

                return redirect()->to('/auth/login')->with('error', '카카오 인증에 실패했습니다. 잠시 후 다시 시도해주세요.');
            }

            $tokenData = json_decode($tokenResponse->getBody(), true);

            if (empty($tokenData['access_token'])) {
                return redirect()->to('/auth/login')->with('error', '카카오 인증에 실패했습니다.');
            }

            $userInfoResponse = $client->get('https://kapi.kakao.com/v2/user/me', [
                'headers'     => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
                'http_errors' => false,
            ]);

            if ($userInfoResponse->getStatusCode() !== 200) {
                log_message('error', '카카오 사용자 정보 조회 실패 (' . $userInfoResponse->getStatusCode() . '): ' . $userInfoResponse->getBody());

                return redirect()->to('/auth/login')->with('error', '카카오 인증에 실패했습니다. 잠시 후 다시 시도해주세요.');
            }
        } catch (\Throwable $e) {
            // http_errors => false로 4xx/5xx는 이제 위에서 걸러지므로, 여기 걸리는 건
            // 네트워크 자체가 끊기는 등 진짜 통신 실패일 때뿐임 — 안전망으로 남겨둠.
            log_message('error', '카카오 로그인 통신 실패: ' . $e->getMessage());

            return redirect()->to('/auth/login')->with('error', '카카오 인증에 실패했습니다. 잠시 후 다시 시도해주세요.');
        }

        // 카카오는 구글/네이버보다 한 단계 더 감싸져서 옴
        // { "id": 123, "kakao_account": { "email": "...", "profile": { "nickname": "..." } } }
        $body         = json_decode($userInfoResponse->getBody(), true);
        $kakaoId      = $body['id'] ?? null;
        $kakaoAccount = $body['kakao_account'] ?? [];
        $email        = $kakaoAccount['email'] ?? null;
        $nickname     = $kakaoAccount['profile']['nickname'] ?? null;

        if (empty($kakaoId) || empty($email)) {
            // 원인 구분용으로 "동의 필요 여부"만 남김 (이메일 등 개인정보는 로그에 남기지 않음)
            log_message('error', '카카오 이메일 획득 실패. email_needs_agreement='
                . var_export($kakaoAccount['email_needs_agreement'] ?? null, true));

            return redirect()->to('/auth/login')->with('error', '카카오 계정에서 이메일 정보를 가져오지 못했습니다. (앱 미심사 상태에서는 개발자 본인 계정만 이메일을 받을 수 있습니다)');
        }

        // 카카오는 이메일이 "유효한지(is_email_valid)"와 "인증됐는지(is_email_verified)"를 따로 알려줌 — 둘 다 true여야 믿음
        $emailVerified = ! empty($kakaoAccount['is_email_valid']) && ! empty($kakaoAccount['is_email_verified']);

        $user = $this->findOrCreateSocialUser('kakao_id', (string) $kakaoId, $email, $nickname, $emailVerified);

        if (is_string($user)) {
            return redirect()->to('/auth/login')->with('error', $user);
        }

        $this->logInUser($user);

        return redirect()->to('/dashboard');
    }

    // 소셜 로그인 공통: 소셜 ID로 회원을 찾고, 없으면 같은 이메일 계정에 연결하거나 새로 만든다.
    // 같은 이메일 계정에 자동 연결하는 건 "소셜 쪽에서 인증된 이메일"이고 "기존 계정도 소셜 로그인으로
    // 만들어진 계정"일 때만 허용함. 그렇지 않으면 공격자가 피해자 이메일로 먼저 비밀번호 가입을 해두고,
    // 나중에 피해자가 소셜 로그인할 때 그 계정을 그대로 넘겨받는 계정 탈취가 가능해짐.
    // 반환값: 로그인시킬 회원 배열, 또는 로그인 화면에 보여줄 오류 메시지(string)
    private function findOrCreateSocialUser(string $idColumn, string $socialId, string $email, ?string $name, bool $emailVerified): array|string
    {
        $model = new UserModel();

        $user = $model->where($idColumn, $socialId)->first();

        if ($user) {
            return $user;
        }

        if (! $emailVerified) {
            return '인증되지 않은 이메일이라 로그인할 수 없습니다. 소셜 계정에서 이메일 인증을 마친 뒤 다시 시도해주세요.';
        }

        $user = $model->where('email', $email)->first();

        if ($user) {
            $isSocialAccount = ! empty($user['google_id']) || ! empty($user['naver_id']) || ! empty($user['kakao_id']);

            if (! $isSocialAccount) {
                return '이미 이메일·비밀번호로 가입된 계정입니다. 이메일과 비밀번호로 로그인해주세요.';
            }

            $model->update($user['id'], [$idColumn => $socialId]);

            return $model->find($user['id']);
        }

        $newId = $model->insert([
            'email'    => $email,
            // 소셜로만 로그인할 계정이라 실제로 쓰이지 않는 임의 비밀번호를 해시로 저장
            // (password 컬럼이 NOT NULL이라 값은 필요하지만, 본인은 이 값을 알 필요가 없음)
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'name'     => $name,
            $idColumn  => $socialId,
        ]);

        return $model->find($newId);
    }

    private function logInUser(array $user): void
    {
        // 로그인 직전에 세션 ID를 새로 발급 — 남이 미리 심어둔 세션 ID가 그대로 로그인 상태가 되는 것(세션 고정) 방지
        session()->regenerate(true);

        session()->set([
            'user_id'    => $user['id'],
            'user_email' => $user['email'],
            'isLoggedIn' => true,
        ]);
    }
}
