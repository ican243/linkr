<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">결제 내역</h1>

<!-- 요약 카드: 현재 요금제 + 다음 결제 예정일 -->
<div class="card p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="text-muted small">현재 이용 중인 요금제</div>
            <div class="fs-4 fw-bold"><?= esc($planLabels[$currentPlan] ?? $currentPlan) ?></div>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="text-muted small">다음 결제 예정일</div>
            <div class="fs-5 fw-bold">
                <?= $nextBillingDate ? esc(date('Y년 n월 j일', strtotime($nextBillingDate))) : '해당 없음' ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($payments)) : ?>
    <p class="text-muted">아직 결제 내역이 없습니다.</p>
<?php else : ?>

    <?php
    // 화면(테이블/카드/모달)에서 공통으로 쓰는 값들을 미리 계산해서 $rows에 모아둠.
    $statusBadges = [
        'completed' => ['label' => '완료', 'class' => 'bg-success'],
        'pending'   => ['label' => '대기중', 'class' => 'bg-warning text-dark'],
        'failed'    => ['label' => '실패', 'class' => 'bg-danger'],
    ];

    $rows = [];
    foreach ($payments as $p) {
        $badge = $statusBadges[$p['status']] ?? ['label' => $p['status'], 'class' => 'bg-secondary']; // 취소/만료 등 그 외 상태는 회색

        $period = '-';
        if ($p['status'] === 'completed' && ! empty($p['approved_at'])) {
            $start  = date('Y-m-d', strtotime($p['approved_at']));
            $end    = date('Y-m-d', strtotime($p['approved_at'] . ' +1 month -1 day'));
            $period = "{$start} ~ {$end}";
        }

        $rows[] = [
            'p'      => $p,
            'badge'  => $badge,
            'period' => $period,
        ];
    }
    ?>

    <!-- 데스크톱: 테이블 (모바일에서는 숨김) -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>주문일</th>
                    <th>요금제</th>
                    <th>금액</th>
                    <th>결제수단</th>
                    <th>이용기간</th>
                    <th>상태</th>
                    <th>영수증</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : $p = $row['p']; ?>
                    <tr>
                        <td>
                            <div><?= esc(date('Y-m-d', strtotime($p['created_at']))) ?></div>
                            <button type="button" class="btn btn-link btn-sm p-0 text-muted small text-decoration-none"
                                    data-bs-toggle="modal" data-bs-target="#paymentModal<?= (int) $p['id'] ?>">
                                <?= esc(substr($p['order_id'], 0, 8)) ?>...
                            </button>
                        </td>
                        <td><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></td>
                        <td><?= number_format((int) $p['amount']) ?>원</td>
                        <td><?= esc($p['method'] ?? '-') ?></td>
                        <td class="small"><?= esc($row['period']) ?></td>
                        <td><span class="badge <?= esc($row['badge']['class']) ?>"><?= esc($row['badge']['label']) ?></span></td>
                        <td>
                            <?php if ($p['status'] === 'completed' && ! empty($p['receipt_url'])) : ?>
                                <a href="<?= esc($p['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">영수증</a>
                            <?php else : ?>
                                <span class="text-muted small">-</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- 모바일: 카드 목록 (데스크톱에서는 숨김) -->
    <div class="d-md-none">
        <?php foreach ($rows as $row) : $p = $row['p']; ?>
            <div class="card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold"><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></div>
                        <div class="text-muted small"><?= esc(date('Y-m-d', strtotime($p['created_at']))) ?></div>
                    </div>
                    <span class="badge <?= esc($row['badge']['class']) ?>"><?= esc($row['badge']['label']) ?></span>
                </div>
                <div class="small text-muted mb-1">금액: <?= number_format((int) $p['amount']) ?>원</div>
                <div class="small text-muted mb-1">결제수단: <?= esc($p['method'] ?? '-') ?></div>
                <div class="small text-muted mb-2">이용기간: <?= esc($row['period']) ?></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#paymentModal<?= (int) $p['id'] ?>">상세보기</button>
                    <?php if ($p['status'] === 'completed' && ! empty($p['receipt_url'])) : ?>
                        <a href="<?= esc($p['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">영수증</a>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <?= $pager->links('default', 'bootstrap5') ?>

    <!-- 결제 상세 모달 (행마다 하나씩) -->
    <?php foreach ($rows as $row) : $p = $row['p']; ?>
        <div class="modal fade" id="paymentModal<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">결제 상세</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-5">주문번호</dt>
                            <dd class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="orderIdInput<?= (int) $p['id'] ?>" value="<?= esc($p['order_id']) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyPaymentOrderId(<?= (int) $p['id'] ?>, this)">복사</button>
                                </div>
                            </dd>
                            <dt class="col-5">주문일시</dt>
                            <dd class="col-7"><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></dd>
                            <dt class="col-5">요금제</dt>
                            <dd class="col-7"><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></dd>
                            <dt class="col-5">금액</dt>
                            <dd class="col-7"><?= number_format((int) $p['amount']) ?>원</dd>
                            <dt class="col-5">결제수단</dt>
                            <dd class="col-7"><?= esc($p['method'] ?? '-') ?></dd>
                            <dt class="col-5">이용기간</dt>
                            <dd class="col-7"><?= esc($row['period']) ?></dd>
                            <dt class="col-5">상태</dt>
                            <dd class="col-7"><span class="badge <?= esc($row['badge']['class']) ?>"><?= esc($row['badge']['label']) ?></span></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <script>
    function copyPaymentOrderId(id, button) {
        var input = document.getElementById('orderIdInput' + id);
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(function () {
            var original = button.textContent;
            button.textContent = '복사됨';
            setTimeout(function () { button.textContent = original; }, 1200);
        });
    }
    </script>

<?php endif ?>
<?= $this->endSection() ?>
