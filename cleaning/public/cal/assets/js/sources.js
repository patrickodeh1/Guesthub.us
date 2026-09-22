/* ════════════════════════════════════════
   DATA
════════════════════════════════════════ */
const PLAT_LABEL = { airbnb: 'Airbnb', vrbo: 'VRBO', booking: 'Booking.com', other: 'Other' };
const PLAT_LOGO = {
  airbnb: '<div style="width:34px;height:34px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/black-airbnb.png" style="width:28px;height:28px;object-fit:contain;" alt="Airbnb"></div>',

  airbnb_small: '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/black-airbnb.png" style="width:20px;height:20px;object-fit:contain;" alt="Airbnb"></div>',

  vrbo: '<div style="width:34px;height:34px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/logo-new.png" style="width:40px;height:40px;object-fit:contain;" alt="VRBO"></div>',

  vrbo_small: '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/logo-new.png" style="width:20px;height:20px;object-fit:contain;" alt="VRBO"></div>',

  booking: '<div style="width:34px;height:34px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/logo-booking.png" style="width:40px;height:40px;object-fit:contain;" alt="Booking.com"></div>',

  booking_small: '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><img src="assets/img/logo-booking.png" style="width:20px;height:20px;object-fit:contain;" alt="Booking.com"></div>',

  other: '<div style="width:34px;height:34px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><i data-lucide="calendar" style="width:18px;height:18px;color:#fff;"></i></div>',

  other_small: '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;"><i data-lucide="calendar" style="width:14px;height:14px;color:#000;"></i></div>'
};

const PRESET_COLORS = [
  '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
  '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#f43f5e'
];

// Sources: { id, name, platform, color, enabled, url, bookings:[] }
let sources = [];
let editingBookingId = null, editingSrcId = null;
let editingSourceId = null;
let contextMenuBooking = null;
let lastRefreshTime = null;
let dragSrcIdx = null;
const tip = document.getElementById('tooltip');
let tipT;

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
    {
      name: "US Holidays",
      url: "https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics",
      platform: "other"
    },
    {
      name: "UK Holidays",
      url: "https://calendar.google.com/calendar/ical/en.uk%23holiday%40group.v.calendar.google.com/public/basic.ics",
      platform: "other"
    },
    {
      name: "Canadian Holidays",
      url: "https://calendar.google.com/calendar/ical/en.canadian%23holiday%40group.v.calendar.google.com/public/basic.ics",
      platform: "other"
    },
    {
      name: "Premier League Fixtures",
      url: "https://www.calendarlabs.com/ical-calendar/ics/47/Premier_League_Fixtures.ics",
      platform: "other"
    },
    {
      name: "NBA Schedule",
      url: "https://www.calendarlabs.com/ical-calendar/ics/105/NBA_Schedule.ics",
      platform: "other"
    },
    {
      name: "Moon Phases",
      url: "https://www.calendarlabs.com/ical-calendar/ics/76/Moon_Phases.ics",
      platform: "other"
    }
  ];
  return demoUrls[Math.floor(Math.random() * demoUrls.length)];
}

function getMockAirbnbUrl(propertyName = "Beach House") {
  const mockIds = ["12345678", "87654321", "56781234", "43218765", "98765432"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '-');
  return {
    name: `${propertyName} (Airbnb)`,
    url: `https://www.airbnb.com/calendar/ical/${randomId}.ics?locale=en&calendar_name=${propertySlug}`,
    platform: "airbnb"
  };
}

function getMockVrboUrl(propertyName = "Lake Cabin") {
  const mockIds = ["123456", "654321", "789012", "210987", "345678"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '');
  return {
    name: `${propertyName} (VRBO)`,
    url: `https://www.vrbo.com/icalendar/${randomId}/calendar.ics?property=${propertySlug}`,
    platform: "vrbo"
  };
}

function getMockBookingUrl(propertyName = "City Apartment") {
  const mockIds = ["prop_123456", "prop_789012", "prop_345678", "prop_901234"];
  const randomId = mockIds[Math.floor(Math.random() * mockIds.length)];
  const propertySlug = propertyName.toLowerCase().replace(/\s+/g, '-');
  return {
    name: `${propertyName} (Booking.com)`,
    url: `https://calendar.booking.com/ical/${randomId}/calendar.ics?name=${propertySlug}`,
    platform: "booking"
  };
}

