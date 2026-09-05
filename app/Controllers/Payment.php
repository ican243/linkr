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

        $user         = (new UserModel())->find(session()->get('user_id'));
        $settingModel = new SettingModel();
        $amount       = (int) $settingModel->getValue(self::PLAN_PRICE_KEYS[$plan]);

        // 주문번호는 짧은 URL 코드와 다르게 "짧고 예측 불가능"할 필요는 없고,
        // 대신 절대 겹치면 안 되므로 충분히 긴 랜덤값을 씀 (32자리 hex, 사실상 중복 불가능).
        $orderId = 'king_' . bin2hex(random_bytes(16));

        (new PaymentModel())->insert([
            'user_id'  => $user['id'],
            'order_id' => $orderId,
            'amount'   => $amount,
            'plan'     => $plan,
            // status는 지정 안 하면 테이블 기본값 'pending'이 자동으로 들어감
        ]);

        return view('payment/checkout', [
            'orderId'      => $orderId,
            'amount'       => $amount,
            'planLabel'    => self::PLAN_LABELS[$plan],
            'customerName' => $user['name'] ?? $user['email'],
            'clientKey'    => env('tosspayments.clientKey'),
        ]);
    }

    public function success()
    {
        $paymentKey = $this->request->getGet('paymentKey');
        $orderId    = $this->request->getGet('orderId');
        $amount     = (int) $this->request->getGet('amount');

        $model   = new PaymentModel();
        $payment = $model->where('order_id', $orderId)->first();

        // ① 우리가 3단계에서 미리 저장해둔 주문 기록이 있는지, 금액이 일치하는지부터 확인.
        //    여기서 걸러야 "결제창 URL을 흉내내서 금액을 조작해 요청하는" 위변조를 막을 수 있음.
        if (! $payment || (int) $payment['amount'] !== $amount) {
            return redirect()->to('/payment/fail')->with('error', '결제 정보가 일치하지 않습니다.');
        }

        // ② 이미 완료 처리된 주문이면(새로고침 등으로 이 화면에 다시 들어온 경우),
        //    토스에 승인 요청을 또 보내지 않고 그냥 완료 화면만 다시 보여줌.
        if ($payment['status'] === 'completed') {
            return view('payment/success', ['payment' => $payment]);
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

            return redirect()->to('/payment/fail')->with('error', '결제 승인 중 오류가 발생했습니다.');
        }

        $result = json_decode($response->getBody(), true);

        // ④ 승인 성공 -> DB 갱신 + 회원 등급 업그레이드
        $model->update($payment['id'], [
            'status'      => 'completed',
            'payment_key' => $paymentKey,
            'method'      => $result['method'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        // 결제 당시(checkout) 저장해둔 요금제로 업그레이드. 예전엔 'pro'로 고정되어 있어서,
        // 엔터프라이즈를 결제해도 프로로 잘못 올라가는 문제가 있었음.
        (new UserModel())->update($payment['user_id'], ['plan' => $payment['plan']]);

        return view('payment/success', ['payment' => $payment]);
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
