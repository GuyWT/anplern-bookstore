<?php include "connect.php"; ?>
<?php
// ประมวลผลการชำระเงิน (จำลอง Payment Gateway) แล้วสร้างคำสั่งซื้อ
//   ทุกขั้นตอนตรวจซ้ำฝั่งเซิร์ฟเวอร์ และบันทึกในฐานข้อมูลแบบ transaction (สำเร็จทั้งหมด หรือไม่บันทึกเลย)
require_login();
check_csrf();

$checkout = $_SESSION["checkout"] ?? null;
$cart     = cart_get($pdo);
$price    = price_cart($pdo, $cart, $_SESSION["coupon"] ?? "");
if (!$checkout || !$price["lines"]) {
    header("location: cart.php");
    exit;
}

// ยอดเงินต้องตรงกับที่แสดงในหน้าชำระเงิน (เช่น โปรฯ หมดเวลาหรือตะกร้าถูกแก้ในแท็บอื่น)
if (abs($price["net_total"] - $checkout["amount"]) > 0.001) {
    flash("error", "ยอดชำระเปลี่ยนเป็น " . number_format($price["net_total"], 2) . " บาท กรุณาตรวจสอบอีกครั้ง");
    header("location: checkout.php");
    exit;
}

// ตรวจบัตร: 16 หลัก ผ่านสูตร Luhn ยังไม่หมดอายุ CVV 3 หลัก
$card_last4 = null;
if ($checkout["method"] == "card") {
    $card_no = preg_replace('/\D/', '', $_POST["card_no"] ?? "");
    $sum = 0;
    for ($i = 0; $i < strlen($card_no); $i++) {
        $d = (int)$card_no[strlen($card_no) - 1 - $i];
        if ($i % 2 == 1) {
            $d *= 2;
            if ($d > 9) $d -= 9;
        }
        $sum += $d;
    }
    $exp_ok = preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $_POST["expiry"] ?? "", $exp)
              && ("20" . $exp[2] . $exp[1]) >= date("Ym");

    $error = "";
    if (strlen($card_no) != 16 || $sum % 10 != 0)
        $error = "เลขบัตรไม่ถูกต้อง";
    else if (!$exp_ok)
        $error = "บัตรหมดอายุหรือวันหมดอายุไม่ถูกต้อง";
    else if (!preg_match('/^[0-9]{3}$/', $_POST["cvv"] ?? ""))
        $error = "CVV ไม่ถูกต้อง";
    else if ($card_no == "4000000000000002")
        $error = "ธนาคารปฏิเสธรายการ: วงเงินไม่เพียงพอ";   // บัตรทดลองที่จำลองว่าถูกปฏิเสธ
    if ($error != "") {
        flash("error", "ชำระเงินไม่สำเร็จ: " . $error);
        header("location: payment.php");
        exit;
    }
    $card_last4 = substr($card_no, -4);
}

try {
    $pdo->beginTransaction();

    // ล็อกแถวหนังสือไว้ก่อน (FOR UPDATE) กันลูกค้า 2 คนซื้อเล่มสุดท้ายพร้อมกัน
    $ids = array_keys($cart);
    $marks = implode(", ", array_fill(0, count($ids), "?"));
    $stmt = $pdo->prepare("SELECT book_id, title, stock FROM book WHERE book_id IN ($marks) FOR UPDATE");
    $stmt->execute($ids);
    while ($row = $stmt->fetch()) {
        if ($cart[$row["book_id"]] > $row["stock"])
            throw new Exception("\"" . $row["title"] . "\" เหลือในสต็อกเพียง " . $row["stock"] . " เล่ม");
    }

    // 1. คำสั่งซื้อ
    $tier_id     = $price["tier"] ? $price["tier"]["promo_id"] : null;
    $coupon_code = $price["coupon"] ? $price["coupon"]["coupon_code"] : null;
    $stmt = $pdo->prepare("INSERT INTO orders (member_id, order_date, ship_address, subtotal, item_discount,
                                               promo_id, tier_discount, coupon_code, coupon_discount, net_total, status)
                           VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION["member_id"], $checkout["address"], $price["subtotal"], $price["item_discount"],
                    $tier_id, $price["tier_discount"], $coupon_code, $price["coupon_discount"], $price["net_total"]]);
    $order_id = $pdo->lastInsertId();

    // 2. รายการหนังสือ + ตัดสต็อก
    $item = $pdo->prepare("INSERT INTO order_item (order_id, book_id, quantity, unit_price, promo_id, discount_amount)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $cut  = $pdo->prepare("UPDATE book SET stock = stock - ? WHERE book_id = ?");
    foreach ($price["lines"] as $line) {
        $item->execute([$order_id, $line["book_id"], $line["qty"], $line["price"], $line["promo_id"], $line["discount_amount"]]);
        $cut->execute([$line["qty"], $line["book_id"]]);
    }

    // 3. การชำระเงิน (เลขอ้างอิงรูปแบบเดียวกับข้อมูลตัวอย่าง PAYyymmdd + เลขที่คำสั่งซื้อ 4 หลัก)
    $ref_no = "PAY" . date("ymd") . str_pad($order_id, 4, "0", STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO payment (order_id, method, amount, status, ref_no, card_last4, paid_at)
                           VALUES (?, ?, ?, 'paid', ?, ?, NOW())");
    $stmt->execute([$order_id, $checkout["method"], $price["net_total"], $ref_no, $card_last4]);

    // 4. ประวัติสถานะแรกของคำสั่งซื้อ
    $note = "ชำระเงินสำเร็จด้วย" . $method_th[$checkout["method"]] . ($card_last4 ? " ****" . $card_last4 : "")
          . " (เลขอ้างอิง " . $ref_no . ") รอร้านยืนยันคำสั่งซื้อ";
    $stmt = $pdo->prepare("INSERT INTO order_status_log (order_id, status, note, changed_at) VALUES (?, 'pending', ?, NOW())");
    $stmt->execute([$order_id, $note]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    flash("error", "สั่งซื้อไม่สำเร็จ: " . ($e instanceof PDOException ? "เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่" : $e->getMessage()));
    header("location: cart.php");
    exit;
}

cart_clear($pdo);
unset($_SESSION["checkout"]);
flash("ok", "ชำระเงินสำเร็จ ขอบคุณที่สั่งซื้อ คำสั่งซื้อเลขที่ #" . $order_id);
header("location: order-detail.php?order_id=" . $order_id);
?>
