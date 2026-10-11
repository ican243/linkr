<?php
/*
 * 에러 화면 공통 틀 (404 / 400 / 500에서 같이 씀).
 * 에러 화면은 프로그램이 정상적으로 돌지 못한 상황에서도 떠야 하므로, 레이아웃(layout.php)이나
 * 헬퍼 함수에 기대지 않고 이 파일 하나만으로 그려지게 만듦. 디자인은 사이트와 같은 theme.css를 씀.
 *
 * 이 파일을 include하기 전에 $errorCode, $errorHeading, $errorMessage 세 변수를 정해두면 됨.
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $h($errorHeading) ?> | Linkr</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-light bg-light border-bottom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Linkr</a>
        </div>
    </nav>

    <main class="flex-grow-1 d-flex align-items-center">
        <div class="container text-center py-5" style="max-width:520px;">
            <div class="display-1 fw-bold mb-2" style="color:var(--king-primary);letter-spacing:-0.04em;"><?= $h($errorCode) ?></div>
            <h1 class="h3 mb-3"><?= $h($errorHeading) ?></h1>
            <p class="text-muted mb-4"><?= $h($errorMessage) ?></p>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                <a href="/" class="btn btn-primary">홈으로 가기</a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">이전 페이지</a>
            </div>
        </div>
    </main>

    <footer class="border-top py-4 text-center text-muted small">
        © <?= date('Y') ?> Linkr. 개인 학습용 프로젝트입니다.
    </footer>
</body>
</html>
