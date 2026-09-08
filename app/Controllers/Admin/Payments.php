<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminLogModel;
use App\Models\PaymentModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Payments extends BaseController
{
    private const PLAN_LABELS = [
        'free'       => '무료',
        'pro'        => '프로',
        'enterprise' => '엔터프라이즈',
    ];

    private const KNOWN_STATUSES = ['completed', 'pending', 'failed'];

    public function index()
    {
        $status   = $this->request->getGet('status');
        $email    = trim((string) $this->request->getGet('email'));
        $dateFrom = $this->request->getGet('date_from');
        $dateTo   = $this->request->getGet('date_to');

        $model = (new PaymentModel())
            ->select('payments.*, users.email AS user_email')
            ->join('users', 'users.id = payments.user_id')
            ->orderBy('payments.created_at', 'DESC');

        // 상태 필터. '취소·만료'는 아직 실제로 만들어지는 상태값이 아니라서(지금은
        // completed/pending/failed 3개뿐), "알려진 3개가 아닌 나머지 전부"로 정의해둠.
        // 나중에 환불 등으로 새 상태값이 생겨도 이 필터가 자동으로 그걸 잡아냄.
        if ($status === 'other') {
            $model->whereNotIn('payments.status', self::KNOWN_STATUSES);
        } elseif (in_array($status, self::KNOWN_STATUSES, true)) {
            $model->where('payments.status', $status);
        }

        if ($email !== '') {
            $model->like('users.email', $email);
        }

        if (! empty($dateFrom)) {
            $model->where('payments.created_at >=', $dateFrom . ' 00:00:00');
        }

        if (! empty($dateTo)) {
            $model->where('payments.created_at <=', $dateTo . ' 23:59:59');
        }

        $payments = $model->paginate(20);
        $pager    = $model->pager;

        return view('admin/payments', [
            'payments'   => $payments,
            'pager'      => $pager,
            'planLabels' => self::PLAN_LABELS,
            'filters'    => [
                'status'     => $status,
                'email'      => $email,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
            ],
            'summary'    => $this->buildSummary(),
            'logsById'   => $this->buildLogsByPaymentId($payments),
        ]);
    }

    // 화면에 보이는 결제 건들(최대 20개)에 대한 처리 이력만 한 번에 모아서 반환.
    // 행마다 따로 쿼리 날리는 대신, id 목록으로 한 번에 가져와서 payment_id별로 묶어줌.
    private function buildLogsByPaymentId(array $payments): array
    {
        $paymentIds = array_column($payments, 'id');

        if ($paymentIds === []) {
            return [];
        }

        $logs = (new AdminLogModel())
            ->select('admin_logs.*, admins.email AS admin_email')
            ->join('admins', 'admins.id = admin_logs.admin_id')
            ->where('admin_logs.target_type', 'payment')
            ->whereIn('admin_logs.target_id', $paymentIds)
            ->orderBy('admin_logs.created_at', 'DESC')
            ->findAll();

        $byId = [];
        foreach ($logs as $log) {
            $byId[(int) $log['target_id']][] = $log;
        }

        return $byId;
    }

    // 토스에 진짜 환불(결제취소) 요청을 보낸다. 완료된 결제만 가능하고,
    // 성공하면 방침대로 그 회원을 무료 요금제로 자동 강등시킨다.
    public function refund(int $id)
    {
        $adminId = (int) session()->get('admin_id');
        $reason  = trim((string) $this->request->getPost('cancel_reason'));

        if ($reason === '') {
            return redirect()->to('/admin/payments')->with('error', '환불 사유를 입력해주세요.');
        }

        $model   = new PaymentModel();
        $payment = $model->find($id);

        if (! $payment) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($payment['status'] !== 'completed') {
            return redirect()->to('/admin/payments')->with('error', '완료된 결제만 환불할 수 있습니다.');
        }

        // 토스 서버가 4xx/5xx를 내려도 예외를 던지지 말고(http_errors => false) 그대로 받아서,
        // 실패 이유(response body)를 로그와 화면 둘 다에 정확히 남긴다.
        try {
            $client   = service('curlrequest');
            $response = $client->post("https://api.tosspayments.com/v1/payments/{$payment['payment_key']}/cancel", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(env('tosspayments.secretKey') . ':'),
                    'Content-Type'  => 'application/json',
                ],
                'json'        => ['cancelReason' => $reason],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '토스 결제취소 통신 실패: ' . $e->getMessage());

            return redirect()->to('/admin/payments')->with('error', '환불 처리 중 통신 오류가 발생했습니다.');
        }

        $result = json_decode($response->getBody(), true);

        if ($response->getStatusCode() !== 200) {
            log_message('error', '토스 결제취소 실패 (' . $response->getStatusCode() . '): ' . $response->getBody());

            return redirect()->to('/admin/payments')->with('error', '토스에서 환불 요청을 거부했습니다: ' . ($result['message'] ?? '알 수 없는 오류'));
        }

        $model->update($id, [
            'status'        => 'refunded',
            'refunded_at'   => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason,
            'toss_response' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'admin_id'      => $adminId,
        ]);

        // 정책: 환불되면 그 회원은 자동으로 무료 요금제로 강등됨(무료는 만료일 개념이 없어서 함께 비움).
        (new UserModel())->update($payment['user_id'], ['plan' => 'free', 'plan_expires_at' => null]);

        (new AdminLogModel())->record(
            $adminId,
            'refund',
            'payment',
            $id,
            "주문 {$payment['order_id']} 환불 처리 (사유: {$reason}). 회원(user_id={$payment['user_id']})을 무료 요금제로 강등함.",
        );

        return redirect()->to('/admin/payments')->with('message', '환불 처리가 완료되었습니다.');
    }

    // 토스가 테스트 키(test_ck_/test_sk_)로 되어있어서 실제 카드 결제 테스트가 어려운 동안,
    // 대기중인 결제 건을 "실제로 결제된 것처럼" 처리해주는 임시 기능. updateStatus()와 달리
    // 이건 회원 plan/plan_expires_at까지 실제 결제 승인(success())과 동일하게 갱신해준다.
    // method를 'admin_test'로 남겨서 나중에 실결제 이력과 절대 헷갈리지 않게 구분함.
    public function markTestCompleted(int $id)
    {
        $adminId = (int) session()->get('admin_id');

        $model   = new PaymentModel();
        $payment = $model->find($id);

        if (! $payment) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($payment['status'] !== 'pending') {
            return redirect()->to('/admin/payments')->with('error', '대기중인 결제만 테스트 완료 처리할 수 있습니다.');
        }

        $model->update($id, [
            'status'      => 'completed',
            'method'      => 'admin_test',
            'approved_at' => date('Y-m-d H:i:s'),
            'admin_id'    => $adminId,
        ]);

        (new UserModel())->update($payment['user_id'], [
            'plan'            => $payment['plan'],
            'plan_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        (new AdminLogModel())->record(
            $adminId,
            'test_complete',
            'payment',
            $id,
            "주문 {$payment['order_id']}을(를) 테스트 완료 처리함(실제 결제 아님). 회원(user_id={$payment['user_id']})을 '{$payment['plan']}' 요금제로 업그레이드함.",
        );

        return redirect()->to('/admin/payments')->with('message', '테스트 완료 처리되었습니다. (실제 결제가 아닙니다)');
    }

    // 상태를 수동으로 바꾼다. 결제 자체를 다시 부르지 않는 단순 수정이라, 회원 요금제에는 영향을 주지 않음
    // (요금제를 직접 바꾸고 싶으면 회원 관리 화면의 요금제 변경 기능을 쓰는 게 맞음 — C단계에서 진행 예정).
    public function updateStatus(int $id)
    {
        $adminId   = (int) session()->get('admin_id');
        $newStatus = $this->request->getPost('status');

        if (! in_array($newStatus, self::KNOWN_STATUSES, true)) {
            return redirect()->to('/admin/payments')->with('error', '올바르지 않은 상태값입니다.');
        }

        $model   = new PaymentModel();
        $payment = $model->find($id);

        if (! $payment) {
            throw PageNotFoundException::forPageNotFound();
        }

        $oldStatus = $payment['status'];
        $model->update($id, ['status' => $newStatus, 'admin_id' => $adminId]);

        (new AdminLogModel())->record(
            $adminId,
            'status_change',
            'payment',
            $id,
            "주문 {$payment['order_id']} 상태를 '{$oldStatus}' → '{$newStatus}'로 수동 변경함.",
        );

        return redirect()->to('/admin/payments')->with('message', '상태가 변경되었습니다.');
    }

    public function updateMemo(int $id)
    {
        $adminId = (int) session()->get('admin_id');
        $memo    = trim((string) $this->request->getPost('admin_memo'));

        $model   = new PaymentModel();
        $payment = $model->find($id);

        if (! $payment) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->update($id, ['admin_memo' => $memo, 'admin_id' => $adminId]);

        (new AdminLogModel())->record($adminId, 'memo_update', 'payment', $id, "주문 {$payment['order_id']} 관리자 메모 수정함.");

        return redirect()->to('/admin/payments')->with('message', '메모가 저장되었습니다.');
    }

    private function buildSummary(): array
    {
        $monthStart = date('Y-m-01 00:00:00');

        // SUM()은 조건에 맞는 행이 0개여도 NULL 한 줄을 돌려주므로(에러 아님), null 방어만 해주면 됨.
        $revenueRow     = (new PaymentModel())
            ->where('status', 'completed')
            ->where('approved_at >=', $monthStart)
            ->selectSum('amount')
            ->first();
        $monthlyRevenue = (int) ($revenueRow['amount'] ?? 0);

        $monthlyCount = (new PaymentModel())
            ->where('status', 'completed')
            ->where('approved_at >=', $monthStart)
            ->countAllResults();

        $pendingCount = (new PaymentModel())
            ->where('status', 'pending')
            ->countAllResults();

        $paidUserCount = (new UserModel())
            ->whereNotIn('plan', ['free'])
            ->countAllResults();

        return [
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyCount'   => $monthlyCount,
            'pendingCount'   => $pendingCount,
            'paidUserCount'  => $paidUserCount,
        ];
    }
}
