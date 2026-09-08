<?php
$isAdminLoggedIn = (bool) session()->get('isAdminLoggedIn');
$currentPath     = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$navItems        = [
    ['href' => '/admin/dashboard', 'label' => '대시보드', 'icon' => 'bi-speedometer2'],
    ['href' => '/admin/members',   'label' => '회원',     'icon' => 'bi-people'],
    ['href' => '/admin/links',     'label' => '링크',     'icon' => 'bi-link-45deg'],
    ['href' => '/admin/payments',  'label' => '결제',     'icon' => 'bi-credit-card'],
    ['href' => '/admin/settings',  'label' => '설정',     'icon' => 'bi-gear'],
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? '관리자 - Linkr') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/pretendard@1.3.9/dist/web/static/pretendard.css" rel="stylesheet">
    <!-- 관리자 화면 전역에서 차트를 쓸 수 있도록 여기서 한 번만 불러옴. defer라서 파싱은
         안 막고, 실행은 DOMContentLoaded 직전으로 미뤄짐 — 각 화면의 차트 초기화 코드는
         반드시 DOMContentLoaded 안에서 실행해야 이 라이브러리 로드 순서와 항상 맞음. -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
    <style>
        :root {
            --admin-navy: #1e293b;
            --admin-navy-dark: #0f172a;
            --admin-blue: #2563eb;
            --admin-purple: #7c3aed;
            --admin-border: #e2e8f0;
            --admin-bg: #f8fafc;
            --admin-text: #1e293b;
            --admin-muted: #64748b;
        }
        body {
            font-family: 'Pretendard Variable', Pretendard, -apple-system, sans-serif;
            font-size: 14px;
            background: var(--admin-bg);
            color: var(--admin-text);
        }
        h1, h2, h3, h4, h5, h6 { font-weight: 700; }
        h1 { font-size: 1.3rem; }
        h2 { font-size: 1.1rem; }

        /* ---- 레이아웃: 좌측 사이드바 + 상단 헤더 ---- */
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 220px;
            flex-shrink: 0;
            background: var(--admin-navy-dark);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .admin-sidebar-brand {
            padding: 1.1rem 1.25rem;
            font-weight: 700;
            font-size: 1.05rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .admin-nav { padding: .75rem; display: flex; flex-direction: column; gap: 2px; }
        .admin-nav-link {
            display: flex; align-items: center; gap: .6rem;
            padding: .55rem .75rem;
            border-radius: 6px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: .875rem;
            transition: background .12s ease, color .12s ease;
        }
        .admin-nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .admin-nav-link.active { background: var(--admin-blue); color: #fff; }
        .admin-nav-link i { font-size: 1rem; width: 1.1rem; text-align: center; }

        .admin-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .admin-topbar {
            height: 56px; flex-shrink: 0;
            border-bottom: 1px solid var(--admin-border);
            background: #fff;
            display: flex; align-items: center;
            padding: 0 1.25rem;
            gap: 1rem;
        }
        .admin-topbar-email { color: var(--admin-muted); font-size: .85rem; }
        .admin-sidebar-toggle {
            border: 0; background: none; font-size: 1.25rem; color: var(--admin-text);
        }
        .admin-content { padding: 1.5rem; max-width: 1280px; width: 100%; margin: 0 auto; }

        /* ---- 카드 ---- */
        .admin-card {
            background: #fff;
            border: 1px solid var(--admin-border);
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        /* ---- 버튼 3종 ---- */
        .admin-btn { font-size: .85rem; padding: .4rem .85rem; border-radius: 6px; font-weight: 600; border: 1px solid transparent; }
        .admin-btn-primary { background: var(--admin-navy); color: #fff; }
        .admin-btn-primary:hover { background: var(--admin-navy-dark); color: #fff; }
        .admin-btn-secondary { background: #fff; color: var(--admin-text); border-color: var(--admin-border); }
        .admin-btn-secondary:hover { background: #f1f5f9; color: var(--admin-text); }
        .admin-btn-danger { background: transparent; color: #dc2626; border-color: transparent; }
        .admin-btn-danger:hover { background: #fef2f2; color: #dc2626; }

        /* ---- 테이블 ---- */
        .admin-table { font-size: .85rem; margin-bottom: 0; }
        .admin-table thead th {
            font-size: .75rem; text-transform: uppercase; letter-spacing: .03em;
            color: var(--admin-muted); font-weight: 600;
            border-bottom: 1px solid var(--admin-border);
            padding: .6rem .9rem;
        }
        .admin-table tbody td { padding: 0 .9rem; height: 48px; vertical-align: middle; border-bottom: 1px solid var(--admin-border); }
        .admin-table tbody tr:hover { background: #f8fafc; }
        .admin-table .cell-email { color: var(--admin-muted); }
        .admin-table .cell-name { font-weight: 600; }

        /* ---- pill 배지 ---- */
        .pill { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
        .pill-free { background: #f1f5f9; color: #475569; }
        .pill-pro { background: #dbeafe; color: #1d4ed8; }
        .pill-enterprise { background: #ede9fe; color: #6d28d9; }
        .pill-verified { background: #dcfce7; color: #15803d; }
        .pill-unverified { background: #f1f5f9; color: #64748b; }
        .pill-completed { background: #dcfce7; color: #15803d; }
        .pill-pending { background: #fef9c3; color: #854d0e; }
        .pill-failed { background: #fee2e2; color: #b91c1c; }
        .pill-other { background: #f1f5f9; color: #64748b; }
        .pill-expired { background: #fee2e2; color: #b91c1c; }
        .pill-soon { background: #fef9c3; color: #854d0e; }
        .pill-unlimited { background: #f1f5f9; color: #64748b; }
        .cell-sub { color: var(--admin-muted); font-size: .78rem; }

        /* ---- 통계 카드 ---- */
        .stat-card { padding: 1rem 1.1rem; }
        .stat-card-icon {
            width: 36px; height: 36px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; color: #fff; margin-bottom: .6rem;
        }
        .stat-card-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
        .stat-card-label { font-size: .78rem; color: var(--admin-muted); margin-top: .15rem; }
        .stat-card-delta { font-size: .75rem; font-weight: 600; margin-top: .4rem; }
        .stat-card-delta.up { color: #15803d; }
        .stat-card-delta.down { color: #b91c1c; }
        .stat-card-delta.flat { color: var(--admin-muted); }

        /* ---- 액션 드롭다운 ---- */
        .admin-action-btn {
            width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--admin-border);
            background: #fff; display: inline-flex; align-items: center; justify-content: center;
            color: var(--admin-muted);
        }
        .admin-action-btn:hover { background: #f1f5f9; }

        /* ---- 빈 상태 ---- */
        .admin-empty { text-align: center; padding: 3rem 1rem; color: var(--admin-muted); }
        .admin-empty i { font-size: 2rem; display: block; margin-bottom: .6rem; opacity: .5; }

        /* ---- 로그인 화면(사이드바 없는 상태) ---- */
        .admin-auth-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--admin-bg); }

        @media (max-width: 991.98px) {
            .admin-sidebar { position: fixed; left: 0; top: 0; z-index: 1040; transform: translateX(-100%); transition: transform .18s ease; }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<?php if (! $isAdminLoggedIn) : ?>
    <div class="admin-auth-shell">
        <?= $this->renderSection('content') ?>
    </div>
<?php else : ?>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-brand">Linkr 관리자</div>
            <nav class="admin-nav">
                <?php foreach ($navItems as $item) : ?>
                    <?php $active = str_starts_with($currentPath, $item['href']); ?>
                    <a href="<?= esc($item['href']) ?>" class="admin-nav-link<?= $active ? ' active' : '' ?>">
                        <i class="bi <?= esc($item['icon']) ?>"></i>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endforeach ?>
            </nav>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="admin-sidebar-toggle d-lg-none" id="adminSidebarToggle" aria-label="메뉴 열기">
                    <i class="bi bi-list"></i>
                </button>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <span class="admin-topbar-email"><?= esc(session()->get('admin_email')) ?></span>
                    <a href="/admin/logout" class="btn admin-btn admin-btn-secondary btn-sm">로그아웃</a>
                </div>
            </header>
            <main class="admin-content">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
<?php endif ?>

<!-- 공용 확인 모달: onclick="return confirm(...)" 대신, data-confirm 속성이 있는 폼은 전부 이걸 씀 -->
<div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body pt-4" id="genericConfirmText">정말 진행하시겠습니까?</div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn admin-btn admin-btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn admin-btn admin-btn-danger" id="genericConfirmBtn">확인</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var toggleBtn = document.getElementById('adminSidebarToggle');
    var sidebar   = document.getElementById('adminSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    }

    var modalEl = document.getElementById('genericConfirmModal');
    if (! modalEl) { return; }
    var modal = new bootstrap.Modal(modalEl);
    var pendingForm = null;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.hasAttribute && form.hasAttribute('data-confirm')) {
            e.preventDefault();
            pendingForm = form;
            document.getElementById('genericConfirmText').textContent = form.getAttribute('data-confirm');
            modal.show();
        }
    });

    document.getElementById('genericConfirmBtn').addEventListener('click', function () {
        modal.hide();
        if (pendingForm) {
            var f = pendingForm;
            pendingForm = null;
            f.submit();
        }
    });
})();
</script>
</body>
</html>
