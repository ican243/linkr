<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class Settings extends BaseController
{
    // /admin/settings 라우트에 adminAuth 필터가 걸려있어서, 여기까지 온 시점엔
    // 이미 관리자 로그인이 확인된 상태임(Dashboard 컨트롤러와 같은 방식).
    public function form()
    {
        $model = new SettingModel();

        return view('admin/settings', [
            'freeLinkLimit'   => $model->getValue('free_plan_link_limit', '100'),
            'proPrice'        => $model->getValue('pro_plan_price', '33000'),
            'enterprisePrice' => $model->getValue('enterprise_plan_price', '79800'),
        ]);
    }

    public function update()
    {
        $rules = [
            'free_plan_link_limit'  => 'required|is_natural_no_zero',
            'pro_plan_price'        => 'required|is_natural_no_zero',
            'enterprise_plan_price' => 'required|is_natural_no_zero',
        ];

        $messages = [
            'free_plan_link_limit'  => ['required' => '무료 요금제 월 링크 한도를 입력해주세요.', 'is_natural_no_zero' => '0보다 큰 숫자만 입력해주세요.'],
            'pro_plan_price'        => ['required' => '프로 요금제 가격을 입력해주세요.', 'is_natural_no_zero' => '0보다 큰 숫자만 입력해주세요.'],
            'enterprise_plan_price' => ['required' => '엔터프라이즈 요금제 가격을 입력해주세요.', 'is_natural_no_zero' => '0보다 큰 숫자만 입력해주세요.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->to('/admin/settings')->with('error', implode(' ', $this->validator->getErrors()))->withInput();
        }

        $model = new SettingModel();
        $model->setValue('free_plan_link_limit', (string) (int) $this->request->getPost('free_plan_link_limit'));
        $model->setValue('pro_plan_price', (string) (int) $this->request->getPost('pro_plan_price'));
        $model->setValue('enterprise_plan_price', (string) (int) $this->request->getPost('enterprise_plan_price'));

        return redirect()->to('/admin/settings')->with('message', '설정이 저장되었습니다. 요금제 페이지와 결제 화면에 바로 반영됩니다.');
    }
}
