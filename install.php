<?php
require_once 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS words (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word TEXT NOT NULL UNIQUE,
            phonetic TEXT,
            part_of_speech TEXT,
            meaning_cn TEXT NOT NULL,
            meaning_en TEXT,
            topic TEXT,
            proficiency INTEGER DEFAULT 0,
            created_at DATETIME,
            updated_at DATETIME
        );

        CREATE TABLE IF NOT EXISTS examples (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word_id INTEGER NOT NULL,
            sentence TEXT NOT NULL,
            source TEXT,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS synonyms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word_id INTEGER NOT NULL,
            synonym TEXT NOT NULL,
            nuance TEXT,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS review_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word_id INTEGER NOT NULL,
            review_date DATE NOT NULL,
            quality INTEGER NOT NULL,
            next_review_date DATE NOT NULL,
            FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
        );
    ");

    echo "数据表创建成功！<a href='index.php'>返回首页</a>";
} catch (PDOException $e) {
    die('建表失败：' . $e->getMessage());
}