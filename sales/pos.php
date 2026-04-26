<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// ══════════════════════════════════════════════════════════
//  PROCESS SALE  (POST handler)
// ══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_sale'])) {
    verifyCsrf();
    $pdo = db();

    $cartJson   = $_POST['cart_json']   ?? '[]';
    $customer   = trim($_POST['customer'] ?? 'Walk-in Customer') ?: 'Walk-in Customer';
    $discount   = max(0, (float)($_POST['discount']  ?? 0));
    $paidAmount = (float)($_POST['paid'] ?? 0);
    $payMethod  = in_array($_POST['payment_method'] ?? '', ['cash','card','mobile'])
                    ? $_POST['payment_method'] : 'cash';

    $cartItems = json_decode($cartJson, true);
    if (!is_array($cartItems) || count($cartItems) === 0) {
        setFlash('error', 'Cart is empty. Add at least one medicine before processing.');
        header('Location: ' . base_url('sales/pos.php')); exit;
    }

    // Validate every line against live DB stock
    $lineItems = [];
    $subtotal  = 0.00;
    foreach ($cartItems as $item) {
        $medId = (int)($item['id']  ?? 0);
        $qty   = (int)($item['qty'] ?? 0);
        if ($medId <= 0 || $qty <= 0) continue;

        $st = $pdo->prepare("SELECT * FROM medicines WHERE id = ? LIMIT 1");
        $st->execute([$medId]);
        $med = $st->fetch();

        if (!$med) {
            setFlash('error', "Medicine #$medId no longer exists.");
            header('Location: ' . base_url('sales/pos.php')); exit;
        }
        if ($med['quantity'] < $qty) {
            setFlash('error', "Not enough stock for \"{$med['name']}\". Available: {$med['quantity']}, requested: $qty.");
            header('Location: ' . base_url('sales/pos.php')); exit;
        }
        $lt         = round((float)$med['selling_price'] * $qty, 2);
        $subtotal  += $lt;
        $lineItems[] = ['med' => $med, 'qty' => $qty, 'unit_price' => (float)$med['selling_price'], 'total' => $lt];
    }

    if (empty($lineItems)) {
        setFlash('error', 'Cart contained no valid items.');
        header('Location: ' . base_url('sales/pos.php')); exit;
    }

    $discount     = min($discount, $subtotal);
    $totalAmount  = round($subtotal - $discount, 2);
    $changeAmount = max(0, round($paidAmount - $totalAmount, 2));

    if ($payMethod === 'cash' && $paidAmount < $totalAmount) {
        setFlash('error', 'Cash paid (' . formatCurrency($paidAmount) . ') is less than total (' . formatCurrency($totalAmount) . ').');
        header('Location: ' . base_url('sales/pos.php')); exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO sales (user_id,customer_name,total_amount,discount,paid_amount,change_amount,payment_method)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([currentUserId(), $customer, $totalAmount, $discount, $paidAmount, $changeAmount, $payMethod]);

        $saleId = (int)$pdo->lastInsertId();

        foreach ($lineItems as $li) {
            $pdo->prepare(
                "INSERT INTO sale_items (sale_id,medicine_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?)"
            )->execute([$saleId, $li['med']['id'], $li['qty'], $li['unit_price'], $li['total']]);

            $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")
                ->execute([$li['qty'], $li['med']['id']]);
        }

        $pdo->commit();
        auditLog('CREATE_SALE', 'sales', $saleId, null, ['items' => count($lineItems), 'total' => $totalAmount]);
        header('Location: ' . base_url('sales/receipt.php') . '?id=' . $saleId);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Sale failed: ' . $e->getMessage());
        header('Location: ' . base_url('sales/pos.php')); exit;
    }
}

// ══════════════════════════════════════════════════════════
//  LOAD MEDICINES
// ══════════════════════════════════════════════════════════
$medicines = db()->query(
    "SELECT m.id, m.name, m.selling_price, m.quantity,
            m.barcode, m.low_stock_threshold, c.name AS category
     FROM medicines m
     LEFT JOIN categories c ON c.id = m.category_id
     WHERE m.quantity > 0 AND m.expiry_date >= CURDATE()
     ORDER BY m.name ASC"
)->fetchAll();

