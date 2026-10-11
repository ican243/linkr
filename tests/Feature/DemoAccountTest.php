<?php

use App\Libraries\DemoAccount;
use App\Models\LinkModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

// 포트폴리오 방문자용 체험 계정: 바로 로그인, 샘플 데이터, 막힌 기능, 하루 링크 제한, 이동 전 확인 화면
final class DemoAccountTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function demoSession(): array
    {
        [$userId] = DemoAccount::reset();

        return ['isLoggedIn' => true, 'user_id' => $userId, 'user_email' => DemoAccount::EMAIL, 'is_demo' => true];
    }

    // 버튼 한 번으로 체험 계정이 만들어지고(샘플 링크 포함) 대시보드로 들어가지는지
    public function testDemoLoginCreatesAccountWithSampleData(): void
    {
        $result = $this->post('/auth/demo', [csrf_token() => csrf_hash()]);

        $this->assertTrue($result->isRedirect());
        $this->assertStringEndsWith('/dashboard', $result->getRedirectUrl());

        $user = (new UserModel())->where('email', DemoAccount::EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertSame('enterprise', $user['plan']);
        $this->assertGreaterThan(0, (new LinkModel())->where('user_id', $user['id'])->countAllResults());
        $this->assertSame((int) $user['id'], (int) session()->get('user_id'));
        $this->assertTrue((bool) session()->get('is_demo'));
    }

    // 초기화하면 방문자가 만든 링크는 지워지고 샘플 링크만 남는지
    public function testResetRemovesVisitorLinks(): void
    {
        [$userId, $sampleCount] = DemoAccount::reset();

        (new LinkModel())->insert(['user_id' => $userId, 'original_url' => 'https://example.com/visitor', 'short_code' => 'visit1']);

        DemoAccount::reset();

        $this->assertSame($sampleCount, (new LinkModel())->where('user_id', $userId)->countAllResults());
        $this->assertNull((new LinkModel())->where('short_code', 'visit1')->first());
    }

    // 체험 계정에서는 계정 정보 변경이 막히고, 막혔다는 안내가 나오는지
    public function testDemoCannotChangeAccount(): void
    {
        $session = $this->demoSession();

        $result = $this->withSession($session)->post('/account', [
            csrf_token() => csrf_hash(),
            'name'       => '바꾼이름',
        ]);

        $this->assertTrue($result->isRedirect());
        $this->assertStringEndsWith('/account', $result->getRedirectUrl());
        $this->assertSame(DemoAccount::NAME, (new UserModel())->find($session['user_id'])['name']);
        $this->assertNotEmpty(session()->getFlashdata('demo_blocked'));
    }

    // 결제 화면은 열기만 해도 주문이 생기므로 체험 계정은 요금제 페이지로 돌려보내는지
    public function testDemoCannotOpenCheckout(): void
    {
        $session = $this->demoSession();

        $result = $this->withSession($session)->get('/payment/checkout?plan=pro');

        $this->assertTrue($result->isRedirect());
        $this->assertStringEndsWith('/pricing', $result->getRedirectUrl());
        $this->assertNotEmpty(session()->getFlashdata('demo_blocked'));
        $this->assertSame(0, (new PaymentModel())->where('user_id', $session['user_id'])->countAllResults());
    }

    // 체험 계정은 하루에 정해진 개수까지만 링크를 만들 수 있는지
    public function testDemoDailyLinkLimit(): void
    {
        $session = $this->demoSession();
        $model   = new LinkModel();

        for ($i = 0; $i < DemoAccount::DAILY_LINK_LIMIT; $i++) {
            $model->insert(['user_id' => $session['user_id'], 'original_url' => 'https://example.com/' . $i, 'short_code' => 'dl' . $i]);
        }

        $before = $model->where('user_id', $session['user_id'])->countAllResults();

        $result = $this->withSession($session)->post('/shorten', [
            csrf_token()   => csrf_hash(),
            'original_url' => 'https://example.com/one-more',
        ]);

        $result->assertSee('하루에 ' . DemoAccount::DAILY_LINK_LIMIT . '개까지만');
        $this->assertSame($before, $model->where('user_id', $session['user_id'])->countAllResults());
    }

    // 체험 계정이 만든 링크는 바로 이동하지 않고 확인 화면을 보여주는지 (일반 회원 링크는 그대로 이동)
    public function testDemoLinkShowsNoticeInsteadOfRedirect(): void
    {
        $session = $this->demoSession();

        (new LinkModel())->insert(['user_id' => $session['user_id'], 'original_url' => 'https://example.com/demo-target', 'short_code' => 'demoN1']);

        $result = $this->get('/demoN1');
        $result->assertOK();
        $result->assertSee('이 주소로 이동합니다');
        $result->assertSee('https://example.com/demo-target');

        $normalId = (new UserModel())->insert(['email' => 'normal@example.com', 'password' => 'x', 'plan' => 'free']);
        (new LinkModel())->insert(['user_id' => $normalId, 'original_url' => 'https://example.com/normal', 'short_code' => 'normN1']);

        $this->assertTrue($this->get('/normN1')->isRedirect());
    }

    // 안내 띠의 "내 계정 만들기"는 로그아웃 후 회원가입 화면으로 보내는지
    public function testLogoutToRegister(): void
    {
        $result = $this->withSession($this->demoSession())->post('/logout', [csrf_token() => csrf_hash(), 'to' => 'register']);

        $this->assertTrue($result->isRedirect());
        $this->assertStringEndsWith('/auth/register', $result->getRedirectUrl());
    }
}
