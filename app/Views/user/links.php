<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">링크 관리</h1>
    <a href="/shorten" class="btn btn-primary">+ 새 링크 만들기</a>
</div>

<!-- 검색/필터/정렬 (GET 방식이라 CSRF 토큰 불필요 — 페이지 이동일 뿐 데이터를 바꾸는 요청이 아님) -->
<form action="/links" method="get" class="row g-2 mb-4">
    <div class="col-md-5">
        <input type="text" name="q" value="<?= esc($query) ?>" placeholder="제목, URL, 코드로 검색"
               class="form-control">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>전체</option>
            <option value="password" <?= $status === 'password' ? 'selected' : '' ?>>비밀번호 보호됨</option>
            <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>만료됨</option>
        </select>
    </div>
    <div class="col-md-3">
        <select name="sort" class="form-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>최신순</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>오래된순</option>
            <option value="clicks" <?= $sort === 'clicks' ? 'selected' : '' ?>>클릭 많은순</option>
        </select>
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-outline-secondary w-100">검색</button>
    </div>
</form>

<?php if (empty($links)) : ?>
    <p>조건에 맞는 링크가 없습니다.</p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>QR</th>
                    <th>제목</th>
                    <th>단축 주소</th>
                    <th>원본 주소</th>
                    <th>상태</th>
                    <th>클릭 수</th>
                    <th>생성일</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link) : ?>
                    <?php $isExpired = ! empty($link['expires_at']) && strtotime($link['expires_at']) < time(); ?>
                    <tr>
                        <td><img src="<?= esc(base_url('qr/' . $link['short_code'])) ?>" alt="QR" width="60" height="60"></td>
                        <td><?= ! empty($link['title']) ? esc($link['title']) : '<span class="text-muted">—</span>' ?></td>
                        <td><a href="<?= esc(short_url($link)) ?>" target="_blank" rel="noopener noreferrer"><?= esc(short_url($link)) ?></a></td>
                        <td class="text-truncate" style="max-width:220px;"><?= esc($link['original_url']) ?></td>
                        <td>
                            <?php if ($isExpired) : ?>
                                <span class="badge bg-secondary">만료됨</span>
                            <?php endif ?>
                            <?php if (! empty($link['password'])) : ?>
                                <span class="badge bg-warning text-dark">비밀번호</span>
                            <?php endif ?>
                        </td>
                        <td><?= (int) $link['click_count'] ?></td>
                        <td><?= esc($link['created_at']) ?></td>
                        <td class="text-nowrap">
                            <a href="/links/<?= esc($link['short_code']) ?>/stats" class="btn btn-sm btn-outline-primary">통계</a>
                            <a href="/links/<?= esc($link['short_code']) ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= $pager->links('default', 'bootstrap5') ?>
<?php endif ?>
<?= $this->endSection() ?>
