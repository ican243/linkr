<?php
// 없는 주소(또는 없는 단축 링크)로 들어왔을 때 보여주는 화면
$errorCode    = '404';
$errorHeading = '페이지를 찾을 수 없어요';
$errorMessage = '주소가 잘못 입력됐거나, 삭제된 링크일 수 있어요. 주소를 다시 확인해주세요.';

include __DIR__ . '/brand_error.php';
