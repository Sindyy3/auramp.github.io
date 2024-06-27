<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$item_id = $_GET['id'];
$user_id = $_SESSION['id'];

$sql = "SELECT items.*, users.username, users.avatar, users.id AS seller_id, users.role FROM items JOIN users ON items.seller_id = users.id WHERE items.id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'])){
    $message = trim($_POST['message']);
    $sql = "INSERT INTO messages (item_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)";
    if($stmt = mysqli_prepare($conn, $sql)){
        $receiver_id = ($user_id == $item['seller_id']) ? $user_id : $item['seller_id'];
        mysqli_stmt_bind_param($stmt, "iiis", $item_id, $user_id, $receiver_id, $message);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true]);
        exit;
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'get_messages'){
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    $sql = "SELECT messages.*, users.username, users.avatar, users.role FROM messages JOIN users ON messages.sender_id = users.id WHERE item_id = ? AND messages.id > ? ORDER BY created_at ASC";
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "ii", $item_id, $last_id);
        mysqli_stmt_execute($stmt);
        $messages_result = mysqli_stmt_get_result($stmt);
        $new_messages = [];
        while($message = mysqli_fetch_assoc($messages_result)){
            $new_messages[] = $message;
        }
        echo json_encode($new_messages);
        exit;
    }
}

$sql = "SELECT messages.*, users.username, users.avatar, users.role FROM messages JOIN users ON messages.sender_id = users.id WHERE item_id = ? ORDER BY created_at ASC";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $messages_result = mysqli_stmt_get_result($stmt);
}

// Проверяем, имеет ли пользователь право на просмотр чата
$can_view_chat = ($user_id == $item['seller_id'] || $user_id == $item['buyer_id'] || in_array($_SESSION['role'], ['admin', 'moderator', 'support']));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['name']); ?></title>
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
                    <li><a href="seller_profile.php">Профиль</a></li>
                    <li><a href="messages.php">Сообщения</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="item-details">
                <div class="item-image">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                </div>
                <div class="item-info">
                    <h1><?php echo htmlspecialchars($item['name']); ?></h1>
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
                    <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="seller-info">
                        <img src="<?php echo htmlspecialchars($item['avatar']); ?>" alt="<?php echo htmlspecialchars($item['username']); ?>" class="avatar">
                        <span>Продавец: <a href="seller_profile.php?id=<?php echo $item['seller_id']; ?>"><?php echo htmlspecialchars($item['username']); ?></a></span>
                    </div>
                </div>
            </div>

            <div class="chat-container">
                <h2><i class="fas fa-comments"></i> Чат</h2>
                <div class="messages" id="messages">
                    <?php while($message = mysqli_fetch_assoc($messages_result)): ?>
                        <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>" data-id="<?php echo $message['id']; ?>">
                            <img src="<?php echo htmlspecialchars($message['avatar']); ?>" alt="<?php echo htmlspecialchars($message['username']); ?>" class="message-avatar">
                            <div class="message-content">
                                <p><?php echo htmlspecialchars($message['message']); ?></p>
                                <span>
                                    <?php echo htmlspecialchars($message['username']); ?>
                                    <?php if ($message['role'] != 'user'): ?>
                                        <span class="role-badge <?php echo $message['role']; ?>"><?php echo ucfirst($message['role']); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $item_id; ?>" method="post" class="message-form" id="message-form">
                    <textarea name="message" required placeholder="Введите сообщение..."></textarea>
                    <button type="submit" class="btn"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>

    <audio id="notification-sound" src="sms.mp3" preload="auto"></audio>

    <script>
        const messagesContainer = document.getElementById('messages');
        const messageForm = document.getElementById('message-form');
        const notificationSound = document.getElementById('notification-sound');
        let lastMessageId = 0;

        // Функция для прокрутки чата вниз
        function scrollChatToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Функция для добавления нового сообщения в чат
        function addMessageToChat(message) {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${message.sender_id == <?php echo $user_id; ?> ? 'sent' : 'received'}`;
            messageElement.dataset.id = message.id;
            messageElement.innerHTML = `
                <img src="${message.avatar}" alt="${message.username}" class="message-avatar">
                <div class="message-content">
                    <p>${message.message}</p>
                    <span>
                        ${message.username}
                        ${message.role != 'user' ? `<span class="role-badge ${message.role}">${message.role}</span>` : ''}
                    </span>
                </div>
            `;
            messagesContainer.appendChild(messageElement);
            scrollChatToBottom();
            lastMessageId = Math.max(lastMessageId, message.id);
        }

        // Функция для получения новых сообщений
        function getNewMessages() {
            fetch(`<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $item_id; ?>&action=get_messages&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(messages => {
                    if (messages.length > 0) {
                        messages.forEach(addMessageToChat);
                        notificationSound.play();
                    }
                });
        }

        // Отправка сообщения
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.reset();
                    getNewMessages();
                }
            });
        });

        // Автоматическое изменение высоты текстового поля
        const textarea = messageForm.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Инициализация
        scrollChatToBottom();
        const messages = document.querySelectorAll('.message');
        if (messages.length > 0) {
            lastMessageId = parseInt(messages[messages.length - 1].dataset.id);
        }

        // Периодическая проверка новых сообщений
        setInterval(getNewMessages, 5000); // Проверка каждые 5 секунд
    </script>
</body>
</html>