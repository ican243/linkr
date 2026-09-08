<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">결제 관리</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- 요약 카드 -->
<div class="row mb-4 text-center">
    <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= number_format($summary['monthlyRevenue']) ?>원</div>
            <div class="text-muted small">이번 달 매출</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $summary['monthlyCount'] ?>건</div>
            <div class="text-muted small">이번 달 결제 건수</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $summary['pendingCount'] ?>건</div>
            <div class="text-muted small">대기중 건수</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $summary['paidUserCount'] ?>명</div>
            <div class="text-muted small">총 유료 회원 수</div>
        </div>
    </div>
</div>

<!-- 필터 -->
<div class="card p-3 mb-4">
    <form action="/admin/payments" method="get" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">상태</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">전체</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>완료</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>대기중</option>
                <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>실패</option>
                <option value="other" <?= $filters['status'] === 'other' ? 'selected' : '' ?>>취소·만료</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">이메일 검색</label>
            <input type="text" name="email" class="form-control form-control-sm" placeholder="example@email.com" value="<?= esc($filters['email']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">시작일</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">종료일</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to']) ?>">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">필터 적용</button>
            <a href="/admin/payments" class="btn btn-outline-secondary btn-sm">초기화</a>
        </div>
    </form>
</div>

<?php
$statusBadges = [
    'completed' => ['label' => '완료', 'class' => 'bg-success'],
    'pending'   => ['label' => '대기중', 'class' => 'bg-warning text-dark'],
    'failed'    => ['label' => '실패', 'class' => 'bg-danger'],
];
$statusLabel = static fn (string $s) => ($statusBadges[$s]['label'] ?? $s);
?>

<?php if (empty($payments)) : ?>
    <p class="text-muted">조건에 맞는 결제 내역이 없습니다.</p>
