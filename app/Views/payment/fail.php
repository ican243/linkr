<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto text-center" style="max-width:400px;">
    <h1 class="mb-3">❌ 결제 실패</h1>
    <p class="text-muted"><?= esc($message) ?></p>
    <a href="/pricing" class="btn btn-outline-primary">요금제 다시 보기</a>
</div>
<?= $this->endSection() ?>
