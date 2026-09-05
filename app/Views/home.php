<?php $isLoggedIn = (bool) session()->get('isLoggedIn'); ?>
<?= $this->extend('layout') ?>

<?= $this->section('content') ?>

<!-- 히어로 -->
<div class="king-hero mb-5">
    <div class="row align-items-center g-5">
        <div class="col-lg-6 text-center text-lg-start">
            <h1 class="mb-2">king</h1>
            <p class="lead">긴 주소를 짧은 주소로, 클릭 통계까지 한 번에.</p>

            <form action="<?= $isLoggedIn ? '/shorten' : '/demo-preview' ?>" method="post" class="d-flex flex-column flex-sm-row justify-content-center justify-content-lg-start gap-2 mt-4">
                <?= csrf_field() ?>
                <input type="url" name="original_url" placeholder="https://example.com" required
                       value="<?= esc($preview['original_url'] ?? '') ?>"
                       class="form-control" style="max-width:400px;">
                <button type="submit" class="btn btn-primary"><?= $isLoggedIn ? '단축하기' : '미리보기 보기' ?></button>
            </form>

            <?php if (! empty($previewError)) : ?>
                <p class="text-danger mt-3 mb-0"><?= esc($previewError) ?></p>
            <?php elseif (! $isLoggedIn) : ?>
                <p class="text-muted mt-3 mb-0">* 회원가입 없이 결과 예시만 먼저 확인할 수 있어요. 실제로 링크를 저장하려면 회원가입이 필요합니다.</p>
            <?php endif ?>

            <?php if (! empty($preview)) : ?>
                <div class="card p-3 mt-4 text-start" style="max-width:460px;">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= $preview['qr_data_uri'] ?>" alt="QR 미리보기" width="72" height="72" class="rounded">
                        <div class="flex-grow-1 text-truncate">
                            <div class="text-muted small text-truncate"><?= esc($preview['original_url']) ?></div>
                            <div class="fw-bold text-primary text-truncate"><?= esc($preview['short_url']) ?></div>
                        </div>
                    </div>
                    <div class="text-muted small mt-3 mb-2">* 이건 미리보기 예시라 실제로 접속되진 않아요. 회원가입하면 진짜로 저장돼서 바로 쓸 수 있어요.</div>
                    <a href="/auth/register" class="btn btn-primary btn-sm w-100">가입하고 진짜로 저장하기</a>
                </div>
            <?php endif ?>
        </div>

        <div class="col-lg-6">
            <!-- 실제 스크린샷 대신, 제품 사용 흐름을 보여주는 목업(순수 CSS/SVG, 이미지 파일 없음) -->
            <div class="king-hero-visual">
                <div class="mockup-window">
                    <div class="mockup-bar">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="mockup-body">
                        <div class="mockup-url-row">https://example.com/campaign/summer-sale/2026?utm_source=...</div>
                        <div class="mockup-arrow">↓</div>
                        <div class="mockup-url-row mockup-url-short">
                            <span>kir1.cafe24.com/AbC123</span>
                            <span class="badge">복사됨</span>
                        </div>
                    </div>
                </div>
                <div class="mockup-stats-card">
                    <div class="mockup-stats-title">오늘 클릭</div>
                    <div class="mockup-stats-number">1,284</div>
                    <svg class="mockup-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none">
                        <polyline points="0,25 15,20 30,22 45,10 60,14 75,5 90,8 100,3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 기능 소개 -->
<div class="py-4">
    <h2 class="text-center h4 mb-4">king이 제공하는 기능</h2>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 15L15 9"/>
                        <path d="M10.5 6.5l1-1a3.5 3.5 0 015 5l-1 1"/>
                        <path d="M13.5 17.5l-1 1a3.5 3.5 0 01-5-5l1-1"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">URL 단축</h3>
                <p class="text-muted small mb-0">긴 주소를 짧고 기억하기 쉬운 주소로 바꿔드려요.</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="3" height="3"/>
                        <rect x="18" y="14" width="3" height="3"/>
                        <rect x="14" y="18" width="3" height="3"/>
                        <rect x="18" y="18" width="3" height="3"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">QR 코드</h3>
                <p class="text-muted small mb-0">단축한 주소를 QR 코드로도 바로 받아서 인쇄물 등에 쓸 수 있어요.</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="11" width="14" height="9" rx="2"/>
                        <path d="M8 11V7a4 4 0 018 0v4"/>
                        <circle cx="12" cy="15.5" r="1.4" fill="currentColor" stroke="none"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">비밀번호 보호</h3>
                <p class="text-muted small mb-0">아무나 못 열게, 링크에 비밀번호를 걸 수 있어요.</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="8.5"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">만료일 설정</h3>
                <p class="text-muted small mb-0">정해진 시각이 지나면 자동으로 링크가 만료돼요.</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 20V12"/>
                        <path d="M12 20V4"/>
                        <path d="M20 20v-7"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">클릭 통계</h3>
                <p class="text-muted small mb-0">누가 언제 얼마나 클릭했는지 그래프로 확인할 수 있어요.</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 p-4">
                <div class="feature-icon mb-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="8.5"/>
                        <path d="M3.5 12h17"/>
                        <path d="M12 3.5c2.5 2.3 3.9 5.4 3.9 8.5s-1.4 6.2-3.9 8.5c-2.5-2.3-3.9-5.4-3.9-8.5S9.5 5.8 12 3.5z"/>
                    </svg>
                </div>
                <h3 class="h6 mt-2">커스텀 도메인</h3>
                <p class="text-muted small mb-0">내 도메인을 연결해서 나만의 단축 주소를 만들 수 있어요.</p>
            </div>
        </div>
    </div>
</div>

<!-- 요금제 미리보기 (자세한 내용은 전용 페이지로) -->
<div class="py-5 text-center">
    <h2 class="h4 mb-2">요금제</h2>
    <p class="text-muted">무료로 시작해서, 필요할 때 업그레이드하세요.</p>
    <a href="/pricing" class="btn btn-outline-primary">요금제 자세히 보기</a>
</div>

<?= $this->endSection() ?>
