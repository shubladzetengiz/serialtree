<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

$dbPath = __DIR__ . '/data.sqlite';

// Check if DB exists before routing
if (!file_exists($dbPath)) {
    // Handle actions
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
            // Create empty DB, then import
            file_put_contents($dbPath, '');
            require __DIR__ . '/config.php';
            $result = import_sql($_FILES['sql_file']['tmp_name']);
            if ($result['success']) {
                header('Location: /');
                exit;
            }
            $err = 'Import failed: ' . ($result['message'] ?? 'unknown error');
            // Clean up failed DB
            unlink($dbPath);
        }
        // Show error
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SerialManager</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem;text-align:center}.box{background:#1e1e1e;padding:2rem;border-radius:12px;max-width:420px}p{color:#999;margin:1rem 0;line-height:1.5}.error{color:#f87171;background:rgba(248,113,113,.1);padding:.75rem;border-radius:8px;margin:1rem 0;font-size:.9rem}.btn{display:inline-block;padding:.6rem 1.5rem;border-radius:8px;text-decoration:none;font-size:1rem;cursor:pointer;border:none;margin:.3rem;font-family:inherit}.btn-primary{background:#2563eb;color:#fff}.btn-secondary{background:#444;color:#eee}</style></head><body><div class="box"><h2>Import failed</h2><p class="error">' . htmlspecialchars($err) . '</p><a href="/" class="btn btn-primary">Back</a></div></body></html>';
        exit;
    }

    if ($action === 'cancel') {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SerialManager</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:1rem;text-align:center}.box{background:#1e1e1e;padding:2rem;border-radius:12px;max-width:400px}p{color:#999;margin:1rem 0}.btn{display:inline-block;padding:.6rem 1.5rem;border-radius:8px;text-decoration:none;font-size:1rem;cursor:pointer;border:none;margin:.3rem}.btn-primary{background:#2563eb;color:#fff}</style></head><body><div class="box"><h2>Database not found</h2><p>Operation cancelled. The application cannot run without a database.</p><a href="/" class="btn btn-primary">Back</a></div></body></html>';
        exit;
    }

    // Show choice page: create or import
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

// DB exists — normal routing
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}
require __DIR__ . '/index.php';
