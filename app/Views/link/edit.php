<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:500px;">
    <h1 class="mb-4">링크 수정</h1>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>
    <p class="text-muted">단축 주소: <a href="<?= esc(short_url($link)) ?>" target="_blank" rel="noopener noreferrer"><?= esc(short_url($link)) ?></a> (변경 불가)</p>
    <form action="/links/<?= esc($link['short_code']) ?>/edit" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">원본 URL</label>
            <input type="url" name="original_url" value="<?= esc($link['original_url']) ?>" required class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">제목 (선택)</label>
            <input type="text" name="title" value="<?= esc($link['title'] ?? '') ?>" maxlength="255" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">새 비밀번호 (빈칸으로 두면 기존 설정 유지)</label>
            <input type="password" name="password" placeholder="변경하려면 입력" class="form-control">
            <?php if (! empty($link['password'])) : ?>
                <div class="form-check mt-2">
                    <input type="checkbox" name="remove_password" value="1" class="form-check-input" id="removePw">
                    <label class="form-check-label" for="removePw">비밀번호 보호 해제</label>
                </div>
            <?php endif ?>
        </div>

        <div class="mb-3">
            <label class="form-label">만료일 (비우면 만료일 제거됨)</label>
            <input type="datetime-local" name="expires_at" class="form-control"
                   value="<?= ! empty($link['expires_at']) ? esc(str_replace(' ', 'T', substr($link['expires_at'], 0, 16))) : '' ?>">
        </div>

        <button type="submit" class="btn btn-primary w-100">저장</button>
    </form>

    <form action="/links/<?= esc($link['short_code']) ?>/delete" method="post"
          onsubmit="return confirm('정말 이 링크를 삭제하시겠습니까? 클릭 기록도 함께 삭제됩니다.');" class="mt-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger w-100">링크 삭제</button>
    </form>

    <p class="mt-3"><a href="/dashboard">대시보드로</a></p>
</div>
<?= $this->endSection() ?>
