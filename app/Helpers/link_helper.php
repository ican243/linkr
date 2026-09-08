<?php

use App\Models\UserModel;

if (! function_exists('short_url')) {
    /**
     * 링크 배열(short_code, user_id 포함)을 받아서 단축 URL 문자열을 만든다.
     * - 소유자가 커스텀 도메인을 등록했고 DNS 인증까지 완료했으면 그 도메인을 사용.
     * - 그 외에는 기존처럼 기본 도메인(base_url())을 사용.
     */
    function short_url(array $link): string
    {
        static $userCache = [];

        $userId = $link['user_id'] ?? null;

        if ($userId !== null) {
            if (! array_key_exists($userId, $userCache)) {
                $userCache[$userId] = (new UserModel())->find($userId);
            }

            $user = $userCache[$userId];

            // SSL은 아직 지원 전이라(커스텀 도메인에 인증서가 없음) 당분간 http로만 생성함.
            // SSL 붙이면 이 부분만 https로 바꾸면 됨.
            if (! empty($user) && ! empty($user['custom_domain']) && ! empty($user['custom_domain_verified'])) {
                return 'http://' . rtrim($user['custom_domain'], '/') . '/' . $link['short_code'];
            }
        }

        return base_url($link['short_code']);
    }
}

if (! function_exists('short_url_prefix')) {
    /**
     * short_url()과 같은 도메인 판단 로직인데, 아직 short_code가 없는 시점(단축 폼 화면)에서
     * "이 주소 뒤에 코드가 붙습니다"를 보여주기 위한 prefix만 반환.
     */
    function short_url_prefix(?array $user): string
    {
        if (! empty($user) && ! empty($user['custom_domain']) && ! empty($user['custom_domain_verified'])) {
            return 'http://' . rtrim($user['custom_domain'], '/') . '/';
        }

        return base_url();
    }
}

if (! function_exists('kst_input_to_utc')) {
    /**
     * <input type="datetime-local">에서 온 문자열(예: "2026-09-08T15:00")은 타임존 정보가 없고,
     * 사용자는 이걸 한국시간(Asia/Seoul) 기준으로 입력한 것임. 서버는 UTC로 돌아가므로,
     * 저장 전에 반드시 이 함수로 UTC로 변환해야 시각 비교(만료 체크 등)가 정확해짐.
     * 형식이 이상하면(빈 문자열 등) null을 반환.
     */
    function kst_input_to_utc(string $localDateTime): ?string
    {
        if ($localDateTime === '') {
            return null;
        }

        try {
            $dt = new DateTime($localDateTime, new DateTimeZone('Asia/Seoul'));
            $dt->setTimezone(new DateTimeZone('UTC'));

            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}

if (! function_exists('utc_to_kst_local')) {
    /**
     * DB에 UTC로 저장된 시각을, 사람이 보기 편하게(또는 <input type="datetime-local">에 다시
     * 채워넣기 좋게) 한국시간 문자열로 변환.
     */
    function utc_to_kst_local(string $utcDateTime, string $format = 'Y-m-d\TH:i'): string
    {
        $dt = new DateTime($utcDateTime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Seoul'));

        return $dt->format($format);
    }
}