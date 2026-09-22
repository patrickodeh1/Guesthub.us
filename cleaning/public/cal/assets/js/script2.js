/* ════════════════════════════════════════
   DATA
════════════════════════════════════════ */
const PLAT_LABEL = { airbnb: 'Airbnb', vrbo: 'VRBO', booking: 'Booking.com', other: 'Other' };
const circleWrap = (imgHtml) => `
  <div style="
    width:26px;
    height:26px;
    background:#fff;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 1px 3px rgba(0,0,0,0.25);
  ">
    ${imgHtml}
  </div>
`;

const smallCircleWrap = (imgHtml) => `
  <div style="
    width:22px;
    height:22px;
    background:#fff;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 1px 2px rgba(0,0,0,0.2);
  ">
    ${imgHtml}
  </div>
`;

const PLAT_LOGO = {
  airbnb: circleWrap('<img src="assets/img/black-airbnb.png" style="width:14px;height:14px;object-fit:contain;">'),
  airbnb_small: smallCircleWrap('<img src="assets/img/black-airbnb.png" style="width:14px;height:14px;object-fit:contain;">'),

  vrbo: circleWrap('<img src="assets/img/logo-new.png" style="width:16px;height:16px;object-fit:contain;">'),
  vrbo_small: smallCircleWrap('<img src="assets/img/logo-new.png" style="width:15px;height:15px;object-fit:contain;">'),

  booking: circleWrap('<img src="assets/img/logo-booking.png" style="width:16px;height:16px;object-fit:contain;">'),
  booking_small: smallCircleWrap('<img src="assets/img/logo-booking.png" style="width:15px;height:15px;object-fit:contain;">'),

  other: circleWrap('<i data-lucide="calendar" style="width:14px;height:14px;color:#000;"></i>'),
  other_small: smallCircleWrap('<i data-lucide="calendar" style="width:12px;height:12px;color:#000;"></i>')
};


const PRESET_COLORS = [
  '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
  '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#f43f5e'
];

// Sources: { id, name, platform, color, enabled, url, showAvailable, bookings:[], blockedDates:[] }
let sources = [];
let calendar;
let editingBookingId = null, editingSrcId = null;
let editingSourceId = null;
let contextMenuBooking = null;
let lastRefreshTime = null;
let dragSrcIdx = null;
const tip = document.getElementById('tooltip');
let tipT;

// Available dates display settings
let availableDatesSettings = {
  enabled: false,
  color: '#22c55e',
  opacity: 0.15,
  showAsBlocks: true
};

// Blocked dates settings
let blockedDatesSettings = {
  enabled: true
};

/* ════════════════════════════════════════
   THEME
════════════════════════════════════════ */
function toggleTheme() {
  const h = document.documentElement;
  h.classList.toggle('dark');
  const isDark = h.classList.contains('dark');
  const icon = document.getElementById('themeIcon');
  if (icon) {
    icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
    lucide.createIcons();
  }
}

