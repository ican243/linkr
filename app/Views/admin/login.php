<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<div class="admin-card p-4" style="width: 360px;">
    <div class="text-center mb-4">
        <div class="fw-bold fs-5">Linkr 관리자</div>
    </div>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger py-2 small"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="/admin/login" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label small fw-semibold">아이디</label>
            <input type="text" name="email" class="form-control" required autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold">비밀번호</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn admin-btn admin-btn-primary w-100">로그인</button>
    </form>
</div>
<?= $this->endSection() ?>
