<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\PaymentModel;
use App\Models\UserModel;

class Payments extends BaseController
{
    // pricing.php/checkout.php에서도 쓰는 것과 같은 한글 요금제 이름. 화면 여러 곳에서
    // 필요해질 때마다 다시 만들지 않도록 이 컨트롤러 안에 하나로 모아둠.
    private const PLAN_LABELS = [
        'free'       => '무료',
        'pro'        => '프로',
        'enterprise' => '엔터프라이즈',
    ];

    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $userId = session()->get('user_id');
        $user   = (new UserModel())->find($userId);

        $model = (new PaymentModel())
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC');

        $payments = $model->paginate(10);
        $pager    = $model->pager;

        return view('user/payments', [
            'payments'        => $payments,
            'pager'           => $pager,
            'currentPlan'     => $user['plan'],
            'planLabels'      => self::PLAN_LABELS,
            'nextBillingDate' => $this->nextBillingDate($userId, $user['plan']),
        ]);
    }

    // "다음 결제 예정일"을 별도로 저장해두지 않고, 가장 최근 완료된 결제일 + 1개월로 계산함.
    // 지금은 자동 정기결제가 아니라 매달 수동으로 다시 결제하는 구조라 이렇게 어림잡는 것.
    // 무료 요금제는 결제 자체가 없으므로 null을 반환.
    private function nextBillingDate(int $userId, string $plan): ?string
    {
        if ($plan === 'free') {
            return null;
        }

        $latestCompleted = (new PaymentModel())
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('approved_at', 'DESC')
            ->first();

        if (! $latestCompleted || empty($latestCompleted['approved_at'])) {
            return null;
        }

        return date('Y-m-d', strtotime($latestCompleted['approved_at'] . ' +1 month'));
    }
}
