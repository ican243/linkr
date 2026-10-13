<?php

namespace App\Libraries;

use App\Models\UserModel;

// 회원 탈퇴(본인) / 회원 삭제(관리자) 공통 처리 — 개인정보처리방침의 보관 규칙을 그대로 따름.
// - 링크, 클릭 기록, API 키: 바로 삭제
// - 결제 기록: 「전자상거래법」에 따라 5년 보관 → 결제 기록이 있으면 회원 줄은 남기되 개인정보를 모두 지움(익명화)
//   (5년이 지난 결제 기록과, 결제 기록이 다 지워진 탈퇴 회원 줄은 spark privacy:purge 크론이 정리)
// - 결제 기록이 없으면 회원 줄까지 완전히 삭제
class AccountDeletion
{
    public static function delete(int $userId): void
    {
        $db = db_connect();

        $db->transStart();

        // 링크를 지우면 그 링크의 클릭 기록도 FK CASCADE로 같이 지워짐
        $db->table('links')->where('user_id', $userId)->delete();
        $db->table('api_keys')->where('user_id', $userId)->delete();

        if ($db->table('payments')->where('user_id', $userId)->countAllResults() > 0) {
            self::anonymize($userId);
        } else {
            (new UserModel())->delete($userId);
        }

        $db->transComplete();
    }

    // 회원 줄은 남기고 개인정보만 지움. 이메일은 다시 로그인하거나 같은 이메일로 재가입하는 데 걸리지 않도록
    // 존재할 수 없는 주소(.invalid 도메인)로, 비밀번호는 아무도 모르는 무작위 값으로 바꿈.
    private static function anonymize(int $userId): void
    {
        db_connect()->table('users')->where('id', $userId)->update([
            'email'                     => 'withdrawn-' . $userId . '@withdrawn.invalid',
            'password'                  => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'name'                      => null,
            'google_id'                 => null,
            'naver_id'                  => null,
            'kakao_id'                  => null,
            'plan'                      => 'free',
            'plan_expires_at'           => null,
            'next_plan'                 => null,
            'next_plan_at'              => null,
            'custom_domain'             => null,
            'custom_domain_verified'    => 0,
            'custom_domain_verify_code' => null,
            'withdrawn_at'              => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s'),
        ]);
    }
}
