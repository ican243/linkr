<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('demo-preview', 'Home::previewShorten');
$routes->get('pricing', 'Pricing::index');
$routes->get('privacy', 'Privacy::index');
$routes->get('about', 'About::index');

// 회원가입 / 로그인 (일반 사용자, users 테이블)
// me2.to의 URL 구조(auth/register, auth/login)에 맞춤 + 소셜 로그인(auth/google 등)과도 통일됨
$routes->get('auth/register', 'Auth::registerForm');
$routes->post('auth/register', 'Auth::register');
$routes->get('auth/login', 'Auth::loginForm');
$routes->post('auth/login', 'Auth::login');
$routes->get('logout', 'Auth::logout');

// 예전 주소(/register, /login)로 들어와도 새 주소로 자동 이동시켜줌 (북마크 등 대비)
$routes->get('register', static fn () => redirect()->to('/auth/register'));
$routes->get('login', static fn () => redirect()->to('/auth/login'));

// 구글 소셜 로그인 (구글 콘솔에 등록한 콜백 주소와 반드시 똑같아야 함)
$routes->get('auth/google', 'Auth::googleLogin');
$routes->get('auth/google/callback', 'Auth::googleCallback');

// 네이버 소셜 로그인 (네이버 개발자센터에 등록한 콜백 주소와 반드시 똑같아야 함)
$routes->get('auth/naver', 'Auth::naverLogin');
$routes->get('auth/naver/callback', 'Auth::naverCallback');

// 카카오 소셜 로그인 (카카오 개발자센터에 등록한 콜백 주소와 반드시 똑같아야 함)
$routes->get('auth/kakao', 'Auth::kakaoLogin');
$routes->get('auth/kakao/callback', 'Auth::kakaoCallback');

// admin 영역
// /admin 만 딱 치고 들어왔을 때는 안내 없이 404가 뜨던 문제 -> /admin/login으로 보내줌
$routes->get('admin', static fn () => redirect()->to('/admin/login'));

// 로그인 화면(login)은 필터를 걸면 안 됨 — 로그인 안 한 사람을 막는 필터인데,
// 로그인 페이지까지 막아버리면 아무도 로그인하러 들어갈 수 없게 됨.
// 그래서 실제 관리자 화면(dashboard)에만 개별적으로 adminAuth 필터를 적용함.
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('login', 'Auth::loginForm');
    $routes->post('login', 'Auth::login');
    $routes->get('logout', 'Auth::logout');
    $routes->get('dashboard', 'Dashboard::index', ['filter' => 'adminAuth']);
    $routes->get('members', 'Dashboard::members', ['filter' => 'adminAuth']);
    $routes->get('links', 'Dashboard::links', ['filter' => 'adminAuth']);
    $routes->get('users/(:num)', 'Dashboard::show/$1', ['filter' => 'adminAuth']);
    $routes->post('users/(:num)/plan', 'Dashboard::updateUserPlan/$1', ['filter' => 'adminAuth']);
    $routes->post('users/(:num)/verify-domain', 'Dashboard::verifyUserDomain/$1', ['filter' => 'adminAuth']);
    $routes->post('users/(:num)/password', 'Dashboard::updateUserPassword/$1', ['filter' => 'adminAuth']);
    $routes->post('users/(:num)/delete', 'Dashboard::deleteUser/$1', ['filter' => 'adminAuth']);
    $routes->post('links/(:num)/delete', 'Dashboard::deleteLink/$1', ['filter' => 'adminAuth']);
    $routes->get('settings', 'Settings::form', ['filter' => 'adminAuth']);
    $routes->post('settings', 'Settings::update', ['filter' => 'adminAuth']);
    $routes->get('payments', 'Payments::index', ['filter' => 'adminAuth']);
    $routes->post('payments/(:num)/test-complete', 'Payments::markTestCompleted/$1', ['filter' => 'adminAuth']);
    $routes->post('payments/(:num)/refund', 'Payments::refund/$1', ['filter' => 'adminAuth']);
    $routes->post('payments/(:num)/status', 'Payments::updateStatus/$1', ['filter' => 'adminAuth']);
    $routes->post('payments/(:num)/memo', 'Payments::updateMemo/$1', ['filter' => 'adminAuth']);
});

// URL 단축 핵심 기능 (로그인 필요 — links.user_id가 필수값이라 로그인 없이는 링크를 만들 수 없음)
$routes->get('shorten', 'Link::form');
$routes->post('shorten', 'Link::create');

