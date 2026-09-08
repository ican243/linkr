<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">회원 관리</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="admin-card p-3 mb-3">
    <form action="/admin/members" method="get" class="d-flex gap-2">
        <input type="text" name="q" value="<?= esc($q) ?>" placeholder="이메일 검색" class="form-control form-control-sm" style="max-width:280px;">
        <button type="submit" class="btn admin-btn admin-btn-secondary btn-sm">검색</button>
        <?php if ($q !== '') : ?>
            <a href="/admin/members" class="btn admin-btn admin-btn-secondary btn-sm">초기화</a>
        <?php endif ?>
    </form>
</div>

<?php $planPill = ['free' => 'pill-free', 'pro' => 'pill-pro', 'enterprise' => 'pill-enterprise']; ?>
<?php $planLabel = ['free' => '무료', 'pro' => '프로', 'enterprise' => '엔터프라이즈']; ?>

<div class="admin-card">
    <?php if (empty($users)) : ?>
        <div class="admin-empty">
            <i class="bi bi-people"></i>
            조건에 맞는 회원이 없습니다
        </div>
    <?php else : ?>
        <!-- 데스크톱 테이블 -->
        <div class="table-responsive d-none d-md-block">
            <table class="admin-table table mb-0">
                <thead>
                    <tr>
                        <th>이메일</th>
                        <th>이름</th>
                        <th>요금제</th>
                        <th>커스텀 도메인</th>
                        <th>가입일</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) : ?>
                        <tr>
                            <td><a href="/admin/users/<?= (int) $user['id'] ?>" class="cell-email text-decoration-none"><?= esc($user['email']) ?></a></td>
                            <td class="cell-name"><?= esc($user['name'] ?? '-') ?></td>
                            <td>
                                <span class="pill <?= esc($planPill[$user['plan']] ?? 'pill-free') ?>"><?= esc($planLabel[$user['plan']] ?? $user['plan']) ?></span>
                                <?php if (! empty($user['plan_expires_at'])) : ?>
                                    <span class="cell-sub">~<?= esc(date('Y-m-d', strtotime($user['plan_expires_at']))) ?></span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (empty($user['custom_domain'])) : ?>
                                    <span class="cell-sub">-</span>
                                <?php else : ?>
                                    <span class="small"><?= esc($user['custom_domain']) ?></span>
                                    <?php if ($user['custom_domain_verified']) : ?>
                                        <span class="pill pill-verified">인증됨</span>
                                    <?php else : ?>
                                        <span class="pill pill-unverified">미인증</span>
                                    <?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="cell-sub"><?= esc($user['created_at']) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="admin-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#planModal<?= (int) $user['id'] ?>">요금제 변경</button></li>
                                        <?php if (! empty($user['custom_domain']) && ! $user['custom_domain_verified']) : ?>
                                            <li>
                                                <form action="/admin/users/<?= (int) $user['id'] ?>/verify-domain" method="post"
                                                      data-confirm="DNS 확인 없이 이 도메인을 강제로 인증 처리하시겠습니까? (실제 소유권 확인 없이 처리되며, 시연/테스트 목적으로만 사용하세요)">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item">도메인 강제 인증</button>
                                                </form>
                                            </li>
                                        <?php endif ?>
                                        <li><a class="dropdown-item" href="/admin/users/<?= (int) $user['id'] ?>">상세 보기</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="/admin/users/<?= (int) $user['id'] ?>/delete" method="post"
                                                  data-confirm="이 회원을 삭제하시겠습니까? 이 회원의 모든 링크와 클릭 기록도 함께 삭제됩니다.">
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

        <!-- 모바일 카드 -->
        <div class="d-md-none p-3">
            <?php foreach ($users as $user) : ?>
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <a href="/admin/users/<?= (int) $user['id'] ?>" class="cell-email text-decoration-none"><?= esc($user['email']) ?></a>
                        <span class="pill <?= esc($planPill[$user['plan']] ?? 'pill-free') ?>"><?= esc($planLabel[$user['plan']] ?? $user['plan']) ?></span>
                    </div>
                    <div class="cell-name small mb-1"><?= esc($user['name'] ?? '-') ?></div>
                    <div class="cell-sub mb-2"><?= esc($user['created_at']) ?></div>
                    <button type="button" class="btn admin-btn admin-btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal<?= (int) $user['id'] ?>">요금제 변경</button>
                </div>
            <?php endforeach ?>
        </div>

        <div class="p-3 pt-0"><?= $pager->links('default', 'bootstrap5') ?></div>
    <?php endif ?>
</div>

<!-- 요금제 변경 모달 (회원마다 하나) -->
<?php foreach ($users as $user) : ?>
    <div class="modal fade" id="planModal<?= (int) $user['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="/admin/users/<?= (int) $user['id'] ?>/plan" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h2 class="modal-title h5"><?= esc($user['email']) ?> 요금제 변경</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small">요금제</label>
                            <select name="plan" class="form-select form-select-sm">
                                <?php foreach ($planLabel as $value => $label) : ?>
                                    <option value="<?= esc($value) ?>" <?= $user['plan'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">이용기간 (개월) — 무료로 변경 시에는 무시됨</label>
                            <input type="number" name="months" class="form-control form-control-sm" min="1" value="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">변경 사유 (필수)</label>
                            <textarea name="reason" class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn admin-btn admin-btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn admin-btn admin-btn-primary">변경 적용</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach ?>
<?= $this->endSection() ?>
