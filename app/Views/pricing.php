<?php
$isLoggedIn = (bool) session()->get('isLoggedIn');

// 월 요금($proPrice/$enterprisePrice)은 관리자가 /admin/settings에서 바꾸면 여기도 같이 바뀜.
// (예전엔 "연간 결제 20% 할인" 토글이 있었는데, 실제 결제는 월 단위만 지원해서 화면과 실제가 달라 제거함)

$planOrder = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
$planLabel = ['free' => '무료', 'pro' => '프로', 'enterprise' => '엔터프라이즈'];

// 만료된 유료 요금제는 "사실상 무료"로 취급해서 버튼 상태를 판단함(Payment::checkout()과 동일 기준).
$currentPlan = 'free';
if (! empty($user)) {
    $isActive = $user['plan'] === 'free' || empty($user['plan_expires_at']) || strtotime($user['plan_expires_at']) > time();
    $currentPlan = $isActive ? $user['plan'] : 'free';
}

// 각 요금제 카드에 대해: 지금 이용 중인지, 다운그레이드 대상인지 미리 계산.
$planState = static function (string $plan) use ($currentPlan, $planOrder): string {
    if ($plan === $currentPlan) {
        return 'current';
    }

    return $planOrder[$plan] < $planOrder[$currentPlan] ? 'downgrade' : 'upgrade';
};
?>
<?= $this->extend('layout') ?>

<?= $this->section('title') ?>요금제<?= $this->endSection() ?>
<?= $this->section('description') ?>무료·프로·엔터프라이즈 요금제의 가격과 기능을 비교해보세요. 무료로 바로 시작할 수 있습니다.<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="text-center py-4">
    <h1 class="fw-bold">요금제</h1>
    <p class="text-muted">필요한 만큼만, 언제든 변경할 수 있어요.</p>
</div>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success text-center"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger text-center"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<?php if (! empty($user['next_plan']) && ! empty($user['next_plan_at'])) : ?>
    <div class="alert alert-info text-center">
        <?= esc(date('n월 j일', strtotime($user['next_plan_at']))) ?>부터 <strong><?= esc($planLabel[$user['next_plan']] ?? $user['next_plan']) ?></strong>(으)로 전환 예정입니다.
        <form action="/pricing/cancel-downgrade" method="post" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary ms-2">예약 취소</button>
        </form>
    </div>
<?php endif ?>

<?php if (! $isLoggedIn) : ?>
    <div class="alert alert-light border text-center small">
        💳 카드 없어도 무료로 바로 시작할 수 있어요. 유료 요금제는 언제든 해지 가능합니다.
    </div>
<?php endif ?>
<div class="alert alert-warning text-center small">
    ⚠️나에게 맞는 요금제를 선택하고 Linkr의 모든 기능을 활용해보세요️⚠️
</div>

<!-- 요금제 카드 -->
<div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
    <div class="col">
        <div class="card h-100 p-4">
            <div class="fs-2">🎉</div>
            <h2 class="h5 mt-2">무료</h2>
            <p class="display-6 mb-0">0원</p>
            <p class="text-muted small">영원히 무료</p>
            <ul class="list-unstyled small text-muted flex-grow-1">
                <li>✔ 월 <?= number_format($freeLinkLimit) ?>개 링크</li>
                <li>✔ QR 코드</li>
                <li>✔ 기본 클릭 통계 (7일 보관)</li>
                <li>✖ 커스텀 도메인</li>
                <li>✖ 비밀번호 보호 / 만료일</li>
            </ul>
            <?php $freeState = $planState('free'); ?>
            <?php if ($isLoggedIn && $freeState === 'current') : ?>
                <button type="button" class="btn btn-outline-secondary w-100" disabled>이용 중</button>
            <?php elseif ($isLoggedIn && $freeState === 'downgrade') : ?>
                <?php if (! empty($user['next_plan'])) : ?>
                    <button type="button" class="btn btn-outline-secondary w-100" disabled>예약됨</button>
                <?php else : ?>
                    <form action="/pricing/downgrade" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan" value="free">
                        <button type="submit" class="btn btn-outline-secondary w-100">무료 전환 예약</button>
                    </form>
                <?php endif ?>
            <?php else : ?>
                <a href="<?= $isLoggedIn ? '/dashboard' : '/auth/register' ?>" class="btn btn-outline-primary w-100">무료로 시작하기</a>
            <?php endif ?>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 p-4 border-primary">
            <span class="badge bg-primary mb-2" style="width:fit-content;">가장 인기</span>
            <div class="fs-2">🚀</div>
            <h2 class="h5 mt-2">프로</h2>
            <p class="display-6 mb-0">
                <?= number_format($proPrice) ?>원<span class="fs-6 text-muted">/월</span>
            </p>
            <p class="text-muted small">매월 결제 · 언제든 변경 가능</p>
            <ul class="list-unstyled small text-muted flex-grow-1">
                <li>✔ 무제한 링크</li>
                <li>✔ 커스텀 도메인 1개</li>
                <li>✔ 비밀번호 보호 / 만료일</li>
                <li>✔ 고급 통계, CSV 내보내기</li>
            </ul>
            <?php $proState = $planState('pro'); ?>
            <?php if ($isLoggedIn && $proState === 'current') : ?>
                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                    이용 중<?php if (! empty($user['plan_expires_at'])) : ?> (~<?= esc(date('Y-m-d', strtotime($user['plan_expires_at']))) ?>)<?php endif ?>
                </button>
            <?php elseif ($isLoggedIn && $proState === 'downgrade') : ?>
                <?php // 자동 결제가 없어서, 엔터프라이즈 → 프로는 지금 기간이 끝난 뒤 프로를 새로 결제하는 방식 ?>
                <button type="button" class="btn btn-outline-secondary w-100" disabled>이용 기간 종료 후 결제 가능</button>
            <?php else : ?>
                <a href="<?= $isLoggedIn ? '/payment/checkout?plan=pro' : '/auth/register' ?>" class="btn btn-primary w-100"><?= $isLoggedIn && $currentPlan !== 'free' ? '업그레이드' : '프로 시작하기' ?></a>
            <?php endif ?>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 p-4">
            <div class="fs-2">🏢</div>
            <h2 class="h5 mt-2">엔터프라이즈</h2>
            <p class="display-6 mb-0">
                <?= number_format($enterprisePrice) ?>원<span class="fs-6 text-muted">/월</span>
            </p>
            <p class="text-muted small">매월 결제 · 언제든 변경 가능</p>
            <ul class="list-unstyled small text-muted flex-grow-1">
                <li>✔ 프로의 모든 기능</li>
                <li>✔ 커스텀 도메인 1개</li>
                <li>✔ API 액세스</li>
                <li>✔ 화이트라벨링</li>
            </ul>
            <?php $entState = $planState('enterprise'); ?>
            <?php if ($isLoggedIn && $entState === 'current') : ?>
                <button type="button" class="btn btn-outline-secondary w-100" disabled>
                    이용 중<?php if (! empty($user['plan_expires_at'])) : ?> (~<?= esc(date('Y-m-d', strtotime($user['plan_expires_at']))) ?>)<?php endif ?>
                </button>
            <?php else : ?>
                <a href="<?= $isLoggedIn ? '/payment/checkout?plan=enterprise' : '/auth/register' ?>" class="btn btn-outline-primary w-100"><?= $isLoggedIn && $currentPlan === 'pro' ? '업그레이드' : '엔터프라이즈 시작하기' ?></a>
            <?php endif ?>
        </div>
    </div>
