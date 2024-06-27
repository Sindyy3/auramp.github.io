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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = floatval($_POST["amount"]);
    if ($amount >= 50) {
        $merchant_id = '52503';
$secret_word = 'Fleydi333';
        $order_id = time() . '_' . $user_id;
        $currency = 'RUB';
        $sign = md5($merchant_id . ':' . $amount . ':' . $secret_word . ':' . $currency . ':' . $order_id);

        $payment_url = "https://pay.freekassa.ru/?m={$merchant_id}&oa={$amount}&o={$order_id}&s={$sign}&currency={$currency}";
        header("Location: $payment_url");
        exit;
    } else {
        $message = "Минимальная сумма пополнения - 50 рублей.";
    }
}

// Получаем текущий баланс пользователя
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пополнение баланса</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .balance-container {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .balance-title {
            font-size: 18px;
            color: #bbb;
            margin-bottom: 10px;
        }
        .balance-amount {
            font-size: 36px;
            color: #00aaff;
            font-weight: bold;
        }
        .add-balance-form {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 30px;
            max-width: 400px;
            margin: 0 auto;
        }
        .form-title {
            font-size: 24px;
            color: #fff;
            margin-bottom: 20px;
            text-align: center;
        }
        .amount-input {
            display: flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }
        .amount-input input {
            flex-grow: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 18px;
            outline: none;
        }
        .amount-input span {
            color: #bbb;
            font-size: 18px;
            margin-left: 10px;
        }
        .submit-btn {
            background: linear-gradient(45deg, #00aaff, #2ecc71);
            border: none;
            color: #fff;
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        .submit-btn:hover {
            background: linear-gradient(45deg, #2ecc71, #00aaff);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        .message {
            text-align: center;
            color: #ff4757;
            margin-top: 20px;
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
                    <li><a href="logout.php">Выйти</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="balance-container">
                <div class="balance-title">Текущий баланс</div>
                <div class="balance-amount"><?php echo number_format($balance, 2); ?> ₽</div>
            </div>
            <div class="add-balance-form">
                <h2 class="form-title">Пополнение баланса</h2>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="amount-input">
                        <input type="number" name="amount" id="amount" min="50" step="1" required placeholder="Сумма пополнения">
                        <span>₽</span>
                    </div>
                    <button type="submit" class="submit-btn">Пополнить</button>
                </form>
                <?php
                if (!empty($message)) {
                    echo "<p class='message'>" . htmlspecialchars($message) . "</p>";
                }
                ?>
            </div>
        </div>
    </main>

    <script>
        const amountInput = document.getElementById('amount');
        amountInput.addEventListener('input', function() {
            if (this.value < 50) {
                this.setCustomValidity('Минимальная сумма пополнения - 50 рублей');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>