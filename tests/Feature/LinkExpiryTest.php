<?php

use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 링크 만료 기능 정리 작업: 타임존(KST) 버그 수정 + 요금제별 만료 정책(무료 30일 상한/
// 프로·엔터프라이즈 무제한+대체URL/엔터프라이즈 클릭한도+재활성화).
final class LinkExpiryTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUser(string $email, string $plan): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Link Expiry Test',
            'plan'     => $plan,
        ]);
    }

    // 핵심 버그 수정 확인: "5분 전(한국시간 기준)"으로 만료일을 입력하면, 예전 버그처럼
    // 9시간 뒤에나 만료 처리되는 게 아니라 즉시(생성 직후부터) 만료 상태여야 한다.
    public function testKstExpiryInputIsImmediatelyExpiredNotNineHoursLater(): void
    {
        $userId = $this->createUser('kstbug@example.com', 'pro');

        helper('link');
        // "5분 전"을 한국시간 기준 datetime-local 형식으로 만듦
        $fiveMinAgoUtc = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $kstLocalInput = utc_to_kst_local($fiveMinAgoUtc);

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com/kst-bug',
                'custom_alias'  => 'kstbugtest',
                'expires_at'    => $kstLocalInput,
            ]);

        $link = (new LinkModel())->where('short_code', 'kstbugtest')->first();
        $this->assertNotNull($link);

        // 예전 버그였다면 이 시점(생성 직후)엔 아직 안 만료된 것처럼 나왔을 것임(9시간 밀림).
        $result = $this->get('/kstbugtest');
        $result->assertSee('만료된 링크입니다');
    }

    public function testFreePlanDefaultsToThirtyDaysWhenExpiryNotProvided(): void
    {
        $userId = $this->createUser('freedefault@example.com', 'free');

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com/free-default',
                'custom_alias'  => 'freedefaultlink',
            ]);

        $link = (new LinkModel())->where('short_code', 'freedefaultlink')->first();
        $this->assertNotNull($link);
        $this->assertNotNull($link['expires_at']);

        $expected = strtotime('+30 days');
        $actual   = strtotime($link['expires_at']);
        $this->assertLessThan(60, abs($expected - $actual)); // 1분 오차 허용
    }

    public function testFreePlanCannotSetExpiryBeyondThirtyDays(): void
    {
        $userId = $this->createUser('freeover@example.com', 'free');

        helper('link');
        $tooFarKst = utc_to_kst_local(date('Y-m-d H:i:s', strtotime('+60 days')));

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com/free-over',
                'custom_alias'  => 'freeoverlink',
                'expires_at'    => $tooFarKst,
            ]);

        $result->assertSee('무료 요금제는 만료일을 최대 30일 이내로만 설정할 수 있습니다.');
        $this->assertNull((new LinkModel())->where('short_code', 'freeoverlink')->first());
    }

    public function testExpiredFreePlanLinkReturns404(): void
    {
        $userId = $this->createUser('free404@example.com', 'free');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/free-404',
            'short_code'   => 'free404link',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->get('/free404link');

        $result->assertStatus(404);
        $result->assertSee('만료된 링크입니다');
    }

    public function testExpiredProPlanLinkWithFallbackUrlRedirectsThere(): void
    {
        $userId = $this->createUser('profallback@example.com', 'pro');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/original',
            'short_code'   => 'profallbacklink',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
            'fallback_url' => 'https://example.com/renewed-fallback',
        ]);

        $result = $this->get('/profallbacklink');

        $result->assertStatus(302);
        $result->assertHeader('Location', 'https://example.com/renewed-fallback');
    }

    public function testExpiredProPlanLinkWithoutFallbackShowsExpiredPageNotFourOhFour(): void
    {
        $userId = $this->createUser('proplain@example.com', 'pro');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/original',
            'short_code'   => 'proplainlink',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->get('/proplainlink');

        $result->assertOK();
        $result->assertSee('만료된 링크입니다');
    }

    public function testEnterpriseMaxClicksAutoExpiresLink(): void
    {
        $userId = $this->createUser('maxclicks@example.com', 'enterprise');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/limited',
            'short_code'   => 'maxclickslink',
            'click_count'  => 5,
            'max_clicks'   => 5,
        ]);

        $result = $this->get('/maxclickslink');

        $result->assertSee('만료된 링크입니다');
    }

    public function testUnderClickLimitStillRedirectsNormally(): void
    {
        $userId = $this->createUser('underlimit@example.com', 'enterprise');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/still-alive',
            'short_code'   => 'underlimitlink',
            'click_count'  => 3,
            'max_clicks'   => 5,
        ]);

        $result = $this->get('/underlimitlink');

        $result->assertStatus(302);
        $result->assertHeader('Location', 'https://example.com/still-alive');
    }

    public function testEnterpriseCanReactivateExpiredLink(): void
    {
        $userId = $this->createUser('reactivate@example.com', 'enterprise');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/reactivate-me',
            'short_code'   => 'reactivatelink',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
            'max_clicks'   => 5,
            'click_count'  => 5,
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/links/reactivatelink/reactivate', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/links');
        $result->assertSessionHas('message');

        $link = (new LinkModel())->where('short_code', 'reactivatelink')->first();
        $this->assertNull($link['expires_at']);
        $this->assertNull($link['max_clicks']);
    }

    public function testProPlanCannotReactivateLink(): void
    {
        $userId = $this->createUser('proreactivate@example.com', 'pro');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/pro-reactivate',
            'short_code'   => 'proreactivatelink',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/links/proreactivatelink/reactivate', [csrf_token() => csrf_hash()]);

        $result->assertSessionHas('error', '링크 재활성화는 엔터프라이즈 요금제부터 사용할 수 있습니다.');

        $link = (new LinkModel())->where('short_code', 'proreactivatelink')->first();
        $this->assertNotNull($link['expires_at']);
    }

    public function testShortenFormShowsCustomDomainPrefixWhenVerified(): void
    {
        $userId = $this->createUser('domainprefix@example.com', 'pro');
        (new UserModel())->update($userId, [
            'custom_domain'          => 'prefix-test.example',
            'custom_domain_verified' => true,
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/shorten');

        $result->assertSee('http://prefix-test.example/');
    }
}
