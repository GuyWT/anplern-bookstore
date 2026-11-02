<?php
// เชื่อมต่อฐานข้อมูลด้วย PDO (รูปแบบเดียวกับ Lecture 7-8) และเริ่มใช้ Session (Lecture 9)
// *** แก้ dbname / username / password ให้ตรงกับเครื่องที่ใช้ ***
//   XAMPP ในเครื่อง : host=localhost, user=root, password=""
//   Host 193        : ใช้ชื่อฐานข้อมูลและรหัสผ่านของบัญชี csuser ตัวเอง
//   Docker          : docker-compose.yml กำหนด DB_HOST=db ให้อัตโนมัติ
$db_host = getenv("DB_HOST") ? getenv("DB_HOST") : "localhost";
$pdo = new PDO("mysql:host=$db_host;dbname=bookshop;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Initialize a session object: ทุกหน้าเรียก session_start() ผ่านไฟล์นี้
// (cookie ของ session อ่านด้วย JavaScript ไม่ได้ และส่งเฉพาะเว็บเดียวกัน)
ini_set("session.cookie_httponly", 1);
ini_set("session.cookie_samesite", "Lax");
session_start();

include "functions.php";
?>
