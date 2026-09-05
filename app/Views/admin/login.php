<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:400px;">
    <h1 class="mb-4">관리자 로그인</h1>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="/admin/login" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">이메일</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">비밀번호</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-dark w-100">로그인</button>
    </form>
</div>
<?= $this->endSection() ?>
