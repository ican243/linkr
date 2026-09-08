<!doctype html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>연결되지 않은 도메인</title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Pretendard, sans-serif; background:#f8f9fa; color:#212529; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { text-align:center; padding:2rem; max-width:420px; }
    h1 { font-size:1.4rem; margin-bottom:0.75rem; }
    p { color:#6c757d; }
</style>
</head>
<body>
<div class="box">
    <h1>연결되지 않은 도메인입니다</h1>
    <p><?= esc($host ?? '') ?> 은(는) Linkr 서비스에 연결된 도메인이 아닙니다.</p>
</div>
</body>
</html>
