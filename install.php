<?php
require_once 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS words (
            id INT PRIMARY KEY AUTO_INCREMENT,
            word VARCHAR(255) NOT NULL UNIQUE,
            phonetic TEXT,
            part_of_speech TEXT,
            meaning_cn TEXT NOT NULL,
            meaning_en TEXT,
            topic TEXT,
            proficiency INT DEFAULT 0,
            created_at DATETIME,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS examples (
            id INT PRIMARY KEY AUTO_INCREMENT,
            word_id INT NOT NULL,
            sentence TEXT NOT NULL,
            source TEXT,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS synonyms (
            id INT PRIMARY KEY AUTO_INCREMENT,
            word_id INT NOT NULL,
            synonym TEXT NOT NULL,
            nuance TEXT,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS review_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            word_id INT NOT NULL,
            review_date DATE NOT NULL,
            quality INT NOT NULL,
            next_review_date DATE NOT NULL,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "数据表创建成功！<a href='index.php'>返回首页</a>";
} catch (PDOException $e) {
    die('建表失败：' . $e->getMessage());
}