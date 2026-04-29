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

/* -- SYSLOG HELPER -- */
function jhs_log($level, $message) {
    openlog('jhs96-reunion', LOG_PID | LOG_NDELAY, LOG_LOCAL0);
    syslog($level, $message);
    closelog();
}

/* -- EMAIL NOTIFICATIONS -- */
define('NOTIFY_TO',    'staff@judson96.org');
define('NOTIFY_FROM',  'noreply@judson96.org');
define('SITE_NAME',    'JHS96 30th Reunion');
define('SMTP_HOST',    '192.178.20.72');
define('SMTP_PORT',    25);

function smtp_send($to, $subject, $html_body) {
    $from = NOTIFY_FROM;
    $host = SMTP_HOST;
    $port = SMTP_PORT;

    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$sock) {
        jhs_log(LOG_ERR, '[email] SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    $read = function() use ($sock) {
        $r = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            $r .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $r;
    };

    $send = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    // Read greeting
    $resp = $read();
    if (substr($resp, 0, 3) !== '220') {
        jhs_log(LOG_ERR, '[email] SMTP bad greeting: ' . trim($resp));
        fclose($sock); return false;
    }

    // EHLO
    $send('EHLO judson96.org');
    $ehlo = $read();

    // STARTTLS
    $send('STARTTLS');
    $tls = $read();
    if (substr($tls, 0, 3) !== '220') {
        jhs_log(LOG_ERR, '[email] STARTTLS failed: ' . trim($tls));
        fclose($sock); return false;
    }

    // Upgrade to TLS
    stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    // EHLO again after TLS
    $send('EHLO judson96.org');
    $read();

    // MAIL FROM
    $send('MAIL FROM:<' . $from . '>');
    $r = $read();
    if (substr($r, 0, 3) !== '250') {
        jhs_log(LOG_ERR, '[email] MAIL FROM rejected: ' . trim($r));
        fclose($sock); return false;
    }

    // RCPT TO
    $send('RCPT TO:<' . $to . '>');
    $r = $read();
    if (substr($r, 0, 3) !== '250') {
        jhs_log(LOG_ERR, '[email] RCPT TO rejected: ' . trim($r));
        fclose($sock); return false;
    }

    // DATA
    $send('DATA');
    $r = $read();
    if (substr($r, 0, 3) !== '354') {
        jhs_log(LOG_ERR, '[email] DATA rejected: ' . trim($r));
        fclose($sock); return false;
    }

    // Headers + body
    $date    = date('r');
    $msgid   = '<' . uniqid('jhs96.', true) . '@judson96.org>';
    $message = implode("\r\n", [
        'Date: '         . $date,
        'From: '         . SITE_NAME . ' <' . $from . '>',
        'To: '           . $to,
        'Message-ID: '   . $msgid,
        'Subject: '      . '[JHS96] ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        '',
        quoted_printable_encode($html_body),
        '.',
    ]);
    $send($message);
    $r = $read();
    if (substr($r, 0, 3) !== '250') {
        jhs_log(LOG_ERR, '[email] Message rejected: ' . trim($r));
        fclose($sock); return false;
    }

    $send('QUIT');
    fclose($sock);
    return true;
}

function send_notification($subject, $html_body) {
    $result = smtp_send(NOTIFY_TO, $subject, $html_body);
    jhs_log($result ? LOG_INFO : LOG_WARNING,
        '[email] ' . ($result ? 'SENT' : 'FAILED') . ' subject: ' . $subject);
}

function btn($text) {
    return "<div style='margin-top:20px;text-align:center;'><a href='https://judson96.org/admin' style='background:#C9973A;color:#fff;padding:10px 24px;text-decoration:none;border-radius:4px;font-weight:bold;display:inline-block;'>" . $text . "</a></div>";
}

function email_wrap($title, $content) {
    return "<html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;'>
<div style='background:#8B1A1A;padding:20px;text-align:center;'>
  <h1 style='color:#fff;margin:0;font-size:1.3rem;'>" . $title . "</h1>
</div>
<div style='padding:24px;background:#f9f5ee;'>" . $content . btn('View in Admin Panel') . "</div>
<div style='padding:12px;text-align:center;color:#999;font-size:0.8rem;'>judson96.org &mdash; JHS Class of 1996</div>
</body></html>";
}

