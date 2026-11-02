// เปิด/ปิดเมนูบนมือถือ (ดัดแปลงจาก mpage.js ของ Lab)
function toggle_visibility(id) {
  var e = document.getElementById(id);
  if (e.style.display == 'block')
    e.style.display = 'none';
  else
    e.style.display = 'block';
}

// เปลี่ยนขนาดจอ: จอกว้างแสดงเมนูด้านข้างเสมอ จอมือถือซ่อนเมนูไว้ก่อน
window.onresize = function () {
  var e = document.getElementById("menu");
  if (window.innerWidth >= 768)
    e.style.display = 'block';
  else
    e.style.display = 'none';
};
