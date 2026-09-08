<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpiryFeaturesToLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('links', [
            // 프로/엔터프라이즈 전용: 만료됐을 때 안내 페이지 대신 이 주소로 보냄
            'fallback_url'        => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true, 'after' => 'expires_at'],
            // 엔터프라이즈 전용: 이 클릭 수에 도달하면 만료된 것과 동일하게 처리
            'max_clicks'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'fallback_url'],
            // 만료 3일 전 이메일 알림용 — 이메일 발송 기능은 아직 없어서(SMTP 미설정) 지금은 안 씀.
            // 나중에 중복 발송 방지용으로 쓸 컬럼만 미리 만들어둠.
            'expiry_notified_at'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'max_clicks'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('links', ['fallback_url', 'max_clicks', 'expiry_notified_at']);
    }
}
