<?php
session_start();
require_once "config.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$item_id = $_GET['id'];
$user_id = $_SESSION['id'];

// Проверяем, имеет ли пользователь право редактировать товар
$sql = "SELECT * FROM items WHERE id = ? AND (seller_id = ? OR ? IN (SELECT id FROM users WHERE role IN ('admin', 'moderator')))";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "iii", $item_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    if(!$item){
        header("location: seller_profile.php?id=" . $item['seller_id']);
        exit;
    }
} else {
    echo "Ошибка: Не удалось получить информацию о товаре.";
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $new_price = floatval($_POST["new_price"]);
    $old_price = floatval($_POST["old_price"]);
    $category = $_POST["category"];

    $error = "";

    // Проверяем, что старая цена не больше изначальной цены
    if ($old_price > $item['original_price']) {
        $error = "Старая цена не может быть больше изначальной цены товара.";
    }
    // Проверяем, что новая цена меньше старой
    elseif ($new_price >= $old_price) {
        $error = "Новая цена должна быть меньше старой цены.";
    }

    if (empty($error)) {
        // Если старая цена не изменилась, используем текущую old_price из базы данных
        $current_old_price = $item['old_price'] ?: $item['original_price'];
        
        // Обновляем информацию о товаре
        $update_sql = "UPDATE items SET name = ?, description = ?, price = ?, old_price = ?, category = ? WHERE id = ?";
        if($update_stmt = mysqli_prepare($conn, $update_sql)){
            mysqli_stmt_bind_param($update_stmt, "ssddsi", $name, $description, $new_price, $current_old_price, $category, $item_id);
            if(mysqli_stmt_execute($update_stmt)){
                header("location: seller_profile.php?id=" . $item['seller_id']);
                exit;
            } else {
                $error = "Ошибка при обновлении товара.";
            }
        }
    }
}

$categories = [
    "Dota 2", "Counter-Strike", "World of Warcraft", "Fortnite", "League of Legends",
    "Minecraft", "Overwatch", "PUBG", "Apex Legends", "Valorant",
    "Rocket League", "Rainbow Six Siege", "FIFA", "GTA V", "Call of Duty",
    "Among Us", "Fall Guys", "Roblox", "Genshin Impact", "Cyberpunk 2077",
    "Другое"
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование товара</title>
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
            <h1>Редактирование товара</h1>
            <?php if(!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $item_id; ?>" method="post" class="edit-item-form">
                <div class="form-group">
                    <label for="name">Название товара</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea name="description" id="description" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="old_price">Старая цена</label>
                    <input type="number" name="old_price" id="old_price" step="0.01" value="<?php echo htmlspecialchars($item['old_price'] ?: $item['original_price']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="new_price">Новая цена (со скидкой)</label>
                    <input type="number" name="new_price" id="new_price" step="0.01" value="<?php echo htmlspecialchars($item['price']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="category">Категория</label>
                    <select name="category" id="category" required>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category == $item['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>
</body>
</html>