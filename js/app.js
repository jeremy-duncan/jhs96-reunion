const API = '/php/api.php';

/* -- XSS SANITIZATION -- always use this for any user content rendered to DOM */
function esc(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str || '')));
  return d.innerHTML;
}

/* -- NAVIGATION -- */
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  const link = document.querySelector('[data-page="' + id + '"]');
  if (link) link.classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
  document.querySelector('.nav-links').classList.remove('open');
}

document.querySelector('.nav-hamburger').addEventListener('click', () => {
  document.querySelector('.nav-links').classList.toggle('open');
});

/* -- COUNTDOWN -- */
function updateCountdown() {
  const target = new Date('2026-09-05T12:00:00');
  const now = new Date();
  const diff = target - now;
  if (diff <= 0) {
    document.getElementById('countdown-strip').innerHTML = '<div id="countdown-message">Welcome Back, Rockets! The Reunion is Here!</div>';
    return;
  }
  const d = Math.floor(diff / 86400000);
  const h = Math.floor((diff % 86400000) / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  document.getElementById('cd-d').textContent = d;
  document.getElementById('cd-h').textContent = String(h).padStart(2, '0');
  document.getElementById('cd-m').textContent = String(m).padStart(2, '0');
  document.getElementById('cd-s').textContent = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);

/* -- TOAST -- */
let toastTimer;
function showToast(msg) {
  clearTimeout(toastTimer);
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* -- MODAL HELPERS -- */
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

function initials(name) {
  return String(name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

async function loadEvents() {
  try {
    const res = await fetch(API + '?action=get_events');
    const events = await res.json();
    const list = document.getElementById('events-list');
    if (!list) return;
    list.innerHTML = events.map(ev => `
      <div class="event-card" id="event-${parseInt(ev.id)}">
        <div class="event-datebox">
          <div class="ev-month">${esc(ev.month)}</div>
          <div class="ev-day">${parseInt(ev.day)}</div>
          <div class="ev-year">${parseInt(ev.year)}</div>
        </div>
        <div class="event-body">
          <h3>${esc(ev.name)}</h3>
          <div class="event-meta">${esc(ev.timeloc)}</div>
          <p>${esc(ev.description)}</p>
        </div>
      </div>`).join('');
  } catch(e) { console.error('loadEvents:', e); }
}

/* -- RSVP -- */
async function loadRsvps() {
  try {
    const res = await fetch(API + '?action=get_rsvps');
    const d = await res.json();
    document.getElementById('rsvp-count-yes').textContent = parseInt(d.yes) || 0;
    document.getElementById('rsvp-count-maybe').textContent = parseInt(d.maybe) || 0;
    document.getElementById('rsvp-count-guests').textContent = parseInt(d.guests) || 0;
    const list = document.getElementById('rsvp-list');
    if (!list) return;
    list.innerHTML = (d.rsvps || []).map(r => `
      <div class="rsvp-item">
        <div class="rsvp-avatar">${esc(initials(r.name))}</div>
        <div class="rsvp-info">
          <strong>${esc(r.name)}</strong>
          <span>${esc(r.location || '')}${r.guests > 1 ? ' - ' + parseInt(r.guests) + ' guests' : ''}</span>
        </div>
        <div class="rsvp-badge ${r.attending === 'maybe' ? 'maybe' : ''}">${r.attending === 'yes' ? 'Attending' : 'Maybe'}</div>
      </div>`).join('');
  } catch(e) { console.error('loadRsvps:', e); }
}

async function submitRsvp() {
  const name = document.getElementById('rsvp-name').value.trim();
  const email = document.getElementById('rsvp-email').value.trim();
  if (!name || !email) { showToast('Please enter your name and email.'); return; }
  const data = {
    name, email,
    location: document.getElementById('rsvp-location').value.trim(),
    guests: parseInt(document.getElementById('rsvp-guests').value) || 1,
    attending: document.getElementById('rsvp-attending').value,
    note: document.getElementById('rsvp-note').value.trim()
  };
  try {
    const res = await fetch(API + '?action=submit_rsvp', { method: 'POST', body: JSON.stringify(data) });
    const result = await res.json();
    if (result.ok) {
      ['rsvp-name','rsvp-email','rsvp-location','rsvp-note'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('rsvp-guests').value = '1';
      document.getElementById('rsvp-attending').value = 'yes';
      loadRsvps();
      showToast('Thanks ' + name.split(' ')[0] + '! Your RSVP has been saved!');
    } else {
      showToast(result.error || 'Something went wrong.');
    }
  } catch(e) { showToast('Connection error. Please try again.'); }
}

/* -- CLASS UPDATES -- */
async function loadUpdates() {
  try {
    const res = await fetch(API + '?action=get_updates');
    const rows = await res.json();
    const feed = document.getElementById('updates-feed');
    if (!feed) return;
    feed.innerHTML = (rows || []).map(u => `
      <div class="update-card">
        <div class="update-header">
          <div class="update-avatar">${esc(initials(u.name))}</div>
          <div class="update-meta">
            <strong>${esc(u.name)}</strong>
            <span>${esc(u.location || '')} - ${new Date(u.created_at).toLocaleDateString('en-US', {month:'long', year:'numeric'})}</span>
          </div>
        </div>
        <p>${esc(u.body)}</p>
      </div>`).join('');
  } catch(e) { console.error('loadUpdates:', e); }
}

async function submitUpdate() {
  const name = document.getElementById('upd-name').value.trim();
  const body = document.getElementById('upd-text').value.trim();
  if (!name || !body) { showToast('Please enter your name and update.'); return; }
  if (body.length > 1000) { showToast('Update is too long (max 1000 characters).'); return; }
  const data = { name, location: document.getElementById('upd-location').value.trim(), body };
  try {
    const res = await fetch(API + '?action=submit_update', { method: 'POST', body: JSON.stringify(data) });
    const result = await res.json();
    if (result.ok) {
      ['upd-name','upd-location','upd-text'].forEach(id => document.getElementById(id).value = '');
      loadUpdates();
      showToast('Update posted ' + name.split(' ')[0] + '!');
    } else {
      showToast(result.error || 'Something went wrong.');
    }
  } catch(e) { showToast('Connection error. Please try again.'); }
}

/* -- LIGHTBOX -- */
function openLightbox(url, caption) {
  document.getElementById('lightbox-img').src = url;
  document.getElementById('lightbox-img').alt = caption;
  document.getElementById('lightbox-caption').textContent = caption;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.getElementById('lightbox-img').src = '';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeLightbox();
});

/* -- PHOTOS -- */
async function loadPhotos() {
  try {
    const res = await fetch(API + '?action=get_photos');
    const photos = await res.json();
    const grid = document.getElementById('photo-grid');
    if (!grid) return;
    const defaults = [
      { url: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=600&q=75', caption: 'Red Campus - Class of 96', uploader: 'Admin' },
      { url: 'https://images.unsplash.com/photo-1566577739112-5180d4bf9390?w=600&q=75', caption: 'Rockets Football 1995', uploader: 'Admin' },
      { url: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=75', caption: 'Senior Week 1996', uploader: 'Admin' },
      { url: 'https://images.unsplash.com/photo-1540479859555-17af45c78602?w=600&q=75', caption: 'Prom Night 96', uploader: 'Admin' }
    ];
    const all = (Array.isArray(photos) && photos.length > 0) ? photos : defaults;
    grid.innerHTML = all.map(p => `
      <div class="photo-card" onclick="openLightbox('${esc(p.url)}', '${esc(p.caption || 'Photo')}')" style="cursor:zoom-in;">
        <img src="${esc(p.url)}" alt="${esc(p.caption || '')}" loading="lazy">
        <div class="photo-caption">
          <strong>${esc(p.caption || 'Photo')}</strong>
          <span>Shared by ${esc(p.uploader || 'Classmate')}</span>
        </div>
      </div>`).join('');
  } catch(e) { console.error('loadPhotos:', e); }
}

async function handleUpload(e) {
  const files = Array.from(e.target.files);
  if (!files.length) return;
  const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
  for (const file of files) {
    if (!allowed.includes(file.type)) { showToast('Only JPG, PNG, GIF and WebP allowed.'); return; }
    if (file.size > 10 * 1024 * 1024) { showToast('Max file size is 10MB.'); return; }
  }
  showToast('Uploading...');
  let uploaded = 0;
  for (const file of files) {
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('caption', file.name.replace(/\.[^/.]+$/, ''));
    formData.append('uploader', 'Classmate');
    try {
      await fetch(API + '?action=upload_photo', { method: 'POST', body: formData });
      uploaded++;
    } catch(e) { console.error('upload error:', e); }
  }
  e.target.value = '';
  loadPhotos();
  showToast(uploaded + ' photo' + (uploaded > 1 ? 's' : '') + ' uploaded!');
}

/* -- INIT -- */
loadEvents();
loadRsvps();
loadUpdates();
loadPhotos();
