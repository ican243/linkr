<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">통계</h1>

<!-- 통계 카드 4개 -->
<div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
    <div class="col">
        <div class="card h-100 p-3">
            <div class="text-muted small">총 링크 수</div>
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
            <div class="text-muted small">최고 클릭 수</div>
            <div class="fs-2 fw-bold"><?= (int) $topClicks ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 p-3">
            <div class="text-muted small">평균 클릭 수</div>
            <div class="fs-2 fw-bold"><?= esc($avgClicks) ?></div>
        </div>
    </div>
</div>

<!-- 최근 7일 링크 생성 추이 -->
<div class="card p-3 mb-4">
    <h2 class="h6 mb-3">최근 7일 링크 생성 추이</h2>
    <canvas id="creationChart" height="80"></canvas>
</div>

<!-- 인기 URL Top 5 -->
<h2 class="h5 mb-3">인기 URL Top 5</h2>
<?php if (empty($topLinks)) : ?>
    <p class="text-muted">아직 클릭 데이터가 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>제목</th>
                    <th>단축 URL</th>
                    <th>원본 URL</th>
                    <th>클릭 수</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topLinks as $i => $link) : ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= ! empty($link['title']) ? esc($link['title']) : '<span class="text-muted">—</span>' ?></td>
                        <td><a href="<?= esc(short_url($link)) ?>" target="_blank" rel="noopener noreferrer"><?= esc(short_url($link)) ?></a></td>
                        <td class="text-truncate" style="max-width:250px;"><?= esc($link['original_url']) ?></td>
                        <td><?= (int) $link['click_count'] ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('creationChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($creationStats, 'label')) ?>,
        datasets: [{
            label: '생성된 링크 수',
            data: <?= json_encode(array_column($creationStats, 'count')) ?>,
            backgroundColor: '#2c3e50',
        }],
    },
    options: {
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false } },
    },
});
</script>
<?= $this->endSection() ?>
