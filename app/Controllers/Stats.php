<?php

namespace App\Controllers;

use App\Models\ClickLogModel;
use App\Models\LinkModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Stats extends BaseController
{
    // 무료 요금제는 클릭 기록을 최근 며칠까지만 볼 수 있는지 (pricing.php의 "클릭 통계 보관기간: 7일" 기준)
    private const FREE_PLAN_HISTORY_DAYS = 7;

    public function show(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $linkModel = new LinkModel();
        $link      = $linkModel->where('short_code', $code)->first();

        // 링크가 없거나, 있어도 내 것이 아니면 둘 다 그냥 404로 처리한다.
        // ("남의 링크입니다" 라고 따로 알려주면 오히려 그 코드가 존재한다는 걸 알려주는 셈이라 똑같이 처리)
        if (! $link || (int) $link['user_id'] !== (int) session()->get('user_id')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $user       = (new UserModel())->find(session()->get('user_id'));
        $isFreePlan = $user['plan'] === 'free';

        // 실제 데이터를 지우는 게 아니라, 무료 회원 화면에 보여줄 때만 최근 7일로 걸러서 보여준다.
        // (나중에 프로로 업그레이드하면 그 이전 기록도 다시 보임 — 데이터 자체는 항상 그대로 보존됨)
        $historyCutoff = $isFreePlan
            ? date('Y-m-d H:i:s', strtotime('-' . self::FREE_PLAN_HISTORY_DAYS . ' days'))
            : null;

        $totalClicksQuery = (new ClickLogModel())->where('link_id', $link['id']);
        if ($historyCutoff !== null) {
            $totalClicksQuery->where('clicked_at >=', $historyCutoff);
        }
        $totalClicks = $totalClicksQuery->countAllResults();

        $dailyClicksQuery = (new ClickLogModel())
            ->select('DATE(clicked_at) AS day, COUNT(*) AS cnt')
            ->where('link_id', $link['id']);
        if ($historyCutoff !== null) {
            $dailyClicksQuery->where('clicked_at >=', $historyCutoff);
        }
        $dailyClicks = $dailyClicksQuery->groupBy('day')->orderBy('day', 'DESC')->limit(14)->find();

        $recentLogsQuery = (new ClickLogModel())->where('link_id', $link['id']);
        if ($historyCutoff !== null) {
            $recentLogsQuery->where('clicked_at >=', $historyCutoff);
        }
        $recentLogs = $recentLogsQuery->orderBy('clicked_at', 'DESC')->limit(50)->find();

        return view('stats/show', [
            'link'          => $link,
            'totalClicks'   => $totalClicks,
            'dailyClicks'   => $dailyClicks,
            'recentLogs'    => $recentLogs,
            'isFreePlan'    => $isFreePlan,
            'historyDays'   => self::FREE_PLAN_HISTORY_DAYS,
            'canExportCsv'  => ! $isFreePlan,
        ]);
    }

    // 클릭 기록을 CSV로 내려받기 (프로 요금제부터). 데이터는 화면과 동일하게
    // 무료 회원 여부와 상관없이 항상 "본인 소유 링크인지"부터 확인한 뒤, 요금제를 확인한다.
    public function exportCsv(string $code)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $linkModel = new LinkModel();
        $link      = $linkModel->where('short_code', $code)->first();

        if (! $link || (int) $link['user_id'] !== (int) session()->get('user_id')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $user = (new UserModel())->find(session()->get('user_id'));

        if ($user['plan'] === 'free') {
            return redirect()->to("/links/{$code}/stats")->with('error', 'CSV 내보내기는 프로 요금제부터 사용할 수 있습니다.');
        }

        $logs = (new ClickLogModel())
            ->where('link_id', $link['id'])
            ->orderBy('clicked_at', 'DESC')
            ->findAll();

        $csv = "클릭일시,IP주소,User-Agent\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,\"%s\"\n",
                $log['clicked_at'],
                $log['ip_address'] ?? '',
                str_replace('"', '""', (string) $log['user_agent']),
            );
        }

        // 앞에 UTF-8 BOM(\xEF\xBB\xBF)을 붙여야 엑셀에서 열었을 때 한글이 안 깨짐.
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="Linkr_' . $code . '_clicks.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }
}
