<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\LinkModel;

class QrCodes extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $query = trim((string) $this->request->getGet('q'));

        $model = (new LinkModel())->where('user_id', session()->get('user_id'));

        if ($query !== '') {
            $model->groupStart()
                ->like('title', $query)
                ->orLike('original_url', $query)
                ->orLike('short_code', $query)
                ->groupEnd();
        }

        $links = $model->orderBy('created_at', 'DESC')->paginate(8);

        helper('link');
		return view('user/qr_codes', [
            'links' => $links,
            'pager' => $model->pager,
            'query' => $query,
        ]);
    }
}
