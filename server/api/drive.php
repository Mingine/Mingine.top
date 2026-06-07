<?php
declare(strict_types=1);

set_error_handler(function ($s, $m, $f, $l) {
    if (!(error_reporting() & $s)) return false;
    throw new ErrorException($m, 0, $s, $f, $l);
});
set_exception_handler(function ($e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('html_errors', '0');

session_start();

// ─── 工具函数 ───────────────────────────────────────

function respond(int $code, array $payload): void
{
    ob_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getPassword(): string
{
    $pw = getenv('DRIVE_PASSWORD') ?: '';
    if ($pw === '' && isset($_SERVER['DRIVE_PASSWORD'])) {
        $pw = $_SERVER['DRIVE_PASSWORD'];
    }
    if ($pw === '' && isset($_ENV['DRIVE_PASSWORD'])) {
        $pw = $_ENV['DRIVE_PASSWORD'];
    }
    return $pw;
}

function requireAuth(): void
{
    if (empty($_SESSION['drive_authed'])) {
        respond(401, ['error' => '未授权，请先输入密码']);
    }
    // 24 小时过期
    $loginTime = $_SESSION['drive_authed_time'] ?? 0;
    if (time() - $loginTime > 86400) {
        unset($_SESSION['drive_authed'], $_SESSION['drive_authed_time']);
        respond(401, ['error' => '登录已过期，请重新输入密码']);
    }
}

function storagePath(): string
{
    $path = __DIR__ . '/../storage/';
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true)) {
            respond(500, ['error' => '无法创建存储目录，请检查 server/ 目录权限']);
        }
    }
    if (!is_writable($path)) {
        respond(500, ['error' => '存储目录无写入权限，请在宝塔面板中设置 server/storage/ 权限为 775']);
    }
    return $path;
}

function safeFilename(string $name): string
{
    $name = basename($name);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9._\-()（）\s]/u', '_', $base);
    $base = trim($base);
    if ($base === '') {
        $base = 'file_' . date('Ymd_His');
    }
    return $ext ? $base . '.' . $ext : $base;
}

function formatSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return sprintf('%.2f GB', $bytes / 1073741824);
    }
    if ($bytes >= 1048576) {
        return sprintf('%.2f MB', $bytes / 1048576);
    }
    if ($bytes >= 1024) {
        return sprintf('%.2f KB', $bytes / 1024);
    }
    return $bytes . ' B';
}

function getFileIcon(string $ext): string
{
    $map = [
        'jpg' => 'fa-image', 'jpeg' => 'fa-image', 'png' => 'fa-image',
        'gif' => 'fa-image', 'webp' => 'fa-image', 'svg' => 'fa-image',
        'bmp' => 'fa-image', 'ico' => 'fa-image',
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'csv' => 'fa-file-csv',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'flac' => 'fa-file-audio',
        'aac' => 'fa-file-audio', 'ogg' => 'fa-file-audio', 'wma' => 'fa-file-audio',
        'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mkv' => 'fa-file-video',
        'mov' => 'fa-file-video', 'wmv' => 'fa-file-video', 'flv' => 'fa-file-video',
        'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', '7z' => 'fa-file-zipper',
        'tar' => 'fa-file-zipper', 'gz' => 'fa-file-zipper',
        'txt' => 'fa-file-alt', 'md' => 'fa-file-alt', 'log' => 'fa-file-alt',
        'html' => 'fa-file-code', 'css' => 'fa-file-code', 'js' => 'fa-file-code',
        'php' => 'fa-file-code', 'py' => 'fa-file-code', 'json' => 'fa-file-code',
        'xml' => 'fa-file-code', 'sql' => 'fa-file-code',
    ];
    return $map[strtolower($ext)] ?? 'fa-file';
}

function mimeType(string $ext): string
{
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
    ];
    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

// ─── 路由 ───────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// 特殊处理：download 不走 JSON
if ($action === 'download') {
    requireAuth();
    $file = $_GET['file'] ?? '';
    if ($file === '') {
        respond(400, ['error' => '缺少文件名']);
    }
    $safeName = safeFilename($file);
    $fullPath = storagePath() . $safeName;
    if (!is_file($fullPath)) {
        respond(404, ['error' => '文件不存在']);
    }
    $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $mime = mimeType($ext);
    $size = filesize($fullPath);

    // 清空输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($safeName) . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
    header('Content-Length: ' . $size);
    header('Cache-Control: private, max-age=0');
    readfile($fullPath);
    exit;
}

