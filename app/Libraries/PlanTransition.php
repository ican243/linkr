<?php

namespace App\Libraries;

use App\Models\UserModel;

// 다운그레이드 예약(users.next_plan/next_plan_at)이 도달했으면 실제로 적용해준다.
// 두 곳에서 똑같이 써야 해서(로그인 요청마다 확인하는 필터 + 수동/크론으로 돌리는 spark 커맨드)
// 로직을 여기 한 곳에만 둠.
class PlanTransition
{
    // 예약이 지금 시점에 적용 대상이면 적용하고 true, 아니면(예약 자체가 없거나 아직 안 됐으면) false.
    public static function applyIfDue(array $user): bool
    {
        if (empty($user['next_plan']) || empty($user['next_plan_at'])) {
            return false;
        }

        if (strtotime($user['next_plan_at']) > time()) {
            return false;
        }

        // 결제/환불 없이 예약만 해뒀던 거라, 적용 시점엔 새로 시작하는 이용기간에 대한
        // 실제 결제 기록이 없음 — plan_expires_at은 "결제로 보장된 기간이 없다"는 뜻으로 비워둠
        // (무료로 내려가는 경우든, 더 낮은 유료 등급으로 내려가는 경우든 동일하게 처리).
        (new UserModel())->update($user['id'], [
            'plan'            => $user['next_plan'],
            'plan_expires_at' => null,
            'next_plan'       => null,
            'next_plan_at'    => null,
        ]);

        return true;
    }

    // 지금 기준으로 예약 적용 대상인 회원을 전부 찾아서 적용. spark 커맨드에서 씀.
    // 반환값은 적용된 회원 수.
    public static function applyAllDue(): int
    {
        $userModel = new UserModel();

        $due = $userModel
            ->where('next_plan_at IS NOT NULL')
            ->where('next_plan_at <=', date('Y-m-d H:i:s'))
            ->findAll();

        $count = 0;
        foreach ($due as $user) {
            if (self::applyIfDue($user)) {
                $count++;
            }
        }

        return $count;
    }
}
