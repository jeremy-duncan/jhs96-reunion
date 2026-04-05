# Judson High School — Class of 1996 — 30th Reunion Website

## How to Use This Site

### Opening the Site
Just double-click `index.html` to open it in your browser. No server or internet connection required (except for loading Google Fonts and placeholder images on first open).

### Editing Events
1. Click the **Events** tab in the nav
2. Click **✏ Edit** on any event card to change the name, date, time, location, or description
3. Click **+ Add Event** to create a new event
4. Click **✕ Remove** to delete an event

### RSVP
- Classmates fill out the form and click **Submit RSVP**
- Their name appears in the RSVP list below with a count of total attendees
- Note: RSVPs are stored in memory only — they reset when the page is refreshed. See "Hosting" below for a permanent solution.

### Class Updates
- Classmates type their name, location, and a life update, then click **Post Update**
- Updates appear in the feed in reverse chronological order

### Photos
- Click the upload zone (or drag & drop) to add photos from your computer
- Photos appear immediately in the gallery grid
- Note: Uploaded photos are stored in memory only — see "Hosting" below

---

## Hosting This Site (Recommended)

For a real, permanent site that classmates can visit:

### Option 1: GitHub Pages (Free)
1. Create a free GitHub account at github.com
2. Create a new repository called `jhs96reunion`
3. Upload all files from this folder
4. Go to Settings → Pages → set source to "main branch"
5. Your site will be live at `https://yourusername.github.io/jhs96reunion`

### Option 2: Netlify (Free, Easiest)
1. Go to netlify.com and create a free account
2. Drag and drop the entire `judson-reunion` folder onto the Netlify dashboard
3. Your site is live instantly with a URL like `jhs96reunion.netlify.app`
4. You can set a custom domain if you want

### Option 3: Custom Domain
- Buy a domain like `judsonrockets96.com` from Namecheap or GoDaddy (~$12/year)
- Point it to your Netlify or GitHub Pages site

---

## Making RSVPs and Updates Permanent

Currently, RSVPs and class updates reset when the page is refreshed because they're stored in JavaScript memory. To make them permanent, you have a few options:

1. **Google Sheets + Google Forms** — Create a Google Form for RSVPs that saves to a Sheet, and link to it from the site
2. **Formspree.io** — Free form backend, just add your email to receive RSVP notifications
3. **Firebase** (free tier) — Add a small database to store RSVPs and updates permanently
4. **Ask Claude** — Claude can add any of these integrations for you!

---

## Customizing

### Changing the Hero Background
In `css/style.css`, find the `.hero` rule and change the image URL:
```css
url('https://YOUR-IMAGE-URL-HERE') center 30%/cover no-repeat
```
If you have an actual photo of the Red Campus, save it as `images/red-campus.jpg` and change the URL to `images/red-campus.jpg`.

### Changing Colors
All colors are in `css/style.css` under `:root`. The main colors are:
- `--crimson` — the main Judson red (#8B1A1A)
- `--gold` — accent gold (#C9973A)
- `--cream` — page background (#FAF7F0)

### Changing the Reunion Date (Countdown)
In `js/app.js`, find this line and update the date:
```javascript
const target = new Date('2026-08-14T19:00:00');
```

### Changing Contact Email
In `index.html`, search for `jhs96reunion@gmail.com` and replace with your actual email.

---

## File Structure
```
judson-reunion/
├── index.html          ← Main website (open this in your browser)
├── css/
│   └── style.css       ← All styling
├── js/
│   └── app.js          ← All interactivity
├── images/             ← Put your photos here (currently empty)
└── README.md           ← This file
```

---

## Need Help?
Ask Claude at claude.ai to make any changes! Just describe what you want and paste in the relevant code.

**Go Rockets! Class of 1996! 🚀**
