<?= $this->extend('layout') ?>

<?= $this->section('title') ?>이동 전 확인<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- 체험 계정이 만든 링크를 열었을 때, 바로 이동하지 않고 목적지 주소를 먼저 보여주는 안내 화면 -->
<div class="mx-auto" style="max-width:520px;">
    <div class="card p-4 p-md-5 text-center">
        <h1 class="h4 mb-3">이 주소로 이동합니다</h1>
        <p class="text-muted mb-4">
            이 링크는 Linkr의 <strong>체험 계정</strong>으로 만들어졌어요.<br>
            누구나 사용할 수 있는 계정이라, 이동하기 전에 주소를 꼭 확인해주세요.
        </p>
        <div class="bg-light border rounded-3 p-3 mb-4 text-break small"><?= esc($url) ?></div>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a href="<?= esc($url, 'attr') ?>" class="btn btn-primary" rel="noopener noreferrer nofollow">이 주소로 이동</a>
            <a href="/" class="btn btn-outline-secondary">Linkr 홈으로</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
