<?php

namespace App\Controllers;

use App\Models\LinkModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class Qr extends BaseController
{
    // 단축주소(이미 공개된 정보) 하나를 QR 이미지로 그려서 보여줄 뿐이라 로그인 없이도 접근 허용
    public function show(string $code)
    {
        $model = new LinkModel();
        $link  = $model->where('short_code', $code)->first();

        if (! $link) {
            throw PageNotFoundException::forPageNotFound();
        }

        helper('link');
        $result = (new Builder(
            writer: new PngWriter(),
            data: short_url($link),
            size: 300,
            margin: 10,
        ))->build();

        return $this->response
            ->setContentType('image/png')
            ->setBody($result->getString());
    }
}
