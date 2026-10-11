<?php

use App\Models\LinkModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class HomeTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    // 로그인 없이도 "이렇게 만들어져요" 미리보기가 뜨는지 (실제로 DB에는 저장되지 않음).
    public function testDemoPreviewShowsShortUrlAndQrWithoutLogin(): void
    {
        $result = $this->post('/demo-preview', [
            csrf_token()    => csrf_hash(),
            'original_url'  => 'https://example.com/campaign',
        ]);

        $result->assertOK();
        $result->assertSee('가입하고 진짜로 저장하기');
        $result->assertSee('data:image/png;base64,');

        // 진짜로 링크가 저장된 게 아니어야 함
        $this->assertSame(0, (new LinkModel())->countAllResults());
    }

    public function testDemoPreviewRejectsInvalidUrl(): void
    {
        $result = $this->post('/demo-preview', [
            csrf_token()    => csrf_hash(),
            'original_url'  => 'not-a-url',
        ]);

        $result->assertOK();
        $result->assertSee('올바른 URL을 입력해주세요');
    }

    public function testAboutPageIsReachable(): void
    {
        $result = $this->get('/about');

        $result->assertOK();
        $result->assertSee('Linkr는 이런 서비스예요');
    }

    // 요금제 페이지: 실제로 제공하지 않는 것(연간 결제, 24/7 지원)과 "준비 중" 문구가 없어야 한다.
    public function testPricingPageHasNoUnsupportedPromises(): void
    {
        $result = $this->get('/pricing');

        $result->assertOK();
        $result->assertSee('매월 결제');
        $result->assertDontSee('연간 결제');
        $result->assertDontSee('24/7');
        $result->assertDontSee('준비 중');
    }

    // 페이지마다 제목이 다르고, 공유 미리보기(OG)·파비콘 태그가 들어가는지.
    public function testPagesHaveTitleAndShareTags(): void
    {
        $home = html_entity_decode($this->get('/')->getBody());
        $this->assertStringContainsString('<title>Linkr – 긴 URL은 짧게, 클릭 통계는 한눈에</title>', $home);
        $this->assertStringContainsString('og-image.png', $home);
        $this->assertStringContainsString('favicon.svg', $home);

        $this->assertStringContainsString('<title>요금제 | Linkr</title>', html_entity_decode($this->get('/pricing')->getBody()));
        $this->assertStringContainsString('<title>서비스 소개 | Linkr</title>', html_entity_decode($this->get('/about')->getBody()));
    }
}
