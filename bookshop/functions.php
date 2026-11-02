<?php
// ฟังก์ชันและค่าคงที่ที่ใช้ร่วมกันทุกหน้า (ถูก include จาก connect.php)

// ป้ายภาษาไทย
$status_th = [
    "pending"   => "รอยืนยันคำสั่งซื้อ",
    "packing"   => "กำลังแพ็กสินค้า",
    "shipped"   => "จัดส่งแล้ว",
    "delivered" => "ได้รับสินค้าแล้ว",
    "cancelled" => "ยกเลิก",
];
$method_th = ["card" => "บัตรเครดิต/เดบิต", "promptpay" => "พร้อมเพย์ (QR)"];
$carriers  = ["Kerry Express", "Flash Express", "J&T Express", "ไปรษณีย์ไทย (EMS)"];

// ลำดับสถานะคำสั่งซื้อที่เปลี่ยนได้ (ได้รับสินค้าแล้ว/ยกเลิก ถือว่าจบ แก้ไม่ได้)
//   ใช้ทั้งตัวเลือกในฟอร์ม (order-detail.php) และการตรวจฝั่งเซิร์ฟเวอร์ (order-update.php)
$next_status = [
    "pending" => ["packing", "cancelled"],
    "packing" => ["shipped", "cancelled"],
    "shipped" => ["delivered"],
];

// วันเริ่มต้นของรายงาน: 1 ม.ค. ของปีที่มีคำสั่งซื้อแรก
//   (ถ้าใช้ "ต้นปีปัจจุบัน" พอขึ้นปีใหม่ แดชบอร์ดจะว่างเพราะข้อมูลตัวอย่างอยู่ปีก่อน)
function report_start($pdo) {
    $first = $pdo->query("SELECT YEAR(MIN(order_date)) FROM orders")->fetchColumn();
    return ($first ? $first : date("Y")) . "-01-01";
}

// แสดงตัวเลขจากฐานข้อมูลแบบตัดศูนย์ท้าย เช่น 600.00 -> 600 (ค่าว่าง/NULL แสดงเป็นช่องว่าง)
function blank_num($value) {
    return $value === null || $value === "" ? "" : $value + 0;
}

// ภาพปกหนังสือ img/<book_id>.jpg (ถ้าไม่มีใช้ภาพแทน)
function cover($book_id) {
    return file_exists("img/" . (int)$book_id . ".jpg") ? "img/" . (int)$book_id . ".jpg" : "img/no-cover.jpg";
}

// ---------- ผู้ใช้และสิทธิ์ (ข้อมูลจาก Session) ----------
function is_login() {
    return !empty($_SESSION["member_id"]);
}

function is_admin() {
    return isset($_SESSION["role"]) && $_SESSION["role"] == "admin";
}

// หน้าปัจจุบัน (ชื่อไฟล์ + query string) ใช้พากลับมาหลัง login
function this_page() {
    return basename($_SERVER["PHP_SELF"]) . ($_SERVER["QUERY_STRING"] != "" ? "?" . $_SERVER["QUERY_STRING"] : "");
}

// รับเฉพาะลิงก์ภายในเว็บ เช่น cart.php?x=1 (กันการพาไปเว็บอื่น)
function safe_back($url) {
    return preg_match('/^[a-z0-9\-]+\.php(\?[^\s]*)?$/i', $url) ? $url : "index.php";
}

// หน้าที่ต้อง login ก่อน: ถ้ายังไม่ login ให้ไปหน้า login แล้วพากลับมาหน้าเดิม
function require_login() {
    if (!is_login()) {
        header("location: login.php?back=" . urlencode(this_page()));
        exit;
    }
}

// หน้าสำหรับผู้ดูแลระบบเท่านั้น
function require_admin() {
    if (!is_admin()) {
        header("location: login.php?msg=admin&back=" . urlencode(this_page()));
        exit;
    }
}

// ข้อความแจ้งเตือนครั้งเดียว: เก็บใน session แล้วแสดงในหน้าถัดไป (header.php แสดงแล้วลบทิ้ง)
function flash($type, $text) {
    $_SESSION["flash"] = [$type, $text];
}

// ---------- CSRF token: กันเว็บอื่นแอบส่งฟอร์มแทนผู้ใช้ ----------
function csrf_token() {
    if (empty($_SESSION["token"]))
        $_SESSION["token"] = bin2hex(random_bytes(16));
    return $_SESSION["token"];
}

function csrf_field() {
    return '<input type="hidden" name="token" value="' . csrf_token() . '">';
}

