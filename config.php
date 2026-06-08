<?php
// config.php
session_start();

// MySQL 数据库配置
$db_host = 'mysql6.sqlpub.com';
$db_port = '3311';
$db_name = 'english1113';
$db_user = 'admin1113';
$db_pass = 'uertk5wR4SY4n9Vq';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // 开启异常模式，方便调试
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 网站根目录（用于链接生成）
define('BASE_URL', '/');

// 管理员密码（DEMO 用，实际使用请修改）
// 这里是 'admin123' 的哈希值，登录时用 password_verify 验证
define('ADMIN_PASSWORD_HASH', '$2y$12$YzPgI/40KMTnwPhSRy7AzuZgEHpKprH9BkGWw75HFx1v9oXXI4iha');