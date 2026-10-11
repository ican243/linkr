<?php

namespace App\Filters;

use App\Libraries\PlanTransition;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

// 로그인한 회원이 요청을 보낼 때마다, 예약해둔 다운그레이드(users.next_plan_at)가
// 도달했으면 그 자리에서 바로 적용해준다("조회 시점 처리"). 로그아웃 상태거나 예약이
// 없으면 DB 조회 자체가 거의 공짜라 성능 부담은 무시할 만한 수준.
class PlanTransitionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = session()->get('user_id');

        if (! $userId) {
            return;
        }

        $user = (new UserModel())->find($userId);

        if ($user !== null) {
            PlanTransition::applyIfDue($user);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
