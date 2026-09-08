<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

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

    public function before(RequestInterface $request, $arguments = null)
    {
        $host = $request->getHeaderLine('Host');

        if ($host === '') {
            $host = (string) $request->getServer('HTTP_HOST');
        }

        // 포트 번호 제거 (예: "kir1.cafe24.com:8443" -> "kir1.cafe24.com")
        $host = preg_replace('/:\d+$/', '', $host);

        // Host를 알 수 없는 경우(테스트 등 실제 브라우저 요청이 아닌 컨텍스트)는
        // 우리 도메인으로 간주하고 통과시킴 — 안전한 기본값.
        if ($host === '' || $host === self::OWN_HOST) {
            return;
        }

        $user = (new UserModel())->where('custom_domain', $host)->first();

        if ($user && $user['custom_domain_verified']) {
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