$categories = db()->query(
    "SELECT DISTINCT c.id, c.name FROM categories c
     INNER JOIN medicines m ON m.category_id = c.id
     WHERE m.quantity > 0 AND m.expiry_date >= CURDATE()
     ORDER BY c.name ASC"
)->fetchAll();

$medsJson = json_encode(
    array_map(fn($m) => [
        'id'       => (int)$m['id'],
        'name'     => $m['name'],
        'price'    => (float)$m['selling_price'],
        'stock'    => (int)$m['quantity'],
        'barcode'  => $m['barcode'] ?? '',
        'category' => $m['category'] ?? '',
    ], $medicines),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

define('PAGE_TITLE', 'New Sale — POS');
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Two-column POS layout ───────────────────────────── */
.pos-wrap   { display:flex; gap:1rem; align-items:flex-start; }
.pos-left   { flex:1 1 0; min-width:0; }
.pos-right  { width:400px; flex-shrink:0; position:sticky; top:68px; }

/* ── Medicine grid ───────────────────────────────────── */
.med-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px,1fr));
    gap: .6rem;
    max-height: calc(100vh - 250px);
    overflow-y: auto;
    padding: 2px 4px 4px 2px;
}
.med-tile {
    position: relative;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: .75rem .7rem;
    cursor: pointer;
    transition: border-color .15s, background .15s, transform .12s, box-shadow .12s;
    display: flex; flex-direction: column; gap: .28rem;
    user-select: none;
}
.med-tile:hover {
    border-color: #2563eb; background: #eff6ff;
    transform: translateY(-2px); box-shadow: 0 4px 14px rgba(37,99,235,.14);
}
.med-tile.in-cart { border-color: #16a34a; background: #f0fdf4; }
.med-tile.low-stock { border-left: 3px solid #f97316; }

.t-cat   { font-size:.63rem; background:#f1f5f9; color:#475569;
           border-radius:4px; padding:1px 5px; width:fit-content; }
.t-name  { font-weight:700; font-size:.82rem; line-height:1.3; color:#0f172a; }
.t-price { font-weight:800; font-size:1rem; color:#2563eb; }
.t-stock { font-size:.7rem; color:#64748b; }

/* Cart-quantity pill on each tile */
.tile-pill {
    display: none; position: absolute; top:5px; right:5px;
    background: #16a34a; color:#fff; font-size:.65rem; font-weight:800;
    border-radius: 12px; padding: 1px 7px; min-width:22px; text-align:center;
}
.med-tile.in-cart .tile-pill { display: block; }

/* ── Cart panel ──────────────────────────────────────── */
.cart-panel {
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 18px rgba(0,0,0,.1);
    display: flex; flex-direction: column;
    max-height: calc(100vh - 72px); overflow: hidden;
}
.c-head { padding: .8rem 1rem; border-bottom:1px solid #f1f5f9;
          display:flex; align-items:center; justify-content:space-between; }
.c-customer { padding: .5rem .9rem; border-bottom:1px solid #f1f5f9; }

/* Separate scrollable rows area from static empty message */
.c-rows { flex:1; overflow-y:auto; padding: .35rem .85rem; min-height: 60px; }
.c-empty {
    text-align:center; padding:2rem 1rem; color:#94a3b8;
    pointer-events:none;
}
.c-empty i { font-size:2.2rem; display:block; margin-bottom:.5rem; }

.c-foot { padding:.8rem 1rem; border-top:1px solid #f1f5f9; }

/* ── Cart rows ───────────────────────────────────────── */
.c-row {
    display:flex; align-items:center; gap:.45rem;
    padding:.45rem 0; border-bottom:1px solid #f5f7fa;
}
.c-row:last-child { border-bottom:none; }

.c-row-name { flex:1; min-width:0; }
.c-row-name strong {
    display:block; font-size:.8rem; font-weight:700; color:#0f172a;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.c-row-name span { font-size:.69rem; color:#94a3b8; }

/* +/- controls */
.qty-wrap { display:flex; align-items:center; gap:3px; }
.qty-dec, .qty-inc {
    width:26px; height:26px; border-radius:7px;
    border:2px solid #e2e8f0; background:#f8fafc;
    font-size:1rem; font-weight:800; line-height:1;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .12s; flex-shrink:0;
    /* Make sure they're buttons, not form-submit */
    type: button;
}
.qty-dec:hover { background:#fee2e2; border-color:#ef4444; color:#ef4444; }
.qty-inc:hover { background:#dcfce7; border-color:#16a34a; color:#16a34a; }
.qty-val {
    width:34px; height:26px; border:2px solid #e2e8f0; border-radius:6px;
    text-align:center; font-size:.82rem; font-weight:700;
    padding:0; background:#fff;
}
.qty-val:focus { border-color:#2563eb; outline:none; box-shadow:0 0 0 2px rgba(37,99,235,.15); }

.c-row-total { min-width:54px; text-align:right; font-weight:700; font-size:.84rem; color:#0f172a; }
.c-row-del   { background:none; border:none; color:#cbd5e1; cursor:pointer;
               border-radius:5px; padding:3px 4px; font-size:.82rem; line-height:1; }
.c-row-del:hover { color:#ef4444; background:#fef2f2; }

/* ── Totals section ──────────────────────────────────── */
.tot-line { display:flex; justify-content:space-between; align-items:center;
            padding:.22rem 0; font-size:.875rem; }
.tot-grand { font-size:1.15rem; font-weight:800; color:#0f172a;
             border-top:2px solid #e2e8f0; padding-top:.45rem; margin-top:.3rem; }

/* ── Payment tabs ────────────────────────────────────── */
.pay-tabs { display:flex; gap:.35rem; margin:.55rem 0 .4rem; }
.pay-tab  {
    flex:1; padding:.4rem .1rem; border:2px solid #e2e8f0; border-radius:9px;
    background:#f8fafc; font-size:.78rem; font-weight:700; color:#475569;
    cursor:pointer; transition:all .15s; text-align:center;
}
.pay-tab.on { border-color:#2563eb; background:#eff6ff; color:#2563eb; }

/* ── Quick-cash shortcuts ────────────────────────────── */
.cash-shortcuts { display:flex; gap:.3rem; flex-wrap:wrap; margin-bottom:.6rem; }
.cs-btn {
    flex:1 1 calc(16% - .3rem); min-width:38px;
    padding:.3rem 0; font-size:.76rem; font-weight:700;
    border:1.5px solid #e2e8f0; border-radius:7px; background:#f8fafc;
    cursor:pointer; transition:all .12s;
}
.cs-btn:hover { background:#eff6ff; border-color:#2563eb; color:#2563eb; }

/* ── Scrollbars ──────────────────────────────────────── */
.med-grid::-webkit-scrollbar,
.c-rows::-webkit-scrollbar { width:4px; }
.med-grid::-webkit-scrollbar-thumb,
.c-rows::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:4px; }

/* ── Toast ───────────────────────────────────────────── */
.pos-toast {
    position:fixed; bottom:1.4rem; right:1.4rem; z-index:9999;
    color:#fff; padding:.55rem 1.1rem; border-radius:10px; max-width:280px;
    font-size:.84rem; font-weight:700;
    box-shadow:0 4px 16px rgba(0,0,0,.22);
    animation:toastIn .18s ease; pointer-events:none;
}
@keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* ── Responsive ──────────────────────────────────────── */
@media(max-width:880px){
    .pos-wrap   { flex-direction:column; }
    .pos-right  { width:100%; position:static; }
    .med-grid   { max-height:390px; }
}
</style>

<!-- ─── Page heading ─────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus text-primary me-2"></i>Point of Sale</h5>
    <small class="text-muted">Add unlimited medicines · use +/− to adjust quantities</small>
  </div>
  <a href="<?= base_url('sales/index.php') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-clock-history me-1"></i>Sales History
  </a>
</div>

<div class="pos-wrap">

  <!-- ══════════════════ LEFT — Medicine picker ══════════ -->
  <div class="pos-left">

    <!-- Search + filter bar -->
    <div class="card table-card mb-3">
      <div class="card-body p-3">
        <div class="row g-2">
          <div class="col-sm-6">
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
              <input type="text" id="searchInput" class="form-control border-start-0"
                     placeholder="Search by name or scan barcode…" autocomplete="off"/>
            </div>
          </div>
          <div class="col-sm-4">
            <select id="catFilter" class="form-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>">
                  <?= htmlspecialchars($c['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="col-sm-2">
            <button class="btn btn-outline-secondary w-100" onclick="clearSearch()">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tile counter -->
    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
      <small class="text-muted">
        <span id="visibleCount"><?= count($medicines) ?></span> medicines available
      </small>
      <small class="text-muted d-none d-md-inline">
        <i class="bi bi-info-circle me-1"></i>Click tile to add · click again for +1
      </small>
    </div>

    <!-- Medicine tiles -->
    <div class="med-grid" id="medGrid">
      <?php foreach ($medicines as $m): ?>
        <div class="med-tile <?= $m['quantity'] <= $m['low_stock_threshold'] ? 'low-stock' : '' ?>"
             id="tile-<?= $m['id'] ?>"
             data-id="<?= $m['id'] ?>"
             data-name="<?= htmlspecialchars(mb_strtolower($m['name']), ENT_QUOTES) ?>"
             data-barcode="<?= htmlspecialchars(mb_strtolower($m['barcode'] ?? ''), ENT_QUOTES) ?>"
             data-cat="<?= htmlspecialchars($m['category'] ?? '', ENT_QUOTES) ?>"
             onclick="tileClick(<?= (int)$m['id'] ?>)">
          <span class="tile-pill" id="pill-<?= $m['id'] ?>">0</span>
          <div class="t-cat"><?= htmlspecialchars($m['category'] ?? 'General') ?></div>
          <div class="t-name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="t-price"><?= formatCurrency($m['selling_price']) ?></div>
          <div class="t-stock">
            Stock: <strong><?= (int)$m['quantity'] ?></strong>
            <?php if ($m['quantity'] <= $m['low_stock_threshold'] && $m['quantity'] > 0): ?>
              <span style="color:#f97316;" title="Low stock"> ⚠</span>
            <?php endif ?>
          </div>
        </div>
      <?php endforeach ?>
    </div>

  </div><!-- /.pos-left -->


  <!-- ══════════════════ RIGHT — Cart ══════════════════════ -->
  <div class="pos-right">
    <div class="cart-panel">

      <!-- Cart header -->
      <div class="c-head">
        <div class="d-flex align-items-center gap-2">
          <span style="font-size:1rem;">🛒</span>
          <strong>Cart</strong>
          <span class="badge bg-primary ms-1" id="cartBadge" style="font-size:.72rem;">Empty</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                id="clearBtn" style="display:none; font-size:.75rem;"
                onclick="clearCart()">
          <i class="bi bi-trash2 me-1"></i>Clear all
        </button>
      </div>

      <!-- Customer name -->
      <div class="c-customer">
        <input type="text" id="customerName" class="form-control form-control-sm"
               placeholder="👤 Customer name (optional)" value="Walk-in Customer"/>
      </div>

      <!--
        IMPORTANT: #cartEmpty and #cartRows are SIBLINGS inside #cartWrap.
        We never destroy cartEmpty via innerHTML — we just show/hide it.
        Cart rows live in #cartRows which gets innerHTML replaced safely.
      -->
      <div class="c-rows" id="cartWrap">
        <div id="cartEmpty" class="c-empty">
          <i class="bi bi-cart3"></i>
          Cart is empty<br>
          <small>Tap any medicine to add it.<br>No limit on items.</small>
        </div>
        <div id="cartRows"></div>
      </div>

      <!-- Totals + payment + checkout -->
      <div class="c-foot">

        <!-- Totals -->
        <div class="tot-line">
          <span class="text-muted">Subtotal</span>
          <span id="el-subtotal" class="fw-600">$0.00</span>
        </div>
        <div class="tot-line">
          <span class="text-muted">Discount ($)</span>
          <input type="number" id="discountInput"
                 class="form-control form-control-sm text-end fw-600"
                 style="width:82px;" min="0" step="0.01" value="0"
                 oninput="recalc()"/>
        </div>
        <div class="tot-line tot-grand">
          <span>TOTAL</span>
          <span id="el-total" class="text-primary">$0.00</span>
        </div>

        <!-- Payment method tabs -->
        <div class="pay-tabs">
          <button type="button" class="pay-tab on" data-m="cash"   onclick="setPay(this)"><i class="bi bi-cash me-1"></i>Cash</button>
          <button type="button" class="pay-tab"    data-m="card"   onclick="setPay(this)"><i class="bi bi-credit-card me-1"></i>Card</button>
          <button type="button" class="pay-tab"    data-m="mobile" onclick="setPay(this)"><i class="bi bi-phone me-1"></i>Mobile</button>
        </div>

        <!-- Cash-only fields -->
        <div id="cashSection">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label mb-1"
                     style="font-size:.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">
                Paid ($)
              </label>
              <input type="number" id="paidInput"
                     class="form-control form-control-sm fw-bold"
                     min="0" step="0.01" placeholder="0.00" oninput="recalc()"/>
            </div>
            <div class="col-6">
              <label class="form-label mb-1"
                     style="font-size:.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">
                Change
              </label>
              <input type="text" id="el-change"
                     class="form-control form-control-sm fw-bold text-success" readonly/>
            </div>
          </div>
          <!-- Quick cash shortcuts -->
          <div class="cash-shortcuts">
            <?php foreach ([5,10,20,50,100,200] as $v): ?>
              <button type="button" class="cs-btn" onclick="quickPay(<?= $v ?>)">$<?= $v ?></button>
            <?php endforeach ?>
            <button type="button" class="cs-btn"
                    style="border-color:#2563eb;color:#2563eb;background:#eff6ff;"
                    onclick="quickPay(null)">Exact</button>
          </div>
        </div>

        <!-- Hidden form for server submission -->
        <form method="POST" id="saleForm" onsubmit="return doSubmit()">
          <input type="hidden" name="csrf_token"     value="<?= csrfToken() ?>"/>
          <input type="hidden" name="process_sale"   value="1"/>
          <input type="hidden" name="cart_json"      id="f-cart"/>
          <input type="hidden" name="customer"       id="f-customer"/>
          <input type="hidden" name="discount"       id="f-discount"/>
          <input type="hidden" name="paid"           id="f-paid"/>
          <input type="hidden" name="payment_method" id="f-paymethod" value="cash"/>

          <button type="submit" id="checkoutBtn"
                  class="btn btn-success w-100 fw-bold py-2 mt-1" disabled>
            <i class="bi bi-check-circle-fill me-2"></i>
            <span id="checkoutLabel">Add items to checkout</span>
          </button>
        </form>

        <div id="cartSummary" class="text-center text-muted mt-2"
             style="font-size:.72rem; display:none;"></div>

      </div><!-- /.c-foot -->
    </div><!-- /.cart-panel -->
  </div><!-- /.pos-right -->

</div><!-- /.pos-wrap -->


<script>
/* ══════════════════════════════════════════════════════════
   CATALOGUE — injected from PHP
══════════════════════════════════════════════════════════ */
const CAT = <?= $medsJson ?>;      // array of medicine objects
const MED = {};                    // lookup by id
CAT.forEach(m => MED[m.id] = m);

/* ══════════════════════════════════════════════════════════
   CART STATE
   Plain object: { [medicineId (string)]: qty (number) }
   We keep it dead-simple to avoid any reference bugs.
══════════════════════════════════════════════════════════ */
const CART = {};   // e.g. { "3": 2, "7": 1, "12": 4 }
let payMode = 'cash';

/* ──────────────────────────────────────────────────────────
   DOM REFERENCES  (grabbed once, never destroyed)
────────────────────────────────────────────────────────── */
const DOM = {
    cartEmpty    : document.getElementById('cartEmpty'),
    cartRows     : document.getElementById('cartRows'),
    cartBadge    : document.getElementById('cartBadge'),
    clearBtn     : document.getElementById('clearBtn'),
    subtotal     : document.getElementById('el-subtotal'),
    total        : document.getElementById('el-total'),
    change       : document.getElementById('el-change'),
    discount     : document.getElementById('discountInput'),
    paid         : document.getElementById('paidInput'),
    checkoutBtn  : document.getElementById('checkoutBtn'),
    checkoutLabel: document.getElementById('checkoutLabel'),
    cartSummary  : document.getElementById('cartSummary'),
    cashSection  : document.getElementById('cashSection'),
    fCart        : document.getElementById('f-cart'),
    fCustomer    : document.getElementById('f-customer'),
    fDiscount    : document.getElementById('f-discount'),
    fPaid        : document.getElementById('f-paid'),
    fPaymethod   : document.getElementById('f-paymethod'),
    customer     : document.getElementById('customerName'),
    visCount     : document.getElementById('visibleCount'),
};

/* ══════════════════════════════════════════════════════════
   CART OPERATIONS
══════════════════════════════════════════════════════════ */

/** Called when a medicine tile is clicked */
function tileClick(id) {
    const med = MED[id];
    if (!med) return;
    const key = String(id);

    if (CART[key] !== undefined) {
        if (CART[key] >= med.stock) {
            notify('Max stock reached for "' + med.name + '" (' + med.stock + ' units).', 'warn');
            return;
        }
        CART[key]++;
    } else {
        CART[key] = 1;
    }

    renderCart();
    notify('+1  ' + med.name, 'ok');
}

/** Increase qty by 1 */
function inc(id) {
    const key = String(id);
    if (CART[key] === undefined) return;
    const med = MED[id];
    if (CART[key] >= med.stock) {
        notify('Only ' + med.stock + ' units in stock.', 'warn');
        return;
    }
    CART[key]++;
    renderCart();
}

/** Decrease qty by 1; removes item if reaches 0 */
function dec(id) {
    const key = String(id);
    if (CART[key] === undefined) return;
    CART[key]--;
    if (CART[key] <= 0) delete CART[key];
    renderCart();
}

/** Set qty directly from the number input */
function setQty(id, rawVal) {
    const key = String(id);
    const med = MED[id];
    if (!med) return;
    let q = parseInt(rawVal, 10);
    if (isNaN(q) || q <= 0) { delete CART[key]; renderCart(); return; }
    if (q > med.stock)       { q = med.stock; notify('Max ' + med.stock + ' units.', 'warn'); }
    CART[key] = q;
    renderCart();
}

/** Remove one item entirely */
function removeItem(id) {
    delete CART[String(id)];
    renderCart();
}

/** Wipe the whole cart after confirmation */
function clearCart() {
    if (Object.keys(CART).length === 0) return;
    if (!confirm('Remove all items from the cart?')) return;
    Object.keys(CART).forEach(k => delete CART[k]);
    renderCart();
}

/* ══════════════════════════════════════════════════════════
   RENDER CART
   KEY FIX: #cartEmpty and #cartRows are SEPARATE divs.
   We NEVER replace innerHTML of the container that holds
   #cartEmpty — only #cartRows gets its innerHTML swapped.
   This means getElementById('cartEmpty') always returns
   the same element and never becomes null.
══════════════════════════════════════════════════════════ */
function renderCart() {
    const keys       = Object.keys(CART);                       // string ids in cart
    const typeCount  = keys.length;                             // distinct medicine types
    const unitCount  = keys.reduce((s,k) => s + CART[k], 0);   // total units
    const isEmpty    = typeCount === 0;

    /* 1 ── Show/hide the empty-state message (element is NEVER destroyed) */
    DOM.cartEmpty.style.display = isEmpty ? 'block' : 'none';

    /* 2 ── Build cart row HTML and inject into #cartRows only */
    if (isEmpty) {
        DOM.cartRows.innerHTML = '';
    } else {
        DOM.cartRows.innerHTML = keys.map(k => {
            const id    = parseInt(k, 10);
            const qty   = CART[k];
            const med   = MED[id];
            if (!med) return '';
            const line  = (med.price * qty).toFixed(2);
            return `
            <div class="c-row" id="row-${id}">
              <div class="c-row-name">
                <strong title="${esc(med.name)}">${esc(med.name)}</strong>
                <span>$${med.price.toFixed(2)} / unit</span>
              </div>
              <div class="qty-wrap">
                <button type="button" class="qty-dec" onclick="dec(${id})" title="Decrease">&#8722;</button>
                <input  type="number" class="qty-val"
                        value="${qty}" min="1" max="${med.stock}"
                        onchange="setQty(${id}, this.value)"
                        onblur="setQty(${id}, this.value)"
                        title="Quantity"/>
                <button type="button" class="qty-inc" onclick="inc(${id})" title="Increase">&#43;</button>
              </div>
              <div class="c-row-total">$${line}</div>
              <button type="button" class="c-row-del" onclick="removeItem(${id})" title="Remove item">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>`;
        }).join('');
    }

    /* 3 ── Sync tile pills + in-cart class */
    CAT.forEach(m => {
        const key  = String(m.id);
        const tile = document.getElementById('tile-' + m.id);
        const pill = document.getElementById('pill-' + m.id);
        if (!tile || !pill) return;
        const qty  = CART[key] || 0;
        pill.textContent = qty;
        tile.classList.toggle('in-cart', qty > 0);
    });

    /* 4 ── Header badge */
    DOM.cartBadge.textContent = isEmpty
        ? 'Empty'
        : unitCount + ' item' + (unitCount > 1 ? 's' : '');

    DOM.clearBtn.style.display = isEmpty ? 'none' : '';

    /* 5 ── Checkout button */
    DOM.checkoutBtn.disabled = isEmpty;
    DOM.checkoutLabel.textContent = isEmpty
        ? 'Add items to checkout'
        : 'Process Sale  (' + typeCount + ' product' + (typeCount > 1 ? 's' : '')
          + ', ' + unitCount + ' unit' + (unitCount > 1 ? 's' : '') + ')';

    /* 6 ── Summary line below button */
    DOM.cartSummary.style.display = isEmpty ? 'none' : '';
    if (!isEmpty) {
        DOM.cartSummary.textContent =
            typeCount + ' product type' + (typeCount > 1 ? 's' : '') +
            ' · ' + unitCount + ' total unit' + (unitCount > 1 ? 's' : '');
    }

    recalc();
}

/* ══════════════════════════════════════════════════════════
   TOTALS
══════════════════════════════════════════════════════════ */
function recalc() {
    let sub = 0;
    Object.keys(CART).forEach(k => {
        const med = MED[parseInt(k, 10)];
        if (med) sub += med.price * CART[k];
    });

    const rawDisc = parseFloat(DOM.discount.value) || 0;
    const disc    = Math.max(0, Math.min(rawDisc, sub));
    const total   = Math.max(0, sub - disc);
    const paid    = parseFloat(DOM.paid.value) || 0;
    const change  = paid - total;

    DOM.subtotal.textContent = '$' + sub.toFixed(2);
    DOM.total.textContent    = '$' + total.toFixed(2);

    if (payMode === 'cash') {
        if (paid > 0 && change < 0) {
            DOM.change.value      = '— Short $' + Math.abs(change).toFixed(2);
            DOM.change.style.color = '#ef4444';
        } else if (paid > 0) {
            DOM.change.value      = '$' + change.toFixed(2);
            DOM.change.style.color = '#16a34a';
        } else {
            DOM.change.value = '';
            DOM.change.style.color = '#16a34a';
        }
    } else {
        DOM.change.value = '';
    }
}

/* ══════════════════════════════════════════════════════════
   PAYMENT MODE
══════════════════════════════════════════════════════════ */
function setPay(btn) {
    document.querySelectorAll('.pay-tab').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    payMode = btn.dataset.m;
    DOM.fPaymethod.value = payMode;
    DOM.cashSection.style.display = payMode === 'cash' ? '' : 'none';
    recalc();
}

function quickPay(amount) {
    const total = parseFloat(DOM.total.textContent.replace('$','')) || 0;
    DOM.paid.value = (amount === null ? total : amount).toFixed ? (amount === null ? total.toFixed(2) : String(amount)) : String(amount);
    recalc();
}

/* ══════════════════════════════════════════════════════════
   FORM SUBMISSION
══════════════════════════════════════════════════════════ */
function doSubmit() {
    const keys = Object.keys(CART);
    if (keys.length === 0) { notify('Cart is empty!', 'err'); return false; }

    const total = parseFloat(DOM.total.textContent.replace('$','')) || 0;
    const paid  = payMode === 'cash'
        ? (parseFloat(DOM.paid.value) || 0)
        : total;

    if (payMode === 'cash' && paid < total) {
        notify('Cash paid is less than the total.', 'err');
        return false;
    }

    DOM.fCart.value     = JSON.stringify(keys.map(k => ({ id: parseInt(k,10), qty: CART[k] })));
    DOM.fCustomer.value = DOM.customer.value;
    DOM.fDiscount.value = DOM.discount.value;
    DOM.fPaid.value     = paid.toFixed(2);
    return true;
}

/* ══════════════════════════════════════════════════════════
   SEARCH / FILTER
══════════════════════════════════════════════════════════ */
document.getElementById('searchInput').addEventListener('input', applyFilter);
document.getElementById('catFilter').addEventListener('change', applyFilter);

function applyFilter() {
    const q   = document.getElementById('searchInput').value.trim().toLowerCase();
    const cat = document.getElementById('catFilter').value;
    let n = 0;
    document.querySelectorAll('.med-tile').forEach(tile => {
        const show = (!q   || tile.dataset.name.includes(q) || tile.dataset.barcode.includes(q))
                  && (!cat || tile.dataset.cat === cat);
        tile.style.display = show ? '' : 'none';
        if (show) n++;
    });
    DOM.visCount.textContent = n;
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('catFilter').value   = '';
    applyFilter();
}

/* ══════════════════════════════════════════════════════════
   BARCODE SCANNER SUPPORT
   Collects rapid keystrokes (< 80 ms apart) into a buffer;
   fires on Enter or after a short pause.
══════════════════════════════════════════════════════════ */
let bcBuf = '', bcTimer = null;
document.addEventListener('keydown', function(e) {
    const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

    if (e.key === 'Enter') {
        if (bcBuf.length >= 3) scanBarcode(bcBuf.trim());
        bcBuf = ''; clearTimeout(bcTimer); return;
    }
    if (e.key.length === 1) {
        bcBuf += e.key;
        clearTimeout(bcTimer);
        bcTimer = setTimeout(() => {
            if (bcBuf.length >= 3) scanBarcode(bcBuf.trim());
            bcBuf = '';
        }, 80);
    }
});

function scanBarcode(code) {
    const lc  = code.toLowerCase();
    const med = CAT.find(m => m.barcode && m.barcode.toLowerCase() === lc);
    if (med) {
        tileClick(med.id);
        document.getElementById('searchInput').value = '';
        applyFilter();
    } else {
        document.getElementById('searchInput').value = code;
        applyFilter();
        notify('Barcode "' + code + '" not found — showing search results', 'info');
    }
}

/* ══════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
══════════════════════════════════════════════════════════ */
const TCOLORS = { ok:'#16a34a', err:'#dc2626', warn:'#ea580c', info:'#2563eb' };
function notify(msg, type) {
    const el = document.createElement('div');
    el.className = 'pos-toast';
    el.style.background = TCOLORS[type] || TCOLORS.info;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .25s'; }, 2400);
    setTimeout(() => el.remove(), 2700);
}

/* ── HTML escape helper ─────────────────────────────────── */
function esc(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Initial render ─────────────────────────────────────── */
renderCart();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
