<?php
helper('url');
// 이 레이아웃을 쓰는 화면은 전부 컨트롤러에서 이미 로그인 여부를 확인하고 난 뒤라 항상 로그인된 상태.
// 지금 주소가 어느 메뉴에 해당하는지 비교해서 그 메뉴만 강조 표시(active)하는 데 씀.
$currentPath = uri_string();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Linkr') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="d-flex flex-column flex-md-row min-vh-100">
        <!-- 사이드바 -->
        <nav class="king-sidebar p-3 d-flex flex-column" style="width: 100%; max-width: 220px; flex-shrink: 0;">
            <a href="/" class="d-block fw-bold fs-4 mb-4 text-decoration-none">Linkr</a>

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
            <a href="/logout" class="btn btn-outline-secondary btn-sm w-100">로그아웃</a>
        </nav>

        <!-- 본문 -->
        <main class="flex-grow-1 p-4">
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
