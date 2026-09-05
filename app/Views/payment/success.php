<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto text-center" style="max-width:400px;">
    <h1 class="mb-3">✅ 결제 완료</h1>
    <p class="text-muted">프로 요금제로 업그레이드되었습니다.</p>
    <p class="fw-bold"><?= number_format($payment['amount']) ?>원 결제됨</p>
    <a href="/dashboard" class="btn btn-primary">대시보드로 이동</a>
</div>
<?= $this->endSection() ?>
