<?= $this->extend($layout ?? 'layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:400px;">
    <h1 class="mb-4">비밀번호로 보호된 링크입니다</h1>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="/<?= esc($code) ?>" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">비밀번호</label>
            <input type="password" name="password" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">확인</button>
    </form>
</div>
<?= $this->endSection() ?>
