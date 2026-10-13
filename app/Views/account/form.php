<?= $this->extend('user_layout') ?>

<?= $this->section('title') ?>계정 설정<?= $this->endSection() ?>

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

    <!-- 회원 탈퇴 (Account::delete) -->
    <div class="card border-danger mt-5">
        <div class="card-body">
            <h2 class="h6 fw-bold text-danger">회원 탈퇴</h2>
            <ul class="small text-muted ps-3 mb-3">
                <li>만든 링크, 클릭 기록, API 키가 <strong>모두 바로 삭제</strong>되며 되돌릴 수 없습니다.</li>
                <?php if (in_array($user['plan'], ['pro', 'enterprise'], true)) : ?>
                    <li>남은 유료 이용 기간도 함께 사라집니다.</li>
                <?php endif ?>
                <li>결제 기록은 법에 따라 5년 동안 보관되며, 회원 정보와 연결되지 않은 상태로 남습니다.</li>
            </ul>

            <?php if (! empty($deleteError)) : ?>
                <div class="alert alert-danger py-2 small"><?= esc($deleteError) ?></div>
            <?php endif ?>

            <form action="/account/delete" method="post">
                <?= csrf_field() ?>
                <label for="confirmDelete" class="form-label small">계속하려면 아래 칸에 <strong>탈퇴</strong>라고 입력하세요.</label>
                <input type="text" id="confirmDelete" name="confirm" class="form-control mb-2" autocomplete="off" required>
                <button type="submit" class="btn btn-outline-danger w-100">회원 탈퇴</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
