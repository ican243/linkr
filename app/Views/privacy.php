<?= $this->extend('layout') ?>

<?= $this->section('content') ?>

<div class="py-4">
    <h1 class="fw-bold mb-1">개인정보처리방침</h1>
    <p class="text-muted mb-4">시행일: <?= date('Y-m-d') ?></p>

    <p>
        Linkr(이하 "서비스")는 이용자의 개인정보를 소중히 다루며,
        「개인정보보호법」 등 관련 법령을 준수하기 위해 다음과 같이 개인정보처리방침을 안내합니다.
    </p>

    <h2 class="h5 fw-bold mt-4">1. 수집하는 개인정보 항목</h2>
    <table class="table table-bordered table-sm mt-2">
        <thead class="table-light">
            <tr>
                <th style="width: 25%">구분</th>
                <th>수집 항목</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>일반 회원가입</td>
                <td>이메일, 비밀번호(암호화 저장), 이름(선택)</td>
            </tr>
            <tr>
                <td>소셜 로그인 (구글 / 네이버 / 카카오)</td>
                <td>이메일, 이름(닉네임), 소셜 계정 식별값(고유 ID)</td>
            </tr>
            <tr>
                <td>단축 링크 클릭 통계</td>
                <td>접속 IP 주소, 브라우저/기기 정보(User-Agent), 클릭 일시</td>
            </tr>
            <tr>
                <td>유료 요금제 결제</td>
                <td>주문번호, 결제금액, 결제수단, 결제일시 (카드번호 등 결제 상세정보는 결제대행사가 직접 처리하며 서비스는 저장하지 않습니다)</td>
            </tr>
        </tbody>
    </table>

    <h2 class="h5 fw-bold mt-4">2. 개인정보 수집 및 이용 목적</h2>
    <ul>
        <li>회원 식별 및 로그인 인증, 서비스 부정 이용 방지</li>
        <li>단축 링크 생성/관리, 클릭 통계 제공</li>
        <li>유료 요금제 결제 처리 및 결제 내역 확인</li>
        <li>문의 응대 및 서비스 관련 안내</li>
    </ul>

    <h2 class="h5 fw-bold mt-4">3. 개인정보의 보유 및 이용 기간</h2>
    <p>
        원칙적으로 회원 탈퇴 시 지체 없이 파기합니다. 다만 다음의 경우 명시한 기간 동안 보관합니다.
    </p>
    <ul>
        <li>결제 관련 기록: 「전자상거래 등에서의 소비자보호에 관한 법률」에 따라 5년</li>
        <li>부정 이용 방지를 위한 접속 기록(IP 등): 최대 3개월</li>
    </ul>

    <h2 class="h5 fw-bold mt-4">4. 개인정보의 제3자 제공 및 위탁</h2>
    <p>서비스는 아래 목적을 위해 최소한의 정보를 외부 사업자와 공유합니다.</p>
    <ul>
        <li><strong>소셜 로그인</strong>: 구글(Google), 네이버(Naver), 카카오(Kakao) — 로그인 시 이메일·프로필 정보 확인 목적</li>
        <li><strong>결제대행</strong>: 토스페이먼츠 — 결제 승인 및 처리 목적</li>
    </ul>
    <p>이 외의 목적으로 이용자의 개인정보를 제3자에게 제공하지 않습니다.</p>

    <h2 class="h5 fw-bold mt-4">5. 이용자의 권리</h2>
    <p>
        이용자는 언제든지 로그인 후 계정 설정 화면에서 등록된 정보를 열람·수정할 수 있으며,
        회원 탈퇴를 통해 개인정보 삭제를 요청할 수 있습니다.
    </p>

    <h2 class="h5 fw-bold mt-4">6. 개인정보 보호책임자 및 문의</h2>
    <p>
        개인정보 관련 문의사항은 아래 이메일로 연락해 주시기 바랍니다.<br>
        이메일: <a href="mailto:<?= esc(env('privacy.contactEmail', 'contact@example.com')) ?>"><?= esc(env('privacy.contactEmail', 'contact@example.com')) ?></a>
    </p>

    <p class="text-muted small mt-4">
        ※ 본 서비스는 개인 학습 목적으로 운영되는 프로젝트입니다.
    </p>
</div>

<?= $this->endSection() ?>
