<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:500px;">
    <h1 class="mb-4">URL 단축</h1>

    <?php if (! empty($error)) : ?>
        <div class="alert alert-danger"><?= esc($error) ?></div>
    <?php endif ?>

    <?php if (! empty($shortUrl)) : ?>
        <div class="alert alert-success">
            단축 완료: <a href="<?= esc($shortUrl) ?>"><?= esc($shortUrl) ?></a>
        </div>
        <p><img src="<?= esc(base_url('qr/' . $shortCode)) ?>" alt="QR 코드" width="150" height="150"></p>
    <?php endif ?>

    <form action="/shorten" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">원본 URL</label>
            <input type="url" name="original_url" placeholder="https://example.com" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">제목 (선택 — 내 링크 목록에서 구분하기 쉽게)</label>
            <input type="text" name="title" placeholder="예: 8월 이벤트 페이지" maxlength="255" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">커스텀 URL (선택 — 비우면 랜덤 코드로 생성됨)</label>
            <div class="input-group">
                <span class="input-group-text"><?= esc(base_url()) ?></span>
                <input type="text" name="custom_alias" placeholder="my-link" pattern="[A-Za-z0-9_-]{3,30}" maxlength="30" class="form-control">
            </div>
            <div class="form-text">영문, 숫자, -, _ 만 사용해서 3~30자로 입력해주세요.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">비밀번호 (선택 — 설정하면 접속 시 비밀번호를 입력해야 이동됨)</label>
            <input type="password" name="password" placeholder="입력 안 하면 보호 없음" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">만료일 (선택 — 설정하면 이 시각 이후엔 링크가 무효화됨)</label>
            <input type="datetime-local" name="expires_at" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary w-100">단축하기</button>
    </form>

    <p class="mt-3"><a href="/">홈으로</a></p>
</div>
<?= $this->endSection() ?>
