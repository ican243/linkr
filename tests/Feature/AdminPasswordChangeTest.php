<?php

use App\Models\AdminLogModel;
use App\Models\AdminModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdminPasswordChangeTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createAdmin(): int
    {
        return (new AdminModel())->insert([
            'email'    => 'pwchangeadmin@example.com',
            'password' => password_hash('AdminTest1234!', PASSWORD_DEFAULT),
            'name'     => 'Password Change Test Admin',
        ]);
    }

    private function createUser(string $email = 'pwchangeuser@example.com'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('OldPassword123!', PASSWORD_DEFAULT),
            'name'     => 'Password Change Test User',
        ]);
    }

    public function testAdminCanChangeUserPassword(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUser();
        $oldHash = (new UserModel())->find($userId)['password'];

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/password", [
                csrf_token() => csrf_hash(),
                'password'   => 'NewPassword456!',
            ]);

        $result->assertRedirectTo("/admin/users/{$userId}");
        $result->assertSessionHas('message');

        $user = (new UserModel())->find($userId);
        $this->assertNotSame($oldHash, $user['password']);
        $this->assertTrue(password_verify('NewPassword456!', $user['password']));

        $log = (new AdminLogModel())->where('target_type', 'user')->where('target_id', $userId)->first();
        $this->assertNotNull($log);
        $this->assertSame('password_change', $log['action']);
        // 실제 비밀번호 값이 로그에 남으면 안 됨
        $this->assertStringNotContainsString('NewPassword456!', $log['detail']);
    }

    public function testShortPasswordIsRejected(): void
    {
        $adminId = $this->createAdmin();
        $userId  = $this->createUser('shortpw@example.com');
        $oldHash = (new UserModel())->find($userId)['password'];

        $result = $this->withSession(['isAdminLoggedIn' => true, 'admin_id' => $adminId])
            ->post("/admin/users/{$userId}/password", [
                csrf_token() => csrf_hash(),
                'password'   => 'short',
            ]);

        $result->assertSessionHas('error', '비밀번호는 8자 이상이어야 합니다.');

        $user = (new UserModel())->find($userId);
        $this->assertSame($oldHash, $user['password']);
    }

    public function testNonAdminCannotChangeUserPassword(): void
    {
        $userId  = $this->createUser('blockedpw@example.com');
        $oldHash = (new UserModel())->find($userId)['password'];

        $result = $this->post("/admin/users/{$userId}/password", [
            csrf_token() => csrf_hash(),
            'password'   => 'NewPassword456!',
        ]);

        $result->assertRedirectTo('/');

        $user = (new UserModel())->find($userId);
        $this->assertSame($oldHash, $user['password']);
    }
}
