<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Определяем, какой параметр используется для идентификации продавца
if (isset($_GET['id'])) {
    $seller_id = $_GET['id'];
    $sql = "SELECT id, username, avatar, rating, role, is_banned, ban_expires, ban_reason FROM users WHERE id = ?";
    $param_type = "i";
} elseif (isset($_GET['username'])) {
    $seller_username = $_GET['username'];
    $sql = "SELECT id, username, avatar, rating, role, is_banned, ban_expires, ban_reason FROM users WHERE username = ?";
    $param_type = "s";
} else {
    // Если параметр не указан, используем текущего пользователя
    $seller_username = $_SESSION['username'];
    $sql = "SELECT id, username, avatar, rating, role, is_banned, ban_expires, ban_reason FROM users WHERE username = ?";
    $param_type = "s";
}

// Получаем информацию о продавце
if($stmt = mysqli_prepare($conn, $sql)){
    if (isset($seller_id)) {
        mysqli_stmt_bind_param($stmt, $param_type, $seller_id);
    } else {
        mysqli_stmt_bind_param($stmt, $param_type, $seller_username);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $seller = mysqli_fetch_assoc($result);
    if(!$seller) {
        // Если продавец не найден, перенаправляем на главную страницу
        header("location: index.php");
        exit;
    }
} else {
    echo "Ошибка: Не удалось получить информацию о продавце.";
    exit;
}

$seller_id = $seller['id'];
$current_user_id = $_SESSION['id'];

// Получаем товары продавца
$sql = "SELECT * FROM items WHERE seller_id = ? ORDER BY created_at DESC";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $seller_id);
    mysqli_stmt_execute($stmt);
    $items_result = mysqli_stmt_get_result($stmt);
} else {
    echo "Ошибка: Не удалось получить товары продавца.";
    exit;
}