function getRandomCalendar() {
  const types = ['public', 'airbnb', 'vrbo', 'booking'];
  const type = types[Math.floor(Math.random() * types.length)];
  
  const propertyNames = [
    "Beachfront Villa", "Mountain Retreat", "City Loft", "Lake House",
    "Downtown Apartment", "Cozy Cabin", "Luxury Condo", "Garden Studio",
    "Penthouse Suite", "Country Cottage", "Ocean View", "Ski Chalet"
  ];
  
  const randomName = propertyNames[Math.floor(Math.random() * propertyNames.length)];
  
  switch(type) {
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
  
  if (typeof showToast === 'function') {
    showToast(`Random ${random.platform} calendar generated!`, 'info');
  }
}

function generateRandomCalendars(count = 3) {
  for (let i = 0; i < count; i++) {
    setTimeout(() => {
      fillWithRandomICal();
    }, i * 100);
  }
}

/* ════════════════════════════════════════
   iCAL PARSER
════════════════════════════════════════ */
function parseIcal(text, srcId, platform = 'other') {
  text = text.replace(/\r\n[ \t]/g, '').replace(/\r\n/g, '\n');
  const blocks = text.match(/BEGIN:VEVENT[\s\S]*?END:VEVENT/g) || [];
  
  return blocks.reduce((acc, block, i) => {
    const g = k => { 
      const m = block.match(new RegExp(`${k}[^:\\n]*:([^\\n]+)`)); 
      return m ? m[1].trim() : ''; 
    };
    
    const summary = g('SUMMARY');
    const description = g('DESCRIPTION');
    
    // Skip blocked/unavailable dates
    if (/block|unavailable|not\s+available|closed|hold/i.test(summary)) return acc;
    
    // Parse dates
    const td = r => { 
      const c = r.replace(/[TZ].*/, ''); 
      return c.length >= 8 ? `${c.slice(0, 4)}-${c.slice(4, 6)}-${c.slice(6, 8)}` : ''; 
    };
    
    const start = td(g('DTSTART'));
    const end = td(g('DTEND'));
    
    if (!start || !end) return acc;
    
    // Extract guest name
    let guest = 'Guest';
    let guestNameMissing = true;
    
    const removePattern = /reserved|reservation|airbnb|vrbo|booking\.com|booking|homeaway|confirmed|booked|blocked|unavailable|new|guest/i;
    
    // Try SUMMARY first
    let extractedGuest = summary.replace(removePattern, '').trim();
    extractedGuest = extractedGuest.replace(/^[-–—\s]+|[-–—\s]+$/g, '');
    
    // If SUMMARY doesn't yield a good name, try DESCRIPTION
    if ((!extractedGuest || extractedGuest.length < 2 || extractedGuest === 'Guest') && description) {
      extractedGuest = description.replace(removePattern, '').trim();
      extractedGuest = extractedGuest.replace(/^[-–—\s]+|[-–—\s]+$/g, '');
    }
    
    // If we found a valid guest name
    if (extractedGuest && extractedGuest.length >= 2 && extractedGuest !== 'Guest' && !extractedGuest.match(/^\d+$/)) {
      guest = extractedGuest;
      guestNameMissing = false;
    }
    
    // Create unique ID
    const uid = g('UID') || `${platform}-${i}-${start}-${end}`;
    
    acc.push({ 
      id: `${srcId}-${i}-${Date.now()}`, 
      srcId, 
      guest, 
      start, 
      end, 
      guestNameMissing,
      platform 
    });
    
    return acc;
  }, []);
}

/* ════════════════════════════════════════
   OVERLAP DETECTION
════════════════════════════════════════ */
function allVisible() {
  return sources.filter(s => s.enabled).flatMap(s => s.bookings);
}

function detectOverlaps(bookings) {
  const out = [];
  for (let i = 0; i < bookings.length; i++)
    for (let j = i + 1; j < bookings.length; j++) {
      const a = bookings[i], b = bookings[j];
      if (a.srcId === b.srcId) continue;
      if (new Date(a.start) < new Date(b.end) && new Date(b.start) < new Date(a.end))
        out.push({ a, b });
    }
  return out;
}

/* ════════════════════════════════════════
   REFRESH ALL
════════════════════════════════════════ */
function refresh() {
  renderSidebar();
  renderLegend();
  updateStats();
  updateList();
  updateUIVisibility();
  updateRefreshButtonState();
}

/* ════════════════════════════════════════
   UI VISIBILITY - Updated for new layout
════════════════════════════════════════ */
function updateUIVisibility() {
  const hasSources = sources.length > 0;
  
  const statsSection = document.getElementById('stats-section');
  const legendSection = document.getElementById('legend-section');
  const bookingSection = document.getElementById('booking-section');
  const noBookingsMessage = document.getElementById('no-bookings-message');
  
  if (hasSources) {
    statsSection?.classList.remove('hidden');
    legendSection?.classList.remove('hidden');
    bookingSection?.classList.remove('hidden');
  } else {
    statsSection?.classList.add('hidden');
    legendSection?.classList.add('hidden');
    bookingSection?.classList.add('hidden');
  }
}

/* ════════════════════════════════════════
   SIDEBAR SOURCE LIST
════════════════════════════════════════ */
function renderSidebar() {
  const el = document.getElementById('source-list');
  if (!el) return;
  const srcCount = document.getElementById('src-count');
  if (srcCount) {
    srcCount.textContent = `${sources.length} source${sources.length !== 1 ? 's' : ''}`;
  }
  if (!sources.length) {
    el.innerHTML = '<div class="text-center py-5 text-[#9aa0bb] dark:text-[#4e5675] text-xs">No sources yet.<br>Click <strong>+ Add iCal</strong> to start.</div>';
    return;
  }
  el.innerHTML = '';

  sources.forEach(src => {
    const row = document.createElement('div');
    row.className = `flex items-center gap-2.5 p-2.5 rounded-xl border-2 transition-all cursor-default ${src.enabled ? 'border-transparent bg-primary/5 dark:bg-primary/10' : 'border-transparent bg-[#f4f6fc] dark:bg-[#1f2436]'}`;
    if (src.enabled) row.style.borderColor = src.color + '40';

    row.innerHTML = `
      <div class="w-2.5 h-2.5 rounded-full shrink-0 shadow-[0_0_0_2px_rgba(255,255,255,0.1)]" style="background:${src.color}"></div>
      <div class="flex-1 min-w-0">
        <div class="text-[12px] font-semibold leading-tight truncate">${src.name}</div>
        <div class="text-[10px] text-[#5b6380] dark:text-[#8b93b5]">${PLAT_LABEL[src.platform] || src.platform} &middot; ${src.bookings.length} reservations</div>
      </div>
      <div class="shrink-0 opacity-90">${PLAT_LOGO[src.platform] || PLAT_LOGO.other}</div>
      <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-primary dark:hover:text-primary hover:bg-primary/10 transition-all" onclick="editSourceModal('${src.id}',event)" title="Edit source">
        <i data-lucide="edit-3" class="w-4 h-4" style="pointer-events:none"></i>
      </button>
      <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-red-500 dark:hover:text-red-400 hover:bg-red-500/10 transition-all" onclick="deleteSource('${src.id}',event)" title="Delete source">
        <i data-lucide="trash-2" class="w-4 h-4" style="pointer-events:none"></i>
      </button>     
    `;
    el.appendChild(row);
  });
  lucide.createIcons();
}

function toggleSource(id, e) {
  e.stopPropagation();
  const src = sources.find(s => s.id === id);
  if (src) { src.enabled = !src.enabled; refresh(); saveToServer(); }
}

function deleteSource(id, e) {
  e.stopPropagation();
  if (!confirm('Delete this iCal source and all its bookings?')) return;
  sources = sources.filter(s => s.id !== id);
  refresh();
  saveToServer();
  const settingsModal = document.getElementById('settings-modal-bg');
  if (settingsModal && settingsModal.classList.contains('open')) {
    closeSettings();
  }
}

function clearAllSources() {
  if (sources.length === 0) return;
  if (!confirm('Delete ALL iCal sources and bookings? This cannot be undone.')) return;
  sources = [];
  refresh();
  saveToServer();
}

/* ════════════════════════════════════════
   REFRESH ALL SOURCES
════════════════════════════════════════ */
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
    if (!lastRefreshTime) {
      lastUpdatedEl.textContent = 'Never synced';
    } else {
      const diff = Math.floor((Date.now() - lastRefreshTime) / 1000);
      let t = diff < 60 ? 'just now'
            : diff < 3600 ? `${Math.floor(diff / 60)} min ago`
            : diff < 86400 ? `${Math.floor(diff / 3600)}h ago`
            : `${Math.floor(diff / 86400)}d ago`;
      lastUpdatedEl.textContent = `Synced ${t}`;
    }
  }
}

