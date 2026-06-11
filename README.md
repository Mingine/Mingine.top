# Mingine.top

> 个人全栈网站 — 博客 · 游戏 · 音乐 · AI · 云盘 · 仪表盘

<p align="center">
  <strong>🏠 我的个人小站 — 集博客、游戏、音乐、AI 聊天、云盘于一体</strong>
</p>

<p align="center">
  <a href="https://mingine.top"><img src="https://img.shields.io/badge/🌐-mingine.top-FB7299?style=flat-square" alt="Website" /></a>
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="JS" />
  <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License" />
</p>

---

## ✨ 特性亮点

| 模块 | 说明 |
|:--|:--|
| 🤖 **AI 聊天机器人** | 悬浮窗 DeepSeek AI 助手，与访客实时对话 |
| 🎮 **游戏中心** | 内置 2048 等小游戏，以及 Unity WebGL 游戏 |
| 🎵 **音乐播放器** | 网易云 + Spotify 双源切换，悬浮播放 |
| � **博客系统** | Markdown 写作，分类/标签，文章目录，自动部署 |
| 💬 **B站风格评论区** | 仿 Bilibili 评论区：一级评论 + 楼中楼回复 + 点赞 + 分页 |
| ☁️ **私有云盘** | 密码保护的文件上传/下载/管理，仅自己可访问 |
| 🌓 **明暗主题** | Glassmorphism 毛玻璃风格，自动跟随系统偏好 |
| 📱 **响应式设计** | 适配 PC、平板、手机 |

---

## 🛠 技术栈

| 层级 | 技术 |
|:--|:--|
| **前端** | HTML5 · CSS3 (Glassmorphism) · Vanilla JavaScript |
| **后端** | PHP 8 · PDO · league/commonmark (GFM) |
| **数据库** | MySQL 8 · utf8mb4 |
| **AI** | DeepSeek API |
| **图表** | Chart.js |
| **部署** | 阿里云 ECS · 宝塔面板 · Nginx |
| **CDN** | Cloudflare |
| **图标** | Font Awesome 6 |

---

## 📁 项目结构

```
Mingine.top/
├── index.html                     # 首页
├── pages/
│   ├── contact.html               # 联系页 + B站风格评论区
│   ├── drive.html                 # 私有云盘
│   ├── game.html                  # 游戏中心
│   ├── game2048.html              # 2048 游戏
│   ├── blog.html                  # 博客前台（列表 + 详情 + 目录）
│   ├── blog-admin.html            # 博客管理后台（Markdown 编辑）
│   ├── dashboard.html             # 管理仪表盘（PV/UV/在线/访客）
│   └── portfolio.html             # 作品集
├── assets/
│   ├── css/
│   │   └── style.css              # 全局样式 (Glassmorphism + 明暗主题)
│   ├── js/
│   │   ├── main.js                # 共享逻辑 (主题切换)
│   │   ├── ai-chat.js             # AI 聊天
│   │   ├── contact.js             # 评论区交互
│   │   ├── drive.js               # 云盘交互
│   │   ├── game2048.js            # 2048 游戏逻辑
│   │   └── music-player.js        # 音乐播放器
├── content/                       # 🆕 博客媒体资源
│   ├── images/
│   │   ├── YYYY/MM/               # 按年月归档
│   │   └── common/                # 通用图片
│   ├── images/                    # 图片资源
│   └── videos/                    # 视频资源
├── H5Game/                        # Unity WebGL 游戏
├── server/
│   ├── api/
│   │   ├── chat.php               # AI 聊天 API (DeepSeek)
│   │   ├── guestbook.php          # 评论区 API (CRUD + 点赞)
│   │   ├── guestbook.config.php   # 数据库配置
│   │   └── drive.php              # 云盘 API (上传/下载/管理)
│   ├── storage/                   # 云盘文件存储 (Web 不可访问)
│   ├── lib/
│   │   └── Markdown.php           # Markdown 解析库（已弃用，改用 league/commonmark）
│   ├── sql/
│   │   ├── analytics.sql          # 仪表盘统计表
│   │   ├── blog.sql               # 博客系统表
│   │   └── guestbook.sql          # 评论区表
│   ├── config/                    # 配置文件
│   └── data/
│       └── guestbook.json         # 数据文件
└── README.md
```

