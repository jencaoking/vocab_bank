# 词汇银行（Vocab Bank）企业级实施方案

> 版本：v2.0 · 状态：执行规划 · 文档密级：内部公开
> 适用代码库：`j:\PROJECT\PHP project\vocab_bank`（PHP 7.4+ / MySQL 5.7+ / 原生前端）
> 文档目标：在不大改技术栈的前提下，把当前「单文件 PHP」原型，重构为具备 **多用户、可观测、可维护、可演进** 特性的企业级应用。

---

## 0. 目录

- [1. 项目背景与愿景](#1-项目背景与愿景)
- [2. 现状评估（基线扫描）](#2-现状评估基线扫描)
- [3. 目标与非目标](#3-目标与非目标)
- [4. 总体架构](#4-总体架构)
- [5. 技术栈与依赖策略](#5-技术栈与依赖策略)
- [6. 数据架构（Schema 设计）](#6-数据架构schema-设计)
- [7. 领域模型与业务规则](#7-领域模型与业务规则)
- [8. 复习算法（标准 SM-2）](#8-复习算法标准-sm-2)
- [9. 安全架构](#9-安全架构)
- [10. API 设计（REST）](#10-api-设计rest)
- [11. 前端架构](#11-前端架构)
- [12. 性能与可扩展性](#12-性能与可扩展性)
- [13. 部署与运维](#13-部署与运维)
- [14. 质量保障（测试体系）](#14-质量保障测试体系)
- [15. 监控、日志与告警](#15-监控日志与告警)
- [16. 备份与容灾](#16-备份与容灾)
- [17. 分阶段实施路线图](#17-分阶段实施路线图)
- [18. 风险登记册与缓解策略](#18-风险登记册与缓解策略)
- [19. 团队、角色与协作](#19-团队角色与协作)
- [20. 成本与资源估算](#20-成本与资源估算)
- [21. 验收与成功指标（KPI）](#21-验收与成功指标kpi)
- [附录 A：目录结构演进](#附录-a目录结构演进)
- [附录 B：迁移脚本示例](#附录-b迁移脚本示例)
- [附录 C：术语表](#附录-c术语表)

---

## 1. 项目背景与愿景

### 1.1 业务背景

面向 **IELTS 备考人群** 的「输入 → 存储 → 复习 → 输出」闭环单词训练系统。当前实现为单管理员、原生 PHP + MySQL 的内部小工具，已具备：

- 单词 / 例句 / 同义词 / 复习记录 CRUD
- 简化版间隔重复（SM-2 近似）
- 话题分类
- 单一密码登录

### 1.2 愿景

> 三年内，把 Vocab Bank 打造成 **个人 / 小班 / 留学机构** 都能直接复用的「词汇资产平台」。

三个层级的产品形态：

| 层级 | 用户 | 价值主张 | 时点 |
|------|------|----------|------|
| L1 个人版 | 单备考者 | 极简录入、强复习曲线、数据自有 | 当前 |
| L2 协作版 | 留学机构 / 班级 | 词库共享、班级督学、学习画像 | +6 个月 |
| L3 平台版 | 多租户 | API 化、白标、按词库订阅 | +18 个月 |

### 1.3 设计原则

1. **演进式重构**：禁止大爆炸式重写，保留接口兼容，逐步替换。
2. **小而可验证**：每个里程碑必须可上线、可回滚、可观测。
3. **数据第一**：单词、复习记录、学习画像是不可再生资产，先保证零丢失。
4. **默认安全**：CSRF / XSS / SQL 注入 / 越权在框架层默认挡住。
5. **可独立运行**：单 VPS 即可跑通，不强依赖容器化（但不留容器化障碍）。

---

## 2. 现状评估（基线扫描）

| 维度 | 现状 | 风险 / 债务 | 优先级 |
|------|------|------------|--------|
| **架构** | 平铺 PHP 文件，`includes/functions.php` 用 `global $pdo`，无路由、无分层 | 测试难、复用差、回归成本高 | P0 |
| **认证** | 单密码 hash + session，账号=密码；无 CSRF、无密码修改、无登录限速 | 暴力破解、XSS 内嵌、CSRF | P0 |
| **算法** | 简化 SM-2：固定 2.5 倍间隔、无 EF、无响应档位细化 | 复习曲线不科学，长期记忆效率低 | P1 |
| **数据模型** | `words` / `examples` / `synonyms` / `review_logs`；无 user_id、无 soft delete | 无法支持多用户，无法审计 | P0 |
| **搜索** | `LIKE %...%`，无索引、无全文检索 | 词量大时全表扫 | P1 |
| **导入导出** | 无 | 灾备 / 迁移 / 班级共享阻塞 | P2 |
| **统计** | 仅首页总数、待复习数 | 缺乏学习洞察，难促活 | P2 |
| **移动端** | 仅 `viewport` meta，无响应式栅格、无 PWA | 移动用户流失 | P2 |
| **可观测性** | 无日志、无 metrics、无 trace | 线上故障黑盒 | P1 |
| **CI/CD** | 无 | 手动部署、易出错 | P1 |
| **配置** | 凭据明文 `config.php`（已演示化） | 生产凭据泄露风险 | P0 |
| **依赖管理** | 无 Composer / 无 package.json | 第三方库升级成本高 | P0 |

---

## 3. 目标与非目标

### 3.1 目标（In-Scope）

- G1：**多用户**：注册 / 登录 / 角色（admin / user）；数据按 user_id 隔离。
- G2：**标准 SM-2 算法**：EF、Repetition、Interval 三元组可追溯、可调参。
- G3：**安全基线**：CSRF、密码策略、登录限速、HTTPS、SQL 参数化、输出转义。
- G4：**可观测**：结构化日志、关键 metric、错误追踪。
- G5：**可测试**：单元 / 集成 / E2E 三层覆盖率，关键路径 ≥ 80%。
- G6：**CI/CD**：提交即跑测试、构建、部署预发。
- G7：**PWA + 离线缓存**：移动端体验不掉线。
- G8：**统计仪表板**：留存、复习分布、话题熟练度热图。
- G9：**开放 API**：第三方扩展（Anki 同步、Chrome 插件）有据可依。

### 3.2 非目标（Out-of-Scope，本期不做）

- N1：移动 Native App（用 PWA 满足）。
- N2：AI 自动生成例句（仅预留接口位）。
- N3：多语言（i18n 字段预留，文案先中英两份）。
- N4：付费订阅 / 支付（L3 阶段再考虑）。

---

## 4. 总体架构

### 4.1 逻辑分层

```
┌────────────────────────────────────────────────────────────┐
│ Presentation Layer（前端）                                 │
│  模板 (PHP) + 渐进式 JS + PWA (Service Worker)            │
└────────────────────────────────────────────────────────────┘
                          │ HTTP/JSON
┌────────────────────────────────────────────────────────────┐
│ Application Layer（应用服务 / Controller）                  │
│  路由 · 鉴权 · CSRF · 参数校验 · 业务编排                   │
└────────────────────────────────────────────────────────────┘
                          │
┌────────────────────────────────────────────────────────────┐
│ Domain Layer（领域模型）                                    │
│  Word / Review / User / Topic / Statistic                  │
└────────────────────────────────────────────────────────────┘
                          │
┌────────────────────────────────────────────────────────────┐
│ Infrastructure Layer（基础设施）                            │
│  PDO Repository · Cache · Logger · Mailer · TTS 客户端     │
└────────────────────────────────────────────────────────────┘
                          │
┌────────────────────────────────────────────────────────────┐
│  MySQL · Redis(可选) · Object Storage(备份)                │
└────────────────────────────────────────────────────────────┘
```

### 4.2 物理部署拓扑

```
                  ┌──────────┐
                  │  用户    │──HTTPS──┐
                  └──────────┘         │
                  ┌──────────┐         │
                  │ 爬虫/插件│──API key│
                  └──────────┘         ▼
                              ┌─────────────────┐
                              │ Nginx + WAF     │  ← TLS、限流、静态资源
                              └─────────────────┘
                                       │
                              ┌─────────────────┐
                              │ PHP-FPM 8.x     │  ← 应用层
                              │ (含 OPcache)    │
                              └─────────────────┘
                              │             │
                  ┌───────────┘             └───────────┐
                  ▼                                     ▼
          ┌──────────────┐                    ┌──────────────────┐
          │  MySQL 8.0   │                    │ Redis(可选)      │
          │  (主从/只读) │                    │  会话/限流/缓存  │
          └──────────────┘                    └──────────────────┘
```

### 4.3 关键流程：单词复习

```
[前端] 进入 /review
   │  GET /api/review/queue
   ▼
[Controller] ReviewController::queue
   │  1) 校验登录 & CSRF
   │  2) 取 today ≤ next_review_date 的 word_id
   │  3) 按 (next_review_date asc, proficiency asc) 排序
   ▼
[Domain] ReviewService::nextBatch()
   │  返回 [ {word, examples, synonyms, ef, interval, rep} ]
   ▼
[前端] 展示单词 → 用户回忆 → 选择 0~5
   │  POST /api/review/answer {word_id, quality}
   ▼
[Domain] SM2::update(quality)
   │  计算新的 (EF, rep, interval, next_review_date)
   │  写 review_logs
   │  更新 words.proficiency
   │  发布事件 ReviewRecorded
   ▼
[异步/同步] StatisticService::record() → 触发统计聚合
```

---

## 5. 技术栈与依赖策略

### 5.1 选型清单

| 层 | 选型 | 理由 | 备选 |
|----|------|------|------|
| 语言 | PHP 8.2+ | 强类型、enum、readonly、性能 | 8.1 LTS |
| Web 服务器 | Nginx 1.24+ | 反代 + 静态 + WAF 友好 | Apache |
| 应用服务器 | PHP-FPM + OPcache | 主流稳定 | RoadRunner |
| 数据库 | MySQL 8.0 (utf8mb4_0900_ai_ci) | 已用，兼容好 | MariaDB 10.11 |
| 缓存 | Redis 7（可选） | 会话/限流/查询缓存 | 文件缓存 |
| 包管理 | **Composer** | 必须引入 | — |
| 依赖（必要） | `vlucas/phpdotenv`、`nyholm/psr7`、`php-cs-fixer`、`phpunit/phpunit` | 轻量、生产级 | — |
| 前端 | 原生 JS（模块化）+ Chart.js + vis-network | 渐进增强 | Vue 3（按需） |
| CI | GitHub Actions | 免费、与代码托管一致 | GitLab CI |
| 监控 | Sentry（错误）+ UptimeRobot（探活）+ Prometheus 文本指标 | 成本低 | 自建 ELK |
| 部署 | Ansible / 简单 shell + rsync | 单机足够 | Docker Compose |

> **原则**：能少一个服务就少一个，能晚引框架就晚引框架。

### 5.2 引入第三方库的门槛

- Star ≥ 1k 或仓库持续维护 ≥ 2 年
- MIT / BSD / Apache-2.0 协议
- 无 native 扩展依赖（除非平台兼容）
- 引入前写 ADR（架构决策记录）说明理由

---

## 6. 数据架构（Schema 设计）

### 6.1 关键原则

- 所有表带 `id`、`created_at`、`updated_at`。
- 所有用户数据 `user_id NOT NULL`，无外键必须删数据 → **软删除** + 应用层校验。
- 业务表加 `version` 列做乐观锁（防止多人编辑冲突）。
- 文本字段明确 `VARCHAR(N)` 上限，避免滥用 `TEXT`。

### 6.2 ER 概览

```
users 1───* words 1───* examples
                  1───* synonyms
                  1───* review_logs
                  *───* topics (via word_topics)
        1───* review_sessions
        1───* study_stats (按日聚合)
```

### 6.3 表 DDL（精简版，已对齐原结构并扩展）

```sql
-- 用户
CREATE TABLE users (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,        -- argon2id
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  status ENUM('active','locked','disabled') NOT NULL DEFAULT 'active',
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Shanghai',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 单词（按用户隔离）
CREATE TABLE words (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  word VARCHAR(190) NOT NULL,                -- 索引前缀
  phonetic VARCHAR(128) NULL,
  part_of_speech VARCHAR(64) NULL,
  meaning_cn TEXT NOT NULL,
  meaning_en TEXT NULL,
  topic VARCHAR(64) NULL,
  proficiency TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0~5
  ef DECIMAL(4,2) NOT NULL DEFAULT 2.50,    -- SM-2 easiness factor
  rep SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- repetition number
  interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_review_date DATE NULL,
  last_reviewed_at DATETIME NULL,
  deleted_at DATETIME NULL,                  -- 软删除
  version INT UNSIGNED NOT NULL DEFAULT 1,   -- 乐观锁
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_user_word (user_id, word, deleted_at),
  KEY idx_user_topic (user_id, topic),
  KEY idx_user_due (user_id, next_review_date),
  FULLTEXT KEY ft_word_meaning (word, meaning_cn, meaning_en) /*!50100 WITH PARSER ngram */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 例句
CREATE TABLE examples (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  word_id BIGINT UNSIGNED NOT NULL,
  sentence TEXT NOT NULL,
  source VARCHAR(255) NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_word (word_id),
  CONSTRAINT fk_examples_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 同义词
CREATE TABLE synonyms (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  word_id BIGINT UNSIGNED NOT NULL,
  synonym VARCHAR(190) NOT NULL,
  nuance TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_word (word_id),
  UNIQUE KEY uk_word_syn (word_id, synonym),
  CONSTRAINT fk_synonyms_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 复习记录（追加写，永不更新）
CREATE TABLE review_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,           -- 冗余便于按用户统计
  word_id BIGINT UNSIGNED NOT NULL,
  review_date DATE NOT NULL,
  quality TINYINT UNSIGNED NOT NULL,          -- 0~5
  ef_before DECIMAL(4,2) NOT NULL,
  ef_after DECIMAL(4,2) NOT NULL,
  rep_before SMALLINT UNSIGNED NOT NULL,
  rep_after SMALLINT UNSIGNED NOT NULL,
  interval_before SMALLINT UNSIGNED NOT NULL,
  interval_after SMALLINT UNSIGNED NOT NULL,
  next_review_date DATE NOT NULL,
  duration_ms INT UNSIGNED NULL,              -- 用户思考时长
  created_at DATETIME NOT NULL,
  KEY idx_user_date (user_id, review_date),
  KEY idx_word_date (word_id, review_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 每日统计（物化视图）
CREATE TABLE study_stats_daily (
  user_id BIGINT UNSIGNED NOT NULL,
  stat_date DATE NOT NULL,
  words_added INT UNSIGNED NOT NULL DEFAULT 0,
  reviews_done INT UNSIGNED NOT NULL DEFAULT 0,
  reviews_correct INT UNSIGNED NOT NULL DEFAULT 0,    -- quality >= 3
  minutes_active SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 审计日志（管理类操作）
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,         -- e.g. word.delete
  target_type VARCHAR(32) NULL,
  target_id BIGINT UNSIGNED NULL,
  ip VARBINARY(16) NULL,
  ua VARCHAR(255) NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL,
  KEY idx_user_time (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.4 索引策略

- 复习队列查询走 `idx_user_due`。
- 搜索走 `FULLTEXT ... WITH PARSER ngram`（中文友好）；3 字符以下降级为 `LIKE`。
- 统计聚合走 `study_stats_daily` 物化视图，不在 OLTP 上做重 COUNT。

### 6.5 迁移策略

- 引入 `migrations/` 目录，**版本号顺序执行**。
- 关键迁移要写 `up` 与 `down`；上线先在预发完整跑通。
- 一次性脚本（如把现有单词分配给默认用户）写进 `migrations/0X_seed_default_user.php`。

---

## 7. 领域模型与业务规则

### 7.1 实体

- **User**：账号、时区、状态。
- **Word**：核心词条；`proficiency` 与 `ef` 解耦（proficiency 是面向用户的「熟练度」展示星标；ef 是算法内部状态）。
- **Example / Synonym**：值对象集合。
- **ReviewLog**：不可变事件。
- **Topic**：字符串枚举起步（教育/科技/环境…），后续可独立成表以支持自定义。
- **Statistic**：派生数据。

### 7.2 关键不变量

- 同一用户下，`word` 唯一（不含已软删）。
- `ef ∈ [1.3, 2.5]`，超出则夹紧。
- `next_review_date ≥ review_date`。
- 任何写入 `words` 的接口必须更新 `updated_at` 与 `version+1`。
- 删除单词为软删除；7 天后由定时任务物理清理并审计。

### 7.3 服务边界

```
AuthService        登录/注册/锁定/限速
WordService        词条 CRUD、批量导入
ReviewService      队列选取、SM-2 更新
StatisticService   聚合、写 study_stats_daily
ExportService      CSV/JSON 导出
PronunciationService  TTS / 词典 API 代理
```

服务之间通过**方法调用**而非事件总线起步（避免过度工程）；待 L3 多租户再引入领域事件。

---

## 8. 复习算法（标准 SM-2）

### 8.1 公式

设 `q` 为用户 0~5 自评，`EF` 为当前简易度（默认 2.5）：

```
if q < 3:                       -- 失败
    rep = 0
    interval = 1
else:                           -- 通过
    if rep == 0:    interval = 1
    elif rep == 1:  interval = 6
    else:           interval = round(interval * EF)
    rep += 1

EF' = EF + (0.1 - (5-q) * (0.08 + (5-q) * 0.02))
if EF' < 1.3: EF' = 1.3
```

### 8.2 字段落表

每次答题写入 `review_logs` 的 `*_before / *_after` 双值，便于回放和 A/B 分析。

### 8.3 队列策略

- **取词顺序**：`next_review_date asc, ef asc, proficiency asc, last_reviewed_at asc`。
- **新词上限**：每日 ≤ 20（可配置），与复习词按 1:3 配比。
- **懒队列**：先取 50 个放客户端，本地消化完再拉；减少请求次数。

### 8.4 可调参数

`config/review.php`：

```php
return [
  'new_words_per_day' => 20,
  'review_batch_size' => 50,
  'min_ef' => 1.3,
  'max_interval_days' => 365,
  'quality_threshold' => 3,    // ≥ 视为通过
];
```

---

## 9. 安全架构

### 9.1 认证与会话

- 密码：argon2id（`password_hash` 默认即可），强制 8 字符 + 1 字母 1 数字。
- 登录限速：同 IP 5 分钟内 10 次失败 → 拉黑 15 分钟；同账号连续 5 次失败 → 锁 10 分钟。
- 会话：`session.cookie_httponly=1; secure=1; samesite=Lax`；`session.use_strict_mode=1`。
- 登出销毁 session id，重新生成。

### 9.2 CSRF

- 所有写操作（POST/PUT/DELETE/PATCH）校验双提交 token：
  - 服务端生成 → 写入 session → 通过 `meta` 标签输出。
  - 前端 fetch 自动带 `X-CSRF-Token`。
  - 失败一律 419。

### 9.3 越权

- 资源（`/word/{id}`）首先校验 `word.user_id == session.user.id`，admin 例外。
- 列表接口默认只返回当前用户数据；管理后台另设端点。

### 9.4 XSS

- 模板层默认 `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`。
- 不允许用户提交 HTML 富文本（仅 Markdown 预览且经过白名单清洗）。

### 9.5 SQL 注入

- 100% PDO 预处理；禁止拼接。
- CI 规则：搜 `\$pdo->query\(`、`->exec\(`，命中即失败。

### 9.6 传输与存储

- 强制 HTTPS（HSTS preload）。
- 凭据走 `.env`（不进 VCS），`config.php` 仅做加载。
- 敏感日志（邮箱、IP）打码；IP 入库用 `VARBINARY(16)` 存原始值，展示时按需脱敏。

### 9.7 依赖安全

- Composer 开启 `audit`；CI 步骤 `composer audit` 失败即阻断。
- Dependabot/Renovate 每周自动提 PR。

### 9.8 备份与恢复

- 见 [§16](#16-备份与容灾)。

---

## 10. API 设计（REST）

> 路径前缀 `/api/v1`，统一 JSON，错误格式见 §10.4。

### 10.1 端点清单

| 方法 | 路径 | 说明 | 鉴权 |
|------|------|------|------|
| POST | /auth/register | 注册 | 公开 |
| POST | /auth/login | 登录 | 公开 |
| POST | /auth/logout | 登出 | 需 |
| GET  | /me | 当前用户 | 需 |
| GET  | /words | 词条列表（分页/搜索/筛选） | 需 |
| POST | /words | 新建 | 需 |
| GET  | /words/{id} | 详情 | 需 |
| PUT  | /words/{id} | 更新（带 version） | 需 |
| DELETE | /words/{id} | 软删除 | 需 |
| POST | /words/import | CSV 导入 | 需 |
| GET  | /words/export | CSV/JSON 导出 | 需 |
| GET  | /topics | 话题枚举 | 需 |
| GET  | /review/queue | 复习队列 | 需 |
| POST | /review/answer | 提交评分 | 需 |
| GET  | /stats/daily?from&to | 每日统计 | 需 |
| GET  | /stats/proficiency | 熟练度分布 | 需 |

### 10.2 列表查询规范

```
GET /words?cursor=eyJpZCI6MTAwfQ&limit=20&search=abate&topic=环境&proficiency=0,1
```

- 分页用 **cursor**（基于 `id` 编码的 base64），比 offset 在大表上稳定。
- `limit` 默认 20，上限 100。

### 10.3 写入并发控制

`PUT /words/{id}` 需带 `If-Match: <version>`，服务端不匹配返回 `409 Conflict`。

### 10.4 错误格式

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "参数错误",
    "fields": { "word": "必填" },
    "trace_id": "01HXY..."
  }
}
```

HTTP 状态码：4xx 业务错误、5xx 服务错误；统一 `trace_id` 关联日志。

---

## 11. 前端架构

### 11.1 现状

- 全站共用 `css/style.css` + 局部内联。
- 复习页用了 `details/summary` 实现折叠，无 JS 模块化。

### 11.2 目标

- 渐进增强：**HTML 可独立使用**，JS 失败时核心功能仍可用。
- 模块化：拆为 `auth.js / words.js / review.js / stats.js`，按页按需加载。
- 设计系统：颜色 / 间距 / 字号 全部走 CSS 自定义属性，便于换肤。
- 图表：仅引 Chart.js + vis-network（按需 dynamic import）。

### 11.3 关键页面线框

- **首页 Dashboard**：顶部 4 张 KPI 卡片，下方两栏（30 天复习热力图、熟练度分布饼图）。
- **复习页**：全屏单词居中 → 操作区（4 档：`忘了 / 模糊 / 记得 / 熟练` 替代 0~5，更友好；后端映射到 SM-2 quality）。
- **单词详情**：上半部分例句卡 + 同义词网，下半部分复习曲线。
- **批量导入**：上传 CSV → 进度条 → 结果回执（成功 / 失败原因）。

### 11.4 PWA

- `manifest.webmanifest`：name、icons（192/512）、`display: standalone`。
- Service Worker 缓存策略：
  - 静态资源（`/css`, `/js`, `/img`）：`CacheFirst`，带版本号触发更新。
  - 页面：`StaleWhileRevalidate`。
  - API：`NetworkOnly`，失败时回退到上一次缓存的 JSON 提示。
- 离线降级页：明确提示「网络恢复后可继续复习」。

### 11.5 无障碍

- 全部交互元素键盘可达；色对比 ≥ 4.5:1；图标配 `aria-label`。
- 复习页支持「空格」翻牌、「数字键 1~4」评分。

---

## 12. 性能与可扩展性

### 12.1 性能预算

| 场景 | 目标 P95 |
|------|----------|
| 首页加载（含首屏 JS/CSS） | < 1.2s（4G） |
| 列表查询（20 条） | < 150ms |
| 复习队列拉取 | < 200ms |
| 复习提交 | < 250ms |
| 单词导入 1000 行 | < 5s |

### 12.2 优化手段

- **OPcache** 开启，`opcache.validate_timestamps=0`（部署触发 reload）。
- **HTTP 缓存**：静态资源 `Cache-Control: public, max-age=31536000, immutable`（带哈希文件名）。
- **DB 缓存**：常用话题、用户统计走 Redis（TTL 5 分钟 + 写时失效）。
- **N 皇后问题避免**：批量导入分批 `INSERT`，单批 100~500 行。
- **全文搜索**：MySQL ngram 已能覆盖 10w 行内；如扩到 100w+，迁移到 Meilisearch。

### 12.3 容量规划

- 单用户 5000 词 → 复习日志约 5w 行 → 一年 5w×12 ≈ 60w 行。
- MySQL 单表 1000w 行仍可控；按此估算单机可服务 100 活跃用户。
- 超出后策略：垂直升级 → 读写分离 → 分库（按 user_id hash）。

---

## 13. 部署与运维

### 13.1 环境分层

| 环境 | 用途 | 域名 | 数据 |
|------|------|------|------|
| local | 开发者本机 | localhost | 自建 MySQL / SQLite |
| staging | 预发 | `staging.vocab.example.com` | 生产脱敏快照 |
| prod | 生产 | `vocab.example.com` | 生产 |

### 13.2 部署流水线

```
PR → GitHub Actions
   ├─ 静态分析：php -l、php-cs-fixer --dry、phpstan level 6
   ├─ 单元测试：phpunit --coverage-clover
   ├─ 集成测试：docker-compose up mysql + phpunit
   ├─ 安全扫描：composer audit、trivy fs
   └─ 构建：composer install --no-dev
merge → main
   ├─ 打包：生成 build.tar.gz
   ├─ 推送：rsync 到目标机
   ├─ 迁移：php migrations/migrate.php up
   ├─ 重载：systemctl reload php8.2-fpm
   └─ 探活：curl /healthz
```

### 13.3 零停机

- 蓝绿或灰度：`upstream { server 1.1.1.1:9000; server 1.1.1.1:9001 backup; }`，新版本先上 backup 槽位 → 切流。
- 数据库迁移向后兼容（先加列、双写、降级时才删）。

### 13.4 运行时配置（`.env`）

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vocab.example.com
DB_HOST=...
DB_PORT=3306
DB_NAME=...
DB_USER=...
DB_PASS=...
SESSION_NAME=vocab_sess
SESSION_LIFETIME=2592000
LOG_LEVEL=info
LOG_PATH=/var/log/vocab/app.log
SENTRY_DSN=...
TTS_PROVIDER=local|free_dictionary
REVIEW_NEW_WORDS_PER_DAY=20
```

### 13.5 健康检查

- `/healthz`：进程 + DB ping。
- `/readyz`：DB + 缓存 + 关键表。
- 探针间隔 30s，失败 3 次告警。

---

## 14. 质量保障（测试体系）

### 14.1 分层

| 层 | 工具 | 覆盖目标 | 速度 |
|----|------|----------|------|
| 单元 | PHPUnit | 领域逻辑 90% | < 30s |
| 集成 | PHPUnit + 真实 MySQL | 仓储 / API 70% | < 2m |
| E2E | Playwright | 关键路径 100% | < 5m |
| 视觉 | Percy / 自建对比 | 关键页 | 按需 |
| 性能 | k6 | 列表 / 复习 API | 每周 |

### 14.2 关键测试用例（样例）

- `Sm2Test::testFailureResetsRep`：q<3 时 rep=0，interval=1。
- `Sm2Test::testEfFloorAt130`：连续低分后 EF 夹紧到 1.3。
- `WordServiceTest::testSoftDeleteKeepsUniqueConstraint`：同名词软删后可重新添加。
- `AuthTest::testBruteForceLockout`：第 6 次失败返回 423。
- `ApiTest::testCrossUserAccessForbidden`：A 用户访问 B 单词 → 404。
- `MigrationTest::testUpThenDown`：迁移可回滚。

### 14.3 静态检查

- PHPStan level 6（先 level 4，逐步加码）。
- php-cs-fixer 强制 PSR-12 + 项目规则。
- 提交前 husky（Git hooks）跑 `lint + test:unit`。

### 14.4 缺陷管理

- 缺陷模板：复现步骤 / 期望 / 实际 / 影响面 / 截图。
- 严重度：P0（数据丢失/安全）→ 24h；P1（核心流程阻塞）→ 3d；P2 → 迭代；P3 → backlog。
- 每个 PR 关联 Issue；修复后必须补回归测试。

---

## 15. 监控、日志与告警

### 15.1 日志

- 库：Monolog，结构化 JSON，字段 `ts, level, trace_id, user_id, route, status, latency_ms`。
- 等级：`debug` 仅本地；线上 `info` 起。
- 保留：本地 7 天，远程 90 天。
- **禁止记录**：密码、session id、明文答案。

### 15.2 指标（Prometheus 文本格式，路径 `/metrics`）

```
vocab_http_requests_total{route,method,status}
vocab_http_request_duration_seconds_bucket{route,method,le}
vocab_review_answers_total{quality}
vocab_words_total{user_id}
vocab_db_query_seconds_bucket{query_name,le}
vocab_cache_hits_total{cache_name}
vocab_login_failures_total{ip}
```

### 15.3 告警规则

- 5xx 比例 > 1%（5 分钟窗口）→ Pager。
- P95 延迟 > SLO 2 倍（10 分钟窗口）→ Slack。
- 登录失败突增 5× → 提示可能扫描。
- 磁盘使用 > 80% → 邮件。

### 15.4 错误追踪

- Sentry：捕获未捕获异常、按用户聚合。
- 关键事务（复习提交）打点，定位慢请求。

---

## 16. 备份与容灾

### 16.1 备份策略

- 数据库：每日 03:00 全量 + 每 15 分钟 binlog 增量。
- 文件（用户上传的音频等）：每日增量 rsync 到 OSS 兼容存储。
- 备份保留：本地 7 天、异地 30 天、年终永久 1 份。

### 16.2 恢复演练

- 季度演练：拉取最近一次全量 + binlog，在隔离环境恢复到任意时间点。
- RPO ≤ 15 分钟、RTO ≤ 1 小时。

### 16.3 导出兜底

- 用户可一键导出全量（JSON + CSV），作为「最后兜底」，不依赖平台。

---

## 17. 分阶段实施路线图

> 总周期 24 周（约 6 个月）。每个里程碑结束都产出可上线版本。

### M0 · 基线与可观测（Week 1~2）

- [ ] 引入 Composer、`.env`、PSR-4 目录骨架（不破坏现有页面）。
- [ ] 抽离 `Database` 单例为注入式 `Connection`。
- [ ] 接入 Monolog + Sentry。
- [ ] 添加 `/healthz`、基础 `/metrics`。
- [ ] 部署脚本（rsync + reload）。
- **验收**：CI 跑通；故障可定位。

### M1 · 多用户 + 安全基线（Week 3~6）

- [ ] `users` 表 + 迁移（admin 默认账号）。
- [ ] 注册 / 登录 / 登出 / 修改密码。
- [ ] 登录限速、密码策略、argon2id。
- [ ] CSRF token 中间件。
- [ ] 资源越权统一拦截。
- [ ] `words` 等表加 `user_id`，按用户隔离。
- [ ] 审计日志。
- **验收**：A 用户无法看到 B 用户数据；暴力破解 5 次后被锁。

### M2 · 标准 SM-2 + 复习 UX（Week 7~9）

- [ ] 实现 `Sm2` 领域类 + 全量单测。
- [ ] `review_logs` 双值字段改造。
- [ ] 复习页 4 档按钮 + 快捷键。
- [ ] 复习页：「显示例句回忆」模式。
- **验收**：算法对照 SuperMemo 文档单测全绿；复习体验主观 ≥ 8/10。

### M3 · 编辑 / 删除 / 导入导出（Week 10~12）

- [ ] `edit.php` 复用 `add.php`，乐观锁。
- [ ] 删除走软删除 + 二次确认。
- [ ] CSV 导入（编码自动识别、行级错误回执）。
- [ ] 导出 JSON / CSV。
- [ ] 全文搜索（FULLTEXT ngram）。
- **验收**：1 万行 CSV 导入成功率 ≥ 99.5%，错误行可下载。

### M4 · 统计仪表板 + PWA（Week 13~16）

- [ ] `study_stats_daily` 物化 + 后台聚合。
- [ ] Dashboard：KPI 卡、热力图、熟练度分布、话题雷达。
- [ ] 响应式栅格 + 移动端核心页验收。
- [ ] Service Worker 缓存与离线降级。
- [ ] Web App Manifest。
- **验收**：Lighthouse PWA ≥ 90；离线可继续复习。

### M5 · 进阶学习能力（Week 17~20）

- [ ] 语音发音（先 HTML5 SpeechSynthesis，必要时接 API）。
- [ ] 同义词网（vis-network）。
- [ ] 雅思场景任务：内置话题词库 + 替换词推荐（数据源 → `data/ielts_topics.json`）。
- [ ] 输出训练打卡（字数 / 词频统计）。
- **验收**：话题练习闭环可用。

### M6 · 开放 API + 协作雏形（Week 21~24）

- [ ] `/api/v1` 全量开放 + OpenAPI 文档。
- [ ] API Key 管理（个人/班级）。
- [ ] 班级概念（teacher/student 角色 + 词库共享 + 学习画像）。
- [ ] 文档站（mkdocs）。
- **验收**：第三方脚本能拉取队列、提交答案、读取统计。

### M7+ · 持续演进

- L3 多租户 / 订阅 / AI 推荐例句（仅在 M6 数据基础具备后启动）。

---

## 18. 风险登记册与缓解策略

| ID | 风险 | 概率 | 影响 | 缓解 |
|----|------|------|------|------|
| R1 | MySQL 凭据明文入库 | 中 | 高 | 立即迁移到 `.env`、轮换密码、加访问白名单 |
| R2 | SM-2 改造导致历史复习节奏被打断 | 中 | 中 | 旧字段保留并并行写入；后台脚本双写回填；提供「按旧数据重算」开关 |
| R3 | 全文索引对中文性能不佳 | 中 | 中 | 用 ngram parser；3 字符以下 fallback LIKE；监控慢查询 |
| R4 | 大量用户同时复习触发雪崩 | 低 | 高 | 复习提交走消息队列（后期可加 Redis Stream），先做本地节流 |
| R5 | PWA 离线缓存陈旧导致错乱 | 中 | 中 | 资源带哈希；接口 NetworkOnly；版本号提示刷新 |
| R6 | 第三方 TTS / 词典 API 限流 | 中 | 低 | 多源 fallback；本地缓存；最终退化为 SpeechSynthesis |
| R7 | 数据迁移丢词 | 低 | 极高 | 迁移前全量备份；脚本幂等；二次回放校验 |
| R8 | 误删/恶意清空 | 低 | 高 | 软删除 + 7 天回收站 + 审计 + 二次确认 |
| R9 | 单点故障（单机） | 中 | 中 | 监控告警 + 备份演练；中期引入第二可用区 |
| R10 | 过度工程、节奏失控 | 中 | 中 | 严格 M0→M1→… 顺序；每个 M 必须有可演示产出 |

---

## 19. 团队、角色与协作

> 单人维护期：建议保持「1 主力 + 1 Code Reviewer（外部）」。

| 角色 | 关键职责 |
|------|----------|
| Product Owner | 优先级、验收标准、用户访谈 |
| Tech Lead | 架构、Code Review、风险把关 |
| Backend | PHP / MySQL / 部署脚本 |
| Frontend | CSS / JS / PWA / 图表 |
| QA | 测试用例 / 自动化 / 验收 |
| SRE（兼职） | 监控 / 备份 / 应急响应 |

- 节奏：周一双周会 / 周三技术分享 / 周五 Demo。
- 文档：所有 ADR 进 `docs/adr/`，每次重构先写 ADR 再写代码。
- Code Review：每个 PR 至少 1 人 approve；超过 400 行改动需拆分。

---

## 20. 成本与资源估算

### 20.1 基础设施（生产 / 月）

| 资源 | 规格 | 估算 |
|------|------|------|
| VPS | 2C4G SSD 80G | ¥60~120 |
| 域名 | .com | ¥10/月摊 |
| MySQL | 同机或托管 RDS | ¥0~200 |
| 对象存储 | 备份 10G | ¥5 |
| Sentry | 免费版 | ¥0 |
| 监控 | UptimeRobot 免费 | ¥0 |
| **小计** | — | **¥100~350 / 月** |

### 20.2 人力

- 单人 1 天 / 周 × 24 周 ≈ 24 人日（M0~M6 合计）。
- M6 之后按实际增长评估是否扩到 2 人。

### 20.3 ROI

- 复用价值：M3 导入导出后，班级场景可承接 B 端付费（按词库订阅），回本周期可期。
- 学习价值：个人掌握企业级开发完整链路（架构、安全、运维、测试）。

---

## 21. 验收与成功指标（KPI）

### 21.1 工程质量

- 单元测试覆盖率 ≥ 80%（领域层 ≥ 90%）。
- CI 流水线通过率 ≥ 95%。
- 静态分析 0 error。
- 高危漏洞（依赖 / OWASP Top 10）= 0。

### 21.2 运行时

- P95 延迟达标（见 §12.1）。
- 月度可用性 ≥ 99.5%。
- 备份恢复演练每季度 1 次，RPO/RTO 达标。

### 21.3 业务

- 周活跃复习率 ≥ 60%（次周仍回来复习）。
- 30 天单词留存熟练度（proficiency ≥ 3）≥ 40%。
- NPS ≥ 40（季度调研）。

---

## 附录 A：目录结构演进

```
vocab_bank/
├── bin/                      # CLI 工具
├── config/                   # 纯配置（不存凭据）
├── public/                   # Web 根（document root）
│   ├── index.php             # 入口
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   ├── img/
│   │   └── manifest.webmanifest
│   └── service-worker.js
├── src/
│   ├── Auth/
│   ├── Domain/               # 实体 + 领域服务
│   │   ├── Word/
│   │   ├── Review/
│   │   └── User/
│   ├── Application/          # 用例 / Service
│   ├── Infrastructure/       # PDO、Cache、Logger、Mail
│   ├── Http/                 # Controller / Middleware
│   └── Support/              # 工具类
├── views/                    # PHP 模板
├── migrations/               # 数据库迁移
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── E2E/
├── storage/
│   ├── cache/
│   ├── logs/
│   └── backups/
├── docs/adr/
├── .env.example
├── composer.json
├── phpunit.xml
└── README.md
```

> 迁移建议：先把现有 `*.php` 移到 `public/` 作为入口；`includes/` 内容拆到 `src/` 对应命名空间。

---

## 附录 B：迁移脚本示例

### B.1 安装默认管理员

```php
// migrations/2026_06_10_000001_create_default_admin.php
public function up(PDO $pdo): void
{
    $hash = password_hash('ChangeMe!2026', PASSWORD_ARGON2ID);
    $pdo->prepare("INSERT INTO users (username, email, password_hash, role, created_at, updated_at)
                   VALUES ('admin', 'admin@example.com', :h, 'admin', NOW(), NOW())")
        ->execute(['h' => $hash]);
}
public function down(PDO $pdo): void
{
    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
}
```

### B.2 现有单词分配给默认用户

```php
// migrations/2026_06_10_000002_assign_existing_words_to_admin.php
public function up(PDO $pdo): void
{
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
    $pdo->exec("UPDATE words SET user_id = {$adminId} WHERE user_id IS NULL");
    $pdo->exec("ALTER TABLE words MODIFY user_id BIGINT UNSIGNED NOT NULL");
}
```

### B.3 重建 SM-2 历史（可选）

读取旧 `review_logs`，用 `quality >= 3` 判定通过 / 失败，**重算** EF/rep/interval 回填 `words`，并将旧记录迁移至双值结构（`ef_before=2.50, ef_after=新值`，`rep_before=0, rep_after=新值`）。

---

## 附录 C：术语表

| 术语 | 含义 |
|------|------|
| **SM-2** | SuperMemo-2 间隔重复算法，本项目核心复习调度策略 |
| **EF** | Easiness Factor，简易度，越高间隔增长越快 |
| **rep** | Repetition，连续通过次数 |
| **interval** | 距下次复习的天数 |
| **quality** | 用户自评 0~5，< 3 视为失败 |
| **proficiency** | 面向用户展示的熟练度（0~5 星），与 EF 解耦 |
| **ngram parser** | MySQL 内置中文分词器，对 CJK 友好 |
| **soft delete** | 软删除：通过 `deleted_at` 标记，保留数据 |
| **PWA** | Progressive Web App，可安装、可离线 |
| **CSP** | Content Security Policy，缓解 XSS |
| **CSRF** | Cross-Site Request Forgery，跨站请求伪造 |
| **RPO / RTO** | 恢复点目标 / 恢复时间目标 |
| **ADR** | Architecture Decision Record，架构决策记录 |

---

*文档维护者：Tech Lead · 评审周期：每月一次 · 变更需经 PR 流程*