/* ════════════════════════════════════════
   AVATAR HELPERS
════════════════════════════════════════ */
function initials(name) {
  if (!name || name === 'Guest') return 'G';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

/* ════════════════════════════════════════
   PRESET COLOR DOTS
════════════════════════════════════════ */
function renderPresetColors() {
  const wrap = document.getElementById('preset-colors');
  if (!wrap) return;
  wrap.innerHTML = '';
  const inpColor = document.getElementById('inp-color');
  if (!inpColor) return;
  const cur = inpColor.value;
  PRESET_COLORS.forEach(c => {
    const d = document.createElement('div');
    d.className = `w-6 h-6 rounded-full cursor-pointer border-2 transition-all hover:scale-110 ${c.toLowerCase() === cur.toLowerCase() ? 'border-white scale-110' : 'border-transparent'}`;
    d.style.background = c;
    d.title = c;
    d.onclick = () => {
      inpColor.value = c;
      renderPresetColors();
    };
    wrap.appendChild(d);
  });
}

/* ════════════════════════════════════════
   RANDOM iCal URL GENERATOR
════════════════════════════════════════ */
function getRandomICalUrl() {
  const demoUrls = [
    { name: "US Holidays", url: "https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics", platform: "other" },
    { name: "UK Holidays", url: "https://calendar.google.com/calendar/ical/en.uk%23holiday%40group.v.calendar.google.com/public/basic.ics", platform: "other" },
    { name: "Canadian Holidays", url: "https://calendar.google.com/calendar/ical/en.canadian%23holiday%40group.v.calendar.google.com/public/basic.ics", platform: "other" },
    { name: "Premier League Fixtures", url: "https://www.calendarlabs.com/ical-calendar/ics/47/Premier_League_Fixtures.ics", platform: "other" },
    { name: "NBA Schedule", url: "https://www.calendarlabs.com/ical-calendar/ics/105/NBA_Schedule.ics", platform: "other" },
    { name: "Moon Phases", url: "https://www.calendarlabs.com/ical-calendar/ics/76/Moon_Phases.ics", platform: "other" }
  ];
  return demoUrls[Math.floor(Math.random() * demoUrls.length)];
}

function getMockAirbnbUrl(propertyName = "Beach House") {
  const mockIds = ["12345678", "87654321", "56781234", "43218765", "98765432"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '-');
  return { name: `${propertyName} (Airbnb)`, url: `https://www.airbnb.com/calendar/ical/${randomId}.ics?locale=en&calendar_name=${propertySlug}`, platform: "airbnb" };
}

function getMockVrboUrl(propertyName = "Lake Cabin") {
  const mockIds = ["123456", "654321", "789012", "210987", "345678"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '');
  return { name: `${propertyName} (VRBO)`, url: `https://www.vrbo.com/icalendar/${randomId}/calendar.ics?property=${propertySlug}`, platform: "vrbo" };
}

function getMockBookingUrl(propertyName = "City Apartment") {
  const mockIds = ["prop_123456", "prop_789012", "prop_345678", "prop_901234"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '-');
  return { name: `${propertyName} (Booking.com)`, url: `https://calendar.booking.com/ical/${randomId}/calendar.ics?name=${propertySlug}`, platform: "booking" };
}

function getRandomCalendar() {
  const types = ['public', 'airbnb', 'vrbo', 'booking'];
  const type = types[Math.floor(Math.random() * types.length)];
  const propertyNames = ["Beachfront Villa", "Mountain Retreat", "City Loft", "Lake House", "Downtown Apartment", "Cozy Cabin", "Luxury Condo", "Garden Studio", "Penthouse Suite", "Country Cottage", "Ocean View", "Ski Chalet"];
  const randomName = propertyNames[Math.floor(Math.random() * propertyNames.length)];
  switch (type) {
    case 'airbnb': return getMockAirbnbUrl(randomName);
    case 'vrbo': return getMockVrboUrl(randomName);
    case 'booking': return getMockBookingUrl(randomName);
    default: return getRandomICalUrl();
  }
}

function fillWithRandomICal() {
  const random = getRandomCalendar();
  const inpName = document.getElementById('inp-name');
  const inpUrl = document.getElementById('inp-url');
  const platformSelect = document.getElementById('platform-select');
  if (inpName) inpName.value = random.name;
  if (inpUrl) inpUrl.value = random.url;
  if (platformSelect && random.platform) platformSelect.value = random.platform;
  if (typeof showToast === 'function') showToast(`Random ${random.platform} calendar generated!`, 'info');
}

/* ════════════════════════════════════════
   iCAL PARSER
════════════════════════════════════════ */
function parseIcal(text, srcId, platform = 'other') {
  text = text.replace(/\r\n[ \t]/g, '').replace(/\r\n/g, '\n');
  const blocks = text.match(/BEGIN:VEVENT[\s\S]*?END:VEVENT/g) || [];
  const bookings = [];
  const blockedDates = [];

  blocks.forEach((block, i) => {
    const g = k => { const m = block.match(new RegExp(`${k}[^:\\n]*:([^\\n]+)`)); return m ? m[1].trim() : ''; };
    const summary = g('SUMMARY');
    const description = g('DESCRIPTION');
    const td = r => { const c = r.replace(/[TZ].*/, ''); return c.length >= 8 ? `${c.slice(0, 4)}-${c.slice(4, 6)}-${c.slice(6, 8)}` : ''; };
    const start = td(g('DTSTART'));
    const end = td(g('DTEND'));
    if (!start || !end) return;

    const isBlocked = /block|unavailable|not\s+available|closed|hold|maintenance|owner|private/i.test(summary);
    if (isBlocked) {
      blockedDates.push({ start, end, summary, isBlocked: true });
      return;
    }

    let guest = 'Guest';
    let guestNameMissing = true;
    let extractedGuest = summary.replace(/reserved|reservation|airbnb|vrbo|booking\.com|booking|homeaway|confirmed|booked/gi, '').trim();
    extractedGuest = extractedGuest.replace(/^[-–—\s]+|[-–—\s]+$/g, '');
    if ((!extractedGuest || extractedGuest.length < 2 || extractedGuest === 'Guest') && description) {
      extractedGuest = description.replace(/reserved|reservation|airbnb|vrbo|booking\.com|booking|homeaway|confirmed|booked/gi, '').trim();
      extractedGuest = extractedGuest.replace(/^[-–—\s]+|[-–—\s]+$/g, '');
    }
    if (extractedGuest && extractedGuest.length >= 2 && extractedGuest !== 'Guest' && !extractedGuest.match(/^\d+$/)) {
      guest = extractedGuest;
      guestNameMissing = false;
    }
    bookings.push({ id: `${srcId}-${i}-${Date.now()}`, srcId, guest, start, end, guestNameMissing, platform, isBooking: true });
  });
  return { bookings, blockedDates };
}

async function refreshSingleSource(src) {
  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(src.url)}&platform=${src.platform}&unit=${encodeURIComponent(src.name)}`;
    const r = await fetch(proxyUrl);
    const data = await r.json();
    if (data.success) {
      const newBookings = data.events || [];
      const newBlockedDates = data.blockedEvents || [];
      src.blockedDates = newBlockedDates.map(block => ({ start: block.start, end: block.end, summary: block.summary }));
      const existingBookingsMap = new Map();
      src.bookings.forEach(bk => existingBookingsMap.set(bk.id, bk));
      const finalBookings = newBookings.map(ev => {
        const existing = existingBookingsMap.get(ev.id);
        const booking = { id: ev.id || `${src.id}-${Date.now()}-${Math.random()}`, srcId: src.id, guest: ev.guest || 'Guest', start: ev.start, end: ev.end, guestNameMissing: !ev.guest || ev.guest === 'Guest', platform: src.platform, notes: null, lastEdited: null };
        if (existing) {
          const wasCustomized = existing.guest !== 'Guest' && !existing.guestNameMissing;
          booking.guest = wasCustomized ? existing.guest : booking.guest;
          booking.guestNameMissing = wasCustomized ? false : booking.guestNameMissing;
          booking.notes = existing.notes || null;
          booking.lastEdited = existing.lastEdited || null;
        }
        return booking;
      });
      return { success: true, bookings: finalBookings, blockedDates: newBlockedDates };
    }
    return { success: false, error: data.error || 'Failed to parse iCal' };
  } catch (err) {
    return { success: false, error: err.message };
  }
}

/* ════════════════════════════════════════
   OVERLAP DETECTION
════════════════════════════════════════ */
function allVisible() { return sources.filter(s => s.enabled).flatMap(s => s.bookings); }
function detectOverlaps(bookings) {
  const out = [];
  for (let i = 0; i < bookings.length; i++)
    for (let j = i + 1; j < bookings.length; j++) {
      const a = bookings[i], b = bookings[j];
      if (a.srcId === b.srcId) continue;
      if (new Date(a.start) < new Date(b.end) && new Date(b.start) < new Date(a.end)) out.push({ a, b });
    }
  return out;
}

/* ════════════════════════════════════════
   SIMPLE DOT STATUS INDICATOR
════════════════════════════════════════ */
function addDateStatusIndicator(cellElement, date) {
  // Only show if available dates toggle is ON
  if (!availableDatesSettings?.enabled) {
    return;
  }
  
  if (!cellElement || cellElement.classList.contains('fc-day-other')) return;
  
  // Remove existing indicator
  const existingIndicator = cellElement.querySelector('.status-indicator-bar');
  if (existingIndicator) existingIndicator.remove();
  
  const dateStr = date.toISOString().split('T')[0];
  const statuses = [];
  
  sources.filter(s => s.enabled).forEach(src => {
    const isBooked = src.bookings.some(b => {
      const start = new Date(b.start + 'T00:00:00');
      const end = new Date(b.end + 'T00:00:00');
      const checkDate = new Date(dateStr + 'T00:00:00');
      return checkDate >= start && checkDate < end;
    });
    
    const isBlocked = src.blockedDates && src.blockedDates.some(block => {
      const start = new Date(block.start + 'T00:00:00');
      const end = new Date(block.end + 'T00:00:00');
      const checkDate = new Date(dateStr + 'T00:00:00');
      return checkDate >= start && checkDate < end;
    });
    
    if (isBooked) {
      statuses.push({ type: 'booked', srcName: src.name, color: src.color, platform: src.platform, priority: 1 });
    } else if (isBlocked && blockedDatesSettings.enabled) {
      statuses.push({ type: 'blocked', srcName: src.name, color: '#ef4444', platform: src.platform, priority: 2 });
    } else if (!isBooked && !isBlocked) {
      statuses.push({ type: 'available', srcName: src.name, color: availableDatesSettings.color, platform: src.platform, priority: 3 });
    }
  });
  
  statuses.sort((a, b) => a.priority - b.priority);
  if (statuses.length === 0) return;
  
  // Simple colored dots
  const dotContainer = document.createElement('div');
  dotContainer.className = 'status-indicator-bar';
  dotContainer.style.cssText = `
    position: absolute; 
    bottom: 2px; 
    right: 2px;
    display: flex;
    gap: 2px;
    z-index: 5;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.85);
    padding: 2px 4px;
    border-radius: 10px;
    backdrop-filter: blur(2px);
  `;
  
  // Add colored dots
  statuses.forEach((status) => {
    const dot = document.createElement('div');
    dot.style.cssText = `
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: ${status.type === 'booked' ? status.color : (status.type === 'blocked' ? '#ef4444' : status.color)};
      transition: all 0.2s;
      cursor: pointer;
    `;
    dot.title = `${status.srcName}: ${status.type.toUpperCase()}`;
    dot.addEventListener('mouseenter', (e) => { 
      e.stopPropagation(); 
      showSimpleTooltip(e, dateStr, status); 
    });
    dotContainer.appendChild(dot);
  });
  
  dotContainer.addEventListener('mouseenter', (e) => { 
    e.stopPropagation(); 
    showAllSimpleTooltip(e, dateStr, statuses); 
  });
  
  cellElement.appendChild(dotContainer);
}

/* ════════════════════════════════════════
   SIMPLE TOOLTIP FUNCTIONS
════════════════════════════════════════ */
function showSimpleTooltip(event, dateStr, status) {
  const tip = document.getElementById('tooltip');
  if (!tip) return;
  if (window.tipTimeout) clearTimeout(window.tipTimeout);
  
  const formattedDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
  const statusText = status.type === 'booked' ? '📅 Booked' : status.type === 'blocked' ? '🚫 Blocked' : '🟢 Available';
  const statusColor = status.type === 'booked' ? status.color : (status.type === 'blocked' ? '#ef4444' : status.color);
  
  tip.innerHTML = `
    <div class="text-xs font-bold uppercase tracking-wider mb-2 pb-1 border-b border-[#dce0f0] dark:border-[#2a3047]">${formattedDate}</div>
    <div class="flex items-center gap-2">
      <div class="w-6 h-6 rounded-full overflow-hidden flex items-center justify-center bg-white" style="border: 2px solid ${statusColor}">
        ${PLAT_LOGO[status.platform + '_small'] || PLAT_LOGO[status.platform] || PLAT_LOGO.other_small}
      </div>
      <div>
        <div class="font-bold text-sm">${status.srcName}</div>
        <div class="text-xs font-semibold" style="color: ${statusColor}">${statusText}</div>
      </div>
    </div>
  `;
  
  positionSimpleTooltip(event, tip);
  lucide.createIcons();
}

function showAllSimpleTooltip(event, dateStr, statuses) {
  const tip = document.getElementById('tooltip');
  if (!tip) return;
  if (window.tipTimeout) clearTimeout(window.tipTimeout);
  
  const formattedDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
  const booked = statuses.filter(s => s.type === 'booked');
  const blocked = statuses.filter(s => s.type === 'blocked');
  const available = statuses.filter(s => s.type === 'available');
  
  let tooltipHtml = `<div class="text-xs font-bold uppercase tracking-wider mb-2 pb-1 border-b border-[#dce0f0] dark:border-[#2a3047]">${formattedDate}</div>`;
  
  if (booked.length > 0) {
    tooltipHtml += `<div class="mb-2"><div class="text-xs font-semibold mb-1">📅 Booked (${booked.length})</div>${booked.map(s => `<div class="flex items-center gap-2 text-[11px] py-0.5"><div class="w-2 h-2 rounded-full" style="background:${s.color}"></div><span>${s.srcName}</span></div>`).join('')}</div>`;
  }
  
  if (blocked.length > 0) {
    tooltipHtml += `<div class="mb-2"><div class="text-xs font-semibold mb-1">🚫 Blocked (${blocked.length})</div>${blocked.map(s => `<div class="flex items-center gap-2 text-[11px] py-0.5"><div class="w-2 h-2 rounded-full bg-red-500"></div><span>${s.srcName}</span></div>`).join('')}</div>`;
  }
  
  if (available.length > 0 && availableDatesSettings.enabled) {
    tooltipHtml += `<div><div class="text-xs font-semibold mb-1">🟢 Available (${available.length})</div>${available.map(s => `<div class="flex items-center gap-2 text-[11px] py-0.5"><div class="w-2 h-2 rounded-full" style="background:${availableDatesSettings.color}"></div><span>${s.srcName}</span></div>`).join('')}</div>`;
  }
  
  tip.innerHTML = tooltipHtml;
  positionSimpleTooltip(event, tip);
  lucide.createIcons();
}

function positionSimpleTooltip(event, tip) {
  const x = event.clientX, y = event.clientY;
  const right = window.innerWidth - x > 200;
  const bottom = window.innerHeight - y > 150;
  tip.style.left = (right ? x + 12 : x - 180) + 'px';
  tip.style.top = (bottom ? y - 8 : y - 100) + 'px';
  tip.classList.remove('opacity-0', 'translate-y-1.5');
  tip.classList.add('opacity-100', 'translate-y-0');
}

function generateAvailableDates() {
  return [];
}

/* ════════════════════════════════════════
   HIDE TIP FUNCTION
════════════════════════════════════════ */
function hideTip() {
  if (window.tipTimeout) clearTimeout(window.tipTimeout);
  window.tipTimeout = setTimeout(() => {
    const tip = document.getElementById('tooltip');
    if (tip) { tip.classList.add('opacity-0', 'translate-y-1.5'); tip.classList.remove('opacity-100', 'translate-y-0'); }
  }, 180);
}

/* ════════════════════════════════════════
   LEGEND
════════════════════════════════════════ */
function renderLegend() {
  const el = document.getElementById('legend-list');
  if (!el) return;
  let legendHtml = '';
  
  if (sources.length) {
    sources.forEach(src => {
      legendHtml += `<div class="flex items-center gap-2.5 text-xs font-medium text-[#5b6380] dark:text-[#8b93b5]"><div class="w-3 h-3 rounded-full shrink-0" style="background:${src.color}"></div><span class="flex-1 truncate">${src.name}</span><div class="shrink-0 scale-75 transform origin-right">${PLAT_LOGO[src.platform + '_small'] || PLAT_LOGO[src.platform] || PLAT_LOGO.other_small}</div></div>`;
    });
  }
  
  if (availableDatesSettings.enabled) {
    legendHtml += `<div class="flex items-center gap-2.5 text-xs font-medium text-[#5b6380] dark:text-[#8b93b5] mt-2 pt-2 border-t border-[#dce0f0] dark:border-[#2a3047]"><div class="flex gap-1"><div class="w-2.5 h-2.5 rounded-full bg-primary"></div><div class="w-2.5 h-2.5 rounded-full bg-red-500"></div><div class="w-2.5 h-2.5 rounded-full" style="background:${availableDatesSettings.color}"></div></div><span class="flex-1">Status dots (Booked/Blocked/Available)</span></div>`;
    legendHtml += `<div class="flex items-center gap-2.5 text-[10px] text-[#5b6380] dark:text-[#8b93b5] mt-1"><i data-lucide="mouse-pointer" class="w-3 h-3"></i><span>Hover over dots for details</span></div>`;
  }
  
  if (!sources.length) {
    el.innerHTML = '<div class="text-xs text-[#9aa0bb] dark:text-[#4e5675]">Add sources to see legend</div>';
  } else {
    el.innerHTML = legendHtml;
  }
  lucide.createIcons();
}

/* ════════════════════════════════════════
   SETTINGS FUNCTIONS
════════════════════════════════════════ */
function updateAvailableDatesSettings() {
  const enabledCheckbox = document.getElementById('available-dates-enabled');
  const colorInput = document.getElementById('available-dates-color');
  const opacityInput = document.getElementById('available-dates-opacity');
  const showAsBlocksCheckbox = document.getElementById('available-dates-blocks');
  const quickToggle = document.getElementById('quick-available-toggle');
  
  if (enabledCheckbox) availableDatesSettings.enabled = enabledCheckbox.checked;
  if (colorInput) availableDatesSettings.color = colorInput.value;
  if (opacityInput) availableDatesSettings.opacity = parseFloat(opacityInput.value) / 100;
  if (showAsBlocksCheckbox) availableDatesSettings.showAsBlocks = showAsBlocksCheckbox.checked;
  if (quickToggle) quickToggle.checked = availableDatesSettings.enabled;
  
  const preview = document.getElementById('available-color-preview');
  if (preview) { 
    preview.style.backgroundColor = availableDatesSettings.color; 
    preview.style.opacity = availableDatesSettings.opacity; 
  }
  
  updateCalendar();
  saveAvailableSettings();
  
  const allBars = document.querySelectorAll('.status-indicator-bar');
  allBars.forEach(bar => bar.remove());
  
  if (availableDatesSettings.enabled) {
    refreshDateIndicators();
  }
}

function toggleSourceAvailable(srcId, enabled) {
  const src = sources.find(s => s.id === srcId);
  if (src) { src.showAvailable = enabled; updateCalendar(); saveToServer(); refreshDateIndicators(); }
}

function toggleAvailableDatesQuick() {
  const checkbox = document.getElementById('quick-available-toggle');
  if (!checkbox) return;
  const newState = checkbox.checked;
  availableDatesSettings.enabled = newState;
  
  const modalToggle = document.getElementById('available-dates-enabled');
  if (modalToggle) modalToggle.checked = newState;
  
  saveAvailableSettings();
  
  const allBars = document.querySelectorAll('.status-indicator-bar');
  allBars.forEach(bar => bar.remove());
  
  if (newState) {
    refreshDateIndicators();
    showToast('Showing property status dots', 'success');
  } else {
    showToast('Hiding property status dots', 'info');
  }
  
  renderLegend();
}

function refresh() {
  renderSidebar();
  renderLegend();
  updateCalendar();
  updateStats();
  updateList();
  updateEmptyState();
  updateRefreshButtonState();
  updateLastUpdatedDisplay();
  setTimeout(() => { refreshDateIndicators(); }, 100);
}

function refreshDateIndicators() {
  setTimeout(() => {
    const dayCells = document.querySelectorAll('.fc-daygrid-day');
    dayCells.forEach(cell => {
      const dateAttr = cell.getAttribute('data-date');
      if (dateAttr) { 
        const date = new Date(dateAttr); 
        if (!isNaN(date.getTime())) {
          const existing = cell.querySelector('.status-indicator-bar');
          if (existing) existing.remove();
          if (availableDatesSettings?.enabled) {
            addDateStatusIndicator(cell, date);
          }
        }
      }
    });
  }, 10);
}

function renderSidebar() {
  const el = document.getElementById('source-list');
  if (!el) return;
  const srcCount = document.getElementById('src-count');
  if (srcCount) srcCount.textContent = `${sources.length} source${sources.length !== 1 ? 's' : ''}`;
  if (!sources.length) { el.innerHTML = '<div class="text-center py-5 text-[#9aa0bb] dark:text-[#4e5675] text-xs">No sources yet.<br>Click <strong>+ Add iCal</strong> to start.</div>'; return; }
  el.innerHTML = '';
  sources.forEach(src => {
    const row = document.createElement('div');
    row.className = `flex items-center gap-2.5 p-2.5 rounded-xl border-2 transition-all cursor-default ${src.enabled ? 'border-transparent bg-primary/5 dark:bg-primary/10' : 'border-transparent bg-[#f4f6fc] dark:bg-[#1f2436]'}`;
    if (src.enabled) row.style.borderColor = src.color + '40';
    row.innerHTML = `<div class="w-2.5 h-2.5 rounded-full shrink-0 shadow-[0_0_0_2px_rgba(255,255,255,0.1)]" style="background:${src.color}"></div><div class="flex-1 min-w-0"><div class="text-[12px] font-semibold leading-tight truncate">${src.name}</div><div class="text-[10px] text-[#5b6380] dark:text-[#8b93b5]">${PLAT_LABEL[src.platform] || src.platform} &middot; ${src.bookings.length} reservations</div></div><div class="shrink-0 opacity-90">${PLAT_LOGO[src.platform] || PLAT_LOGO.other}</div><button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-primary dark:hover:text-primary hover:bg-primary/10 transition-all" onclick="editSourceModal('${src.id}',event)" title="Edit source"><i data-lucide="edit-3" class="w-4 h-4" style="pointer-events:none"></i></button><button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-red-500 dark:hover:text-red-400 hover:bg-red-500/10 transition-all" onclick="deleteSource('${src.id}',event)" title="Delete source"><i data-lucide="trash-2" class="w-4 h-4" style="pointer-events:none"></i></button><button class="w-9 h-5 rounded-full relative shrink-0 transition-colors ${src.enabled ? 'bg-primary' : 'bg-[#dce0f0] dark:bg-[#2a3047]'}" onclick="toggleSource('${src.id}',event)"><div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform shadow-sm ${src.enabled ? 'translate-x-4' : ''}"></div></button>`;
    el.appendChild(row);
  });
  lucide.createIcons();
}

