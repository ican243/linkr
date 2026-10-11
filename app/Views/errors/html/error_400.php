<?php
// 주소에 허용되지 않는 문자가 들어가는 등 잘못된 요청(400)일 때 보여주는 화면
$errorCode    = '400';
$errorHeading = '잘못된 요청이에요';
$errorMessage = '요청한 주소의 형식이 올바르지 않아요. 주소를 다시 확인해주세요.';

include __DIR__ . '/brand_error.php';
