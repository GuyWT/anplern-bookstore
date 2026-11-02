<?php include "connect.php"; ?>
<?php
// หน้าชำระเงินของ Payment Gateway จำลอง "อ่านเพลิน Pay" (ไม่มีการตัดเงินจริง)
require_login();

$cart  = cart_get($pdo);
$price = price_cart($pdo, $cart, $_SESSION["coupon"] ?? "");
if (!$price["lines"]) {
    header("location: cart.php");
    exit;
}

// มาจากหน้ายืนยันคำสั่งซื้อ: เก็บที่อยู่ วิธีชำระเงิน และยอดเงินไว้ใน session
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    check_csrf();
    $address = trim($_POST["address"] ?? "");
    $method  = $_POST["method"] ?? "";
    if (mb_strlen($address) < 10 || !isset($method_th[$method])) {
        flash("error", "กรุณากรอกที่อยู่จัดส่งและเลือกวิธีชำระเงิน");
        header("location: checkout.php");
        exit;
    }
    $_SESSION["checkout"] = ["address" => $address, "method" => $method, "amount" => $price["net_total"]];
}
if (empty($_SESSION["checkout"])) {
    header("location: checkout.php");
    exit;
}
$checkout = $_SESSION["checkout"];

$title = "ชำระเงิน";
include "header.php";

// QR Code จำลอง: วาดตาราง 25x25 ช่อง จากค่า hash ของยอดเงิน (ไม่ใช่ QR จริง สแกนไม่ได้)
function fake_qr($seed) {
    $n = 25;
    $bits = "";
    for ($i = 0; strlen($bits) < $n * $n; $i++)
        foreach (str_split(md5($seed . $i)) as $hex)
            $bits .= str_pad(base_convert($hex, 16, 2), 4, "0", STR_PAD_LEFT);
    $svg = '<svg viewBox="0 0 ' . ($n + 2) . ' ' . ($n + 2) . '" class="qr" role="img" aria-label="QR Code พร้อมเพย์ (จำลอง)">';
    $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            $finder = ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
            if ($finder) {
                $dx = $x < 7 ? $x : $x - ($n - 7);
                $dy = $y < 7 ? $y : $y - ($n - 7);
                $on = $dx == 0 || $dx == 6 || $dy == 0 || $dy == 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4);
            } else {
                $on = $bits[$y * $n + $x] == "1";
            }
            if ($on)
                $svg .= '<rect x="' . ($x + 1) . '" y="' . ($y + 1) . '" width="1" height="1"/>';
        }
    }
    return $svg . '</svg>';
}
?>
<script>
  // จัดรูปแบบเลขบัตรเป็นกลุ่มละ 4 ตัว ขณะพิมพ์
  function formatCard(input) {
    var digits = input.value.replace(/\D/g, "").substring(0, 16);
    input.value = digits.replace(/(\d{4})(?=\d)/g, "$1 ");
  }

  // ตรวจเลขบัตรด้วยสูตร Luhn (เลขบัตรจริงทุกใบผ่านสูตรนี้) และตรวจวันหมดอายุ ก่อนส่งฟอร์ม
  function checkCard() {
    var input = document.getElementById("card_no");
    var digits = input.value.replace(/\D/g, "");
    var sum = 0;
    for (var i = 0; i < digits.length; i++) {
      var d = parseInt(digits.charAt(digits.length - 1 - i));
      if (i % 2 == 1) { d = d * 2; if (d > 9) d = d - 9; }
      sum += d;
    }
    input.setCustomValidity(digits.length == 16 && sum % 10 == 0 ? "" : "เลขบัตรไม่ถูกต้อง");

    var exp = document.getElementById("expiry");
    var parts = exp.value.split("/");
    var now = new Date();
    var expired = parts.length == 2 &&
      (2000 + parseInt(parts[1]) < now.getFullYear() ||
       (2000 + parseInt(parts[1]) == now.getFullYear() && parseInt(parts[0]) < now.getMonth() + 1));
    exp.setCustomValidity(expired ? "บัตรหมดอายุแล้ว" : "");
  }

  // นับถอยหลังเวลาชำระเงินด้วย QR (5 นาที)
  function startTimer() {
    var left = 300;
    var timer = setInterval(function () {
      left--;
      var m = Math.floor(left / 60), s = left % 60;
      document.getElementById("qr-timer").innerHTML = m + ":" + (s < 10 ? "0" : "") + s;
      if (left <= 0) {
        clearInterval(timer);
        document.getElementById("qr-box").style.opacity = "0.3";
        document.getElementById("qr-paid").disabled = true;
        document.getElementById("qr-timer").innerHTML = "QR หมดอายุ กรุณารีเฟรชหน้า";
      }
    }, 1000);
  }
