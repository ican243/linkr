<?php

namespace App\Filters;

use App\Libraries\DemoAccount;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

// 체험 계정은 여러 방문자가 같이 쓰는 계정이라, 다른 사람에게 영향을 주거나 악용될 수 있는 기능은 막는다.
// 어떤 주소에 이 필터가 걸리는지는 app/Config/Filters.php의 $filters['demoGuard'] 참고.
// - 화면 보기(GET)는 허용: 계정 설정/커스텀 도메인/API 키 화면이 어떻게 생겼는지는 볼 수 있음
// - 저장/변경(POST)은 차단: 비밀번호·이메일 변경, 커스텀 도메인 등록, API 키 발급, 요금제 변경
// - 결제 화면은 열기만 해도 주문이 만들어지므로 GET도 차단
class DemoGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! DemoAccount::isDemoSession()) {
            return;
        }

        // getPath(): 라우팅에 쓰이는 경로 (예: "account", "payment/checkout") — index.php 같은 접두어가 없음
        $path       = trim($request->getPath(), '/');
        $isPayment  = str_starts_with($path, 'payment/checkout') || str_starts_with($path, 'payment/success');
        $isWriteReq = strtoupper($request->getMethod()) === 'POST';

        if (! $isPayment && ! $isWriteReq) {
            return;
        }

        session()->setFlashdata('demo_blocked', '체험 계정에서는 이 기능을 사용할 수 없어요. 직접 써보시려면 내 계정을 만들어주세요.');

        return redirect()->to($isPayment ? '/pricing' : '/' . strtok($path, '/'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
