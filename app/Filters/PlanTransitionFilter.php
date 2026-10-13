<?php

namespace App\Filters;

use App\Libraries\PlanTransition;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

// 로그인한 회원이 요청을 보낼 때마다 회원 상태를 점검한다.
// - 유료 이용 기간이 끝났으면(또는 예약한 전환 시점이 지났으면) 그 자리에서 무료로 전환("조회 시점 처리")
// - 관리자가 회원을 삭제했거나 회원이 탈퇴했는데(다른 기기 등) 로그인 세션이 남아 있으면, 로그인 정보를 지워서 로그아웃 상태로 만듦
//   (안 지우면 각 화면이 "없는 회원"의 요금제를 읽으려다 500 오류가 남)
// 로그아웃 상태면 바로 통과하고, 로그인 상태여도 회원 1명 조회뿐이라 성능 부담은 거의 없음.
class PlanTransitionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = session()->get('user_id');

        if (! $userId) {
            return;
        }

        $user = (new UserModel())->find($userId);

        if ($user === null || ! empty($user['withdrawn_at'])) {
            session()->remove(['user_id', 'user_email', 'isLoggedIn', 'is_demo']);

            return;
        }

        PlanTransition::applyIfDue($user);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
