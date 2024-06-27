<?php
session_start();
require_once "config.php";
require_once "check_ban.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$message = "";

function updateAvatar($conn, $user_id) {
    $target_dir = "uploads/avatars/";
    $target_file = $target_dir . basename($_FILES["avatar"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if ($check === false) {
        return "Файл не является изображением.";
    }

    if ($_FILES["avatar"]["size"] > 900000) {
        return "Извините, ваш файл слишком большой.";
    }

    if (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
        return "Извините, разрешены только JPG, JPEG, PNG & GIF файлы.";
    }

    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->bind_param("si", $target_file, $user_id);
        $stmt->execute();
        $stmt->close();
        return "Аватар успешно обновлен.";
    } else {
        return "Извините, произошла ошибка при загрузке вашего файла.";
    }
}

function changePassword($conn, $user_id, $new_password, $confirm_password) {
    if ($new_password !== $confirm_password) {
        return "Пароли не совпадают.";
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();
    $stmt->close();
    return "Пароль успешно изменен.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["update_avatar"])) {
        $message = updateAvatar($conn, $user_id);
    } elseif (isset($_POST["change_password"])) {
        $message = changePassword($conn, $user_id, $_POST["new_password"], $_POST["confirm_password"]);
    }
}

$stmt = $conn->prepare("SELECT username, avatar, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $avatar, $balance);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .balance-display {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
            font-size: 16px;
            color: #fff;
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
                    <li><a href="seller_profile.php">Профиль</a></li>
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="balance-display">
        Баланс: <?php echo number_format($balance, 2); ?> руб.
    </div>

    <main>
        <div class="container">
            <h1>Профиль пользователя</h1>
            <?php
            if (!empty($message)) {
                echo "<p class='message'>" . htmlspecialchars($message) . "</p>";
            }
            ?>
            <div class="profile-container">
                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($username); ?>" class="avatar">
                <h2><?php echo htmlspecialchars($username); ?></h2>
                <p>Баланс: <?php echo number_format($balance, 2); ?> руб.</p>
                <a href="add_balance.php" class="btn">Пополнить баланс</a>

                <h3>Изменить аватар</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group file-input">
                        <input type="file" name="avatar" id="avatar" required>
                        <label for="avatar" class="btn">Выбрать файл</label>
                        <span class="file-name">Файл не выбран</span>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="update_avatar" class="btn">Обновить аватар</button>
                    </div>
                </form>

                <h3>Изменить пароль</h3>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="new_password">Новый пароль</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите новый пароль</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn">Изменить пароль</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('avatar').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.querySelector('.file-name').textContent = fileName;
        });
    </script>
</body>
</html>