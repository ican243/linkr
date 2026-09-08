<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\LinkModel;

class Statistics extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $userId = session()->get('user_id');
        $model  = new LinkModel();
        $links  = $model->where('user_id', $userId)->findAll();

        $totalLinks  = count($links);
        $totalClicks = array_sum(array_column($links, 'click_count'));
        $topClicks   = $totalLinks > 0 ? max(array_column($links, 'click_count')) : 0;
        $avgClicks   = $totalLinks > 0 ? round($totalClicks / $totalLinks, 1) : 0;

        $topLinks = $links;
        usort($topLinks, static fn ($a, $b) => (int) $b['click_count'] <=> (int) $a['click_count']);
        $topLinks = array_slice($topLinks, 0, 5);

        helper('link');
		return view('user/statistics', [
            'totalLinks'      => $totalLinks,
            'totalClicks'     => $totalClicks,
            'topClicks'       => $topClicks,
            'avgClicks'       => $avgClicks,
            'topLinks'        => $topLinks,
            'creationStats'   => $this->getCreationStats($userId),
        ]);
    }

    /**
     * 최근 7일간 "링크를 몇 개 만들었는지" 날짜별로 집계한다.
     * 46번 항목의 클릭 추이 집계와 같은 방식(0으로 미리 채운 뒤 실제 값으로 덮어씀)이지만,
     * 대상이 click_logs가 아니라 links.created_at이라는 점이 다르다.
     */
    private function getCreationStats(int $userId): array
    {
        $weekdayKr = ['일', '월', '화', '수', '목', '금', '토'];

        $stats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date          = date('Y-m-d', strtotime("-{$i} days"));
            $label         = $weekdayKr[(int) date('w', strtotime($date))];
            $stats[$date]  = ['label' => $label, 'count' => 0];
        }

        $rows = (new LinkModel())
            ->select('DATE(created_at) AS created_date, COUNT(*) AS created_total', false)
            ->where('user_id', $userId)
            ->where('created_at >=', array_key_first($stats) . ' 00:00:00')
            ->groupBy('created_date')
            ->findAll();

        foreach ($rows as $row) {
            if (isset($stats[$row['created_date']])) {
                $stats[$row['created_date']]['count'] = (int) $row['created_total'];
            }
        }

        return $stats;
    }
}
