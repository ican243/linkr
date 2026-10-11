<?php

use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// nginx가 우리 도메인(kir1.cafe24.com)이 아닌 모든 Host를 이 앱으로 그대로 넘겨주기 때문에,
// App\Filters\CustomDomainFilter가 그 Host를 인증된 커스텀 도메인인지 판별해서 걸러준다.
// 여기서는 Host 헤더만 바꿔가며 그 판별 로직 자체를 검증한다(실제 DNS/nginx는 관여 안 함).
final class CustomDomainFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testUnknownHostShowsNotConnectedPage(): void
    {
        $result = $this->withHeaders(['Host' => 'totally-unregistered.example'])->get('/');

        $result->assertStatus(404);
        $result->assertSee('연결되지 않은 도메인입니다');
    }

    public function testVerifiedCustomDomainPassesThroughToNormalRouting(): void
    {
        $userId = (new UserModel())->insert([
            'email'                  => 'filtertest@example.com',
            'password'               => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'                   => 'Filter Test',
            'plan'                   => 'pro',
            'custom_domain'          => 'verified-filter-test.example',
            'custom_domain_verified' => true,
        ]);

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/filter-test-target',
            'short_code'   => 'filtertestcode',
        ]);

        $result = $this->withHeaders(['Host' => 'verified-filter-test.example'])->get('/filtertestcode');

        $result->assertRedirectTo('https://example.com/filter-test-target');
    }

    // 커스텀 도메인으로는 그 도메인 주인의 링크만 열려야 함 — 다른 회원 링크, 로그인 화면 등은 404
    public function testCustomDomainOnlyServesOwnersLinks(): void
    {
        $userModel = new UserModel();
        $ownerId   = $userModel->insert([
            'email' => 'owner@example.com', 'password' => 'x', 'plan' => 'pro',
            'custom_domain' => 'owner-domain.example', 'custom_domain_verified' => true,
        ]);
        $otherId = $userModel->insert(['email' => 'other@example.com', 'password' => 'x', 'plan' => 'free']);

        (new LinkModel())->insert(['user_id' => $ownerId, 'original_url' => 'https://example.com/mine', 'short_code' => 'ownerCode1']);
        (new LinkModel())->insert(['user_id' => $otherId, 'original_url' => 'https://example.com/theirs', 'short_code' => 'otherCode1']);

        $host = ['Host' => 'owner-domain.example'];

        $this->withHeaders($host)->get('/ownerCode1')->assertRedirectTo('https://example.com/mine');
        $this->withHeaders($host)->get('/otherCode1')->assertStatus(404);
        $this->withHeaders($host)->get('/auth/login')->assertStatus(404);
        $this->withHeaders($host)->get('/qr/otherCode1')->assertStatus(404);
    }

    // 무료로 내려간 회원의 도메인은 (인증 정보는 남아있어도) 연결되지 않은 도메인으로 취급
    public function testCustomDomainStopsWhenOwnerIsFree(): void
    {
        $ownerId = (new UserModel())->insert([
            'email' => 'freeowner@example.com', 'password' => 'x', 'plan' => 'free',
            'custom_domain' => 'free-owner.example', 'custom_domain_verified' => true,
        ]);
        (new LinkModel())->insert(['user_id' => $ownerId, 'original_url' => 'https://example.com/x', 'short_code' => 'freeOwn1']);

        $result = $this->withHeaders(['Host' => 'free-owner.example'])->get('/freeOwn1');

        $result->assertStatus(404);
        $result->assertSee('연결되지 않은 도메인입니다');
    }

    public function testUnverifiedCustomDomainStillShowsNotConnectedPage(): void
    {
        (new UserModel())->insert([
            'email'                  => 'filtertest-unverified@example.com',
            'password'               => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'                   => 'Filter Test Unverified',
            'plan'                   => 'pro',
            'custom_domain'          => 'unverified-filter-test.example',
            'custom_domain_verified' => false,
        ]);

        $result = $this->withHeaders(['Host' => 'unverified-filter-test.example'])->get('/');

        $result->assertStatus(404);
        $result->assertSee('연결되지 않은 도메인입니다');
    }
}
