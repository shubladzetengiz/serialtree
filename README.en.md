# SerialManager

[🇬🇪 ქართული](README.md) · [🇬🇧 English](README.en.md) · [🇷🇺 Русский](README.ru.md) · [🇫🇷 Français](README.fr.md)

Web application for managing TV series and movies. PHP + SQLite + Tailwind CSS.

![Demo](demo.png)

## Requirements

- PHP 8.1 or newer
- `mbstring` extension (part of PHP)
- `php-sqlite3` package

```
sudo apt install php-sqlite3 php-mbstring
```

## Running

### With router (recommended)

```sh
php -S localhost:8002 -t serial2 serial2/router.php
```

### Without router

```sh
php -S localhost:8002 -t serial2
```

The router handles static file serving (JS, CSS, images).

### Changing the port

Replace `8002` with any available port.

## Features

### Core
- Add / Edit / Delete series and movies
- Two display modes: grid and list
- Live search (Unicode case-insensitive, 300ms debounce)
- Status filter
- Sorting: by date (newest/oldest) and alphabetically (A–Z / Z–A)

### Covers
- Download from external URL and store locally
- Badge: ✓ Local / ✗ Online
- 100+ covers already downloaded

### Auto-Status
- Both fields set (season + episode) → `ნანახი` (Watched)
- Only one field set → `გასაგრძელებელია` (Ongoing)
- Both empty → `სანახავია` (To Watch)
- Season > 99 → clears both fields, status → `სანახავია`

### Import / Export
- SQL export (single file for both tables)
- SQL import (.sql, max 10MB, CSRF-protected)
- Import filters columns to match existing schema (backward compatible)

### Interface
- 4 languages: 🇬🇪 Georgian, 🇬🇧 English, 🇷🇺 Russian, 🇫🇷 French
- Dark theme (Tailwind CSS + Font Awesome 6)
- Responsive: table on desktop, cards on mobile
- CSRF protection (POST requests)
- Delete with 5-second countdown timer

## File Structure

```
serial2/
├── config.php          # DB, i18n, CSRF, import/export
├── index.php           # Routing, CRUD, AJAX handlers
├── router.php          # Static file router
├── data.sqlite         # SQLite database
├── views/
│   └── layout.php      # HTML layout (Tailwind)
├── public/
│   └── js/
│       └── app.js      # Frontend logic
├── lang/
│   ├── ka.php          # Georgian
│   ├── en.php          # English
│   ├── ru.php          # Russian
│   └── fr.php          # French
└── uploads/
    └── covers/          # Local cover images
```

## Database

- File: `data.sqlite`
- Tables: `series` and `movies`
- Auto-migration: missing columns are added automatically

### series
| Column | Type | Description |
|---|---|---|
| id | INTEGER | Primary key |
| cover | TEXT | Cover path/URL |
| title | TEXT | Title |
| season | INTEGER | Season |
| episode | INTEGER | Episode |
| status | TEXT | Status |
| rating | INTEGER | Rating (0–5) |
| resource_url | TEXT | Resource link |

### movies
| Column | Type | Description |
|---|---|---|
| id | INTEGER | Primary key |
| cover | TEXT | Cover path/URL |
| title | TEXT | Title |
| description | TEXT | Description |
| status | TEXT | Status |
| rating | INTEGER | Rating (0–5) |
| resource_url | TEXT | Resource link |

## Recovery

If `data.sqlite` is missing (or on first run), the app offers:
1. **Create empty database** — auto-migration
2. **Import SQL file** — restore from a backup
3. **Cancel**

To create a SQL backup: click the `Export` button in the app toolbar.

## Security

- CSRF token on all POST requests
- SQL injection protection: prepared statements
- XSS protection: `htmlspecialchars()` on all output
- File import: only `.sql`, max 10MB

## License

MIT
