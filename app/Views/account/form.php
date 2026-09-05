<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:450px;">
    <h1 class="mb-4">계정 설정</h1>

    <?php if (session()->getFlashdata('message')) : ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif ?>
    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="/account" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">이메일</label>
            <input type="email" value="<?= esc($user['email']) ?>" class="form-control" disabled>
            <div class="form-text">이메일은 변경할 수 없습니다.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">이름</label>
            <input type="text" name="name" value="<?= esc($user['name'] ?? '') ?>" class="form-control">
        </div>

        <hr>
        <p class="text-muted">비밀번호를 바꾸려면 아래 두 칸을 모두 입력하세요. (바꾸지 않으려면 비워두세요)</p>

        <div class="mb-3">
            <label class="form-label">현재 비밀번호</label>
            <input type="password" name="current_password" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">새 비밀번호 (8자 이상)</label>
            <input type="password" name="new_password" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary w-100">저장</button>
    </form>

    <p class="mt-3"><a href="/dashboard">대시보드로</a></p>
</div>
<?= $this->endSection() ?>
