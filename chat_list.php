<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['role'], ['admin', 'moderator', 'support'])){
    header("location: login.php");
    exit;
}

// Пагинация
$items_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Получаем общее количество чатов
$total_chats_sql = "SELECT COUNT(DISTINCT item_id) as total FROM messages";
$total_result = mysqli_query($conn, $total_chats_sql);
$total_chats = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_chats / $items_per_page);

// Получаем список чатов с пагинацией
$sql = "SELECT DISTINCT m.item_id, i.name as item_name, i.image as item_image,
        (SELECT message FROM messages WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE item_id = i.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT username FROM users WHERE id = i.seller_id) as seller_name
        FROM messages m
        JOIN items i ON m.item_id = i.id 
        ORDER BY last_message_time DESC
        LIMIT ? OFFSET ?";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "ii", $items_per_page, $offset);
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
    <title>Список чатов</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .chat-list-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .chat-list-title {
            font-size: 2.5em;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(76, 0, 255, 0.6);
        }
        .chat-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .chat-item {
            background-color: rgba(25, 25, 40, 0.8);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(76, 0, 255, 0.3);
            display: flex;
            flex-direction: column;
        }
        .chat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(76, 0, 255, 0.2);
        }
        .chat-item-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .chat-item-content {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-item-title {
            font-size: 1.2em;
            color: #00aaff;
            margin-bottom: 10px;
        }
        .chat-item-info {
            font-size: 0.9em;
            color: #bbb;
            margin-bottom: 10px;
        }
        .chat-item-message {
            font-style: italic;
            color: #ddd;
            margin-bottom: 10px;
            flex-grow: 1;
        }
        .chat-item-time {
            font-size: 0.8em;
            color: #888;
            text-align: right;
        }
        .chat-item-link {
            display: block;
            background: linear-gradient(45deg, #ff00ea, #00aaff);
            color: #fff;
            text-align: center;
            padding: 10px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        .chat-item-link:hover {
            background: linear-gradient(45deg, #00aaff, #ff00ea);
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        .pagination a {
            color: #fff;
            background-color: rgba(76, 0, 255, 0.6);
            padding: 10px 15px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .pagination a:hover, .pagination a.active {
            background-color: rgba(255, 0, 221, 0.6);
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
                    <li><a href="chat_list.php">Список чатов</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="chat-list-container">
            <h1 class="chat-list-title">Список чатов</h1>
            <div class="chat-list">
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <div class="chat-item">
                        <img src="<?php echo htmlspecialchars($row['item_image']); ?>" alt="<?php echo htmlspecialchars($row['item_name']); ?>" class="chat-item-image">
                        <div class="chat-item-content">
                            <h3 class="chat-item-title"><?php echo htmlspecialchars($row['item_name']); ?></h3>
                            <p class="chat-item-info">Продавец: <?php echo htmlspecialchars($row['seller_name']); ?></p>
                            <p class="chat-item-message"><?php echo htmlspecialchars(substr($row['last_message'], 0, 50)) . '...'; ?></p>
                            <p class="chat-item-time"><?php echo date('d.m.Y H:i', strtotime($row['last_message_time'])); ?></p>
                            <a href="item.php?id=<?php echo $row['item_id']; ?>" class="chat-item-link">Перейти к чату</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" <?php echo $page == $i ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>
</body>
</html>