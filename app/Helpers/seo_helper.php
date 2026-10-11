<?php

if (! function_exists('page_meta')) {
    /**
     * <head> 안에 들어갈 페이지 제목 / 설명 / 파비콘 / 공유 미리보기(OG) 태그를 한 번에 만들어준다.
     * 레이아웃(layout.php, user_layout.php, admin_layout.php)에서 호출하고, 각 화면은
     * $this->section('title') / $this->section('description')으로 자기 제목·설명만 넘기면 됨.
     *
     * - 제목: "요금제 | Linkr"처럼 화면 이름 뒤에 서비스 이름을 붙임. 화면 이름이 없으면(홈) 기본 문구.
     * - OG/트위터 태그: 카카오톡·슬랙 등에 주소를 붙여넣었을 때 뜨는 미리보기 카드용.
     * - $noindex: 로그인해야 보이는 화면(대시보드, 관리자 등)은 검색엔진에 노출되지 않게 함.
     */
    function page_meta(string $pageTitle = '', string $description = '', bool $noindex = false): string
    {
        $siteName    = 'Linkr';
        $fullTitle   = $pageTitle !== '' ? $pageTitle . ' | ' . $siteName : $siteName . ' – 긴 URL은 짧게, 클릭 통계는 한눈에';
        $description = $description !== ''
            ? $description
            : '긴 URL을 짧은 링크로 바꾸고 QR 코드와 클릭 통계까지 한 번에 관리하는 URL 단축 서비스. 비밀번호 보호, 만료일, 커스텀 도메인 지원.';

        $tags = [
            '<title>' . esc($fullTitle) . '</title>',
            '<meta name="description" content="' . esc($description) . '">',
            '<meta name="theme-color" content="#4f46e5">',
            '<link rel="icon" href="/favicon.ico" sizes="any">',
            '<link rel="icon" href="/favicon.svg" type="image/svg+xml">',
            '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
        ];

        if ($noindex) {
            $tags[] = '<meta name="robots" content="noindex, nofollow">';
        }

        $tags = array_merge($tags, [
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="' . $siteName . '">',
            '<meta property="og:locale" content="ko_KR">',
            '<meta property="og:title" content="' . esc($fullTitle) . '">',
            '<meta property="og:description" content="' . esc($description) . '">',
            // current_url()은 주소에 index.php가 붙어서, 기본 주소 + 현재 경로로 직접 만듦
            '<meta property="og:url" content="' . esc(base_url(uri_string())) . '">',
            '<meta property="og:image" content="' . esc(base_url('assets/img/og-image.png')) . '">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta name="twitter:card" content="summary_large_image">',
        ]);

        return implode("\n    ", $tags) . "\n";
    }
}