async function refreshSingleSource(src) {
  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(src.url)}&platform=${src.platform}&unit=${encodeURIComponent(src.name)}`;
    const r = await fetch(proxyUrl);
    const data = await r.json();

    if (data.success && data.events) {
      const existingBookingsMap = new Map();
      src.bookings.forEach(bk => existingBookingsMap.set(bk.id, bk));

      const updatedBookings = data.events.map(ev => {
        const existing = existingBookingsMap.get(ev.id);
        
        if (existing) {
          const wasCustomized = existing.guest !== 'Guest' && !existing.guestNameMissing;
          
          return {
            ...ev,
            srcId: src.id,
            guest: wasCustomized ? existing.guest : ev.guest,
            guestNameMissing: wasCustomized ? false : (ev.guest === 'Guest'),
            avatarUrl: existing.avatarUrl || null,
            notes: existing.notes || null,
            lastEdited: existing.lastEdited || null
          };
        }
        
        const guestNameMissing = (ev.guest === 'Guest');
        
        return {
          ...ev,
          srcId: src.id,
          guestNameMissing,
          avatarUrl: null,
          notes: null,
          lastEdited: null
        };
      });
      
      return { success: true, bookings: updatedBookings };
    }
    
    return { success: false, error: data.error || 'Failed to parse iCal' };
  } catch (err) {
    return { success: false, error: err.message };
  }
}

async function refreshAllSources() {
  if (sources.length === 0) return;

  const refreshBtn = document.getElementById('refresh-btn');
  const refreshIcon = document.getElementById('refresh-icon');
  const refreshLabel = document.getElementById('refresh-label');
  const refreshStatus = document.getElementById('refresh-status');

  if (refreshBtn) { refreshBtn.disabled = true; refreshBtn.classList.add('opacity-60', 'pointer-events-none'); }
  if (refreshIcon) refreshIcon.classList.add('animate-spin');
  if (refreshLabel) refreshLabel.textContent = 'Syncing…';
  if (refreshStatus) refreshStatus.classList.remove('hidden');

  let successCount = 0, errorCount = 0;

  for (const src of sources) {
    try {
      const result = await refreshSingleSource(src);
      if (result.success) { src.bookings = result.bookings; successCount++; }
      else { errorCount++; console.warn(`Failed to refresh ${src.name}:`, result.error); }
    } catch (err) {
      errorCount++;
      console.warn(`Error refreshing ${src.name}:`, err);
    }
  }

  lastRefreshTime = new Date();
  refresh();
  updateRefreshButtonState();
  await saveToServer();

  if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.classList.remove('opacity-60', 'pointer-events-none'); }
  if (refreshIcon) refreshIcon.classList.remove('animate-spin');
  if (refreshLabel) refreshLabel.textContent = 'Refresh All Calendars';
  if (refreshStatus) refreshStatus.classList.add('hidden');

  if (errorCount > 0) {
    // alert(`Refreshed ${successCount} calendar${successCount !== 1 ? 's' : ''}. ${errorCount} failed — check the iCal URLs.`);
  }
}

/* ════════════════════════════════════════
   LEGEND
════════════════════════════════════════ */
function renderLegend() {
  const el = document.getElementById('legend-list');
  if (!el) return;
  if (!sources.length) {
    el.innerHTML = '<div class="text-xs text-[#9aa0bb] dark:text-[#4e5675]">Add sources to see legend</div>';
    return;
  }
  el.innerHTML = '';
  sources.forEach(src => {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2.5 text-xs font-medium text-[#5b6380] dark:text-[#8b93b5]';
    row.innerHTML = `
      <div class="w-3.5 h-3.5 rounded shrink-0" style="background:${src.color}"></div>
      <span class="flex-1 truncate">${src.name}</span>
      <div class="shrink-0">${PLAT_LOGO[src.platform + '_small'] || PLAT_LOGO[src.platform] || PLAT_LOGO.other_small}</div>
    `;
    el.appendChild(row);
  });
  lucide.createIcons();
}

/* ════════════════════════════════════════
   STATS
════════════════════════════════════════ */
function updateStats() {
  const now = new Date(), y = now.getFullYear(), mo = now.getMonth();
  const mS = new Date(y, mo, 1), mE = new Date(y, mo + 1, 1);
  const vis = allVisible();
  const month = vis.filter(b => new Date(b.start) < mE && new Date(b.end) > mS);

  const statTotal = document.getElementById('stat-total');
  const statOverlap = document.getElementById('stat-overlap');
  const statNights = document.getElementById('stat-nights');
  const statAvail = document.getElementById('stat-avail');
  
  // Footer stats
  const footerTotal = document.getElementById('footer-total');
  const footerNights = document.getElementById('footer-nights');
  const footerOverlaps = document.getElementById('footer-overlaps');

  if (statTotal) statTotal.textContent = month.length;
  if (statOverlap) statOverlap.textContent = detectOverlaps(month).length;
  if (footerTotal) footerTotal.textContent = month.length;
  if (footerOverlaps) footerOverlaps.textContent = detectOverlaps(month).length;

  const days = new Set();
  month.forEach(b => { 
    let d = new Date(b.start); 
    while (d < new Date(b.end)) { 
      days.add(d.toISOString().split('T')[0]); 
      d.setDate(d.getDate() + 1); 
    } 
  });

  if (statNights) statNights.textContent = days.size;
  if (footerNights) footerNights.textContent = days.size;
  if (statAvail) statAvail.textContent = Math.max(0, new Date(y, mo + 1, 0).getDate() - days.size);
}

/* ════════════════════════════════════════
   BOOKING LIST - Main content for right panel
════════════════════════════════════════ */
function updateList() {
  const vis = allVisible();
  const ovPairs = detectOverlaps(vis);
  const ovIds = new Set();
  ovPairs.forEach(({ a, b }) => { ovIds.add(a.id); ovIds.add(b.id); });

  const ovEl = document.getElementById('ov-list');
  const badge = document.getElementById('ov-badge');
  const noBookingsMessage = document.getElementById('no-bookings-message');
  
  // Handle overlap alerts
  if (ovEl) {
    ovEl.innerHTML = '';
    if (ovPairs.length) {
      if (badge) badge.classList.remove('hidden');
      ovPairs.forEach(({ a, b }) => {
        const srcA = sources.find(s => s.id === a.srcId);
        const srcB = sources.find(s => s.id === b.srcId);
        const el = document.createElement('div');
        el.className = 'flex items-start gap-4 p-4 bg-overlap/10 border-2 border-overlap/25 rounded-2xl text-[13px] text-overlap leading-relaxed mb-3 shadow-sm';

        const renderClash = (bk, src) => {
          return `
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full shrink-0 overflow-hidden flex items-center justify-center bg-white" style="border: 2px solid ${src?.color}">
              ${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-bold text-sm">${src?.name || 'Unknown'}</div>
              <div class="text-[11px] opacity-80 font-medium">
                ${fmtDate(bk.start)} — ${fmtDate(bk.end)}
              </div>
            </div>
          </div>
        `;
        }

        el.innerHTML = `
          <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5 animate-pulse"></i>
          <div class="flex flex-col gap-3 flex-1">
            <strong class="text-sm font-black uppercase tracking-tight">Double Booking Conflict</strong>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
              ${renderClash(a, srcA)}
              <div class="hidden sm:block text-center opacity-30 font-bold text-[10px]">VS</div>
              ${renderClash(b, srcB)}
            </div>
          </div>`;
        ovEl.appendChild(el);
      });
    } else {
      if (badge) badge.classList.add('hidden');
    }
  }

  // Main booking list
  const listEl = document.getElementById('booking-list');
  if (listEl) {
    listEl.innerHTML = '';

    const upcoming = [...vis]
      .filter(b => new Date(b.end) >= new Date())
      .sort((a, b) => new Date(a.start) - new Date(b.start));

    if (!upcoming.length) {
      listEl.innerHTML = '';
      if (noBookingsMessage) noBookingsMessage.classList.remove('hidden');
      return;
    } else {
      if (noBookingsMessage) noBookingsMessage.classList.add('hidden');
    }

    upcoming.forEach(b => {
      const src = sources.find(s => s.id === b.srcId);
      const isOv = ovIds.has(b.id);
      const color = src?.color || '#888';
      const div = document.createElement('div');
      div.className = `flex items-center gap-3.5 p-3.5 bg-white dark:bg-[#181c27] border-2 border-[#dce0f0] dark:border-[#2a3047] rounded-xl hover:border-[#b4bcd0] dark:hover:border-[#353d5a] transition-all relative overflow-hidden group cursor-pointer`;
      
      div.dataset.bookingId = b.id;
      div.dataset.srcId = b.srcId;

      div.onclick = (e) => {
        if (e.target.closest('.edit-booking-btn')) return;
        e.stopPropagation();
        openEditModal(b.id, b.srcId);
      };

      div.oncontextmenu = (e) => {
        e.preventDefault();
        showContextMenu(e, b.id, b.srcId);
      };

      div.innerHTML = `
        <div class="absolute left-0 top-0 bottom-0 w-1" style="background:${color}"></div>
        <div class="w-8 h-8 rounded-full shrink-0 shadow-sm overflow-hidden flex items-center justify-center bg-white" style="border: 2px solid ${color}">
          ${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="text-[13px] font-bold leading-tight truncate">
              ${src?.name || ''}
            </span>
            ${b.guest && b.guest !== 'Guest' && !b.guestNameMissing 
              ? `<span class="text-[11px] text-[#5b6380] dark:text-[#8b93b5]">(${b.guest})</span>` 
              : b.guestNameMissing && src?.platform === 'airbnb'
                ? '<span class="text-[11px] text-amber-500 italic">(No guest name)</span>'
                : ''}
            ${isOv ? '<i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-overlap ml-1" style="pointer-events:none"></i>' : ''}
          </div>
          <div class="text-[11px] text-[#5b6380] dark:text-[#8b93b5] mt-0.5 flex items-center gap-1.5 font-medium">
            <i data-lucide="calendar" class="w-3.5 h-3.5" style="pointer-events:none"></i> ${fmtDate(b.start)} → ${fmtDate(b.end)} &middot; ${nts(b.start, b.end)} nights
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button class="edit-booking-btn w-8 h-8 rounded-lg flex items-center justify-center ${b.guestNameMissing ? 'text-amber-500 bg-amber-500/10 hover:bg-amber-500/20' : 'text-primary bg-primary/10 hover:bg-primary/20'} transition-all" onclick="event.stopPropagation(); openEditModal('${b.id}', '${b.srcId}')" title="${b.guestNameMissing ? 'Add guest name' : 'Edit booking'}">
            <i data-lucide="${b.guestNameMissing ? 'user-plus' : 'edit-3'}" class="w-4 h-4"></i>
          </button>
          <span class="text-[10px] font-bold tracking-wider px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm border border-transparent" style="background:${color}15; color:${color}; border-color:${color}20">
            <span class="scale-75 origin-center">${PLAT_LOGO[src?.platform + '_small'] || PLAT_LOGO[src?.platform] || PLAT_LOGO.other_small}</span>
            ${PLAT_LABEL[src?.platform] || 'Other'}
          </span>
        </div>
      `;
      listEl.appendChild(div);
    });
    lucide.createIcons();
  }
}

