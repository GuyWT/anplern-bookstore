<?php include "connect.php"; ?>
<?php
// Ajax: ค้นหาหนังสือจากฐานข้อมูลขณะพิมพ์ ตอบกลับเป็น HTML (รายการ 5 เล่มแรกที่ตรงกับคำค้น)
$like = "%" . ($_GET["keyword"] ?? "") . "%";
$stmt = $pdo->prepare("SELECT book_id, title, author, price FROM book
                       WHERE title LIKE ? OR author LIKE ?
                       ORDER BY title LIMIT 5");
$stmt->bindParam(1, $like);
$stmt->bindParam(2, $like);
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<?php if (!$rows) : ?>
<p class="suggest-empty">ไม่พบหนังสือที่ตรงกับคำค้น</p>
<?php else : ?>
<ul>
<?php   foreach ($rows as $row) : ?>
  <li>
    <a href="book.php?book_id=<?=$row["book_id"]?>">
      <img src="<?=cover($row["book_id"])?>" alt="" width="28">
      <span><?=htmlspecialchars($row["title"])?><small><?=htmlspecialchars($row["author"])?> &middot; <?=number_format($row["price"], 2)?> ฿</small></span>
    </a>
  </li>
<?php   endforeach; ?>
</ul>
<?php endif; ?>
