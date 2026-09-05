<?php

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testLoginBlockedAfterTooManyFailedAttempts(): void
    {
        (new UserModel())->insert([
            'email'    => 'throttle-test@example.com',
            'password' => password_hash('CorrectPass1!', PASSWORD_DEFAULT),
            'name'     => 'Throttle Test',
        ]);

        // 처음 5번은 "비밀번호 틀림" 메시지가 정상적으로 떠야 함
        for ($i = 0; $i < 5; $i++) {
            $result = $this->post('/auth/login', [
                csrf_token() => csrf_hash(),
                'email'      => 'throttle-test@example.com',
                'password'   => 'WrongPassword',
            ]);

            $result->assertSee('이메일 또는 비밀번호가 올바르지 않습니다');
        }

        // 6번째부터는 시도 자체가 막혀야 함
        $result = $this->post('/auth/login', [
            csrf_token() => csrf_hash(),
            'email'      => 'throttle-test@example.com',
            'password'   => 'WrongPassword',
        ]);

        $result->assertSee('로그인 시도가 너무 많습니다');
    }
}
