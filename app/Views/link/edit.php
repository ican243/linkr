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
            <input type="password" name="password" placeholder="변경하려면 입력"
                   class="form-control" <?= ($user['plan'] ?? 'free') === 'free' ? 'disabled' : '' ?>>
            <?php if (! empty($link['password'])) : ?>
                <div class="form-check mt-2">
                    <input type="checkbox" name="remove_password" value="1" class="form-check-input" id="removePw">
                    <label class="form-check-label" for="removePw">비밀번호 보호 해제</label>
                </div>
            <?php endif ?>
            <?php if (($user['plan'] ?? 'free') === 'free') : ?>
                <div class="form-text">비밀번호 보호는 <a href="/pricing">프로 요금제</a>부터 사용할 수 있습니다.</div>
            <?php endif ?>
        </div>

        <?php $expiresAtKst = ! empty($link['expires_at']) ? utc_to_kst_local($link['expires_at']) : ''; ?>
        <?php if (($user['plan'] ?? 'free') === 'free') : ?>
            <?php
                $nowKst = utc_to_kst_local(date('Y-m-d H:i:s'));
                $maxKst = utc_to_kst_local(date('Y-m-d H:i:s', strtotime('+' . \App\Controllers\Link::FREE_PLAN_MAX_EXPIRY_DAYS . ' days')));
            ?>
            <div class="mb-3">
                <label class="form-label">만료일 (무료 요금제는 필수 — 최대 <?= \App\Controllers\Link::FREE_PLAN_MAX_EXPIRY_DAYS ?>일, 비워두면 <?= \App\Controllers\Link::FREE_PLAN_MAX_EXPIRY_DAYS ?>일 후로 자동 재설정됨)</label>
                <input type="datetime-local" name="expires_at" class="form-control" min="<?= esc($nowKst) ?>" max="<?= esc($maxKst) ?>" value="<?= esc($expiresAtKst) ?>">
            </div>
        <?php else : ?>
            <div class="mb-3">
                <label class="form-label">만료일 (비우면 무제한)</label>
                <input type="datetime-local" name="expires_at" class="form-control" value="<?= esc($expiresAtKst) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">만료 시 대체 URL (선택)</label>
                <input type="url" name="fallback_url" value="<?= esc($link['fallback_url'] ?? '') ?>" placeholder="https://example.com/renewed" class="form-control">
            </div>
        <?php endif ?>
        <?php if (($user['plan'] ?? 'free') === 'enterprise') : ?>
            <div class="mb-3">
                <label class="form-label">클릭 한도 (선택 — 도달하면 만료된 것처럼 처리됨)</label>
                <input type="number" name="max_clicks" min="1" value="<?= esc($link['max_clicks'] ?? '') ?>" placeholder="예: 1000" class="form-control">
            </div>
        <?php endif ?>

        <button type="submit" class="btn btn-primary w-100">저장</button>
    </form>

    <?php if (($user['plan'] ?? 'free') === 'enterprise' && (
        (! empty($link['expires_at']) && strtotime($link['expires_at']) < time())
        || (! empty($link['max_clicks']) && (int) $link['click_count'] >= (int) $link['max_clicks'])
    )) : ?>
        <form action="/links/<?= esc($link['short_code']) ?>/reactivate" method="post"
              onsubmit="return confirm('만료 시각과 클릭 한도를 모두 없애고 이 링크를 다시 활성화하시겠습니까?');" class="mt-2">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-success w-100">링크 재활성화</button>
        </form>
    <?php endif ?>

    <form action="/links/<?= esc($link['short_code']) ?>/delete" method="post"
          onsubmit="return confirm('정말 이 링크를 삭제하시겠습니까? 클릭 기록도 함께 삭제됩니다.');" class="mt-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger w-100">링크 삭제</button>
    </form>

    <p class="mt-3"><a href="/dashboard">대시보드로</a></p>
</div>
<?= $this->endSection() ?>
