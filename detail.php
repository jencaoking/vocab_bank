<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$id = $_GET['id'] ?? 0;
$word = getWordById($id);
if (!$word) {
    die('单词不存在');
}
$examples = getExamples($id);
$synonyms = getSynonyms($id);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($word['word']) ?> - 详情</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav>
        <a href="index.php">首页</a>
        <a href="add.php">添加单词</a>
        <a href="list.php">单词列表</a>
        <a href="review.php">复习</a>
        <a href="logout.php">退出</a>
    </nav>

    <h1><?= htmlspecialchars($word['word']) ?></h1>
    <p><strong>音标：</strong><?= htmlspecialchars($word['phonetic']) ?></p>
    <p><strong>词性：</strong><?= htmlspecialchars($word['part_of_speech']) ?></p>
    <p><strong>中文释义：</strong><?= htmlspecialchars($word['meaning_cn']) ?></p>
    <p><strong>英文释义：</strong><?= htmlspecialchars($word['meaning_en']) ?></p>
    <p><strong>话题：</strong><?= htmlspecialchars($word['topic']) ?></p>

    <h2>例句</h2>
    <?php if (empty($examples)): ?>
        <p>暂无例句</p>
    <?php else: ?>
        <ul>
        <?php foreach ($examples as $ex): ?>
            <li>
                <?= htmlspecialchars($ex['sentence']) ?>
                <?php if (!empty($ex['source'])): ?>
                    <em>(<?= htmlspecialchars($ex['source']) ?>)</em>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>同义词</h2>
    <?php if (empty($synonyms)): ?>
        <p>暂无同义词</p>
    <?php else: ?>
        <ul>
        <?php foreach ($synonyms as $syn): ?>
            <li>
                <a href="list.php?search=<?= urlencode($syn['synonym']) ?>"><?= htmlspecialchars($syn['synonym']) ?></a>
                <?php if (!empty($syn['nuance'])): ?>
                    (<?= htmlspecialchars($syn['nuance']) ?>)
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><a href="list.php">← 返回列表</a></p>
</body>
</html>