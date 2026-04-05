<?php
require_once __DIR__ . '/config.php';

/* -- SECURITY HEADERS -- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'');

/* -- CORS - only allow same origin -- */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://judson96.org', 'https://www.judson96.org'];
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . ($allowed_origins[0]));
}
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

/* -- RATE LIMITING -- */
function rate_limit($key, $max, $window_seconds) {
    $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now = time();
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    // Remove expired entries
    $data = array_filter($data, fn($t) => $t > $now - $window_seconds);
    if (count($data) >= $max) {
        json_response(['error' => 'Too many requests. Please wait a moment and try again.'], 429);
    }
    $data[] = $now;
    file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
}

function get_ip() {
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
}

/* -- INPUT HELPERS -- */
function get_json() {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function sanitize_str($val, $max = 255) {
    return mb_substr(strip_tags(trim((string)($val ?? ''))), 0, $max);
}

$action = $_GET['action'] ?? '';

switch ($action) {

    /* -- EVENTS -- */
    case 'get_events':
        $rows = db()->query('SELECT id,name,month,day,year,timeloc,description FROM events ORDER BY sort_order,year,day')->fetchAll();
        json_response($rows);

    case 'save_event':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        $name    = sanitize_str($d['name'] ?? '', 255);
        $month   = sanitize_str($d['month'] ?? '', 3);
        $day     = (int)($d['day'] ?? 0);
        $year    = (int)($d['year'] ?? 2026);
        $timeloc = sanitize_str($d['timeloc'] ?? '', 255);
        $desc    = sanitize_str($d['desc'] ?? '', 1000);
        if (!$name || !$month || !$day) json_response(['error' => 'Name, month and day required'], 400);
        if (!empty($d['id'])) {
            $s = db()->prepare('UPDATE events SET name=?,month=?,day=?,year=?,timeloc=?,description=? WHERE id=?');
            $s->execute([$name,$month,$day,$year,$timeloc,$desc,(int)$d['id']]);
        } else {
            $s = db()->prepare('INSERT INTO events (name,month,day,year,timeloc,description,sort_order) SELECT ?,?,?,?,?,?,COALESCE(MAX(sort_order),0)+1 FROM events');
            $s->execute([$name,$month,$day,$year,$timeloc,$desc]);
        }
        json_response(['ok' => true]);

    case 'delete_event':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        db()->prepare('DELETE FROM events WHERE id=?')->execute([(int)$d['id']]);
        json_response(['ok' => true]);

    /* -- RSVPs -- */
    case 'get_rsvps':
        // Public endpoint - strip email addresses for privacy
        $rows = db()->query('SELECT id,name,location,guests,attending,created_at FROM rsvps ORDER BY created_at DESC')->fetchAll();
        $yes    = count(array_filter($rows, fn($r) => $r['attending'] === 'yes'));
        $maybe  = count($rows) - $yes;
        $guests = array_sum(array_map(fn($r) => $r['attending'] === 'yes' ? (int)$r['guests'] : 0, $rows));
        json_response(['rsvps' => $rows, 'yes' => $yes, 'maybe' => $maybe, 'guests' => $guests]);

    case 'submit_rsvp':
        // Rate limit: 3 RSVPs per IP per hour
        rate_limit('rsvp_' . get_ip(), 3, 3600);
        $d     = get_json();
        $name  = sanitize_str($d['name'] ?? '', 255);
        $email = sanitize_str($d['email'] ?? '', 255);
        $loc   = sanitize_str($d['location'] ?? '', 255);
        $note  = sanitize_str($d['note'] ?? '', 500);
        $guests   = max(1, min(20, (int)($d['guests'] ?? 1)));
        $attending = in_array($d['attending'] ?? '', ['yes','maybe']) ? $d['attending'] : 'yes';
        if (!$name || !$email) json_response(['error' => 'Name and email required'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['error' => 'Invalid email address'], 400);
        $s = db()->prepare('INSERT INTO rsvps (name,email,location,guests,attending,note) VALUES (?,?,?,?,?,?)');
        $s->execute([$name,$email,$loc,$guests,$attending,$note]);
        json_response(['ok' => true]);

    case 'delete_rsvp':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        db()->prepare('DELETE FROM rsvps WHERE id=?')->execute([(int)$d['id']]);
        json_response(['ok' => true]);

    /* -- CLASS UPDATES -- */
    case 'get_updates':
        $rows = db()->query('SELECT id,name,location,body,created_at FROM updates_feed ORDER BY created_at DESC')->fetchAll();
        json_response($rows);

    case 'submit_update':
        // Rate limit: 5 updates per IP per hour
        rate_limit('update_' . get_ip(), 5, 3600);
        $d    = get_json();
        $name = sanitize_str($d['name'] ?? '', 255);
        $loc  = sanitize_str($d['location'] ?? '', 255);
        $body = sanitize_str($d['body'] ?? '', 1000);
        if (!$name || !$body) json_response(['error' => 'Name and update required'], 400);
        $s = db()->prepare('INSERT INTO updates_feed (name,location,body) VALUES (?,?,?)');
        $s->execute([$name,$loc,$body]);
        json_response(['ok' => true]);

    case 'delete_update':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        db()->prepare('DELETE FROM updates_feed WHERE id=?')->execute([(int)$d['id']]);
        json_response(['ok' => true]);

    /* -- PHOTOS -- */
    case 'get_photos':
        $rows = db()->query('SELECT id,caption,uploader,filename,created_at FROM photos ORDER BY created_at DESC')->fetchAll();
        foreach ($rows as &$r) {
            $r['url'] = strpos($r['filename'], 'http') === 0
                ? $r['filename']
                : '/uploads/' . $r['filename'];
        }
        json_response($rows);

    case 'upload_photo':
        // Rate limit: 10 photo uploads per IP per hour
        rate_limit('photo_' . get_ip(), 10, 3600);
        if (empty($_FILES['photo'])) json_response(['error' => 'No file received'], 400);
        $file    = $_FILES['photo'];
        $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
        $allowed_exts  = ['jpg','jpeg','png','gif','webp'];
        // Verify MIME type from actual file content, not just extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_types)) json_response(['error' => 'Invalid file type'], 400);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) json_response(['error' => 'Invalid file extension'], 400);
        if ($file['size'] > 10 * 1024 * 1024) json_response(['error' => 'File too large (max 10MB)'], 400);
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
        $caption  = sanitize_str($_POST['caption'] ?? '', 255);
        $uploader = sanitize_str($_POST['uploader'] ?? 'Classmate', 100);
        $s = db()->prepare('INSERT INTO photos (caption,uploader,filename) VALUES (?,?,?)');
        $s->execute([$caption, $uploader, $filename]);
        json_response(['ok' => true, 'url' => '/uploads/' . $filename]);

    case 'delete_photo':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d   = get_json();
        $row = db()->prepare('SELECT filename FROM photos WHERE id=?');
        $row->execute([(int)$d['id']]);
        $photo = $row->fetch();
        if ($photo && strpos($photo['filename'], 'http') === false) {
            @unlink(UPLOAD_DIR . $photo['filename']);
        }
        db()->prepare('DELETE FROM photos WHERE id=?')->execute([(int)$d['id']]);
        json_response(['ok' => true]);

    /* -- ADMIN AUTH -- */
    case 'admin_login':
        session_start();
        // Brute force protection: 5 attempts per IP per 15 minutes
        rate_limit('admin_login_' . get_ip(), 5, 900);
        $d = get_json();
        if (hash_equals(ADMIN_PASSWORD, $d['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            json_response(['ok' => true]);
        } else {
            // Consistent timing to prevent timing attacks
            usleep(500000);
            json_response(['error' => 'Incorrect password'], 401);
        }

    case 'admin_logout':
        session_start();
        session_destroy();
        json_response(['ok' => true]);

    case 'admin_check':
        session_start();
        json_response(['admin' => is_admin()]);

    default:
        json_response(['error' => 'Unknown action'], 404);
}
