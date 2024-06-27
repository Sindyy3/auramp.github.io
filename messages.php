<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];

// Получаем список активных чатов для текущего пользователя
$sql = "SELECT DISTINCT m.item_id, i.name as item_name, i.image as item_image,
        (SELECT message FROM messages WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT username FROM users WHERE id = CASE WHEN i.seller_id = ? THEN m.sender_id ELSE i.seller_id END) as other_user
        FROM messages m
        JOIN items i ON m.item_id = i.id 
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY last_message_time DESC";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    echo "Ошибка: " . mysqli_error($conn);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои сообщения</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .messages-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .messages-title {
            font-size: 2em;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(76, 0, 255, 0.6);
        }
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .chat-item {
            background-color: rgba(25, 25, 40, 0.8);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(76, 0, 255, 0.3);
            display: flex;
            align-items: center;
        }
        .chat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(76, 0, 255, 0.2);
        }
        .chat-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        .chat-item-content {
            padding: 15px;
            flex-grow: 1;
        }
        .chat-item-title {
            font-size: 1.2em;
            color: #00aaff;
            margin-bottom: 5px;
        }
        .chat-item-info {
            font-size: 0.9em;
            color: #bbb;
            margin-bottom: 5px;
        }
        .chat-item-message {
            font-style: italic;
            color: #ddd;
        }
        .chat-item-time {
            font-size: 0.8em;
            color: #888;
            text-align: right;
        }
        .chat-item-link {
            background: linear-gradient(45deg, #ff00ea, #00aaff);
            color: #fff;
            text-align: center;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .chat-item-link:hover {
            background: linear-gradient(45deg, #00aaff, #ff00ea);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="add_item.php">Добавить товар</a></li>
                    <li><a href="profile.php">Профиль</a></li>
                    <li><a href="messages.php">Сообщения</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="messages-container">
            <h1 class="messages-title">Мои сообщения</h1>
            <div class="chat-list">
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <div class="chat-item">
                        <img src="<?php echo htmlspecialchars($row['item_image']); ?>" alt="<?php echo htmlspecialchars($row['item_name']); ?>" class="chat-item-image">
                        <div class="chat-item-content">
                            <h3 class="chat-item-title"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                            <p class="chat-item-info">Собеседник: <?php echo htmlspecialchars($row['other_user']); ?></p>
                            <p class="chat-item-message"><?php echo htmlspecialchars(substr($row['last_message'], 0, 50)) . '...'; ?></p>
                            <p class="chat-item-time"><?php echo date('d.m.Y H:i', strtotime($row['last_message_time'])); ?></p>
                        </div>
                        <a href="item.php?id=<?php echo $row['item_id']; ?>" class="chat-item-link">Открыть чат</a>
                    </div>
                <?php endwhile; ?>
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