function toggleSource(id, e) { e.stopPropagation(); const src = sources.find(s => s.id === id); if (src) { src.enabled = !src.enabled; refresh(); saveToServer(); } }
function deleteSource(id, e) { e.stopPropagation(); if (!confirm('Delete this iCal source and all its bookings?')) return; sources = sources.filter(s => s.id !== id); refresh(); saveToServer(); const settingsModal = document.getElementById('settings-modal-bg'); if (settingsModal && settingsModal.classList.contains('open')) closeSettings(); }
function clearAllSources() { if (sources.length === 0) return; if (!confirm('Delete ALL iCal sources and bookings? This cannot be undone.')) return; sources = []; refresh(); saveToServer(); }
function updateRefreshButtonState() {
  const hasSource = sources.length > 0;
  const refreshBtn = document.getElementById('refresh-btn');
  const clearBtn = document.getElementById('clear-all-btn');
  const srcCount = document.getElementById('src-count');
  const lastUpdatedEl = document.getElementById('last-updated');
  if (refreshBtn) refreshBtn.disabled = !hasSource;
  if (clearBtn) clearBtn.disabled = !hasSource;
  if (srcCount) srcCount.textContent = sources.length;
  if (lastUpdatedEl) {
    if (!lastRefreshTime) { lastUpdatedEl.textContent = 'Never synced'; }
    else { const diff = Math.floor((Date.now() - lastRefreshTime) / 1000); let t = diff < 60 ? 'just now' : diff < 3600 ? `${Math.floor(diff / 60)} min ago` : diff < 86400 ? `${Math.floor(diff / 3600)}h ago` : `${Math.floor(diff / 86400)}d ago`; lastUpdatedEl.textContent = `Synced ${t}`; }
  }
}

