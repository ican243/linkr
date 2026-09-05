<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>

<!-- 통계 카드 3개: 총 링크 개수 / 총 클릭 수 / 최고 클릭 링크 -->
<div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
    <div class="col">
        <div class="card h-100 p-3">
            <div class="text-muted small">링크 총 개수</div>
            <div class="fs-2 fw-bold"><?= (int) $totalLinks ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 p-3">
            <div class="text-muted small">총 클릭 수</div>
            <div class="fs-2 fw-bold"><?= (int) $totalClicks ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 p-3">
            <div class="text-muted small">최고 클릭 링크</div>
            <?php if ($topLink) : ?>
                <div class="fs-5 fw-bold text-truncate">
                    <?= ! empty($topLink['title']) ? esc($topLink['title']) : esc($topLink['short_code']) ?>
                </div>
                <div class="text-muted small"><?= (int) $topLink['click_count'] ?>회 클릭</div>
            <?php else : ?>
                <div class="fs-5 text-muted">아직 없음</div>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- 최근 7일 일별 클릭 추이 그래프 -->
<div class="card p-3 mb-4">
    <h2 class="h6 mb-3">최근 7일 일별 클릭 추이</h2>
    <canvas id="dailyClicksChart" height="80"></canvas>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0">대시보드</h1>
    <a href="/shorten" class="btn btn-primary">+ 새 링크 만들기</a>
</div>

<p class="text-muted mb-4">
    현재 요금제: <strong><?= esc(ucfirst($plan)) ?></strong>
    <?php if ($plan === 'free') : ?>
        — 이번 달 <?= (int) $usedThisMonth ?> / <?= (int) $monthlyLimit ?>개 사용
        <?php if ($usedThisMonth >= $monthlyLimit) : ?>
            <span class="badge bg-danger">한도 도달</span>
        <?php endif ?>
    <?php else : ?>
        — 링크 개수 무제한
    <?php endif ?>
</p>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">최근 링크</h2>
    <a href="/links" class="btn btn-sm btn-outline-secondary">전체 보기</a>
</div>

<?php $recentLinks = array_slice($links, 0, 5); ?>
<?php if (empty($recentLinks)) : ?>
    <p>아직 만든 링크가 없습니다. <a href="/shorten">지금 만들어보세요</a>.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>제목</th>
                    <th>단축 주소</th>
                    <th>원본 주소</th>
                    <th>클릭 수</th>
                    <th>생성일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLinks as $link) : ?>
                    <tr>
                        <td><?= ! empty($link['title']) ? esc($link['title']) : '<span class="text-muted">—</span>' ?></td>
                        <td><a href="<?= esc(base_url($link['short_code'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc(base_url($link['short_code'])) ?></a></td>
                        <td class="text-truncate" style="max-width:250px;"><?= esc($link['original_url']) ?></td>
                        <td><?= (int) $link['click_count'] ?></td>
                        <td><?= esc($link['created_at']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<!-- Chart.js: 그래프 전용 무료 오픈소스 라이브러리 (CDN 1줄, 이 화면에서만 로드) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('dailyClicksChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dailyStats, 'label')) ?>,
        datasets: [{
            label: '클릭 수',
            data: <?= json_encode(array_column($dailyStats, 'count')) ?>,
            borderColor: '#2c3e50',
            backgroundColor: 'rgba(44, 62, 80, 0.1)',
            tension: 0.3,
            fill: true,
        }],
    },
    options: {
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false } },
    },
});
</script>
<?= $this->endSection() ?>
