<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$categories = [
    "Dota 2", "Counter-Strike", "World of Warcraft", "Fortnite", "League of Legends",
    "Minecraft", "Overwatch", "PUBG", "Apex Legends", "Valorant",
    "Rocket League", "Rainbow Six Siege", "FIFA", "GTA V", "Call of Duty",
    "Among Us", "Fall Guys", "Roblox", "Genshin Impact", "Cyberpunk 2077",
    "Другое"
];

$error_message = "";
$success_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $original_price = floatval($_POST["price"]);
    $price_with_commission = $original_price * 1.04; // 4% комиссия
    $category = $_POST["category"];
    $seller_id = $_SESSION["id"];

    $target_dir = "uploads/items/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . uniqid() . '_' . basename($_FILES["image"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

    // Проверка изображения
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if($check !== false) {
        $uploadOk = 1;
    } else {
        $error_message = "Файл не является изображением.";
        $uploadOk = 0;
    }

    // Проверка размера файла
    if ($_FILES["image"]["size"] > 5000000) {
        $error_message = "Извините, ваш файл слишком большой.";
        $uploadOk = 0;
    }

    // Разрешенные форматы файлов
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        $error_message = "Извините, разрешены только JPG, JPEG, PNG & GIF файлы.";
        $uploadOk = 0;
    }

    // Загрузка файла
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO items (name, description, price, original_price, image, seller_id, category) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ssddsis", $name, $description, $price_with_commission, $original_price, $target_file, $seller_id, $category);
                if(mysqli_stmt_execute($stmt)){
                    $success_message = "Товар успешно добавлен.";
                } else{
                    $error_message = "Ошибка при добавлении товара в базу данных: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Ошибка подготовки запроса: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Извините, произошла ошибка при загрузке вашего файла.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить товар</title>
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
            <section class="add-item-section">
                <h1>Добавить новый товар</h1>
                <?php
                if (!empty($error_message)) {
                    echo "<p class='error-message'>$error_message</p>";
                }
                if (!empty($success_message)) {
                    echo "<p class='success-message'>$success_message</p>";
                }
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="add-item-form">
                    <div class="form-group">
                        <label for="name">Название товара</label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea name="description" id="description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="price">Цена (без комиссии)</label>
                        <input type="number" name="price" id="price" step="0.01" required>
                        <p class="price-info">К указанной цене будет добавлена комиссия сервиса 4%</p>
                    </div>
                    <div class="form-group">
                        <label for="category">Категория</label>
                        <select name="category" id="category" required>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group file-input">
                        <input type="file" name="image" id="image" required>
                        <label for="image" class="btn"><i class="fas fa-upload"></i> Выбрать изображение</label>
                        <span class="file-name">Файл не выбран</span>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn"><i class="fas fa-plus-circle"></i> Добавить товар</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Маркетплейс. Все права защищены.</p>
        </div>
    </footer>

    <script>
        document.getElementById('image').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.querySelector('.file-name').textContent = fileName;
        });

        document.getElementById('price').addEventListener('input', function(e) {
            var originalPrice = parseFloat(e.target.value);
            if (!isNaN(originalPrice)) {
                var priceWithCommission = originalPrice * 1.04;
                document.querySelector('.price-info').textContent = 
                    `К указанной цене будет добавлена комиссия сервиса 4%. Итоговая цена: ${priceWithCommission.toFixed(2)} руб.`;
            }
        });
    </script>
</body>
</html>