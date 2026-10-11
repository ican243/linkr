<?php

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Models\SettingModel;
use App\Models\UserModel;

class Payment extends BaseController
{
    // 요금제별 금액을 여기 저장된 settings 키에서 읽어온다. 예전엔 코드에 33000/79800이
    // 하드코딩되어 있었는데, 이제 관리자가 /admin/settings 화면에서 바꾸면 여기도 바로 반영됨.
    private const PLAN_PRICE_KEYS = [
        'pro'        => 'pro_plan_price',
        'enterprise' => 'enterprise_plan_price',
    ];

    private const PLAN_LABELS = [
        'pro'        => '프로',
        'enterprise' => '엔터프라이즈',
    ];

    // 요금제 등급 순서(다운그레이드/업그레이드 판단용). free < pro < enterprise.
    private const PLAN_ORDER = ['free' => 0, 'pro' => 1, 'enterprise' => 2];

    public function checkout()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        // pricing.php의 버튼이 ?plan=pro 또는 ?plan=enterprise로 넘겨줌. 지정 안 하면 프로로 취급.
        $plan = $this->request->getGet('plan') ?? 'pro';

        if (! isset(self::PLAN_PRICE_KEYS[$plan])) {
            return redirect()->to('/pricing')->with('error', '올바르지 않은 요금제입니다.');
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        // 만료된 유료 요금제는(자동으로 무료로 내려가는 로직은 아직 없어서 users.plan엔
        // 예전 값이 그대로 남아있을 수 있음) 여기서는 "사실상 무료"로 취급해서 판단함.
        $currentPlan = $this->isPlanActive($user) ? $user['plan'] : 'free';

        if ($plan === $currentPlan) {
            return redirect()->to('/pricing')->with('error', '이미 이용 중인 요금제입니다.');
        }

        if (self::PLAN_ORDER[$plan] < self::PLAN_ORDER[$currentPlan]) {
            return redirect()->to('/pricing')->with('error', '더 낮은 유료 요금제는 지금 이용 기간이 끝난 뒤 결제할 수 있어요.');
        }

        $settingModel = new SettingModel();
        $fullPrice    = (int) $settingModel->getValue(self::PLAN_PRICE_KEYS[$plan]);

        $proration = $this->calculateUpgradeAmount($user, $plan, $currentPlan, $fullPrice, $settingModel);

        $orderId = 'king_' . bin2hex(random_bytes(16));

        // 차액이 0원 이하로 계산되면(잔여 기간이 넉넉해서 상위 요금제 가격을 이미 다 채운 경우)
        // 토스 결제창을 띄울 필요가 없음 — 실제 결제 없이 바로 적용해준다.
        if ($proration['amount'] <= 0) {
            (new PaymentModel())->insert([
                'user_id'         => $user['id'],
                'order_id'        => $orderId,
                'amount'          => 0,
                'expected_amount' => 0,
                'plan'            => $plan,
                'type'            => $proration['type'],
                'change_note'     => $proration['note'],
                'status'          => 'completed',
                'method'          => 'proration_credit',
                'approved_at'     => date('Y-m-d H:i:s'),
            ]);

            (new UserModel())->update($user['id'], [
                'plan'            => $plan,
                'plan_expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'next_plan'       => null,
                'next_plan_at'    => null,
            ]);

            return redirect()->to('/payment/history')->with('message', '잔여 기간 차감으로 결제 없이 바로 적용되었습니다.');
        }

        (new PaymentModel())->insert([
            'user_id'         => $user['id'],
            'order_id'        => $orderId,
            'amount'          => $proration['amount'],
            'expected_amount' => $proration['amount'],
            'plan'            => $plan,
            'type'            => $proration['type'],
            'change_note'     => $proration['note'],
            // status는 지정 안 하면 테이블 기본값 'pending'이 자동으로 들어감
        ]);

        return view('payment/checkout', [
            'orderId'       => $orderId,
            'amount'        => $proration['amount'],
            'planLabel'     => self::PLAN_LABELS[$plan],
            'customerName'  => $user['name'] ?? $user['email'],
            'clientKey'     => env('tosspayments.clientKey'),
            'prorationNote' => $proration['note'],
        ]);
    }

