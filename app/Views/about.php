<?= $this->extend('layout') ?>

<?= $this->section('content') ?>

<div class="text-center py-4">
    <h1 class="fw-bold">Linkr는 이런 서비스예요</h1>
    <p class="lead text-muted">긴 주소를 짧게 줄이고, 클릭 통계까지 한 번에 관리하는 URL 단축 서비스입니다.</p>
</div>

<!-- 이용 흐름 3단계 -->
<div class="row row-cols-1 row-cols-md-3 g-4 py-3">
    <div class="col">
        <div class="card h-100 p-4 text-center">
            <div class="feature-icon mx-auto mb-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <path d="M7 9h10M7 13h6"/>
                </svg>
            </div>
            <h2 class="h6 mt-2">1. 원본 주소 입력</h2>
            <p class="text-muted small mb-0">단축하고 싶은 긴 URL을 붙여넣으세요. 원하면 커스텀 별칭이나 만료일, 비밀번호도 설정할 수 있어요.</p>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 p-4 text-center">
            <div class="feature-icon mx-auto mb-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 15L15 9"/>
                    <path d="M10.5 6.5l1-1a3.5 3.5 0 015 5l-1 1"/>
                    <path d="M13.5 17.5l-1 1a3.5 3.5 0 01-5-5l1-1"/>
                </svg>
            </div>
            <h2 class="h6 mt-2">2. 짧은 주소 생성</h2>
            <p class="text-muted small mb-0">누구나 기억하기 쉬운 짧은 주소가 즉시 만들어져요. QR 코드도 같이 받을 수 있어요.</p>
        </div>
    </div>
    <div class="col">
        <div class="card h-100 p-4 text-center">
            <div class="feature-icon mx-auto mb-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 20V12"/>
                    <path d="M12 20V4"/>
                    <path d="M20 20v-7"/>
                </svg>
            </div>
            <h2 class="h6 mt-2">3. 클릭 통계 확인</h2>
            <p class="text-muted small mb-0">누가 언제 클릭했는지 대시보드에서 그래프로 바로 확인할 수 있어요.</p>
        </div>
    </div>
</div>

<!-- 이런 분들께 추천 -->
<div class="py-5">
    <h2 class="text-center h4 mb-4">이런 분들께 추천해요</h2>
    <div class="row row-cols-1 row-cols-md-2 g-4">
        <div class="col">
            <div class="card h-100 p-4">
                <p class="mb-0">📢 SNS나 카카오톡에 길고 지저분한 URL 대신, 깔끔한 짧은 주소를 공유하고 싶은 분</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <p class="mb-0">📊 공유한 링크를 누가, 언제 클릭했는지 통계로 확인하고 싶은 분</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <p class="mb-0">🖨️ 인쇄물이나 포스터에 QR 코드로 넣을 짧은 주소가 필요한 분</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <p class="mb-0">🌐 나만의 도메인으로 브랜딩된 짧은 주소를 쓰고 싶은 분</p>
            </div>
        </div>
    </div>
</div>

<div class="py-5 text-center">
    <h2 class="h4 mb-2">무료로 바로 시작해보세요</h2>
    <p class="text-muted">신용카드 없이 회원가입만으로 월 100개까지 무료로 링크를 만들 수 있어요.</p>
    <a href="/auth/register" class="btn btn-primary me-2">무료로 시작하기</a>
    <a href="/pricing" class="btn btn-outline-primary">요금제 보기</a>
</div>

<p class="text-muted small text-center mb-0">Linkr는 개인 학습 목적으로 만들어지고 있는 프로젝트입니다.</p>

<?= $this->endSection() ?>
