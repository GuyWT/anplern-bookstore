<?php include "connect.php"; ?>
<?php
// Delete: ลบหนังสือตามรหัสที่ส่งมา (รับแบบ POST พร้อม token กันการลบด้วยลิงก์จากเว็บอื่น)
// ถ้าหนังสือมีประวัติการสั่งซื้อ (order_item อ้างถึงอยู่) ฐานข้อมูลจะไม่ยอมให้ลบ (Foreign Key)
require_admin();
check_csrf();
$book_id = (int)($_POST["book_id"] ?? 0);
try {
    $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = ?");
    $stmt->bindParam(1, $book_id);
    $stmt->execute();
    if (file_exists("img/" . $book_id . ".jpg"))
        unlink("img/" . $book_id . ".jpg");   // ลบไฟล์ภาพปกด้วย
    header("location: list-book.php?msg=deleted&book_id=" . $book_id);
} catch (PDOException $e) {
    header("location: list-book.php?msg=delete_failed&book_id=" . $book_id);
}
?>
