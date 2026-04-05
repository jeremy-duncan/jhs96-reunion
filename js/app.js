/* ── NAVIGATION ── */
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  document.getElementById('page-' + id).classList.add('active');
  document.querySelector(`[data-page="${id}"]`).classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
  // close mobile menu
  document.querySelector('.nav-links').classList.remove('open');
}

document.querySelector('.nav-hamburger').addEventListener('click', () => {
  document.querySelector('.nav-links').classList.toggle('open');
});

/* ── COUNTDOWN ── */
function updateCountdown() {
  const target = new Date('2026-08-14T19:00:00');
  const now = new Date();
  const diff = target - now;
  if (diff <= 0) {
    document.getElementById('countdown-strip').innerHTML = '<div id="countdown-message">🎉 Welcome Back, Rockets! The Reunion is Here! 🎉</div>';
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

/* ── EVENTS ── */
let events = [
  { id: 1, name: 'Welcome Reception', month: 'Aug', day: 14, year: 2026, timeloc: '7:00 PM · Judson High School — Original Red Campus Grounds', desc: 'Start the reunion weekend right! Drinks, appetizers, and a video slideshow of our greatest '90s moments. Bring your old yearbook and your best stories.' },
  { id: 2, name: 'Main Reunion Dinner & Dance', month: 'Aug', day: 15, year: 2026, timeloc: '6:30 PM · San Antonio Marriott Northwest, 9821 Colonnade Blvd', desc: 'The big night — dinner, dancing, and the crowning of our 30-year class awards. Business casual attire. Cash bar available. DJ spinning the hits of \'96.' },
  { id: 3, name: 'Sunday Farewell Brunch', month: 'Aug', day: 16, year: 2026, timeloc: '10:30 AM · Bill Miller Bar-B-Q, Converse', desc: 'A casual farewell brunch before everyone heads home. Come as you are, bring the whole family, and let\'s close out the weekend with some good Texas BBQ.' }
];
let editingEventId = null;
let nextEventId = 100;

function renderEvents() {
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
        <div class="event-meta">📍 ${ev.timeloc}</div>
        <p>${ev.desc}</p>
      </div>
      <div class="event-actions">
        <button class="btn-edit" onclick="openEditEvent(${ev.id})">✏ Edit</button>
        <button class="btn-delete" onclick="deleteEvent(${ev.id})">✕ Remove</button>
      </div>
    </div>
  `).join('');
}

function openAddEvent() {
  editingEventId = null;
  document.getElementById('event-modal-title').textContent = 'Add New Event';
  ['ev-name','ev-month','ev-day','ev-year','ev-timeloc','ev-desc'].forEach(id => {
    document.getElementById(id).value = '';
  });
  openModal('event-modal');
}

function openEditEvent(id) {
  const ev = events.find(e => e.id === id);
  editingEventId = id;
  document.getElementById('event-modal-title').textContent = 'Edit Event';
  document.getElementById('ev-name').value = ev.name;
  document.getElementById('ev-month').value = ev.month;
  document.getElementById('ev-day').value = ev.day;
  document.getElementById('ev-year').value = ev.year;
  document.getElementById('ev-timeloc').value = ev.timeloc;
  document.getElementById('ev-desc').value = ev.desc;
  openModal('event-modal');
}

function saveEvent() {
  const name = document.getElementById('ev-name').value.trim();
  const month = document.getElementById('ev-month').value.trim();
  const day = parseInt(document.getElementById('ev-day').value);
  const year = parseInt(document.getElementById('ev-year').value) || 2026;
  const timeloc = document.getElementById('ev-timeloc').value.trim();
  const desc = document.getElementById('ev-desc').value.trim();
  if (!name || !month || !day) { alert('Please fill in the event name, month, and day.'); return; }
  if (editingEventId) {
    const ev = events.find(e => e.id === editingEventId);
    Object.assign(ev, { name, month, day, year, timeloc, desc });
    showToast('Event updated!');
  } else {
    events.push({ id: nextEventId++, name, month, day, year, timeloc, desc });
    showToast('Event added!');
  }
  closeModal('event-modal');
  renderEvents();
}

function deleteEvent(id) {
  if (!confirm('Remove this event?')) return;
  events = events.filter(e => e.id !== id);
  renderEvents();
  showToast('Event removed.');
}

/* ── RSVP ── */
let rsvps = [
  { name: 'Maria Gonzalez', location: 'San Antonio, TX', guests: 2, attending: 'yes' },
  { name: 'Derek Williams', location: 'Austin, TX', guests: 1, attending: 'yes' },
  { name: 'Tanya Ruiz', location: 'Houston, TX', guests: 2, attending: 'maybe' },
];

function initials(name) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function renderRsvps() {
  const list = document.getElementById('rsvp-list');
  if (!list) return;
  const yes = rsvps.filter(r => r.attending === 'yes').length;
  const maybe = rsvps.filter(r => r.attending === 'maybe').length;
  const totalGuests = rsvps.reduce((s, r) => s + (r.attending === 'yes' ? r.guests : 0), 0);
  document.getElementById('rsvp-count-yes').textContent = yes;
  document.getElementById('rsvp-count-maybe').textContent = maybe;
  document.getElementById('rsvp-count-guests').textContent = totalGuests;
  list.innerHTML = rsvps.map(r => `
    <div class="rsvp-item">
      <div class="rsvp-avatar">${initials(r.name)}</div>
      <div class="rsvp-info">
        <strong>${r.name}</strong>
        <span>${r.location} · ${r.guests} guest${r.guests !== 1 ? 's' : ''}</span>
      </div>
      <div class="rsvp-badge ${r.attending === 'maybe' ? 'maybe' : ''}">${r.attending === 'yes' ? '✓ Attending' : '? Maybe'}</div>
    </div>
  `).join('');
}

function submitRsvp() {
  const name = document.getElementById('rsvp-name').value.trim();
  const email = document.getElementById('rsvp-email').value.trim();
  const location = document.getElementById('rsvp-location').value.trim();
  const guests = parseInt(document.getElementById('rsvp-guests').value) || 1;
  const attending = document.getElementById('rsvp-attending').value;
  const note = document.getElementById('rsvp-note').value.trim();
  if (!name || !email) { showToast('Please enter your name and email.'); return; }
  rsvps.unshift({ name, location: location || 'Unknown', guests, attending });
  ['rsvp-name','rsvp-email','rsvp-location','rsvp-note'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('rsvp-guests').value = '1';
  document.getElementById('rsvp-attending').value = 'yes';
  renderRsvps();
  showToast(`Thanks, ${name.split(' ')[0]}! Your RSVP has been saved. 🎉`);
}

/* ── CLASS UPDATES ── */
let updates = [
  { name: 'Sandra Okonkwo', location: 'Dallas, TX', date: 'March 2026', text: 'Can\'t believe it\'s been 30 years! I\'m now a pediatric nurse at Baylor and mom of three. Still listening to TLC and thinking about you all. So excited for the reunion!' },
  { name: 'Marcus Lee', location: 'San Antonio, TX', date: 'February 2026', text: 'Running my own landscaping company here in SA. Married 22 years to my high school sweetheart (yes, from Judson!). See everyone in August!' },
  { name: 'Priya Nair', location: 'Seattle, WA', date: 'January 2026', text: 'Flying in all the way from Seattle for this! Working in tech now. Still have my senior yearbook with everyone\'s signatures. This reunion means everything.' },
];

function renderUpdates() {
  const feed = document.getElementById('updates-feed');
  if (!feed) return;
  feed.innerHTML = updates.map(u => `
    <div class="update-card">
      <div class="update-header">
        <div class="update-avatar">${initials(u.name)}</div>
        <div class="update-meta">
          <strong>${u.name}</strong>
          <span>${u.location} · ${u.date}</span>
        </div>
      </div>
      <p>${u.text}</p>
    </div>
  `).join('');
}

function submitUpdate() {
  const name = document.getElementById('upd-name').value.trim();
  const location = document.getElementById('upd-location').value.trim();
  const text = document.getElementById('upd-text').value.trim();
  if (!name || !text) { showToast('Please enter your name and update.'); return; }
  const now = new Date();
  const dateStr = now.toLocaleString('en-US', { month: 'long', year: 'numeric' });
  updates.unshift({ name, location: location || '', date: dateStr, text });
  ['upd-name','upd-location','upd-text'].forEach(id => document.getElementById(id).value = '');
  renderUpdates();
  showToast(`Update posted, ${name.split(' ')[0]}! 🚀`);
}

/* ── PHOTOS ── */
let photos = [
  { name: 'Red Campus — Class of \'96', uploader: 'Admin', url: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=600&q=75' },
  { name: 'Rockets Football 1995', uploader: 'Admin', url: 'https://images.unsplash.com/photo-1566577739112-5180d4bf9390?w=600&q=75' },
  { name: 'Senior Week 1996', uploader: 'Admin', url: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=75' },
  { name: 'Prom Night \'96', uploader: 'Admin', url: 'https://images.unsplash.com/photo-1540479859555-17af45c78602?w=600&q=75' },
];

function renderPhotos() {
  const grid = document.getElementById('photo-grid');
  if (!grid) return;
  grid.innerHTML = photos.map(p => `
    <div class="photo-card">
      <img src="${p.url}" alt="${p.name}" loading="lazy">
      <div class="photo-caption">
        <strong>${p.name}</strong>
        <span>Shared by ${p.uploader}</span>
      </div>
    </div>
  `).join('');
}

function handleUpload(e) {
  const files = Array.from(e.target.files);
  let loaded = 0;
  files.forEach(file => {
    const reader = new FileReader();
    reader.onload = ev => {
      photos.unshift({ name: file.name.replace(/\.[^/.]+$/, ''), uploader: 'You', url: ev.target.result });
      loaded++;
      if (loaded === files.length) {
        renderPhotos();
        showToast(`${files.length} photo${files.length > 1 ? 's' : ''} uploaded! 📷`);
      }
    };
    reader.readAsDataURL(file);
  });
  e.target.value = '';
}

/* ── MODAL HELPERS ── */
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

/* ── TOAST ── */
let toastTimer;
function showToast(msg) {
  clearTimeout(toastTimer);
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── INIT ── */
renderEvents();
renderRsvps();
renderUpdates();
renderPhotos();
