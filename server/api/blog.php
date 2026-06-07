<?php
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

// 会话必须在任何输出之前启动
session_start();

// 兜底捕获所有致命错误
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(['error' => '服务器内部错误: ' . $error['message']], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/../../vendor/autoload.php';

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

function loadDbConfig(): array
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

function connectDb(): PDO
{
    $config = loadDbConfig();
    $dsn = configValue($config, 'dsn', envValue(['GUESTBOOK_DB_DSN', 'DB_DSN']));
    $user = configValue($config, 'user', envValue(['GUESTBOOK_DB_USER', 'DB_USER']));
    $password = configValue($config, 'password', envValue(['GUESTBOOK_DB_PASSWORD', 'DB_PASSWORD']));
    if ($dsn === '') respond(500, ['error' => '数据库未配置']);
    try {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) { respond(500, ['error' => '数据库连接失败']); }
}

function initTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL, slug VARCHAR(40) NOT NULL,
        created_at VARCHAR(40) NOT NULL,
        UNIQUE KEY uk_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL, slug VARCHAR(220) NOT NULL,
        content_md TEXT NOT NULL, content_html TEXT NOT NULL,
        excerpt VARCHAR(500) NOT NULL DEFAULT '',
        category_id INT UNSIGNED DEFAULT NULL,
        cover_image VARCHAR(500) NOT NULL DEFAULT '',
        tags_json TEXT,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        views INT UNSIGNED NOT NULL DEFAULT 0,
        likes INT UNSIGNED NOT NULL DEFAULT 0,
        created_at VARCHAR(40) NOT NULL, updated_at VARCHAR(40) NOT NULL,
        UNIQUE KEY uk_slug (slug),
        KEY idx_category (category_id), KEY idx_published (is_published), KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try { $pdo->exec("ALTER TABLE blog_posts ADD COLUMN tags_json TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE blog_posts ADD COLUMN likes INT UNSIGNED NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_likes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            UNIQUE KEY uk_like (post_id, ip_hash),
            KEY idx_post (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

function slugify(string $text): string
{
    $text = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s-]/u', '', $text);
    $text = preg_replace('/[\s]+/', '-', trim($text));
    $text = preg_replace('/-+/', '-', $text);
    return strtolower(trim($text, '-')) ?: 'post-' . time();
}

function requireAuth(): void
{
    if (empty($_SESSION['blog_authed'])) {
        respond(401, ['error' => '未授权，请先登录']);
    }
}

function getAdminPassword(): string
{
    return envValue(['DRIVE_PASSWORD', 'BLOG_ADMIN_PASSWORD']);
}

function sanitizeStr(string $s, int $max = 500): string
{
    $s = trim(strip_tags($s));
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max);
    }
    return substr($s, 0, $max);
}

// ============================================================
//  初始化
// ============================================================

$pdo = connectDb();
initTables($pdo);
$converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
    'html_input' => 'strip',
    'allow_unsafe_links' => false,
]);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// ============================================================
//  GET — 前台接口
// ============================================================

