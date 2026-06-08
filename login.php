<?php
ob_start();
require_once 'config.php';

$error = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $debug .= "收到POST请求，密码长度: " . strlen($pass) . "<br>";
    
    if (password_verify($pass, ADMIN_PASSWORD_HASH)) {
        $debug .= "密码验证成功<br>";
        $_SESSION['logged_in'] = true;
        $debug .= "Session已设置<br>";
        ob_clean();
        header('Location: index.php');
        exit;
    } else {
        $error = '密码错误';
        $debug .= "密码验证失败 - 输入的密码: '" . htmlspecialchars($pass) . "'<br>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>登录</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 4px;
            margin: 16px 0;
            border: 1px solid #ef5350;
            font-weight: bold;
        }
        .debug-info {
            background-color: #f5f5f5;
            color: #333;
            padding: 12px 16px;
            border-radius: 4px;
            margin: 16px 0;
            border: 1px solid #ddd;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>词汇银行 - 登录</h1>
        <?php if ($error): ?>
            <div class="error-message">✗ <?= htmlspecialchars($error) ?> - 请重试，默认密码是 admin123</div>
        <?php endif; ?>
        <?php if ($debug): ?>
            <div class="debug-info"><strong>调试信息:</strong><br><?= $debug ?></div>
        <?php endif; ?>
        <form method="post">
            <label>管理员密码：<input type="password" name="password" required></label>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>