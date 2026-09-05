<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">QR 코드</h1>
    <a href="/shorten" class="btn btn-primary">+ 새 링크 만들기</a>
</div>

<form action="/qr" method="get" class="mb-4" style="max-width:400px;">
    <div class="input-group">
        <input type="text" name="q" value="<?= esc($query) ?>" placeholder="제목, URL, 코드로 검색" class="form-control">
        <button type="submit" class="btn btn-outline-secondary">검색</button>
    </div>
</form>

<?php if (empty($links)) : ?>
    <p>
        <?= $query !== '' ? '검색 결과가 없습니다.' : '아직 만든 링크가 없습니다.' ?>
        <a href="/shorten">지금 만들어보세요</a>.
    </p>
<?php else : ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($links as $link) : ?>
            <div class="col">
                <div class="card h-100 p-3 text-center">
                    <img src="<?= esc(base_url('qr/' . $link['short_code'])) ?>" alt="QR 코드"
                         width="160" height="160" class="mx-auto mb-2">
                    <div class="fw-bold text-truncate">
                        <?= ! empty($link['title']) ? esc($link['title']) : esc($link['short_code']) ?>
                    </div>
                    <div class="small text-muted text-truncate mb-2">
                        <?= esc(base_url($link['short_code'])) ?>
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="<?= esc(base_url('qr/' . $link['short_code'])) ?>" download="king-qr-<?= esc($link['short_code']) ?>.png"
                           class="btn btn-sm btn-outline-primary">다운로드</a>
                        <a href="/links/<?= esc($link['short_code']) ?>/stats" class="btn btn-sm btn-outline-secondary">통계</a>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <?= $pager->links('default', 'bootstrap5') ?>
<?php endif ?>
<?= $this->endSection() ?>
