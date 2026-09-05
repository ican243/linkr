<?php $isLoggedIn = (bool) session()->get('isLoggedIn'); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Linkr') ?></title>
    <!-- Bootswatch "Flatly" 테마 (부트스트랩 5.3 호환, MIT 라이선스 무료) -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <!-- 한글 웹폰트(Pretendard) + king 자체 테마(폰트/색상/여백/그림자 커스텀) -->
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand navbar-light bg-light border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Linkr</a>
            <a href="/about" class="btn btn-link text-decoration-none">서비스소개</a>
            <a href="/pricing" class="btn btn-link text-decoration-none">요금제</a>
            <div class="ms-auto d-flex gap-2">
                <?php if ($isLoggedIn) : ?>
                    <a href="/dashboard" class="btn btn-outline-primary btn-sm">내 대시보드</a>
                    <a href="/account" class="btn btn-outline-secondary btn-sm">계정 설정</a>
                    <a href="/logout" class="btn btn-outline-secondary btn-sm">로그아웃</a>
                <?php else : ?>
                    <a href="/auth/login" class="btn btn-outline-primary btn-sm">로그인</a>
                    <a href="/auth/register" class="btn btn-primary btn-sm">회원가입</a>
                <?php endif ?>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <?= $this->renderSection('content') ?>
    </div>

    <footer class="border-top py-4 text-center text-muted small">
        <div class="container">
            <div class="mb-2">Linkr &middot; <a href="/about">서비스소개</a> &middot; <a href="/pricing">요금제</a> &middot; <a href="/privacy">개인정보처리방침</a> &middot; <a href="/auth/login">로그인</a> &middot; <a href="/auth/register">회원가입</a></div>
            <div>© <?= date('Y') ?> Linkr. 개인 학습용 프로젝트입니다.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
