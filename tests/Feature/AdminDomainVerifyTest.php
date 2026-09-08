<?php

use App\Models\AdminLogModel;
use App\Models\AdminModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 관리자가 DNS(TXT 레코드) 확인 없이 커스텀 도메인을 강제로 인증 처리하는 기능.
// hosts 파일 등으로 로컬 시연할 때처럼, 우리 서버가 실제 DNS로는 절대 확인할 수 없는
// 상황을 위한 기능이라 admin_logs에 반드시 구분되게 남아야 한다.
final class AdminDomainVerifyTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createAdmin(): int
    {
        return (new AdminModel())->insert([
            'email'    => 'domainverifyadmin@example.com',
            'password' => password_hash('AdminTest1234!', PASSWORD_DEFAULT),
            'name'     => 'Domain Verify Test Admin',
        ]);
    }

    private function createUserWithDomain(string $email, ?string $domain, bool $verified = false): int
    {
        return (new UserModel())->insert([
            'email'                  => $email,
            'password'               => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'                   => 'Domain Verify Test',
            'plan'                   => 'pro',
            'custom_domain'          => $domain,
            'custom_domain_verified' => $verified,
        ]);
    }

    public function testAdminCanForceVerifyUnverifiedDomain(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithDomain('forceverify@example.com', 'demo.qwer.com');

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/verify-domain", [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/admin/members');
        $result->assertSessionHas('message');

        $user = (new UserModel())->find($userId);
        $this->assertSame(1, (int) $user['custom_domain_verified']);

        $log = (new AdminLogModel())->where('target_type', 'user')->where('target_id', $userId)->first();
        $this->assertNotNull($log);
        $this->assertSame('domain_force_verify', $log['action']);
    }

    public function testCannotForceVerifyWhenNoCustomDomainRegistered(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithDomain('nodomain@example.com', null);

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/verify-domain", [csrf_token() => csrf_hash()]);

        $result->assertSessionHas('error', '이 회원은 등록된 커스텀 도메인이 없습니다.');
    }

    public function testCannotForceVerifyAlreadyVerifiedDomain(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUserWithDomain('alreadyverified@example.com', 'already-verified.example', true);

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/verify-domain", [csrf_token() => csrf_hash()]);

        $result->assertSessionHas('error', '이미 인증된 도메인입니다.');
    }

    public function testNonAdminCannotForceVerifyDomain(): void
    {
        $userId = $this->createUserWithDomain('blockedverify@example.com', 'blocked.example');

        $result = $this->post("/admin/users/{$userId}/verify-domain", [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/');

        $user = (new UserModel())->find($userId);
        $this->assertSame(0, (int) $user['custom_domain_verified']);
    }
}
