<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<p class="mb-1"><a href="/admin/members" class="small">&larr; 회원 관리로</a></p>
<h1 class="mb-4"><?= esc($user['email']) ?></h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="admin-card p-4 mb-3">
    <div class="row">
        <div class="col-md-4">
            <div class="cell-sub">이름</div>
            <div class="cell-name"><?= esc($user['name'] ?? '-') ?></div>
        </div>
        <div class="col-md-4">
            <div class="cell-sub">현재 요금제</div>
            <div class="cell-name"><?= esc($planLabels[$user['plan']] ?? $user['plan']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="cell-sub">현재 이용기간</div>
            <div class="cell-name">
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

<div class="admin-card p-4 mb-3">
    <h2 class="mb-2">비밀번호 변경</h2>
    <p class="cell-sub">회원 본인이 로그인을 못 할 때 등, 관리자가 직접 새 비밀번호를 정해서 바꿔줄 수 있습니다.</p>
    <form action="/admin/users/<?= (int) $user['id'] ?>/password" method="post" class="row g-2 align-items-end"
          data-confirm="이 회원의 비밀번호를 지금 입력한 값으로 바꾸시겠습니까?">
        <?= csrf_field() ?>
        <div class="col-auto">
            <label class="form-label small mb-1">새 비밀번호 (8자 이상)</label>
            <input type="text" name="password" class="form-control form-control-sm" minlength="8" required autocomplete="new-password">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn admin-btn admin-btn-primary btn-sm">비밀번호 변경</button>
        </div>
    </form>
</div>

<h2 class="mb-3">결제 이력</h2>
<?php
$statusPill = [
    'completed' => 'pill-completed',
    'pending'   => 'pill-pending',
    'failed'    => 'pill-failed',
];
$statusText = ['completed' => '완료', 'pending' => '대기중', 'failed' => '실패'];
?>
<div class="admin-card">
<?php if (empty($payments)) : ?>
    <div class="admin-empty">
        <i class="bi bi-receipt"></i>
        결제 이력이 없습니다
    </div>
<?php else : ?>
    <div class="table-responsive">
        <table class="admin-table table mb-0">
            <thead>
                <tr>
                    <th>주문일</th>
                    <th>요금제</th>
                    <th>금액</th>
                    <th>결제수단</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p) : $pill = $statusPill[$p['status']] ?? 'pill-other'; $label = $statusText[$p['status']] ?? $p['status']; ?>
                    <tr>
                        <td class="cell-sub"><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                        <td><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></td>
                        <td><?= number_format((int) $p['amount']) ?>원<?php if ($p['method'] === 'admin') : ?> <span class="cell-sub">(관리자 부여)</span><?php endif ?></td>
                        <td><?= esc($p['method'] ?? '-') ?></td>
                        <td><span class="pill <?= esc($pill) ?>"><?= esc($label) ?></span></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
</div>
<?= $this->endSection() ?>
