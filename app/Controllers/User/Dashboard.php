<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Controllers\Link;
use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $userId = session()->get('user_id');
        $model  = new LinkModel();
        $links  = $model->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $user = (new UserModel())->find($userId);

        $usedThisMonth = null;
        if ($user['plan'] === 'free') {
            $usedThisMonth = $model
                ->where('user_id', $userId)
                ->where('created_at >=', date('Y-m-01 00:00:00'))
                ->countAllResults();
        }

        helper('link');
		return view('user/dashboard', [
            'links'         => $links,
            'plan'          => $user['plan'],
            'usedThisMonth' => $usedThisMonth,
            'monthlyLimit'  => Link::freePlanMonthlyLimit(),
            'totalLinks'    => count($links),
            'totalClicks'   => array_sum(array_column($links, 'click_count')),
            'topLink'       => $this->findTopLink($links),
            'dailyStats'    => $this->getDailyStats($userId),
        ]);
    }

    /**
     * 내 링크들 중 click_count가 가장 높은 1개를 찾는다.
     * 이미 컨트롤러에서 links를 전부 조회해둔 상태라, DB에 다시 묻지 않고
     * PHP 배열 안에서 찾는 방식으로 만듦 (똑같은 쿼리를 두 번 안 날리려고).
     */
    private function findTopLink(array $links): ?array
    {
        if (empty($links)) {
            return null;
        }

        $top = $links[0];
        foreach ($links as $link) {
            if ((int) $link['click_count'] > (int) $top['click_count']) {
                $top = $link;
            }
        }

        return $top;
    }

    /**
     * 최근 7일간 "내 링크"에 대한 날짜별 클릭 수를 계산한다.
     * click_logs에는 클릭이 있었던 날짜만 남아있으므로, 클릭이 0인 날도
     * 그래프에 나오도록 먼저 7일치 날짜를 0으로 채워둔 뒤 실제 값을 덮어씀.
     *
     * @return array<string, int> 예: ['08-22' => 0, '08-23' => 2, ...] (키는 화면에 보일 라벨)
     */
    private function getDailyStats(int $userId): array
    {
        $weekdayKr = ['일', '월', '화', '수', '목', '금', '토'];

        // 1) 최근 7일(오늘 포함) 날짜를 전부 0으로 미리 채워둠
        $stats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date          = date('Y-m-d', strtotime("-{$i} days"));
            $label         = $weekdayKr[(int) date('w', strtotime($date))];
            $stats[$date]  = ['label' => $label, 'count' => 0];
        }

        // 2) click_logs를 links와 JOIN해서, 내 링크에 대한 클릭만 날짜별로 집계
        $rows = (new ClickLogModel())
            ->select('DATE(click_logs.clicked_at) AS click_date, COUNT(*) AS click_total', false)
            ->join('links', 'links.id = click_logs.link_id')
            ->where('links.user_id', $userId)
            ->where('click_logs.clicked_at >=', array_key_first($stats) . ' 00:00:00')
            ->groupBy('click_date')
            ->findAll();

        // 3) 실제 클릭이 있었던 날짜만 0에서 실제 값으로 덮어씀
        foreach ($rows as $row) {
            if (isset($stats[$row['click_date']])) {
                $stats[$row['click_date']]['count'] = (int) $row['click_total'];
            }
        }

        return $stats;
    }
}
