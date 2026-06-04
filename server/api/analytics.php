<?php
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
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

function connectDatabase(): PDO
{
    $config = loadDbConfig();
    $dsn = configValue($config, 'dsn', envValue(['GUESTBOOK_DB_DSN', 'DB_DSN']));
    $user = configValue($config, 'user', envValue(['GUESTBOOK_DB_USER', 'DB_USER']));
    $password = configValue($config, 'password', envValue(['GUESTBOOK_DB_PASSWORD', 'DB_PASSWORD']));

    if ($dsn === '') {
        respond(500, ['error' => '数据库未配置']);
    }
    try {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        respond(500, ['error' => '数据库连接失败']);
    }
}

function initTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_pv (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        page VARCHAR(100) NOT NULL DEFAULT '',
        ip_hash VARCHAR(64) NOT NULL,
        ip_raw VARCHAR(45) NOT NULL DEFAULT '',
        country VARCHAR(100) NOT NULL DEFAULT '',
        province VARCHAR(100) NOT NULL DEFAULT '',
        created_at VARCHAR(40) NOT NULL,
        KEY idx_date (created_at),
        KEY idx_page (page),
        KEY idx_ip (ip_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_modules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        module VARCHAR(50) NOT NULL,
        ip_hash VARCHAR(64) NOT NULL,
        created_at VARCHAR(40) NOT NULL,
        KEY idx_module (module),
        KEY idx_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_online (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        ip_hash VARCHAR(64) NOT NULL,
        country VARCHAR(100) NOT NULL DEFAULT '',
        province VARCHAR(100) NOT NULL DEFAULT '',
        page VARCHAR(100) NOT NULL DEFAULT '',
        last_active INT UNSIGNED NOT NULL,
        created_at VARCHAR(40) NOT NULL,
        UNIQUE KEY uk_session (session_id),
        KEY idx_active (last_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function visitorHash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function visitorIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function geoLocation(string $ip): array
{
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
        return ['country' => '本地', 'province' => '开发环境'];
    }
    // 使用本地缓存避免重复请求
    $cacheDir = __DIR__ . '/../data/';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . 'ip_geo.json';
    $cache = [];
    if (is_file($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    $ipKey = md5($ip);
    if (isset($cache[$ipKey])) {
        return $cache[$ipKey];
    }
    // 调用 ip-api.com (免费, 45次/分钟)
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?lang=zh-CN&fields=country,regionName", false, $ctx);
    $result = ['country' => '', 'province' => ''];
    if ($response) {
        $data = json_decode($response, true);
        if (is_array($data) && ($data['country'] ?? '') !== '') {
            $result = ['country' => $data['country'] ?? '', 'province' => $data['regionName'] ?? ''];
        }
    }
    // 缓存 7 天
    $cache[$ipKey] = $result;
    if (count($cache) > 500) $cache = array_slice($cache, -400, 400, true);
    file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
    return $result;
}

// ============================================================
//  初始化
// ============================================================

$pdo = connectDatabase();
initTables($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// ============================================================
//  GET — 看板数据 & 实时在线人数
// ============================================================

if ($method === 'GET') {
    if ($action === 'dashboard') {
        // ── 7 天 PV/UV 趋势 ──
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dateStart = $date . 'T00:00:00';
            $dateEnd = $date . 'T23:59:59';
            $pvStmt = $pdo->prepare("SELECT COUNT(*) FROM analytics_pv WHERE created_at >= ? AND created_at <= ?");
            $pvStmt->execute([$dateStart, $dateEnd]);
            $pv = (int)$pvStmt->fetchColumn();
            $uvStmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_hash) FROM analytics_pv WHERE created_at >= ? AND created_at <= ?");
            $uvStmt->execute([$dateStart, $dateEnd]);
            $uv = (int)$uvStmt->fetchColumn();
            $trend[] = ['date' => $date, 'pv' => $pv, 'uv' => $uv];
        }

        // ── 模块使用统计 ──
        $moduleStmt = $pdo->query("SELECT module, COUNT(*) AS cnt FROM analytics_modules GROUP BY module ORDER BY cnt DESC");
        $modules = $moduleStmt->fetchAll();

        // ── 模块中文名映射 ──
        $moduleNames = [
            'ai_chat' => 'AI 聊天',
            'music' => '音乐播放器',
            'game2048' => '2048 游戏',
            'game_hub' => '游戏中心',
            'drive_upload' => '云盘上传',
            'drive_download' => '云盘下载',
            'guestbook' => '评论区',
            'page_view' => '页面浏览',
        ];
        $moduleStats = [];
        foreach ($modules as $m) {
            $moduleStats[] = [
                'name' => $moduleNames[$m['module']] ?? $m['module'],
                'key' => $m['module'],
                'count' => (int)$m['cnt'],
            ];
        }

        // ── 总访问人数 (历史 UV) ──
        $totalUvStmt = $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM analytics_pv");
        $totalUv = (int)$totalUvStmt->fetchColumn();
        $totalPvStmt = $pdo->query("SELECT COUNT(*) FROM analytics_pv");
        $totalPv = (int)$totalPvStmt->fetchColumn();

        // ── 今日数据 ──
        $today = date('Y-m-d');
        $todayPvStmt = $pdo->prepare("SELECT COUNT(*) FROM analytics_pv WHERE created_at >= ? AND created_at <= ?");
        $todayPvStmt->execute([$today . 'T00:00:00', $today . 'T23:59:59']);
        $todayPv = (int)$todayPvStmt->fetchColumn();
        $todayUvStmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_hash) FROM analytics_pv WHERE created_at >= ? AND created_at <= ?");
        $todayUvStmt->execute([$today . 'T00:00:00', $today . 'T23:59:59']);
        $todayUv = (int)$todayUvStmt->fetchColumn();

        respond(200, [
            'trend' => $trend,
            'moduleStats' => $moduleStats,
            'totalPv' => $totalPv,
            'totalUv' => $totalUv,
            'todayPv' => $todayPv,
            'todayUv' => $todayUv,
        ]);
    }

    if ($action === 'online') {
        // 清理超过 2 分钟未心跳的会话
        $pdo->prepare("DELETE FROM analytics_online WHERE last_active < ?")->execute([time() - 120]);
        $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM analytics_online");
        $count = (int)$stmt->fetchColumn();

        // 在线用户详情
        $detailStmt = $pdo->query("SELECT country, province, page, last_active FROM analytics_online ORDER BY last_active DESC LIMIT 20");
        $details = $detailStmt->fetchAll();

        respond(200, ['online' => $count, 'details' => $details]);
    }

    if ($action === 'visitors') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $totalStmt = $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM analytics_pv");
        $total = (int)$totalStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT ip_hash, country, province, MAX(created_at) AS lastVisit, COUNT(*) AS visitCount
             FROM analytics_pv
             GROUP BY ip_hash, country, province
             ORDER BY lastVisit DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $visitors = $stmt->fetchAll();

        respond(200, [
            'visitors' => $visitors,
            'total' => $total,
            'page' => $page,
        ]);
    }

    respond(400, ['error' => '未知操作']);
}

// ============================================================
//  POST — 追踪埋点
// ============================================================

if ($method !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ── 页面访问 ──
if ($action === 'track') {
    $page = ($input['page'] ?? '') ?: '/';
    $hash = visitorHash();
    $ip = visitorIp();
    $geo = geoLocation($ip);
    $pdo->prepare("INSERT INTO analytics_pv (page, ip_hash, ip_raw, country, province, created_at) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$page, $hash, $ip, $geo['country'], $geo['province'], gmdate('c')]);
    respond(200, ['ok' => true]);
}

// ── 模块使用 ──
if ($action === 'module') {
    $module = ($input['module'] ?? '') ?: 'unknown';
    $pdo->prepare("INSERT INTO analytics_modules (module, ip_hash, created_at) VALUES (?, ?, ?)")
        ->execute([$module, visitorHash(), gmdate('c')]);
    respond(200, ['ok' => true]);
}

// ── 在线心跳 ──
if ($action === 'heartbeat') {
    $sessionId = ($input['session_id'] ?? '') ?: visitorHash();
    $page = ($input['page'] ?? '') ?: '/';
    $hash = visitorHash();
    $ip = visitorIp();
    $geo = geoLocation($ip);

    $pdo->prepare(
        "INSERT INTO analytics_online (session_id, ip_hash, country, province, page, last_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE page = VALUES(page), last_active = VALUES(last_active)"
    )->execute([$sessionId, $hash, $geo['country'], $geo['province'], $page, time(), gmdate('c')]);

    respond(200, ['ok' => true]);
}

respond(400, ['error' => '未知操作']);
