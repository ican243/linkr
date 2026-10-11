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
            'user'            => $user,
        ]);
    }

    // 결제/환불 없이 "예약"만 함 — 지금 이용 중인 요금제의 만료 시각에 실제로 적용됨
    // (PlanTransitionFilter가 로그인 요청마다 도달 여부를 확인해서 처리).
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

        if (empty($user['plan_expires_at'])) {
            return redirect()->to('/pricing')->with('error', '현재 이용기간 정보가 없어 예약할 수 없습니다.');
        }

        $userModel->update($user['id'], [
            'next_plan'    => $targetPlan,
            'next_plan_at' => $user['plan_expires_at'],
        ]);

        return redirect()->to('/pricing')->with('message', '요금제 다운그레이드가 예약되었습니다.');
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