switch ($action) {
    // ── 登录 ──
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(405, ['error' => 'Method not allowed']);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $password = $input['password'] ?? '';
        $stored = getPassword();

        if ($stored === '') {
            respond(500, ['error' => '服务端未配置 DRIVE_PASSWORD']);
        }
        if ($password !== $stored) {
            respond(403, ['error' => '密码错误']);
        }
        $_SESSION['drive_authed'] = true;
        $_SESSION['drive_authed_time'] = time();
        respond(200, ['ok' => true, 'message' => '登录成功']);
        break;

    // ── 登出 ──
    case 'logout':
        unset($_SESSION['drive_authed'], $_SESSION['drive_authed_time']);
        respond(200, ['ok' => true, 'message' => '已退出']);
        break;

    // ── 检查登录状态 ──
    case 'check':
        $authed = !empty($_SESSION['drive_authed']);
        $expired = false;
        if ($authed) {
            $loginTime = $_SESSION['drive_authed_time'] ?? 0;
            if (time() - $loginTime > 86400) {
                unset($_SESSION['drive_authed'], $_SESSION['drive_authed_time']);
                $authed = false;
                $expired = true;
            }
        }
        respond(200, [
            'authed' => $authed,
            'expired' => $expired,
        ]);
        break;

    // ── 文件列表 ──
    case 'list':
        requireAuth();
        $dir = storagePath();
        $files = [];
        $items = scandir($dir);
        if ($items === false) {
            respond(500, ['error' => '无法读取存储目录']);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === 'index.html') {
                continue;
            }
            $realPath = $dir . $item;
            if (!is_file($realPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $files[] = [
                'name' => $item,
                'size' => filesize($realPath),
                'sizeDisplay' => formatSize(filesize($realPath)),
                'mtime' => filemtime($realPath),
                'date' => date('Y-m-d H:i:s', filemtime($realPath)),
                'icon' => getFileIcon($ext),
            ];
        }
        // 按修改时间倒序
        usort($files, function ($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });
        respond(200, ['files' => $files]);
        break;

    // ── 上传 ──
    case 'upload':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(405, ['error' => 'Method not allowed']);
        }
        if (empty($_FILES['file'])) {
            respond(400, ['error' => '没有收到文件']);
        }
        $uploaded = $_FILES['file'];
        if ($uploaded['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '文件超过服务器限制 (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件上传不完整',
                UPLOAD_ERR_NO_FILE => '没有文件',
                UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
                UPLOAD_ERR_CANT_WRITE => '无法写入磁盘',
            ];
            $msg = $errorMessages[$uploaded['error']] ?? '上传错误: ' . $uploaded['error'];
            respond(400, ['error' => $msg]);
        }

        // 大小限制 500MB
        if ($uploaded['size'] > 524288000) {
            respond(400, ['error' => '文件不能超过 500MB']);
        }

        $originalName = $uploaded['name'];
        $safeName = safeFilename($originalName);

        // 避免重名：加时间戳前缀
        $destPath = storagePath() . $safeName;
        if (file_exists($destPath)) {
            $timestamp = date('Ymd_His');
            $safeName = $timestamp . '_' . $safeName;
            $destPath = storagePath() . $safeName;
        }

        if (!move_uploaded_file($uploaded['tmp_name'], $destPath)) {
            $err = error_get_last();
            $detail = $err ? $err['message'] : '未知错误';
            respond(500, ['error' => '文件保存失败: ' . $detail . '。请检查 server/storage/ 目录权限是否为 775']);
        }

        respond(200, [
            'ok' => true,
            'message' => '上传成功',
            'file' => [
                'name' => $safeName,
                'originalName' => $originalName,
            ],
        ]);
        break;

    // ── 删除 ──
    case 'delete':
        requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(405, ['error' => 'Method not allowed']);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = $input['filename'] ?? '';
        if ($filename === '') {
            respond(400, ['error' => '缺少文件名']);
        }
        $safeName = safeFilename($filename);
        $fullPath = storagePath() . $safeName;
        if (!is_file($fullPath)) {
            respond(404, ['error' => '文件不存在']);
        }
        if (!unlink($fullPath)) {
            respond(500, ['error' => '删除失败']);
        }
        respond(200, ['ok' => true, 'message' => '已删除']);
        break;

    default:
        respond(400, ['error' => '未知操作']);
}
