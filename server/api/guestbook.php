<?php
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('html_errors', '0');
header('Content-Type: application/json; charset=utf-8');

// ============================================================
//  工具函数
// ============================================================

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function envValue(array $names, string $default = ''): string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value === false && isset($_SERVER[$name])) { $value = $_SERVER[$name]; }
        if ($value === false && isset($_ENV[$name])) { $value = $_ENV[$name]; }
        if ($value !== false && trim($value) !== '') { return trim($value); }
    }
    return $default;
}

function loadGuestbookConfig(): array
{
    $configFile = __DIR__ . '/guestbook.config.php';
    if (!is_file($configFile)) return [];
    $config = require $configFile;
    return is_array($config) ? $config : [];
}

function configValue(array $config, string $key, string $default = ''): string
{
    $value = $config[$key] ?? $default;
    if (is_string($value)) { $value = trim($value); return $value === '' ? $default : $value; }
    if (is_int($value) || is_float($value)) { return trim((string)$value); }
    return $default;
}

function resolveTableName(): string
{
    $config = loadGuestbookConfig();
    $tableName = configValue($config, 'table', envValue(['GUESTBOOK_DB_TABLE', 'DB_TABLE'], 'guestbook_entries'));
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) { return 'guestbook_entries'; }
    return $tableName;
}

function resolveLikesTableName(): string
{
    return resolveTableName() . '_likes';
}

function connectDatabase(): PDO
{
    $config = loadGuestbookConfig();
    $dsn = configValue($config, 'dsn', envValue(['GUESTBOOK_DB_DSN', 'DB_DSN']));
    $user = configValue($config, 'user', envValue(['GUESTBOOK_DB_USER', 'DB_USER']));
    $password = configValue($config, 'password', envValue(['GUESTBOOK_DB_PASSWORD', 'DB_PASSWORD']));

    if ($dsn === '') {
        respond(500, ['error' => '数据库未配置', 'hint' => '请创建 server/api/guestbook.config.php']);
    }
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        respond(500, ['error' => '数据库连接失败', 'hint' => $e->getMessage()]);
    }
    return $pdo;
}

