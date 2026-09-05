<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $userModel     = new UserModel();
        $linkModel     = new LinkModel();
        $clickLogModel = new ClickLogModel();

        $stats = [
            'totalUsers'  => $userModel->countAllResults(),
            'totalLinks'  => $linkModel->countAllResults(),
            'totalClicks' => $clickLogModel->countAllResults(),
        ];

        $users = $userModel->orderBy('created_at', 'DESC')->findAll();

        $links = $linkModel
            ->select('links.*, users.email AS owner_email')
            ->join('users', 'users.id = links.user_id')
            ->orderBy('links.created_at', 'DESC')
            ->findAll();

        return view('admin/dashboard', [
            'stats' => $stats,
            'users' => $users,
            'links' => $links,
        ]);
    }

    // 회원 목록에서 요금제를 직접 바꿀 수 있게 함 (결제 없이 관리자가 강제로 승급/강등).
    public function updateUserPlan(int $id)
    {
        $plan = $this->request->getPost('plan');

        if (! in_array($plan, ['free', 'pro', 'enterprise'], true)) {
            return redirect()->to('/admin/dashboard')->with('error', '올바르지 않은 요금제입니다.');
        }

        $model = new UserModel();

        if (! $model->find($id)) {
            return redirect()->to('/admin/dashboard')->with('error', '해당 회원을 찾을 수 없습니다.');
        }

        $model->update($id, ['plan' => $plan]);

        return redirect()->to('/admin/dashboard')->with('message', '회원 요금제가 변경되었습니다.');
    }

    public function deleteUser(int $id)
    {
        $model = new UserModel();
        $model->delete($id); // 그 회원의 links/click_logs도 FK CASCADE로 같이 삭제됨

        return redirect()->to('/admin/dashboard')->with('message', '회원이 삭제되었습니다.');
    }

    public function deleteLink(int $id)
    {
        $model = new LinkModel();
        $model->delete($id); // click_logs도 FK CASCADE로 같이 삭제됨

        return redirect()->to('/admin/dashboard')->with('message', '링크가 삭제되었습니다.');
    }
}
