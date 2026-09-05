<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:400px;">
    <h1 class="mb-4">로그인</h1>

    <?php if (session()->getFlashdata('message')) : ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif ?>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <form action="/auth/login" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">이메일</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">비밀번호</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">로그인</button>
    </form>

    <div class="text-center text-muted my-3">또는</div>
    <a href="/auth/google" class="btn btn-outline-dark w-100 mb-2">구글로 로그인</a>
    <a href="/auth/naver" class="btn w-100 text-white mb-2" style="background-color:#03C75A;">네이버로 로그인</a>
    <a href="/auth/kakao" class="btn w-100" style="background-color:#FEE500;">카카오로 로그인</a>

    <p class="mt-3"><a href="/auth/register">계정이 없으신가요? 회원가입</a></p>
</div>
<?= $this->endSection() ?>
