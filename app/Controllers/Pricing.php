<?php

namespace App\Controllers;

use App\Models\SettingModel;

class Pricing extends BaseController
{
    public function index()
    {
        $model = new SettingModel();

        return view('pricing', [
            'freeLinkLimit'   => (int) $model->getValue('free_plan_link_limit', '100'),
            'proPrice'        => (int) $model->getValue('pro_plan_price', '33000'),
            'enterprisePrice' => (int) $model->getValue('enterprise_plan_price', '79800'),
        ]);
    }
}
