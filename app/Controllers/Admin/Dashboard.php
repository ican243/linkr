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

    // 대시보드: 통계 카드(전주 대비 증감률 포함) + 최근 7일 클릭 추이 차트 + 최근 결제 5건.
    // 회원/링크 전체 목록은 각각 별도 메뉴(members()/links())로 분리됨.
    public function index()
    {
        $userModel     = new UserModel();
        $linkModel     = new LinkModel();
        $clickLogModel = new ClickLogModel();
        $paymentModel  = new PaymentModel();

        $weekAgo      = date('Y-m-d H:i:s', strtotime('-7 days'));
        $twoWeeksAgo  = date('Y-m-d H:i:s', strtotime('-14 days'));
        $monthStart   = date('Y-m-01 00:00:00');

        $usersThisWeek = $userModel->where('created_at >=', $weekAgo)->countAllResults();
        $usersLastWeek = $userModel->where('created_at >=', $twoWeeksAgo)->where('created_at <', $weekAgo)->countAllResults();

        $linksThisWeek = $linkModel->where('created_at >=', $weekAgo)->countAllResults();
        $linksLastWeek = $linkModel->where('created_at >=', $twoWeeksAgo)->where('created_at <', $weekAgo)->countAllResults();

        $clicksThisWeek = $clickLogModel->where('clicked_at >=', $weekAgo)->countAllResults();
        $clicksLastWeek = $clickLogModel->where('clicked_at >=', $twoWeeksAgo)->where('clicked_at <', $weekAgo)->countAllResults();

        $revenueThisWeekRow = $paymentModel->where('status', 'completed')->where('approved_at >=', $weekAgo)->selectSum('amount')->first();
        $revenueLastWeekRow = $paymentModel->where('status', 'completed')->where('approved_at >=', $twoWeeksAgo)->where('approved_at <', $weekAgo)->selectSum('amount')->first();
        $revenueThisWeek    = (int) ($revenueThisWeekRow['amount'] ?? 0);
        $revenueLastWeek    = (int) ($revenueLastWeekRow['amount'] ?? 0);

        $revenueThisMonthRow = $paymentModel->where('status', 'completed')->where('approved_at >=', $monthStart)->selectSum('amount')->first();

        $stats = [
            'totalUsers'      => $userModel->countAllResults(),
            'totalLinks'      => $linkModel->countAllResults(),
            'totalClicks'     => $clickLogModel->countAllResults(),
            'monthlyRevenue'  => (int) ($revenueThisMonthRow['amount'] ?? 0),
        ];

        $deltas = [
            'users'   => $this->weekOverWeekDelta($usersThisWeek, $usersLastWeek),
            'links'   => $this->weekOverWeekDelta($linksThisWeek, $linksLastWeek),
            'clicks'  => $this->weekOverWeekDelta($clicksThisWeek, $clicksLastWeek),
            'revenue' => $this->weekOverWeekDelta($revenueThisWeek, $revenueLastWeek),
        ];

        $clickTrend = $this->clickTrendLast7Days();

        $recentPayments = $paymentModel
            ->select('payments.*, users.email AS user_email')
            ->join('users', 'users.id = payments.user_id')
            ->orderBy('payments.created_at', 'DESC')
            ->findAll(5);

        return view('admin/dashboard', [
            'stats'           => $stats,
            'deltas'          => $deltas,
            'clickTrend'      => $clickTrend,
            'recentPayments'  => $recentPayments,
            'planLabels'      => self::PLAN_LABELS,
        ]);
    }

    // 이번 주 값과 지난 주 값을 비교해서 증감률(%)과 방향(up/down/flat)을 계산.
    // 지난주가 0이면 나눗셈이 안 되므로, 이번주도 0이면 flat, 아니면 "새로 생김"이라는 뜻으로 +100%로 표시.
    private function weekOverWeekDelta(int $thisWeek, int $lastWeek): array
    {
        if ($lastWeek === 0) {
            return $thisWeek === 0
                ? ['pct' => 0, 'dir' => 'flat']
                : ['pct' => 100, 'dir' => 'up'];
        }

        $pct = round((($thisWeek - $lastWeek) / $lastWeek) * 100);

        return ['pct' => abs($pct), 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
    }

    // 최근 7일(오늘 포함)의 날짜별 클릭 수. 클릭이 하루도 없었던 날도 0으로 채워서
    // Chart.js에 그대로 넘길 수 있는 배열(labels/data)로 반환.
    private function clickTrendLast7Days(): array
    {
        $since = date('Y-m-d 00:00:00', strtotime('-6 days'));

        $rows = (new ClickLogModel())
            ->select("DATE(clicked_at) AS day, COUNT(*) AS cnt")
            ->where('clicked_at >=', $since)
            ->groupBy('day')
            ->findAll();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = (int) $row['cnt'];
        }

        $labels = [];
        $data   = [];
        for ($i = 6; $i >= 0; $i--) {
            $day      = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('m/d', strtotime($day));
            $data[]   = $byDay[$day] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // 회원 관리 화면(전체 회원 목록, 이메일 검색 + 페이지네이션).
    public function members()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $model = new UserModel();

        if ($q !== '') {
            $model->like('email', $q);
        }

        $users = $model->orderBy('created_at', 'DESC')->paginate(20);

        return view('admin/members', [
            'users' => $users,
            'pager' => $model->pager,
            'q'     => $q,
        ]);
    }

    // 링크 관리 화면(전체 링크 목록, 검색 + 페이지네이션).
    public function links()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $model = (new LinkModel())
            ->select('links.*, users.email AS owner_email')
            ->join('users', 'users.id = links.user_id');

        if ($q !== '') {
            $model->groupStart()
                ->like('links.short_code', $q)
                ->orLike('links.original_url', $q)
                ->orLike('users.email', $q)
                ->groupEnd();
        }

        $links = $model->orderBy('links.created_at', 'DESC')->paginate(20);

        return view('admin/links', [
            'links' => $links,
            'pager' => $model->pager,
            'q'     => $q,
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
            return redirect()->to('/admin/members')->with('error', '올바르지 않은 요금제입니다.');
        }

        if ($reason === '') {
            return redirect()->to('/admin/members')->with('error', '요금제 변경 사유를 입력해주세요.');
        }

        if ($plan !== 'free' && $months < 1) {
            return redirect()->to('/admin/members')->with('error', '이용기간(개월)을 1개월 이상으로 입력해주세요.');
        }

        $userModel = new UserModel();
        $user      = $userModel->find($id);

        if (! $user) {
            return redirect()->to('/admin/members')->with('error', '해당 회원을 찾을 수 없습니다.');
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

        return redirect()->to('/admin/members')->with('message', '회원 요금제가 변경되었습니다.');
    }

    // DNS(TXT 레코드) 확인 없이 관리자가 강제로 커스텀 도메인을 인증 처리한다.
    // 실제 회원용이 아니라, hosts 파일 등으로 로컬 시연할 때처럼 우리 서버가 진짜 DNS로는
    // 절대 확인할 수 없는 상황을 위한 기능이라 admin_logs에 명확히 구분해서 남긴다.
    public function verifyUserDomain(int $id)
    {
        $adminId   = (int) session()->get('admin_id');
        $userModel = new UserModel();
        $user      = $userModel->find($id);

        if (! $user) {
            return redirect()->to('/admin/members')->with('error', '해당 회원을 찾을 수 없습니다.');
        }

        if (empty($user['custom_domain'])) {
            return redirect()->to('/admin/members')->with('error', '이 회원은 등록된 커스텀 도메인이 없습니다.');
        }

        if ((bool) $user['custom_domain_verified']) {
            return redirect()->to('/admin/members')->with('error', '이미 인증된 도메인입니다.');
        }

        $userModel->update($id, ['custom_domain_verified' => true]);

        (new AdminLogModel())->record(
            $adminId,
            'domain_force_verify',
            'user',
            $id,
            "회원(user_id={$id})의 커스텀 도메인 '{$user['custom_domain']}'을(를) DNS 확인 없이 관리자가 강제 인증함(실제 인증 아님, 시연/테스트 목적).",
        );

        return redirect()->to('/admin/members')->with('message', "도메인 '{$user['custom_domain']}'이(가) 강제 인증되었습니다.");
    }

    // 관리자가 회원 비밀번호를 직접 새로 정해서 바꿔준다(전화 문의 등으로 본인이 로그인을
    // 못 할 때). 새 비밀번호 값 자체는 admin_logs에 절대 남기지 않고, "바꿨다"는 사실만 기록함.
    public function updateUserPassword(int $id)
    {
        $adminId  = (int) session()->get('admin_id');
        $password = (string) $this->request->getPost('password');

        $userModel = new UserModel();
        $user      = $userModel->find($id);

        if (! $user) {
            return redirect()->to('/admin/users/' . $id)->with('error', '해당 회원을 찾을 수 없습니다.');
        }

        if (strlen($password) < 8) {
            return redirect()->to('/admin/users/' . $id)->with('error', '비밀번호는 8자 이상이어야 합니다.');
        }

        $userModel->update($id, ['password' => password_hash($password, PASSWORD_DEFAULT)]);

        (new AdminLogModel())->record(
            $adminId,
            'password_change',
            'user',
            $id,
            "회원(user_id={$id})의 비밀번호를 관리자가 변경함.",
        );

        return redirect()->to('/admin/users/' . $id)->with('message', '비밀번호가 변경되었습니다.');
    }

    public function deleteUser(int $id)
    {
        $model = new UserModel();
        $model->delete($id); // 그 회원의 links/click_logs도 FK CASCADE로 같이 삭제됨

        return redirect()->to('/admin/members')->with('message', '회원이 삭제되었습니다.');
    }

    public function deleteLink(int $id)
    {
        $model = new LinkModel();
        $model->delete($id); // click_logs도 FK CASCADE로 같이 삭제됨

        return redirect()->to('/admin/links')->with('message', '링크가 삭제되었습니다.');
    }
}
