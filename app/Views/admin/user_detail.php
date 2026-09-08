<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<p class="mb-1"><a href="/admin/dashboard">&larr; 대시보드로</a></p>
<h1 class="mb-4"><?= esc($user['email']) ?></h1>

<div class="card p-4 mb-4">
    <div class="row">
        <div class="col-md-4">
            <div class="text-muted small">이름</div>
            <div class="fw-bold"><?= esc($user['name'] ?? '-') ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small">현재 요금제</div>
            <div class="fw-bold"><?= esc($planLabels[$user['plan']] ?? $user['plan']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small">현재 이용기간</div>
            <div class="fw-bold">
                <?php if ($user['plan'] === 'free') : ?>
                    해당 없음
                <?php elseif (! empty($currentPeriodStart) && ! empty($user['plan_expires_at'])) : ?>
                    <?= esc(date('Y-m-d', strtotime($currentPeriodStart))) ?> ~ <?= esc(date('Y-m-d', strtotime($user['plan_expires_at']))) ?>
                <?php else : ?>
                    정보 없음
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<h2 class="h5 mb-3">결제 이력</h2>
<?php
$statusBadges = [
    'completed' => ['label' => '완료', 'class' => 'bg-success'],
    'pending'   => ['label' => '대기중', 'class' => 'bg-warning text-dark'],
    'failed'    => ['label' => '실패', 'class' => 'bg-danger'],
];
?>
<?php if (empty($payments)) : ?>
    <p class="text-muted">결제 이력이 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>주문일</th>
                    <th>요금제</th>
                    <th>금액</th>
                    <th>결제수단</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p) : $badge = $statusBadges[$p['status']] ?? ['label' => $p['status'], 'class' => 'bg-secondary']; ?>
                    <tr>
                        <td><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                        <td><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></td>
                        <td><?= number_format((int) $p['amount']) ?>원<?php if ($p['method'] === 'admin') : ?> <span class="text-muted small">(관리자 부여)</span><?php endif ?></td>
                        <td><?= esc($p['method'] ?? '-') ?></td>
                        <td><span class="badge <?= esc($badge['class']) ?>"><?= esc($badge['label']) ?></span></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
