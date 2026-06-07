-- =============================================
-- 博客系统数据库表
-- =============================================

-- 文章分类
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL,
    slug VARCHAR(40) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章标签
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL,
    slug VARCHAR(40) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    content_md TEXT NOT NULL,
    content_html TEXT NOT NULL,
    excerpt VARCHAR(500) NOT NULL DEFAULT '',
    category_id INT UNSIGNED DEFAULT NULL,
    cover_image VARCHAR(500) NOT NULL DEFAULT '',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug),
    KEY idx_category (category_id),
    KEY idx_published (is_published),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 文章-标签关联
CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    KEY idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
