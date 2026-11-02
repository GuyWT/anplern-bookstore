<?php include "connect.php"; ?>
<?php
require_admin();
$promo_id = (int)($_GET["promo_id"] ?? 0);

// แก้ไข: ดึงข้อมูลเดิม / สร้างใหม่: ค่าเริ่มต้น
$row = ["promo_name" => "", "promo_type" => "seasonal", "discount_percent" => "", "min_spend" => "",
        "category_id" => "", "min_days_in_stock" => "", "max_stock" => "",
        "start_date" => date("Y-m-d"), "end_date" => date("Y-m-d", strtotime("+30 days")), "is_active" => 1];
if ($promo_id) {
    $stmt = $pdo->prepare("SELECT * FROM promotion WHERE promo_id = ?");
    $stmt->bindParam(1, $promo_id);
    $stmt->execute();
    $row = $stmt->fetch();
}
$title = $promo_id ? "แก้ไขโปรโมชั่น" : "สร้างโปรโมชั่นใหม่";
include "header.php";
if (!$row) {
    echo '<p class="msg error">ไม่พบโปรโมชั่นนี้</p>';
    include "footer.php";
    exit;
}
$cats = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_id");
$cats->execute();
?>
<script>
  // แสดงเฉพาะช่องเงื่อนไขของประเภทโปรโมชั่นที่เลือก
  function showType() {
    var type = document.getElementById("promo_type").value;
    var groups = { tier: ["min_spend"], seasonal: [], clearance: ["min_days_in_stock", "max_stock"] };
    document.getElementById("field-tier").style.display = type == "tier" ? "block" : "none";
    document.getElementById("field-seasonal").style.display = type == "seasonal" ? "block" : "none";
    document.getElementById("field-clearance").style.display = type == "clearance" ? "block" : "none";
    ["min_spend", "min_days_in_stock", "max_stock"].forEach(function (id) {
      document.getElementById(id).required = groups[type].indexOf(id) >= 0;
    });
  }
  // วันสิ้นสุดต้องไม่ก่อนวันเริ่ม
  function checkDates() {
    var end = document.getElementById("end_date");
    end.min = document.getElementById("start_date").value;
    end.setCustomValidity(end.value < end.min ? "วันสิ้นสุดต้องไม่ก่อนวันเริ่ม" : "");
  }
  window.addEventListener("load", function () { showType(); checkDates(); });
</script>

<form class="edit" action="promotion-save.php" method="post">
  <?=csrf_field()?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="promo_id" value="<?=$promo_id?>">

  <label for="promo_name">ชื่อโปรโมชั่น</label>
  <input type="text" id="promo_name" name="promo_name" required maxlength="150" value="<?=htmlspecialchars($row["promo_name"])?>"
         placeholder="เช่น 11.11 ลดทั้งร้าน 11%">

  <label for="promo_type">ประเภท</label>
  <select id="promo_type" name="promo_type" onchange="showType()">
    <option value="tier"<?=$row["promo_type"] == "tier" ? " selected" : ""?>>ซื้อครบยอด ลดทั้งตะกร้า (ขั้นบันได)</option>
    <option value="seasonal"<?=$row["promo_type"] == "seasonal" ? " selected" : ""?>>เทศกาล (ลดเฉพาะหมวด หรือทุกหมวด)</option>
    <option value="clearance"<?=$row["promo_type"] == "clearance" ? " selected" : ""?>>ล้างสต็อก (อยู่ในคลังนาน และเหลือน้อย)</option>
  </select>

  <div id="field-tier">
    <label for="min_spend">ยอดซื้อขั้นต่ำ (บาท)</label>
    <input type="number" id="min_spend" name="min_spend" min="1" step="1" value="<?=blank_num($row["min_spend"])?>">
  </div>
  <div id="field-seasonal">
    <label for="category_id">ใช้กับหมวด</label>
    <select id="category_id" name="category_id">
      <option value="">ทุกหมวด</option>
<?php while ($c = $cats->fetch()) : ?>
      <option value="<?=$c["category_id"]?>"<?=$c["category_id"] == $row["category_id"] ? " selected" : ""?>><?=$c["category_name"]?></option>
<?php endwhile; ?>
    </select>
  </div>
  <div id="field-clearance" class="row-2">
    <div>
      <label for="min_days_in_stock">อยู่ในคลังอย่างน้อย (วัน)</label>
      <input type="number" id="min_days_in_stock" name="min_days_in_stock" min="1" step="1" value="<?=$row["min_days_in_stock"]?>">
    </div>
    <div>
      <label for="max_stock">เหลือไม่เกิน (เล่ม)</label>
      <input type="number" id="max_stock" name="max_stock" min="0" step="1" value="<?=$row["max_stock"]?>">
    </div>
  </div>

  <label for="discount_percent">ส่วนลด (%)</label>
  <input type="number" id="discount_percent" name="discount_percent" required min="1" max="90" step="0.01" value="<?=blank_num($row["discount_percent"])?>">

  <div class="row-2">
    <div>
      <label for="start_date">วันเริ่ม</label>
      <input type="date" id="start_date" name="start_date" required value="<?=$row["start_date"]?>" onchange="checkDates()">
    </div>
    <div>
      <label for="end_date">วันสิ้นสุด</label>
      <input type="date" id="end_date" name="end_date" required value="<?=$row["end_date"]?>" onchange="checkDates()">
    </div>
  </div>

  <label class="check"><input type="checkbox" name="is_active" value="1"<?=$row["is_active"] ? " checked" : ""?>> เปิดใช้งาน</label>

  <p>
    <input type="submit" value="บันทึกโปรโมชั่น">
    <a class="btn secondary" href="promotions.php">ยกเลิก</a>
  </p>
</form>
<?php include "footer.php"; ?>
