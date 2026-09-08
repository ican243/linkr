<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">링크 관리</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>

<div class="admin-card p-3 mb-3">
    <form action="/admin/links" method="get" class="d-flex gap-2">
        <input type="text" name="q" value="<?= esc($q) ?>" placeholder="코드, URL, 회원 이메일 검색" class="form-control form-control-sm" style="max-width:320px;">
        <button type="submit" class="btn admin-btn admin-btn-secondary btn-sm">검색</button>
        <?php if ($q !== '') : ?>
            <a href="/admin/links" class="btn admin-btn admin-btn-secondary btn-sm">초기화</a>
        <?php endif ?>
    </form>
</div>

<div class="admin-card">
    <?php if (empty($links)) : ?>
        <div class="admin-empty">
            <i class="bi bi-link-45deg"></i>
            조건에 맞는 링크가 없습니다
        </div>
    <?php else : ?>
        <div class="table-responsive d-none d-md-block">
            <table class="admin-table table mb-0">
                <thead>
                    <tr>
                        <th>단축 주소</th>
                        <th>원본 주소</th>
                        <th>만든 회원</th>
                        <th>만료일</th>
                        <th>클릭 수</th>
                        <th>생성일</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link) : ?>
                        <?php
                            $isExpired    = ! empty($link['expires_at']) && strtotime($link['expires_at']) < time();
                            $isClickLimit = ! empty($link['max_clicks']) && (int) $link['click_count'] >= (int) $link['max_clicks'];
                            $expiringSoon = ! $isExpired && ! empty($link['expires_at']) && strtotime($link['expires_at']) < strtotime('+3 days');
                        ?>
                        <tr>
                            <td><a href="<?= esc(base_url($link['short_code'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc($link['short_code']) ?></a></td>
                            <td class="text-truncate" style="max-width:220px;"><?= esc($link['original_url']) ?></td>
                            <td class="cell-email"><?= esc($link['owner_email']) ?></td>
                            <td>
                                <?php if (empty($link['expires_at'])) : ?>
                                    <span class="pill pill-unlimited">무제한</span>
                                <?php elseif ($isExpired || $isClickLimit) : ?>
                                    <span class="pill pill-expired">만료됨</span>
                                <?php elseif ($expiringSoon) : ?>
                                    <span class="pill pill-soon"><?= esc(date('Y-m-d', strtotime($link['expires_at']))) ?></span>
                                <?php else : ?>
                                    <span class="cell-sub"><?= esc(date('Y-m-d', strtotime($link['expires_at']))) ?></span>
                                <?php endif ?>
                            </td>
                            <td><?= (int) $link['click_count'] ?></td>
                            <td class="cell-sub"><?= esc($link['created_at']) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="admin-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <form action="/admin/links/<?= (int) $link['id'] ?>/delete" method="post"
                                                  data-confirm="이 링크를 삭제하시겠습니까? 클릭 기록도 함께 삭제됩니다.">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="dropdown-item admin-btn-danger">삭제</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <div class="d-md-none p-3">
            <?php foreach ($links as $link) : ?>
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <a href="<?= esc(base_url($link['short_code'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc($link['short_code']) ?></a>
                        <span class="cell-sub"><?= (int) $link['click_count'] ?>회</span>
                    </div>
                    <div class="text-truncate small mb-1"><?= esc($link['original_url']) ?></div>
                    <div class="cell-email small"><?= esc($link['owner_email']) ?></div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="p-3 pt-0"><?= $pager->links('default', 'bootstrap5') ?></div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
