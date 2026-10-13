<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

// 쌓이기만 하는 파일 정리 (/etc/cron.d/king 에서 매일 실행)
// ① 오류 로그(writable/logs/log-날짜.log): 30일 지난 것 삭제
// ② 크론 기록(writable/logs/cron-*.log): 한 파일에 계속 이어 쓰므로, 너무 커지면 최근 1,000줄만 남김
// ③ 시도 횟수 제한 기록(writable/cache/throttler_*): 하루 지난 것 삭제 (제한 시간은 길어야 1시간이라 이미 쓸모없음)
//
// 사용법: php spark logs:cleanup
//   (-logdir, -cachedir: 다른 폴더를 대상으로 돌릴 때 — 테스트에서 실제 로그를 건드리지 않으려고 씀)
class CleanupLogs extends BaseCommand
{
    public const LOG_DAYS       = 30;
    public const CRON_LOG_LINES = 1000;
    private const CRON_LOG_MAX_BYTES = 512 * 1024;

    protected $group       = 'Linkr';
    protected $name        = 'logs:cleanup';
    protected $description = '30일 지난 오류 로그, 너무 커진 크론 기록, 지난 시도 횟수 제한 기록을 정리합니다.';
    protected $options     = [
        '-logdir'   => '정리할 로그 폴더 (기본: writable/logs)',
        '-cachedir' => '정리할 캐시 폴더 (기본: writable/cache)',
    ];

    public function run(array $params)
    {
        $logDir   = rtrim((string) ($params['logdir'] ?? CLI::getOption('logdir') ?? WRITEPATH . 'logs'), '/') . '/';
        $cacheDir = rtrim((string) ($params['cachedir'] ?? CLI::getOption('cachedir') ?? WRITEPATH . 'cache'), '/') . '/';

        $logs = $this->deleteOlderThan(glob($logDir . 'log-*.log') ?: [], self::LOG_DAYS * 86400);

        $trimmed = 0;
        foreach (glob($logDir . 'cron-*.log') ?: [] as $file) {
            if (filesize($file) > self::CRON_LOG_MAX_BYTES) {
                $lines = file($file);
                file_put_contents($file, implode('', array_slice($lines, -self::CRON_LOG_LINES)), LOCK_EX);
                $trimmed++;
            }
        }

        $cache = $this->deleteOlderThan(glob($cacheDir . 'throttler_*') ?: [], 86400);

        CLI::write(date('Y-m-d H:i:s') . " 오류 로그 삭제: {$logs}개 · 크론 기록 줄임: {$trimmed}개 · 횟수 제한 기록 삭제: {$cache}개", 'green');
    }

    private function deleteOlderThan(array $files, int $seconds): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < time() - $seconds && unlink($file)) {
                $count++;
            }
        }

        return $count;
    }
}
