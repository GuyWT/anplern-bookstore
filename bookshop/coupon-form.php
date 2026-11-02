<?php include "connect.php"; ?>
<?php
require_admin();
$code = $_GET["code"] ?? "";

$row = ["coupon_code" => "", "description" => "", "discount_type" => "fixed", "discount_value" => "",
        "max_discount" => "", "min_spend" => "0", "usage_limit" => "",
        "start_date" => date("Y-m-d"), "end_date" => date("Y-m-d", strtotime("+30 days"))];
if ($code != "") {
    $stmt = $pdo->prepare("SELECT * FROM coupon WHERE coupon_code = ?");
    $stmt->bindParam(1, $code);
    $stmt->execute();
    $row = $stmt->fetch();
}
$title = $code != "" ? "แก้ไขคูปอง" : "สร้างคูปองใหม่";
include "header.php";
if (!$row) {
    echo '<p class="msg error">ไม่พบคูปองนี้</p>';
    include "footer.php";
    exit;
}
?>
<script>
  // คูปองแบบเปอร์เซ็นต์ แสดงช่องเพดานส่วนลด / แบบเงินสด ซ่อนช่องนี้
  function showDiscountType() {
    var percent = document.getElementById("discount_type").value == "percent";
    document.getElementById("field-max").style.display = percent ? "block" : "none";
    document.getElementById("unit").innerHTML = percent ? "%" : "บาท";
    document.getElementById("discount_value").max = percent ? 90 : 99999;
  }
  window.addEventListener("load", showDiscountType);
</script>

<form class="edit" action="coupon-save.php" method="post">
  <?=csrf_field()?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="is_new" value="<?=$code == "" ? 1 : 0?>">

  <label for="coupon_code">รหัสคูปอง</label>
  <input type="text" id="coupon_code" name="coupon_code" required value="<?=htmlspecialchars($row["coupon_code"])?>"
         pattern="[A-Z0-9]{3,20}" title="ตัวพิมพ์ใหญ่ภาษาอังกฤษหรือตัวเลข 3-20 ตัว" placeholder="เช่น NEWYEAR2027"
         oninput="this.value = this.value.toUpperCase()"<?=$code != "" ? " readonly" : ""?>>

  <label for="description">รายละเอียด</label>
  <input type="text" id="description" name="description" required maxlength="150" value="<?=htmlspecialchars($row["description"])?>">

  <div class="row-2">
    <div>
      <label for="discount_type">ประเภทส่วนลด</label>
      <select id="discount_type" name="discount_type" onchange="showDiscountType()">
        <option value="fixed"<?=$row["discount_type"] == "fixed" ? " selected" : ""?>>ลดเป็นเงินสด (บาท)</option>
        <option value="percent"<?=$row["discount_type"] == "percent" ? " selected" : ""?>>ลดเป็นเปอร์เซ็นต์</option>
      </select>
    </div>
    <div>
      <label for="discount_value">ส่วนลด (<span id="unit">บาท</span>)</label>
      <input type="number" id="discount_value" name="discount_value" required min="1" step="0.01" value="<?=blank_num($row["discount_value"])?>">
    </div>
  </div>
  <div id="field-max">
    <label for="max_discount">ลดสูงสุดไม่เกิน (บาท) <small>(เว้นว่าง = ไม่จำกัด)</small></label>
    <input type="number" id="max_discount" name="max_discount" min="1" step="0.01" value="<?=blank_num($row["max_discount"])?>">
  </div>

  <div class="row-2">
    <div>
      <label for="min_spend">ยอดซื้อขั้นต่ำ (บาท)</label>
      <input type="number" id="min_spend" name="min_spend" required min="0" step="0.01" value="<?=blank_num($row["min_spend"])?>">
    </div>
    <div>
      <label for="usage_limit">จำนวนสิทธิ์ทั้งหมด</label>
      <input type="number" id="usage_limit" name="usage_limit" required min="1" step="1" value="<?=$row["usage_limit"]?>">
    </div>
  </div>

  <div class="row-2">
    <div>
      <label for="start_date">ใช้ได้ตั้งแต่</label>
      <input type="date" id="start_date" name="start_date" required value="<?=$row["start_date"]?>">
    </div>
    <div>
      <label for="end_date">ถึงวันที่</label>
      <input type="date" id="end_date" name="end_date" required value="<?=$row["end_date"]?>">
    </div>
  </div>

  <p>
    <input type="submit" value="บันทึกคูปอง">
    <a class="btn secondary" href="coupons.php">ยกเลิก</a>
  </p>
</form>
<?php include "footer.php"; ?>
