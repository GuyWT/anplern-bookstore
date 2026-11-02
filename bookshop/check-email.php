<?php include "connect.php"; ?>
<?php
// Ajax: ตรวจว่าอีเมลนี้มีผู้ใช้แล้วหรือยัง (เรียกจากหน้าสมัครสมาชิก)
//   ตอบกลับเป็นข้อความธรรมดา (responseText): okay = ใช้ได้, taken = มีผู้ใช้แล้ว, invalid = รูปแบบผิด
$email = trim($_GET["email"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "invalid";
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
    $stmt->bindParam(1, $email);
    $stmt->execute();
    echo $stmt->fetchColumn() > 0 ? "taken" : "okay";
}
?>
