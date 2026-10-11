<?php

use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 요금제 변경 정책: 같은 플랜 재결제 차단, 업그레이드 잔여일 비례 차액, 다운그레이드 예약+자동전환,
// 콜백 금액 위변조 방지(expected_amount).
final class PlanChangeTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUser(string $email, string $plan = 'free', ?string $planExpiresAt = null): int
    {
        return (new UserModel())->insert([
            'email'           => $email,
            'password'        => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'            => 'Plan Change Test',
            'plan'            => $plan,
            'plan_expires_at' => $planExpiresAt,
        ]);
    }

    // 1. 같은 요금제 재결제 차단
    public function testCannotCheckoutSamePlanWhileActive(): void
    {
        $userId = $this->createUser('samecheckout@example.com', 'pro', date('Y-m-d H:i:s', strtotime('+15 days')));

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=pro');

        $result->assertRedirectTo('/pricing');
        $result->assertSessionHas('error', '이미 이용 중인 요금제입니다.');
    }

    // 만료된 플랜은 "사실상 무료" 취급이라 같은 플랜 재결제가 막히면 안 됨(과거에 이 플랜을
    // 결제한 적이 있으므로 '갱신'으로 분류되어야 함).
    public function testCanRebuyExpiredPlan(): void
    {
        $userId = $this->createUser('expiredrebuy@example.com', 'pro', date('Y-m-d H:i:s', strtotime('-1 day')));
        (new PaymentModel())->insert([
            'user_id'     => $userId,
            'order_id'    => 'king_prior_pro_payment',
            'amount'      => 33000,
            'plan'        => 'pro',
            'status'      => 'completed',
            'method'      => '카드',
            'approved_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=pro');

        $result->assertOK();

        $payment = (new PaymentModel())->where('user_id', $userId)->orderBy('id', 'DESC')->first();
        $this->assertNotNull($payment);
        $this->assertSame('renewal', $payment['type']);
    }

    // 2. 업그레이드 — 프로(33,000원) 15일 남은 상태에서 엔터프라이즈(79,800원)로: 63,300원
    public function testUpgradeAmountIsProratedCorrectly(): void
    {
        $userId = $this->createUser('upgrade63300@example.com', 'pro', date('Y-m-d H:i:s', strtotime('+15 days')));

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=enterprise');

        $result->assertOK();
        $result->assertSee('63,300');
        $result->assertSee('프로 잔여 15일');

        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertNotNull($payment);
        $this->assertSame(63300, (int) $payment['amount']);
        $this->assertSame(63300, (int) $payment['expected_amount']);
        $this->assertSame('upgrade', $payment['type']);
        $this->assertStringContainsString('16,500', $payment['change_note']);
    }

    // 잔여일이 30일을 넘는 경우(관리자 장기부여 등) 차감액이 상위 요금제 가격보다 커질 수 있는데,
    // 0원 아래로는 안 내려가고 결제 없이 바로 적용돼야 함.
    public function testUpgradeAmountClampsToZeroWhenDeductionExceedsPrice(): void
    {
        $userId = $this->createUser('clampzero@example.com', 'pro', date('Y-m-d H:i:s', strtotime('+90 days')));

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/payment/checkout?plan=enterprise');

        $result->assertRedirectTo('/payment/history');
        $result->assertSessionHas('message');

        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']);
        $this->assertNotNull($user['plan_expires_at']);

        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertSame(0, (int) $payment['amount']);
        $this->assertSame('completed', $payment['status']);
        $this->assertSame('proration_credit', $payment['method']);
    }

    // 무료에서 업그레이드는 전액 결제 + '신규' 분류
    public function testFreeToProCheckoutChargesFullPriceAndIsNew(): void
    {
        $userId = $this->createUser('freetopronew@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/payment/checkout?plan=pro');

        $result->assertOK();

        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertSame(33000, (int) $payment['amount']);
        $this->assertSame('new', $payment['type']);
    }

    // 더 낮은 요금제로는 checkout()이 아니라 다운그레이드 예약을 쓰라고 안내해야 함
    public function testCheckoutRejectsDowngradeDirection(): void
    {
        $userId = $this->createUser('checkoutdowngrade@example.com', 'enterprise', date('Y-m-d H:i:s', strtotime('+10 days')));

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/payment/checkout?plan=pro');

        $result->assertRedirectTo('/pricing');
        $result->assertSessionHas('error', '더 낮은 요금제는 다운그레이드 예약을 이용해주세요.');
    }

    // 3. 다운그레이드 예약 후 만료일에 전환
    public function testDowngradeIsScheduledNotAppliedImmediately(): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 days'));
        $userId    = $this->createUser('scheduledowngrade@example.com', 'enterprise', $expiresAt);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/pricing/downgrade', [csrf_token() => csrf_hash(), 'plan' => 'pro']);

        $result->assertRedirectTo('/pricing');

        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']); // 아직 안 바뀜
        $this->assertSame('pro', $user['next_plan']);
        $this->assertSame($expiresAt, $user['next_plan_at']);
    }

    public function testDowngradeAppliesAutomaticallyOncePastAt(): void
    {
        $userId = $this->createUser('autotransition@example.com', 'enterprise', null);
        (new UserModel())->update($userId, [
            'next_plan'    => 'pro',
            'next_plan_at' => date('Y-m-d H:i:s', strtotime('-1 minute')), // 이미 지난 시각
        ]);

        // 로그인한 회원이 아무 페이지나 접속하면 PlanTransitionFilter가 적용해줌
        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/dashboard');

        $result->assertOK();

        $user = (new UserModel())->find($userId);
        $this->assertSame('pro', $user['plan']);
        $this->assertNull($user['next_plan']);
        $this->assertNull($user['next_plan_at']);
    }

    public function testCanCancelScheduledDowngrade(): void
    {
        $userId = $this->createUser('canceldowngrade@example.com', 'enterprise', date('Y-m-d H:i:s', strtotime('+10 days')));
        (new UserModel())->update($userId, ['next_plan' => 'pro', 'next_plan_at' => date('Y-m-d H:i:s', strtotime('+10 days'))]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/pricing/cancel-downgrade', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/pricing');

        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']);
        $this->assertNull($user['next_plan']);
        $this->assertNull($user['next_plan_at']);
    }

    // 4. 콜백 금액 불일치 시 실패 (expected_amount 기준)
    public function testCallbackAmountMismatchFailsAndOrderStaysPending(): void
    {
        $userId = $this->createUser('mismatch@example.com', 'free');

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/payment/checkout?plan=pro');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertSame(33000, (int) $payment['expected_amount']);

        // 위변조 시도: 실제 예상 금액과 다른 금액으로 콜백 호출
        $result = $this->get('/payment/success?paymentKey=fake&orderId=' . $payment['order_id'] . '&amount=1');

        $result->assertRedirectTo('/payment/fail');

        $unchanged = (new PaymentModel())->find($payment['id']);
        $this->assertSame('pending', $unchanged['status']);
    }
}
