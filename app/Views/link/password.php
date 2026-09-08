<?= $this->extend($layout ?? 'layout') ?>

<?= $this->section('content') ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">
                        <div class="fs-1 mb-3">🔒</div>

                        <h2 class="fw-bold mb-2">
                            링크가 보호되어 있습니다
                        </h2>

                        <p class="text-muted mb-0">
                            계속하려면 비밀번호를 입력해 주세요.
                        </p>
                    </div>

                    <?php if (! empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= esc($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="/<?= esc($code) ?>" method="post">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                비밀번호
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control form-control-lg"
                                placeholder="비밀번호를 입력해 주세요"
                                autocomplete="current-password"
                                required
                                autofocus
                            >
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary btn-lg w-100 fw-semibold"
                        >
                            링크 확인
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?= $this->endSection() ?>