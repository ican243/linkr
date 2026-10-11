<?php
// 운영(production) 환경에서 예상치 못한 오류(500)가 났을 때 보여주는 화면.
// 오류의 자세한 내용은 방문자에게 보여주지 않고 writable/logs 로그 파일에만 남음.
$errorCode    = '500';
$errorHeading = '잠시 문제가 생겼어요';
$errorMessage = '일시적인 오류가 발생했어요. 잠시 후 다시 시도해주세요.';

include __DIR__ . '/brand_error.php';