// Обработка бана/разбана пользователя
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'moderator'])) {
    if (isset($_POST['action']) && $_POST['action'] == 'ban') {
        $ban_reason = trim($_POST['ban_reason']);
        $ban_duration = intval($_POST['ban_duration']);
        $ban_expires = date('Y-m-d H:i:s', strtotime("+$ban_duration days"));

        // Начинаем транзакцию
        mysqli_begin_transaction($conn);

        try {
            // Баним пользователя
            $ban_sql = "UPDATE users SET is_banned = 1, ban_reason = ?, ban_expires = ? WHERE id = ?";
            if ($ban_stmt = mysqli_prepare($conn, $ban_sql)) {
                mysqli_stmt_bind_param($ban_stmt, "ssi", $ban_reason, $ban_expires, $seller_id);
                mysqli_stmt_execute($ban_stmt);
                mysqli_stmt_close($ban_stmt);
            } else {
                throw new Exception("Ошибка при блокировке пользователя");
            }

            // Получаем ID товаров пользователя
            $get_items_sql = "SELECT id FROM items WHERE seller_id = ?";
            if ($get_items_stmt = mysqli_prepare($conn, $get_items_sql)) {
                mysqli_stmt_bind_param($get_items_stmt, "i", $seller_id);
                mysqli_stmt_execute($get_items_stmt);
                $items_result = mysqli_stmt_get_result($get_items_stmt);
                $item_ids = [];
                while ($row = mysqli_fetch_assoc($items_result)) {
                    $item_ids[] = $row['id'];
                }
                mysqli_stmt_close($get_items_stmt);
            } else {
                throw new Exception("Ошибка при получении ID товаров пользователя");
            }

            // Удаляем связанные сообщения
            if (!empty($item_ids)) {
                $delete_messages_sql = "DELETE FROM messages WHERE item_id IN (" . implode(',', array_fill(0, count($item_ids), '?')) . ")";
                if ($delete_messages_stmt = mysqli_prepare($conn, $delete_messages_sql)) {
                    mysqli_stmt_bind_param($delete_messages_stmt, str_repeat('i', count($item_ids)), ...$item_ids);
                    mysqli_stmt_execute($delete_messages_stmt);
                    mysqli_stmt_close($delete_messages_stmt);
                } else {
                    throw new Exception("Ошибка при удалении сообщений");
                }
            }

            // Удаляем все товары пользователя
            $delete_items_sql = "DELETE FROM items WHERE seller_id = ?";
            if ($delete_items_stmt = mysqli_prepare($conn, $delete_items_sql)) {
                mysqli_stmt_bind_param($delete_items_stmt, "i", $seller_id);
                mysqli_stmt_execute($delete_items_stmt);
                mysqli_stmt_close($delete_items_stmt);
            } else {
                throw new Exception("Ошибка при удалении товаров пользователя");
            }

            // Если все операции прошли успешно, подтверждаем транзакцию
            mysqli_commit($conn);

            $seller['is_banned'] = 1;
            $seller['ban_expires'] = $ban_expires;
            $seller['ban_reason'] = $ban_reason;

            // Очищаем результат запроса товаров, так как они были удалены
            mysqli_free_result($items_result);
            $items_result = mysqli_query($conn, "SELECT * FROM items WHERE seller_id = $seller_id");

        } catch (Exception $e) {
            // Если произошла ошибка, отменяем все изменения
            mysqli_rollback($conn);
            echo "Произошла ошибка: " . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'unban') {
        $unban_sql = "UPDATE users SET is_banned = 0, ban_reason = NULL, ban_expires = NULL WHERE id = ?";
        if ($unban_stmt = mysqli_prepare($conn, $unban_sql)) {
            mysqli_stmt_bind_param($unban_stmt, "i", $seller_id);
            mysqli_stmt_execute($unban_stmt);
            mysqli_stmt_close($unban_stmt);
            $seller['is_banned'] = 0;
            $seller['ban_expires'] = null;
            $seller['ban_reason'] = null;
        } else {
            echo "Ошибка при разблокировке пользователя";
        }
    }
}

$can_edit = ($current_user_id == $seller_id) || in_array($_SESSION['role'], ['admin', 'moderator']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль продавца - <?php echo htmlspecialchars($seller['username']); ?></title>
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
                    <li><a href="<?php echo htmlspecialchars($_SESSION['username']); ?>">Профиль</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="seller-profile">
                <div class="profile-header">
                    <div class="avatar-container">
                        <img src="<?php echo htmlspecialchars($seller['avatar']); ?>" alt="<?php echo htmlspecialchars($seller['username']); ?>" class="avatar">
                    </div>
                    <div class="profile-info">
                        <h1>
                            <?php echo htmlspecialchars($seller['username']); ?>
                            <?php if (isset($seller['role']) && $seller['role'] != 'user'): ?>
                                <span class="role-badge <?php echo htmlspecialchars($seller['role']); ?>"><?php echo ucfirst(htmlspecialchars($seller['role'])); ?></span>
                            <?php endif; ?>
                        </h1>
                        <div class="star-rating">
                            <?php
                            $rating = $seller['rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <p>Рейтинг: <?php echo number_format($rating, 1); ?> из 5</p>
                        <?php if ($seller['is_banned']): ?>
                            <p class="ban-info">Пользователь заблокирован до <?php echo $seller['ban_expires']; ?></p>
                            <p class="ban-reason">Причина: <?php echo htmlspecialchars($seller['ban_reason']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'moderator']) && $seller_id != $_SESSION['id']): ?>
                    <?php if (!$seller['is_banned']): ?>
                        <div class="ban-form">
                            <h3>Заблокировать пользователя</h3>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?username=' . $seller['username']; ?>" method="post">
                                <input type="hidden" name="action" value="ban">
                                <div class="form-group">
                                    <label for="ban_reason">Причина блокировки:</label>
                                    <textarea name="ban_reason" id="ban_reason" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="ban_duration">Длительность бана (в днях):</label>
                                    <input type="number" name="ban_duration" id="ban_duration" min="1" required>
                                </div>
                                <button type="submit" class="btn btn-danger">Заблокировать</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="unban-form">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?username=' . $seller['username']; ?>" method="post">
                                <input type="hidden" name="action" value="unban">
                                <button type="submit" class="btn btn-success">Разблокировать пользователя</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <h2>Товары продавца</h2>
            <div class="items-container">
                <?php
                if(mysqli_num_rows($items_result) > 0){
                    while($item = mysqli_fetch_assoc($items_result)){
                        ?>
                        <div class="item">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="item-content">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <div class="item-price-container">
                                    <?php if($item['old_price'] > $item['price']): ?>
                                        <span class="old-price"><?php echo number_format($item['old_price'], 2); ?> руб.</span>
                                        <?php
                                        $discount_percent = round(($item['old_price'] - $item['price']) / $item['old_price'] * 100);
                                        ?>
                                        <span class="discount-badge">-<?php echo $discount_percent; ?>%</span>
                                    <?php endif; ?>
                                    <span class="current-price"><?php echo number_format($item['price'], 2); ?> руб.</span>
                                </div>
                                <a href="item.php?id=<?php echo $item['id']; ?>" class="btn">Подробнее</a>
                                <?php if ($can_edit): ?>
                                    <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-edit">Редактировать</a>
                                    <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-delete" onclick="return confirm('Вы уверены, что хотите удалить этот товар?');">Удалить</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    if ($seller['is_banned']) {
                        echo "<p>Товары этого продавца недоступны, так как пользователь заблокирован.</p>";
                    } else {
                        echo "<p>У этого продавца пока нет товаров.</p>";
                    }
                }
                ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>
</body>
</html>