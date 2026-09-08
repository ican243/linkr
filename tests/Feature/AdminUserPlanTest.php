<?php

use App\Models\AdminLogModel;
use App\Models\AdminModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdminUserPlanTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUser(string $email = 'planchange@example.com'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Plan Change Test',
        ]);
    }

    private function createAdmin(): int
    {
        return (new AdminModel())->insert([
            'email'    => 'planadmintest@example.com',
            'password' => password_hash('AdminTest1234!', PASSWORD_DEFAULT),
            'name'     => 'Plan Change Test Admin',
        ]);
    }

    public function testAdminCanUpgradeUserPlanWithMonthsAndReason(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUser();

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/plan", [
                csrf_token() => csrf_hash(),
                'plan'       => 'enterprise',
                'months'     => 3,
                'reason'     => '전화 문의 후 3개월 부여',
            ]);

        $result->assertRedirectTo('/admin/dashboard');
        $result->assertSessionHas('message');

        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']);
        $this->assertNotNull($user['plan_expires_at']);

        // 3개월 뒤 날짜인지(±1일 오차 허용해서 날짜 계산 흔들림에 안전하게)
        $expected = strtotime('+3 month');
        $actual   = strtotime($user['plan_expires_at']);
        $this->assertLessThan(2 * 86400, abs($expected - $actual));

        // payments에 관리자 부여 이력이 남아야 함(사용자 결제내역 화면에도 그대로 보임)
        $payment = (new PaymentModel())->where('user_id', $userId)->first();
        $this->assertNotNull($payment);
        $this->assertSame('admin', $payment['method']);
        $this->assertSame(0, (int) $payment['amount']);
        $this->assertSame('completed', $payment['status']);

        // admin_logs에도 기록되어야 함
        $log = (new AdminLogModel())->where('target_type', 'user')->where('target_id', $userId)->first();
        $this->assertNotNull($log);
        $this->assertSame('plan_change', $log['action']);
    }

    public function testDowngradingToFreeClearsExpiryAndSkipsPaymentRecord(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUser('downgrade@example.com');
        (new UserModel())->update($userId, ['plan' => 'pro', 'plan_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month'))]);

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/plan", [
                csrf_token() => csrf_hash(),
                'plan'       => 'free',
                'reason'     => '환불 요청으로 강등',
            ]);

        $result->assertRedirectTo('/admin/dashboard');

        $user = (new UserModel())->find($userId);
        $this->assertSame('free', $user['plan']);
        $this->assertNull($user['plan_expires_at']);

        // 무료로 내릴 땐 결제 이력을 새로 남기지 않음(부여한 게 없으니까)
        $this->assertSame(0, (new PaymentModel())->where('user_id', $userId)->countAllResults());
    }

    public function testReasonIsRequired(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUser('noreason@example.com');

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/plan", [
                csrf_token() => csrf_hash(),
                'plan'       => 'pro',
                'months'     => 1,
                'reason'     => '',
            ]);

        $result->assertSessionHas('error', '요금제 변경 사유를 입력해주세요.');

        $user = (new UserModel())->find($userId);
        $this->assertSame('free', $user['plan']);
    }

    public function testInvalidPlanValueIsRejected(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isAdminLoggedIn' => true])
            ->post("/admin/users/{$userId}/plan", [csrf_token() => csrf_hash(), 'plan' => 'super-vip']);

        $result->assertRedirectTo('/admin/dashboard');
        $result->assertSessionHas('error');

        // 잘못된 값이면 기존 요금제(free)가 그대로 유지되어야 함
        $user = (new UserModel())->find($userId);
        $this->assertSame('free', $user['plan']);
    }

    public function testNonAdminCannotChangePlan(): void
    {
        $userId = $this->createUser();

        $result = $this->post("/admin/users/{$userId}/plan", [csrf_token() => csrf_hash(), 'plan' => 'enterprise']);

        $result->assertRedirectTo('/');

        $user = (new UserModel())->find($userId);
        $this->assertSame('free', $user['plan']);
    }

    public function testAdminCanViewUserDetailPage(): void
    {
        $userId = $this->createUser('detailview@example.com');

        $result = $this->withSession(['isAdminLoggedIn' => true])->get("/admin/users/{$userId}");

        $result->assertOK();
        $result->assertSee('detailview@example.com');
        $result->assertSee('결제 이력');
    }

    public function testNonAdminCannotViewUserDetailPage(): void
    {
        $userId = $this->createUser('blockedview@example.com');

        $result = $this->get("/admin/users/{$userId}");

        $result->assertRedirectTo('/');
    }
}