async function refreshAllSources() {
  if (sources.length === 0) { showToast('No sources to refresh', 'warning'); return; }
  const refreshBtn = document.getElementById('refresh-btn');
  const refreshBtnHeader = document.getElementById('refresh-btn-header');
  const refreshIcon = document.getElementById('refresh-icon');
  const refreshIconHeader = document.getElementById('refresh-icon-header');
  const refreshLabel = document.getElementById('refresh-label');
  const refreshStatus = document.getElementById('refresh-status');
  const refreshStatusModal = document.getElementById('refresh-status-modal');
  [refreshBtn, refreshBtnHeader].forEach(btn => { if (btn) { btn.disabled = true; btn.classList.add('opacity-60', 'pointer-events-none'); } });
  [refreshIcon, refreshIconHeader].forEach(icon => { if (icon) icon.classList.add('animate-spin'); });
  if (refreshLabel) refreshLabel.textContent = 'Syncing…';
  if (refreshStatus) refreshStatus.classList.remove('hidden');
  if (refreshStatusModal) refreshStatusModal.classList.remove('hidden');
  let successCount = 0, errorCount = 0;
  for (const src of sources) {
    try {
      const result = await refreshSingleSource(src);
      if (result.success) { src.bookings = result.bookings; src.blockedDates = result.blockedDates; successCount++; }
      else { errorCount++; }
    } catch (err) { errorCount++; }
  }
  lastRefreshTime = new Date();
  refresh();
  updateLastUpdatedDisplay();
  await saveToServer();
  [refreshBtn, refreshBtnHeader].forEach(btn => { if (btn) { btn.disabled = false; btn.classList.remove('opacity-60', 'pointer-events-none'); } });
  [refreshIcon, refreshIconHeader].forEach(icon => { if (icon) icon.classList.remove('animate-spin'); });
  if (refreshLabel) refreshLabel.textContent = 'Refresh All Calendars';
  if (refreshStatus) refreshStatus.classList.add('hidden');
  if (refreshStatusModal) refreshStatusModal.classList.add('hidden');
  if (errorCount > 0) { showToast(`Refreshed ${successCount} calendars. ${errorCount} failed.`, 'warning'); }
  else { showToast(`Successfully refreshed ${successCount} calendar(s)`, 'success'); }
}

function updateLastUpdatedDisplay() {
  const lastUpdatedEl = document.getElementById('last-updated');
  const lastUpdatedHeader = document.getElementById('last-updated-header');
  const lastUpdatedModal = document.getElementById('last-updated-modal');
  if (!lastRefreshTime) { const text = 'Never synced'; if (lastUpdatedEl) lastUpdatedEl.textContent = text; if (lastUpdatedHeader) lastUpdatedHeader.textContent = text; if (lastUpdatedModal) lastUpdatedModal.textContent = text; return; }
  const diff = Math.floor((Date.now() - lastRefreshTime) / 1000);
  let text = diff < 60 ? 'just now' : diff < 3600 ? `${Math.floor(diff / 60)} min ago` : diff < 86400 ? `${Math.floor(diff / 3600)}h ago` : `${Math.floor(diff / 86400)}d ago`;
  const displayText = `Synced ${text}`;
  if (lastUpdatedEl) lastUpdatedEl.textContent = displayText;
  if (lastUpdatedHeader) lastUpdatedHeader.textContent = displayText;
  if (lastUpdatedModal) lastUpdatedModal.textContent = displayText;
}

function makeCalEvents() {
  const vis = allVisible();
  const ovIds = new Set();
  detectOverlaps(vis).forEach(({ a, b }) => { ovIds.add(a.id); ovIds.add(b.id); });
  const bookingEvents = vis.map((b, _i) => {
    const src = sources.find(s => s.id === b.srcId);
    const isOv = ovIds.has(b.id);
    const srcIdx = sources.findIndex(s => s.id === b.srcId);
    return { id: b.id, title: b.guest, start: b.start, end: b.end, color: src?.color || '#888', textColor: '#ffffff', order: srcIdx, extendedProps: { ...b, isOverlap: isOv, srcColor: src?.color, srcName: src?.name, srcPlatform: src?.platform } };
  });
  return bookingEvents;
}

function updateCalendar() {
  if (!calendar) return;
  calendar.removeAllEvents();
  const bookingEvents = makeCalEvents();
  calendar.addEventSource(bookingEvents);
}

function updateStats() {
  const now = new Date(), y = now.getFullYear(), mo = now.getMonth();
  const mS = new Date(y, mo, 1), mE = new Date(y, mo + 1, 1);
  const vis = allVisible();
  const month = vis.filter(b => new Date(b.start) < mE && new Date(b.end) > mS);
  const statTotal = document.getElementById('stat-total');
  const statOverlap = document.getElementById('stat-overlap');
  const statNights = document.getElementById('stat-nights');
  const statAvail = document.getElementById('stat-avail');
  if (statTotal) statTotal.textContent = month.length;
  if (statOverlap) statOverlap.textContent = detectOverlaps(month).length;
  const days = new Set();
  month.forEach(b => { let d = new Date(b.start); while (d < new Date(b.end)) { days.add(d.toISOString().split('T')[0]); d.setDate(d.getDate() + 1); } });
  if (statNights) statNights.textContent = days.size;
  if (statAvail) statAvail.textContent = Math.max(0, new Date(y, mo + 1, 0).getDate() - days.size);
}