function check_csrf() {
    if (!hash_equals(csrf_token(), $_POST["token"] ?? "")) {
        http_response_code(400);
        exit("คำขอไม่ถูกต้อง (token ไม่ตรง) กรุณากลับไปหน้าเดิม รีเฟรช แล้วลองใหม่");
    }
}

// ---------- ตะกร้าสินค้า ----------
//   ยังไม่ login : เก็บใน $_SESSION["cart"] = [book_id => จำนวน]
//   login แล้ว   : เก็บในตาราง cart_item ของสมาชิก (เปิดเครื่องอื่นก็ยังอยู่)
function cart_get($pdo) {
    if (!is_login())
        return $_SESSION["cart"] ?? [];
    $stmt = $pdo->prepare("SELECT book_id, quantity FROM cart_item WHERE member_id = ? ORDER BY added_at");
    $stmt->bindParam(1, $_SESSION["member_id"]);
    $stmt->execute();
    $cart = [];
    while ($row = $stmt->fetch())
        $cart[$row["book_id"]] = $row["quantity"];
    return $cart;
}

// กำหนดจำนวนของหนังสือในตะกร้า (จำนวน 0 = ลบออกจากตะกร้า)
function cart_set($pdo, $book_id, $qty) {
    if (!is_login()) {
        if ($qty > 0)
            $_SESSION["cart"][$book_id] = $qty;
        else
            unset($_SESSION["cart"][$book_id]);   // Remove a session variable
        return;
    }
    if ($qty > 0) {
        $stmt = $pdo->prepare("INSERT INTO cart_item (member_id, book_id, quantity) VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
        $stmt->execute([$_SESSION["member_id"], $book_id, $qty]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart_item WHERE member_id = ? AND book_id = ?");
        $stmt->execute([$_SESSION["member_id"], $book_id]);
    }
}

function cart_count($pdo) {
    return array_sum(cart_get($pdo));
}

function cart_clear($pdo) {
    unset($_SESSION["cart"], $_SESSION["coupon"]);
    if (is_login()) {
        $stmt = $pdo->prepare("DELETE FROM cart_item WHERE member_id = ?");
        $stmt->bindParam(1, $_SESSION["member_id"]);
        $stmt->execute();
    }
}

// หลัง login: ย้ายหนังสือที่เลือกไว้ตอนยังไม่ login (ใน session) ไปรวมกับตะกร้าในฐานข้อมูล
//   รวมจำนวนกับของเดิม แต่ไม่เกินจำนวนคงเหลือในสต็อก
function cart_merge_session($pdo) {
    if (empty($_SESSION["cart"]))
        return;
    $stmt = $pdo->prepare("INSERT INTO cart_item (member_id, book_id, quantity)
                           SELECT ?, book_id, LEAST(?, stock) FROM book WHERE book_id = ? AND stock > 0
                           ON DUPLICATE KEY UPDATE quantity = LEAST(cart_item.quantity + VALUES(quantity),
                                                                    (SELECT stock FROM book WHERE book_id = ?))");
    foreach ($_SESSION["cart"] as $book_id => $qty)
        $stmt->execute([$_SESSION["member_id"], $qty, $book_id, $book_id]);
    unset($_SESSION["cart"]);
}

// ---------- ตรวจข้อมูลหนังสือฝั่งเซิร์ฟเวอร์ (ใช้ทั้งเพิ่มและแก้ไข) ----------
//   แก้ bug ที่พบในวิชา Software Testing:
//   BUG-01 ราคา/จำนวนติดลบบันทึกได้ เมื่อปิดการตรวจในเบราว์เซอร์  -> ตรวจซ้ำที่นี่
//   BUG-02 ส่งหมวดหมู่ที่ไม่มีอยู่จริงแล้วขึ้น Fatal error        -> ตรวจว่าหมวดมีอยู่จริงก่อนบันทึก
function book_errors($pdo, $data) {
    $errors = [];
    if (trim($data["title"] ?? "") == "" || mb_strlen($data["title"]) > 200)
        $errors[] = "กรุณากรอกชื่อหนังสือ (ไม่เกิน 200 ตัวอักษร)";
    if (trim($data["author"] ?? "") == "" || mb_strlen($data["author"]) > 150)
        $errors[] = "กรุณากรอกชื่อผู้แต่ง (ไม่เกิน 150 ตัวอักษร)";
    if (!is_numeric($data["price"] ?? "") || $data["price"] < 1 || $data["price"] > 99999)
        $errors[] = "ราคาต้องอยู่ระหว่าง 1 - 99,999 บาท";
    if (!preg_match('/^[0-9]{1,5}$/', $data["stock"] ?? ""))
        $errors[] = "จำนวนคงเหลือต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป";
    $d = DateTime::createFromFormat("Y-m-d", $data["added_date"] ?? "");
    if (!$d || $d->format("Y-m-d") != $data["added_date"])
        $errors[] = "วันที่นำเข้าระบบไม่ถูกต้อง";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE category_id = ?");
    $stmt->bindParam(1, $data["category_id"]);
    $stmt->execute();
    if ($stmt->fetchColumn() == 0)
        $errors[] = "กรุณาเลือกหมวดหมู่ที่มีอยู่ในระบบ";
    return $errors;
}

// บันทึกภาพปก img/<book_id>.jpg จากไฟล์ที่อัปโหลด (รับเฉพาะ JPG)
//   หรือจากลิงก์ภาพปกของ Open Library ที่ได้จากการนำเข้าด้วย ISBN
function save_cover($book_id, $cover_url) {
    if (!empty($_FILES["cover"]["tmp_name"])) {
        $info = getimagesize($_FILES["cover"]["tmp_name"]);
        if (!$info || $info[2] != IMAGETYPE_JPEG)
            return false;
        return move_uploaded_file($_FILES["cover"]["tmp_name"], "img/" . (int)$book_id . ".jpg");
    }
    // รับเฉพาะลิงก์ของ covers.openlibrary.org (กันการสั่งให้เซิร์ฟเวอร์ไปโหลดไฟล์จากที่อื่น)
    if (preg_match('#^https://covers\.openlibrary\.org/b/(id|isbn|olid)/[A-Za-z0-9\-]+\.jpg$#', $cover_url)) {
        $context = stream_context_create(["http" => ["timeout" => 10]]);
        $image = @file_get_contents($cover_url, false, $context);
        $info = $image ? @getimagesizefromstring($image) : false;
        if (!$info || $info[2] != IMAGETYPE_JPEG || $info[0] < 20)
            return false;
        return file_put_contents("img/" . (int)$book_id . ".jpg", $image) !== false;
    }
    return true;   // ไม่ได้เลือกภาพปก
}

// ---------- คำนวณราคา ----------
// Query 6.1: ตรวจคูปองที่ลูกค้ากรอก กับยอดซื้อหลังหักโปรโมชั่น
//   ตรวจ วันเริ่ม/วันหมดอายุ, สิทธิ์คงเหลือ (usage_limit - จำนวนครั้งที่ใช้แล้ว), ยอดขั้นต่ำ
//   แล้วคำนวณส่วนลด (percent มีเพดาน max_discount / fixed ลดไม่เกินยอดซื้อ)
define("COUPON_CHECK_SQL", "SELECT c.coupon_code, c.description, c.min_spend, c.start_date, c.end_date,
       c.usage_limit - COUNT(o.order_id) AS remaining,
       CASE
         WHEN CURDATE() < c.start_date              THEN 'คูปองยังไม่เริ่มใช้งาน'
         WHEN CURDATE() > c.end_date                THEN 'คูปองหมดอายุแล้ว'
         WHEN COUNT(o.order_id) >= c.usage_limit    THEN 'สิทธิ์การใช้คูปองเต็มแล้ว'
         WHEN a.amount < c.min_spend                THEN 'ยอดซื้อยังไม่ถึงขั้นต่ำของคูปอง'
         ELSE 'ใช้คูปองได้'
       END AS check_result,
       CASE
         WHEN c.discount_type = 'fixed' THEN LEAST(c.discount_value, a.amount)
         ELSE LEAST(ROUND(a.amount * c.discount_value / 100, 2), IFNULL(c.max_discount, a.amount))
       END AS discount
FROM coupon c
CROSS JOIN (SELECT CAST(? AS DECIMAL(10,2)) AS amount) a
LEFT JOIN orders o ON o.coupon_code = c.coupon_code AND o.status <> 'cancelled'
WHERE c.coupon_code = ?
GROUP BY c.coupon_code, c.description, c.discount_type, c.discount_value, c.max_discount,
         c.min_spend, c.usage_limit, c.start_date, c.end_date, a.amount");

function check_coupon($pdo, $code, $amount) {
    $stmt = $pdo->prepare(COUPON_CHECK_SQL);
    $stmt->bindParam(1, $amount);
    $stmt->bindParam(2, $code);
    $stmt->execute();
    return $stmt->fetch();
}

// คิดราคาตะกร้าตามลำดับส่วนลดของร้าน (วิธีเดียวกับคำสั่งซื้อที่มีอยู่ในฐานข้อมูล)
//   (1) ส่วนลดระดับสินค้า: โปรฯ เทศกาล/ล้างสต็อกที่ลดมากที่สุด 1 รายการต่อเล่ม
//   (2) ส่วนลดยอดซื้อขั้นบันได: ซื้อครบ 600 ลด 5%, ครบ 1,200 ลด 10% (คิดจากยอดหลังข้อ 1)
//   (3) คูปอง: คิดจากยอดหลังข้อ 2
function price_cart($pdo, $cart, $coupon_code) {
    $r = ["lines" => [], "subtotal" => 0, "item_discount" => 0,
          "tier" => null, "next_tier" => null, "tier_discount" => 0,
          "coupon" => null, "coupon_error" => "", "coupon_discount" => 0, "net_total" => 0];
    if (!$cart)
        return $r;

    // (1) ราคาและโปรฯ ระดับสินค้าของหนังสือในตะกร้า (เงื่อนไขเดียวกับ Query 1 หน้าค้นหา)
    $ids = array_keys($cart);
    $marks = implode(", ", array_fill(0, count($ids), "?"));
    $stmt = $pdo->prepare("SELECT b.book_id, b.title, b.author, b.price, b.stock,
               p.promo_id, p.promo_name, IFNULL(p.discount_percent, 0) AS discount_percent
        FROM book b
        LEFT JOIN promotion p ON p.promo_id = (
            SELECT p2.promo_id
            FROM promotion p2
            WHERE p2.is_active = 1
              AND CURDATE() BETWEEN p2.start_date AND p2.end_date
              AND (  (p2.promo_type = 'seasonal'
                      AND (p2.category_id IS NULL OR p2.category_id = b.category_id))
                  OR (p2.promo_type = 'clearance'
                      AND DATEDIFF(CURDATE(), b.added_date) >= p2.min_days_in_stock
                      AND b.stock <= p2.max_stock) )
            ORDER BY p2.discount_percent DESC
            LIMIT 1)
        WHERE b.book_id IN ($marks)
        ORDER BY b.title");
    $stmt->execute($ids);
    while ($row = $stmt->fetch()) {
        $row["qty"]             = $cart[$row["book_id"]];
        $row["line_price"]      = $row["qty"] * $row["price"];
        $row["discount_amount"] = round($row["line_price"] * $row["discount_percent"] / 100, 2);
        $row["line_total"]      = $row["line_price"] - $row["discount_amount"];
        $r["lines"][]           = $row;
        $r["subtotal"]         += $row["line_price"];
        $r["item_discount"]    += $row["discount_amount"];
    }
    $after_item = round($r["subtotal"] - $r["item_discount"], 2);

    // (2) โปรฯ ขั้นบันไดขั้นที่สูงที่สุดที่ยอดถึง และขั้นถัดไปที่ยังไม่ถึง (ไว้บอกลูกค้าว่าซื้อเพิ่มอีกเท่าไร)
    $stmt = $pdo->prepare("SELECT promo_id, promo_name, discount_percent, min_spend
                           FROM promotion
                           WHERE promo_type = 'tier' AND is_active = 1
                             AND CURDATE() BETWEEN start_date AND end_date
                           ORDER BY min_spend");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if ($after_item >= $row["min_spend"])
            $r["tier"] = $row;
        else if (!$r["next_tier"])
            $r["next_tier"] = $row;
    }
    if ($r["tier"])
        $r["tier_discount"] = round($after_item * $r["tier"]["discount_percent"] / 100, 2);
    $after_tier = round($after_item - $r["tier_discount"], 2);

    // (3) คูปอง
    if ($coupon_code != "") {
        $check = check_coupon($pdo, $coupon_code, $after_tier);
        if (!$check)
            $r["coupon_error"] = "ไม่พบคูปองรหัส " . $coupon_code;
        else if ($check["check_result"] != "ใช้คูปองได้")
            $r["coupon_error"] = $check["check_result"] . " (ยอดขั้นต่ำ " . number_format($check["min_spend"], 2) . " บาท)";
        else {
            $r["coupon"] = $check;
            $r["coupon_discount"] = $check["discount"];
        }
    }
    $r["net_total"] = round($after_tier - $r["coupon_discount"], 2);
    return $r;
}
?>
