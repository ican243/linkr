<?php
$isLoggedIn = (bool) session()->get('isLoggedIn');

// 연간 결제는 아직 실제 결제 연동은 없고 화면 표시만(20% 할인 가정) 계산해서 보여줌.
// 월간 금액($proPrice/$enterprisePrice)은 관리자가 /admin/settings에서 바꾸면 여기도 같이 바뀜.
$proPriceYearly       = (int) round($proPrice * 0.8);
$proPriceYearlySaved  = ($proPrice - $proPriceYearly) * 12;
$enterprisePriceYearly      = (int) round($enterprisePrice * 0.8);
$enterprisePriceYearlySaved = ($enterprisePrice - $enterprisePriceYearly) * 12;
?>
<?= $this->extend('layout') ?>

<?= $this->section('content') ?>

<div class="text-center py-4">
    <h1 class="fw-bold">요금제</h1>
    <p class="text-muted">필요한 만큼만, 언제든 변경할 수 있어요.</p>

    <!-- 월간/연간 토글 (화면 표시만 바뀌고, 아직 결제 연동은 없음) -->
    <div class="btn-group mb-2" role="group" aria-label="결제 주기 선택">
        <button type="button" class="btn btn-outline-primary active" id="btn-monthly" onclick="showBilling('monthly')">월간 결제</button>
        <button type="button" class="btn btn-outline-primary" id="btn-yearly" onclick="showBilling('yearly')">연간 결제 <span class="badge bg-danger">20% 할인</span></button>
    </div>
</div>

<?php if (! $isLoggedIn) : ?>
    <div class="alert alert-light border text-center small">
        💳 카드 없어도 무료로 바로 시작할 수 있어요. 유료 요금제는 언제든 해지 가능합니다.
    </div>
<?php endif ?>
<div class="alert alert-warning text-center small">
    ⚠️ 결제 시스템은 아직 준비 중입니다. 지금은 요금제 안내만 확인하실 수 있어요.
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
            <a href="<?= $isLoggedIn ? '/dashboard' : '/auth/register' ?>" class="btn btn-outline-primary w-100">무료로 시작하기</a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 p-4 border-primary">
            <span class="badge bg-primary mb-2" style="width:fit-content;">가장 인기</span>
            <div class="fs-2">🚀</div>
            <h2 class="h5 mt-2">프로</h2>
            <p class="display-6 mb-0">
                <span class="price-monthly"><?= number_format($proPrice) ?>원</span>
                <span class="price-yearly d-none"><?= number_format($proPriceYearly) ?>원</span>
                <span class="fs-6 text-muted">/월</span>
            </p>
            <p class="text-muted small yearly-note d-none">연간 결제 시 연 <?= number_format($proPriceYearly * 12) ?>원 (<?= number_format($proPriceYearlySaved) ?>원 절약)</p>
            <p class="text-muted small monthly-note">&nbsp;</p>
            <ul class="list-unstyled small text-muted flex-grow-1">
                <li>✔ 무제한 링크</li>
                <li>✔ 커스텀 도메인 1개</li>
                <li>✔ 비밀번호 보호 / 만료일</li>
                <li>✔ 고급 통계, CSV 내보내기</li>
                <li>✔ 우선 고객 지원</li>
            </ul>
            <a href="<?= $isLoggedIn ? '/payment/checkout?plan=pro' : '/auth/register' ?>" class="btn btn-primary w-100">프로 시작하기</a>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 p-4">
            <div class="fs-2">🏢</div>
            <h2 class="h5 mt-2">엔터프라이즈</h2>
            <p class="display-6 mb-0">
                <span class="price-monthly"><?= number_format($enterprisePrice) ?>원</span>
                <span class="price-yearly d-none"><?= number_format($enterprisePriceYearly) ?>원</span>
                <span class="fs-6 text-muted">/월</span>
            </p>
            <p class="text-muted small yearly-note d-none">연간 결제 시 연 <?= number_format($enterprisePriceYearly * 12) ?>원 (<?= number_format($enterprisePriceYearlySaved) ?>원 절약)</p>
            <p class="text-muted small monthly-note">&nbsp;</p>
            <ul class="list-unstyled small text-muted flex-grow-1">
                <li>✔ 프로의 모든 기능</li>
                <li>✔ 커스텀 도메인 1개</li>
                <li>✔ API 액세스</li>
                <li>✔ 화이트라벨링</li>
                <li>✔ 24/7 전담 지원</li>
            </ul>
            <a href="<?= $isLoggedIn ? '/payment/checkout?plan=enterprise' : '/auth/register' ?>" class="btn btn-outline-primary w-100">엔터프라이즈 시작하기</a>
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
            <tr><td class="text-start">고객 지원</td><td>이메일</td><td>우선 지원</td><td>24/7 전담</td></tr>
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
            <div class="accordion-body">네, 언제든 상위/하위 요금제로 변경할 수 있도록 준비 중입니다. (결제 시스템 연동 후 지원 예정)</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                연간 결제 할인은 얼마나 되나요?
            </button>
        </h3>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">연간 결제 시 월 요금 기준 20% 할인된 금액이 적용됩니다.</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                환불 정책이 어떻게 되나요?
            </button>
        </h3>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">결제 후 7일 이내에는 전액 환불이 가능하도록 준비 중입니다. (결제 시스템 연동 후 실제 적용 예정)</div>
        </div>
    </div>
    <div class="accordion-item">
        <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                엔터프라이즈 요금제는 어떻게 시작하나요?
            </button>
        </h3>
        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#pricingFaq">
            <div class="accordion-body">회원가입 후 문의를 남겨주시면 필요에 맞는 맞춤 견적을 안내해드릴 예정입니다.</div>
        </div>
    </div>
</div>

<script>
function showBilling(mode) {
    const isYearly = mode === 'yearly';
    document.querySelectorAll('.price-monthly').forEach(el => el.classList.toggle('d-none', isYearly));
    document.querySelectorAll('.price-yearly').forEach(el => el.classList.toggle('d-none', !isYearly));
    document.querySelectorAll('.yearly-note').forEach(el => el.classList.toggle('d-none', !isYearly));
    document.getElementById('btn-monthly').classList.toggle('active', !isYearly);
    document.getElementById('btn-yearly').classList.toggle('active', isYearly);
}
</script>

<?= $this->endSection() ?>