if ($method === 'GET') {
    // ── 文章列表 ──
    if ($action === 'posts') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;
        $category = $_GET['category'] ?? '';
        $tag = $_GET['tag'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $where = "p.is_published = 1";
        $params = [];

        if ($category !== '') {
            $catStmt = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = ?");
            $catStmt->execute([$category]);
            $catId = $catStmt->fetchColumn();
            if ($catId) { $where .= " AND p.category_id = ?"; $params[] = (int)$catId; }
        }
        if ($tag !== '') {
            $where .= " AND p.tags_json IS NOT NULL AND p.tags_json LIKE ?";
            $params[] = '%"' . $tag . '"%';
        }
        if ($search !== '') {
            $where .= " AND (p.title LIKE ? OR p.excerpt LIKE ?)";
            $kw = '%' . $search . '%';
            $params[] = $kw; $params[] = $kw;
        }

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p WHERE $where");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $limit));

        $sql = "SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.tags_json, p.views, p.likes, p.created_at,
                       c.name AS category_name, c.slug AS category_slug
                FROM blog_posts p
                LEFT JOIN blog_categories c ON p.category_id = c.id
                WHERE $where
                ORDER BY p.created_at DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        foreach ($posts as &$post) {
            $decoded = json_decode($post['tags_json'] ?? '[]', true);
            $post['tags'] = is_array($decoded) ? array_map(fn($n) => ['name' => $n], $decoded) : [];
            unset($post['tags_json']);
            // 评论数（source 列可能尚未由 guestbook.php 创建）
            try {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM guestbook_entries WHERE source = ?");
                $cnt->execute(['blog:' . $post['id']]);
                $post['comment_count'] = (int)$cnt->fetchColumn();
            } catch (Throwable $e) { $post['comment_count'] = 0; }
        }
        unset($post);

        respond(200, ['posts' => $posts, 'pagination' => ['page' => $page, 'totalPages' => $totalPages, 'total' => $total]]);
    }

    // ── 单篇文章 ──
    if ($action === 'post') {
        $slug = $_GET['slug'] ?? '';
        if ($slug === '') respond(400, ['error' => '缺少 slug']);

        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id
             WHERE p.slug = ? AND p.is_published = 1"
        );
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        if (!$post) respond(404, ['error' => '文章不存在']);

        $decoded = json_decode($post['tags_json'] ?? '[]', true);
        $post['tags'] = is_array($decoded) ? array_map(fn($n) => ['name' => $n], $decoded) : [];
        unset($post['tags_json']);

        $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([(int)$post['id']]);

        // 评论数
        try {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM guestbook_entries WHERE source = ?");
            $cnt->execute(['blog:' . $post['id']]);
            $post['comment_count'] = (int)$cnt->fetchColumn();
        } catch (Throwable $e) { $post['comment_count'] = 0; }

        // 当前访客是否已点赞
        $vHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $likeStmt = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND ip_hash = ?");
        $likeStmt->execute([(int)$post['id'], $vHash]);
        $post['liked'] = (bool)$likeStmt->fetch();

        respond(200, ['post' => $post]);
    }

    // ── 分类列表 ──
    if ($action === 'categories') {
        $stmt = $pdo->query(
            "SELECT c.*, COUNT(p.id) AS post_count FROM blog_categories c
             LEFT JOIN blog_posts p ON p.category_id = c.id AND p.is_published = 1
             GROUP BY c.id ORDER BY c.name"
        );
        respond(200, ['categories' => $stmt->fetchAll()]);
    }

    // ── 管理后台：文章列表（含未发布） ──
    if ($action === 'admin_posts') {
        requireAuth();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $cntStmt = $pdo->query("SELECT COUNT(*) FROM blog_posts");
        $total = (int)$cntStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS category_name FROM blog_posts p
             LEFT JOIN blog_categories c ON p.category_id = c.id
             ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        respond(200, ['posts' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'totalPages' => max(1, (int)ceil($total / $limit))]);
    }

    respond(400, ['error' => '未知操作']);
}

// ============================================================
//  POST — 管理后台接口
// ============================================================

