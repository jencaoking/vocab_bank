<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examples = [];
    if (isset($_POST['examples']['sentence'])) {
        foreach ($_POST['examples']['sentence'] as $i => $sent) {
            $examples[] = [
                'sentence' => $sent,
                'source' => $_POST['examples']['source'][$i] ?? ''
            ];
        }
    }
    $synonyms = [];
    if (isset($_POST['synonyms']['synonym'])) {
        foreach ($_POST['synonyms']['synonym'] as $i => $syn) {
            $synonyms[] = [
                'synonym' => $syn,
                'nuance' => $_POST['synonyms']['nuance'][$i] ?? ''
            ];
        }
    }

    $result = addWord($_POST, $examples, $synonyms);
    if ($result) {
        header('Location: add.php?success=1');
        exit;
    } else {
        $message = '<p class="error">添加失败，可能单词已存在。</p>';
    }
}

if (isset($_GET['success'])) {
    $message = '<p class="success">单词添加成功！</p>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加单词</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/main.js"></script>
</head>
<body>
    <nav>
        <a href="index.php">首页</a>
        <a href="add.php" class="active">添加单词</a>
        <a href="list.php">单词列表</a>
        <a href="review.php">复习</a>
        <a href="logout.php">退出</a>
    </nav>

    <h1>添加新单词</h1>
    <?= $message ?>
    <form method="post">
        <div class="form-group">
            <label>单词 *</label>
            <input type="text" name="word" required>
        </div>
        <div class="form-group">
            <label>音标</label>
            <input type="text" name="phonetic">
        </div>
        <div class="form-group">
            <label>词性</label>
            <input type="text" name="part_of_speech" placeholder="如 n., v., adj.">
        </div>
        <div class="form-group">
            <label>中文释义 *</label>
            <input type="text" name="meaning_cn" required>
        </div>
        <div class="form-group">
            <label>英文释义</label>
            <input type="text" name="meaning_en">
        </div>
        <div class="form-group">
            <label>话题分类</label>
            <select name="topic">
                <option value="">-- 无 --</option>
                <option value="科技">科技</option>
                <option value="教育">教育</option>
                <option value="环境">环境</option>
                <option value="政府">政府</option>
                <option value="健康">健康</option>
                <option value="文化">文化</option>
                <option value="犯罪">犯罪</option>
                <option value="媒体">媒体</option>
            </select>
        </div>

        <h3>例句</h3>
        <div id="examples-container">
            <div class="example-item">
                <input type="text" name="examples[sentence][]" placeholder="例句" style="width:70%">
                <input type="text" name="examples[source][]" placeholder="来源" style="width:25%">
            </div>
        </div>
        <button type="button" onclick="addExample()">+ 添加例句</button>

        <h3>同义词</h3>
        <div id="synonyms-container">
            <div class="synonym-item">
                <input type="text" name="synonyms[synonym][]" placeholder="同义词">
                <input type="text" name="synonyms[nuance][]" placeholder="细微差别">
            </div>
        </div>
        <button type="button" onclick="addSynonym()">+ 添加同义词</button>

        <br><br>
        <button type="submit">保存单词</button>
    </form>
</body>
</html>