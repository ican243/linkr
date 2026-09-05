<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:400px;">
    <h1 class="mb-4">회원가입</h1>

    <?php if (! empty($errors)) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error) : ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <form action="/auth/register" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">이메일</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">비밀번호 (8자 이상)</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">비밀번호 확인</label>
            <input type="password" name="password_confirm" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">이름(선택)</label>
            <input type="text" name="name" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">가입하기</button>
    </form>

    <div class="text-center text-muted my-3">또는</div>
    <a href="/auth/google" class="btn btn-outline-dark w-100 mb-2">구글로 계속하기</a>
    <a href="/auth/naver" class="btn w-100 text-white mb-2" style="background-color:#03C75A;">네이버로 계속하기</a>
    <a href="/auth/kakao" class="btn w-100" style="background-color:#FEE500;">카카오로 계속하기</a>

    <p class="mt-3"><a href="/auth/login">이미 계정이 있으신가요? 로그인</a></p>
</div>
<?= $this->endSection() ?>
