<?php

namespace App\Libraries;

use CodeIgniter\Throttle\Throttler;

// 같은 사람(IP 등)이 짧은 시간에 너무 많이 요청하는 걸 막는 횟수 제한.
// CI 기본 Throttler(토큰 버킷)를 쓰고, 캐시 키에 못 쓰는 특수문자(@, :, / 등)가 있을 수 있어서 대상은 해시로 바꿈.
// 예) RateLimit::allow('qr', $ip, 60, 60) → 같은 IP는 60초에 60번까지 허용
class RateLimit
{
    private static ?Throttler $throttler = null;

    // 허용 범위 안이면 true, 초과했으면 false
    public static function allow(string $name, string $subject, int $capacity, int $seconds): bool
    {
        // 매번 지금의 캐시 서비스로 만듦 (테스트마다 캐시가 새로 바뀌어도 이전 테스트의 횟수가 남지 않게)
        self::$throttler = new Throttler(service('cache'));

        return self::$throttler->check($name . '_' . md5($subject), $capacity, $seconds) !== false;
    }

    // 초과했을 때 몇 초 뒤에 다시 시도하면 되는지 (Retry-After 헤더용)
    public static function retryAfter(): int
    {
        return max(1, self::$throttler?->getTokenTime() ?? 1);
    }
}
