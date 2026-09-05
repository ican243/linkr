<?php

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class CustomDomainTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    // 커스텀 도메인 저장은 프로 요금제부터 가능해서, 저장 기능 자체를 테스트하는 케이스들은
    // 기본적으로 pro 회원으로 만든다. free 회원이 막히는지는 별도 테스트로 확인.
    private function createUser(string $email = 'domaintest@example.com', string $plan = 'pro'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Domain Test',
            'plan'     => $plan,
        ]);
    }

    public function testFreePlanUserCannotSaveDomain(): void
    {
        $userId = $this->createUser('domaintest-free@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/custom-domain', [
                csrf_token()    => csrf_hash(),
                'custom_domain' => 'mybrand-blocked.com',
            ]);

        $result->assertRedirectTo('/custom-domain');
        $result->assertSessionHas('error', '커스텀 도메인은 프로 요금제부터 사용할 수 있습니다.');

        $user = (new UserModel())->find($userId);
        $this->assertNull($user['custom_domain']);
    }

    public function testInvalidDomainFormatIsRejected(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/custom-domain', [
                csrf_token()    => csrf_hash(),
                'custom_domain' => 'not-a-domain',
            ]);

        $result->assertRedirectTo('/custom-domain');
        $result->assertSessionHas('error', '올바른 도메인 형식이 아닙니다. (예: mybrand.com)');

        $user = (new UserModel())->find($userId);
        $this->assertNull($user['custom_domain']);
    }

    public function testValidDomainIsSavedAsUnverified(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/custom-domain', [
                csrf_token()    => csrf_hash(),
                'custom_domain' => 'mybrand-test.com',
            ]);

        $result->assertRedirectTo('/custom-domain');

        $user = (new UserModel())->find($userId);
        $this->assertSame('mybrand-test.com', $user['custom_domain']);
        $this->assertSame(0, (int) $user['custom_domain_verified']);
    }

    // DNS가 실제로 연결 안 된(우리 서버를 안 가리키는) 도메인은 인증 통과하면 안 됨.
    public function testVerifyFailsForUnpointedDomain(): void
    {
        $userId = $this->createUser();
        (new UserModel())->update($userId, ['custom_domain' => 'definitely-not-pointed-at-king.invalid']);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/custom-domain/verify', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/custom-domain');
        $result->assertSessionHas('error');

        $user = (new UserModel())->find($userId);
        $this->assertSame(0, (int) $user['custom_domain_verified']);
    }

    public function testRemoveClearsDomainAndVerification(): void
    {
        $userId = $this->createUser();
        (new UserModel())->update($userId, [
            'custom_domain'          => 'toremove-test.com',
            'custom_domain_verified' => true,
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/custom-domain/remove', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/custom-domain');

        $user = (new UserModel())->find($userId);
        $this->assertNull($user['custom_domain']);
        $this->assertSame(0, (int) $user['custom_domain_verified']);
    }
}
