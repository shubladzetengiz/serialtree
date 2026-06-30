<?php

session_start();

define('DB_PATH', __DIR__ . '/data.sqlite');
define('APP_NAME', 'SerialManager');
define('ITEMS_PER_PAGE', 20);

function db(): SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $db->enableExceptions(true);
    }
    return $db;
}

function migrate(string $table, array $columns): void {
    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS $table (id INTEGER PRIMARY KEY AUTOINCREMENT)");
    $existing = $db->query("PRAGMA table_info($table)");
    $names = [];
    while ($col = $existing->fetchArray(SQLITE3_ASSOC)) {
        $names[] = $col['name'];
    }
    foreach ($columns as $name => $def) {
        if (!in_array($name, $names)) {
            $db->exec("ALTER TABLE $table ADD COLUMN $name $def");
        }
    }
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function redirect_with_filters(string $base): never {
    $params = [];
    foreach (['search', 'status_filter', 'sort'] as $k) {
        $v = $_GET[$k] ?? '';
        if ($v !== '') $params[$k] = $v;
    }
    if ($params) {
        $base .= (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }
    redirect($base);
}

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cover_ext(string $url): string {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? $ext : 'jpg';
}

function json_response(array $data): never {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function export_sql(): never {
    $db = db();
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="serialmanager-' . date('Y-m-d') . '.sql"');

    $out = "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n";

    foreach (['series', 'movies'] as $table) {
        $row = $db->querySingle("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'", true);
        if ($row) {
            $out .= $row['sql'] . ";\n\n";
        }

        $cols = [];
        $colInfo = $db->query("PRAGMA table_info($table)");
        while ($c = $colInfo->fetchArray(SQLITE3_ASSOC)) {
            $cols[] = $c['name'];
        }
        $colList = '"' . implode('", "', $cols) . '"';

        $result = $db->query("SELECT * FROM $table");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $vals = [];
            foreach ($cols as $col) {
                $v = $row[$col];
                $vals[] = $v === null ? 'NULL' : "'" . SQLite3::escapeString($v) . "'";
            }
            $out .= "INSERT INTO \"$table\" ($colList) VALUES (" . implode(', ', $vals) . ");\n";
        }
        $out .= "\n";
    }

    $out .= "COMMIT;\n";
    echo $out;
    exit;
}

function parse_sql_values(string $s): array {
    $result = [];
    $len = strlen($s);
    $i = 0;
    $cur = '';
    $inStr = false;
    while ($i < $len) {
        $ch = $s[$i];
        if ($ch === "'" && !$inStr) {
            $inStr = true;
            $cur .= $ch;
        } elseif ($ch === "'" && $inStr) {
            // Check for escaped quote ''
            if ($i + 1 < $len && $s[$i + 1] === "'") {
                $cur .= "''";
                $i += 2;
                continue;
            }
            $inStr = false;
            $cur .= $ch;
        } elseif ($ch === ',' && !$inStr) {
            $result[] = trim($cur);
            $cur = '';
        } else {
            $cur .= $ch;
        }
        $i++;
    }
    if ($cur !== '') $result[] = trim($cur);
    return $result;
}

function import_sql(string $path): array {
    $db = db();
    $content = file_get_contents($path);
    if ($content === false) {
        return ['success' => false, 'message' => 'Failed to read file'];
    }

    // Get existing columns for each table
    $existingCols = [];
    foreach (['series', 'movies'] as $table) {
        $cols = [];
        $info = $db->query("PRAGMA table_info($table)");
        while ($c = $info->fetchArray(SQLITE3_ASSOC)) {
            $cols[$c['name']] = true;
        }
        $existingCols[$table] = $cols;
    }

    $statements = preg_split('/;\s*\r?\n/', $content);
    $count = 0;

    $db->exec('BEGIN TRANSACTION');
    try {
        // Clear existing data for a clean restore
        $db->exec('DELETE FROM series');
        $db->exec('DELETE FROM movies');
        $db->exec('DELETE FROM sqlite_sequence');
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;

            $upper = strtoupper(substr($stmt, 0, 30));
            if (str_starts_with($upper, 'PRAGMA') ||
                str_starts_with($upper, 'BEGIN') ||
                str_starts_with($upper, 'COMMIT') ||
                str_starts_with($upper, 'CREATE TABLE') ||
                str_starts_with($upper, '--')) {
                continue;
            }

            if (!str_contains($upper, 'INSERT INTO')) continue;
            if (!str_contains(strtoupper($stmt), 'SERIES') && !str_contains(strtoupper($stmt), 'MOVIES')) continue;

            // Parse INSERT — extract columns and filter to existing ones
            if (preg_match('/INSERT\s+INTO\s+"?(\w+)"?\s*(?:\(([^)]+)\))?\s*VALUES\s*\((.+)\)\s*;?\s*$/is', rtrim($stmt), $m)) {
                $table = strtolower($m[1]);
                if (!isset($existingCols[$table])) continue;

                $rawVals = parse_sql_values($m[3]);

                if (isset($m[2]) && trim($m[2]) !== '') {
                    // Named columns
                    $rawCols = preg_split('/\s*,\s*/', trim($m[2]));
                    if (count($rawCols) !== count($rawVals)) continue;
                } else {
                    // Unnamed columns — map by position using known old schema
                    $schemaMap = [
                        'series' => ['id', 'cover', 'title', 'season', 'episode', 'rating', 'status', 'resource_url', 'release_date', 'reminded', 'notified', 'release_date_1', 'release_date_2'],
                        'movies' => ['id', 'cover', 'title', 'description', 'status', 'rating', 'resource_url', 'release_date', 'reminded', 'notified', 'release_date_1', 'release_date_2'],
                    ];
                    $rawCols = $schemaMap[$table] ?? [];
                    if (count($rawCols) !== count($rawVals)) continue;
                }

                $filteredCols = [];
                $filteredVals = [];
                foreach ($rawCols as $i => $col) {
                    $colName = trim(str_replace('"', '', $col));
                    if (isset($existingCols[$table][$colName]) && isset($rawVals[$i])) {
                        $filteredCols[] = '"' . SQLite3::escapeString($colName) . '"';
                        $filteredVals[] = $rawVals[$i];
                    }
                }
                if (empty($filteredCols)) continue;

                $rebuild = "INSERT INTO \"$table\" (" . implode(', ', $filteredCols) . ') VALUES (' . implode(', ', $filteredVals) . ')';
                $db->exec($rebuild);
                $count++;
            }
        }

        $db->exec('COMMIT');
        return ['success' => true, 'count' => $count];
    } catch (\Exception $e) {
        $db->exec('ROLLBACK');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// --- i18n ---
$lang = null;

function init_lang(): array {
    global $lang;
    $codes = ['ka', 'en', 'ru', 'fr'];
    $default = 'ka';

    if (isset($_GET['lang']) && in_array($_GET['lang'], $codes, true)) {
        $_SESSION['lang'] = $_GET['lang'];
        setcookie('lang', $_GET['lang'], time() + 86400 * 365, '/');
    }

    $code = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? $default;
    if (!in_array($code, $codes, true)) $code = $default;

    $file = __DIR__ . "/lang/$code.php";
    $lang = file_exists($file) ? require $file : require __DIR__ . '/lang/ka.php';
    return $lang;
}

function __(string $key, mixed ...$args): string {
    global $lang;
    $str = $lang[$key] ?? $key;
    return $args ? sprintf($str, ...$args) : $str;
}

function __status(string $georgian): string {
    return match ($georgian) {
        'ნანახი' => __('status_watched'),
        'სანახავია' => __('status_towatch'),
        'გასაგრძელებელია' => __('status_ongoing'),
        default => $georgian,
    };
}

function lang_switcher(): string {
    global $lang;
    $current = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'ka';
    $codes = [
        'ka' => ['🇬🇪', 'lang_ka'],
        'en' => ['🇬🇧', 'lang_en'],
        'ru' => ['🇷🇺', 'lang_ru'],
        'fr' => ['🇫🇷', 'lang_fr'],
    ];

    // Desktop: flag buttons
    $html = '<div class="hidden md:flex gap-0.5 sm:gap-1 text-xs sm:text-sm" id="lang-switcher">';
    foreach ($codes as $code => [$flag, $label]) {
        $active = $code === $current ? ' bg-indigo-600 text-white' : ' text-gray-400 hover:text-gray-200 hover:bg-gray-700';
        $html .= '<a href="?lang=' . $code . '" class="px-1.5 sm:px-2 py-1 rounded transition font-medium' . $active . '" title="' . __($label) . '">' . $flag . '</a>';
    }
    $html .= '</div>';

    // Mobile: flag buttons
    $html .= '<div class="md:hidden flex gap-0.5 sm:gap-1 text-xs sm:text-sm">';
    foreach ($codes as $code => [$flag, $label]) {
        $params = array_merge($_GET, ['lang' => $code]);
        $url = '?' . http_build_query($params);
        $active = $code === $current ? ' bg-indigo-600 text-white' : ' text-gray-400 hover:text-gray-200 hover:bg-gray-700';
        $html .= '<a href="' . h($url) . '" class="px-1.5 sm:px-2 py-1 rounded transition font-medium' . $active . '" title="' . __($label) . '">' . $flag . '</a>';
    }
    $html .= '</div>';

    return $html;
}

init_lang();

migrate('series', [
    'cover'         => 'TEXT',
    'title'         => 'TEXT NOT NULL',
    'season'        => 'INTEGER',
    'episode'       => 'INTEGER',
    'status'        => "TEXT DEFAULT 'სანახავია'",
    'rating'        => 'INTEGER DEFAULT 0',
    'resource_url'  => 'TEXT',
]);

// Fix existing series: apply auto-status rules
$fixdb = db();
$fixdb->exec("UPDATE series SET season = NULL, episode = NULL, status = 'სანახავია' WHERE season > 99");
$fixdb->exec("UPDATE series SET status = 'ნანახი' WHERE season IS NOT NULL AND episode IS NOT NULL AND status <> 'ნანახი'");
$fixdb->exec("UPDATE series SET status = 'გასაგრძელებელია' WHERE (season IS NOT NULL OR episode IS NOT NULL) AND status <> 'ნანახი' AND status <> 'გასაგრძელებელია'");

migrate('movies', [
    'cover'         => 'TEXT',
    'title'         => 'TEXT NOT NULL',
    'description'   => 'TEXT',
    'status'        => "TEXT DEFAULT 'სანახავია'",
    'rating'        => 'INTEGER DEFAULT 0',
    'resource_url'  => 'TEXT',
]);

// Migrate existing external covers to local
$fixdb = db();
foreach (['series', 'movies'] as $t) {
    $rows = $fixdb->query("SELECT id, cover FROM $t WHERE cover LIKE 'http%'");
    while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
        $ext = cover_ext($row['cover']);
        $name = md5($row['cover']) . '.' . $ext;
        $local = 'uploads/covers/' . $name;
        if (!file_exists(__DIR__ . '/' . $local)) {
            $data = @file_get_contents($row['cover'], false, stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']]));
            if ($data !== false) {
                file_put_contents(__DIR__ . '/' . $local, $data);
            }
        }
        if (file_exists(__DIR__ . '/' . $local)) {
            $fixdb->exec("UPDATE $t SET cover = '$local' WHERE id = {$row['id']}");
        }
    }
}
