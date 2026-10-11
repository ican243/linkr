<?php

namespace App\Libraries;

use App\Models\UserModel;

// 유료 요금제의 이용 기간이 끝났는지 확인해서, 끝났으면 무료로 전환한다.
// 세 곳에서 똑같이 써야 해서 로직을 여기 한 곳에만 둠:
// - PlanTransitionFilter: 로그인한 회원이 요청을 보낼 때마다
// - Api\Links: API 키로 호출할 때마다 (API는 로그인 세션이 없어서 필터가 안 돎)
// - spark plan:apply-downgrades: 크론으로 매시간 (한동안 접속 안 한 회원까지)
//
// 자동 결제(정기 결제) 기능이 없으므로, 유료 기간이 끝나면 항상 무료로 돌아가고 원하는 요금제를
// 새로 결제하는 방식임. "다운그레이드 예약"은 "이번 기간이 끝나면 무료로 전환"을 미리 알려두는 것.
class PlanTransition
{
    // 전환 대상이면 무료로 바꾸고 true, 아니면 false.
    public static function applyIfDue(array $user): bool
    {
        if (! self::isDue($user)) {
            return false;
        }

        // 커스텀 도메인 정보(custom_domain, 인증 여부)는 지우지 않고 남겨둠 — 무료인 동안은
        // CustomDomainFilter/short_url()이 요금제를 보고 사용을 막고, 다시 유료로 올리면 바로 다시 쓸 수 있음.
        (new UserModel())->update($user['id'], [
            'plan'            => 'free',
            'plan_expires_at' => null,
            'next_plan'       => null,
            'next_plan_at'    => null,
        ]);

        return true;
    }

    // 무료로 전환해야 하는 상태인지
    // ① 유료 요금제인데 이용 기간(plan_expires_at)이 지남
    //    (기간이 비어있는 유료 회원 = 기간 제한 없이 부여된 계정, 예: 체험 계정 → 만료 없음)
    // ② 예약해둔 전환 시점(next_plan_at)이 지남 (예전 버전에서 유료 하위 요금제로 예약해둔 경우 포함)
    public static function isDue(array $user): bool
    {
        $now = time();

        $expired = $user['plan'] !== 'free'
            && ! empty($user['plan_expires_at'])
            && strtotime($user['plan_expires_at']) <= $now;

        $scheduled = ! empty($user['next_plan'])
            && ! empty($user['next_plan_at'])
            && strtotime($user['next_plan_at']) <= $now;

        return $expired || $scheduled;
    }

    // 지금 기준으로 전환 대상인 회원을 전부 찾아서 적용. spark 커맨드(크론)에서 씀.
    // 반환값은 무료로 전환된 회원 수.
    public static function applyAllDue(): int
    {
        $now = date('Y-m-d H:i:s');

        $candidates = (new UserModel())
            ->groupStart()
                ->where('plan !=', 'free')
                ->where('plan_expires_at <=', $now)
            ->groupEnd()
            ->orGroupStart()
                ->where('next_plan_at IS NOT NULL')
                ->where('next_plan_at <=', $now)
            ->groupEnd()
            ->findAll();

        $count = 0;
        foreach ($candidates as $user) {
            if (self::applyIfDue($user)) {
                $count++;
            }
        }

        return $count;
    }
}
