<?php

use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class QrTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testExistingLinkReturnsPngImage(): void
    {
        $userId = (new UserModel())->insert([
            'email'    => 'qrtest@example.com',
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'QR Test',
        ]);

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/qr-target',
            'short_code'   => 'qrcode1',
        ]);

        $result = $this->get('/qr/qrcode1');

        $result->assertOK();
        $this->assertStringContainsString('image/png', $result->response()->getHeaderLine('Content-Type'));

        // PNG 파일은 항상 이 8바이트로 시작함 (실제로 이미지가 생성됐는지 확인)
        $pngSignature = "\x89PNG\r\n\x1a\n";
        $this->assertSame($pngSignature, substr($result->response()->getBody(), 0, 8));
    }

    // 존재하지 않는 코드는 PageNotFoundException으로 404 처리됨.
    // FeatureTestTrait는 전역 예외 핸들러를 거치지 않아서, 실제 서비스에서는 404 페이지로
    // 보이는 것과 달리 테스트에서는 예외를 직접 잡아서 확인해야 함(CI4 테스트의 알려진 특성).
    public function testNonExistentLinkReturns404(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('/qr/no-such-code-xyz');
    }
}
