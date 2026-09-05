<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-1">클릭 통계</h1>
<p class="text-muted">
    <a href="<?= esc(base_url($link['short_code'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc(base_url($link['short_code'])) ?></a>
    → <?= esc($link['original_url']) ?>
</p>

<div class="d-flex align-items-center gap-3 mb-4">
    <div class="card p-3 text-center" style="max-width:200px;">
        <div class="fs-3 fw-bold"><?= (int) $totalClicks ?></div>
        <div class="text-muted">총 클릭 수</div>
    </div>

    <?php if ($canExportCsv) : ?>
        <a href="/links/<?= esc($link['short_code']) ?>/stats/export" class="btn btn-outline-secondary btn-sm">CSV로 내려받기</a>
    <?php endif ?>
</div>

<?php if ($isFreePlan) : ?>
    <div class="alert alert-warning">
        무료 요금제는 최근 <?= (int) $historyDays ?>일간의 클릭 기록만 볼 수 있습니다.
        더 오래된 기록과 CSV 내보내기는 <a href="/pricing" class="alert-link">프로 요금제</a>부터 이용할 수 있어요.
    </div>
<?php endif ?>

<h2 class="h5">최근 14일 클릭 추이</h2>
<?php if (empty($dailyClicks)) : ?>
    <p class="text-muted">아직 클릭 기록이 없습니다.</p>
<?php else : ?>
    <?php $max = max(array_column($dailyClicks, 'cnt')); ?>
    <div class="mb-4">
        <?php foreach (array_reverse($dailyClicks) as $day) : ?>
            <?php $percent = $max > 0 ? (int) round(($day['cnt'] / $max) * 100) : 0; ?>
            <div class="d-flex align-items-center mb-1" style="gap:8px;">
                <div style="width:90px;" class="text-muted small"><?= esc($day['day']) ?></div>
                <div class="flex-grow-1 bg-light rounded" style="height:18px;">
                    <div class="bg-primary rounded" style="width:<?= $percent ?>%; height:18px;"></div>
                </div>
                <div style="width:30px;" class="text-end small"><?= (int) $day['cnt'] ?></div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<h2 class="h5">최근 클릭 기록</h2>
<?php if (empty($recentLogs)) : ?>
    <p class="text-muted">아직 클릭 기록이 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>시각</th>
                    <th>IP</th>
                    <th>브라우저/기기(User-Agent)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log) : ?>
                    <tr>
                        <td><?= esc($log['clicked_at']) ?></td>
                        <td><?= esc($log['ip_address']) ?></td>
                        <td class="text-truncate" style="max-width:300px;"><?= esc($log['user_agent']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<p><a href="/dashboard">대시보드로</a></p>
<?= $this->endSection() ?>
