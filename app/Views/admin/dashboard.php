<?= $this->extend('admin_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">관리자 대시보드</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>

<div class="row mb-4 text-center">
    <div class="col">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $stats['totalUsers'] ?></div>
            <div class="text-muted">전체 회원 수</div>
        </div>
    </div>
    <div class="col">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $stats['totalLinks'] ?></div>
            <div class="text-muted">전체 링크 수</div>
        </div>
    </div>
    <div class="col">
        <div class="card p-3">
            <div class="fs-4 fw-bold"><?= (int) $stats['totalClicks'] ?></div>
            <div class="text-muted">전체 클릭 수</div>
        </div>
    </div>
</div>

<h2 class="h4">회원 목록</h2>
<?php if (empty($users)) : ?>
    <p>아직 가입한 회원이 없습니다.</p>
<?php else : ?>
    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>이메일</th>
                    <th>이름</th>
                    <th>요금제</th>
                    <th>가입일</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= esc($user['email']) ?></td>
                        <td><?= esc($user['name'] ?? '-') ?></td>
                        <td>
                            <form action="/admin/users/<?= (int) $user['id'] ?>/plan" method="post" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <select name="plan" class="form-select form-select-sm" style="width:auto;">
                                    <?php foreach (['free' => '무료', 'pro' => '프로', 'enterprise' => '엔터프라이즈'] as $value => $label) : ?>
                                        <option value="<?= esc($value) ?>" <?= $user['plan'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">변경</button>
                            </form>
                        </td>
                        <td><?= esc($user['created_at']) ?></td>
                        <td>
                            <form action="/admin/users/<?= (int) $user['id'] ?>/delete" method="post"
                                  onsubmit="return confirm('이 회원을 삭제하시겠습니까? 이 회원의 모든 링크와 클릭 기록도 함께 삭제됩니다.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<h2 class="h4">전체 링크 목록</h2>
<?php if (empty($links)) : ?>
    <p>아직 생성된 링크가 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>단축 주소</th>
                    <th>원본 주소</th>
                    <th>만든 회원</th>
                    <th>클릭 수</th>
                    <th>생성일</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link) : ?>
                    <tr>
                        <td><a href="<?= esc(base_url($link['short_code'])) ?>" target="_blank" rel="noopener noreferrer"><?= esc($link['short_code']) ?></a></td>
                        <td class="text-truncate" style="max-width:250px;"><?= esc($link['original_url']) ?></td>
                        <td><?= esc($link['owner_email']) ?></td>
                        <td><?= (int) $link['click_count'] ?></td>
                        <td><?= esc($link['created_at']) ?></td>
                        <td>
                            <form action="/admin/links/<?= (int) $link['id'] ?>/delete" method="post"
                                  onsubmit="return confirm('이 링크를 삭제하시겠습니까? 클릭 기록도 함께 삭제됩니다.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
