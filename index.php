<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$total_words = count(getWords());
$today_review = getTodayReviewCount();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>词汇银行 - 首页</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav>
        <a href="index.php" class="active">首页</a>
        <a href="add.php">添加单词</a>
        <a href="list.php">单词列表</a>
        <a href="review.php">复习 (<?= $today_review ?>)</a>
        <a href="logout.php">退出</a>
    </nav>

    <h1>词汇银行</h1>
    <div class="card">
        <p>总单词数：<strong><?= $total_words ?></strong></p>
        <p>今日待复习：<strong><?= $today_review ?></strong> 个</p>
    </div>
</body>
</html>