<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">결제 내역</h1>

<?php if (empty($payments)) : ?>
    <p class="text-muted">아직 결제 내역이 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>주문번호</th>
                    <th>요금제</th>
                    <th>금액</th>
                    <th>상태</th>
                    <th>결제일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p) : ?>
                    <tr>
                        <td class="small"><?= esc($p['order_id']) ?></td>
                        <td><?= esc($p['plan']) ?></td>
                        <td><?= number_format((int) $p['amount']) ?>원</td>
                        <td>
                            <?php if ($p['status'] === 'completed') : ?>
                                <span class="badge bg-success">완료</span>
                            <?php elseif ($p['status'] === 'failed') : ?>
                                <span class="badge bg-danger">실패</span>
                            <?php else : ?>
                                <span class="badge bg-secondary">대기중</span>
                            <?php endif ?>
                        </td>
                        <td><?= $p['approved_at'] ? esc(date('Y-m-d H:i', strtotime($p['approved_at']))) : esc(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= $pager->links('default', 'bootstrap5') ?>
<?php endif ?>
<?= $this->endSection() ?>
