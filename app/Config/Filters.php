<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        // CI 기본 보안 헤더 + HSTS(https일 때만) — app/Filters/SecureHeadersFilter.php 참고
        'secureheaders' => \App\Filters\SecureHeadersFilter::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'adminAuth'     => \App\Filters\AdminAuthFilter::class,
        'customDomain'  => \App\Filters\CustomDomainFilter::class,
        'planTransition' => \App\Filters\PlanTransitionFilter::class,
        'demoGuard'     => \App\Filters\DemoGuardFilter::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            // 'honeypot',
            // 커스텀 도메인(Host 헤더) 판별이 제일 먼저 이루어져야 함 — 우리 도메인이 아닌
            // Host로 들어온 요청은 인증된 커스텀 도메인이 아닌 이상 여기서 바로 걸러짐.
            'customDomain',
            // 로그인한 회원의 다운그레이드 예약(users.next_plan_at)이 지금 시점에 도달했으면
            // 여기서 바로 적용함("조회 시점 처리"). 비로그인 요청은 필터 안에서 바로 통과됨.
            'planTransition',
            // api/* 는 브라우저 세션이 아니라 API 키(Authorization: Bearer ...)로 인증하는
            // 외부 프로그램용 엔드포인트라, 세션 쿠키 기반 CSRF 토큰이 없어 여기서 검사를 뺌.
            'csrf' => ['except' => ['api/*']],
            // 'invalidchars',
        ],
        'after' => [
            // 'honeypot',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        // 체험 계정에서 막을 기능들 (자세한 규칙은 app/Filters/DemoGuardFilter.php)
        'demoGuard' => [
            'before' => [
                'account',
                'custom-domain', 'custom-domain/*',
                'api-keys', 'api-keys/*',
                'pricing/*',
                'payment/checkout', 'payment/success',
            ],
        ],
    ];
}
