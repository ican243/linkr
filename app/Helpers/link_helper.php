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

            if (! empty($user) && ! empty($user['custom_domain']) && ! empty($user['custom_domain_verified'])) {
                return 'https://' . rtrim($user['custom_domain'], '/') . '/' . $link['short_code'];
            }
        }

        return base_url($link['short_code']);
    }
}