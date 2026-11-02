<?php include "connect.php"; ?>
<?php
// Terminate a session: ลบ session id ในคุกกี้เครื่องผู้ใช้ แล้วทำลาย session (Lecture 9 สไลด์ 26)
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);
session_destroy();

header("location: login.php?msg=logout");
?>
