<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cookie as CookieConfig;

// nginx 80포트의 default_server가 우리 도메인(kir1.cafe24.com)이 아닌 모든 Host를
// 이 앱으로 그대로 넘겨주기 때문에(커스텀 도메인 기능을 위해), 여기서 그 Host가
// 인증 완료된 커스텀 도메인이 맞는지 확인해서 걸러준다.
// - 우리 도메인(kir1.cafe24.com)이면 그냥 통과 (평소처럼 동작)
// - 인증된 커스텀 도메인이면 통과 (이후 Link::redirect 등 기존 라우팅이 처리 —
//   short_code는 전체에서 유일하므로 별도 처리 없이도 그 회원의 링크가 그대로 찾아짐)
// - 그 외(미등록/미인증 도메인, 서버 IP로 직접 접속 등)는 안내 페이지만 보여줌
class CustomDomainFilter implements FilterInterface
{
    private const OWN_HOST = 'kir1.cafe24.com';

    // 요청 Host에서 포트 번호를 뗀 값 (예: "kir1.cafe24.com:8443" -> "kir1.cafe24.com")
    private static function hostOf(RequestInterface $request): string
    {
        $host = $request->getHeaderLine('Host');

        if ($host === '') {
            $host = (string) $request->getServer('HTTP_HOST');
        }

        return preg_replace('/:\d+$/', '', $host);
    }

    // 우리 도메인으로 들어온 요청인지. Host를 알 수 없는 경우(테스트 등 실제 브라우저 요청이 아닌
    // 컨텍스트)는 우리 도메인으로 간주 — 안전한 기본값.
    public static function isOwnHost(RequestInterface $request): bool
    {
        $host = self::hostOf($request);

        return $host === '' || $host === self::OWN_HOST;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if (self::isOwnHost($request)) {
            return;
        }

        $host = self::hostOf($request);

        $user = (new UserModel())->where('custom_domain', $host)->first();

        if ($user && $user['custom_domain_verified']) {
            // 커스텀 도메인은 아직 http로만 접속되는데, CI는 Secure 쿠키를 http로 보내려 하면 예외(500)를 던짐.
            // 그래서 이 요청에 한해서만 쿠키 Secure를 끔 (우리 도메인은 https라 그대로 Secure 유지).
            // 세션/CSRF는 이 필터 다음에 처음 만들어지므로, 여기서 바꾼 설정이 그대로 적용됨.
            $cookieConfig         = config(CookieConfig::class);
            $cookieConfig->secure = false;
            Cookie::setDefaults($cookieConfig);

            return;
        }

        return service('response')
            ->setStatusCode(404)
            ->setBody(view('custom_domain/not_connected', ['host' => $host]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
