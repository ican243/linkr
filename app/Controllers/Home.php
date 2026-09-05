<?php

namespace App\Controllers;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class Home extends BaseController
{
    public function index(): string
    {
        return view('home');
    }

    // 로그인 안 한 방문자가 회원가입 없이 "이렇게 만들어져요"를 바로 볼 수 있게 하는 미리보기.
    // 실제로 DB에 저장되지 않고, 매 요청마다 랜덤 코드로 화면에만 보여주는 예시일 뿐임
    // (진짜로 저장하려면 로그인 후 실제 단축 기능을 써야 함).
    public function previewShorten(): string
    {
        $url = $this->request->getPost('original_url');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return view('home', ['previewError' => '올바른 URL 형식이 아닙니다. (예: https://example.com)']);
        }

        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code  = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $shortUrl = base_url($code);

        $qr = (new Builder(
            writer: new PngWriter(),
            data: $shortUrl,
            size: 160,
            margin: 8,
        ))->build();

        return view('home', [
            'preview' => [
                'original_url' => $url,
                'short_url'    => $shortUrl,
                'qr_data_uri'  => 'data:image/png;base64,' . base64_encode($qr->getString()),
            ],
        ]);
    }
}
