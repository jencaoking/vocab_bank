<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$words = getTodayReviewWords();

$current = null;
if (!empty($words)) {
    // 始终取第一个（动态消费队列）
    $current = $words[0];
    $total = count($words);
} else {
    $total = 0;
}

// 处理评分提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['word_id'], $_POST['quality'])) {
    addReviewLog($_POST['word_id'], (int)$_POST['quality']);
    // 重新加载页面，始终取新的 $words[0]
    header('Location: review.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>复习</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav>
        <a href="index.php">首页</a>
        <a href="add.php">添加单词</a>
        <a href="list.php">单词列表</a>
        <a href="review.php" class="active">复习</a>
        <a href="logout.php">退出</a>
    </nav>

    <h1>今日复习</h1>

    <?php if (isset($_GET['done'])): ?>
        <div class="card success">今日复习完成！</div>
        <a href="index.php">返回首页</a>
    <?php elseif ($current): ?>
        <div class="card" style="font-size: 2em; text-align: center;">
            <?= htmlspecialchars($current['word']) ?>
        </div>
        <p style="text-align: center;">(<?= htmlspecialchars($current['part_of_speech']) ?>)</p>
        <details>
            <summary>显示释义</summary>
            <p><?= htmlspecialchars($current['meaning_cn']) ?></p>
            <?php if (!empty($current['meaning_en'])): ?>
                <p><?= htmlspecialchars($current['meaning_en']) ?></p>
            <?php endif; ?>
        </details>

        <form method="post" style="text-align: center; margin-top: 30px;">
            <input type="hidden" name="word_id" value="<?= $current['id'] ?>">
            <p>你记得怎么样？</p>
            <?php foreach ([0,1,2,3,4,5] as $q): ?>
                <button type="submit" name="quality" value="<?= $q ?>"><?= $q ?></button>
            <?php endforeach; ?>
            <p><small>(0=完全忘了，5=非常熟练)</small></p>
        </form>
        <p style="text-align: center;">剩余 <?= $total ?> 个</p>
    <?php else: ?>
        <div class="card">今天没有需要复习的单词。</div>
    <?php endif; ?>
</body>
</html>