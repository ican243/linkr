<?php
$isLoggedIn = (bool) session()->get('isLoggedIn');
helper('seo');
// 각 화면이 $this->section('title')로 넘긴 화면 이름. 없으면 컨트롤러가 넘긴 $title, 그것도 없으면 기본 제목.
$pageTitle = trim(strip_tags($this->renderSection('title'))) ?: (string) ($title ?? '');
$pageDescription = trim(strip_tags($this->renderSection('description')));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= page_meta($pageTitle, $pageDescription) ?>
    <!-- Bootswatch "Flatly" 테마 (부트스트랩 5.3 호환, MIT 라이선스 무료) -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <!-- 한글 웹폰트(Pretendard) + king 자체 테마(폰트/색상/여백/그림자 커스텀) -->
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
    <!-- 화면이 좁으면(휴대폰) 메뉴가 햄버거 버튼(☰) 안으로 접히고, 넓으면(PC) 한 줄로 펼쳐짐 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Linkr</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="메뉴 열기">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <div class="d-flex flex-column flex-lg-row">
                    <a href="/about" class="btn btn-link text-decoration-none text-start">서비스소개</a>
                    <a href="/pricing" class="btn btn-link text-decoration-none text-start">요금제</a>
                </div>
                <div class="ms-lg-auto d-flex flex-column flex-lg-row gap-2 mt-2 mt-lg-0 pb-2 pb-lg-0">
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
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
