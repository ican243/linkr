<?php

namespace App\Controllers;

use App\Libraries\RateLimit;
use App\Models\LinkModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class Qr extends BaseController
{
    // 단축주소(이미 공개된 정보) 하나를 QR 이미지로 그려서 보여줄 뿐이라 로그인 없이도 접근 허용
    // 이미지를 만드는 작업이 꽤 무거워서, 같은 IP는 1분에 60장까지만 (넘으면 429 Too Many Requests)
    public function show(string $code)
    {
        if (! RateLimit::allow('qr', $this->request->getIPAddress(), 60, 60)) {
            return $this->response->setStatusCode(429)
                ->setHeader('Retry-After', (string) RateLimit::retryAfter())
                ->setBody('요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.');
        }

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
            // 같은 QR을 다시 볼 때는 브라우저에 저장된 이미지를 쓰게 해서 서버 요청 자체를 줄임
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody($result->getString());
    }
}