function initializeSchema(PDO $pdo, string $tableName, string $likesTable): void
{
    // 主评论表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `parent_id` BIGINT UNSIGNED DEFAULT NULL,
        `root_id` BIGINT UNSIGNED DEFAULT NULL,
        `name` VARCHAR(40) NOT NULL,
        `email` VARCHAR(100) NOT NULL DEFAULT '',
        `message` VARCHAR(500) NOT NULL,
        `likes` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` VARCHAR(40) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_root` (`root_id`),
        KEY `idx_parent` (`parent_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 兼容旧表: 尝试添加新列 (忽略重复列错误)
    try { $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `root_id` BIGINT UNSIGNED DEFAULT NULL AFTER `parent_id`"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `likes` INT UNSIGNED NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `$tableName` ADD KEY `idx_root` (`root_id`)"); } catch (Throwable $e) {}

    // 点赞记录表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$likesTable` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `comment_id` BIGINT UNSIGNED NOT NULL,
        `ip_hash` VARCHAR(64) NOT NULL,
        `created_at` VARCHAR(40) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_like` (`comment_id`, `ip_hash`),
        KEY `idx_comment` (`comment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 修复旧数据: root_id 为 NULL 的一级评论设为自身 id
    $pdo->exec("UPDATE `$tableName` SET `root_id` = `id` WHERE `parent_id` IS NULL AND `root_id` IS NULL");

    // 修复旧回复: 直接回复一级评论的 → root_id = parent_id
    $pdo->exec("UPDATE `$tableName` r1
        JOIN `$tableName` r2 ON r1.parent_id = r2.id
        SET r1.root_id = r2.id
        WHERE r1.root_id IS NULL AND r1.parent_id IS NOT NULL AND r2.parent_id IS NULL");

    // 修复旧回复: 回复其他回复的 → root_id = 父回复的 root_id
    $pdo->exec("UPDATE `$tableName` r1
        JOIN `$tableName` r2 ON r1.parent_id = r2.id
        SET r1.root_id = r2.root_id
        WHERE r1.root_id IS NULL AND r1.parent_id IS NOT NULL AND r2.root_id IS NOT NULL");
}

function visitorHash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function sanitizeText(?string $value): string
{
    $value = trim((string)$value);
    $value = strip_tags($value);
    $cleaned = preg_replace('/\s+/u', ' ', $value);
    return is_string($cleaned) ? trim($cleaned) : '';
}

function sanitizeMessage(?string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", (string)$value);
    $value = trim(strip_tags($value));
    $lines = explode("\n", $value);
    $lines = array_map(static fn(string $line): string => rtrim($line), $lines);
    return implode("\n", $lines);
}

function readRequestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// ============================================================
//  初始化
// ============================================================

$tableName = resolveTableName();
$likesTable = resolveLikesTableName();
$pdo = connectDatabase();
initializeSchema($pdo, $tableName, $likesTable);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// ============================================================
//  GET — 获取评论列表 (分页 + 楼中楼 + 点赞状态)
// ============================================================

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $sort = $_GET['sort'] ?? 'newest';

    $orderBy = match ($sort) {
        'oldest' => 'id ASC',
        'hottest' => 'likes DESC, id DESC',
        default => 'id DESC',
    };

    // 一级评论总数
    $cntStmt = $pdo->query("SELECT COUNT(*) FROM `$tableName` WHERE parent_id IS NULL");
    $total = $cntStmt ? (int)$cntStmt->fetchColumn() : 0;
    $totalPages = max(1, (int)ceil($total / $limit));
    $offset = ($page - 1) * $limit;

    // 一级评论
    $stmt = $pdo->prepare(
        "SELECT id, name, email, message, likes, created_at AS createdAt
         FROM `$tableName`
         WHERE parent_id IS NULL
         ORDER BY $orderBy
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    $visitor = visitorHash();

    // 已点赞的评论 ID
    $likedIds = [];
    if (!empty($items)) {
        $allIds = array_column($items, 'id');
        $ph = implode(',', array_fill(0, count($allIds), '?'));
        $likeStmt = $pdo->prepare("SELECT comment_id FROM `$likesTable` WHERE ip_hash = ? AND comment_id IN ($ph)");
        $likeStmt->execute(array_merge([$visitor], $allIds));
        $likedIds = $likeStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 楼中楼回复 (每条一级评论最多展示5条预览)
    $replyStmt = $pdo->prepare(
        "SELECT id, parent_id, root_id, name, message, likes, created_at AS createdAt
         FROM `$tableName`
         WHERE root_id = :root AND parent_id IS NOT NULL
         ORDER BY id ASC
         LIMIT 5"
    );
    $replyCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `$tableName` WHERE root_id = :root AND parent_id IS NOT NULL"
    );

    foreach ($items as &$item) {
        $item['liked'] = in_array($item['id'], $likedIds, true);

        $replyStmt->execute([':root' => $item['id']]);
        $replies = $replyStmt->fetchAll();

        $replyCountStmt->execute([':root' => $item['id']]);
        $totalReplies = (int)$replyCountStmt->fetchColumn();

        if (!empty($replies)) {
            $rIds = array_column($replies, 'id');
            $rph = implode(',', array_fill(0, count($rIds), '?'));
            $rLikeStmt = $pdo->prepare("SELECT comment_id FROM `$likesTable` WHERE ip_hash = ? AND comment_id IN ($rph)");
            $rLikeStmt->execute(array_merge([$visitor], $rIds));
            $rLikedIds = $rLikeStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($replies as &$r) {
                $r['liked'] = in_array($r['id'], $rLikedIds, true);
            }
            unset($r);
        }

        $item['replies'] = $replies;
        $item['replyCount'] = $totalReplies;
        $item['hasMoreReplies'] = $totalReplies > 5;
    }
    unset($item);

    respond(200, [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'limit' => $limit,
        ],
    ]);
}

// ============================================================
//  POST — 发评论 / 点赞
// ============================================================

if ($method !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

$input = readRequestBody();
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

// ── 点赞/取消点赞 ──
if ($action === 'like') {
    $commentId = (int)($input['comment_id'] ?? 0);
    if ($commentId <= 0) {
        respond(400, ['error' => '缺少 comment_id']);
    }

    $check = $pdo->prepare("SELECT id, likes FROM `$tableName` WHERE id = ?");
    $check->execute([$commentId]);
    $comment = $check->fetch();
    if (!$comment) {
        respond(404, ['error' => '评论不存在']);
    }

    $visitor = visitorHash();

    $existStmt = $pdo->prepare("SELECT id FROM `$likesTable` WHERE comment_id = ? AND ip_hash = ?");
    $existStmt->execute([$commentId, $visitor]);
    $existing = $existStmt->fetch();

    if ($existing) {
        // 取消点赞
        $pdo->prepare("DELETE FROM `$likesTable` WHERE id = ?")->execute([$existing['id']]);
        $pdo->prepare("UPDATE `$tableName` SET likes = GREATEST(likes - 1, 0) WHERE id = ?")->execute([$commentId]);
        $check->execute([$commentId]);
        $updated = $check->fetch();
        respond(200, ['ok' => true, 'action' => 'unliked', 'likes' => (int)($updated['likes'] ?? 0)]);
    } else {
        // 点赞
        $pdo->prepare("INSERT INTO `$likesTable` (comment_id, ip_hash, created_at) VALUES (?, ?, ?)")
            ->execute([$commentId, $visitor, gmdate('c')]);
        $pdo->prepare("UPDATE `$tableName` SET likes = likes + 1 WHERE id = ?")->execute([$commentId]);
        $check->execute([$commentId]);
        $updated = $check->fetch();
        respond(200, ['ok' => true, 'action' => 'liked', 'likes' => (int)($updated['likes'] ?? 0)]);
    }
    exit;
}

// ── 发布评论/回复 ──

$name = sanitizeText($input['name'] ?? '');
$email = sanitizeText($input['email'] ?? '');
$message = sanitizeMessage($input['message'] ?? '');
$parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;

if ($name === '' || $message === '') {
    respond(400, ['error' => '昵称和内容不能为空']);
}

$maxLen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
if ($maxLen($name) > 40 || $maxLen($email) > 100 || $maxLen($message) > 500) {
    respond(400, ['error' => '输入内容过长']);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['error' => '邮箱格式不正确']);
}

try {
    $createdAt = gmdate('c');

    if ($parentId > 0) {
        // 查找根评论 ID
        $rootStmt = $pdo->prepare("SELECT root_id FROM `$tableName` WHERE id = ? AND parent_id IS NULL");
        $rootStmt->execute([$parentId]);
        $root = $rootStmt->fetch();

        if ($root) {
            $rootId = $parentId;
        } else {
            $pStmt = $pdo->prepare("SELECT root_id FROM `$tableName` WHERE id = ?");
            $pStmt->execute([$parentId]);
            $pRow = $pStmt->fetch();
            $rootId = $pRow ? (int)$pRow['root_id'] : $parentId;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO `$tableName` (parent_id, root_id, name, email, message, created_at) VALUES (:pid, :rid, :name, :email, :msg, :ca)"
        );
        $stmt->execute([':pid' => $parentId, ':rid' => $rootId, ':name' => $name, ':email' => $email, ':msg' => $message, ':ca' => $createdAt]);
    } else {
        $pdo->prepare(
            "INSERT INTO `$tableName` (name, email, message, created_at) VALUES (:name, :email, :msg, :ca)"
        )->execute([':name' => $name, ':email' => $email, ':msg' => $message, ':ca' => $createdAt]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE `$tableName` SET root_id = ? WHERE id = ?")->execute([$newId, $newId]);
    }
} catch (Throwable $e) {
    respond(500, ['error' => '保存失败', 'hint' => $e->getMessage()]);
}

respond(200, [
    'success' => true,
    'item' => [
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'createdAt' => $createdAt,
    ],
]);
