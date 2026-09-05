<?php

use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class LinkTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function loginAsNewUser(string $email = 'linktest@example.com', string $plan = 'free'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Link Test',
            'plan'     => $plan,
        ]);
    }

    // 비밀번호 보호/만료일은 pricing.php에서 "프로 요금제부터"라고 안내하는 기능이라,
    // 무료 회원이 둘 중 하나라도 채워서 만들려고 하면 막혀야 한다.
    public function testFreePlanCannotCreatePasswordProtectedLink(): void
    {
        $userId = $this->loginAsNewUser('linktest-freepw@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com',
                'password'      => 'secret1234',
            ]);

        $result->assertSee('비밀번호 보호와 만료일 설정은 프로 요금제부터 사용할 수 있습니다.');
        $this->assertSame(0, (new LinkModel())->where('user_id', $userId)->countAllResults());
    }

    public function testProPlanCanCreatePasswordProtectedLink(): void
    {
        $userId = $this->loginAsNewUser('linktest-propw@example.com', 'pro');

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com',
                'custom_alias'  => 'propwlink',
                'password'      => 'secret1234',
            ]);

        $link = (new LinkModel())->where('short_code', 'propwlink')->first();
        $this->assertNotNull($link);
        $this->assertNotEmpty($link['password']);
    }

    // 화이트라벨링(엔터프라이즈 전용): 엔터프라이즈 회원이 만든 링크를 클릭한 방문자한테는
    // king 브랜딩(네비게이션/푸터/광고 문구)이 없는 화면이 떠야 한다.
    public function testExpiredLinkFromEnterpriseOwnerHidesKingBranding(): void
    {
        $userId = $this->loginAsNewUser('linktest-wl-ent@example.com', 'enterprise');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/expired-wl',
            'short_code'   => 'wlexpent',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->get('/wlexpent');

        $result->assertOK();
        $result->assertSee('만료된 링크입니다');
        $result->assertDontSee('개인 학습용 프로젝트입니다');
        $result->assertDontSee('요금제');
    }

    // 프로/무료 회원이 만든 링크는 지금처럼 king 브랜딩이 그대로 보여야 한다(회귀 확인).
    public function testExpiredLinkFromProOwnerStillShowsKingBranding(): void
    {
        $userId = $this->loginAsNewUser('linktest-wl-pro@example.com', 'pro');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/expired-branded',
            'short_code'   => 'wlexppro',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $this->get('/wlexppro');

        $result->assertOK();
        $result->assertSee('만료된 링크입니다');
        $result->assertSee('개인 학습용 프로젝트입니다');
    }

    public function testCannotUseReservedWordAsCustomAlias(): void
    {
        $userId = $this->loginAsNewUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com',
                'custom_alias'  => 'admin',
            ]);

        $result->assertSee('이 커스텀 URL은 시스템에서 사용 중이라 선택할 수 없습니다');
    }

    public function testCreatedLinkRedirectsToOriginalUrl(): void
    {
        $userId = $this->loginAsNewUser();

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com/redirect-test',
                'custom_alias'  => 'testalias123',
            ]);

        $result = $this->get('/testalias123');

        $result->assertStatus(302);
        $result->assertHeader('Location', 'https://example.com/redirect-test');
    }
}