// 사용자 대시보드 (내가 만든 링크 목록)
$routes->get('dashboard', '\App\Controllers\User\Dashboard::index');

// 계정 관리 (이름 수정, 비밀번호 변경)
$routes->get('account', 'Account::form');
$routes->post('account', 'Account::update');

// 커스텀 도메인 설정 (로그인 필요). 'custom-domain'은 1단계 경로라 아래
// 와일드카드보다 반드시 위에 있어야 함.
$routes->get('custom-domain', 'CustomDomain::form');
$routes->post('custom-domain', 'CustomDomain::save');
$routes->post('custom-domain/verify', 'CustomDomain::verify');
$routes->post('custom-domain/remove', 'CustomDomain::remove');

// 링크 관리 (검색/필터/정렬 가능한 전체 목록)
$routes->get('links', '\App\Controllers\User\Links::index');

// 통합 통계 대시보드 (전체 링크 합산 통계 + 인기 URL Top 5)
$routes->get('stats', '\App\Controllers\User\Statistics::index');

// API 키 관리 화면 (로그인 필요, 브라우저에서 발급/삭제)
$routes->get('api-keys', '\App\Controllers\User\ApiKeys::index');
$routes->post('api-keys', '\App\Controllers\User\ApiKeys::create');
$routes->post('api-keys/(:num)/delete', '\App\Controllers\User\ApiKeys::delete/$1');

// 외부 프로그램용 REST API. 로그인 세션이 아니라 API 키(Authorization: Bearer ...)로 인증하므로
// 브라우저 세션 기반 CSRF 검사 대상에서 제외해야 함 (Filters.php의 'except' 설정 참고).
$routes->post('api/v1/shorten', '\App\Controllers\Api\Links::create');
$routes->get('api/v1/links', '\App\Controllers\Api\Links::index');
$routes->delete('api/v1/links/(:segment)', '\App\Controllers\Api\Links::delete/$1');

// 링크별 클릭 통계 (내 링크만 조회 가능, 컨트롤러에서 소유자 확인)
$routes->get('links/(:segment)/stats', 'Stats::show/$1');
$routes->get('links/(:segment)/stats/export', 'Stats::exportCsv/$1');

// 링크 수정/삭제 (내 링크만 가능, 컨트롤러에서 소유자 확인)
$routes->get('links/(:segment)/edit', 'Link::editForm/$1');
$routes->post('links/(:segment)/edit', 'Link::update/$1');
$routes->post('links/(:segment)/delete', 'Link::delete/$1');
$routes->post('links/(:segment)/reactivate', 'Link::reactivate/$1');

// QR 코드 관리 화면 (내 링크들을 QR 격자로 보여줌)
$routes->get('qr', '\App\Controllers\User\QrCodes::index');

// QR 코드 이미지 (예: /qr/aFbkd0 -> PNG 이미지 응답). 경로가 2단계(qr/코드)라
// 위에 있는 1단계짜리 '/qr'이나 아래 1단계짜리 와일드카드(:segment)와 겹치지 않지만,
// 다른 구체적 라우트들과 같이 위쪽에 모아두는 게 읽기 편해서 여기 둠.
$routes->get('qr/(:segment)', 'Qr::show/$1');

// 단축 주소 리다이렉트. king도메인/아무코드 형태를 전부 받아야 해서
// 반드시 다른 모든 라우트보다 아래(맨 마지막)에 둬야 함.
// 위에 있는 라우트일수록 먼저 매칭되므로, 이 라우트를 위쪽에 두면
// /login, /admin 같은 진짜 경로까지 전부 "단축코드"로 오인해서 가로채버림.
$routes->get('(:segment)', 'Link::redirect/$1');
$routes->post('(:segment)', 'Link::verifyPassword/$1');

// 결제 (프로 요금제 결제 화면)
$routes->get('payment/checkout', 'Payment::checkout');

// 토스 결제 성공 시 돌아오는 주소, 여기서 실제 승인까지 처리 완료함
$routes->get('payment/success', 'Payment::success');

// 결제 실패/취소 시 돌아오는 주소. checkout.php의 failUrl.
$routes->get('payment/fail', 'Payment::fail');

// 결제 내역 화면 (로그인한 회원이 자기 결제 기록 조회)
$routes->get('payment/history', '\App\Controllers\User\Payments::index');