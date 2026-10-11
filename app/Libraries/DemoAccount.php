<?php

namespace App\Libraries;

use App\Models\LinkModel;
use App\Models\UserModel;

// 체험 계정(포트폴리오 방문자들이 회원가입 없이 같이 쓰는 계정 하나) 관리.
// - 비밀번호는 아무도 모르는 무작위 값이라, 로그인 화면의 "체험 계정으로 둘러보기" 버튼으로만 들어올 수 있음.
// - 모든 기능을 보여주려고 엔터프라이즈 요금제로 설정하고, 보기 좋은 샘플 링크와 30일치 클릭 기록을 채워둠.
// - 누가 링크를 지우거나 이상하게 바꿔놔도 매일 새벽 4시(한국)에 reset()이 처음 상태로 되돌림
//   (크론: php spark demo:reset).
class DemoAccount
{
    public const EMAIL = 'demo@linkr.local';
    public const NAME  = '체험계정';

    // 체험 계정이 하루에 새로 만들 수 있는 링크 수 — 누구나 쓰는 계정이라 스팸/피싱 링크 대량 생성 방지
    public const DAILY_LINK_LIMIT = 20;

    // 샘플 비밀번호 보호 링크의 비밀번호 (링크 제목에 적어둬서 방문자가 직접 체험해볼 수 있게 함)
    public const SAMPLE_LINK_PASSWORD = 'demo1234';

    public static function userId(): ?int
    {
        $user = (new UserModel())->where('email', self::EMAIL)->first();

        return $user ? (int) $user['id'] : null;
    }

    public static function isDemoUser(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return (new UserModel())->where('id', $userId)->where('email', self::EMAIL)->countAllResults() > 0;
    }

    // 지금 로그인한 사람이 체험 계정인지
    public static function isDemoSession(): bool
    {
        if (! session()->get('isLoggedIn')) {
            return false;
        }

        return (bool) session()->get('is_demo') || self::isDemoUser((int) session()->get('user_id'));
    }

    // 체험 계정을 처음 상태로 되돌린다(없으면 새로 만듦). 반환값: [회원 id, 링크 수, 클릭 기록 수]
    public static function reset(): array
    {
        $db        = db_connect();
        $userModel = new UserModel();
        $user      = $userModel->where('email', self::EMAIL)->first();

        $db->transStart();

        if (! $user) {
            $userId = $userModel->insert([
                'email'    => self::EMAIL,
                'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                'name'     => self::NAME,
                'plan'     => 'enterprise',
            ]);
        } else {
            $userId = (int) $user['id'];
        }

        // 회원 정보 초기화: 엔터프라이즈(기간 제한 없음), 예약/커스텀 도메인 없음
        $db->table('users')->where('id', $userId)->update([
            'name'                      => self::NAME,
            'plan'                      => 'enterprise',
            'plan_expires_at'           => null,
            'next_plan'                 => null,
            'next_plan_at'              => null,
            'custom_domain'             => null,
            'custom_domain_verified'    => 0,
            'custom_domain_verify_code' => null,
        ]);

        // 방문자들이 만든 링크/키/결제 기록 전부 삭제 (클릭 기록은 링크 삭제 시 FK CASCADE로 같이 지워짐)
        $db->table('links')->where('user_id', $userId)->delete();
        $db->table('api_keys')->where('user_id', $userId)->delete();
        $db->table('payments')->where('user_id', $userId)->delete();

        [$linkCount, $clickCount] = self::seedSampleLinks($userId);

        $db->transComplete();

        return [$userId, $linkCount, $clickCount];
    }

