<?php

use App\Models\ApiKeyModel;
use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 횟수 제한, 봇 가입 차단, IP 가림, CSV 수식 주입 차단, POST 로그아웃
final class SecurityLimitsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUser(string $email, string $plan = 'free'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Security Test',
            'plan'     => $plan,
        ]);
    }

    // 비밀번호 보호 링크는 같은 IP가 10번까지만 시도할 수 있고, 11번째는 맞는 비밀번호여도 막힘
    public function testLinkPasswordAttemptsAreLimited(): void
    {
        $userId = $this->createUser('sec-pw@example.com', 'pro');
        (new LinkModel())->insert([
            'user_id' => $userId, 'original_url' => 'https://example.com/secret', 'short_code' => 'pwlim1',
            'password' => password_hash('right-pass', PASSWORD_DEFAULT),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/pwlim1', [csrf_token() => csrf_hash(), 'password' => 'wrong'])->assertSee('비밀번호가 올바르지 않습니다');
        }

        $result = $this->post('/pwlim1', [csrf_token() => csrf_hash(), 'password' => 'right-pass']);
        $result->assertStatus(429);
        $result->assertSee('너무 많이 틀렸습니다');
    }

    // 봇만 채우는 숨은 칸(website)에 값이 있으면 가입된 것처럼 보이지만 실제로는 계정이 안 만들어짐
    public function testRegisterHoneypotBlocksBots(): void
    {
        $result = $this->post('/auth/register', [
            csrf_token()       => csrf_hash(),
            'email'            => 'bot@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'website'          => 'http://spam.example',
        ]);

        $this->assertTrue($result->isRedirect());
        $this->assertNull((new UserModel())->where('email', 'bot@example.com')->first());
    }

    // 같은 IP에서는 1시간에 10번까지만 가입 시도 가능
    public function testRegisterIsLimitedPerIp(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/auth/register', [csrf_token() => csrf_hash(), 'email' => 'not-an-email']);
        }

        $result = $this->post('/auth/register', [
            csrf_token()       => csrf_hash(),
            'email'            => 'late@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
        ]);

        $result->assertSee('가입 시도가 너무 많습니다');
        $this->assertNull((new UserModel())->where('email', 'late@example.com')->first());
    }

    // 이메일을 바꿔가며 시도해도 같은 IP는 5분에 20번까지만
    public function testLoginIsLimitedPerIp(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/auth/login', [csrf_token() => csrf_hash(), 'email' => "nobody{$i}@example.com", 'password' => 'x'])
                ->assertSee('이메일 또는 비밀번호가 올바르지 않습니다');
        }

        $this->post('/auth/login', [csrf_token() => csrf_hash(), 'email' => 'nobody99@example.com', 'password' => 'x'])
            ->assertSee('로그인 시도가 너무 많습니다');
    }

    // IP 마지막 자리 가리기
    public function testMaskIp(): void
    {
        helper('link');

        $this->assertSame('203.0.113.0', mask_ip('203.0.113.57'));
        $this->assertSame('2001:db8:85a3::', mask_ip('2001:db8:85a3:8d3:1319:8a2e:370:7348'));
        $this->assertNull(mask_ip('not-an-ip'));
    }

    // 클릭 기록에는 가린 IP만 저장됨
    public function testClickLogStoresMaskedIp(): void
    {
        $userId = $this->createUser('sec-ip@example.com');
        $linkId = (new LinkModel())->insert(['user_id' => $userId, 'original_url' => 'https://example.com/ip', 'short_code' => 'maskip']);

        service('superglobals')->setServer('REMOTE_ADDR', '198.51.100.77');
        $this->get('/maskip');
        service('superglobals')->setServer('REMOTE_ADDR', '0.0.0.0');

        $log = (new ClickLogModel())->where('link_id', $linkId)->first();
        $this->assertSame('198.51.100.0', $log['ip_address']);
    }

    // User-Agent가 =로 시작해도 CSV에서 수식이 아니라 글자로 들어가야 함
    public function testCsvExportNeutralizesFormulas(): void
    {
        $userId = $this->createUser('sec-csv@example.com', 'pro');
        $linkId = (new LinkModel())->insert(['user_id' => $userId, 'original_url' => 'https://example.com/csv', 'short_code' => 'csvinj']);
        (new ClickLogModel())->insert([
            'link_id' => $linkId, 'clicked_at' => date('Y-m-d H:i:s'), 'ip_address' => '1.2.3.0',
            'user_agent' => '=HYPERLINK("http://evil.example","click")',
        ]);

        $body = (string) $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get('/links/csvinj/stats/export')->response()->getBody();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil.example"",""click"")"', $body);
        $this->assertStringNotContainsString(',=HYPERLINK', $body);
    }

    // 로그아웃은 POST로만: GET /logout으로는 로그아웃되지 않음
    public function testLogoutRequiresPost(): void
    {
        $userId  = $this->createUser('sec-logout@example.com');
        $session = ['isLoggedIn' => true, 'user_id' => $userId, 'user_email' => 'sec-logout@example.com'];

        // GET /logout은 더 이상 로그아웃 주소가 아님 → 없는 페이지(404)
        try {
            $this->withSession($session)->get('/logout');
            $this->fail('GET /logout은 404여야 함');
        } catch (PageNotFoundException) {
        }
        $this->assertTrue((bool) session()->get('isLoggedIn'));

        $result = $this->withSession($session)->post('/logout', [csrf_token() => csrf_hash()]);
        $result->assertRedirectTo('/');
    }

    // QR 이미지는 같은 IP가 1분에 60번까지 (1초에 1장씩 다시 채워지므로, 빠르게 계속 요청하면 결국 막힘)
    public function testQrIsRateLimited(): void
    {
        $userId = $this->createUser('sec-qr@example.com');
        (new LinkModel())->insert(['user_id' => $userId, 'original_url' => 'https://example.com/qr', 'short_code' => 'qrlim1']);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/qr/qrlim1')->assertOK();
        }

        for ($i = 0; $i < 60; $i++) {
            $result = $this->get('/qr/qrlim1');
            if ($result->response()->getStatusCode() === 429) {
                break;
            }
        }

        $result->assertStatus(429);
        $this->assertNotSame('', $result->response()->getHeaderLine('Retry-After'));
    }

    // API는 같은 IP가 1분에 60번까지
    public function testApiIsRateLimited(): void
    {
        $userId = $this->createUser('sec-api@example.com', 'enterprise');
        $rawKey = 'king_' . bin2hex(random_bytes(24));
        (new ApiKeyModel())->insert(['user_id' => $userId, 'label' => 't', 'key_hash' => hash('sha256', $rawKey), 'key_last4' => substr($rawKey, -4)]);

        // 60번까지는 통과. 1초에 1번씩 다시 채워지므로(서버가 느리면 그 사이 몇 번 더 채워짐) 막힐 때까지 조금 더 보냄
        for ($i = 0; $i < 60; $i++) {
            $this->withHeaders(['Authorization' => 'Bearer ' . $rawKey])->get('/api/v1/links')->assertOK();
        }

        for ($i = 0; $i < 60; $i++) {
            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $rawKey])->get('/api/v1/links');
            if ($result->response()->getStatusCode() === 429) {
                break;
            }
        }

        $result->assertStatus(429);
    }
}
