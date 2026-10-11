<?php

namespace App\Commands;

use App\Libraries\PlanTransition;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

// 예약된 다운그레이드 중 시점이 지난 것들을 한 번에 적용한다.
// 로그인 요청마다 자동으로 적용되긴 하지만(PlanTransitionFilter), 한동안 접속을 안 한
// 회원은 그걸로 처리가 안 되므로, 크론으로 매시간 실행해서 그런 경우까지 놓치지 않고 정리함
// (/etc/cron.d/king 참고).
//
// 사용법: php spark plan:apply-downgrades
class ApplyPlanDowngrades extends BaseCommand
{
    protected $group       = 'Linkr';
    protected $name        = 'plan:apply-downgrades';
    protected $description = '예약 시점이 지난 요금제 다운그레이드를 전부 적용합니다.';

    public function run(array $params)
    {
        $count = PlanTransition::applyAllDue();

        CLI::write("적용된 다운그레이드: {$count}건", 'green');
    }
}
