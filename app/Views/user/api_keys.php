<?= $this->extend('user_layout') ?>

<?= $this->section('content') ?>
<h1 class="mb-4">API 키</h1>

<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<?php if (session()->getFlashdata('newKey')) : ?>
    <div class="alert alert-warning">
        <strong>새 API 키가 발급되었습니다. 이 키는 지금만 볼 수 있으니 꼭 복사해두세요.</strong>
        <pre class="bg-white border rounded p-2 mt-2 mb-0 user-select-all"><?= esc(session()->getFlashdata('newKey')) ?></pre>
    </div>
<?php endif ?>

<?php if (empty($canUseApi)) : ?>
    <div class="alert alert-warning">
        API 키 발급/사용은 <strong>엔터프라이즈 요금제</strong> 전용 기능입니다.
        <a href="/pricing" class="alert-link">요금제 업그레이드하러 가기</a>
    </div>
<?php else : ?>
    <form action="/api-keys" method="post" class="row g-2 mb-4">
        <?= csrf_field() ?>
        <div class="col-auto">
            <input type="text" name="label" class="form-control" placeholder="키 별명 (예: 내 스크립트)" required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">새 키 발급</button>
        </div>
    </form>
<?php endif ?>

<?php if (empty($keys)) : ?>
    <p class="text-muted">아직 발급한 API 키가 없습니다.</p>
<?php else : ?>
    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>별명</th>
                    <th>키</th>
                    <th>발급일</th>
                    <th>마지막 사용</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $key) : ?>
                    <tr>
                        <td><?= esc($key['label']) ?></td>
                        <td><code>king_****<?= esc($key['key_last4']) ?></code></td>
                        <td><?= esc(date('Y-m-d H:i', strtotime($key['created_at']))) ?></td>
                        <td>
                            <?php if ($key['last_used_at']) : ?>
                                <?= esc(date('Y-m-d H:i', strtotime($key['last_used_at']))) ?>
                            <?php else : ?>
                                <span class="text-muted">사용 안 함</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <form action="/api-keys/<?= (int) $key['id'] ?>/delete" method="post"
                                  onsubmit="return confirm('이 API 키를 삭제하시겠습니까? 이 키를 쓰는 프로그램은 더 이상 동작하지 않습니다.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= $pager->links('default', 'bootstrap5') ?>
<?php endif ?>

<?php if (! empty($canUseApi)) : ?>
    <div class="card p-3 mb-4">
        <h2 class="h6">API로 링크 만들기</h2>
        <p class="mb-1">발급받은 키로 아래처럼 호출하면 로그인 없이 링크를 만들 수 있습니다.</p>
        <pre class="bg-light p-2 mb-0 small">curl -X POST <?= esc(base_url('api/v1/shorten')) ?> \
  -H "Authorization: Bearer 발급받은키" \
  -d "original_url=https://example.com" \
  -d "title=선택입력" \
  -d "custom_alias=선택입력"</pre>
    </div>

    <div class="card p-3 mb-4">
        <h2 class="h6">API로 내 링크 목록 조회</h2>
        <pre class="bg-light p-2 mb-0 small">curl <?= esc(base_url('api/v1/links')) ?> \
  -H "Authorization: Bearer 발급받은키"</pre>
    </div>

    <div class="card p-3">
        <h2 class="h6">API로 링크 삭제</h2>
        <pre class="bg-light p-2 mb-0 small">curl -X DELETE <?= esc(base_url('api/v1/links/')) ?>코드 \
  -H "Authorization: Bearer 발급받은키"</pre>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
