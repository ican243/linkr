<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? '') ?></title>
    <!--
        화이트라벨링(엔터프라이즈 전용) 레이아웃.
        king 로고/네비게이션/푸터/광고 문구를 전부 뺀 빈 껍데기.
        엔터프라이즈 회원이 만든 링크를 클릭한 "그 회원의 방문자"에게 보여주는 화면이라,
        king 브랜딩이나 king 회원가입/요금제 유도 문구가 노출되면 안 됨.
        디자인 톤(폰트/색상)은 동일하게 유지해서 어색하게 다르게 보이지 않게 함.
    -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <?= $this->renderSection('content') ?>
    </div>
</body>
</html>
