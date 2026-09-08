<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">요금제 설정</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<p class="cell-sub">
    여기서 바꾸면 <a href="/pricing" target="_blank">요금제 페이지</a>와 실제 결제 금액,
    무료 회원 월 링크 한도에 바로 반영됩니다.
</p>

<form action="/admin/settings" method="post" class="admin-card p-4" style="max-width:480px;">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label small fw-semibold">무료 요금제 월 링크 한도 (개)</label>
        <input type="number" name="free_plan_link_limit" class="form-control" min="1"
               value="<?= esc(old('free_plan_link_limit', $freeLinkLimit)) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">프로 요금제 가격 (원/월)</label>
        <input type="number" name="pro_plan_price" class="form-control" min="1"
               value="<?= esc(old('pro_plan_price', $proPrice)) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">엔터프라이즈 요금제 가격 (원/월)</label>
        <input type="number" name="enterprise_plan_price" class="form-control" min="1"
               value="<?= esc(old('enterprise_plan_price', $enterprisePrice)) ?>" required>
    </div>

    <button type="submit" class="btn admin-btn admin-btn-primary w-100">저장</button>
</form>
<?= $this->endSection() ?>
