<?php

namespace App\Filters;

use CodeIgniter\Filters\SecureHeaders;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

// CI 기본 SecureHeaders(클릭재킹 방지 X-Frame-Options, MIME 스니핑 방지 등)에 두 가지만 바꿔서 씀.
// - Referrer-Policy: 기본값 same-origin은 외부 사이트(토스 결제창, 소셜 로그인 등)로 갈 때 출처를
//   아예 안 보내서 연동이 깨질 수 있음 → 브라우저 표준 기본값(다른 사이트엔 도메인까지만 전달)으로.
// - HSTS: https로 들어온 요청에만 붙임. 커스텀 도메인은 아직 http로만 서비스되므로 거기엔 붙이지 않음
//   (forceGlobalSecureRequests를 쓰면 커스텀 도메인까지 https로 강제 이동돼서 접속이 깨짐).
class SecureHeadersFilter extends SecureHeaders
{
    protected $headers = [
        'X-Frame-Options'                   => 'SAMEORIGIN',
        'X-Content-Type-Options'            => 'nosniff',
        'X-Download-Options'                => 'noopen',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
    ];

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        parent::after($request, $response, $arguments);

        if ($request instanceof IncomingRequest && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
