  <?= $this->extend('user_layout') ?>
  
  <?= $this->section('content') ?>
  <div class="mx-auto" style="max-width:400px;">
      <h1 class="mb-4">결제하기</h1>
  
      <div class="card p-3 mb-4">
          <div class="d-flex justify-content-between">
              <span><?= esc($planLabel) ?> 요금제 (월간)</span>
              <strong><?= number_format($amount) ?>원</strong>
          </div>
      </div>
  
      <button id="pay-button" class="btn btn-primary w-100">결제하기</button>
  </div>
  
  <script src="https://js.tosspayments.com/v1/payment"></script>
  <script>
  const tossPayments = TossPayments('<?= esc($clientKey) ?>');
  
  document.getElementById('pay-button').addEventListener('click', function () {
      tossPayments.requestPayment('카드', {
          amount: <?= (int) $amount ?>,
          orderId: '<?= esc($orderId, 'js') ?>',
          orderName: '<?= esc($planLabel, 'js') ?> 요금제 (월간)',
          customerName: '<?= esc($customerName, 'js') ?>',
          successUrl: window.location.origin + '/payment/success',
          failUrl: window.location.origin + '/payment/fail',
      });
  });
  </script>
  <?= $this->endSection() ?>
