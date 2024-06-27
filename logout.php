<?php
// logout.php
session_start();

// Удаление всех переменных сессии
$_SESSION = array();

// Уничтожение сессии
session_destroy();

// Перенаправление на страницу входа
header("location: login.php");
exit;
?>