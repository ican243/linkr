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

        $model = new UserModel();

        $user = $model->where('google_id', $googleUser['sub'])->first();

        if (! $user) {
            // 이미 이메일/비밀번호로 가입된 계정이면 구글 계정만 연결, 없으면 새로 생성
            $user = $model->where('email', $googleUser['email'])->first();

            if ($user) {
                $model->update($user['id'], ['google_id' => $googleUser['sub']]);
            } else {
                $newId = $model->insert([
                    'email'     => $googleUser['email'],
                    // 구글로만 로그인할 계정이라 실제로 쓰이지 않는 임의 비밀번호를 해시로 저장
                    // (password 컬럼이 NOT NULL이라 값은 필요하지만, 본인은 이 값을 알 필요가 없음)
                    'password'  => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'name'      => $googleUser['name'] ?? null,
                    'google_id' => $googleUser['sub'],
                ]);
                $user = $model->find($newId);
            }
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

        $model = new UserModel();

        $user = $model->where('naver_id', $naverUser['id'])->first();

        if (! $user) {
            $user = $model->where('email', $naverUser['email'])->first();

            if ($user) {
                $model->update($user['id'], ['naver_id' => $naverUser['id']]);
            } else {
                $newId = $model->insert([
                    'email'    => $naverUser['email'],
                    'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'name'     => $naverUser['name'] ?? null,
                    'naver_id' => $naverUser['id'],
                ]);
                $user = $model->find($newId);
            }
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
            // 진단용 로그(임시): state 불일치가 "세션이 아예 안 이어진 것"인지
            // "다른 값이 들어있는 것"인지 구분하기 위해 실제 값들을 남긴다.
            log_message('error', '카카오 state 불일치. 세션ID=' . session_id()
                . ', 콜백으로 받은 state=' . ($state ?? 'null')
                . ', 세션에 저장된 state=' . (session()->get('kakao_oauth_state') ?? 'null (세션이 비어있음)'));

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
            // 진짜 원인 진단용 로그. kakao_account 안의 email_needs_agreement(동의 안 함),
            // is_email_valid/is_email_verified 같은 세부 플래그를 남겨서, 다음 시도 때
            // "동의를 안 눌렀는지" vs "애초에 항목이 안 떴는지"를 로그만 보고 구분할 수 있게 함.
            log_message('error', '카카오 이메일 획득 실패. kakao_id=' . ($kakaoId ?? 'null')
                . ', kakao_account 원본=' . json_encode($kakaoAccount, JSON_UNESCAPED_UNICODE));

            return redirect()->to('/auth/login')->with('error', '카카오 계정에서 이메일 정보를 가져오지 못했습니다. (앱 미심사 상태에서는 개발자 본인 계정만 이메일을 받을 수 있습니다)');
        }

        $model = new UserModel();

        $user = $model->where('kakao_id', $kakaoId)->first();

        if (! $user) {
            $user = $model->where('email', $email)->first();

            if ($user) {
                $model->update($user['id'], ['kakao_id' => $kakaoId]);
            } else {
                $newId = $model->insert([
                    'email'    => $email,
                    'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    'name'     => $nickname,
                    'kakao_id' => $kakaoId,
                ]);
                $user = $model->find($newId);
            }
        }

        $this->logInUser($user);

        return redirect()->to('/dashboard');
    }

    private function logInUser(array $user): void
    {
        session()->set([
            'user_id'    => $user['id'],
            'user_email' => $user['email'],
            'isLoggedIn' => true,
        ]);
    }
}