<?php else : ?>

    <!-- 데스크톱 테이블 -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>주문일</th>
                    <th>회원 이메일</th>
                    <th>요금제</th>
                    <th>금액</th>
                    <th>결제수단</th>
                    <th>상태</th>
                    <th>영수증</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p) : $badge = $statusBadges[$p['status']] ?? ['label' => $p['status'], 'class' => 'bg-secondary']; ?>
                    <tr>
                        <td><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                        <td><?= esc($p['user_email']) ?></td>
                        <td><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></td>
                        <td><?= number_format((int) $p['amount']) ?>원</td>
                        <td><?= esc($p['method'] ?? '-') ?></td>
                        <td><span class="badge <?= esc($badge['class']) ?>"><?= esc($badge['label']) ?></span></td>
                        <td>
                            <?php if ($p['status'] === 'completed' && ! empty($p['receipt_url'])) : ?>
                                <a href="<?= esc($p['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">영수증</a>
                            <?php else : ?>
                                <span class="text-muted small">-</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int) $p['id'] ?>">상세</button>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- 모바일 카드 -->
    <div class="d-md-none">
        <?php foreach ($payments as $p) : $badge = $statusBadges[$p['status']] ?? ['label' => $p['status'], 'class' => 'bg-secondary']; ?>
            <div class="card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold"><?= esc($p['user_email']) ?></div>
                        <div class="text-muted small"><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></div>
                    </div>
                    <span class="badge <?= esc($badge['class']) ?>"><?= esc($badge['label']) ?></span>
                </div>
                <div class="small text-muted mb-1">요금제: <?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></div>
                <div class="small text-muted mb-1">금액: <?= number_format((int) $p['amount']) ?>원</div>
                <div class="small text-muted mb-2">결제수단: <?= esc($p['method'] ?? '-') ?></div>
                <div class="d-flex gap-2">
                    <?php if ($p['status'] === 'completed' && ! empty($p['receipt_url'])) : ?>
                        <a href="<?= esc($p['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">영수증</a>
                    <?php endif ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int) $p['id'] ?>">상세</button>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <?= $pager->links('default', 'bootstrap5') ?>

    <!-- 결제 상세 모달 (행마다 하나) -->
    <?php foreach ($payments as $p) : $badge = $statusBadges[$p['status']] ?? ['label' => $p['status'], 'class' => 'bg-secondary']; ?>
        <div class="modal fade" id="detailModal<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5">결제 상세 — <?= esc($p['order_id']) ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-3">
                            <dt class="col-4">회원</dt><dd class="col-8"><?= esc($p['user_email']) ?></dd>
                            <dt class="col-4">주문일시</dt><dd class="col-8"><?= esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></dd>
                            <dt class="col-4">요금제</dt><dd class="col-8"><?= esc($planLabels[$p['plan']] ?? $p['plan']) ?></dd>
                            <dt class="col-4">금액</dt><dd class="col-8"><?= number_format((int) $p['amount']) ?>원</dd>
                            <dt class="col-4">결제수단</dt><dd class="col-8"><?= esc($p['method'] ?? '-') ?></dd>
                            <dt class="col-4">상태</dt><dd class="col-8"><span class="badge <?= esc($badge['class']) ?>"><?= esc($badge['label']) ?></span></dd>
                            <?php if (! empty($p['refunded_at'])) : ?>
                                <dt class="col-4">환불일시</dt><dd class="col-8"><?= esc(date('Y-m-d H:i', strtotime($p['refunded_at']))) ?></dd>
                                <dt class="col-4">환불 사유</dt><dd class="col-8"><?= esc($p['cancel_reason']) ?></dd>
                            <?php endif ?>
                        </dl>

                        <!-- 관리자 액션: 테스트 완료 처리 / 환불 / 상태 변경 -->
                        <div class="d-flex gap-2 mb-3">
                            <?php if ($p['status'] === 'pending') : ?>
                                <form action="/admin/payments/<?= (int) $p['id'] ?>/test-complete" method="post" onsubmit="return confirm('토스 테스트 환경이라 실제 결제가 어려워, 이 결제를 결제된 것처럼 테스트 완료 처리합니다. 실제 결제가 아니며 회원 요금제가 즉시 업그레이드됩니다. 계속할까요?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">테스트 완료 처리</button>
                                </form>
                            <?php endif ?>
                            <?php if ($p['status'] === 'completed') : ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#refundModal<?= (int) $p['id'] ?>">환불</button>
                            <?php endif ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int) $p['id'] ?>">상태 수동 변경</button>
                        </div>

                        <!-- 관리자 메모 -->
                        <form action="/admin/payments/<?= (int) $p['id'] ?>/memo" method="post" class="mb-3">
                            <?= csrf_field() ?>
                            <label class="form-label small fw-semibold">관리자 메모</label>
                            <textarea name="admin_memo" class="form-control form-control-sm" rows="2"><?= esc($p['admin_memo'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-sm btn-outline-primary mt-2">메모 저장</button>
                        </form>

                        <!-- 처리 이력 -->
                        <?php if (! empty($logsById[$p['id']])) : ?>
                            <div class="small fw-semibold mb-1">처리 이력</div>
                            <ul class="list-group list-group-flush small mb-3">
                                <?php foreach ($logsById[$p['id']] as $log) : ?>
                                    <li class="list-group-item px-0">
                                        <span class="text-muted"><?= esc(date('Y-m-d H:i', strtotime($log['created_at']))) ?></span>
                                        · <?= esc($log['admin_email']) ?> · <?= esc($log['detail']) ?>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>

                        <!-- 토스 응답 원본 -->
                        <?php if (! empty($p['toss_response'])) : ?>
                            <div class="small fw-semibold mb-1">토스 API 응답 원본</div>
                            <pre class="bg-light p-2 small" style="max-height:200px; overflow:auto;"><?= esc(json_encode(json_decode($p['toss_response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 환불 확인 모달 -->
        <?php if ($p['status'] === 'completed') : ?>
            <div class="modal fade" id="refundModal<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="/admin/payments/<?= (int) $p['id'] ?>/refund" method="post" onsubmit="return confirm('정말 환불 처리하시겠습니까? 토스에 실제 환불 요청이 전송되고, 해당 회원은 무료 요금제로 강등됩니다.');">
                            <?= csrf_field() ?>
                            <div class="modal-header">
                                <h2 class="modal-title h5">환불 처리</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-danger small">토스에 실제 결제취소 요청이 전송됩니다. 처리 후에는 되돌릴 수 없습니다.</p>
                                <label class="form-label small">환불 사유 (필수)</label>
                                <textarea name="cancel_reason" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                                <button type="submit" class="btn btn-danger btn-sm">환불 실행</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <!-- 상태 수동 변경 모달 -->
        <div class="modal fade" id="statusModal<?= (int) $p['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="/admin/payments/<?= (int) $p['id'] ?>/status" method="post">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h2 class="modal-title h5">상태 수동 변경</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">회원 요금제에는 영향을 주지 않고, 이 결제 건의 상태값만 바꿉니다.</p>
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach (['completed' => '완료', 'pending' => '대기중', 'failed' => '실패'] as $value => $label) : ?>
                                    <option value="<?= esc($value) ?>" <?= $p['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                            <button type="submit" class="btn btn-primary btn-sm">변경</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach ?>

<?php endif ?>
<?= $this->endSection() ?>
