<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$search = $_GET['search'] ?? '';
$topic = $_GET['topic'] ?? '';
$words = getWords($search, $topic);
$topics = getTopics();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>单词列表</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav>
        <a href="index.php">首页</a>
        <a href="add.php">添加单词</a>
        <a href="list.php" class="active">单词列表</a>
        <a href="review.php">复习</a>
        <a href="logout.php">退出</a>
    </nav>

    <h1>单词列表</h1>

    <form method="get" style="margin-bottom:20px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索单词或释义...">
        <select name="topic">
            <option value="">所有话题</option>
            <?php foreach ($topics as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $topic === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">搜索</button>
    </form>

    <table>
        <tr>
            <th>单词</th>
            <th>词性</th>
            <th>中文释义</th>
            <th>话题</th>
            <th>操作</th>
        </tr>
        <?php foreach ($words as $w): ?>
        <tr>
            <td><?= htmlspecialchars($w['word']) ?></td>
            <td><?= htmlspecialchars($w['part_of_speech']) ?></td>
            <td><?= htmlspecialchars($w['meaning_cn']) ?></td>
            <td><?= htmlspecialchars($w['topic']) ?></td>
            <td><a href="detail.php?id=<?= $w['id'] ?>">详情</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($words)): ?>
        <tr><td colspan="5">暂无单词</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>