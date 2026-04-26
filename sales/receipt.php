<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
define('PAGE_TITLE', 'Receipt');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid sale.'); header('Location: '.base_url('sales/index.php')); exit; }

$saleStmt = db()->prepare(
    "SELECT s.*, u.name AS cashier FROM sales s JOIN users u ON u.id=s.user_id WHERE s.id=?"
);
$saleStmt->execute([$id]);
$sale = $saleStmt->fetch();
if (!$sale) { setFlash('error','Sale not found.'); header('Location: '.base_url('sales/index.php')); exit; }

$items = db()->prepare(
    "SELECT si.*, m.name AS medicine_name FROM sale_items si JOIN medicines m ON m.id=si.medicine_id WHERE si.sale_id=?"
);
$items->execute([$id]);
$lineItems = $items->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4 no-print">
  <a href="<?= base_url('sales/index.php') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-bold">Receipt #<?= str_pad($sale['id'],6,'0',STR_PAD_LEFT) ?></h5>
  <div class="ms-auto d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="bi bi-printer me-1"></i>Print</button>
    <a href="<?= base_url('sales/pos.php') ?>" class="btn btn-sm btn-success"><i class="bi bi-plus me-1"></i>New Sale</a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card table-card" id="receiptCard">
      <div class="card-body p-4">
        <!-- Header -->
        <div class="text-center mb-4">
          <div style="width:50px;height:50px;background:#2563eb;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:.75rem;">
            <i class="bi bi-capsule" style="font-size:1.4rem;color:#fff;"></i>
          </div>
          <h4 class="fw-bold mb-0"><?= APP_NAME ?></h4>
          <p class="text-muted mb-0" style="font-size:.8rem;">Pharmacy Receipt</p>
          <hr/>
          <div style="font-size:.8rem;">
            <div><strong>Receipt #:</strong> <?= str_pad($sale['id'],6,'0',STR_PAD_LEFT) ?></div>
            <div><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></div>
            <div><strong>Cashier:</strong> <?= sanitize($sale['cashier']) ?></div>
            <div><strong>Customer:</strong> <?= sanitize($sale['customer_name']) ?></div>
            <div><strong>Payment:</strong> <span class="text-capitalize"><?= sanitize($sale['payment_method']) ?></span></div>
          </div>
        </div>

        <!-- Items table -->
        <table class="table table-sm mb-0">
          <thead style="background:#f8fafc;">
            <tr>
              <th>Item</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Price</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($lineItems as $item): ?>
            <tr>
              <td style="font-size:.8rem;"><?= sanitize($item['medicine_name']) ?></td>
              <td class="text-center"><?= $item['quantity'] ?></td>
              <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
              <td class="text-end fw-600"><?= formatCurrency($item['total_price']) ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>

        <hr/>

        <!-- Totals -->
        <div class="d-flex justify-content-between mb-1">
          <span>Subtotal</span>
          <span><?= formatCurrency($sale['total_amount'] + $sale['discount']) ?></span>
        </div>
        <?php if ($sale['discount'] > 0): ?>
        <div class="d-flex justify-content-between mb-1 text-success">
          <span>Discount</span>
          <span>— <?= formatCurrency($sale['discount']) ?></span>
        </div>
        <?php endif ?>
        <div class="d-flex justify-content-between mb-1 fw-bold fs-5">
          <span>TOTAL</span>
          <span class="text-primary"><?= formatCurrency($sale['total_amount']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1 text-muted" style="font-size:.85rem;">
          <span>Paid</span>
          <span><?= formatCurrency($sale['paid_amount']) ?></span>
        </div>
        <?php if ($sale['change_amount'] > 0): ?>
        <div class="d-flex justify-content-between text-success">
          <span>Change</span>
          <span><?= formatCurrency($sale['change_amount']) ?></span>
        </div>
        <?php endif ?>

        <hr/>
        <div class="text-center text-muted" style="font-size:.75rem;">
          <i class="bi bi-heart-fill text-danger"></i> Thank you for your purchase!<br>
          Please keep this receipt for reference.
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
  #sidebar, #topbar, .no-print { display:none !important; }
  #main { margin:0 !important; }
  #content { padding:0 !important; }
  #receiptCard { box-shadow:none !important; }
  body { background:#fff !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
