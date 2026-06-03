# Mingine.top Project Structure

This workspace has been reorganized to follow a common static-website development layout.

## Directory Layout

```text
Mingine.top/
  index.html                # Home page entry
  pages/
    game.html               # 2048 game page
    contact.html            # Contact page
  assets/
    css/
      style.css             # Global stylesheet
    js/
      main.js               # Shared UI logic (theme toggle)
      ai-chat.js            # Chat widget behavior
      game2048.js           # 2048 game logic
    images/
      image00.jpg           # Site image assets
  server/
    api/
      chat.php              # Backend chat API endpoint
      guestbook.php          # Database-backed guestbook API endpoint
      drive.php              # Cloud drive API (upload/download/manage)
    storage/                  # Cloud drive file storage (web-inaccessible)
    sql/
      guestbook.sql          # MySQL schema for the guestbook table
  README.md
```

## Suggested Development Workflow

1. Add or update page templates in `index.html` and `pages/`.
2. Put shared styles in `assets/css/`.
3. Put client scripts in `assets/js/`.
4. Put image/media files in `assets/images/`.
5. Keep backend interfaces in `server/api/`.
6. Test links and resource paths after adding new pages.

## Guestbook Database Setup

The contact page guestbook uses a MySQL database through PDO.

Set these environment variables on your server:

- `GUESTBOOK_DB_DSN` or `DB_DSN`
- `GUESTBOOK_DB_USER` or `DB_USER`
- `GUESTBOOK_DB_PASSWORD` or `DB_PASSWORD`
- `GUESTBOOK_DB_TABLE` or `DB_TABLE` (optional, defaults to `guestbook_entries`)

Use `server/sql/guestbook.sql` to create the table before first use.

## Cloud Drive (云盘) Setup

The cloud drive is a password-protected private file storage area. Only the site owner can upload, download, and manage files.

### Server Configuration

Set this environment variable on your server (宝塔面板 → 网站 → 配置文件 → PHP 环境变量):

- `DRIVE_PASSWORD` — Your private access password

### How to set DRIVE_PASSWORD in 宝塔面板 (Baota)

1. Open 宝塔面板 → **网站** → click your site → **配置文件**
2. Find the PHP configuration section
3. Add: `env[DRIVE_PASSWORD] = "your-strong-password"`
4. Save and restart PHP

### Upload Limits

Default max file size is **500MB**. To increase it, adjust `upload_max_filesize` and `post_max_size` in PHP settings (宝塔 → PHP → 配置修改).

### Storage

All uploaded files are stored in `server/storage/`, which is protected from direct web access. Files are only served through the authenticated PHP download endpoint.

### API Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `?action=login` | POST | Authenticate with password |
| `?action=logout` | GET | End session |
| `?action=check` | GET | Check auth status |
| `?action=list` | GET | List all files |
| `?action=upload` | POST | Upload file (multipart) |
| `?action=download&file=...` | GET | Download a file |
| `?action=delete` | POST | Delete a file |

## Path Rules

- From `index.html`: use `assets/...` and `pages/...`.
- From files in `pages/`: use `../assets/...` and `../index.html`.
- API requests from frontend use `server/api/...`.
# Mingine.top
# Mingine.top
