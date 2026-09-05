<?php

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * king_db_test 연결/마이그레이션이 정상 동작하는지 확인하는 기초 테스트.
 * 실제 king_db는 전혀 건드리지 않는다 (DatabaseTestTrait가 테스트 전용 DB에 마이그레이션을
 * 자동 실행하고, 각 테스트가 끝나면 만든 데이터를 자동으로 롤백해준다).
 */
final class DatabaseSetupTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // 기본값은 CI4 내부 테스트용 마이그레이션 폴더(Tests\Support)만 찾음.
    // null로 설정해야 우리 앱의 실제 마이그레이션(app/Database/Migrations)까지 전부 실행해줌.
    protected $namespace = null;

    public function testCanInsertAndFindUserInTestDatabase(): void
    {
        $model = new UserModel();

        $id = $model->insert([
            'email'    => 'setup-check@example.com',
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Setup Check',
        ]);

        $user = $model->find($id);

        $this->assertSame('setup-check@example.com', $user['email']);
    }
}
