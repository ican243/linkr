<?php
helper('url');
// 이 레이아웃을 쓰는 화면은 전부 컨트롤러에서 이미 로그인 여부를 확인하고 난 뒤라 항상 로그인된 상태.
// 지금 주소가 어느 메뉴에 해당하는지 비교해서 그 메뉴만 강조 표시(active)하는 데 씀.
$currentPath = uri_string();
helper('seo');
$pageTitle = trim(strip_tags($this->renderSection('title'))) ?: (string) ($title ?? '');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // 로그인해야 보이는 화면이라 검색엔진에는 노출하지 않음(noindex) ?>
    <?= page_meta($pageTitle, '', true) ?>
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
    <?= $this->include('partials/demo_bar') ?>
    <!-- 휴대폰 화면에서만 보이는 상단 바: "메뉴" 버튼을 누르면 왼쪽에서 사이드바가 밀려 나옴 -->
    <header class="king-mobilebar d-flex d-md-none align-items-center justify-content-between px-3 py-2 sticky-top">
        <a href="/" class="fw-bold fs-5 text-decoration-none">Linkr</a>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar"
                aria-controls="userSidebar" aria-label="메뉴 열기">☰ 메뉴</button>
    </header>

    <div class="d-flex flex-column flex-md-row min-vh-100">
        <!-- 사이드바: PC(md 이상)에선 왼쪽에 항상 보이고, 휴대폰에선 숨어 있다가 메뉴 버튼으로 열리는 서랍(offcanvas)이 됨 -->
        <nav class="king-sidebar offcanvas-md offcanvas-start p-3 d-flex flex-column" id="userSidebar" tabindex="-1" aria-label="회원 메뉴">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <a href="/" class="d-block fw-bold fs-4 text-decoration-none">Linkr</a>
                <button type="button" class="btn btn-outline-secondary btn-sm d-md-none" data-bs-dismiss="offcanvas" data-bs-target="#userSidebar" aria-label="메뉴 닫기">✕ 닫기</button>
            </div>

            <ul class="nav nav-pills flex-column mb-auto gap-1">
                <li class="nav-item">
                    <a href="/dashboard" class="nav-link <?= $currentPath === 'dashboard' ? 'active' : '' ?>">대시보드</a>
                </li>
                <li class="nav-item">
                    <a href="/shorten" class="nav-link <?= $currentPath === 'shorten' ? 'active' : '' ?>">새 링크 만들기</a>
                </li>
                <li class="nav-item">
                    <a href="/links" class="nav-link <?= $currentPath === 'links' ? 'active' : '' ?>">링크 관리</a>
                </li>
                <li class="nav-item">
                    <a href="/qr" class="nav-link <?= $currentPath === 'qr' ? 'active' : '' ?>">QR 코드</a>
                </li>
                <li class="nav-item">
                    <a href="/stats" class="nav-link <?= $currentPath === 'stats' ? 'active' : '' ?>">통계</a>
                </li>
                <li class="nav-item">
                    <a href="/api-keys" class="nav-link <?= $currentPath === 'api-keys' ? 'active' : '' ?>">API 키</a>
                </li>
                <li class="nav-item">
                    <a href="/payment/history" class="nav-link <?= $currentPath === 'payment/history' ? 'active' : '' ?>">결제 내역</a>
                </li>
                <li class="nav-item">
                    <a href="/custom-domain" class="nav-link <?= $currentPath === 'custom-domain' ? 'active' : '' ?>">커스텀 도메인</a>
                </li>
                <li class="nav-item">
                    <a href="/account" class="nav-link <?= $currentPath === 'account' ? 'active' : '' ?>">계정 설정</a>
                </li>
            </ul>

            <hr>
            <div class="small text-muted mb-2 text-truncate"><?= esc(session()->get('user_email')) ?></div>
            <form action="/logout" method="post"><?= csrf_field() ?><button type="submit" class="btn btn-outline-secondary btn-sm w-100">로그아웃</button></form>
        </nav>

        <!-- 본문 -->
        <main class="king-main flex-grow-1 p-4">
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
