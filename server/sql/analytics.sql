-- =============================================
-- 仪表盘 — 数据统计表
-- =============================================

-- 页面访问记录 (PV/UV 统计, IP 地理信息)
CREATE TABLE IF NOT EXISTS analytics_pv (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page VARCHAR(100) NOT NULL DEFAULT '',
    ip_hash VARCHAR(64) NOT NULL,
    ip_raw VARCHAR(45) NOT NULL DEFAULT '',
    country VARCHAR(100) NOT NULL DEFAULT '',
    province VARCHAR(100) NOT NULL DEFAULT '',
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_date (created_at),
    KEY idx_page (page),
    KEY idx_ip (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 模块使用记录
CREATE TABLE IF NOT EXISTS analytics_modules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module VARCHAR(50) NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_module (module),
    KEY idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 在线会话 (心跳)
CREATE TABLE IF NOT EXISTS analytics_online (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(64) NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT '',
    province VARCHAR(100) NOT NULL DEFAULT '',
    page VARCHAR(100) NOT NULL DEFAULT '',
    last_active INT UNSIGNED NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_session (session_id),
    KEY idx_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