function fmtDate(str) { 
  return new Date(str + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); 
}

function nts(s, e) { 
  return Math.round((new Date(e) - new Date(s)) / 864e5); 
}

/* ════════════════════════════════════════
   TOOLTIP - No avatar
════════════════════════════════════════ */
function showTip(event, jsEvent) {
  if (!tip) return;
  clearTimeout(tipT);
  const b = event.extendedProps;
  const src = sources.find(s => s.id === b.srcId);
  const color = src?.color || '#888';
  const plat = src?.platform || 'other';
  const guestName = b.guest && b.guest !== 'Guest' && !b.guestNameMissing ? b.guest : '';

  document.getElementById('tip-dot').style.background = color;
  document.getElementById('tip-plat-name').textContent = src?.name || PLAT_LABEL[plat];
  document.getElementById('tip-plat-logo').innerHTML = PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small;
  
  const tipGuest = document.getElementById('tip-guest');
  if (guestName) {
    tipGuest.textContent = `Guest: ${guestName}`;
    tipGuest.className = 'text-sm font-bold mb-1';
  } else {
    tipGuest.textContent = plat === 'airbnb' ? 'No guest name' : 'Guest not available';
    tipGuest.className = 'text-sm font-bold mb-1 text-amber-600 dark:text-amber-400 italic';
  }
  
  document.getElementById('tip-unit').textContent = src?.name || '';
  document.getElementById('tip-dates').textContent = `${fmtDate(b.start)} → ${fmtDate(b.end)}`;
  document.getElementById('tip-nights').textContent = `🌙 ${nts(b.start, b.end)} nights`;
  
  const tipOv = document.getElementById('tip-ov');
  if (b.isOverlap) tipOv?.classList.remove('hidden');
  else tipOv?.classList.add('hidden');

  const x = jsEvent.clientX, y = jsEvent.clientY;
  tip.style.left = (x + 16) + 'px';
  tip.style.top = (y - 8) + 'px';
  tip.classList.remove('opacity-0', 'translate-y-1.5');
  tip.classList.add('opacity-100', 'translate-y-0');
}

