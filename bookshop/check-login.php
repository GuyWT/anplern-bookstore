<?php include "connect.php"; ?>
<?php
// ตรวจอีเมล/รหัสผ่าน (รหัสผ่านในฐานข้อมูลเก็บแบบ password_hash จึงตรวจด้วย password_verify)
check_csrf();
$email = trim($_POST["email"] ?? "");
$back  = safe_back($_POST["back"] ?? "index.php");

$stmt = $pdo->prepare("SELECT member_id, first_name, password_hash, role FROM member WHERE email = ?");
$stmt->bindParam(1, $email);
$stmt->execute();
$row = $stmt->fetch();

// หากอีเมลและรหัสผ่านตรงกัน
if ($row && password_verify($_POST["password"] ?? "", $row["password_hash"])) {
    // เปลี่ยน session id ป้องกัน Session Fixation (Lecture 9 สไลด์ 23)
    session_regenerate_id(true);

    // นำข้อมูลผู้ใช้จากฐานข้อมูลเขียนลง session
    $_SESSION["member_id"]  = $row["member_id"];
    $_SESSION["first_name"] = $row["first_name"];
    $_SESSION["role"]       = $row["role"];
    $_SESSION["login_time"] = date("Y-m-d H:i:s");

    // หนังสือที่เลือกไว้ก่อน login ย้ายไปตะกร้าในฐานข้อมูล
    cart_merge_session($pdo);

    // Cookie จดจำอีเมล 30 วัน (ถ้าไม่ติ๊ก ให้ลบ cookie ทิ้ง)
    if (!empty($_POST["remember"]))
        setcookie("remember_email", $email, time() + 3600 * 24 * 30, "", "", false, true);
    else
        setcookie("remember_email", "", time() - 3600);

    flash("ok", "ยินดีต้อนรับ คุณ" . $row["first_name"]);
    if ($row["role"] == "admin" && $back == "index.php")
        $back = "admin.php";
    header("location: " . $back);

// ไม่บอกว่าผิดที่อีเมลหรือรหัสผ่าน เพื่อไม่ให้ผู้ไม่หวังดีเดาบัญชีได้ (Web Application Security)
} else {
    header("location: login.php?msg=failed&back=" . urlencode($back));
}
?>
