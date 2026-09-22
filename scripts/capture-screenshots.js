import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const outDir = path.join(root, 'public', 'client-preview', 'screenshots');
const manifestPath = path.join(root, 'public', 'client-preview', 'screenshots-manifest.json');
const appBase = process.env.CLIENT_PREVIEW_BASE_URL || 'http://127.0.0.1:8003';
const adminEmail = process.env.CLIENT_PREVIEW_ADMIN_EMAIL || 'admin@example.com';
const adminPassword = process.env.CLIENT_PREVIEW_ADMIN_PASSWORD || 'password';
const chromePort = Number(process.env.CLIENT_PREVIEW_CHROME_PORT || 9333);

const desktop = { name: 'desktop', width: 1440, height: 1000, mobile: false };
const tablet = { name: 'tablet', width: 768, height: 1024, mobile: true };
const mobile = { name: 'mobile', width: 390, height: 844, mobile: true };

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function run(command, args, options = {}) {
  console.log(`> ${command} ${args.join(' ')}`);
  const result = spawnSync(command, args, {
    cwd: root,
    shell: process.platform === 'win32',
    stdio: 'inherit',
    ...options,
  });
  if (result.status !== 0) {
    throw new Error(`${command} ${args.join(' ')} failed`);
  }
}

function httpJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let body = '';
      res.setEncoding('utf8');
      res.on('data', (chunk) => (body += chunk));
      res.on('end', () => {
        try {
          resolve(JSON.parse(body));
        } catch (error) {
          reject(error);
        }
      });
    }).on('error', reject);
  });
}

function httpOk(url) {
  return new Promise((resolve) => {
    const req = http.get(url, (res) => {
      res.resume();
      resolve(res.statusCode >= 200 && res.statusCode < 500);
    });
    req.setTimeout(2500, () => {
      req.destroy();
      resolve(false);
    });
    req.on('error', () => resolve(false));
  });
}

async function waitFor(url, timeoutMs = 20000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    if (await httpOk(url)) return true;
    await sleep(500);
  }
  return false;
}

async function ensureLaravelServer() {
  if (await httpOk(`${appBase}/login`)) {
    console.log(`Laravel is already responding at ${appBase}`);
    return null;
  }

  console.log(`Starting Laravel at ${appBase}`);
  const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8003'], {
    cwd: root,
    shell: process.platform === 'win32',
    stdio: 'ignore',
    windowsHide: true,
  });

  if (!await waitFor(`${appBase}/login`)) {
    server.kill();
    throw new Error(`Laravel did not start at ${appBase}`);
  }

  return server;
}

function findChrome() {
  const candidates = [
    process.env.CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium-browser',
    '/usr/bin/chromium',
  ].filter(Boolean);

  const found = candidates.find((candidate) => fs.existsSync(candidate));
  if (!found) {
    throw new Error('Chrome or Edge was not found. Set CHROME_PATH to your browser executable.');
  }
  return found;
}

async function startChrome() {
  if (typeof WebSocket === 'undefined') {
    throw new Error('This script needs Node.js with global WebSocket support. Use Node 22+.');
  }

  const chromePath = findChrome();
  const profileDir = path.join(root, 'storage', 'app', 'client-preview', `chrome-profile-${process.pid}`);
  ensureDir(profileDir);

  const chrome = spawn(chromePath, [
    `--remote-debugging-port=${chromePort}`,
    '--remote-debugging-address=127.0.0.1',
    `--user-data-dir=${profileDir}`,
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--no-first-run',
    '--no-default-browser-check',
    '--hide-scrollbars',
    'about:blank',
  ], {
    cwd: root,
    stdio: 'ignore',
    windowsHide: true,
  });

  const ok = await waitFor(`http://127.0.0.1:${chromePort}/json/version`, 20000);
  if (!ok) {
    chrome.kill();
    throw new Error('Chrome DevTools endpoint did not become available.');
  }

  const targets = await httpJson(`http://127.0.0.1:${chromePort}/json/list`);
  const pageTarget = targets.find((target) => target.type === 'page' && target.webSocketDebuggerUrl);
  if (!pageTarget) {
    chrome.kill();
    throw new Error('Chrome page target was not available.');
  }

  return { chrome, webSocketDebuggerUrl: pageTarget.webSocketDebuggerUrl };
}

class Cdp {
  constructor(wsUrl) {
    this.ws = new WebSocket(wsUrl);
    this.id = 1;
    this.pending = new Map();
    this.events = [];
  }