    private function isPlanActive(array $user): bool
    {
        if ($user['plan'] === 'free') {
            return true;
        }

        return empty($user['plan_expires_at']) || strtotime($user['plan_expires_at']) > time();
    }

    // 업그레이드 결제금액을 계산한다.
    // - 현재 무료(또는 사실상 무료로 취급되는 만료 상태)면: 전액 결제. 이 요금제를 예전에
    //   결제한 적 있으면 '갱신', 처음이면 '신규'로 분류.
    // - 현재 유료(더 낮은 등급)면: 잔여일 비례 차액만 결제. '업그레이드'로 분류하고,
    //   계산 내역을 change_note에 사람이 읽을 수 있는 문장으로 남김.
    private function calculateUpgradeAmount(array $user, string $targetPlan, string $currentPlan, int $fullPrice, SettingModel $settingModel): array
    {
        if ($currentPlan === 'free') {
            $hadBefore = (new PaymentModel())
                ->where('user_id', $user['id'])
                ->where('plan', $targetPlan)
                ->where('status', 'completed')
                ->first();

            return [
                'amount' => $fullPrice,
                'type'   => $hadBefore ? 'renewal' : 'new',
                'note'   => null,
            ];
        }

        $currentPriceKey = self::PLAN_PRICE_KEYS[$currentPlan] ?? null;
        $currentPrice    = $currentPriceKey ? (int) $settingModel->getValue($currentPriceKey) : 0;

        $remainingDays = 0;
        if (! empty($user['plan_expires_at'])) {
            $remainingDays = max(0, (int) ceil((strtotime($user['plan_expires_at']) - time()) / 86400));
        }

        // 원 단위 내림. 관리자가 장기간(30일 초과)을 강제로 부여해둔 회원이 업그레이드하면
        // 차감액이 상위 요금제 가격보다 커질 수 있어서, 결제금액은 0원 아래로는 안 내려가게 막음.
        $deduction = (int) floor($currentPrice * $remainingDays / 30);
        $amount    = max(0, $fullPrice - $deduction);

        $currentLabel = self::PLAN_LABELS[$currentPlan] ?? $currentPlan;
        $note         = "{$currentLabel} 잔여 {$remainingDays}일(" . number_format($deduction) . '원) 차감, ' . number_format($amount) . '원 결제';

        return ['amount' => $amount, 'type' => 'upgrade', 'note' => $note];
    }

