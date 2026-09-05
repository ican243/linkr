<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\PaymentModel;

class Payments extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $model = (new PaymentModel())
            ->where('user_id', session()->get('user_id'))
            ->orderBy('created_at', 'DESC');

        $payments = $model->paginate(10);

        return view('user/payments', ['payments' => $payments, 'pager' => $model->pager]);
    }
}