if ($method !== 'POST') respond(405, ['error' => 'Method not allowed']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ── 登录 ──
if ($action === 'login') {
    $pw = $input['password'] ?? '';
    $stored = getAdminPassword();
    if ($stored === '') respond(500, ['error' => '未配置管理员密码']);
    if ($pw !== $stored) respond(403, ['error' => '密码错误']);
    $_SESSION['blog_authed'] = true;
    respond(200, ['ok' => true]);
}

// ── 文章点赞/取消 ──（无需登录）
if ($action === 'post_like') {
    $postId = (int)($input['post_id'] ?? 0);
    if ($postId <= 0) respond(400, ['error' => '缺少 post_id']);
    $vHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $existStmt = $pdo->prepare("SELECT id FROM blog_likes WHERE post_id = ? AND ip_hash = ?");
    $existStmt->execute([$postId, $vHash]);
    $existing = $existStmt->fetch();
    if ($existing) {
        $pdo->prepare("DELETE FROM blog_likes WHERE id = ?")->execute([$existing['id']]);
        $pdo->prepare("UPDATE blog_posts SET likes = GREATEST(likes - 1, 0) WHERE id = ?")->execute([$postId]);
        $newLikes = $pdo->query("SELECT likes FROM blog_posts WHERE id = $postId")->fetchColumn();
        respond(200, ['ok' => true, 'action' => 'unliked', 'likes' => (int)$newLikes]);
    } else {
        $pdo->prepare("INSERT INTO blog_likes (post_id, ip_hash, created_at) VALUES (?,?,?)")->execute([$postId, $vHash, gmdate('c')]);
        $pdo->prepare("UPDATE blog_posts SET likes = likes + 1 WHERE id = ?")->execute([$postId]);
        $newLikes = $pdo->query("SELECT likes FROM blog_posts WHERE id = $postId")->fetchColumn();
        respond(200, ['ok' => true, 'action' => 'liked', 'likes' => (int)$newLikes]);
    }
    exit;
}

requireAuth();

// ── 创建文章 ──
if ($action === 'create') {
    $title = sanitizeStr($input['title'] ?? '', 200);
    $contentMd = $input['content_md'] ?? '';
    $excerpt = sanitizeStr($input['excerpt'] ?? '', 500);
    $categoryId = $input['category_id'] ? (int)$input['category_id'] : null;
    $coverImage = sanitizeStr($input['cover_image'] ?? '', 500);
    $isPublished = !empty($input['is_published']) ? 1 : 0;
    $tagNames = isset($input['tags']) && is_array($input['tags']) ? $input['tags'] : [];
    $tagNames = array_values(array_unique(array_filter(array_map(fn($t) => trim((string)$t), $tagNames), fn($t) => $t !== '')));
    $tagsJson = json_encode($tagNames, JSON_UNESCAPED_UNICODE);

    if ($title === '') respond(400, ['error' => '标题不能为空']);
    if ($excerpt === '') $excerpt = function_exists('mb_substr') ? mb_substr(strip_tags($contentMd), 0, 200) : substr(strip_tags($contentMd), 0, 200);

    $slug = slugify($title);
    // 确保唯一
    $existStmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
    $existStmt->execute([$slug]);
    if ($existStmt->fetch()) $slug .= '-' . time();

    $contentHtml = $converter->convert($contentMd)->getContent();
    $now = gmdate('c');

    $pdo->prepare(
        "INSERT INTO blog_posts (title, slug, content_md, content_html, excerpt, category_id, cover_image, tags_json, is_published, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([$title, $slug, $contentMd, $contentHtml, $excerpt, $categoryId, $coverImage, $tagsJson, $isPublished, $now, $now]);
    respond(200, ['ok' => true, 'slug' => $slug]);
}

// ── 更新文章 ──
if ($action === 'update') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) respond(400, ['error' => '缺少 id']);

    $title = sanitizeStr($input['title'] ?? '', 200);
    $contentMd = $input['content_md'] ?? '';
    $excerpt = sanitizeStr($input['excerpt'] ?? '', 500);
    $categoryId = $input['category_id'] ? (int)$input['category_id'] : null;
    $coverImage = sanitizeStr($input['cover_image'] ?? '', 500);
    $isPublished = !empty($input['is_published']) ? 1 : 0;
    $tagNames = isset($input['tags']) && is_array($input['tags']) ? $input['tags'] : [];
    $tagNames = array_values(array_unique(array_filter(array_map(fn($t) => trim((string)$t), $tagNames), fn($t) => $t !== '')));
    $tagsJson = json_encode($tagNames, JSON_UNESCAPED_UNICODE);

    if ($title === '') respond(400, ['error' => '标题不能为空']);
    if ($excerpt === '') $excerpt = function_exists('mb_substr') ? mb_substr(strip_tags($contentMd), 0, 200) : substr(strip_tags($contentMd), 0, 200);

    $contentHtml = $converter->convert($contentMd)->getContent();
    $now = gmdate('c');

    $pdo->prepare(
        "UPDATE blog_posts SET title=?, content_md=?, content_html=?, excerpt=?, category_id=?, cover_image=?, tags_json=?, is_published=?, updated_at=? WHERE id=?"
    )->execute([$title, $contentMd, $contentHtml, $excerpt, $categoryId, $coverImage, $tagsJson, $isPublished, $now, $id]);
    respond(200, ['ok' => true]);
}

// ── 删除文章 ──
if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) respond(400, ['error' => '缺少 id']);
    $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
    respond(200, ['ok' => true]);
}

// ── 创建分类 ──
if ($action === 'create_category') {
    $name = sanitizeStr($input['name'] ?? '', 30);
    if ($name === '') respond(400, ['error' => '名称不能为空']);
    $slug = slugify($name);
    $pdo->prepare("INSERT IGNORE INTO blog_categories (name, slug, created_at) VALUES (?,?,?)")->execute([$name, $slug, gmdate('c')]);
    respond(200, ['ok' => true, 'slug' => $slug]);
}

// ── 删除分类 ──
if ($action === 'delete_category') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) respond(400, ['error' => '缺少 id']);
    $pdo->prepare("UPDATE blog_posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$id]);
    respond(200, ['ok' => true]);
}

respond(400, ['error' => '未知操作']);