function hideTip() {
  if (tip) tipT = setTimeout(() => {
    tip.classList.add('opacity-0', 'translate-y-1.5');
    tip.classList.remove('opacity-100', 'translate-y-0');
  }, 180);
}

/* ════════════════════════════════════════
   ADD MODAL
════════════════════════════════════════ */
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

function closeAddModal() {
  const modalBg = document.getElementById('add-modal-bg');
  if (modalBg) modalBg.classList.remove('open');
}

/* ════════════════════════════════════════
   PERSISTENT STORAGE
════════════════════════════════════════ */
async function loadFromServer() {
  try {
    const r = await fetch('data.php');
    const data = await r.json();
    if (Array.isArray(data.sources) && data.sources.length) {
      sources = data.sources;
      sources.forEach(src => {
        if (Array.isArray(src.bookings)) {
          src.bookings.forEach(bk => {
            if (bk.guestNameMissing === undefined) {
              bk.guestNameMissing = !bk.guest || bk.guest === 'Guest';
            }
          });
        }
      });
    }
    if (data.lastRefreshTime) {
      lastRefreshTime = new Date(data.lastRefreshTime);
    }
  } catch (e) {
    console.warn('Could not load saved data:', e);
  }
  refresh();
}

async function saveToServer() {
  try {
    await fetch('data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sources,
        lastRefreshTime: lastRefreshTime ? lastRefreshTime.toISOString() : null
      })
    });
  } catch (e) {
    console.warn('Could not save data:', e);
  }
}

