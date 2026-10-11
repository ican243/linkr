<?php
// 체험 계정으로 로그인했을 때만 화면 맨 위에 보이는 안내 띠 (layout.php, user_layout.php에서 불러옴).
// 체험 계정에서 막힌 기능을 눌렀을 때의 안내(demo_blocked)도 여기서 같이 보여줌.
if (! \App\Libraries\DemoAccount::isDemoSession()) {
    return;
}
$blocked = session()->getFlashdata('demo_blocked');
?>
<div class="king-demo-bar">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-center gap-2 py-2 px-3 text-center">
        <span><strong>체험 계정</strong>으로 둘러보는 중이에요<span class="d-none d-sm-inline"> · 매일 새벽 4시에 처음 상태로 돌아가요</span></span>
        <a href="/logout?to=register" class="king-demo-bar-link">내 계정 만들기 ›</a>
    </div>
</div>
<?php if ($blocked) : ?>
    <div class="alert alert-warning rounded-0 mb-0 text-center small" role="alert"><?= esc($blocked) ?></div>
<?php endif ?>