---

## 🚀 快速部署

### 环境要求

- PHP 8.0+
- MySQL 8.0+
- Nginx / Apache
- 宝塔面板 (推荐)

### 部署步骤

1. **克隆仓库到服务器**

```bash
cd /www/wwwroot
git clone https://github.com/Mingine/Mingine.top.git
```

2. **安装 Composer 依赖**

```bash
cd /www/wwwroot/mingine.top
composer install --no-dev
```

3. **配置数据库**

在宝塔面板中创建 MySQL 数据库，编辑 `server/api/guestbook.config.php`：

```php
return [
    'dsn'      => 'mysql:host=127.0.0.1;port=3306;dbname=你的数据库名;charset=utf8mb4',
    'user'     => '你的数据库用户名',
    'password' => '你的数据库密码',
    'table'    => 'guestbook_entries',
];
```

3. **配置环境变量**

在宝塔面板 → 网站 → 配置文件 → PHP 环境变量中设置：

```ini
env[DEEPSEEK_API_KEY] = "你的 DeepSeek API Key"
env[DRIVE_PASSWORD]   = "你的云盘密码"
```

4. **配置 Nginx**

网站根目录指向项目根目录，PHP 由 `server/api/` 处理。

5. **（可选）Cloudflare CDN**

参考下方 CDN 配置说明。

---

## ☁️ Cloudflare CDN 配置

1. 注册 [Cloudflare](https://cloudflare.com) 并添加域名
2. DNS 记录开启橙色云朵代理
3. SSL/TLS 设为 **Full**
4. 添加 Cache Rule：
   - `/assets/*` → 缓存 1 个月
   - `/server/api/*` → 绕过缓存

---

## 📡 API 文档

### 评论区 API (`server/api/guestbook.php`)

| 方法 | 参数 | 说明 |
|:--|:--|:--|
| `GET` | `?page=1&limit=20&sort=newest` | 分页获取评论列表 |
| `POST` | `{name, message, email?}` | 发布一级评论 |
| `POST` | `{name, message, parent_id}` | 发布楼中楼回复 |
| `POST` | `?action=like` + `{comment_id}` | 点赞/取消点赞 |

### 云盘 API (`server/api/drive.php`)

| 方法 | 参数 | 说明 |
|:--|:--|:--|
| `POST` | `?action=login` + `{password}` | 登录验证 |
| `GET` | `?action=check` | 检查登录状态 |
| `GET` | `?action=list` | 获取文件列表 |
| `POST` | `?action=upload` (multipart) | 上传文件 |
| `GET` | `?action=download&file=文件名` | 下载文件 |
| `POST` | `?action=delete` + `{filename}` | 删除文件 |

### 聊天 API (`server/api/chat.php`)

| 方法 | 说明 |
|:--|:--|
| `POST` | `{message}` → 返回 AI 回复 |

### 博客 API (`server/api/blog.php`)

| 方法 | 参数 | 说明 |
|:--|:--|:--|
| `GET` | `?action=posts&page=1&category=slug` | 分页获取文章列表 |
| `GET` | `?action=post&slug=xxx` | 单篇文章详情 |
| `GET` | `?action=categories` | 获取分类列表 |
| `POST` | `?action=login` + `{password}` | 管理员登录 |
| `POST` | `?action=create` + `{title,content_md,tags,...}` | 创建文章 |
| `POST` | `?action=update` + `{id,...}` | 更新文章 |
| `POST` | `?action=delete` + `{id}` | 删除文章 |
| `POST` | `?action=create_category` + `{name}` | 创建分类 |
| `POST` | `?action=delete_category` + `{id}` | 删除分类 |

---

## ⚙️ 自动部署 (Git + 宝塔 WebHook)

项目支持 Git push 自动部署：

1. 服务器安装宝塔 WebHook 插件
2. 添加 Hook 执行脚本：
   ```bash
   cd /www/wwwroot/mingine.top && git pull origin main
   ```
3. GitHub 仓库 Settings → Webhooks 中填入 Hook URL
4. 每次 `git push` 后服务器自动拉取更新

---

## 📝 许可

MIT License · 个人站点，欢迎访问。

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/Mingine">Mingine</a>
</p>



