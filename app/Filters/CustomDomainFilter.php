<?php

namespace App\Filters;

use App\Models\LinkModel;
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
// - 사용 가능한 커스텀 도메인(DNS 인증 + 유료 요금제)이면, 그 도메인 주인의 단축 링크와 QR 이미지만 허용
//   (예전엔 모든 주소가 열려서, 남의 도메인으로 다른 회원의 링크나 로그인/관리자 화면까지 열렸음)
// - 그 외(미등록/미인증 도메인, 무료로 내려간 회원의 도메인, 서버 IP로 직접 접속 등)는 안내 페이지만 보여줌
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

    // 우리 도메인으로 들어온 요청인지.
    // Host가 비어 있으면: 웹서버를 거치지 않은 요청(테스트 등)만 우리 도메인으로 간주.
    // 웹서버(nginx)를 거친 실제 요청인데 Host가 없는 건 HTTP/1.0 봇/스캐너뿐이라, 미등록 도메인과 똑같이 안내 페이지로 보냄.
    // (예전엔 통과시켜서, http 요청에 일반 404 화면이 Secure 쿠키를 보내려다 500 오류가 났음)
    public static function isOwnHost(RequestInterface $request): bool
    {
        $host = self::hostOf($request);

        if ($host === '') {
            return (string) $request->getServer('GATEWAY_INTERFACE') === '';
        }

        return $host === self::OWN_HOST;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if (self::isOwnHost($request)) {
            return;
        }

        $host = self::hostOf($request);

        $user = (new UserModel())->where('custom_domain', $host)->first();

        helper('link');

        if (custom_domain_active($user)) {
            // 커스텀 도메인은 아직 http로만 접속되는데, CI는 Secure 쿠키를 http로 보내려 하면 예외(500)를 던짐.
            // 그래서 이 요청에 한해서만 쿠키 Secure를 끔 (우리 도메인은 https라 그대로 Secure 유지).
            // 세션/CSRF는 이 필터 다음에 처음 만들어지므로, 여기서 바꾼 설정이 그대로 적용됨.
            // (아래 404 화면도 같은 응답이라, 404를 내기 전에 먼저 꺼둬야 함)
            $cookieConfig         = config(CookieConfig::class);
            $cookieConfig->secure = false;
            Cookie::setDefaults($cookieConfig);

            if (! self::isOwnersLinkPath($request, (int) $user['id'])) {
                return service('response')
                    ->setStatusCode(404)
                    ->setBody(view('errors/html/error_404'));
            }

            return;
        }

        return service('response')
            ->setStatusCode(404)
            ->setBody(view('custom_domain/not_connected', ['host' => $host]));
    }

    // 커스텀 도메인에서 허용하는 주소인지: "/{코드}"(링크 이동, 비밀번호 입력) 또는 "/qr/{코드}"(QR 이미지)이고,
    // 그 코드가 도메인 주인의 링크여야 함.
    private static function isOwnersLinkPath(RequestInterface $request, int $ownerId): bool
    {
        if (! preg_match('#^(?:qr/)?([A-Za-z0-9_-]+)$#', trim($request->getPath(), '/'), $m)) {
            return false;
        }

        return (new LinkModel())->where('short_code', $m[1])->where('user_id', $ownerId)->countAllResults() > 0;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
