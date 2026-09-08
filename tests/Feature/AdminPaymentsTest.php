<?php

use App\Models\AdminLogModel;
use App\Models\AdminModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdminPaymentsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUserWithPayment(string $email, string $status, string $plan = 'pro', int $amount = 33000): int
    {
        $userId = (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Admin Payments Test',
            'plan'     => $status === 'completed' ? $plan : 'free',
        ]);

        (new PaymentModel())->insert([
            'user_id'     => $userId,
            'order_id'    => 'king_admintest_' . $userId,
            'amount'      => $amount,
            'plan'        => $plan,
            'status'      => $status,
            'method'      => $status === 'completed' ? '카드' : null,
            'approved_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
        ]);

        return $userId;
    }

    private function createAdmin(): int
    {
        return (new AdminModel())->insert([
            'email'    => 'admintest@example.com',
            'password' => password_hash('AdminTest1234!', PASSWORD_DEFAULT),
            'name'     => 'Admin Payments Test Admin',
        ]);
    }

    public function testNonAdminCannotReachPaymentsPage(): void
    {
        $result = $this->get('/admin/payments');

        $result->assertRedirectTo('/');
    }

    public function testAdminSeesPaymentsWithEmailAndSummary(): void
    {
        $this->createUserWithPayment('paid1@example.com', 'completed');
        $this->createUserWithPayment('paid2@example.com', 'pending');

        $result = $this->withSession(['isAdminLoggedIn' => true])->get('/admin/payments');

        $result->assertOK();
        $result->assertSee('paid1@example.com');
        $result->assertSee('paid2@example.com');
        $result->assertSee('33,000원'); // 이번 달 매출(완료 1건)
        $result->assertSee('1건'); // 대기중 건수
    }

    public function testStatusFilterShowsOnlyMatchingRows(): void
    {
        $this->createUserWithPayment('completed-user@example.com', 'completed');
        $this->createUserWithPayment('failed-user@example.com', 'failed');

        $result = $this->withSession(['isAdminLoggedIn' => true])->get('/admin/payments?status=failed');

        $result->assertOK();
        $result->assertSee('failed-user@example.com');
        $result->assertDontSee('completed-user@example.com');
    }

    public function testEmailFilterNarrowsResults(): void
    {
        $this->createUserWithPayment('findme@example.com', 'completed');
        $this->createUserWithPayment('other@example.com', 'completed');

        $result = $this->withSession(['isAdminLoggedIn' => true])->get('/admin/payments?email=findme');

        $result->assertOK();
        $result->assertSee('findme@example.com');
        $result->assertDontSee('other@example.com');
    }

    public function testAdminCanChangePaymentStatusAndLogsIt(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('statuschange@example.com', 'pending');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/status", [
                csrf_token() => csrf_hash(),
                'status'     => 'completed',
            ]);

        $result->assertRedirectTo('/admin/payments');
        $result->assertSessionHas('message');

        $updated = (new PaymentModel())->find($payment['id']);
        $this->assertSame('completed', $updated['status']);
        $this->assertSame($adminId, (int) $updated['admin_id']);

        $log = (new AdminLogModel())->where('target_type', 'payment')->where('target_id', $payment['id'])->first();
        $this->assertNotNull($log);
        $this->assertSame('status_change', $log['action']);
    }

    public function testInvalidStatusValueIsRejected(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('badstatus@example.com', 'pending');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/status", [
                csrf_token() => csrf_hash(),
                'status'     => 'super-refunded',
            ]);

        $result->assertSessionHas('error');

        $unchanged = (new PaymentModel())->find($payment['id']);
        $this->assertSame('pending', $unchanged['status']);
    }

    public function testAdminCanSaveMemo(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('memotest@example.com', 'completed');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/memo", [
                csrf_token()  => csrf_hash(),
                'admin_memo'  => '고객이 이메일로 결제 확인 요청함',
            ]);

        $result->assertRedirectTo('/admin/payments');

        $updated = (new PaymentModel())->find($payment['id']);
        $this->assertSame('고객이 이메일로 결제 확인 요청함', $updated['admin_memo']);
    }

    public function testRefundRequiresReason(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('norefundreason@example.com', 'completed');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/refund", [
                csrf_token()     => csrf_hash(),
                'cancel_reason'  => '',
            ]);

        $result->assertSessionHas('error', '환불 사유를 입력해주세요.');

        $unchanged = (new PaymentModel())->find($payment['id']);
        $this->assertSame('completed', $unchanged['status']);
    }

    public function testCannotRefundNonCompletedPayment(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('notcompleted@example.com', 'pending');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/refund", [
                csrf_token()    => csrf_hash(),
                'cancel_reason' => '고객 요청',
            ]);

        $result->assertSessionHas('error', '완료된 결제만 환불할 수 있습니다.');
    }

    public function testAdminCanRefundTestCompletedPaymentWithoutCallingToss(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('refundtestcomplete@example.com', 'completed');
        (new PaymentModel())->where('user_id', $userId)->set(['method' => 'admin_test', 'payment_key' => null])->update();
        (new UserModel())->update($userId, ['plan' => 'pro', 'plan_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month'))]);
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/refund", [
                csrf_token()    => csrf_hash(),
                'cancel_reason' => '테스트 완료 처리 취소',
            ]);

        $result->assertRedirectTo('/admin/payments');
        $result->assertSessionHas('message');

        $updated = (new PaymentModel())->find($payment['id']);
        $this->assertSame('refunded', $updated['status']);

        // payment_key가 없는 admin_test 건이라 실제 토스 API를 호출하지 않고도 정상 처리되어야 함
        $user = (new UserModel())->find($userId);
        $this->assertSame('free', $user['plan']);
        $this->assertNull($user['plan_expires_at']);
    }

    public function testAdminCanMarkPendingPaymentAsTestCompleted(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('testcomplete@example.com', 'pending', 'enterprise', 79800);
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/test-complete", [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/admin/payments');
        $result->assertSessionHas('message');

        $updated = (new PaymentModel())->find($payment['id']);
        $this->assertSame('completed', $updated['status']);
        $this->assertSame('admin_test', $updated['method']);
        $this->assertNotNull($updated['approved_at']);

        // 실제 결제 승인(success())과 동일하게 회원 요금제/만료일이 갱신되어야 함
        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']);
        $this->assertNotNull($user['plan_expires_at']);

        $log = (new AdminLogModel())->where('target_type', 'payment')->where('target_id', $payment['id'])->first();
        $this->assertNotNull($log);
        $this->assertSame('test_complete', $log['action']);
    }

    public function testCannotTestCompleteAlreadyCompletedPayment(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithPayment('alreadydone@example.com', 'completed');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/payments/{$payment['id']}/test-complete", [csrf_token() => csrf_hash()]);

        $result->assertSessionHas('error', '대기중인 결제만 테스트 완료 처리할 수 있습니다.');
    }

    public function testNonAdminCannotMarkPaymentAsTestCompleted(): void
    {
        $userId  = $this->createUserWithPayment('blockedtestcomplete@example.com', 'pending');
        $payment = (new PaymentModel())->where('user_id', $userId)->first();

        $result = $this->post("/admin/payments/{$payment['id']}/test-complete", [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/');

        $unchanged = (new PaymentModel())->find($payment['id']);
        $this->assertSame('pending', $unchanged['status']);
    }
}
