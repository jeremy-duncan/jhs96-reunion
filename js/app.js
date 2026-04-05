const API = '/php/api.php';

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
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

/* -- EVENTS -- */
let editingEventId = null;

async function loadEvents() {
  const res = await fetch(API + '?action=get_events');
  const events = await res.json();
  const list = document.getElementById('events-list');
  if (!list) return;
  list.innerHTML = events.map(ev => `
    <div class="event-card" id="event-${ev.id}">
      <div class="event-datebox">
        <div class="ev-month">${ev.month}</div>
        <div class="ev-day">${ev.day}</div>
        <div class="ev-year">${ev.year}</div>
      </div>
      <div class="event-body">
        <h3>${ev.name}</h3>
        <div class="event-meta">${ev.timeloc}</div>
        <p>${ev.description}</p>
      </div>
      <div class="event-actions">
        <button class="btn-edit" onclick='openEditEvent(${JSON.stringify(ev)})'>Edit</button>
        <button class="btn-delete" onclick="deleteEvent(${ev.id})">Remove</button>
      </div>
    </div>`).join('');
}

function openAddEvent() {
  editingEventId = null;
  document.getElementById('event-modal-title').textContent = 'Add New Event';
  ['ev-name','ev-month','ev-day','ev-year','ev-timeloc','ev-desc'].forEach(id => {
    document.getElementById(id).value = '';
  });
  openModal('event-modal');
}

function openEditEvent(ev) {
  editingEventId = ev.id;
  document.getElementById('event-modal-title').textContent = 'Edit Event';
  document.getElementById('ev-name').value = ev.name;
  document.getElementById('ev-month').value = ev.month;
  document.getElementById('ev-day').value = ev.day;
  document.getElementById('ev-year').value = ev.year;
  document.getElementById('ev-timeloc').value = ev.timeloc;
  document.getElementById('ev-desc').value = ev.description;
  openModal('event-modal');
}

async function saveEvent() {
  const data = {
    id: editingEventId,
    name: document.getElementById('ev-name').value.trim(),
    month: document.getElementById('ev-month').value.trim(),
    day: parseInt(document.getElementById('ev-day').value),
    year: parseInt(document.getElementById('ev-year').value) || 2026,
    timeloc: document.getElementById('ev-timeloc').value.trim(),
    desc: document.getElementById('ev-desc').value.trim()
  };
  if (!data.name || !data.month || !data.day) { alert('Please fill in name, month, and day.'); return; }
  await fetch(API + '?action=save_event', { method: 'POST', body: JSON.stringify(data) });
  closeModal('event-modal');
  loadEvents();
  showToast(editingEventId ? 'Event updated!' : 'Event added!');
}

async function deleteEvent(id) {
  if (!confirm('Remove this event?')) return;
  await fetch(API + '?action=delete_event', { method: 'POST', body: JSON.stringify({ id }) });
  loadEvents();
  showToast('Event removed.');
}

/* -- RSVP -- */
async function loadRsvps() {
  const res = await fetch(API + '?action=get_rsvps');
  const d = await res.json();
  document.getElementById('rsvp-count-yes').textContent = d.yes;
  document.getElementById('rsvp-count-maybe').textContent = d.maybe;
  document.getElementById('rsvp-count-guests').textContent = d.guests;
  const list = document.getElementById('rsvp-list');
  if (!list) return;
  list.innerHTML = d.rsvps.map(r => `
    <div class="rsvp-item">
      <div class="rsvp-avatar">${initials(r.name)}</div>
      <div class="rsvp-info">
        <strong>${r.name}</strong>
        <span>${r.location || ''}${r.guests > 1 ? ' - ' + r.guests + ' guests' : ''}</span>
      </div>
      <div class="rsvp-badge ${r.attending === 'maybe' ? 'maybe' : ''}">${r.attending === 'yes' ? 'Attending' : 'Maybe'}</div>
    </div>`).join('');
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
}

/* -- CLASS UPDATES -- */
async function loadUpdates() {
  const res = await fetch(API + '?action=get_updates');
  const rows = await res.json();
  const feed = document.getElementById('updates-feed');
  if (!feed) return;
  feed.innerHTML = rows.map(u => `
    <div class="update-card">
      <div class="update-header">
        <div class="update-avatar">${initials(u.name)}</div>
        <div class="update-meta">
          <strong>${u.name}</strong>
          <span>${u.location || ''} - ${new Date(u.created_at).toLocaleDateString('en-US', {month:'long', year:'numeric'})}</span>
        </div>
      </div>
      <p>${u.body}</p>
    </div>`).join('');
}

async function submitUpdate() {
  const name = document.getElementById('upd-name').value.trim();
  const body = document.getElementById('upd-text').value.trim();
  if (!name || !body) { showToast('Please enter your name and update.'); return; }
  const data = { name, location: document.getElementById('upd-location').value.trim(), body };
  const res = await fetch(API + '?action=submit_update', { method: 'POST', body: JSON.stringify(data) });
  const result = await res.json();
  if (result.ok) {
    ['upd-name','upd-location','upd-text'].forEach(id => document.getElementById(id).value = '');
    loadUpdates();
    showToast('Update posted ' + name.split(' ')[0] + '!');
  }
}

/* -- PHOTOS -- */
async function loadPhotos() {
  const res = await fetch(API + '?action=get_photos');
  const photos = await res.json();
  const grid = document.getElementById('photo-grid');
  if (!grid) return;
  const defaults = [
    { url: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=600&q=75', caption: "Red Campus - Class of 96", uploader: 'Admin' },
    { url: 'https://images.unsplash.com/photo-1566577739112-5180d4bf9390?w=600&q=75', caption: 'Rockets Football 1995', uploader: 'Admin' },
    { url: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=75', caption: 'Senior Week 1996', uploader: 'Admin' },
    { url: 'https://images.unsplash.com/photo-1540479859555-17af45c78602?w=600&q=75', caption: "Prom Night 96", uploader: 'Admin' }
  ];
  const all = photos.length > 0 ? photos : defaults;
  grid.innerHTML = all.map(p => `
    <div class="photo-card">
      <img src="${p.url}" alt="${p.caption || ''}" loading="lazy">
      <div class="photo-caption">
        <strong>${p.caption || 'Photo'}</strong>
        <span>Shared by ${p.uploader || 'Classmate'}</span>
      </div>
    </div>`).join('');
}

async function handleUpload(e) {
  const files = Array.from(e.target.files);
  if (!files.length) return;
  let uploaded = 0;
  for (const file of files) {
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('caption', file.name.replace(/\.[^/.]+$/, ''));
    formData.append('uploader', 'You');
    await fetch(API + '?action=upload_photo', { method: 'POST', body: formData });
    uploaded++;
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
