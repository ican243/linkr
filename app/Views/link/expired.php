<?= $this->extend($layout ?? 'layout') ?>

<?= $this->section('content') ?>
<div class="text-center py-5">
    <h1 class="mb-3">만료된 링크입니다</h1>
    <?php if (! empty($title)) : ?>
        <p class="fw-bold mb-1"><?= esc($title) ?></p>
    <?php endif ?>
    <p class="text-muted">이 링크는 설정된 만료 시각이 지났거나 클릭 한도에 도달해 더 이상 사용할 수 없습니다.</p>
    <?php if (($layout ?? 'layout') !== 'minimal_layout') : ?>
        <a href="/" class="btn btn-primary">홈으로</a>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
