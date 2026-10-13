<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

// 개인정보처리방침에 적은 보관 기간이 지난 정보를 정리한다 (/etc/cron.d/king 에서 매일 실행).
// ① 클릭 기록의 접속 정보(IP·기기 정보): 3개월이 지나면 지움
//    - 클릭 일시는 남겨둠 → 요금제 페이지의 "클릭 통계 보관기간 무제한"(클릭 수·날짜별 추이)은 그대로 유지
// ② 결제 기록: 5년이 지나면 삭제 (「전자상거래법」 보관 기간)
// ③ 탈퇴 회원: 결제 기록 때문에 남겨둔 익명 회원 줄은, 결제 기록이 모두 지워지면 함께 삭제
//
// 사용법: php spark privacy:purge
class PurgeExpiredData extends BaseCommand
{
    public const CLICK_INFO_MONTHS = 3;
    public const PAYMENT_YEARS     = 5;

    protected $group       = 'Linkr';
    protected $name        = 'privacy:purge';
    protected $description = '보관 기간이 지난 접속 기록(IP·기기 정보)과 결제 기록, 탈퇴 회원 정보를 정리합니다.';

    public function run(array $params)
    {
        $db = db_connect();

        $clickCutoff = date('Y-m-d H:i:s', strtotime('-' . self::CLICK_INFO_MONTHS . ' months'));
        $db->table('click_logs')
            ->where('clicked_at <', $clickCutoff)
            ->groupStart()->where('ip_address IS NOT NULL')->orWhere('user_agent IS NOT NULL')->groupEnd()
            ->update(['ip_address' => null, 'user_agent' => null]);
        $clicks = $db->affectedRows();

        $paymentCutoff = date('Y-m-d H:i:s', strtotime('-' . self::PAYMENT_YEARS . ' years'));
        $db->table('payments')->where('created_at <', $paymentCutoff)->delete();
        $payments = $db->affectedRows();

        $db->table('users')
            ->where('withdrawn_at IS NOT NULL')
            ->where('id NOT IN (SELECT user_id FROM ' . $db->prefixTable('payments') . ')', null, false)
            ->delete();
        $users = $db->affectedRows();

        CLI::write(date('Y-m-d H:i:s') . " 접속 정보 지움: {$clicks}건 · 결제 기록 삭제: {$payments}건 · 탈퇴 회원 정리: {$users}명", 'green');
    }
}
