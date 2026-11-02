// ฟังก์ชัน JavaScript ที่ใช้หลายหน้า

// ---------- Ajax: หยิบหนังสือใส่ตะกร้าโดยไม่ต้องโหลดหน้าใหม่ ----------
//   ส่ง POST ไป cart-action.php แล้วรับผลลัพธ์กลับมาเป็น JSON
//   เช่น {"ok":true,"count":3,"message":"เพิ่ม ... ลงตะกร้าแล้ว"}
function addToCart(book_id, qty) {
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
      var result = JSON.parse(xhr.responseText);
      if (result.ok) {
        var count = document.getElementById("cart-count");
        count.innerHTML = result.count;
        count.className = "count bump";            // เปลี่ยน style ให้ตัวเลขเด้ง
        setTimeout(function () { count.className = "count"; }, 600);
      }
      showToast(result.message, result.ok);
    }
  };
  xhr.open("POST", "cart-action.php");
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("action=add&ajax=1&book_id=" + book_id + "&qty=" + qty + "&token=" + TOKEN);
  return false;   // ไม่ให้ฟอร์มส่งข้อมูลแบบโหลดหน้าใหม่
}

// กล่องข้อความแจ้งเตือนมุมจอ: สร้าง element ใหม่ด้วย DOM แล้วลบทิ้งหลัง 3 วินาที
function showToast(text, ok) {
  var box = document.getElementById("toast");
  if (box == null) {
    box = document.createElement("div");
    box.id = "toast";
    document.body.appendChild(box);
  }
  var item = document.createElement("p");
  item.className = ok ? "ok" : "error";
  item.textContent = text;
  box.appendChild(item);
  setTimeout(function () { box.removeChild(item); }, 3000);
}