    // 대시보드/통계 화면이 비어 보이지 않게 샘플 링크와 최근 30일 클릭 기록을 만든다.
    private static function seedSampleLinks(int $userId): array
    {
        $db        = db_connect();
        $linkModel = new LinkModel();
        $now       = time();

        // [제목, 원본 주소, 며칠 전에 만들었는지, 하루 평균 클릭 수, 추가 옵션]
        $samples = [
            ['가을 시즌 프로모션 랜딩', 'https://www.naver.com', 28, 9, []],
            ['신제품 런칭 이벤트 페이지', 'https://www.youtube.com', 21, 6, []],
            ['인스타그램 프로필 링크', 'https://www.instagram.com', 30, 4, []],
            ['회사 기술 블로그', 'https://github.com', 18, 3, []],
            ['고객 만족도 설문 (비밀번호: ' . self::SAMPLE_LINK_PASSWORD . ')', 'https://www.google.com/forms/about/', 6, 2,
                ['password' => password_hash(self::SAMPLE_LINK_PASSWORD, PASSWORD_DEFAULT)]],
            ['블랙프라이데이 사전 알림 (곧 만료)', 'https://ko.wikipedia.org/wiki/블랙_프라이데이', 4, 5,
                ['expires_at' => date('Y-m-d H:i:s', $now + 2 * 86400)]],
            ['선착순 쿠폰 (100회 클릭 제한)', 'https://www.kakaocorp.com', 2, 0,
                ['max_clicks' => 100, 'fallback_url' => 'https://www.naver.com']],
            ['지난 여름 이벤트 (만료됨)', 'https://www.notion.so', 25, 0,
                ['expires_at' => date('Y-m-d H:i:s', $now - 3 * 86400), 'fallback_url' => 'https://github.com']],
        ];

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; SM-S921N) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 KAKAOTALK 10.8.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
        ];

        $clickTotal = 0;

        foreach ($samples as [$title, $url, $daysAgo, $perDay, $extra]) {
            $createdAt = date('Y-m-d H:i:s', $now - $daysAgo * 86400);

            $db->table('links')->insert(array_merge([
                'user_id'      => $userId,
                'original_url' => $url,
                'title'        => $title,
                'short_code'   => $linkModel->generateUniqueShortCode(),
                'click_count'  => 0,
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ], $extra));
            $linkId = (int) $db->insertID();

            // 만든 날부터 오늘까지 하루씩 클릭 기록 생성 (최근일수록, 평일일수록 조금 더 많게)
            $logs = [];
            if (isset($extra['max_clicks'])) {
                $clicks = (int) $extra['max_clicks'] - 13; // 거의 다 찬 상태(87/100)로 보여주기
                for ($i = 0; $i < $clicks; $i++) {
                    $logs[] = self::clickRow($linkId, $now - random_int(0, $daysAgo * 86400), $userAgents);
                }
            } else {
                for ($d = $daysAgo; $d >= 0; $d--) {
                    $dayStart = $now - $d * 86400;
                    $weekend  = in_array((int) date('N', $dayStart), [6, 7], true);
                    $base     = $perDay * ($weekend ? 0.6 : 1.0) * (1 + (($daysAgo - $d) / max($daysAgo, 1)) * 0.5);
                    $count    = max(0, (int) round($base + random_int(-2, 2)));

                    // 만료된 링크는 만료 시점 이후엔 클릭 기록이 없어야 자연스러움
                    if (isset($extra['expires_at']) && $dayStart > strtotime($extra['expires_at'])) {
                        $count = 0;
                    }

                    for ($i = 0; $i < $count; $i++) {
                        $logs[] = self::clickRow($linkId, min($now, $dayStart + random_int(0, 86399)), $userAgents);
                    }
                }

                // 만료된 샘플은 예전에 꽤 클릭됐던 링크처럼 보이게 기록을 몰아서 넣음
                if (isset($extra['expires_at']) && strtotime($extra['expires_at']) < $now) {
                    for ($i = 0; $i < 140; $i++) {
                        $logs[] = self::clickRow($linkId, random_int(strtotime($createdAt), strtotime($extra['expires_at'])), $userAgents);
                    }
                }
            }

            if ($logs !== []) {
                $db->table('click_logs')->insertBatch($logs);
            }

            $db->table('links')->where('id', $linkId)->update(['click_count' => count($logs)]);
            $clickTotal += count($logs);
        }

        return [count($samples), $clickTotal];
    }

    private static function clickRow(int $linkId, int $timestamp, array $userAgents): array
    {
        return [
            'link_id'    => $linkId,
            'clicked_at' => date('Y-m-d H:i:s', $timestamp),
            // 실제 방문자 IP가 아닌 문서/예시용 IP 대역(203.0.113.0/24) — 실제 클릭처럼 마지막 자리는 가린 형태
            'ip_address' => '203.0.113.0',
            'user_agent' => $userAgents[array_rand($userAgents)],
        ];
    }
}
