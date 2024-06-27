<?php
require_once "config.php";

$username = $password = $confirm_password = "";
$username_err = $password_err = $confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Валидация имени пользователя
    $username = trim($_POST["username"]);
    if (empty($username)) {
        $username_err = "Пожалуйста, введите имя пользователя.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $username_err = "Имя пользователя может содержать только буквы, цифры и знак подчеркивания.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows == 1) {
            $username_err = "Это имя пользователя уже занято.";
        }
        $stmt->close();
    }
    
    // Валидация пароля
    $password = trim($_POST["password"]);
    if (empty($password)) {
        $password_err = "Пожалуйста, введите пароль.";     
    } elseif (strlen($password) < 6) {
        $password_err = "Пароль должен содержать не менее 6 символов.";
    }
    
    // Валидация подтверждения пароля
    $confirm_password = trim($_POST["confirm_password"]);
    if (empty($confirm_password)) {
        $confirm_password_err = "Пожалуйста, подтвердите пароль.";     
    } elseif ($password != $confirm_password) {
        $confirm_password_err = "Пароли не совпадают.";
    }
    
    // Если нет ошибок, добавляем пользователя в базу данных
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, password_hash($password, PASSWORD_DEFAULT));
        
        if ($stmt->execute()) {
            header("location: login.php");
            exit();
        } else {
            echo "Упс! Что-то пошло не так. Попробуйте позже.";
        }
        $stmt->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Регистрация</h2>
        <p>Пожалуйста, заполните эту форму для создания аккаунта.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Имя пользователя</label>
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>    
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label>Подтвердите пароль</label>
                <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Зарегистрироваться">
                <input type="reset" class="btn btn-secondary ml-2" value="Сбросить">
            </div>
            <p>Уже есть аккаунт? <a href="login.php">Войдите здесь</a>.</p>
        </form>
    </div>    
</body>
</html>