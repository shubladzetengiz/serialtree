<?php

$dbPath = __DIR__ . '/data.sqlite';

if (!file_exists($dbPath)) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'create') {
        file_put_contents($dbPath, '');
        header('Location: /');
        exit;
    }

    if ($action === 'import' && isset($_FILES['sql_file'])) {
        if ($_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $err = 'Upload error: ' . $_FILES['sql_file']['error'];
        } elseif (strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION)) !== 'sql') {
            $err = 'Only .sql files allowed';
        } elseif ($_FILES['sql_file']['size'] > 10 * 1024 * 1024) {
            $err = 'File too large (max 10MB)';
        } else {
            file_put_contents($dbPath, '');
            require_once __DIR__ . '/config.php';
            $result = import_sql($_FILES['sql_file']['tmp_name']);
            if ($result['success']) {
                header('Location: /');
                exit;
            }
            $err = 'Import failed: ' . ($result['message'] ?? 'unknown error');
            unlink($dbPath);
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SerialManager</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem;text-align:center}.box{background:#1e1e1e;padding:2rem;border-radius:12px;max-width:420px}p{color:#999;margin:1rem 0;line-height:1.5}.error{color:#f87171;background:rgba(248,113,113,.1);padding:.75rem;border-radius:8px;margin:1rem 0;font-size:.9rem}.btn{display:inline-block;padding:.6rem 1.5rem;border-radius:8px;text-decoration:none;font-size:1rem;cursor:pointer;border:none;margin:.3rem;font-family:inherit}.btn-primary{background:#2563eb;color:#fff}</style></head><body><div class="box"><h2>Import failed</h2><p class="error">' . htmlspecialchars($err) . '</p><a href="/" class="btn btn-primary">Back</a></div></body></html>';
        exit;
    }

    if ($action === 'cancel') {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SerialManager</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem;text-align:center}.box{background:#1e1e1e;padding:2rem;border-radius:12px;max-width:400px}p{color:#999;margin:1rem 0}.btn{display:inline-block;padding:.6rem 1.5rem;border-radius:8px;text-decoration:none;font-size:1rem;cursor:pointer;border:none;margin:.3rem}.btn-primary{background:#2563eb;color:#fff}</style></head><body><div class="box"><h2>Database not found</h2><p>Operation cancelled. The application cannot run without a database.</p><a href="/" class="btn btn-primary">Back</a></div></body></html>';
        exit;
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SerialManager</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem;text-align:center}.box{background:#1e1e1e;padding:2rem;border-radius:12px;max-width:420px}h2{margin:0 0 .5rem}p{color:#999;margin:1rem 0;line-height:1.5}hr{border:none;border-top:1px solid #333;margin:1.5rem 0}.btn{display:inline-block;padding:.6rem 1.5rem;border-radius:8px;text-decoration:none;font-size:1rem;cursor:pointer;border:none;margin:.3rem;font-family:inherit}.btn-primary{background:#2563eb;color:#fff}.btn-secondary{background:#444;color:#eee}.btn-green{background:#16a34a;color:#fff}input[type=file]{color:#ccc;font-size:.9rem;margin-bottom:1rem;display:block;width:100%}.file-label{display:block;text-align:left;color:#999;font-size:.85rem;margin-bottom:.3rem}</style></head><body><div class="box"><h2>Database not found</h2><p>File <code>data.sqlite</code> does not exist.<br>What would you like to do?</p>
    <form method="post" style="margin-top:1.5rem">
        <input type="hidden" name="action" value="create">
        <button class="btn btn-primary">Create empty database</button>
    </form>
    <hr>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import">
        <label class="file-label">Import from SQL dump:</label>
        <input type="file" name="sql_file" accept=".sql" required>
        <button class="btn btn-green" style="margin-top:.3rem">Import</button>
    </form>
    <hr>
    <form method="post">
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn-secondary">Cancel</button>
    </form>
</div></body></html>';
    exit;
}

require_once __DIR__ . '/config.php';

$type = $_GET['type'] ?? 'series';
if (!in_array($type, ['series', 'movies'], true)) {
    $type = 'series';
}

$db = db();

// --- AJAX: update rating ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    verify_csrf();
    $idCol = $type === 'series' ? 'series_id' : 'movie_id';
    $id = (int)($_POST[$idCol] ?? 0);
    $rating = min(5, max(0, (int)($_POST['rating_value'] ?? 0)));
    $table = $type === 'series' ? 'series' : 'movies';
    $stmt = $db->prepare("UPDATE $table SET rating = :rating WHERE id = :id");
    $stmt->bindValue(':rating', $rating, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    json_response(['success' => true]);
}

// --- AJAX: update status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf();
    $idCol = $type === 'series' ? 'series_id' : 'movie_id';
    $id = (int)($_POST[$idCol] ?? 0);
    $status = $_POST['status_value'] ?? '';
    $table = $type === 'series' ? 'series' : 'movies';
    $stmt = $db->prepare("UPDATE $table SET status = :status WHERE id = :id");
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    json_response(['success' => true]);
}

// --- AJAX: download cover ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_cover'])) {
    verify_csrf();
    $url = $_POST['cover_url'] ?? '';
    if (!preg_match('#^https?://#', $url)) {
        json_response(['success' => false, 'error' => 'Invalid URL']);
    }
    $ext = cover_ext($url);
    $name = md5($url) . '.' . $ext;
    $local = 'uploads/covers/' . $name;
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']]));
    if ($data === false) {
        json_response(['success' => false, 'error' => 'Download failed']);
    }
    file_put_contents(__DIR__ . '/' . $local, $data);
    json_response(['success' => true, 'local_path' => $local]);
}

