<?php
// includes/functions.php
require_once __DIR__ . '/../config.php';

/**
 * 获取所有单词，支持搜索和话题筛选
 */
function getWords($search = '', $topic = '') {
    global $pdo;
    $sql = "SELECT * FROM words WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (word LIKE :search OR meaning_cn LIKE :search2)";
        $params['search'] = "%$search%";
        $params['search2'] = "%$search%";
    }
    if (!empty($topic)) {
        $sql .= " AND topic = :topic";
        $params['topic'] = $topic;
    }

    $sql .= " ORDER BY word ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 根据ID获取单个单词
 */
function getWordById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM words WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取某单词的所有例句
 */
function getExamples($word_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM examples WHERE word_id = :wid");
    $stmt->execute(['wid' => $word_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取某单词的所有同义词
 */
function getSynonyms($word_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM synonyms WHERE word_id = :wid");
    $stmt->execute(['wid' => $word_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 添加单词（含例句和同义词）
 */
function addWord($data, $examples, $synonyms) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO words (word, phonetic, part_of_speech, meaning_cn, meaning_en, topic, created_at, updated_at)
                               VALUES (:word, :phonetic, :pos, :mean_cn, :mean_en, :topic, NOW(), NOW())");
        $stmt->execute([
            'word' => $data['word'],
            'phonetic' => $data['phonetic'],
            'pos' => $data['part_of_speech'],
            'mean_cn' => $data['meaning_cn'],
            'mean_en' => $data['meaning_en'],
            'topic' => $data['topic']
        ]);
        $word_id = $pdo->lastInsertId();

        // 插入例句
        $stmt_example = $pdo->prepare("INSERT INTO examples (word_id, sentence, source) VALUES (:wid, :sent, :src)");
        foreach ($examples as $ex) {
            if (!empty($ex['sentence'])) {
                $stmt_example->execute([
                    'wid' => $word_id,
                    'sent' => $ex['sentence'],
                    'src' => $ex['source']
                ]);
            }
        }

        // 插入同义词
        $stmt_syn = $pdo->prepare("INSERT INTO synonyms (word_id, synonym, nuance) VALUES (:wid, :syn, :nuance)");
        foreach ($synonyms as $syn) {
            if (!empty($syn['synonym'])) {
                $stmt_syn->execute([
                    'wid' => $word_id,
                    'syn' => $syn['synonym'],
                    'nuance' => $syn['nuance']
                ]);
            }
        }

        $pdo->commit();
        return $word_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * 获取今日需要复习的单词数量
 */
function getTodayReviewCount() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM words w
        WHERE w.id NOT IN (
            SELECT word_id FROM review_logs
            GROUP BY word_id
            HAVING MAX(next_review_date) > :today
        )
    ");
    $stmt->execute(['today' => $today]);
    return $stmt->fetchColumn();
}

/**
 * 获取今日待复习的单词列表
 */
function getTodayReviewWords() {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT w.* FROM words w
        WHERE w.id NOT IN (
            SELECT word_id FROM review_logs
            GROUP BY word_id
            HAVING MAX(next_review_date) > :today
        )
        ORDER BY w.word ASC
    ");
    $stmt->execute(['today' => $today]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 添加一条复习记录（简易SM-2算法）
 */
function addReviewLog($word_id, $quality) {
    global $pdo;
    $today = date('Y-m-d');
    // 简单间隔：质量>=4则间隔逐步增长，否则明天再复习
    if ($quality >= 4) {
        $last_log = $pdo->prepare("SELECT next_review_date FROM review_logs WHERE word_id = :wid ORDER BY id DESC LIMIT 1");
        $last_log->execute(['wid' => $word_id]);
        $last_date = $last_log->fetchColumn();
        if ($last_date) {
            $last_ts = strtotime($last_date);
            $interval = max(1, round((time() - $last_ts) / 86400) * 2.5);
        } else {
            $interval = 1;
        }
        $next_date = date('Y-m-d', strtotime("+{$interval} day"));
    } else {
        $next_date = date('Y-m-d', strtotime("+1 day"));
    }

    $stmt = $pdo->prepare("INSERT INTO review_logs (word_id, review_date, quality, next_review_date)
                           VALUES (:wid, :today, :qual, :next)");
    $stmt->execute([
        'wid' => $word_id,
        'today' => $today,
        'qual' => $quality,
        'next' => $next_date
    ]);
}

/**
 * 获取所有不同的话题
 */
function getTopics() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT topic FROM words WHERE topic != '' ORDER BY topic");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}