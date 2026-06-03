-- =============================================
-- 评论区系统数据库表 (B站风格留言板)
-- =============================================

-- 主评论表 (含一级评论 + 楼中楼回复)
CREATE TABLE IF NOT EXISTS guestbook_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL=一级评论, 非NULL=回复某条评论',
    root_id BIGINT UNSIGNED DEFAULT NULL COMMENT '根评论ID,用于快速查询楼中楼',
    name VARCHAR(40) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    message VARCHAR(500) NOT NULL,
    likes INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点赞数',
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_root (root_id),
    KEY idx_parent (parent_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 点赞记录表 (防止重复点赞, 按IP+UA去重)
CREATE TABLE IF NOT EXISTS comment_likes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    ip_hash VARCHAR(64) NOT NULL COMMENT 'SHA256(IP+UA+comment_id)',
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_like (comment_id, ip_hash),
    KEY idx_comment (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