/* ════════════════════════════════════════
   EDIT SOURCE MODAL FUNCTIONS
════════════════════════════════════════ */
function editSourceModal(sourceId, event) {
  event.stopPropagation();
  
  const src = sources.find(s => s.id === sourceId);
  if (!src) return;
  
  editingSourceId = sourceId;
  
  document.getElementById('edit-source-name-input').value = src.name;
  document.getElementById('edit-source-url-input').value = src.url;
  document.getElementById('edit-source-color-input').value = src.color;
  document.getElementById('edit-source-id').value = sourceId;
  
  const modalBg = document.getElementById('edit-source-modal-bg');
  if (modalBg) {
    modalBg.classList.add('open');
    setTimeout(() => {
      document.getElementById('edit-source-name-input')?.focus();
      lucide.createIcons();
    }, 80);
  }
}

function closeEditSourceModal() {
  document.getElementById('edit-source-modal-bg')?.classList.remove('open');
  editingSourceId = null;
}

function saveSourceEdit() {
  const newName = document.getElementById('edit-source-name-input')?.value.trim();
  const newUrl = document.getElementById('edit-source-url-input')?.value.trim();
  const newColor = document.getElementById('edit-source-color-input')?.value;
  const sourceId = document.getElementById('edit-source-id')?.value;
  
  if (!newName) {
    showToast('Please enter a source name', 'error');
    return;
  }
  
  if (!newUrl) {
    showToast('Please enter an iCal URL', 'error');
    return;
  }
  
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.name = newName;
    src.url = newUrl;
    src.color = newColor;
    
    refreshAllSources();
    showToast('Source updated successfully', 'success');
    closeEditSourceModal();
  }
}

/* ════════════════════════════════════════
   EDIT BOOKING FUNCTIONS
════════════════════════════════════════ */
function openEditModal(bookingId, srcId) {
  editingBookingId = bookingId;
  editingSrcId = srcId;

  const src = sources.find(s => s.id === srcId);
  const bk = src?.bookings.find(b => b.id === bookingId);
  
  if (!bk) {
    showToast('Booking not found', 'error');
    return;
  }

  const noGuestName = bk.guestNameMissing || bk.guest === 'Guest';
  
  document.getElementById('edit-guest').value = noGuestName ? '' : bk.guest;
  document.getElementById('edit-start').value = bk.start;
  document.getElementById('edit-end').value = bk.end;
  document.getElementById('edit-notes').value = bk.notes || '';
  document.getElementById('edit-dates').textContent = `${fmtDate(bk.start)} → ${fmtDate(bk.end)}  ·  ${nts(bk.start, bk.end)} nights`;
  document.getElementById('edit-source-name').textContent = src?.name || '';
  document.getElementById('edit-modal-title').textContent = noGuestName ? 'Add Guest Name' : 'Edit Booking Details';
  
  const plat = src?.platform || 'other';
  document.getElementById('edit-platform-badge').innerHTML = `${PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small} ${PLAT_LABEL[plat] || 'Other'}`;

  const modalBg = document.getElementById('edit-modal-bg');
  if (modalBg) {
    modalBg.classList.add('open');
    setTimeout(() => {
      document.getElementById('edit-guest')?.focus();
      lucide.createIcons();
    }, 80);
  }
}

function closeEditModal() {
  document.getElementById('edit-modal-bg')?.classList.remove('open');
  editingBookingId = null;
  editingSrcId = null;
}

async function saveEditBooking() {
  const guest = document.getElementById('edit-guest')?.value.trim();
  const start = document.getElementById('edit-start')?.value;
  const end = document.getElementById('edit-end')?.value;
  const notes = document.getElementById('edit-notes')?.value.trim();

  if (!guest) { 
    alert('Please enter a guest name.'); 
    return; 
  }

  if (start && end && new Date(start) >= new Date(end)) {
    alert('Check-out date must be after check-in date');
    return;
  }

  const src = sources.find(s => s.id === editingSrcId);
  const bk = src?.bookings.find(b => b.id === editingBookingId);
  
  if (bk) {
    bk.guest = guest;
    bk.guestNameMissing = false;
    
    if (start && end) {
      bk.start = start;
      bk.end = end;
    }
    
    if (notes) bk.notes = notes;
    else delete bk.notes;
    
    bk.lastEdited = new Date().toISOString();
  }

  refresh();
  await saveToServer();
  closeEditModal();
  showToast('Booking updated successfully', 'success');
}

async function deleteBooking() {
  if (!editingBookingId || !editingSrcId) return;
  
  if (!confirm('Are you sure you want to delete this booking?')) return;

  const src = sources.find(s => s.id === editingSrcId);
  if (src) {
    const bookingIndex = src.bookings.findIndex(b => b.id === editingBookingId);
    if (bookingIndex !== -1) {
      src.bookings.splice(bookingIndex, 1);
      refresh();
      await saveToServer();
      closeEditModal();
      showToast('Booking deleted successfully', 'success');
    }
  }
}