</script>

<ol class="steps">
  <li class="done">ตะกร้าสินค้า</li>
  <li class="done">ยืนยันคำสั่งซื้อ</li>
  <li class="now">ชำระเงิน</li>
  <li>ติดตามพัสดุ</li>
</ol>

<section class="gateway">
  <header class="gateway-head">
    <b>อ่านเพลิน Pay</b> <span class="badge warn">Payment Gateway จำลอง</span>
  </header>
  <div class="gateway-amount">
    <span>ร้าน อ่านเพลิน Bookstore &middot; <?=array_sum($cart)?> เล่ม</span>
    <b><?=number_format($checkout["amount"], 2)?> ฿</b>
  </div>

<?php if ($checkout["method"] == "card") : ?>
  <form action="pay-process.php" method="post" class="edit" onsubmit="checkCard(); return this.reportValidity();">
    <?=csrf_field()?>
    <label for="card_no">เลขบัตร</label>
    <input type="text" id="card_no" name="card_no" required inputmode="numeric" autocomplete="cc-number"
           pattern="[0-9]{4} [0-9]{4} [0-9]{4} [0-9]{4}" title="เลขบัตร 16 หลัก" placeholder="0000 0000 0000 0000"
           oninput="formatCard(this); this.setCustomValidity('')">

    <label for="card_name">ชื่อบนบัตร</label>
    <input type="text" id="card_name" name="card_name" required autocomplete="cc-name"
           pattern="[A-Za-z ]{2,50}" title="ชื่อภาษาอังกฤษตามบัตร" placeholder="SOMCHAI JAIDEE">

    <div class="row-2">
      <div>
        <label for="expiry">วันหมดอายุ (ดด/ปป)</label>
        <input type="text" id="expiry" name="expiry" required inputmode="numeric" autocomplete="cc-exp"
               pattern="(0[1-9]|1[0-2])/[0-9]{2}" title="เช่น 08/28" placeholder="08/28" oninput="this.setCustomValidity('')">
      </div>
      <div>
        <label for="cvv">CVV</label>
        <input type="password" id="cvv" name="cvv" required inputmode="numeric" autocomplete="cc-csc"
               pattern="[0-9]{3}" title="ตัวเลข 3 หลักหลังบัตร" maxlength="3" placeholder="123">
      </div>
    </div>

    <p><input type="submit" class="block" value="ชำระเงิน <?=number_format($checkout["amount"], 2)?> บาท"></p>
  </form>
  <div class="hint-box">
    <b>บัตรสำหรับทดลอง</b> (วันหมดอายุในอนาคตและ CVV ใดก็ได้)<br>
    <code>4242 4242 4242 4242</code> ชำระสำเร็จ &middot; <code>4000 0000 0000 0002</code> ถูกปฏิเสธ (วงเงินไม่พอ)
  </div>

<?php else : ?>
  <div class="qr-pay">
    <div id="qr-box"><?=fake_qr($checkout["amount"] . $_SESSION["member_id"])?></div>
    <p>สแกนจ่ายด้วยแอปธนาคาร ภายใน <b id="qr-timer">5:00</b> นาที</p>
    <form action="pay-process.php" method="post">
      <?=csrf_field()?>
      <input type="submit" id="qr-paid" value="จำลอง: ชำระเงินผ่านแอปแล้ว">
    </form>
  </div>
  <script>window.addEventListener("load", startTimer);</script>
<?php endif; ?>

  <p class="gateway-foot"><a href="checkout.php">&larr; ยกเลิก และกลับไปเปลี่ยนวิธีชำระเงิน</a></p>
</section>
<?php include "footer.php"; ?>
