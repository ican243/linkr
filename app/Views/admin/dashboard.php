<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">대시보드</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<?php
$statCards = [
    ['icon' => 'bi-people',       'color' => '#2563eb', 'label' => '전체 회원 수',    'value' => number_format($stats['totalUsers']),  'delta' => $deltas['users']],
    ['icon' => 'bi-link-45deg',   'color' => '#7c3aed', 'label' => '전체 링크 수',    'value' => number_format($stats['totalLinks']),  'delta' => $deltas['links']],
    ['icon' => 'bi-cursor',       'color' => '#0891b2', 'label' => '전체 클릭 수',    'value' => number_format($stats['totalClicks']), 'delta' => $deltas['clicks']],
    ['icon' => 'bi-cash-coin',    'color' => '#15803d', 'label' => '이번 달 매출',    'value' => number_format($stats['monthlyRevenue']) . '원', 'delta' => $deltas['revenue']],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($statCards as $card) : ?>
        <div class="col-6 col-lg-3">
            <div class="admin-card stat-card">
                <div class="stat-card-icon" style="background: <?= esc($card['color']) ?>;">
                    <i class="bi <?= esc($card['icon']) ?>"></i>
                </div>
                <div class="stat-card-value"><?= $card['value'] ?></div>
                <div class="stat-card-label"><?= esc($card['label']) ?></div>
                <div class="stat-card-delta <?= esc($card['delta']['dir']) ?>">
                    <?php if ($card['delta']['dir'] === 'up') : ?>
                        <i class="bi bi-arrow-up-short"></i>
                    <?php elseif ($card['delta']['dir'] === 'down') : ?>
                        <i class="bi bi-arrow-down-short"></i>
                    <?php else : ?>
                        <i class="bi bi-dash"></i>
                    <?php endif ?>
                    <?= (int) $card['delta']['pct'] ?>% 전주 대비
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card p-3">
            <h2 class="mb-3">최근 7일 클릭 추이</h2>
            <canvas id="clickTrendChart" height="120"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card">
            <h2 class="p-3 pb-0 mb-2">최근 결제 5건</h2>
            <?php if (empty($recentPayments)) : ?>
                <div class="admin-empty">
                    <i class="bi bi-inbox"></i>
                    결제 내역이 없습니다
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="admin-table table mb-0">
                        <thead>
                            <tr>
                                <th>회원</th>
                                <th>요금제</th>
                                <th>금액</th>
                                <th>상태</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $statusPill = [
                                'completed' => 'pill-completed',
                                'pending'   => 'pill-pending',
                                'failed'    => 'pill-failed',
                            ];
                            $statusLabel = ['completed' => '완료', 'pending' => '대기중', 'failed' => '실패'];
                            ?>
                            <?php foreach ($recentPayments as $p) : ?>
                                <tr>
                                    <td class="cell-email"><?= esc($p['user_email']) ?></td>
                                    <td><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></td>
                                    <td><?= number_format((int) $p['amount']) ?>원</td>
                                    <td><span class="pill <?= esc($statusPill[$p['status']] ?? 'pill-other') ?>"><?= esc($statusLabel[$p['status']] ?? $p['status']) ?></span></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
        <p class="text-end mt-2"><a href="/admin/payments" class="small">결제 관리 전체 보기 &rarr;</a></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('clickTrendChart');
    if (! ctx || typeof Chart === 'undefined') { return; }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($clickTrend['labels']) ?>,
            datasets: [{
                label: '클릭 수',
                data: <?= json_encode($clickTrend['data']) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                tension: 0.3,
                fill: true,
                pointRadius: 3,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
});
</script>
<?= $this->endSection() ?>
