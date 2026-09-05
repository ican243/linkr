<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="mx-auto" style="max-width:500px;">
    <h1 class="mb-4">커스텀 도메인 설정</h1>

    <?php if (session()->getFlashdata('message')) : ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif ?>

    <?php if (! empty($user['custom_domain'])) : ?>
        <p>
            현재 등록된 도메인: <strong><?= esc($user['custom_domain']) ?></strong>
            <?php if ($user['custom_domain_verified']) : ?>
                <span class="badge bg-success">인증됨</span>
            <?php else : ?>
                <span class="badge bg-secondary">미인증</span>
            <?php endif ?>
        </p>

        <?php if (! $user['custom_domain_verified']) : ?>
            <div class="alert alert-light border">
                아래 DNS 레코드 중 하나를 도메인 관리 페이지에서 설정한 뒤 "DNS 연결 확인" 버튼을 눌러주세요.
                <ul class="mb-0">
                    <li>CNAME → <code>kir1.cafe24.com</code></li>
                    <li>또는 A 레코드 → 이 서비스 서버의 IP</li>
                </ul>
            </div>
            <form action="/custom-domain/verify" method="post" class="mb-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary">DNS 연결 확인</button>
            </form>
        <?php endif ?>

        <form action="/custom-domain/remove" method="post" class="mb-4"
              onsubmit="return confirm('커스텀 도메인 연결을 해제하시겠습니까?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">연결 해제</button>
        </form>
    <?php else : ?>
        <p class="text-muted">아직 등록된 커스텀 도메인이 없습니다.</p>
    <?php endif ?>

    <?php if ($user['plan'] === 'free') : ?>
        <div class="alert alert-warning">
            커스텀 도메인은 <strong>프로 요금제</strong>부터 사용할 수 있습니다.
            <a href="/pricing" class="alert-link">요금제 업그레이드하러 가기</a>
        </div>
    <?php else : ?>
        <form action="/custom-domain" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">도메인 입력 (예: mybrand.com)</label>
                <input type="text" name="custom_domain" class="form-control" placeholder="mybrand.com" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">등록/변경</button>
        </form>
    <?php endif ?>

    <p class="mt-3"><a href="/dashboard">대시보드로</a></p>
</div>
<?= $this->endSection() ?>
