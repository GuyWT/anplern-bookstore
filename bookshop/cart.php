<?php include "connect.php"; ?>
<?php
$title = "ตะกร้าสินค้า";
include "header.php";

$cart  = cart_get($pdo);
$price = price_cart($pdo, $cart, $_SESSION["coupon"] ?? "");

// ตะกร้าพร้อมสั่งซื้อเมื่อทุกเล่มมีสต็อกพอ
$stock_ok = true;
foreach ($price["lines"] as $line)
    if ($line["qty"] > $line["stock"]) $stock_ok = false;
?>
<p class="lead">เพิ่ม ลด หรือลบหนังสือที่ต้องการ ระบบคิดโปรโมชั่นอัตโนมัติ: ส่วนลดระดับสินค้า &rarr; ซื้อครบยอดลดเพิ่ม &rarr; คูปอง</p>

<?php if (!$price["lines"]) : ?>
<section class="empty">
  <p>ยังไม่มีหนังสือในตะกร้า</p>
  <p><a class="btn" href="search.php">เลือกซื้อหนังสือ &rarr;</a></p>
</section>
<?php else : ?>
<div class="table-wrap">
<table class="data cart">
  <thead>
    <tr><th>หนังสือ</th><th class="num">ราคา/เล่ม</th><th>จำนวน</th><th>โปรโมชั่น</th><th class="num">ส่วนลด</th><th class="num">รวม</th><th></th></tr>
  </thead>
  <tbody>
<?php foreach ($price["lines"] as $line) : ?>
    <tr>
      <td>
        <div class="book-cell">
        <img src="<?=cover($line["book_id"])?>" alt="" width="44">
        <div>
          <a href="book.php?book_id=<?=$line["book_id"]?>"><?=htmlspecialchars($line["title"])?></a>
          <small><?=htmlspecialchars($line["author"])?></small>
<?php   if ($line["qty"] > $line["stock"]) : ?>
          <small class="warn-text">เหลือในสต็อกเพียง <?=$line["stock"]?> เล่ม กรุณาลดจำนวน</small>
<?php   endif; ?>
        </div>
        </div>
      </td>
      <td class="num"><?=number_format($line["price"], 2)?></td>
      <td>
        <form method="post" action="cart-action.php" class="qty-form">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="book_id" value="<?=$line["book_id"]?>">
          <input type="number" name="qty" value="<?=$line["qty"]?>" min="1" max="<?=max(1, $line["stock"])?>" required
                 aria-label="จำนวน" onchange="this.form.submit()">
          <noscript><input type="submit" value="ปรับ"></noscript>
        </form>
      </td>
      <td><?=$line["promo_name"] ? '<span class="badge sale">-' . ($line["discount_percent"] + 0) . '%</span> ' . htmlspecialchars($line["promo_name"]) : "-"?></td>
      <td class="num"><?=$line["discount_amount"] > 0 ? "-" . number_format($line["discount_amount"], 2) : "-"?></td>
      <td class="num"><?=number_format($line["line_total"], 2)?></td>
      <td>
        <form method="post" action="cart-action.php">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="book_id" value="<?=$line["book_id"]?>">
          <button class="link-btn" title="ลบออกจากตะกร้า">ลบ</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="cart-bottom">
  <section class="panel">
    <h2>โปรโมชั่นและคูปอง</h2>
<?php   if ($price["tier"]) : ?>
    <p class="msg ok">ได้รับ "<?=htmlspecialchars($price["tier"]["promo_name"])?>" แล้ว</p>
<?php   endif; ?>
<?php   if ($price["next_tier"]) : ?>
<?php     $after_item = $price["subtotal"] - $price["item_discount"]; ?>
    <p>ซื้อเพิ่มอีก <b><?=number_format($price["next_tier"]["min_spend"] - $after_item, 2)?></b> บาท
       รับส่วนลดเพิ่ม <?=$price["next_tier"]["discount_percent"] + 0?>% ทันที</p>
    <div class="progress"><div style="width: <?=round($after_item / $price["next_tier"]["min_spend"] * 100)?>%"></div></div>
<?php   endif; ?>

<?php   if (!empty($_SESSION["coupon"])) : ?>
    <form method="post" action="cart-action.php" class="coupon-applied">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="remove_coupon">
<?php     if ($price["coupon"]) : ?>
      คูปอง <b><?=htmlspecialchars($_SESSION["coupon"])?></b> &ndash; <?=htmlspecialchars($price["coupon"]["description"])?>
<?php     else : ?>
      <span class="warn-text">คูปอง <b><?=htmlspecialchars($_SESSION["coupon"])?></b> ใช้ไม่ได้: <?=htmlspecialchars($price["coupon_error"])?></span>
<?php     endif; ?>
      <button class="link-btn">ยกเลิกคูปอง</button>
    </form>
<?php   else : ?>
    <form method="post" action="cart-action.php" class="inline-form">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="coupon">
      <input type="text" name="code" placeholder="รหัสคูปอง เช่น BOOK15" required
             pattern="[A-Za-z0-9]{3,20}" title="ตัวอักษรภาษาอังกฤษหรือตัวเลข 3-20 ตัว" aria-label="รหัสคูปอง">
      <input type="submit" value="ใช้คูปอง">
    </form>
    <p class="hint"><a href="coupon.php">ดูคูปองที่ใช้ได้</a></p>
<?php   endif; ?>
  </section>

  <section class="panel summary">
    <h2>สรุปยอด</h2>
    <dl>
      <dt>ราคารวม</dt><dd><?=number_format($price["subtotal"], 2)?></dd>
      <dt>ส่วนลดระดับสินค้า</dt><dd>-<?=number_format($price["item_discount"], 2)?></dd>
      <dt>ส่วนลดซื้อครบยอด</dt><dd>-<?=number_format($price["tier_discount"], 2)?></dd>
      <dt>คูปอง</dt><dd>-<?=number_format($price["coupon_discount"], 2)?></dd>
      <dt class="total">ยอดที่ต้องชำระ</dt><dd class="total"><?=number_format($price["net_total"], 2)?> ฿</dd>
    </dl>
<?php   if (!$stock_ok) : ?>
    <p class="warn-text">มีหนังสือบางเล่มเกินจำนวนในสต็อก กรุณาแก้ไขจำนวนก่อนสั่งซื้อ</p>
<?php   elseif (is_login()) : ?>
    <a class="btn block" href="checkout.php">ดำเนินการสั่งซื้อ &rarr;</a>
<?php   else : ?>
    <a class="btn block" href="login.php?back=checkout.php">เข้าสู่ระบบเพื่อสั่งซื้อ &rarr;</a>
    <p class="hint">หนังสือในตะกร้าจะย้ายไปอยู่ในบัญชีของคุณหลังเข้าสู่ระบบ</p>
<?php   endif; ?>
  </section>
</div>
<?php endif; ?>
<?php include "footer.php"; ?>