function row($label, $value) {
    return "<tr><td style='padding:8px 0;color:#666;width:130px;vertical-align:top;'>" . $label . "</td>
            <td style='padding:8px 0;font-weight:bold;'>" . htmlspecialchars((string)$value) . "</td></tr>";
}

function notify_rsvp($name, $location, $guests, $attending, $note) {
    $badge = $attending === 'yes' ? '✅ Attending' : '🤔 Maybe';
    $content = "<table style='width:100%;border-collapse:collapse;'>"
        . row('Name', $name)
        . row('Status', $badge)
        . row('Location', $location ?: '—')
        . row('Guests', $guests)
        . row('Note', $note ?: '—')
        . "</table>";
    send_notification('New RSVP from ' . $name, email_wrap('New RSVP &mdash; JHS96 Reunion', $content));
}

function notify_update($name, $location, $body) {
    $content = "<table style='width:100%;border-collapse:collapse;'>"
        . row('From', $name)
        . row('Location', $location ?: '—')
        . "</table>
        <div style='margin-top:16px;padding:16px;background:#fff;border-left:4px solid #8B1A1A;border-radius:2px;'>
          <p style='margin:0;line-height:1.6;'>" . nl2br(htmlspecialchars($body)) . "</p>
        </div>";
    send_notification('New Class Update from ' . $name, email_wrap('New Class Update &mdash; JHS96 Reunion', $content));
}