    public function success()
    {
        $paymentKey = $this->request->getGet('paymentKey');
        $orderId    = $this->request->getGet('orderId');
        $amount     = (int) $this->request->getGet('amount');

        $model   = new PaymentModel();
        $payment = $model->where('order_id', $orderId)->first();

        // ① 체크아웃 시점에 서버가 계산해서 저장해둔 expected_amount와, 토스가 지금 돌려준
        //    실제 결제금액이 일치하는지부터 확인. 여기서 걸러야 "결제창 URL을 흉내내서 금액을
        //    조작해 요청하는" 위변조를 막을 수 있음. (expected_amount가 없는 옛날 레코드는
        //    amount로 대체 비교 — 이 컬럼 생기기 전에 만들어진 pending 건 대비 안전장치)
        $expectedAmount = $payment !== null ? (int) ($payment['expected_amount'] ?? $payment['amount']) : null;

        if (! $payment || $expectedAmount !== $amount) {
            log_message('error', "결제 금액 불일치: orderId={$orderId}, expected=" . ($expectedAmount ?? 'null') . ", actual={$amount}");

            return redirect()->to('/payment/fail')->with('error', '결제 정보가 일치하지 않습니다.');
        }

        // ② 이미 완료 처리된 주문이면(새로고침 등으로 이 화면에 다시 들어온 경우),
        //    토스에 승인 요청을 또 보내지 않고 그냥 완료 화면만 다시 보여줌.
        if ($payment['status'] === 'completed') {
            return view('payment/success', ['payment' => $payment, 'planLabel' => self::PLAN_LABELS[$payment['plan']] ?? $payment['plan']]);
        }

        // ③ 토스 서버에 "진짜 승인해줘" 요청 (여기서만 시크릿 키 사용, 서버 대 서버 통신)
        try {
            $client   = service('curlrequest');
            $response = $client->post('https://api.tosspayments.com/v1/payments/confirm', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(env('tosspayments.secretKey') . ':'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'paymentKey' => $paymentKey,
                    'orderId'    => $orderId,
                    'amount'     => $amount,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', '토스 결제 승인 실패: ' . $e->getMessage());

            // orderId를 같이 넘겨야 fail()이 이 결제 건을 찾아서 'failed'로 표시할 수 있음.
            // 예전엔 orderId 없이 리다이렉트해서, 승인 실패한 결제가 DB에 계속 'pending'으로 남는 버그가 있었음.
            return redirect()->to('/payment/fail?orderId=' . urlencode($orderId))->with('error', '결제 승인 중 오류가 발생했습니다.');
        }

        $result = json_decode($response->getBody(), true);

        // ④ 승인 성공 -> DB 갱신 + 회원 등급 업그레이드
        // receipt.url은 토스가 발급해주는 실제 영수증 페이지 링크. 결제 내역 화면의
        // "영수증 보기" 버튼에 그대로 씀 (우리 쪽에서 영수증을 따로 만들 필요 없음).
        $model->update($payment['id'], [
            'status'        => 'completed',
            'payment_key'   => $paymentKey,
            'method'        => $result['method'] ?? null,
            'receipt_url'   => $result['receipt']['url'] ?? null,
            // 관리자 결제 상세 모달에서 원본 그대로 보여주는 용도 (문제 생겼을 때 디버깅용).
            'toss_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'approved_at'   => date('Y-m-d H:i:s'),
        ]);

        // 결제 당시(checkout) 저장해둔 요금제로 업그레이드. 예전엔 'pro'로 고정되어 있어서,
        // 엔터프라이즈를 결제해도 프로로 잘못 올라가는 문제가 있었음.
        // plan_expires_at은 "지금부터 1개월"로 기록 — 관리자가 강제로 부여할 때(개월수 입력)와
        // 계산 방식을 통일해서, 결제 내역 화면의 "다음 결제 예정일"이 항상 이 값 하나만 보면 되게 함.
        // 업그레이드(차액 결제) 건만 예외적으로 "지금부터 30일" — 차액 계산 자체가 30일 기준
        // (잔여일÷30)이라, 새로 시작하는 기간도 똑같이 30일로 맞춰야 계산이 앞뒤로 맞음.
        $newExpiresAt = $payment['type'] === 'upgrade'
            ? date('Y-m-d H:i:s', strtotime('+30 days'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));

        // 업그레이드 결제가 성공했으니, 혹시 남아있던 다운그레이드 예약은 의미가 없어져서 같이 지움.
        (new UserModel())->update($payment['user_id'], [
            'plan'            => $payment['plan'],
            'plan_expires_at' => $newExpiresAt,
            'next_plan'       => null,
            'next_plan_at'    => null,
        ]);

        return view('payment/success', ['payment' => $payment, 'planLabel' => self::PLAN_LABELS[$payment['plan']] ?? $payment['plan']]);
    }

    public function fail()
    {
        $orderId = $this->request->getGet('orderId');
        $message = $this->request->getGet('message') ?? '결제가 취소되었습니다.';

        $model   = new PaymentModel();
        $payment = $model->where('order_id', $orderId)->first();

        // 우리 DB에 남아있는 pending 기록을 failed로 정리 (완료된 결제는 절대 여기서 안 건드림)
        if ($payment && $payment['status'] === 'pending') {
            $model->update($payment['id'], ['status' => 'failed']);
        }

        return view('payment/fail', ['message' => $message]);
    }
}
