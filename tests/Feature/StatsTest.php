<?php

use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class StatsTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;

    private function createUserWithLinkAndClicks(string $plan): array
    {
        $userId = (new UserModel())->insert([
            'email'    => "statstest-{$plan}@example.com",
            'password' => password_hash('Test1234!', PASSWORD_DEFAULT),
            'name'     => 'Stats Test',
            'plan'     => $plan,
        ]);

        $linkId = (new LinkModel())->insert([
            'user_id'      => $userId,
            'original_url' => 'https://example.com/stats-test',
            'short_code'   => "stats{$plan}",
        ]);

        $clickLogModel = new ClickLogModel();
        // 8일 전(오래된 기록)과 오늘(최근 기록) 하나씩 넣어서, 7일 컷오프가 실제로 걸러내는지 확인.
        $clickLogModel->insert([
            'link_id'    => $linkId,
            'clicked_at' => date('Y-m-d H:i:s', strtotime('-8 days')),
            'ip_address' => '1.1.1.1',
            'user_agent' => 'OldAgent',
        ]);
        $clickLogModel->insert([
            'link_id'    => $linkId,
            'clicked_at' => date('Y-m-d H:i:s'),
            'ip_address' => '2.2.2.2',
            'user_agent' => 'RecentAgent',
        ]);

        return ['userId' => $userId, 'code' => "stats{$plan}"];
    }

    public function testFreePlanOnlySeesRecentSevenDaysOfClicks(): void
    {
        ['userId' => $userId, 'code' => $code] = $this->createUserWithLinkAndClicks('free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get("/links/{$code}/stats");

        $result->assertOK();
        $result->assertDontSee('OldAgent');
        $result->assertSee('RecentAgent');
        $result->assertSee('최근 7일간의 클릭 기록만');
        $result->assertDontSee('CSV로 내려받기');
    }

    public function testProPlanSeesAllClicksAndCanExportCsv(): void
    {
        ['userId' => $userId, 'code' => $code] = $this->createUserWithLinkAndClicks('pro');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get("/links/{$code}/stats");

        $result->assertOK();
        $result->assertSee('OldAgent');
        $result->assertSee('RecentAgent');
        $result->assertSee('CSV로 내려받기');
    }

    public function testFreePlanCannotExportCsv(): void
    {
        ['userId' => $userId, 'code' => $code] = $this->createUserWithLinkAndClicks('free');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get("/links/{$code}/stats/export");

        $result->assertRedirectTo("/links/{$code}/stats");
        $result->assertSessionHas('error', 'CSV 내보내기는 프로 요금제부터 사용할 수 있습니다.');
    }

    public function testProPlanCsvExportContainsClickData(): void
    {
        ['userId' => $userId, 'code' => $code] = $this->createUserWithLinkAndClicks('pro');

        $result = $this->withSession(['isLoggedIn' => true, 'user_id' => $userId])
            ->get("/links/{$code}/stats/export");

        $result->assertOK();
        $result->assertHeader('Content-Disposition', 'attachment; filename="king_' . $code . '_clicks.csv"');
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('OldAgent', $body);
        $this->assertStringContainsString('RecentAgent', $body);
    }
}