function notify_photo($caption, $uploader, $url) {
    $img_url = 'https://judson96.org' . htmlspecialchars($url);
    $content = "<table style='width:100%;border-collapse:collapse;'>"
        . row('Uploaded by', $uploader)
        . row('Caption', $caption ?: '—')
        . "</table>
        <div style='margin-top:16px;text-align:center;'>
          <img src='" . $img_url . "' style='max-width:100%;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.15);'>
        </div>";
    send_notification('New Photo from ' . $uploader, email_wrap('New Photo &mdash; JHS96 Reunion', $content));
}

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
        jhs_log(LOG_WARNING, '[rate_limit] BLOCKED key: ' . $key . ' attempts: ' . count($data) . ' limit: ' . $max);
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
        jhs_log(LOG_INFO, '[submit_rsvp] New RSVP from IP: ' . get_ip() . ' name: ' . $name . ' attending: ' . $attending);
        notify_rsvp($name, $loc, $guests, $attending, $note);
        json_response(['ok' => true]);

    case 'delete_rsvp':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        db()->prepare('DELETE FROM rsvps WHERE id=?')->execute([(int)$d['id']]);
        jhs_log(LOG_NOTICE, '[delete_rsvp] Admin deleted RSVP id: ' . (int)$d['id'] . ' from IP: ' . get_ip());
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
        jhs_log(LOG_INFO, '[submit_update] New update from IP: ' . get_ip() . ' name: ' . $name);
        notify_update($name, $loc, $body);
        json_response(['ok' => true]);

    case 'delete_update':
        session_start();
        if (!is_admin()) json_response(['error' => 'Unauthorized'], 401);
        $d = get_json();
        db()->prepare('DELETE FROM updates_feed WHERE id=?')->execute([(int)$d['id']]);
        jhs_log(LOG_NOTICE, '[delete_update] Admin deleted update id: ' . (int)$d['id'] . ' from IP: ' . get_ip());
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
        $log = [];
        $log[] = '[upload_photo] Request received from IP: ' . get_ip();

        // Rate limit check
        rate_limit('photo_' . get_ip(), 10, 3600);
        $log[] = '[upload_photo] Rate limit passed';

        // Check file was received
        if (empty($_FILES['photo'])) {
            jhs_log(LOG_ERR, implode(' | ', $log) . ' | ERROR: No file received');
            json_response(['error' => 'No file received', 'log' => $log], 400);
        }

        $file = $_FILES['photo'];
        $log[] = '[upload_photo] File received: name=' . $file['name']
               . ' size=' . $file['size'] . ' bytes'
               . ' tmp_name=' . $file['tmp_name']
               . ' upload_error=' . $file['error'];

        // Check for PHP upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension',
            ];
            $msg = $upload_errors[$file['error']] ?? 'Unknown upload error code: ' . $file['error'];
            $log[] = '[upload_photo] ERROR: PHP upload error - ' . $msg;
            jhs_log(LOG_ERR, implode(' | ', $log));
            json_response(['error' => $msg, 'log' => $log], 400);
        }
        $log[] = '[upload_photo] No PHP upload errors';

        // Size check
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            $log[] = '[upload_photo] ERROR: File too large - ' . $file['size'] . ' bytes (max ' . $max_size . ')';
            jhs_log(LOG_ERR, implode(' | ', $log));
            json_response(['error' => 'File too large (max 10MB)', 'log' => $log], 400);
        }
        $log[] = '[upload_photo] Size check passed: ' . $file['size'] . ' bytes';

        // Detect real MIME type from file contents (not filename)
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $log[] = '[upload_photo] Detected MIME type: ' . $mime . ' | Original filename: ' . $file['name'];

        if (!isset($allowed_types[$mime])) {
            $log[] = '[upload_photo] ERROR: MIME type not allowed - ' . $mime;
            jhs_log(LOG_ERR, implode(' | ', $log));
            json_response(['error' => 'Invalid file type: ' . $mime . ' (allowed: jpeg, png, gif, webp)', 'log' => $log], 400);
        }

        // Use extension derived from actual MIME type (fixes mismatched extensions like .png on a JPEG)
        $ext = $allowed_types[$mime];
        $orig_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($orig_ext !== $ext) {
            $log[] = '[upload_photo] NOTE: Extension mismatch - filename says .' . $orig_ext . ' but file is actually ' . $mime . ' - using .' . $ext;
        }
        $log[] = '[upload_photo] MIME and extension checks passed - using ext: ' . $ext;

        // Ensure upload directory exists
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
            $log[] = '[upload_photo] Created upload directory: ' . UPLOAD_DIR;
        }
        $log[] = '[upload_photo] Upload dir exists: ' . UPLOAD_DIR . ' writable=' . (is_writable(UPLOAD_DIR) ? 'yes' : 'NO');

        // Generate safe random filename
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;
        $log[] = '[upload_photo] Destination path: ' . $dest;

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $log[] = '[upload_photo] ERROR: move_uploaded_file failed';
            jhs_log(LOG_ERR, implode(' | ', $log));
            json_response(['error' => 'Failed to save file', 'log' => $log], 500);
        }
        $log[] = '[upload_photo] File saved successfully';

        // Save to database
        $caption  = sanitize_str($_POST['caption'] ?? '', 255);
        $uploader = sanitize_str($_POST['uploader'] ?? 'Classmate', 100);
        $log[] = '[upload_photo] Saving to DB - caption: ' . $caption . ' uploader: ' . $uploader;
        $s = db()->prepare('INSERT INTO photos (caption,uploader,filename) VALUES (?,?,?)');
        $s->execute([$caption, $uploader, $filename]);
        $log[] = '[upload_photo] DB insert successful - photo ID: ' . db()->lastInsertId();

        notify_photo($caption, $uploader, '/uploads/' . $filename);
        jhs_log(LOG_INFO, implode(' | ', $log));
        json_response(['ok' => true, 'url' => '/uploads/' . $filename, 'log' => $log]);

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
        jhs_log(LOG_NOTICE, '[delete_photo] Admin deleted photo id: ' . (int)$d['id'] . ' from IP: ' . get_ip());
        json_response(['ok' => true]);

    /* -- ADMIN AUTH -- */
    case 'admin_login':
        session_start();
        rate_limit('admin_login_' . get_ip(), 5, 900);
        $d = get_json();
        if (hash_equals(ADMIN_PASSWORD, $d['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            jhs_log(LOG_NOTICE, '[admin_login] SUCCESS from IP: ' . get_ip());
            json_response(['ok' => true]);
        } else {
            usleep(500000);
            jhs_log(LOG_WARNING, '[admin_login] FAILED from IP: ' . get_ip());
            json_response(['error' => 'Incorrect password'], 401);
        }

    case 'admin_logout':
        session_start();
        jhs_log(LOG_NOTICE, '[admin_logout] Admin logged out from IP: ' . get_ip());
        session_destroy();
        json_response(['ok' => true]);

    case 'admin_check':
        session_start();
        json_response(['admin' => is_admin()]);

    default:
        json_response(['error' => 'Unknown action'], 404);
}
