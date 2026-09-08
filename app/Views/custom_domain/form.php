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

        <?php if ($user['custom_domain_verified']) : ?>
            <p class="text-muted small">
                현재는 <code>http://<?= esc($user['custom_domain']) ?></code> 로만 접속됩니다(https는 아직 지원 전).
                A 레코드가 <code>1.234.79.116</code>으로 설정되어 있어야 실제로 접속이 됩니다.
            </p>
            <?php if (! empty($exampleLink)) : ?>
                <?php $exampleUrl = 'http://' . rtrim($user['custom_domain'], '/') . '/' . $exampleLink['short_code']; ?>
                <p class="mb-3">
                    예시 링크:
                    <a href="<?= esc($exampleUrl) ?>" target="_blank" rel="noopener noreferrer"><?= esc($exampleUrl) ?></a>
                </p>
            <?php else : ?>
                <p class="text-muted small mb-3">
                    아직 만든 링크가 없습니다. 링크를 만들면 <code>http://<?= esc($user['custom_domain']) ?>/코드</code> 형태로 접속할 수 있습니다.
                </p>
            <?php endif ?>
        <?php endif ?>

        <?php if (! $user['custom_domain_verified']) : ?>
            <div class="alert alert-light border">
                도메인 관리 페이지(가비아, 후이즈 등 구매하신 곳)에서 아래 두 가지를 모두 설정한 뒤
                "TXT 레코드 확인" 버튼을 눌러주세요.
                <ul class="mb-0">
                    <li>TXT 레코드에 <code>linkr-verify=<?= esc($user['custom_domain_verify_code'] ?? '') ?></code> 추가 (소유권 확인용)</li>
                    <li>A 레코드를 <code>1.234.79.116</code>으로 설정 (실제 접속을 위한 라우팅용)</li>
                </ul>
            </div>
            <form action="/custom-domain/verify" method="post" class="mb-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary">TXT 레코드 확인</button>
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
