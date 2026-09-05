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
        $result->assertSee('올바른 URL 형식이 아닙니다');
    }

    public function testAboutPageIsReachable(): void
    {
        $result = $this->get('/about');

        $result->assertOK();
        $result->assertSee('Linkr는 이런 서비스예요');
    }
}