// --- Export ---
if (isset($_GET['export'])) {
    export_sql();
}

// --- Import ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    verify_csrf();
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Upload failed'];
        redirect_with_filters("?type=$type");
    }
    $file = $_FILES['sql_file'];
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Only .sql files allowed'];
        redirect_with_filters("?type=$type");
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'File too large (max 10MB)'];
        redirect_with_filters("?type=$type");
    }
    $result = import_sql($file['tmp_name']);
    $_SESSION['flash'] = $result['success']
        ? ['type' => 'success', 'message' => "Imported {$result['count']} records"]
        : ['type' => 'error', 'message' => 'Import failed: ' . ($result['message'] ?? 'unknown error')];
    redirect_with_filters("?type=$type");
}

// --- CRUD ---
$table = $type === 'series' ? 'series' : 'movies';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    verify_csrf();
    if ($type === 'series') {
        $stmt = $db->prepare("INSERT INTO series (cover, title, season, episode, status, rating, resource_url) VALUES (:cover, :title, :season, :episode, :status, :rating, :resource_url)");
        $s = (int)($_POST['season'] ?? 0);
        $e = (int)($_POST['episode'] ?? 0);
        $status = $_POST['status'] ?? 'სანახავია';
        if ($s > 99) {
            $s = 0;
            $e = 0;
            $status = 'სანახავია';
        } elseif ($s > 0 && $e > 0) {
            $status = 'ნანახი';
        } elseif ($s > 0) {
            $status = 'გასაგრძელებელია';
        } elseif ($e > 0) {
            $status = 'გასაგრძელებელია';
        } else {
            $status = 'სანახავია';
        }
        $stmt->bindValue(':season', $s > 0 ? $s : null, SQLITE3_INTEGER);
        $stmt->bindValue(':episode', $e > 0 ? $e : null, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':resource_url', $_POST['resource_url'] ?? '', SQLITE3_TEXT);
    } else {
        $stmt = $db->prepare("INSERT INTO movies (cover, title, description, status, rating, resource_url) VALUES (:cover, :title, :description, :status, :rating, :resource_url)");
        $stmt->bindValue(':description', $_POST['description'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':status', $_POST['status'] ?? 'სანახავია', SQLITE3_TEXT);
        $stmt->bindValue(':resource_url', $_POST['resource_url'] ?? '', SQLITE3_TEXT);
    }
    $stmt->bindValue(':cover', $_POST['cover'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':title', $_POST['title'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':rating', min(5, max(0, (int)($_POST['rating'] ?? 0))), SQLITE3_INTEGER);
    $stmt->execute();
    redirect_with_filters("?type=$type");
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM $table WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    redirect_with_filters("?type=$type");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    verify_csrf();
    $id = (int)$_POST['id'];

    if ($type === 'series') {
        $stmt = $db->prepare("UPDATE series SET cover = :cover, title = :title, season = :season, episode = :episode, status = :status, rating = :rating, resource_url = :resource_url WHERE id = :id");
        $s = (int)($_POST['season'] ?? 0);
        $e = (int)($_POST['episode'] ?? 0);
        $status = $_POST['status'] ?? 'სანახავია';
        if ($s > 99) {
            $s = 0;
            $e = 0;
            $status = 'სანახავია';
        } elseif ($s > 0 && $e > 0) {
            $status = 'ნანახი';
        } elseif ($s > 0) {
            $status = 'გასაგრძელებელია';
        } elseif ($e > 0) {
            $status = 'გასაგრძელებელია';
        } else {
            $status = 'სანახავია';
        }
        $stmt->bindValue(':season', $s > 0 ? $s : null, SQLITE3_INTEGER);
        $stmt->bindValue(':episode', $e > 0 ? $e : null, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':resource_url', $_POST['resource_url'] ?? '', SQLITE3_TEXT);
    } else {
        $stmt = $db->prepare("UPDATE movies SET cover = :cover, title = :title, description = :description, status = :status, rating = :rating, resource_url = :resource_url WHERE id = :id");
        $stmt->bindValue(':description', $_POST['description'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':status', $_POST['status'] ?? 'სანახავია', SQLITE3_TEXT);
        $stmt->bindValue(':resource_url', $_POST['resource_url'] ?? '', SQLITE3_TEXT);
    }
    $stmt->bindValue(':cover', $_POST['cover'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':title', $_POST['title'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':rating', min(5, max(0, (int)($_POST['rating'] ?? 0))), SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    redirect_with_filters("?type=$type");
}

// --- Fetch data ---
$editData = [];
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editData = $db->querySingle("SELECT * FROM $table WHERE id = $id", true) ?: [];
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status_filter'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';
$where = [];
$params = [];

if ($statusFilter !== '') {
    $where[] = "status = :status";
    $params[':status'] = $statusFilter;
}

$orderBy = match ($sort) {
    'date_asc'  => 'ORDER BY id ASC',
    'alpha_asc' => 'ORDER BY title COLLATE NOCASE ASC',
    'alpha_desc'=> 'ORDER BY title COLLATE NOCASE DESC',
    default     => 'ORDER BY id DESC',
};

$sql = "SELECT * FROM $table";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " $orderBy";

$stmt = $db->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, SQLITE3_TEXT);
}
$result = $stmt->execute();

// PHP-side case-insensitive Unicode search (mbstring required)
$items = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($search === '' || mb_stripos($row['title'], $search, 0, 'UTF-8') !== false) {
        $items[] = $row;
    }
}
$total = count($items);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- Render ---
$pageTitle = $type === 'series' ? __('series_page') : __('movies_page');
$isEditing = !empty($editData);

require __DIR__ . '/views/layout.php';