async function duplicateBooking(bookingId, srcId) {
  const src = sources.find(s => s.id === srcId);
  const bk = src?.bookings.find(b => b.id === bookingId);
  
  if (!bk) return;
  
  const newBooking = {
    ...bk,
    id: `${srcId}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    guest: `${bk.guest} (copy)`,
    guestNameMissing: false
  };
  
  src.bookings.push(newBooking);
  refresh();
  await saveToServer();
  showToast('Booking duplicated', 'info');
}

/* ════════════════════════════════════════
   CONTEXT MENU
════════════════════════════════════════ */
function showContextMenu(event, bookingId, srcId) {
  event.preventDefault();
  event.stopPropagation();
  
  contextMenuBooking = { bookingId, srcId };
  
  const menu = document.getElementById('booking-context-menu');
  if (!menu) return;
  
  menu.style.left = event.clientX + 'px';
  menu.style.top = event.clientY + 'px';
  menu.classList.remove('hidden');
  
  setTimeout(() => {
    document.addEventListener('click', hideContextMenu);
  }, 10);
}

function hideContextMenu() {
  const menu = document.getElementById('booking-context-menu');
  if (menu) menu.classList.add('hidden');
  document.removeEventListener('click', hideContextMenu);
  contextMenuBooking = null;
}

function contextMenuAction(action) {
  if (!contextMenuBooking) return;
  
  const { bookingId, srcId } = contextMenuBooking;
  
  switch(action) {
    case 'edit':
      openEditModal(bookingId, srcId);
      break;
    case 'delete':
      editingBookingId = bookingId;
      editingSrcId = srcId;
      deleteBooking();
      break;
    case 'duplicate':
      duplicateBooking(bookingId, srcId);
      break;
  }
  
  hideContextMenu();
}

/* ════════════════════════════════════════
   TOAST NOTIFICATIONS
════════════════════════════════════════ */
function showToast(message, type = 'info') {
  const existingToast = document.getElementById('toast');
  if (existingToast) existingToast.remove();
  
  const toast = document.createElement('div');
  toast.id = 'toast';
  toast.className = `fixed bottom-5 right-5 z-[10000] px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-0 opacity-100`;
  
  const colors = {
    success: 'bg-emerald-500 text-white',
    error: 'bg-red-500 text-white',
    info: 'bg-primary text-white',
    warning: 'bg-amber-500 text-white'
  };
  
  toast.className += ' ' + (colors[type] || colors.info);
  
  const icons = {
    success: 'check-circle',
    error: 'alert-circle',
    info: 'info',
    warning: 'alert-triangle'
  };
  
  toast.innerHTML = `
    <i data-lucide="${icons[type]}" class="w-5 h-5"></i>
    <span class="text-sm font-medium">${message}</span>
    <button onclick="this.parentElement.remove()" class="ml-2 hover:opacity-80">
      <i data-lucide="x" class="w-4 h-4"></i>
    </button>
  `;
  
  document.body.appendChild(toast);
  lucide.createIcons();
  
  setTimeout(() => {
    toast.style.transform = 'translateY(100%)';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

/* ════════════════════════════════════════
   EXPORT
════════════════════════════════════════ */
function exportBookings() {
  const exportData = {
    sources: sources.map(src => ({
      id: src.id,
      name: src.name,
      platform: src.platform,
      color: src.color,
      url: src.url,
      bookings: src.bookings.map(bk => ({
        id: bk.id,
        guest: bk.guest,
        start: bk.start,
        end: bk.end,
        notes: bk.notes,
        guestNameMissing: bk.guestNameMissing,
        lastEdited: bk.lastEdited
      }))
    })),
    exportDate: new Date().toISOString(),
    version: '1.0'
  };
  
  const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `staysync-backup-${new Date().toISOString().split('T')[0]}.json`;
  a.click();
  
  showToast('Bookings exported successfully', 'success');
}

/* ════════════════════════════════════════
   ADD FROM URL
════════════════════════════════════════ */
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
      
      const bookings = data.events.map(ev => {
        const guestNameMissing = !ev.guest || ev.guest === 'Guest';
        
        return {
          id: ev.id || `${id}-${Math.random()}`,
          srcId: id,
          guest: ev.guest || 'Guest',
          start: ev.start,
          end: ev.end,
          guestNameMissing,
          notes: null,
          lastEdited: null,
          platform: plat
        };
      });

      sources.push({ 
        id, 
        name, 
        platform: plat, 
        color, 
        enabled: true, 
        url, 
        bookings 
      });

      lastRefreshTime = new Date();
      refresh();
      updateRefreshButtonState();
      await saveToServer();
      closeAddModal();
      
      showToast('Calendar added successfully', 'success');
    } else {
      throw new Error(data.error || 'Failed to fetch or parse iCal.');
    }
  } catch (err) {
    showToast('Error: ' + err.message, 'error');
  } finally {
    btn.innerHTML = original;
    btn.disabled = false;
    lucide.createIcons();
  }
}

/* ════════════════════════════════════════
   SETTINGS MODAL
════════════════════════════════════════ */
function openSettings() {
  renderReorderList();
  document.getElementById('settings-modal-bg')?.classList.add('open');
}

function closeSettings() {
  document.getElementById('settings-modal-bg')?.classList.remove('open');
}

function renderReorderList() {
  const el = document.getElementById('reorder-list');
  if (!el) return;
  el.innerHTML = '';
  
  if (!sources.length) {
    el.innerHTML = '<div class="text-center py-6 text-[#9aa0bb] dark:text-[#4e5675] text-sm">No sources added yet.</div>';
    return;
  }
  
  sources.forEach((src, idx) => {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-3 p-3 bg-[#f4f6fc] dark:bg-[#1f2436] border border-[#dce0f0] dark:border-[#2a3047] rounded-xl cursor-grab active:cursor-grabbing transition-all hover:border-[#b4bcd0] dark:hover:border-[#353d5a] group';
    row.draggable = true;
    row.dataset.idx = idx;

    row.innerHTML = `
      <div class="text-[#9aa0bb] dark:text-[#4e5675] text-lg font-bold group-hover:text-[#5b6380] dark:group-hover:text-[#8b93b5]">☰</div>
      <div class="relative w-7 h-7 rounded-md overflow-hidden border border-[#dce0f0] dark:border-[#353d5a] shrink-0">
        <input type="color" value="${src.color}" data-srcid="${src.id}" onchange="updateSourceColor('${src.id}',this.value)" oninput="updateSourceColor('${src.id}',this.value)" class="absolute -inset-1 w-[calc(100%+8px)] h-[calc(100%+8px)] cursor-pointer p-0 border-none">
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-[13px] font-bold leading-tight truncate">${src.name}</div>
        <div class="text-[10px] text-[#5b6380] dark:text-[#8b93b5]">${PLAT_LABEL[src.platform] || src.platform} &middot; ${src.bookings.length} reservations</div>
      </div>
      <div class="shrink-0">${PLAT_LOGO[src.platform + '_small'] || PLAT_LOGO[src.platform] || PLAT_LOGO.other_small}</div>
      <button class="w-8 h-8 rounded-lg flex items-center justify-center text-[#9aa0bb] dark:text-[#4e5675] hover:text-red-500 dark:hover:text-red-400 hover:bg-red-500/10 transition-all" onclick="deleteSource('${src.id}',event)" title="Delete source">
        <i data-lucide="trash-2" class="w-4 h-4"></i>
      </button>
    `;

    row.addEventListener('dragstart', e => { dragSrcIdx = idx; row.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
    row.addEventListener('dragend', () => { row.classList.remove('dragging'); });
    row.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; row.classList.add('drag-over'); });
    row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
    row.addEventListener('drop', e => {
      e.preventDefault();
      e.stopPropagation();
      const toIdx = parseInt(row.dataset.idx);
      if (dragSrcIdx === null || dragSrcIdx === toIdx) return;
      const moved = sources.splice(dragSrcIdx, 1)[0];
      sources.splice(toIdx, 0, moved);
      dragSrcIdx = null;
      renderReorderList();
    });

    el.appendChild(row);
  });
}

function updateSourceColor(id, color) {
  const src = sources.find(s => s.id === id);
  if (src) src.color = color;
}

function applySettings() {
  refresh();
  saveToServer();
  closeSettings();
}

/* ════════════════════════════════════════
   INIT
════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  lucide.createIcons();
  renderPresetColors();
  
  loadFromServer().then(() => {
    if (sources.length > 0) {
      const shouldAutoRefresh = !lastRefreshTime ||
        (new Date() - lastRefreshTime) > (60 * 60 * 1000);
      if (shouldAutoRefresh) {
        refreshAllSources();
      }
    }
  });
});

// Modal click handlers
document.getElementById('add-modal-bg')?.addEventListener('click', e => { 
  if (e.target === document.getElementById('add-modal-bg')) closeAddModal(); 
});

document.getElementById('settings-modal-bg')?.addEventListener('click', e => { 
  if (e.target === document.getElementById('settings-modal-bg')) closeSettings(); 
});

document.getElementById('edit-modal-bg')?.addEventListener('click', e => { 
  if (e.target === document.getElementById('edit-modal-bg')) closeEditModal(); 
});

document.getElementById('edit-source-modal-bg')?.addEventListener('click', e => { 
  if (e.target === document.getElementById('edit-source-modal-bg')) closeEditSourceModal(); 
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { 
    closeEditModal(); 
    closeAddModal(); 
    closeSettings();
    closeEditSourceModal();
  }
});

document.getElementById('edit-guest')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') saveEditBooking();
});

document.getElementById('edit-source-name-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') saveSourceEdit();
});

async function addFromUrl() {
  const inpName = document.getElementById('inp-name');
  const inpColor = document.getElementById('inp-color');
  const inpUrl = document.getElementById('inp-url');
  const platformSelect = document.getElementById('platform-select');

  const name = (inpName && inpName.value.trim()) || 'My iCal Listing';
  const color = inpColor ? inpColor.value : '#5b7fff';
  const url = inpUrl ? inpUrl.value.trim() : '';
  let plat = platformSelect ? platformSelect.value : 'other';

  if (!url) { 
    showToast('Please enter an iCal URL.', 'warning');
    return; 
  }

  const btn = event.target;
  const original = btn.innerHTML;
  btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Fetching...';
  btn.disabled = true;

  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(url)}&platform=${plat}&unit=${encodeURIComponent(name)}`;
    console.log('Fetching from:', proxyUrl);
    
    const response = await fetch(proxyUrl);
    
    // Check if response is OK
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    // Get response text first to debug
    const responseText = await response.text();
    console.log('Response preview:', responseText.substring(0, 200));
    
    // Try to parse JSON
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (e) {
      console.error('Invalid JSON:', responseText);
      throw new Error('Server returned invalid JSON. Check PHP error logs.');
    }
    
    if (data.success && data.events) {
      const id = 'src-' + Date.now();

      const bookings = data.events.map(ev => {
        let guest = ev.guest;
        let guestNameMissing = !guest || guest === 'Guest' || guest === null;
        
        return {
          id: ev.id || `${id}-${Math.random()}`,
          srcId: id,
          guest: guest,
          start: ev.start,
          end: ev.end,
          guestNameMissing: guestNameMissing,
          notes: null,
          lastEdited: null,
          platform: plat
        };
      });

      sources.push({
        id,
        name,
        platform: plat,
        color,
        enabled: true,
        url,
        showAvailable: true,
        bookings,
        blockedDates: data.blockedEvents || []
      });

      lastRefreshTime = new Date();
      refresh();
      updateRefreshButtonState();
      await saveToServer();
      closeAddModal();

      showToast(`Calendar "${name}" added successfully with ${bookings.length} bookings`, 'success');
    } else {
      throw new Error(data.error || 'Failed to fetch or parse iCal.');
    }
  } catch (err) {
    console.error('Error adding source:', err);
    showToast('Error: ' + err.message, 'error');
  } finally {
    btn.innerHTML = original;
    btn.disabled = false;
    lucide.createIcons();
  }
}