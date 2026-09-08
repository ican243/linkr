<?php

use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PaymentTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUser(): int
    {
        return (new UserModel())->insert([
            'email'    => 'paymenttest@example.com',
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Payment Test',
        ]);
    }

    // 9단계 전에 실제로 있었던 버그(문자열 "33000" vs 정수 33000 비교 오류) 재발 방지용.
    public function testTamperedAmountIsRejectedAndOrderStaysPending(): void
    {
        $userId  = $this->createUser();
        $orderId = 'king_test_tamper';

        (new PaymentModel())->insert([
            'user_id'  => $userId,
            'order_id' => $orderId,
            'amount'   => 33000,
            'plan'     => 'pro',
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/success?paymentKey=fake&orderId=' . $orderId . '&amount=1');

        $result->assertRedirectTo('/payment/fail');

        $payment = (new PaymentModel())->where('order_id', $orderId)->first();
        $this->assertSame('pending', $payment['status']);
    }

    // 이미 완료된 주문으로 다시 접속했을 때(새로고침 등) 에러 없이 완료 화면이 재표시되는지.
    public function testAlreadyCompletedOrderShowsSuccessWithoutReapproving(): void
    {
        $userId  = $this->createUser();
        $orderId = 'king_test_completed';

        (new PaymentModel())->insert([
            'user_id'     => $userId,
            'order_id'    => $orderId,
            'amount'      => 33000,
            'plan'        => 'pro',
            'status'      => 'completed',
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/success?paymentKey=whatever&orderId=' . $orderId . '&amount=33000');

        $result->assertOK();
        $result->assertSee('결제 완료');
    }

    // 엔터프라이즈 요금제로 결제를 시작하면, pricing.php에 적힌 79,800원 그대로 주문이 생성되는지
    // (API 액세스 요금제 불일치 수정 작업 중, 엔터프라이즈는 결제 자체가 안 되던 문제를 고치면서 추가).
    public function testCheckoutCreatesPendingOrderForEnterprisePlan(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=enterprise');

        $result->assertOK();
        $result->assertSee('엔터프라이즈');
        $result->assertSee('79,800');

        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertSame('enterprise', $payment['plan']);
        $this->assertSame(79800, (int) $payment['amount']);
        $this->assertSame('pending', $payment['status']);
    }

    // plan 파라미터를 조작해서 없는 요금제로 결제를 시도하면 막혀야 함.
    public function testCheckoutRejectsUnknownPlan(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=super-vip');

        $result->assertRedirectTo('/pricing');
        $this->assertSame(0, (new PaymentModel())->where('user_id', $userId)->countAllResults());
    }

    // receipt_url 컬럼/모델 연결 확인용. 토스 실제 승인(Payment::success()의 ④번)이
    // 진짜 네트워크 호출이라 자동화 테스트에서 그 경로 자체는 못 타지만(기존 테스트들도 회피),
    // 그 경로가 저장하는 값의 형태(컬럼에 URL을 저장/조회)는 여기서 검증함.
    public function testReceiptUrlCanBeStoredAndRetrieved(): void
    {
        $userId  = $this->createUser();
        $orderId = 'king_test_receipt';
        $receiptUrl = 'https://dashboard.tosspayments.com/receipt/test123';

        (new PaymentModel())->insert([
            'user_id'     => $userId,
            'order_id'    => $orderId,
            'amount'      => 33000,
            'plan'        => 'pro',
            'status'      => 'completed',
            'method'      => '카드',
            'receipt_url' => $receiptUrl,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        $payment = (new PaymentModel())->where('order_id', $orderId)->first();
        $this->assertSame($receiptUrl, $payment['receipt_url']);
    }
}
