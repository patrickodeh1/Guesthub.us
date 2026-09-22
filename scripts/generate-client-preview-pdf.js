import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const previewDir = path.join(root, 'public', 'client-preview');
const manifestPath = path.join(previewDir, 'screenshots-manifest.json');
const htmlPath = path.join(previewDir, 'index.html');
const pdfPath = path.join(previewDir, 'client-preview-laravel-guest-portal.pdf');

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

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function todayLabel() {
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(new Date());
}

function groupBySection(screenshots) {
  const order = [
    'Guest Experience',
    'Admin Screens',
    'Admin Guide & Tour',
    'Responsive Design',
  ];
  const groups = new Map();
  for (const section of order) groups.set(section, []);
  for (const shot of screenshots) {
    if (!groups.has(shot.section)) groups.set(shot.section, []);
    groups.get(shot.section).push(shot);
  }
  return Array.from(groups.entries()).filter(([, shots]) => shots.length);
}

function renderHtml(manifest) {
  const sections = groupBySection(manifest.screenshots);
  const total = manifest.screenshots.length;

  const sectionIntro = {
    'Guest Experience': 'A complete branded guest journey: secure pre-arrival check-in, arrival verification, welcome guide, category pages, checkout, and responsive mobile views.',
    'Admin Screens': 'The management workspace for owners and staff: command center, bookings, properties, content, users, settings, security, and audit logs.',
    'Admin Guide & Tour': 'Built-in guidance for non-technical operators, including the interactive onboarding tour and internal documentation.',
    'Responsive Design': 'Phone, tablet, and desktop views showing the product remains polished and usable across real client devices.',
  };

  const toc = sections.map(([section], index) => `
    <li>
      <span>${index + 1}</span>
      <strong>${escapeHtml(section)}</strong>
    </li>
  `).join('');

  const body = sections.map(([section, shots], sectionIndex) => `
    <section class="section-divider">
      <div class="section-kicker">Section ${sectionIndex + 1}</div>
      <h2>${escapeHtml(section)}</h2>
      <p>${escapeHtml(sectionIntro[section] || 'Screens captured from the working Laravel application.')}</p>
    </section>
    ${shots.map((shot, shotIndex) => `
      <article class="screen-card">
        <div class="screen-meta">
          <div>
            <span>${escapeHtml(shot.viewport)} view</span>
            <h3>${escapeHtml(shot.title)}</h3>
          </div>
          <b>${sectionIndex + 1}.${shotIndex + 1}</b>
        </div>
        <img src="${escapeHtml(shot.file)}" alt="${escapeHtml(shot.title)}">
        <p class="caption">${escapeHtml(shot.caption || shot.title)}</p>
      </article>
    `).join('')}
  `).join('');

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guest Check-In & Welcome Guide Platform</title>
  <style>
    @page { size: A4; margin: 13mm; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: #f7f5ef;
      color: #172033;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      line-height: 1.45;
    }
    .cover {
      min-height: 267mm;
      padding: 24mm 16mm;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background:
        radial-gradient(circle at top right, rgba(176, 138, 69, .18), transparent 34%),
        linear-gradient(135deg, #102338 0%, #172033 58%, #26354a 100%);
      color: white;
      page-break-after: always;
    }
    .brand-mark {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: #f3dfb5;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: .16em;
      text-transform: uppercase;
    }
    .brand-mark:before {
      content: "";
      width: 38px;
      height: 38px;
      border-radius: 14px;
      background: #f3dfb5;
      box-shadow: 0 14px 40px rgba(0,0,0,.28);
    }
    .cover h1 {
      max-width: 780px;
      margin: 34mm 0 0;
      font-size: 48px;
      line-height: 1.03;
      letter-spacing: -.03em;
    }
    .cover h1 span { color: #f3dfb5; }
    .cover p {
      max-width: 660px;
      color: rgba(255,255,255,.78);
      font-size: 17px;
    }
    .cover-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-top: 26mm;
    }
    .cover-card {
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 18px;
      padding: 16px;
      background: rgba(255,255,255,.08);
    }
    .cover-card b { display: block; color: #f3dfb5; font-size: 24px; }
    .cover-card span { display: block; margin-top: 4px; color: rgba(255,255,255,.72); font-size: 12px; }
    .cover-footer {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      color: rgba(255,255,255,.7);
      font-size: 12px;
    }
    .toc {
      min-height: 267mm;
      padding: 10mm 2mm;
      page-break-after: always;
    }
    .toc h2, .section-divider h2 {
      margin: 0;
      color: #102338;
      font-size: 34px;
      letter-spacing: -.03em;
    }
    .toc p, .section-divider p {
      max-width: 720px;
      color: #596575;
      font-size: 14px;
    }
    .toc ol {
      margin: 22px 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 12px;
    }
    .toc li {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 15px 18px;
      border: 1px solid #e2dacd;
      border-radius: 18px;
      background: #fff;
      box-shadow: 0 14px 28px rgba(16, 35, 56, .06);
    }
    .toc li span {
      display: grid;
      width: 34px;
      height: 34px;
      place-items: center;
      border-radius: 12px;
      background: #102338;
      color: #f3dfb5;
      font-weight: 800;
    }
    .section-divider {
      min-height: 92mm;
      padding: 18mm 8mm;
      margin: 0 0 8mm;
      border-radius: 28px;
      background:
        linear-gradient(135deg, rgba(16,35,56,.96), rgba(30,46,64,.92)),
        #102338;
      color: white;
      page-break-before: always;
      page-break-after: always;
    }
    .section-divider h2 { color: white; }
    .section-divider p { color: rgba(255,255,255,.76); font-size: 16px; }
    .section-kicker {
      display: inline-block;
      margin-bottom: 16px;
      color: #f3dfb5;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .18em;
      text-transform: uppercase;
    }
    .screen-card {
      margin: 0 0 11mm;
      padding: 14px;
      border: 1px solid #e0d9cf;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 18px 36px rgba(16, 35, 56, .08);
      page-break-inside: avoid;
    }
    .screen-meta {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 18px;
      margin-bottom: 12px;
    }
    .screen-meta span {
      color: #b08a45;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .14em;
      text-transform: uppercase;
    }
    .screen-meta h3 {
      margin: 2px 0 0;
      color: #102338;
      font-size: 17px;
      letter-spacing: -.015em;
    }
    .screen-meta b {
      color: #94a3b8;
      font-size: 12px;
    }
    .screen-card img {
      display: block;
      width: 100%;
      max-height: 188mm;
      object-fit: contain;
      object-position: top center;
      border: 1px solid #e7e2d8;
      border-radius: 14px;
      background: #f8fafc;
    }
    .caption {
      margin: 10px 2px 0;
      color: #596575;
      font-size: 12px;
    }
    .page-note {
      position: fixed;
      bottom: 4mm;
      left: 13mm;
      right: 13mm;
      display: flex;
      justify-content: space-between;
      color: #8a94a3;
      font-size: 9px;
    }
    @media screen {
      body { padding: 24px; }
      .cover, .toc { min-height: auto; border-radius: 28px; margin-bottom: 24px; }
      .section-divider { page-break-before: auto; page-break-after: auto; }
      .screen-card { max-width: 1120px; margin-left: auto; margin-right: auto; }
      .page-note { display: none; }
    }
  </style>
</head>
<body>
  <section class="cover">
    <div>
      <div class="brand-mark">Client Preview</div>
      <h1>Guest Check-In &amp; <span>Welcome Guide</span> Platform</h1>
      <p>Laravel + MySQL production-ready web application for premium short-term rental and hotel-style guest arrival, content management, operations, security, and audit workflows.</p>
      <div class="cover-grid">
        <div class="cover-card"><b>${total}</b><span>Captured product screens</span></div>
        <div class="cover-card"><b>3</b><span>Responsive device sizes</span></div>
        <div class="cover-card"><b>Full</b><span>Guest + admin experience</span></div>
      </div>
    </div>
    <div class="cover-footer">
      <span>Generated ${escapeHtml(todayLabel())}</span>
      <span>Laravel Guest Portal Presentation</span>
    </div>
  </section>

  <section class="toc">
    <h2>Table of Contents</h2>
    <p>This preview follows the product from guest-facing arrival through the admin operations suite, then closes with responsive device examples.</p>
    <ol>${toc}</ol>
  </section>

  ${body}

  <div class="page-note">
    <span>Guest Check-In & Welcome Guide Platform</span>
    <span>Client Presentation</span>
  </div>
</body>
</html>`;
}

function main() {
  if (!fs.existsSync(manifestPath)) {
    throw new Error(`Missing screenshot manifest: ${manifestPath}. Run node scripts/capture-screenshots.js first.`);
  }

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const html = renderHtml(manifest);
  fs.writeFileSync(htmlPath, html);

  const chrome = findChrome();
  const result = spawnSync(chrome, [
    '--headless=new',
    '--disable-gpu',
    '--disable-software-rasterizer',
    '--disable-dev-shm-usage',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    `--print-to-pdf=${pdfPath}`,
    '--print-to-pdf-no-header',
    pathToFileURL(htmlPath).href,
  ], {
    cwd: root,
    stdio: 'inherit',
    windowsHide: true,
  });

  if (result.status !== 0) {
    throw new Error('PDF generation failed.');
  }

  const stats = fs.statSync(pdfPath);
  if (stats.size < 50000) {
    throw new Error(`Generated PDF looks too small (${stats.size} bytes).`);
  }

  console.log(`HTML preview: ${htmlPath}`);
  console.log(`PDF: ${pdfPath}`);
  console.log(`PDF size: ${(stats.size / 1024 / 1024).toFixed(2)} MB`);
}

try {
  main();
} catch (error) {
  console.error(error);
  process.exit(1);
}
