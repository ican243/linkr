<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\UserModel;

class Pricing extends BaseController
{
    private const PLAN_ORDER = ['free' => 0, 'pro' => 1, 'enterprise' => 2];

    public function index()
    {
        $model = new SettingModel();
        $user  = null;

        if (session()->get('isLoggedIn')) {
            $user = (new UserModel())->find(session()->get('user_id'));
        }

        return view('pricing', [
            'freeLinkLimit'   => (int) $model->getValue('free_plan_link_limit', '100'),
            'proPrice'        => (int) $model->getValue('pro_plan_price', '33000'),
            'enterprisePrice' => (int) $model->getValue('enterprise_plan_price', '79800'),
            'freeExpiryDays'  => Link::FREE_PLAN_MAX_EXPIRY_DAYS,
            'user'            => $user,
        ]);
    }

    // 결제/환불 없이 "예약"만 함 — 지금 이용 중인 요금제의 만료 시각에 실제로 적용됨
    // (PlanTransitionFilter가 로그인 요청마다, 크론이 매시간 도달 여부를 확인해서 처리).
    // 자동 결제가 없어서 기간이 끝나면 무료로 돌아가므로, 예약할 수 있는 건 "무료로 전환" 하나뿐.
    // 하위 유료 요금제(엔터프라이즈 → 프로)는 지금 기간이 끝난 뒤 그 요금제를 새로 결제하면 됨.
    public function scheduleDowngrade()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $targetPlan = $this->request->getPost('plan');
        $userModel  = new UserModel();
        $user       = $userModel->find(session()->get('user_id'));

        if (! isset(self::PLAN_ORDER[$targetPlan])) {
            return redirect()->to('/pricing')->with('error', '올바르지 않은 요금제입니다.');
        }

        if (self::PLAN_ORDER[$targetPlan] >= self::PLAN_ORDER[$user['plan']]) {
            return redirect()->to('/pricing')->with('error', '지금 요금제보다 낮은 요금제만 예약할 수 있습니다.');
        }

        if ($targetPlan !== 'free') {
            return redirect()->to('/pricing')->with('error', '하위 유료 요금제는 지금 이용 기간이 끝난 뒤 새로 결제하시면 됩니다.');
        }

        if (empty($user['plan_expires_at'])) {
            return redirect()->to('/pricing')->with('error', '현재 이용기간 정보가 없어 예약할 수 없습니다.');
        }

        $userModel->update($user['id'], [
            'next_plan'    => $targetPlan,
            'next_plan_at' => $user['plan_expires_at'],
        ]);

        return redirect()->to('/pricing')->with('message', '이용 기간이 끝나면 무료로 전환되도록 예약했어요.');
    }

    public function cancelDowngrade()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        (new UserModel())->update(session()->get('user_id'), [
            'next_plan'    => null,
            'next_plan_at' => null,
        ]);

        return redirect()->to('/pricing')->with('message', '예약이 취소되었습니다.');
    }
}
