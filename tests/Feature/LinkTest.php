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

    // 나중에 추가된 페이지 이름(about 등)은 커스텀 주소로 쓸 수 없어야 함 (쓰면 열리지 않는 죽은 링크가 됨)
    public function testReservedAliasesAreRejected(): void
    {
        $userId = $this->loginAsNewUser('linktest-reserved@example.com', 'pro');

        foreach (['about', 'privacy', 'demo-preview', 'assets'] as $alias) {
            $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
                ->post('/shorten', [
                    csrf_token()   => csrf_hash(),
                    'original_url' => 'https://example.com',
                    'custom_alias' => $alias,
                ])
                ->assertSee('이 커스텀 URL은 시스템에서 사용 중이라 선택할 수 없습니다.');
        }

        $this->assertSame(0, (new LinkModel())->where('user_id', $userId)->countAllResults());
    }

    // 무료 회원이 수정 버튼으로 만료일을 계속 늘릴 수 없어야 함: 기준은 "링크를 만든 날부터 30일"
    public function testFreeEditCannotExtendExpiryBeyondCreation(): void
    {
        helper('link');
        $userId    = $this->loginAsNewUser('linktest-extend@example.com', 'free');
        $createdAt = date('Y-m-d H:i:s', strtotime('-20 days'));

        $db = db_connect();
        $db->table('links')->insert([
            'user_id' => $userId, 'original_url' => 'https://example.com', 'short_code' => 'extnd1',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 days')), 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        // 지금부터 25일 뒤 = 만든 날부터 45일 → 거부
        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/links/extnd1/edit', [
                csrf_token()   => csrf_hash(),
                'original_url' => 'https://example.com',
                'expires_at'   => utc_to_kst_local(date('Y-m-d H:i:s', strtotime('+25 days'))),
            ])
            ->assertSee('링크를 만든 날부터 최대 30일');

        // 비워두면 "만든 날 + 30일"로 설정됨 (지금 + 30일이 아님)
        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/links/extnd1/edit', [csrf_token() => csrf_hash(), 'original_url' => 'https://example.com', 'expires_at' => '']);

        $link = (new LinkModel())->where('short_code', 'extnd1')->first();
        $this->assertSame(date('Y-m-d H:i:s', strtotime($createdAt) + 30 * 86400), $link['expires_at']);
    }

    // 클릭 수는 DB에서 +1 되고, 클릭 제한에 도달하면 더 이상 이동하지 않아야 함
    public function testClickLimitIsEnforcedAtomically(): void
    {
        $userId = $this->loginAsNewUser('linktest-clicks@example.com', 'enterprise');

        (new LinkModel())->insert([
            'user_id' => $userId, 'original_url' => 'https://example.com/limited', 'short_code' => 'limit2',
            'max_clicks' => 2, 'click_count' => 0,
        ]);

        $this->get('/limit2')->assertRedirectTo('https://example.com/limited');
        $this->get('/limit2')->assertRedirectTo('https://example.com/limited');
        $this->assertFalse($this->get('/limit2')->isRedirect()); // 3번째는 만료 안내

        $this->assertSame('2', (string) (new LinkModel())->where('short_code', 'limit2')->first()['click_count']);
    }

    // 대시보드 "최근 링크" 표의 원본 주소 칸에 단축 주소가 아니라 실제 원본 주소가 나오는지.
    public function testDashboardShowsOriginalUrl(): void
    {
        $userId = $this->loginAsNewUser('linktest-dash@example.com', 'free');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/original-page',
            'short_code'   => 'dashT1',
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/dashboard');

        $result->assertOK();
        $result->assertSee('https://example.com/original-page');
        // 복사 버튼, 검색엔진 비노출(noindex), 화면별 제목
        $body = html_entity_decode($result->getBody());
        $this->assertStringContainsString('data-copy=', $body);
        $this->assertStringContainsString('noindex', $body);
        $this->assertStringContainsString('<title>대시보드 | Linkr</title>', $body);
    }

    // javascript:, file: 같은 위험한 주소는 단축할 수 없어야 하고, 한글 주소는 정상적으로 만들어져야 한다.
    public function testOnlyHttpUrlsCanBeShortened(): void
    {
        $userId = $this->loginAsNewUser('linktest-scheme@example.com', 'free');

        foreach (['javascript://%0aalert(1)', 'file:///etc/passwd'] as $badUrl) {
            $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
                ->post('/shorten', [
                    csrf_token()   => csrf_hash(),
                    'original_url' => $badUrl,
                ]);

            $result->assertSee('http:// 또는 https://로 시작하는 올바른 URL을 입력해주세요');
        }

        $this->assertSame(0, (new LinkModel())->where('user_id', $userId)->countAllResults());

        $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()   => csrf_hash(),
                'original_url' => 'https://ko.wikipedia.org/wiki/서울',
            ]);

        $this->assertSame(1, (new LinkModel())->where('user_id', $userId)->countAllResults());
    }

    // 비밀번호 보호는 pricing.php에서 "프로 요금제부터"라고 안내하는 기능이라,
    // 무료 회원이 채워서 만들려고 하면 막혀야 한다. (만료일은 이제 무료도 사용함 — 아래 별도 테스트)
    public function testFreePlanCannotCreatePasswordProtectedLink(): void
    {
        $userId = $this->loginAsNewUser('linktest-freepw@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/shorten', [
                csrf_token()    => csrf_hash(),
                'original_url'  => 'https://example.com',
                'password'      => 'secret1234',
            ]);

        $result->assertSee('비밀번호 보호는 프로 요금제부터 사용할 수 있습니다.');
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
