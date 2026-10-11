<?php

namespace App\Commands;

use App\Libraries\DemoAccount;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

// 체험 계정을 처음 상태(샘플 링크 + 30일치 클릭 기록)로 되돌린다. 체험 계정이 없으면 새로 만든다.
// 크론에 등록돼 있어서 매일 새벽 4시(한국 시간)에 자동 실행됨 — /etc/cron.d/king 참고.
//
// 사용법: php spark demo:reset
class ResetDemoAccount extends BaseCommand
{
    protected $group       = 'Linkr';
    protected $name        = 'demo:reset';
    protected $description = '체험 계정을 샘플 데이터가 들어있는 처음 상태로 되돌립니다.';

    public function run(array $params)
    {
        [$userId, $links, $clicks] = DemoAccount::reset();

        CLI::write(date('Y-m-d H:i:s') . " 체험 계정 초기화 완료 (회원 id={$userId}, 링크 {$links}개, 클릭 기록 {$clicks}건)", 'green');
    }
}
