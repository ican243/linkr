<?php

use App\Libraries\DemoAccount;
use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 개인정보처리방침대로 동작하는지: 회원 탈퇴, 보관 기간 지난 정보 정리, 로그 정리
final class PrivacyRetentionTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUserWithLink(string $email, string $plan = 'free'): array
    {
        $userId = (new UserModel())->insert([
            'email' => $email, 'password' => password_hash('Test1234!', PASSWORD_DEFAULT), 'name' => '홍길동', 'plan' => $plan,
        ]);
        $linkId = (new LinkModel())->insert(['user_id' => $userId, 'original_url' => 'https://example.com/x', 'short_code' => 'pr' . $userId]);
        (new ClickLogModel())->insert(['link_id' => $linkId, 'clicked_at' => date('Y-m-d H:i:s'), 'ip_address' => '1.2.3.0', 'user_agent' => 'UA']);

        return [$userId, $linkId];
    }

    private function session(int $userId, string $email): array
    {
        return ['isLoggedIn' => true, 'user_id' => $userId, 'user_email' => $email];
    }

    // 확인 칸에 "탈퇴"를 안 쓰면 탈퇴되지 않음
    public function testWithdrawRequiresConfirmation(): void
    {
        [$userId] = $this->createUserWithLink('pr-confirm@example.com');

        $result = $this->withSession($this->session($userId, 'pr-confirm@example.com'))
            ->post('/account/delete', [csrf_token() => csrf_hash(), 'confirm' => '네']);

        $result->assertSee('탈퇴');
        $this->assertNotNull((new UserModel())->find($userId));
    }

    // 결제 기록이 없으면 회원·링크·클릭 기록이 모두 완전히 삭제됨
    public function testWithdrawWithoutPaymentsDeletesEverything(): void
    {
        [$userId, $linkId] = $this->createUserWithLink('pr-free@example.com');

        $result = $this->withSession($this->session($userId, 'pr-free@example.com'))
            ->post('/account/delete', [csrf_token() => csrf_hash(), 'confirm' => '탈퇴']);

        $result->assertRedirectTo('/');
        $this->assertNull((new UserModel())->find($userId));
        $this->assertNull((new LinkModel())->find($linkId));
        $this->assertSame(0, (new ClickLogModel())->where('link_id', $linkId)->countAllResults());
    }

    // 결제 기록이 있으면 결제 기록은 남기고, 회원 정보는 익명화 (같은 이메일로 다시 가입 가능)
    public function testWithdrawWithPaymentsKeepsPaymentsAndAnonymizes(): void
    {
        [$userId, $linkId] = $this->createUserWithLink('pr-paid@example.com', 'pro');
        (new UserModel())->update($userId, ['google_id' => 'g-123', 'custom_domain' => 'go.example.com', 'custom_domain_verified' => 1]);
        $paymentId = (new PaymentModel())->insert(['user_id' => $userId, 'order_id' => 'pr_order_1', 'amount' => 9900, 'plan' => 'pro', 'status' => 'completed']);

        $this->withSession($this->session($userId, 'pr-paid@example.com'))
            ->post('/account/delete', [csrf_token() => csrf_hash(), 'confirm' => '탈퇴']);

        $user = (new UserModel())->find($userId);
        $this->assertNotNull($user['withdrawn_at']);
        $this->assertStringEndsWith('@withdrawn.invalid', $user['email']);
        $this->assertNull($user['name']);
        $this->assertNull($user['google_id']);
        $this->assertNull($user['custom_domain']);
        $this->assertSame('free', $user['plan']);
        $this->assertNotNull((new PaymentModel())->find($paymentId));
        $this->assertNull((new LinkModel())->find($linkId));

        $this->post('/auth/register', [
            csrf_token() => csrf_hash(), 'email' => 'pr-paid@example.com', 'password' => 'Password123!', 'password_confirm' => 'Password123!',
        ])->assertRedirectTo('/auth/login');
    }

    // 탈퇴한 계정의 로그인이 다른 기기에 남아 있으면 로그아웃 상태가 됨
    public function testWithdrawnSessionIsLoggedOut(): void
    {
        [$userId] = $this->createUserWithLink('pr-session@example.com');
        (new UserModel())->update($userId, ['withdrawn_at' => date('Y-m-d H:i:s')]);

        $this->withSession($this->session($userId, 'pr-session@example.com'))->get('/dashboard')
            ->assertRedirectTo('/auth/login');
    }

    // 체험 계정은 탈퇴할 수 없음
    public function testDemoCannotWithdraw(): void
    {
        [$userId] = DemoAccount::reset();

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId, 'user_email' => DemoAccount::EMAIL, 'is_demo' => true])
            ->post('/account/delete', [csrf_token() => csrf_hash(), 'confirm' => '탈퇴']);

        $this->assertNotNull((new UserModel())->find($userId));
        $this->assertNotEmpty(session()->getFlashdata('demo_blocked'));
    }

    // 관리자 삭제도 같은 규칙: 결제 기록은 남고, 회원 목록에서는 빠지고, 결제 목록에는 "탈퇴한 회원"으로 보임
    public function testAdminDeleteKeepsPayments(): void
    {
        [$userId] = $this->createUserWithLink('pr-admin@example.com', 'pro');
        $paymentId = (new PaymentModel())->insert(['user_id' => $userId, 'order_id' => 'pr_order_2', 'amount' => 9900, 'plan' => 'pro', 'status' => 'completed']);

        $this->withSession(['isAdminLoggedIn' => true])->post("/admin/users/{$userId}/delete", [csrf_token() => csrf_hash()]);

        $this->assertNotNull((new PaymentModel())->find($paymentId));
        $this->withSession(['isAdminLoggedIn' => true])->get('/admin/members')->assertDontSee('withdrawn.invalid');
        $this->withSession(['isAdminLoggedIn' => true])->get('/admin/payments')->assertSee('탈퇴한 회원');
    }

    // 3개월 지난 클릭은 IP·기기 정보만 지우고 클릭 자체는 남김, 5년 지난 결제와 그 탈퇴 회원은 삭제
    public function testPurgeCommandFollowsRetentionPeriods(): void
    {
        [$userId, $linkId] = $this->createUserWithLink('pr-purge@example.com');
        $logs   = new ClickLogModel();
        $oldId  = $logs->insert(['link_id' => $linkId, 'clicked_at' => date('Y-m-d H:i:s', strtotime('-4 months')), 'ip_address' => '9.9.9.0', 'user_agent' => 'OldUA']);
        $newId  = $logs->insert(['link_id' => $linkId, 'clicked_at' => date('Y-m-d H:i:s', strtotime('-2 months')), 'ip_address' => '8.8.8.0', 'user_agent' => 'NewUA']);

        $goneId = (new UserModel())->insert(['email' => 'withdrawn-x@withdrawn.invalid', 'password' => 'x', 'withdrawn_at' => date('Y-m-d H:i:s')]);
        $oldPay = (new PaymentModel())->insert(['user_id' => $goneId, 'order_id' => 'pr_old', 'amount' => 1, 'plan' => 'pro', 'status' => 'completed']);
        db_connect()->table('payments')->where('id', $oldPay)->update(['created_at' => date('Y-m-d H:i:s', strtotime('-6 years'))]);

        command('privacy:purge');

        $old = $logs->find($oldId);
        $this->assertNotNull($old);
        $this->assertNull($old['ip_address']);
        $this->assertNull($old['user_agent']);
        $this->assertSame('8.8.8.0', $logs->find($newId)['ip_address']);
        $this->assertNull((new PaymentModel())->find($oldPay));
        $this->assertNull((new UserModel())->find($goneId));
        $this->assertNotNull((new UserModel())->find($userId));
    }

    // 30일 지난 오류 로그는 삭제, 최근 로그는 유지, 너무 큰 크론 기록은 최근 1,000줄만 남김
    public function testLogsCleanupCommand(): void
    {
        // 실제 로그 폴더가 아닌 임시 폴더에서 실행
        $dir = WRITEPATH . 'cleanup-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($dir);
        $old    = $dir . 'log-2000-01-01.log';
        $recent = $dir . 'log-2999-01-01.log';
        $cron   = $dir . 'cron-cleanup-test.log';

        file_put_contents($old, 'old');
        touch($old, strtotime('-31 days'));
        file_put_contents($recent, 'recent');
        file_put_contents($cron, str_repeat(str_repeat('x', 600) . "\n", 1500));
        $throttle = $dir . 'throttler_old';
        file_put_contents($throttle, 'x');
        touch($throttle, strtotime('-2 days'));

        command('logs:cleanup -logdir ' . $dir . ' -cachedir ' . $dir);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertCount(1000, file($cron));
        $this->assertFileDoesNotExist($throttle);

        array_map('unlink', glob($dir . '*'));
        rmdir($dir);
    }
}
