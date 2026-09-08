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
}
