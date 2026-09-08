<?php $isAdminLoggedIn = (bool) session()->get('isAdminLoggedIn'); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? '관리자 - Linkr') ?></title>
    <!-- Bootswatch "Lux" 테마 (부트스트랩 5.3 호환, MIT 라이선스 무료) — 관리자 화면 전용 -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/lux/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <style>body { font-family: 'Pretendard Variable', Pretendard, -apple-system, sans-serif; }</style>
</head>
<body class="bg-dark-subtle">
    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand fw-bold">Linkr 관리자</span>
            <?php if ($isAdminLoggedIn) : ?>
                <div class="d-flex gap-2 ms-auto">
                    <a href="/admin/dashboard" class="btn btn-outline-light btn-sm">대시보드</a>
                    <a href="/admin/payments" class="btn btn-outline-light btn-sm">결제 관리</a>
                    <a href="/admin/settings" class="btn btn-outline-light btn-sm">설정</a>
                    <a href="/admin/logout" class="btn btn-outline-light btn-sm">관리자 로그아웃</a>
                </div>
            <?php endif ?>
        </div>
    </nav>

    <div class="container pb-5">
        <?= $this->renderSection('content') ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
