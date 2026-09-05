<?php

use App\Models\ApiKeyModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ApiLinksTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    // 유저 생성 + 실제 API 키(원본 문자열)까지 발급해서 돌려준다.
    // API 자체가 엔터프라이즈 요금제 전용이라, 기본값은 enterprise로 만들어서
    // "정상적으로 API가 되는" 시나리오를 테스트하기 편하게 함.
    private function createUserWithApiKey(string $email, string $plan = 'enterprise'): array
    {
        $userId = (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Api Links Test',
            'plan'     => $plan,
        ]);

        $rawKey = 'king_' . bin2hex(random_bytes(24));

        (new ApiKeyModel())->insert([
            'user_id'   => $userId,
            'label'     => 'test key',
            'key_hash'  => hash('sha256', $rawKey),
            'key_last4' => substr($rawKey, -4),
        ]);

        return ['userId' => $userId, 'apiKey' => $rawKey];
    }

    public function testListWithoutApiKeyReturns401(): void
    {
        $result = $this->get('/api/v1/links');

        $result->assertStatus(401);
    }

    // 발급받은 키 자체는 유효해도, 지금 이 회원의 요금제가 엔터프라이즈가 아니면 API 사용은 막혀야 함
    // (요금제 표에서 약속한 "API 액세스는 엔터프라이즈 전용"과 실제 동작을 맞추는 부분).
    public function testFreePlanUserWithValidKeyIsBlockedFromApi(): void
    {
        ['apiKey' => $apiKey] = $this->createUserWithApiKey('apilinks-free@example.com', 'free');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey])->get('/api/v1/links');

        $result->assertStatus(403);
        $decoded = json_decode($result->getJSON(), true);
        $this->assertSame('API 이용은 엔터프라이즈 요금제 전용 기능입니다. 요금제를 업그레이드해주세요.', $decoded['error']);
    }

    public function testListReturnsOnlyOwnLinks(): void
    {
        ['userId' => $userId, 'apiKey' => $apiKey] = $this->createUserWithApiKey('apilinks-owner@example.com');
        $otherUserId = (new UserModel())->insert([
            'email'    => 'apilinks-other@example.com',
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Other',
        ]);

        $linkModel = new LinkModel();
        $linkModel->insert(['user_id' => $userId, 'original_url' => 'https://example.com/1', 'short_code' => 'apil001']);
        $linkModel->insert(['user_id' => $userId, 'original_url' => 'https://example.com/2', 'short_code' => 'apil002']);
        $linkModel->insert(['user_id' => $otherUserId, 'original_url' => 'https://example.com/3', 'short_code' => 'apil003']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey])->get('/api/v1/links');

        $result->assertOK();
        $body = $result->getJSON();
        $decoded = json_decode($body, true);

        $this->assertSame(2, $decoded['meta']['total']);
        $codes = array_column($decoded['data'], 'short_code');
        sort($codes);
        $this->assertSame(['apil001', 'apil002'], $codes);
    }

    public function testDeletingAnotherUsersLinkReturns404AndKeepsData(): void
    {
        ['apiKey' => $attackerKey] = $this->createUserWithApiKey('apilinks-attacker@example.com');
        ['userId' => $victimId] = $this->createUserWithApiKey('apilinks-victim@example.com');

        $linkModel = new LinkModel();
        $linkId    = $linkModel->insert([
            'user_id'      => $victimId,
            'original_url' => 'https://example.com/victim',
            'short_code'   => 'victim1',
        ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $attackerKey])
            ->delete('/api/v1/links/victim1');

        $result->assertStatus(404);
        $this->assertNotNull($linkModel->find($linkId));
    }

    public function testDeletingOwnLinkRemovesItAndItStopsRedirecting(): void
    {
        ['userId' => $userId, 'apiKey' => $apiKey] = $this->createUserWithApiKey('apilinks-delete@example.com');

        (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/to-delete',
            'short_code'   => 'delme01',
        ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
            ->delete('/api/v1/links/delme01');

        $result->assertOK();
        $this->assertNull((new LinkModel())->where('short_code', 'delme01')->first());

        // 실제로 지워졌으니 그 코드로 접속하면 더 이상 리다이렉트되지 않고 404여야 함.
        // (Link::redirect()가 PageNotFoundException을 던지고, FeatureTestTrait는 전역
        // 예외 핸들러를 거치지 않아 예외가 그대로 올라오므로 직접 잡아서 확인함)
        $this->expectException(PageNotFoundException::class);
        $this->get('/delme01');
    }
}