  async open() {
    await new Promise((resolve, reject) => {
      this.ws.addEventListener('open', resolve, { once: true });
      this.ws.addEventListener('error', reject, { once: true });
    });
    this.ws.addEventListener('message', (event) => {
      const message = JSON.parse(event.data);
      if (message.id && this.pending.has(message.id)) {
        const { resolve, reject } = this.pending.get(message.id);
        this.pending.delete(message.id);
        message.error ? reject(new Error(message.error.message)) : resolve(message.result || {});
        return;
      }
      if (message.method) this.events.push(message.method);
    });
    await this.send('Page.enable');
    await this.send('Runtime.enable');
    await this.send('Network.enable');
  }

  send(method, params = {}) {
    const id = this.id++;
    this.ws.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (this.pending.has(id)) {
          this.pending.delete(id);
          reject(new Error(`${method} timed out`));
        }
      }, 30000);
    });
  }

  async setViewport(viewport) {
    await this.send('Emulation.setDeviceMetricsOverride', {
      width: viewport.width,
      height: viewport.height,
      deviceScaleFactor: 1,
      mobile: viewport.mobile,
    });
    await this.send('Emulation.setUserAgentOverride', {
      userAgent: viewport.mobile
        ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
        : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
    });
  }

  async navigate(url, waitMs = 1500) {
    this.events = [];
    await this.send('Page.navigate', { url });
    const started = Date.now();
    while (Date.now() - started < 12000) {
      if (this.events.includes('Page.loadEventFired')) break;
      await sleep(100);
    }
    await sleep(waitMs);
  }

  async eval(expression) {
    const result = await this.send('Runtime.evaluate', {
      expression,
      awaitPromise: true,
      returnByValue: true,
    });
    return result.result?.value;
  }

  async clickSelector(selector, waitMs = 700) {
    await this.eval(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      if (el) { el.scrollIntoView({ block: 'center', inline: 'center' }); el.click(); return true; }
      return false;
    })()`);
    await sleep(waitMs);
  }

  async screenshot(filePath) {
    await this.eval(`(() => {
      document.documentElement.style.scrollBehavior = 'auto';
      window.scrollTo(0, 0);
      document.body.style.caretColor = 'transparent';
      document.querySelectorAll('video').forEach(v => v.pause());
    })()`);
    await sleep(350);
    const metrics = await this.send('Page.getLayoutMetrics');
    const width = Math.ceil(metrics.contentSize.width);
    const height = Math.min(Math.ceil(metrics.contentSize.height), 18000);
    const shot = await this.send('Page.captureScreenshot', {
      format: 'png',
      fromSurface: true,
      captureBeyondViewport: true,
      clip: { x: 0, y: 0, width, height, scale: 1 },
    });
    fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
  }

  close() {
    this.ws.close();
  }
}

function publicPathFor(fileName) {
  return path.join(outDir, fileName);
}

function slugify(value) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

async function discoverLinks(cdp) {
  await cdp.navigate(`${appBase}/admin/bookings`);
  const bookingUrl = await cdp.eval(`(() => {
    const links = Array.from(document.querySelectorAll('a[href*="/admin/bookings/"]')).map(a => a.href);
    return links.find(h => !h.endsWith('/create') && !h.includes('/edit') && !h.includes('/preview/')) || null;
  })()`);
  const bookingId = bookingUrl?.match(/\/admin\/bookings\/(\d+)/)?.[1] || '1';

  await cdp.navigate(`${appBase}/admin/properties`);
  const propertyEditUrl = await cdp.eval(`Array.from(document.querySelectorAll('a[href*="/admin/properties/"][href$="/edit"]')).map(a => a.href)[0] || '${appBase}/admin/properties/1/edit'`);

  await cdp.navigate(`${appBase}/admin/users`);
  const userUrl = await cdp.eval(`Array.from(document.querySelectorAll('a[href*="/admin/users/"]')).map(a => a.href).find(h => !h.endsWith('/create') && !h.includes('/edit') && !h.includes('toggle-status')) || '${appBase}/admin/users/1'`);

  await cdp.navigate(`${appBase}/admin/logs`);
  const logUrl = await cdp.eval(`Array.from(document.querySelectorAll('a[href*="/admin/logs/"]')).map(a => a.href).find(h => !h.includes('?')) || '${appBase}/admin/logs'`);

  await cdp.navigate(`${appBase}/admin/bookings/${bookingId}`);
  const guestUrl = await cdp.eval(`document.querySelector('#guest-url')?.value || document.querySelector('[data-copy="#guest-url"]')?.closest('div')?.querySelector('input')?.value || null`);
  const categoryUrls = await cdp.eval(`(() => {
    const base = ${JSON.stringify(guestUrl || `${appBase}/guest/LUMINA-DEMO/lumina-demo-secure-token`)};
    const slugs = ['wifi','amenities','restaurants','bars','parking','checkout-instructions','contact-guest-services'];
    return Object.fromEntries(slugs.map(slug => [slug, base.replace(/\\/$/, '') + '/guide/' + slug]));
  })()`);

  return {
    bookingId,
    bookingUrl: `${appBase}/admin/bookings/${bookingId}`,
    propertyEditUrl,
    userUrl,
    logUrl,
    guestUrl: guestUrl || `${appBase}/guest/LUMINA-DEMO/lumina-demo-secure-token`,
    categoryUrls,
  };
}

async function login(cdp) {
  await cdp.setViewport(desktop);
  await cdp.navigate(`${appBase}/login`);
  await cdp.eval(`(() => {
    const email = document.querySelector('input[name="email"]');
    const password = document.querySelector('input[name="password"]');
    if (email) email.value = ${JSON.stringify(adminEmail)};
    if (password) password.value = ${JSON.stringify(adminPassword)};
    document.querySelector('form')?.submit();
  })()`);
  await sleep(2200);
}

async function restartTour(cdp) {
  await cdp.navigate(`${appBase}/admin/guide`);
  await cdp.eval(`(async () => {
    const form = Array.from(document.querySelectorAll('form')).find(f => f.action.includes('/admin/tour/restart'));
    const token = form?.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return false;
    await fetch('${appBase}/admin/tour/restart', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
      body: new URLSearchParams({ _token: token }).toString(),
      credentials: 'same-origin'
    });
    return true;
  })()`);
  await sleep(600);
}

async function completeTour(cdp) {
  await cdp.eval(`(async () => {
    const tour = document.querySelector('#admin-onboarding-tour');
    const token = tour?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
    const url = tour?.dataset.completeUrl || '${appBase}/admin/tour/complete';
    if (token) {
      await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, credentials: 'same-origin' });
    }
    document.querySelectorAll('[id*="tour"], .fixed.inset-0').forEach(el => {
      if (el.textContent?.includes('Welcome to your command centre')) el.remove();
    });
    return true;
  })()`);
  await sleep(500);
}

async function overrideCheckin(cdp, bookingUrl) {
  await cdp.navigate(bookingUrl);
  await cdp.eval(`(async () => {
    const form = Array.from(document.querySelectorAll('form')).find(f => f.action.includes('override-checkin'));
    const token = form?.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
    if (!form || !token) return false;
    await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new FormData(form), credentials: 'same-origin' });
    return true;
  })()`);
  await sleep(800);
}

function makeCapture(manifest) {
  return async function capture(cdp, entry) {
    const viewport = entry.viewport || desktop;
    const fileName = `${String(manifest.length + 1).padStart(2, '0')}-${slugify(entry.title)}-${viewport.name}.png`;
    const filePath = publicPathFor(fileName);

    try {
      await cdp.setViewport(viewport);
      await cdp.navigate(entry.url, entry.waitMs || 1500);
      if (entry.action) await entry.action(cdp);
      await cdp.screenshot(filePath);
      manifest.push({
        section: entry.section,
        title: entry.title,
        caption: entry.caption,
        file: `screenshots/${fileName}`,
        viewport: viewport.name,
      });
      console.log(`Captured ${fileName}`);
    } catch (error) {
      console.warn(`Skipped "${entry.title}": ${error.message}`);
    }
  };
}

async function main() {
  ensureDir(outDir);
  ensureDir(path.dirname(manifestPath));

  run('php', ['artisan', 'migrate', '--seed', '--force']);

  const server = await ensureLaravelServer();
  const { chrome, webSocketDebuggerUrl } = await startChrome();
  const cdp = new Cdp(webSocketDebuggerUrl);
  const manifest = [];
  const capture = makeCapture(manifest);

  try {
    await cdp.open();

    await capture(cdp, {
      section: 'Admin Screens',
      title: 'Admin Login Page',
      caption: 'Branded admin login with clean authentication flow.',
      url: `${appBase}/login`,
      viewport: desktop,
    });

    await login(cdp);
    await restartTour(cdp);

    await capture(cdp, {
      section: 'Admin Guide & Tour',
      title: 'Interactive Tour Guide Highlight',
      caption: 'First-time onboarding tour introduces the admin command center and navigation.',
      url: `${appBase}/admin`,
      viewport: desktop,
      waitMs: 2200,
    });
    await completeTour(cdp);

    const links = await discoverLinks(cdp);
    const preview = (state) => `${links.bookingUrl}/preview/${state}`;

    const adminScreens = [
      ['Admin Dashboard Command Center', `${appBase}/admin`, 'Stats, onboarding checklist, needs attention, recent guests, and quick actions.'],
      ['Dashboard Onboarding Checklist', `${appBase}/admin`, 'Setup checklist keeps first-time owners focused on launch-critical steps.'],
      ['Dashboard Recent Activity Section', `${appBase}/admin`, 'Recent system activity gives owners a clear audit trail from the dashboard.'],
      ['Properties List Page', `${appBase}/admin/properties`, 'Professional property list with operational status and management actions.'],
      ['Add Property Page', `${appBase}/admin/properties/create`, 'Grouped property form for address, GPS, branding, and guest instructions.'],
      ['Edit Property Page', links.propertyEditUrl, 'Property editing screen with polished content sections and upload controls.'],
      ['Guests Bookings List Page', `${appBase}/admin/bookings`, 'Guest booking table with filters, statuses, dates, and quick actions.'],
      ['Add Guest Booking Page', `${appBase}/admin/bookings/create`, 'Admin can add bookings and generate secure private guest URLs.'],
      ['Guest Detail Page', links.bookingUrl, 'Complete booking profile with status, actions, guest URL, preview links, and logs.'],
      ['Guest Secure URL Card', links.bookingUrl, 'Secure guest URL and copy controls for sending the check-in link.'],
      ['Guest Progress Timeline', links.bookingUrl, 'Timeline communicates each guest milestone from booking through checkout.'],
      ['Manual GPS Override Section', links.bookingUrl, 'Admin override workflow for guests whose GPS verification needs approval.'],
      ['Copy Guest Message Section', links.bookingUrl, 'Ready-to-send guest message templates reduce admin friction.'],
      ['Categories List Page', `${appBase}/admin/categories`, 'Icon-based guide category management with ordering and activation controls.'],
      ['Add Edit Category Page', `${appBase}/admin/categories/create`, 'Category form with icon selection, text, status, and ordering fields.'],
      ['Amenities Manager Page', `${appBase}/admin/content`, 'Content manager includes guide pages and property-specific amenity items.'],
      ['Settings Page', `${appBase}/admin/settings`, 'Brand, contact, GPS radius, and default guest copy in one polished settings area.'],
      ['Users Team Management List Page', `${appBase}/admin/users`, 'Owner-only team management with roles, statuses, and invited admins.'],
      ['Create User Page', `${appBase}/admin/users/create`, 'User creation form for role-based admin access.'],
      ['User Detail Page', links.userUrl, 'Team member profile with role, status, permissions, and activity context.'],
      ['Roles Permission Overview Section', `${appBase}/admin/guide`, 'Role explanations help owners understand access levels.'],
      ['Activity Logs List Page', `${appBase}/admin/logs`, 'Audit log table for guest events, admin actions, and security records.'],
      ['Activity Log Detail Page', links.logUrl, 'Single audit event view with actor, subject, metadata, and request context.'],
      ['Admin Guide Page', `${appBase}/admin/guide`, 'Built-in help center with quick start, flow explanation, troubleshooting, and security notes.'],
      ['Profile Security Page', `${appBase}/admin/security`, 'Security guidance, profile card, password/2FA readiness, and production notes.'],
      ['2FA Setup Page', `${appBase}/admin/security`, 'Security screen prepared for two-factor authentication workflow.'],
      ['Notification Bell Admin Notifications', `${appBase}/admin`, 'Admin topbar includes operational notifications and pending work indicators.'],
      ['Setup System Health Checklist', `${appBase}/admin/guide`, 'Go-live checklist helps confirm the client is ready for production use.'],
    ];

    for (const [title, url, caption] of adminScreens) {
      await capture(cdp, { section: 'Admin Screens', title, caption, url, viewport: desktop });
    }

    const guestScreens = [
      ['Guest Pre Check-In Page', preview('identity'), 'Premium welcome screen with property branding, guest greeting, and check-in steps.', null],
      ['Guest Email Photo ID Upload Section', preview('identity'), 'The document upload section explains security and accepted file types clearly.', (page) => page.clickSelector('[data-dot="3"]')],
      ['Guest Waiting Confirmation Page', preview('waiting'), 'Confirmation state explains that check-in details unlock on arrival day.', null],
      ['Guest Check-In Day Arrival Page', preview('arrival'), 'Arrival page shows address, map, parking prompt, and GPS verification.', null],
      ['GPS Verification Section', preview('arrival'), 'Location verification CTA gives guests a clear next step at the property.', (page) => page.clickSelector('[data-dot="2"]')],
      ['Parking Question Section', preview('arrival'), 'Parking question is shown cleanly when the booking does not yet have a parking response.', null],
      ['Checked In Success Page', preview('guide'), 'After check-in, guests see instructions and can open the welcome guide.', null],
      ['Welcome Guide Dashboard', preview('guide'), 'Icon-led guide dashboard with highlighted WiFi, parking, checkout, and contact categories.', (page) => page.clickSelector('[data-dot="2"]')],
      ['Checkout Day Page', preview('checkout'), 'Checkout day prioritizes departure instructions while preserving guide access.', null],
    ];

    for (const [title, url, caption, action] of guestScreens) {
      await capture(cdp, { section: 'Guest Experience', title, caption, url, viewport: desktop, action });
    }

    await overrideCheckin(cdp, links.bookingUrl);

    const categoryScreens = [
      ['WiFi Category Detail Page', links.categoryUrls.wifi, 'WiFi guide page with readable rich content and quick category navigation.'],
      ['Amenities Category Detail Page', links.categoryUrls.amenities, 'Amenities content and related cards are laid out for easy scanning.'],
      ['Restaurants Category Detail Page', links.categoryUrls.restaurants, 'Local restaurant recommendations are presented in a premium content page.'],
      ['Bars Category Detail Page', links.categoryUrls.bars, 'Bar recommendations follow the same polished guide detail template.'],
      ['Parking Details Page', links.categoryUrls.parking, 'Parking instructions stay accessible after check-in.'],
      ['Checkout Instructions Page', links.categoryUrls['checkout-instructions'], 'Checkout category page gives guests a focused departure reference.'],
      ['Contact Guest Services Page', links.categoryUrls['contact-guest-services'], 'Guest services category provides clear contact pathways.'],
      ['Invalid or Expired Guest Link Page', `${appBase}/guest/INVALID/no-token`, 'Branded error handling for invalid guest links instead of a raw framework screen.'],
    ];

    for (const [title, url, caption] of categoryScreens) {
      await capture(cdp, { section: 'Guest Experience', title, caption, url, viewport: desktop });
    }

    const responsiveScreens = [
      ['Guest Mobile Pre Check-In', preview('identity'), 'Mobile pre-check-in keeps cards compact and touch-friendly.', mobile, null],
      ['Guest Mobile Welcome Guide', preview('guide'), 'Mobile guide dashboard uses a clean stacked icon-card layout.', mobile, (page) => page.clickSelector('[data-dot="2"]')],
      ['Guest Mobile Category Detail', links.categoryUrls.wifi, 'Category detail remains readable on phone-size screens.', mobile, null],
      ['Admin Mobile Dashboard', `${appBase}/admin`, 'Admin dashboard stacks into a usable mobile command center.', mobile, null],
      ['Admin Mobile Guest Detail', links.bookingUrl, 'Booking detail page remains usable on mobile for urgent operational work.', mobile, null],
      ['Admin Tablet Dashboard', `${appBase}/admin`, 'Tablet layout balances dashboard density and readability.', tablet, null],
    ];

    for (const [title, url, caption, viewport, action] of responsiveScreens) {
      await capture(cdp, { section: 'Responsive Design', title, caption, url, viewport, action });
    }

    fs.writeFileSync(manifestPath, JSON.stringify({
      generatedAt: new Date().toISOString(),
      appBase,
      screenshots: manifest,
    }, null, 2));

    console.log(`\nSaved ${manifest.length} screenshots`);
    console.log(`Manifest: ${manifestPath}`);
  } finally {
    cdp.close();
    chrome.kill();
    if (server) server.kill();
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
