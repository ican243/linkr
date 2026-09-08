<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Dashboard extends BaseController
{
    private const PLAN_LABELS = [
        'free'       => '무료',
        'pro'        => '프로',
        'enterprise' => '엔터프라이즈',
    ];

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

    // 회원 상세 — 결제 이력 + 현재 이용기간(시작일~종료일)을 보여줌.
    // "시작일"은 별도 컬럼이 없고, 지금 요금제를 만들어준 가장 최근 결제(실제 결제든
    // 관리자 강제부여든)의 승인일을 그대로 씀. "종료일"은 users.plan_expires_at.
    public function show(int $id)
    {
        $user = (new UserModel())->find($id);

        if (! $user) {
            throw PageNotFoundException::forPageNotFound();
        }

        $payments = (new PaymentModel())
            ->where('user_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $currentPeriodStart = null;
        if ($user['plan'] !== 'free') {
            $latestGrant = (new PaymentModel())
                ->where('user_id', $id)
                ->where('status', 'completed')
                ->orderBy('approved_at', 'DESC')
                ->first();
            $currentPeriodStart = $latestGrant['approved_at'] ?? null;
        }

        return view('admin/user_detail', [
            'user'               => $user,
            'payments'           => $payments,
            'planLabels'         => self::PLAN_LABELS,
            'currentPeriodStart' => $currentPeriodStart,
        ]);
    }

    // 회원 목록에서 요금제를 직접 바꿀 수 있게 함 (결제 없이 관리자가 강제로 승급/강등).
    // 유료 요금제로 바꿀 땐 개월 수를 받아서 users.plan_expires_at을 채우고, payments에
    // method='admin' 이력을 남기며(사용자 결제내역 화면에도 그대로 보임), admin_logs에도 기록함.
    public function updateUserPlan(int $id)
    {
        $adminId = (int) session()->get('admin_id');
        $plan    = $this->request->getPost('plan');
        $months  = (int) $this->request->getPost('months');
        $reason  = trim((string) $this->request->getPost('reason'));

        if (! in_array($plan, ['free', 'pro', 'enterprise'], true)) {
            return redirect()->to('/admin/dashboard')->with('error', '올바르지 않은 요금제입니다.');
        }

        if ($reason === '') {
            return redirect()->to('/admin/dashboard')->with('error', '요금제 변경 사유를 입력해주세요.');
        }

        if ($plan !== 'free' && $months < 1) {
            return redirect()->to('/admin/dashboard')->with('error', '이용기간(개월)을 1개월 이상으로 입력해주세요.');
        }

        $userModel = new UserModel();
        $user      = $userModel->find($id);

        if (! $user) {
            return redirect()->to('/admin/dashboard')->with('error', '해당 회원을 찾을 수 없습니다.');
        }

        $oldPlan = $user['plan'];

        // 무료로 바꾸는 거면 만료일 자체가 의미 없어서 비움. 유료면 "지금부터 N개월"로
        // 새로 부여함 (기존에 남아있던 기간에 더하지 않음 — admin이 늘 명시적으로 정하는 방식).
        $expiresAt = $plan === 'free' ? null : date('Y-m-d H:i:s', strtotime("+{$months} month"));

        $userModel->update($id, ['plan' => $plan, 'plan_expires_at' => $expiresAt]);

        if ($plan !== 'free') {
            (new PaymentModel())->insert([
                'user_id'     => $id,
                'order_id'    => 'admin_' . bin2hex(random_bytes(16)),
                'amount'      => 0,
                'plan'        => $plan,
                'status'      => 'completed',
                'method'      => 'admin',
                'admin_id'    => $adminId,
                'admin_memo'  => $reason,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
        }

        (new AdminLogModel())->record(
            $adminId,
            'plan_change',
            'user',
            $id,
            "회원(user_id={$id}) 요금제를 '{$oldPlan}' → '{$plan}'로 변경함"
                . ($plan !== 'free' ? " ({$months}개월 부여)" : '')
                . " (사유: {$reason})",
        );

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