function updateList() {
  const vis = allVisible();
  const ovPairs = detectOverlaps(vis);
  const ovIds = new Set();
  ovPairs.forEach(({ a, b }) => { ovIds.add(a.id); ovIds.add(b.id); });
  const ovEl = document.getElementById('ov-list');
  const badge = document.getElementById('ov-badge');
  if (ovEl) {
    ovEl.innerHTML = '';
    if (ovPairs.length) {
      if (badge) badge.classList.remove('hidden');
      ovPairs.forEach(({ a, b }) => {
        const srcA = sources.find(s => s.id === a.srcId);
        const srcB = sources.find(s => s.id === b.srcId);
        const el = document.createElement('div');
        el.className = 'flex items-start gap-4 p-4 bg-overlap/10 border-2 border-overlap/25 rounded-2xl text-[13px] text-overlap leading-relaxed mb-3 shadow-sm';
        const renderClash = (bk, src) => `<div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full shrink-0 overflow-hidden flex items-center justify-center bg-white" style="border: 2px solid ${src?.color}">${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}</div><div class="min-w-0 flex-1"><div class="font-bold text-sm">${src?.name || 'Unknown'}</div><div class="text-[11px] opacity-80 font-medium">${fmtDate(bk.start)} — ${fmtDate(bk.end)}</div></div></div>`;
        el.innerHTML = `<i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5 animate-pulse"></i><div class="flex flex-col gap-3 flex-1"><strong class="text-sm font-black uppercase tracking-tight">Double Booking Conflict</strong><div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">${renderClash(a, srcA)}<div class="hidden sm:block text-center opacity-30 font-bold text-[10px]">VS</div>${renderClash(b, srcB)}</div></div>`;
        ovEl.appendChild(el);
      });
    } else { if (badge) badge.classList.add('hidden'); }
  }
  const listEl = document.getElementById('booking-list');
  if (listEl) {
    listEl.innerHTML = '';
    const upcoming = [...vis].filter(b => new Date(b.end) >= new Date()).sort((a, b) => new Date(a.start) - new Date(b.start));
    if (!upcoming.length) { listEl.innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>No upcoming reservations</p></div>'; return; }
    upcoming.forEach(b => {
      const src = sources.find(s => s.id === b.srcId);
      const isOv = ovIds.has(b.id);
      const color = src?.color || '#888';
      const div = document.createElement('div');
      div.className = `flex items-center gap-3.5 p-3.5 bg-white dark:bg-[#181c27] border-2 border-[#dce0f0] dark:border-[#2a3047] rounded-xl hover:border-[#b4bcd0] dark:hover:border-[#353d5a] transition-all relative overflow-hidden group`;
      div.dataset.bookingId = b.id;
      div.dataset.srcId = b.srcId;
      div.onclick = (e) => { if (e.target.closest('.edit-booking-btn')) return; e.stopPropagation(); openEditModal(b.id, b.srcId); };
      div.oncontextmenu = (e) => { e.preventDefault(); showContextMenu(e, b.id, b.srcId); };
      div.innerHTML = `<div class="absolute left-0 top-0 bottom-0 w-1" style="background:${color}"></div><div class="w-8 h-8 rounded-full shrink-0 shadow-sm overflow-hidden flex items-center justify-center bg-white" style="border: 2px solid ${color}">${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}</div><div class="flex-1 min-w-0"><div class="flex items-center gap-1.5"><span class="text-[13px] font-bold leading-tight truncate">${src?.name || ''}</span>${b.guest && b.guest !== 'Guest' && !b.guestNameMissing ? `<span class="text-[11px] text-[#5b6380] dark:text-[#8b93b5]">(${b.guest})</span>` : b.guestNameMissing && src?.platform === 'airbnb' ? '<span class="text-[11px] text-amber-500 italic">(No guest name)</span>' : ''}${isOv ? '<i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-overlap ml-1" style="pointer-events:none"></i>' : ''}</div><div class="text-[11px] text-[#5b6380] dark:text-[#8b93b5] mt-0.5 flex items-center gap-1.5 font-medium"><i data-lucide="calendar" class="w-3.5 h-3.5" style="pointer-events:none"></i> ${fmtDate(b.start)} → ${fmtDate(b.end)} &middot; ${nts(b.start, b.end)} nights</div></div><div class="flex items-center gap-2 shrink-0"><button class="edit-booking-btn w-8 h-8 rounded-lg flex items-center justify-center ${b.guestNameMissing ? 'text-amber-500 bg-amber-500/10 hover:bg-amber-500/20' : 'text-primary bg-primary/10 hover:bg-primary/20'} transition-all" onclick="event.stopPropagation(); openEditModal('${b.id}', '${b.srcId}')" title="${b.guestNameMissing ? 'Add guest name' : 'Edit booking'}"><i data-lucide="${b.guestNameMissing ? 'user-plus' : 'edit-3'}" class="w-4 h-4"></i></button><span class="text-[10px] font-bold tracking-wider px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm border border-transparent" style="background:${color}15; color:${color}; border-color:${color}20"><span class="scale-75 origin-center">${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}</span>${PLAT_LABEL[src?.platform] || 'Other'}</span></div>`;
      listEl.appendChild(div);
    });
    lucide.createIcons();
  }
}

function fmtDate(str) { return new Date(str + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
function nts(s, e) { return Math.round((new Date(e) - new Date(s)) / 864e5); }

function showTip(event, jsEvent) {
  if (!tip) return;
  clearTimeout(tipT);
  const b = event.extendedProps;
  const src = sources.find(s => s.id === b.srcId);
  const color = src?.color || '#888';
  const plat = src?.platform || 'other';
  const guestName = b.guest && b.guest !== 'Guest' && !b.guestNameMissing ? b.guest : '';
  const tipDot = document.getElementById('tip-dot');
  const tipPlatName = document.getElementById('tip-plat-name');
  const tipPlatLogo = document.getElementById('tip-plat-logo');
  const tipAvatar = document.getElementById('tip-avatar');
  const tipGuest = document.getElementById('tip-guest');
  const tipUnit = document.getElementById('tip-unit');
  const tipDates = document.getElementById('tip-dates');
  const tipNights = document.getElementById('tip-nights');
  const tipOv = document.getElementById('tip-ov');
  if (tipDot) tipDot.style.background = color;
  if (tipPlatName) { tipPlatName.textContent = src?.name || PLAT_LABEL[plat]; tipPlatName.style.color = color; }
  if (tipPlatLogo) tipPlatLogo.innerHTML = PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small;
  if (tipAvatar) { tipAvatar.style.background = color; if (guestName) { tipAvatar.innerHTML = initials(guestName); } else { tipAvatar.innerHTML = PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small; } }
  if (tipGuest) { if (guestName) { tipGuest.textContent = guestName; tipGuest.className = 'text-sm font-bold mb-1'; } else { tipGuest.textContent = plat === 'airbnb' ? 'No guest name - Click to add' : 'Guest name not available'; tipGuest.className = 'text-sm font-bold mb-1 text-amber-600 dark:text-amber-400 italic'; } }
  if (tipUnit) tipUnit.textContent = src?.name || '';
  if (tipDates) tipDates.textContent = `${fmtDate(b.start)} → ${fmtDate(b.end)}`;
  if (tipNights) tipNights.textContent = `🌙 ${nts(b.start, b.end)} nights`;
  if (tipOv) b.isOverlap ? tipOv.classList.remove('hidden') : tipOv.classList.add('hidden');
  const x = jsEvent.clientX, y = jsEvent.clientY;
  const right = window.innerWidth - x > 260;
  tip.style.left = (right ? x + 16 : x - 246) + 'px';
  tip.style.top = (y - 8) + 'px';
  tip.classList.remove('opacity-0', 'translate-y-1.5');
  tip.classList.add('opacity-100', 'translate-y-0');
  lucide.createIcons();
}

function openAddModal() {
  const inpName = document.getElementById('inp-name');
  const inpUrl = document.getElementById('inp-url');
  const inpColor = document.getElementById('inp-color');
  if (inpName) inpName.value = '';
  if (inpUrl) inpUrl.value = '';
  const used = sources.map(s => s.color.toLowerCase());
  const next = PRESET_COLORS.find(c => !used.includes(c.toLowerCase())) || PRESET_COLORS[sources.length % PRESET_COLORS.length];
  if (inpColor) inpColor.value = next;
  renderPresetColors();
  const modalBg = document.getElementById('add-modal-bg');
  if (modalBg) modalBg.classList.add('open');
}

function closeAddModal() { const modalBg = document.getElementById('add-modal-bg'); if (modalBg) modalBg.classList.remove('open'); }

async function loadFromServer() {
  try {
    const r = await fetch('data.php');
    const data = await r.json();
    if (Array.isArray(data.sources) && data.sources.length) {
      sources = data.sources;
      sources.forEach(src => {
        if (Array.isArray(src.bookings)) { src.bookings.forEach(bk => { if (bk.guestNameMissing === undefined) bk.guestNameMissing = !bk.guest || bk.guest === 'Guest'; }); }
        if (src.showAvailable === undefined) src.showAvailable = true;
        if (!src.blockedDates) src.blockedDates = [];
      });
    }
    if (data.lastRefreshTime) lastRefreshTime = new Date(data.lastRefreshTime);
    if (data.availableDatesSettings) availableDatesSettings = { ...availableDatesSettings, ...data.availableDatesSettings };
  } catch (e) { console.warn('Could not load saved data:', e); }
  refresh();
  const quickToggle = document.getElementById('quick-available-toggle');
  if (quickToggle) quickToggle.checked = availableDatesSettings?.enabled || false;
  setTimeout(() => { refreshAllSources(); }, 500);
}

async function saveToServer() {
  try { await fetch('data.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ sources, lastRefreshTime: lastRefreshTime ? lastRefreshTime.toISOString() : null, availableDatesSettings }) }); } 
  catch (e) { console.warn('Could not save data:', e); }
}

function saveAvailableSettings() { saveToServer(); }

function editSourceModal(sourceId, event) {
  event.stopPropagation();
  const src = sources.find(s => s.id === sourceId);
  if (!src) return;
  editingSourceId = sourceId;
  const nameInput = document.getElementById('edit-source-name-input');
  const urlInput = document.getElementById('edit-source-url-input');
  const colorInput = document.getElementById('edit-source-color-input');
  const sourceIdInput = document.getElementById('edit-source-id');
  if (nameInput) nameInput.value = src.name;
  if (urlInput) urlInput.value = src.url;
  if (colorInput) colorInput.value = src.color;
  if (sourceIdInput) sourceIdInput.value = sourceId;
  const modalBg = document.getElementById('edit-source-modal-bg');
  if (modalBg) { modalBg.classList.add('open'); setTimeout(() => { nameInput?.focus(); lucide.createIcons(); }, 80); }
}

function closeEditSourceModal() { const modalBg = document.getElementById('edit-source-modal-bg'); if (modalBg) modalBg.classList.remove('open'); editingSourceId = null; }

function saveSourceEdit() {
  const nameInput = document.getElementById('edit-source-name-input');
  const urlInput = document.getElementById('edit-source-url-input');
  const colorInput = document.getElementById('edit-source-color-input');
  const sourceIdInput = document.getElementById('edit-source-id');
  const newName = nameInput?.value.trim();
  const newUrl = urlInput?.value.trim();
  const newColor = colorInput?.value;
  const sourceId = sourceIdInput?.value;
  if (!newName) { showToast('Please enter a source name', 'error'); return; }
  if (!newUrl) { showToast('Please enter an iCal URL', 'error'); return; }
  const src = sources.find(s => s.id === sourceId);
  if (src) { src.name = newName; src.url = newUrl; src.color = newColor; refreshAllSources(); showToast('Source updated successfully', 'success'); closeEditSourceModal(); }
}

function openEditModal(bookingId, srcId) {
  editingBookingId = bookingId;
  editingSrcId = srcId;
  const src = sources.find(s => s.id === srcId);
  const bk = src?.bookings.find(b => b.id === bookingId);
  if (!bk) { console.warn('Booking not found:', bookingId, srcId); showToast('Booking not found', 'error'); return; }
  const noGuestName = bk.guestNameMissing || bk.guest === 'Guest';
  const editGuest = document.getElementById('edit-guest');
  const editStart = document.getElementById('edit-start');
  const editEnd = document.getElementById('edit-end');
  const editNotes = document.getElementById('edit-notes');
  const editDates = document.getElementById('edit-dates');
  const editSrcName = document.getElementById('edit-source-name');
  const editPlatformBadge = document.getElementById('edit-platform-badge');
  const editModalTitle = document.getElementById('edit-modal-title');
  if (editGuest) { editGuest.value = noGuestName ? '' : bk.guest; editGuest.placeholder = noGuestName ? 'Enter guest name...' : 'Edit guest name'; }
  if (editStart) editStart.value = bk.start;
  if (editEnd) editEnd.value = bk.end;
  if (editNotes) editNotes.value = bk.notes || '';
  if (editDates) editDates.textContent = `${fmtDate(bk.start)} → ${fmtDate(bk.end)}  ·  ${nts(bk.start, bk.end)} nights`;
  if (editSrcName) editSrcName.textContent = src?.name || '';
  if (editPlatformBadge) { const plat = src?.platform || 'other'; editPlatformBadge.innerHTML = `${PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small} ${PLAT_LABEL[plat] || 'Other'}`; }
  if (editModalTitle) editModalTitle.textContent = noGuestName ? 'Add Guest Name' : 'Edit Booking Details';
  const modalBg = document.getElementById('edit-modal-bg');
  if (modalBg) { modalBg.classList.add('open'); setTimeout(() => { editGuest?.focus(); lucide.createIcons(); }, 80); }
}

function closeEditModal() { const modalBg = document.getElementById('edit-modal-bg'); if (modalBg) modalBg.classList.remove('open'); editingBookingId = null; editingSrcId = null; }

async function saveEditBooking() {
  const guest = document.getElementById('edit-guest')?.value.trim();
  const start = document.getElementById('edit-start')?.value;
  const end = document.getElementById('edit-end')?.value;
  const notes = document.getElementById('edit-notes')?.value.trim();
  if (!guest) { alert('Please enter a guest name.'); return; }
  if (start && end && new Date(start) >= new Date(end)) { alert('Check-out date must be after check-in date'); return; }
  const src = sources.find(s => s.id === editingSrcId);
  const bk = src?.bookings.find(b => b.id === editingBookingId);
  if (bk) { bk.guest = guest; bk.guestNameMissing = false; if (start && end) { bk.start = start; bk.end = end; } if (notes) { bk.notes = notes; } else { delete bk.notes; } bk.lastEdited = new Date().toISOString(); }
  refresh();
  await saveToServer();
  closeEditModal();
  showToast('Booking updated successfully', 'success');
}

async function deleteBooking() {
  if (!editingBookingId || !editingSrcId) return;
  if (!confirm('Are you sure you want to delete this booking? This action cannot be undone.')) return;
  const src = sources.find(s => s.id === editingSrcId);
  if (src) { const bookingIndex = src.bookings.findIndex(b => b.id === editingBookingId); if (bookingIndex !== -1) { src.bookings.splice(bookingIndex, 1); refresh(); await saveToServer(); closeEditModal(); showToast('Booking deleted successfully', 'success'); } }
}

async function duplicateBooking(bookingId, srcId) {
  const src = sources.find(s => s.id === srcId);
  const bk = src?.bookings.find(b => b.id === bookingId);
  if (!bk) return;
  const newBooking = { ...bk, id: `${srcId}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`, guest: `${bk.guest} (copy)`, guestNameMissing: false };
  src.bookings.push(newBooking);
  refresh();
  await saveToServer();
  showToast('Booking duplicated', 'info');
}

function showContextMenu(event, bookingId, srcId) {
  event.preventDefault();
  event.stopPropagation();
  contextMenuBooking = { bookingId, srcId };
  const menu = document.getElementById('booking-context-menu');
  if (!menu) return;
  menu.style.left = event.clientX + 'px';
  menu.style.top = event.clientY + 'px';
  menu.classList.remove('hidden');
  setTimeout(() => { document.addEventListener('click', hideContextMenu); }, 10);
}

function hideContextMenu() { const menu = document.getElementById('booking-context-menu'); if (menu) menu.classList.add('hidden'); document.removeEventListener('click', hideContextMenu); contextMenuBooking = null; }

function contextMenuAction(action) {
  if (!contextMenuBooking) return;
  const { bookingId, srcId } = contextMenuBooking;
  switch (action) {
    case 'edit': openEditModal(bookingId, srcId); break;
    case 'delete': editingBookingId = bookingId; editingSrcId = srcId; deleteBooking(); break;
    case 'duplicate': duplicateBooking(bookingId, srcId); break;
  }
  hideContextMenu();
}

function showToast(message, type = 'info') {
  const existingToast = document.getElementById('toast');
  if (existingToast) existingToast.remove();
  const toast = document.createElement('div');
  toast.id = 'toast';
  toast.className = `fixed bottom-5 right-5 z-[10000] px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-0 opacity-100`;
  const colors = { success: 'bg-emerald-500 text-white', error: 'bg-red-500 text-white', info: 'bg-primary text-white', warning: 'bg-amber-500 text-white' };
  toast.className += ' ' + (colors[type] || colors.info);
  const icons = { success: 'check-circle', error: 'alert-circle', info: 'info', warning: 'alert-triangle' };
  toast.innerHTML = `<i data-lucide="${icons[type]}" class="w-5 h-5"></i><span class="text-sm font-medium">${message}</span><button onclick="this.parentElement.remove()" class="ml-2 hover:opacity-80"><i data-lucide="x" class="w-4 h-4"></i></button>`;
  document.body.appendChild(toast);
  lucide.createIcons();
  setTimeout(() => { toast.style.transform = 'translateY(100%)'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

function exportBookings() {
  const exportData = { sources: sources.map(src => ({ id: src.id, name: src.name, platform: src.platform, color: src.color, url: src.url, showAvailable: src.showAvailable, bookings: src.bookings.map(bk => ({ id: bk.id, guest: bk.guest, start: bk.start, end: bk.end, notes: bk.notes, guestNameMissing: bk.guestNameMissing, lastEdited: bk.lastEdited })) })), availableDatesSettings, exportDate: new Date().toISOString(), version: '1.0' };
  const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `staysync-backup-${new Date().toISOString().split('T')[0]}.json`;
  a.click();
  showToast('Bookings exported successfully', 'success');
}

function updateEmptyState() { const el = document.getElementById('empty-state'); if (!el) return; if (sources.length === 0) { el.classList.remove('hidden'); } else { el.classList.add('hidden'); } }

async function addFromUrl() {
  const inpName = document.getElementById('inp-name');
  const inpColor = document.getElementById('inp-color');
  const inpUrl = document.getElementById('inp-url');
  const platformSelect = document.getElementById('platform-select');
  const name = (inpName && inpName.value.trim()) || 'My iCal Listing';
  const color = inpColor ? inpColor.value : '#5b7fff';
  const url = inpUrl ? inpUrl.value.trim() : '';
  let plat = platformSelect ? platformSelect.value : 'other';
  if (!url) { alert('Please enter an iCal URL.'); return; }
  const btn = event.target;
  const original = btn.innerHTML;
  btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Fetching...';
  btn.disabled = true;
  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(url)}&platform=${plat}&unit=${encodeURIComponent(name)}`;
    const r = await fetch(proxyUrl);
    const data = await r.json();
    if (data.success && data.events) {
      const id = 'src-' + Date.now();
      const bookings = data.events.map(ev => ({ id: ev.id || `${id}-${Math.random()}`, srcId: id, guest: ev.guest || 'Guest', start: ev.start, end: ev.end, guestNameMissing: (plat === 'airbnb') || !ev.guest || ev.guest === 'Guest', notes: null, lastEdited: null, platform: plat }));
      sources.push({ id, name, platform: plat, color, enabled: true, url, showAvailable: true, bookings, blockedDates: [] });
      lastRefreshTime = new Date();
      refresh();
      updateRefreshButtonState();
      await saveToServer();
      closeAddModal();
      showToast('Calendar added successfully', 'success');
    } else { throw new Error(data.error || 'Failed to fetch or parse iCal.'); }
  } catch (err) { showToast('Error: ' + err.message, 'error'); }
  finally { btn.innerHTML = original; btn.disabled = false; lucide.createIcons(); }
}

function openSettings() { renderReorderList(); renderAvailableSettings(); const modalBg = document.getElementById('settings-modal-bg'); if (modalBg) modalBg.classList.add('open'); }
function closeSettings() { const modalBg = document.getElementById('settings-modal-bg'); if (modalBg) modalBg.classList.remove('open'); }

function renderAvailableSettings() {
  const container = document.getElementById('available-settings-container');
  if (!container) return;
  const enabledCount = sources.filter(s => s.enabled && s.showAvailable).length;
  const totalEnabled = sources.filter(s => s.enabled).length;
  container.innerHTML = `<div class="space-y-4"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><i data-lucide="calendar-check" class="w-5 h-5 text-primary"></i><span class="font-bold">Show Status Dots</span></div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="available-dates-enabled" class="sr-only peer" ${availableDatesSettings.enabled ? 'checked' : ''} onchange="updateAvailableDatesSettings()"><div class="w-11 h-6 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div></label></div>${availableDatesSettings.enabled ? `<div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-xs"><div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 mb-1"><i data-lucide="info" class="w-3.5 h-3.5"></i><span class="font-semibold">Status dots show: Booked, Blocked, Available</span></div><p class="text-[#5b6380] dark:text-[#8b93b5]">Hover over dots to see property details</p></div>` : ''}<div class="grid grid-cols-2 gap-4 mt-4"><div><label class="block text-xs font-medium text-[#5b6380] dark:text-[#8b93b5] mb-1">Available Color</label><div class="flex gap-2"><input type="color" id="available-dates-color" value="${availableDatesSettings.color}" onchange="updateAvailableDatesSettings()" class="w-10 h-10 rounded cursor-pointer border border-[#dce0f0] dark:border-[#2a3047] p-1 bg-white dark:bg-[#181c27]"><div id="available-color-preview" class="w-10 h-10 rounded border border-[#dce0f0] dark:border-[#2a3047]" style="background-color:${availableDatesSettings.color}; opacity:${availableDatesSettings.opacity}"></div></div></div><div><label class="block text-xs font-medium text-[#5b6380] dark:text-[#8b93b5] mb-1">Opacity</label><input type="range" id="available-dates-opacity" min="0" max="100" value="${availableDatesSettings.opacity * 100}" onchange="updateAvailableDatesSettings()" oninput="updateAvailableDatesSettings()" class="w-full"></div></div><div class="flex items-center gap-2 mt-2"><input type="checkbox" id="available-dates-blocks" ${availableDatesSettings.showAsBlocks ? 'checked' : ''} onchange="updateAvailableDatesSettings()" class="w-4 h-4 rounded border-[#dce0f0] dark:border-[#2a3047] text-primary focus:ring-primary"><label for="available-dates-blocks" class="text-sm">Show as continuous blocks</label></div><div class="mt-6"><h4 class="font-bold text-sm mb-3">Enable per property:</h4><div class="space-y-2 max-h-48 overflow-y-auto pr-2">${sources.map(src => `<div class="flex items-center justify-between p-2 bg-[#f4f6fc] dark:bg-[#1f2436] rounded-lg"><div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full" style="background:${src.color}"></div><span class="text-sm font-medium">${src.name}</span>${src.showAvailable ? '<span class="text-[10px] text-emerald-500">(active)</span>' : ''}</div><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" class="sr-only peer" ${src.showAvailable ? 'checked' : ''} onchange="toggleSourceAvailable('${src.id}', this.checked)"><div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div></label></div>`).join('') || '<div class="text-center text-[#9aa0bb] dark:text-[#4e5675] text-sm py-4">No sources available</div>'}</div></div><div class="mt-4 text-xs text-[#5b6380] dark:text-[#8b93b5] bg-[#f4f6fc] dark:bg-[#1f2436] p-2 rounded-lg"><i data-lucide="mouse-pointer" class="w-3 h-3 inline mr-1"></i><strong>Tip:</strong> Colored dots appear on dates showing property status. Hover for details.</div></div>`;
  lucide.createIcons();
}

function renderReorderList() {
  const el = document.getElementById('reorder-list');
  if (!el) return;
  el.innerHTML = '';
  if (!sources.length) { el.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text3);font-size:13px">No sources added yet.</div>'; return; }
  sources.forEach((src, idx) => {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-3 p-3 bg-[#f4f6fc] dark:bg-[#1f2436] border border-[#dce0f0] dark:border-[#2a3047] rounded-xl cursor-grab active:cursor-grabbing transition-all hover:border-[#b4bcd0] dark:hover:border-[#353d5a] group';
    row.draggable = true;
    row.dataset.idx = idx;
    row.innerHTML = `<div class="text-[#9aa0bb] dark:text-[#4e5675] text-lg font-bold group-hover:text-[#5b6380] dark:group-hover:text-[#8b93b5]">☰</div><div class="relative w-7 h-7 rounded-md overflow-hidden border border-[#dce0f0] dark:border-[#353d5a] shrink-0"><input type="color" value="${src.color}" data-srcid="${src.id}" onchange="updateSourceColor('${src.id}',this.value)" oninput="updateSourceColor('${src.id}',this.value)" class="absolute -inset-1 w-[calc(100%+8px)] h-[calc(100%+8px)] cursor-pointer p-0 border-none"></div><div class="flex-1 min-w-0"><div class="text-[13px] font-bold leading-tight truncate">${src.name}</div><div class="text-[10px] text-[#5b6380] dark:text-[#8b93b5]">${PLAT_LABEL[src.platform] || src.platform} &middot; ${src.bookings.length} reservations</div></div><div class="shrink-0 scale-75 transform origin-right">${PLAT_LOGO[src.platform + '_small'] || PLAT_LOGO[src.platform] || PLAT_LOGO.other_small}</div><button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-red-500 dark:hover:text-red-400 hover:bg-red-500/10 transition-all" onclick="deleteSource('${src.id}',event)" title="Delete source"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
    row.addEventListener('dragstart', e => { dragSrcIdx = idx; row.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
    row.addEventListener('dragend', () => { row.classList.remove('dragging'); });
    row.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; row.classList.add('drag-over'); });
    row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
    row.addEventListener('drop', e => { e.preventDefault(); e.stopPropagation(); const toIdx = parseInt(row.dataset.idx); if (dragSrcIdx === null || dragSrcIdx === toIdx) return; const moved = sources.splice(dragSrcIdx, 1)[0]; sources.splice(toIdx, 0, moved); dragSrcIdx = null; renderReorderList(); });
    el.appendChild(row);
  });
}

function updateSourceColor(id, color) { const src = sources.find(s => s.id === id); if (src) src.color = color; }
function applySettings() { refresh(); saveToServer(); closeSettings(); }
function openFilters() { renderSourceFilterList(); const modalBg = document.getElementById('filters-modal-bg'); if (modalBg) modalBg.classList.add('open'); }
function closeFilters() { const modalBg = document.getElementById('filters-modal-bg'); if (modalBg) modalBg.classList.remove('open'); }

function renderSourceFilterList() {
  const el = document.getElementById('source-filter-list');
  if (!el) return;
  const srcCount = document.getElementById('src-count-modal');
  if (srcCount) srcCount.textContent = sources.length;
  if (!sources.length) { el.innerHTML = '<div class="text-center py-4 text-[#9aa0bb] dark:text-[#4e5675] text-sm">No sources added yet.</div>'; return; }
  el.innerHTML = sources.map(src => `<div class="flex items-center justify-between p-2 bg-white dark:bg-[#181c27] border border-[#dce0f0] dark:border-[#2a3047] rounded-lg"><div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full" style="background:${src.color}"></div><span class="text-sm font-medium">${src.name}</span><span class="text-xs text-[#5b6380] dark:text-[#8b93b5]">${src.bookings.length}</span></div><div class="flex items-center gap-2"><button onclick="editSourceModal('${src.id}',event)" class="text-[#9aa0bb] hover:text-primary" title="Edit"><i data-lucide="edit-3" class="w-4 h-4"></i></button><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" class="sr-only peer" ${src.enabled ? 'checked' : ''} onchange="toggleSource('${src.id}',event)"><div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div></label></div></div>`).join('');
  lucide.createIcons();
}

function enableAllSources() { sources.forEach(src => src.enabled = true); refresh(); saveToServer(); renderSourceFilterList(); }
function disableAllSources() { sources.forEach(src => src.enabled = false); refresh(); saveToServer(); renderSourceFilterList(); }

/* ════════════════════════════════════════
   STYLES
════════════════════════════════════════ */
const dotStyles = `
  .status-indicator-bar { 
    position: absolute; 
    bottom: 2px; 
    right: 2px;
    display: flex;
    gap: 2px;
    z-index: 5;
    cursor: pointer;
    background: rgba(255, 255, 255, 0.85);
    padding: 2px 4px;
    border-radius: 10px;
    backdrop-filter: blur(2px);
  }
  
  .fc-daygrid-day-frame { 
    position: relative; 
    overflow: visible;
    min-height: 90px;
  }
  
  .fc-daygrid-day {
    min-height: 90px;
  }
  
  .fc-daygrid-day-number {
    position: relative;
    z-index: 6;
    background: rgba(255,255,255,0.8);
    border-radius: 20px;
    padding: 2px 6px;
    margin: 2px;
    display: inline-block;
    font-weight: 500;
  }
  
  .dark .fc-daygrid-day-number {
    background: rgba(0,0,0,0.6);
    color: #e5e7eb;
  }
  
  .fc-daygrid-day-top {
    position: relative;
    z-index: 6;
  }
  
  .fc-daygrid-day-events {
    position: relative;
    z-index: 6;
  }
  
  .fc-event {
    border-radius: 4px !important;
    margin: 2px 4px !important;
    font-size: 11px !important;
    cursor: pointer;
  }
  
  .fc-event-title {
    font-weight: 500 !important;
    padding: 2px 4px !important;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: scale(0.9);
    }
    to {
      opacity: 1;
      transform: scale(1);
    }
  }
  
  .status-indicator-bar {
    animation: fadeIn 0.15s ease-out;
  }
`;

const styleSheet = document.createElement("style");
styleSheet.textContent = dotStyles;
document.head.appendChild(styleSheet);

/* ════════════════════════════════════════
   INIT
════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();
  const calEl = document.getElementById('calendar');
  if (calEl) {
    calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth',
      headerToolbar: { left: 'prev', center: 'title', right: 'next' },
      height: 'auto',
      fixedWeekCount: false,
      showNonCurrentDates: false,
      events: [],
      eventOrder: 'order,title',
      eventContent: (arg) => {
        if (arg.event.extendedProps.isAvailable) return { html: '' };
        const b = arg.event.extendedProps;
        const src = sources.find(s => s.id === b.srcId);
        const plat = src?.platform || 'other';
        const isOv = b.isOverlap;
        const getLogoHtml = (platform) => {
          const wrapStart = '<div style="width:26px;height:26px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;">';
          const wrapEnd = '</div>';
          switch (platform) {
            case 'airbnb': return wrapStart + '<img src="assets/img/black-airbnb.png" style="width:16px;height:16px;object-fit:contain;" alt="Airbnb">' + wrapEnd;
            case 'vrbo': return wrapStart + '<img src="assets/img/logo-new.png" style="width:25px;height:25px;object-fit:contain;" alt="Vrbo">' + wrapEnd;
            case 'booking': return wrapStart + '<img src="assets/img/logo-booking.png" style="width:16px;height:16px;object-fit:contain;" alt="Booking">' + wrapEnd;
            default: return wrapStart + '<i data-lucide="calendar" style="width:16px;height:16px;color:#000;"></i>' + wrapEnd;
          }
        };
        let displayText = src?.name || 'Unknown';
        if (b.guest && b.guest !== 'Guest' && !b.guestNameMissing) displayText += ` (${b.guest})`;
        return { html: `<div class="flex items-center gap-1.5 w-full px-1.5 py-1 ${isOv ? 'bg-overlap/30' : ''}" style="color:#fff; min-height:34px;"><span class="flex items-center justify-center shrink-0" style="width:26px; height:26px;">${getLogoHtml(plat)}</span><span class="flex-1 text-[11px] sm:text-[12px] font-medium leading-tight truncate" style="color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${displayText}</span>${isOv ? `<span class="flex items-center justify-center shrink-0 ml-1"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-white drop-shadow-sm"></i></span>` : ''}</div>` };
      },
      eventDidMount: (info) => { lucide.createIcons(); },
      eventMouseEnter: ({ event, jsEvent }) => showTip(event, jsEvent),
      eventMouseLeave: hideTip,
      eventClick: ({ event }) => { hideTip(); const b = event.extendedProps; if (b.isAvailable) return; openEditModal(b.id, b.srcId); },
      dayCellDidMount: ({ date, el }) => { addDateStatusIndicator(el, date); el.addEventListener('mouseleave', () => hideTip()); },
      datesSet: () => { setTimeout(() => refreshDateIndicators(), 50); },
      viewDidMount: () => { setTimeout(() => refreshDateIndicators(), 50); }
    });
    calendar.render();
    loadFromServer().then(() => { if (sources.length > 0) { const shouldAutoRefresh = !lastRefreshTime || (new Date() - lastRefreshTime) > (60 * 60 * 1000); if (shouldAutoRefresh) refreshAllSources(); } setTimeout(() => refreshDateIndicators(), 100); });
  }
});

const addModalBg = document.getElementById('add-modal-bg');
if (addModalBg) addModalBg.addEventListener('click', e => { if (e.target === addModalBg) closeAddModal(); });
const settingsModalBg = document.getElementById('settings-modal-bg');
if (settingsModalBg) settingsModalBg.addEventListener('click', e => { if (e.target === settingsModalBg) closeSettings(); });
const editModalBg = document.getElementById('edit-modal-bg');
if (editModalBg) editModalBg.addEventListener('click', e => { if (e.target === editModalBg) closeEditModal(); });
const editSourceModalBg = document.getElementById('edit-source-modal-bg');
if (editSourceModalBg) editSourceModalBg.addEventListener('click', e => { if (e.target === editSourceModalBg) closeEditSourceModal(); });
const filtersModalBg = document.getElementById('filters-modal-bg');
if (filtersModalBg) filtersModalBg.addEventListener('click', e => { if (e.target === filtersModalBg) closeFilters(); });

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeEditModal(); closeAddModal(); closeSettings(); closeEditSourceModal(); closeFilters(); } });
document.getElementById('edit-guest')?.addEventListener('keydown', e => { if (e.key === 'Enter') saveEditBooking(); });
document.getElementById('edit-source-name-input')?.addEventListener('keydown', e => { if (e.key === 'Enter') saveSourceEdit(); });

window.checkDateStatus = checkDateStatus;
window.checkAllDatesInMonth = checkAllDatesInMonth;
window.toggleAvailableDatesQuick = toggleAvailableDatesQuick;

function setupCalendarNavigation() {
  setTimeout(() => {
    const prevButton = document.querySelector('.fc-prev-button');
    const nextButton = document.querySelector('.fc-next-button');
    const todayButton = document.querySelector('.fc-today-button');
    if (prevButton && !prevButton.hasAttribute('data-listener')) { prevButton.setAttribute('data-listener', 'true'); prevButton.addEventListener('click', () => { setTimeout(() => refreshDateIndicators(), 100); }); }
    if (nextButton && !nextButton.hasAttribute('data-listener')) { nextButton.setAttribute('data-listener', 'true'); nextButton.addEventListener('click', () => { setTimeout(() => refreshDateIndicators(), 100); }); }
    if (todayButton && !todayButton.hasAttribute('data-listener')) { todayButton.setAttribute('data-listener', 'true'); todayButton.addEventListener('click', () => { setTimeout(() => refreshDateIndicators(), 100); }); }
  }, 500);
}

const originalRefresh = refresh;
refresh = function() { originalRefresh(); setTimeout(() => refreshDateIndicators(), 100); };
const originalUpdateCalendar = updateCalendar;
updateCalendar = function() { originalUpdateCalendar(); setTimeout(() => refreshDateIndicators(), 100); };

window.addEventListener('load', () => {
  setupCalendarNavigation();
  const observer = new MutationObserver((mutations) => { mutations.forEach((mutation) => { if (mutation.type === 'childList') { const dayCells = document.querySelectorAll('.fc-daygrid-day'); if (dayCells.length > 0 && !document.querySelector('.status-indicator-bar')) refreshDateIndicators(); } }); });
  const calendarEl = document.getElementById('calendar');
  if (calendarEl) observer.observe(calendarEl, { childList: true, subtree: true });
});