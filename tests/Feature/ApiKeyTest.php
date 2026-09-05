<?php

use App\Models\ApiKeyModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ApiKeyTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    // API 키 발급은 엔터프라이즈 요금제 전용이라, 이 파일의 "발급 자체가 되는" 테스트들은
    // 기본적으로 enterprise 회원으로 만든다. free 회원이 막히는지는 별도 테스트로 확인.
    private function createUser(string $email = 'apikeytest@example.com', string $plan = 'enterprise'): int
    {
        return (new UserModel())->insert([
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Api Key Test',
            'plan'     => $plan,
        ]);
    }

    public function testFreePlanUserCannotCreateKey(): void
    {
        $userId = $this->createUser('freeplan@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/api-keys', [csrf_token() => csrf_hash(), 'label' => '내 서버']);

        $result->assertRedirectTo('/api-keys');
        $result->assertSessionHas('error', 'API 키 발급은 엔터프라이즈 요금제 전용 기능입니다. 요금제를 업그레이드해주세요.');
        $this->assertSame(0, (new ApiKeyModel())->where('user_id', $userId)->countAllResults());
    }

    // 발급 폼뿐 아니라, curl 사용법 예시 카드도 무료 회원한테는 안 보여야 함
    // (예전엔 폼만 숨기고 사용법 카드는 그대로 노출돼서 헷갈린다는 피드백으로 수정함).
    public function testUsageExampleCardsHiddenForFreePlan(): void
    {
        $userId = $this->createUser('freeplan-view@example.com', 'free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/api-keys');

        $result->assertOK();
        $result->assertSee('엔터프라이즈 요금제');
        $result->assertDontSee('API로 링크 만들기');
    }

    public function testUsageExampleCardsVisibleForEnterprisePlan(): void
    {
        $userId = $this->createUser('enterprise-view@example.com', 'enterprise');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])->get('/api-keys');

        $result->assertOK();
        $result->assertSee('API로 링크 만들기');
    }

    public function testEmptyLabelIsRejected(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/api-keys', [csrf_token() => csrf_hash(), 'label' => '']);

        $result->assertRedirectTo('/api-keys');
        $result->assertSessionHas('error', '키 별명을 입력해주세요.');

        $this->assertSame(0, (new ApiKeyModel())->where('user_id', $userId)->countAllResults());
    }

    // 원본 키 문자열은 응답(플래시) 딱 한 번만 노출되고, DB에는 해시만 저장돼야 함.
    public function testCreatingKeyReturnsRawKeyOnceAndStoresOnlyHash(): void
    {
        $userId = $this->createUser();

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post('/api-keys', [csrf_token() => csrf_hash(), 'label' => '내 서버']);

        $result->assertRedirectTo('/api-keys');
        $this->assertArrayHasKey('newKey', $_SESSION);
        $rawKey = $_SESSION['newKey'];
        $this->assertStringStartsWith('king_', $rawKey);

        $stored = (new ApiKeyModel())->where('user_id', $userId)->first();
        $this->assertSame(hash('sha256', $rawKey), $stored['key_hash']);
        $this->assertSame(substr($rawKey, -4), $stored['key_last4']);
    }

    public function testCanDeleteOwnKey(): void
    {
        $userId = $this->createUser();
        $keyId  = (new ApiKeyModel())->insert([
            'user_id'   => $userId,
            'label'     => '삭제될 키',
            'key_hash'  => hash('sha256', 'king_dummyrawkey'),
            'key_last4' => 'abcd',
        ]);

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->post("/api-keys/{$keyId}/delete", [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo('/api-keys');
        $this->assertNull((new ApiKeyModel())->find($keyId));
    }

    // 다른 사람의 API 키를 URL의 id만 바꿔서 지우려는 시도는 반드시 막혀야 함.
    public function testCannotDeleteAnotherUsersKey(): void
    {
        $ownerId = $this->createUser('owner@example.com');
        $keyId   = (new ApiKeyModel())->insert([
            'user_id'   => $ownerId,
            'label'     => '남의 키',
            'key_hash'  => hash('sha256', 'king_ownerkey'),
            'key_last4' => 'wxyz',
        ]);

        $attackerId = $this->createUser('attacker@example.com');

        // 컨트롤러가 소유자가 아니면 PageNotFoundException을 던지는데, FeatureTestTrait는
        // 전역 예외 핸들러를 거치지 않아 예외가 그대로 올라오므로 직접 잡아서 확인함.
        try {
            $this->withSession(['isLoggedIn' => true, 'user_id' => $attackerId])
                ->post("/api-keys/{$keyId}/delete", [csrf_token() => csrf_hash()]);
            $this->fail('다른 사람의 API 키 삭제 시 PageNotFoundException이 발생해야 합니다.');
        } catch (PageNotFoundException) {
            // 기대한 동작
        }

        $this->assertNotNull((new ApiKeyModel())->find($keyId));
    }
}
