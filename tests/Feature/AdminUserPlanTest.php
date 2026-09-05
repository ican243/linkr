<?php

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

    public function testAdminCanUpgradeUserPlan(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isAdminLoggedIn' => true])
            ->post("/admin/users/{$userId}/plan", [csrf_token() => csrf_hash(), 'plan' => 'enterprise']);

        $result->assertRedirectTo('/admin/dashboard');
        $result->assertSessionHas('message');

        $user = (new UserModel())->find($userId);
        $this->assertSame('enterprise', $user['plan']);
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
}
