<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\LinkModel;

class Links extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $userId = session()->get('user_id');
        $query  = trim((string) $this->request->getGet('q'));
        $status = $this->request->getGet('status') ?? 'all';
        $sort   = $this->request->getGet('sort') ?? 'newest';

        $model = (new LinkModel())->where('user_id', $userId);

        if ($query !== '') {
            $model->groupStart()
                ->like('title', $query)
                ->orLike('original_url', $query)
                ->orLike('short_code', $query)
                ->groupEnd();
        }

        if ($status === 'password') {
            $model->where('password IS NOT NULL');
        } elseif ($status === 'expired') {
            $model->where('expires_at IS NOT NULL')->where('expires_at <', date('Y-m-d H:i:s'));
        }

        if ($sort === 'oldest') {
            $model->orderBy('created_at', 'ASC');
        } elseif ($sort === 'clicks') {
            $model->orderBy('click_count', 'DESC');
        } else {
            $sort = 'newest';
            $model->orderBy('created_at', 'DESC');
        }

        $links = $model->paginate(10);

        helper('link');
		return view('user/links', [
            'links'  => $links,
            'pager'  => $model->pager,
            'query'  => $query,
            'status' => $status,
            'sort'   => $sort,
        ]);
    }
}
