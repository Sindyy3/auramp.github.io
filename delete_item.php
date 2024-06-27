<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("location: index.php");
    exit;
}

$item_id = $_GET['id'];
$user_id = $_SESSION['id'];

// Проверяем, имеет ли пользователь право удалить товар
$sql = "SELECT i.*, u.role FROM items i JOIN users u ON i.seller_id = u.id WHERE i.id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);

    if(!$item){
        header("location: index.php");
        exit;
    }

    $can_delete = ($item['seller_id'] == $user_id) || in_array($_SESSION['role'], ['admin', 'moderator']);

    if(!$can_delete){
        header("location: index.php");
        exit;
    }
} else {
    echo "Ошибка: Не удалось получить информацию о товаре.";
    exit;
}

// Если у пользователя есть права на удаление, выполняем удаление
if($can_delete){
    // Начинаем транзакцию
    mysqli_begin_transaction($conn);

    try {
        // Удаляем связанные сообщения
        $delete_messages_sql = "DELETE FROM messages WHERE item_id = ?";
        if($delete_messages_stmt = mysqli_prepare($conn, $delete_messages_sql)){
            mysqli_stmt_bind_param($delete_messages_stmt, "i", $item_id);
            mysqli_stmt_execute($delete_messages_stmt);
            mysqli_stmt_close($delete_messages_stmt);
        } else {
            throw new Exception("Ошибка при удалении сообщений");
        }

        // Удаляем товар
        $delete_item_sql = "DELETE FROM items WHERE id = ?";
        if($delete_item_stmt = mysqli_prepare($conn, $delete_item_sql)){
            mysqli_stmt_bind_param($delete_item_stmt, "i", $item_id);
            mysqli_stmt_execute($delete_item_stmt);
            mysqli_stmt_close($delete_item_stmt);
        } else {
            throw new Exception("Ошибка при удалении товара");
        }

        // Если все операции прошли успешно, подтверждаем транзакцию
        mysqli_commit($conn);

        // Удаляем файл изображения товара, если он существует
        if(!empty($item['image']) && file_exists($item['image'])){
            unlink($item['image']);
        }

        // Перенаправляем на страницу профиля продавца
        header("location: seller_profile.php?id=" . $item['seller_id']);
        exit;

    } catch (Exception $e) {
        // Если произошла ошибка, отменяем все изменения
        mysqli_rollback($conn);
        echo "Произошла ошибка при удалении товара: " . $e->getMessage();
    }
} else {
    echo "У вас нет прав на удаление этого товара.";
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Удаление товара</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="add_item.php">Добавить товар</a></li>
                    <li><a href="profile.php">Профиль</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>Удаление товара</h1>
            <p>Товар успешно удален.</p>
            <a href="seller_profile.php?id=<?php echo $item['seller_id']; ?>" class="btn">Вернуться к профилю продавца</a>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>
</body>
</html>