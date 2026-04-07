<?php
require_once __DIR__ . '/../php/config.php';
session_start();

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        $logged_in = true;
        openlog('jhs96-reunion', LOG_PID | LOG_NDELAY, LOG_LOCAL0);
        syslog(LOG_NOTICE, '[admin_login] SUCCESS from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        closelog();
    } else {
        $error = 'Incorrect password.';
        openlog('jhs96-reunion', LOG_PID | LOG_NDELAY, LOG_LOCAL0);
        syslog(LOG_WARNING, '[admin_login] FAILED from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        closelog();
    }
}
if (isset($_GET['logout'])) {
    openlog('jhs96-reunion', LOG_PID | LOG_NDELAY, LOG_LOCAL0);
    syslog(LOG_NOTICE, '[admin_logout] Admin logged out from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    closelog();
    session_destroy();
    header('Location: /admin/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JHS96 Reunion — Admin</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f0e8; color: #1a1a1a; }
  .topbar { background: #8B1A1A; color: #fff; padding: 0 2rem; height: 56px; display: flex; align-items: center; justify-content: space-between; }
  .topbar h1 { font-size: 1.1rem; letter-spacing: 0.05em; }
  .topbar a { color: #C9973A; text-decoration: none; font-size: 0.85rem; }
  .wrap { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem; }
  .login-box { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 2.5rem; max-width: 400px; margin: 4rem auto; }
  .login-box h2 { color: #8B1A1A; margin-bottom: 1.5rem; font-size: 1.4rem; }
  input[type=password], input[type=text], input[type=email], textarea, select {
    width: 100%; border: 1px solid #ddd; border-radius: 5px; padding: 10px 12px;
    font-size: 0.93rem; margin-bottom: 1rem; font-family: inherit;
  }
  .btn { background: #8B1A1A; color: #fff; border: none; border-radius: 5px; padding: 10px 24px; font-size: 0.9rem; font-weight: 700; cursor: pointer; }
  .btn:hover { background: #6a1212; }
  .btn-gold { background: #C9973A; }
  .btn-gold:hover { background: #b8832c; }
  .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
  .btn-danger { background: #c0392b; }
  .btn-danger:hover { background: #96281b; }
  .error { color: #c0392b; margin-bottom: 1rem; font-size: 0.9rem; }
  .tabs { display: flex; gap: 0; border-bottom: 2px solid #8B1A1A; margin-bottom: 2rem; }
  .tab { padding: 10px 24px; cursor: pointer; font-weight: 700; font-size: 0.88rem; letter-spacing: 0.06em; text-transform: uppercase; color: #666; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
  .tab.active { color: #8B1A1A; border-bottom-color: #8B1A1A; }
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
  .stat { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem; text-align: center; }
  .stat .num { font-size: 2.2rem; font-weight: 700; color: #8B1A1A; display: block; }
  .stat .lbl { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.08em; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; }
  th { background: #8B1A1A; color: #fff; padding: 10px 14px; text-align: left; font-size: 0.82rem; letter-spacing: 0.06em; text-transform: uppercase; }
  td { padding: 10px 14px; border-bottom: 1px solid #f0e8e0; font-size: 0.88rem; vertical-align: top; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #faf7f0; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
  .badge-yes { background: #e8f5e9; color: #2e7d32; }
  .badge-maybe { background: #fff8e1; color: #f57f17; }
  .section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
  .section-head h2 { font-size: 1.2rem; color: #8B1A1A; }
  .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
  .photo-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
  .photo-card img { width: 100%; height: 140px; object-fit: cover; }
  .photo-card .meta { padding: 8px; font-size: 0.8rem; color: #555; }
  .photo-card .meta strong { display: block; color: #1a1a1a; margin-bottom: 4px; }
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
  .modal-overlay.open { display: flex; }
  .modal { background: #fff; border-radius: 8px; padding: 2rem; width: 90%; max-width: 480px; }
  .modal h3 { color: #8B1A1A; margin-bottom: 1.25rem; }
  .modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 1rem; }
  .export-btn { float: right; }
  @media (max-width: 700px) { .stats { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<div class="topbar">
  <h1>JHS '96 Reunion &mdash; Admin Panel</h1>
  <?php if ($logged_in): ?>
  <div style="display:flex;gap:1.5rem;align-items:center;">
    <a href="/" target="_blank">View Site</a>
    <a href="?logout=1">Log Out</a>
  </div>
  <?php endif; ?>
</div>

<?php if (!$logged_in): ?>
<div class="wrap">
  <div class="login-box">
    <h2>Admin Login</h2>
    <?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="Admin password" autofocus>
      <button class="btn" type="submit" style="width:100%">Sign In</button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="wrap">
  <div class="tabs">
    <div class="tab active" onclick="switchTab('rsvps')">RSVPs</div>
    <div class="tab" onclick="switchTab('updates')">Class Updates</div>
    <div class="tab" onclick="switchTab('photos')">Photos</div>
    <div class="tab" onclick="switchTab('events')">Events</div>
  </div>

  <!-- RSVPs TAB -->
  <div class="tab-content active" id="tab-rsvps">
    <div class="stats" id="rsvp-stats">
      <div class="stat"><span class="num" id="stat-yes">-</span><span class="lbl">Attending</span></div>
      <div class="stat"><span class="num" id="stat-maybe">-</span><span class="lbl">Maybe</span></div>
      <div class="stat"><span class="num" id="stat-guests">-</span><span class="lbl">Total Guests</span></div>
      <div class="stat"><span class="num" id="stat-total">-</span><span class="lbl">Responses</span></div>
    </div>
    <div class="section-head">
      <h2>RSVP List</h2>
      <button class="btn btn-gold btn-sm" onclick="exportCSV()">Export CSV</button>
    </div>
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Location</th><th>Guests</th><th>Attending</th><th>Note</th><th>Date</th><th></th></tr></thead>
      <tbody id="rsvp-table-body"></tbody>
    </table>
  </div>

  <!-- UPDATES TAB -->
  <div class="tab-content" id="tab-updates">
    <div class="section-head"><h2>Class Updates</h2></div>
    <table>
      <thead><tr><th>Name</th><th>Location</th><th>Update</th><th>Date</th><th></th></tr></thead>
      <tbody id="updates-table-body"></tbody>
    </table>
  </div>

  <!-- PHOTOS TAB -->
  <div class="tab-content" id="tab-photos">
    <div class="section-head"><h2>Photo Gallery</h2></div>
    <div class="photo-grid" id="photo-grid-admin"></div>
  </div>

  <!-- EVENTS TAB -->
  <div class="tab-content" id="tab-events">
    <div class="section-head">
      <h2>Events</h2>
      <button class="btn btn-gold btn-sm" onclick="openEventModal(null)">+ Add Event</button>
    </div>
    <table>
      <thead><tr><th>Event</th><th>Date</th><th>Time & Location</th><th>Description</th><th></th></tr></thead>
      <tbody id="events-table-body"></tbody>
    </table>
  </div>
</div>

<!-- Event Modal -->
<div class="modal-overlay" id="event-modal">
  <div class="modal">
    <h3 id="modal-title">Edit Event</h3>
    <input type="hidden" id="ev-id">
    <label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Event Name</label>
    <input type="text" id="ev-name" placeholder="Event name">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
      <div><label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Month</label><input type="text" id="ev-month" placeholder="Sep"></div>
      <div><label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Day</label><input type="number" id="ev-day" placeholder="5"></div>
      <div><label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Year</label><input type="number" id="ev-year" placeholder="2026"></div>
    </div>
    <label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Time & Location</label>
    <input type="text" id="ev-timeloc" placeholder="7:00 PM - Venue Name">
    <label style="font-size:0.8rem;color:#666;font-weight:700;text-transform:uppercase;">Description</label>
    <textarea id="ev-desc" style="height:80px;resize:vertical;margin-bottom:0;width:100%;"></textarea>
    <div class="modal-btns">
      <button class="btn btn-sm" style="background:#ccc;color:#333;" onclick="closeModal()">Cancel</button>
      <button class="btn btn-sm" onclick="saveEvent()">Save Event</button>
    </div>
  </div>
</div>

<script>
const API = '/php/api.php';

function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t,i) => {
    const names = ['rsvps','updates','photos','events'];
    t.classList.toggle('active', names[i] === name);
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
}

// ── RSVPs ──
let rsvpData = [];
async function loadRsvps() {
  const r = await fetch(API + '?action=get_rsvps');
  const d = await r.json();
  rsvpData = d.rsvps;
  document.getElementById('stat-yes').textContent = d.yes;
  document.getElementById('stat-maybe').textContent = d.maybe;
  document.getElementById('stat-guests').textContent = d.guests;
  document.getElementById('stat-total').textContent = d.rsvps.length;
  const tbody = document.getElementById('rsvp-table-body');
  tbody.innerHTML = d.rsvps.map(r => `
    <tr>
      <td><strong>${esc(r.name)}</strong></td>
      <td>${esc(r.email||'')}</td>
      <td>${esc(r.location||'')}</td>
      <td>${r.guests}</td>
      <td><span class="badge badge-${r.attending}">${r.attending === 'yes' ? 'Attending' : 'Maybe'}</span></td>
      <td style="max-width:200px;">${esc(r.note||'')}</td>
      <td style="white-space:nowrap;">${new Date(r.created_at).toLocaleDateString()}</td>
      <td><button class="btn btn-danger btn-sm" onclick="deleteRsvp(${r.id})">Delete</button></td>
    </tr>`).join('');
}

async function deleteRsvp(id) {
  if (!confirm('Delete this RSVP?')) return;
  await fetch(API + '?action=delete_rsvp', {method:'POST', body: JSON.stringify({id})});
  loadRsvps();
}

function exportCSV() {
  const headers = ['Name','Email','Location','Guests','Attending','Note','Date'];
  const rows = rsvpData.map(r => [r.name, r.email, r.location, r.guests, r.attending, r.note, r.created_at]);
  const csv = [headers, ...rows].map(r => r.map(v => `"${String(v||'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'jhs96-rsvps.csv';
  a.click();
}

// ── UPDATES ──
async function loadUpdates() {
  const r = await fetch(API + '?action=get_updates');
  const rows = await r.json();
  document.getElementById('updates-table-body').innerHTML = rows.map(u => `
    <tr>
      <td><strong>${esc(u.name)}</strong></td>
      <td>${esc(u.location||'')}</td>
      <td style="max-width:300px;">${esc(u.body)}</td>
      <td style="white-space:nowrap;">${new Date(u.created_at).toLocaleDateString()}</td>
      <td><button class="btn btn-danger btn-sm" onclick="deleteUpdate(${u.id})">Delete</button></td>
    </tr>`).join('');
}

async function deleteUpdate(id) {
  if (!confirm('Delete this update?')) return;
  await fetch(API + '?action=delete_update', {method:'POST', body: JSON.stringify({id})});
  loadUpdates();
}

// ── PHOTOS ──
async function loadPhotos() {
  const r = await fetch(API + '?action=get_photos');
  const rows = await r.json();
  document.getElementById('photo-grid-admin').innerHTML = rows.map(p => `
    <div class="photo-card">
      <img src="${p.url}" alt="${esc(p.caption||'')}">
      <div class="meta">
        <strong>${esc(p.caption||'Untitled')}</strong>
        By ${esc(p.uploader||'')}
        <br><button class="btn btn-danger btn-sm" style="margin-top:6px;width:100%;" onclick="deletePhoto(${p.id})">Delete</button>
      </div>
    </div>`).join('');
}

async function deletePhoto(id) {
  if (!confirm('Delete this photo?')) return;
  await fetch(API + '?action=delete_photo', {method:'POST', body: JSON.stringify({id})});
  loadPhotos();
}

// ── EVENTS ──
let editingEventId = null;
async function loadEvents() {
  const r = await fetch(API + '?action=get_events');
  const rows = await r.json();
  document.getElementById('events-table-body').innerHTML = rows.map(e => `
    <tr>
      <td><strong>${esc(e.name)}</strong></td>
      <td>${esc(e.month)} ${e.day}, ${e.year}</td>
      <td>${esc(e.timeloc||'')}</td>
      <td style="max-width:250px;">${esc(e.description||'')}</td>
      <td style="white-space:nowrap;">
        <button class="btn btn-sm btn-gold" onclick="openEventModal(${JSON.stringify(e).replace(/"/g,'&quot;')})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteEvent(${e.id})">Delete</button>
      </td>
    </tr>`).join('');
}

function openEventModal(ev) {
  editingEventId = ev ? ev.id : null;
  document.getElementById('modal-title').textContent = ev ? 'Edit Event' : 'Add Event';
  document.getElementById('ev-id').value = ev?.id || '';
  document.getElementById('ev-name').value = ev?.name || '';
  document.getElementById('ev-month').value = ev?.month || '';
  document.getElementById('ev-day').value = ev?.day || '';
  document.getElementById('ev-year').value = ev?.year || 2026;
  document.getElementById('ev-timeloc').value = ev?.timeloc || '';
  document.getElementById('ev-desc').value = ev?.description || '';
  document.getElementById('event-modal').classList.add('open');
}

function closeModal() { document.getElementById('event-modal').classList.remove('open'); }

async function saveEvent() {
  const data = {
    id: editingEventId,
    name: document.getElementById('ev-name').value.trim(),
    month: document.getElementById('ev-month').value.trim(),
    day: parseInt(document.getElementById('ev-day').value),
    year: parseInt(document.getElementById('ev-year').value),
    timeloc: document.getElementById('ev-timeloc').value.trim(),
    desc: document.getElementById('ev-desc').value.trim()
  };
  if (!data.name || !data.month || !data.day) { alert('Name, month and day are required.'); return; }
  await fetch(API + '?action=save_event', {method:'POST', body: JSON.stringify(data)});
  closeModal();
  loadEvents();
}

async function deleteEvent(id) {
  if (!confirm('Delete this event?')) return;
  await fetch(API + '?action=delete_event', {method:'POST', body: JSON.stringify({id})});
  loadEvents();
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('event-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('event-modal')) closeModal();
});

loadRsvps();
loadUpdates();
loadPhotos();
loadEvents();
</script>
<?php endif; ?>
</body>
</html>
