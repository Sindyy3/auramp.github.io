<?php
session_start();
require_once "config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Получаем актуальную информацию о пользователе, включая аватарку и баланс
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION["id"];
    $stmt = $conn->prepare("SELECT avatar, balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_avatar, $user_balance);
    $stmt->fetch();
    $stmt->close();
}

$categories = [
    "Все категории", "Dota 2", "Counter-Strike", "World of Warcraft", "Fortnite", "League of Legends",
    "Minecraft", "Overwatch", "PUBG", "Apex Legends", "Valorant",
    "Rocket League", "Rainbow Six Siege", "FIFA", "GTA V", "Call of Duty",
    "Among Us", "Fall Guys", "Roblox", "Genshin Impact", "Cyberpunk 2077",
    "Другое"
];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'Все категории';

$sql = "SELECT i.*, u.username, u.avatar, u.rating FROM items i 
        JOIN users u ON i.seller_id = u.id 
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND i.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($category !== 'Все категории') {
    $sql .= " AND i.category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aura - Главная страница</title>
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
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li><a href="add_item.php">Добавить товар</a></li>
                        <li><a href="seller_profile.php">Профиль</a></li>
                        <li class="user-menu">
                            <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="<?php echo htmlspecialchars($_SESSION['username']); ?>" class="avatar-small">
                            <div class="dropdown-content">
                                <a href="#" class="balance">Баланс: <?php echo number_format($user_balance, 2); ?> ₽</a>
                                <a href="messages.php">Сообщения</a>
                                <a href="rules.php">Правила</a>
                                <a href="support.php">Техподдержка</a>
                                <a href="profile.php">Настройки</a>
                                <a href="logout.php">Выйти</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Войти</a></li>
                        <li><a href="register.php">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <section class="hero-section">
                <h1>Добро пожаловать на AuraMarket</h1>
                <p>Найдите уникальные товары от продавцов со всего мира</p>
                <form action="index.php" method="GET" class="search-bar">
                    <input type="text" name="search" placeholder="Поиск товаров..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $cat === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </section>

            <section class="featured-items">
                <h2><?php echo empty($search) && $category === 'Все категории' ? "Популярные товары" : "Результаты поиска"; ?></h2>
                <div class="items-container">
                    <?php
                    if($result->num_rows > 0){
                        while($row = $result->fetch_assoc()){
                            ?>
                            <div class="item">
                                <div class="item-image">
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                </div>
                                <div class="item-content">
                                    <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                                    <div class="item-price-container">
                                        <?php if($row['old_price'] > $row['price']): ?>
                                            <span class="old-price"><?php echo number_format($row['old_price'], 2); ?> руб.</span>
                                            <?php
                                            $discount_percent = round(($row['old_price'] - $row['price']) / $row['old_price'] * 100);
                                            ?>
                                            <span class="discount-badge">-<?php echo $discount_percent; ?>%</span>
                                        <?php endif; ?>
                                        <span class="current-price"><?php echo number_format($row['price'], 2); ?> руб.</span>
                                    </div>
                                    <p class="item-category"><?php echo htmlspecialchars($row['category']); ?></p>
                                    <div class="seller-info">
                                        <img src="<?php echo htmlspecialchars($row['avatar']); ?>" alt="<?php echo htmlspecialchars($row['username']); ?>" class="avatar">
                                        <a href="<?php echo htmlspecialchars($row['username']); ?>" class="seller-name"><?php echo htmlspecialchars($row['username']); ?></a>
                                        <div class="rating">
                                            <i class="fas fa-star"></i>
                                            <span><?php echo number_format($row['rating'], 1); ?></span>
                                        </div>
                                    </div>
                                    <a href="item.php?id=<?php echo $row['id']; ?>" class="btn">Подробнее</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<p class='no-items'>Нет товаров для отображения.</p>";
                    }
                    ?>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        const categorySelect = document.querySelector('select[name="category"]');
        const itemsContainer = document.querySelector('.items-container');
        const items = itemsContainer.querySelectorAll('.item');

        function filterItems() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categorySelect.value;

            items.forEach(item => {
                const itemName = item.querySelector('h3').textContent.toLowerCase();
                const itemCategory = item.querySelector('.item-category').textContent;
                const matchesSearch = itemName.includes(searchTerm);
                const matchesCategory = selectedCategory === 'Все категории' || itemCategory === selectedCategory;

                item.style.display = matchesSearch && matchesCategory ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterItems);
        categorySelect.addEventListener('change', filterItems);

        const userMenu = document.querySelector('.user-menu');
        if (userMenu) {
            const dropdownContent = userMenu.querySelector('.dropdown-content');

            userMenu.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            document.addEventListener('click', function() {
                dropdownContent.style.display = 'none';
            });
        }
    });

    function checkBanStatus() {
        fetch('check_ban_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.banned) {
                    window.location.href = 'banned.php';
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Проверяем статус бана каждые 60 секунд
    setInterval(checkBanStatus, 60000);
    </script>
</body>
</html>