</div>

<!-- 기능 비교표 -->
<h2 class="h4 text-center mb-4">기능 비교</h2>
<div class="table-responsive mb-5">
    <table class="table table-bordered text-center align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-start">기능</th>
                <th>무료</th>
                <th>프로</th>
                <th>엔터프라이즈</th>
            </tr>
        </thead>
        <tbody>
            <tr><td class="text-start">월 링크 생성</td><td><?= number_format($freeLinkLimit) ?>개</td><td>무제한</td><td>무제한</td></tr>
            <tr><td class="text-start">QR 코드</td><td>✔</td><td>✔</td><td>✔</td></tr>
            <tr><td class="text-start">비밀번호 보호 / 만료일</td><td>✖</td><td>✔</td><td>✔</td></tr>
            <tr><td class="text-start">커스텀 도메인</td><td>✖</td><td>1개</td><td>1개</td></tr>
            <tr><td class="text-start">클릭 통계 보관기간</td><td>7일</td><td>무제한</td><td>무제한</td></tr>
            <tr><td class="text-start">CSV 내보내기</td><td>✖</td><td>✔</td><td>✔</td></tr>
            <tr><td class="text-start">API 액세스</td><td>✖</td><td>✖</td><td>✔</td></tr>
            <tr><td class="text-start">화이트라벨링</td><td>✖</td><td>✖</td><td>✔</td></tr>
        </tbody>
    </table>
</div>

<!-- FAQ -->
<h2 class="h4 text-center mb-4">자주 묻는 질문</h2>
<div class="accordion mb-5" id="pricingFaq" style="max-width:700px;margin:0 auto;">
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                무료 요금제만으로도 충분한가요?
            </button>
        </h3>
        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">개인적으로 가끔 링크를 단축하는 정도라면 무료 요금제(월 <?= number_format($freeLinkLimit) ?>개)로 충분합니다. 커스텀 도메인이나 비밀번호 보호 같은 기능이 필요하면 프로 요금제를 고려해보세요.</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                나중에 요금제를 변경할 수 있나요?
            </button>
        </h3>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">네, 이 페이지에서 언제든 변경할 수 있습니다. 상위 요금제로 올리면 지금 요금제의 남은 기간만큼 금액을 차감하고 바로 적용됩니다. 유료 요금제는 이용 기간(1개월)이 끝나면 무료로 돌아가며, 그 뒤 원하는 요금제를 다시 결제하면 됩니다. 무료 전환을 미리 예약해둘 수도 있어요(그 전까지 예약 취소 가능).</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                환불 정책이 어떻게 되나요?
            </button>
        </h3>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">Linkr는 시연용 서비스라 결제는 토스페이먼츠 테스트 모드로 진행되며, 실제 금액이 청구되지 않습니다. 결제 흐름을 부담 없이 체험해보세요.</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                엔터프라이즈 요금제는 어떻게 시작하나요?
            </button>
        </h3>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">회원가입 후 이 페이지에서 "엔터프라이즈 시작하기"를 누르면 바로 결제하고 사용할 수 있습니다. 프로 요금제를 이용 중이라면 남은 기간만큼 차감된 금액만 결제하면 됩니다.</div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
