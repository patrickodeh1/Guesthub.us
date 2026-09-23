/* ════════════════════════════════════════
   DATA
════════════════════════════════════════ */
const PLAT_LABEL = { airbnb: 'Airbnb', vrbo: 'VRBO', booking: 'Booking.com', other: 'Other' };
const circleWrap = (imgHtml) => `
  <div style="width:26px;height:26px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,0.25);">
    ${imgHtml}
  </div>
`;

const smallCircleWrap = (imgHtml) => `
  <div style="width:22px;height:22px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.2);">
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

let sources = [];
let calendar;
let editingBookingId = null, editingSrcId = null;
let editingSourceId = null;
let contextMenuBooking = null;
let lastRefreshTime = null;
let dragSrcIdx = null;
const tip = document.getElementById('tooltip');
let tipT;

// Global settings for blocked dots appearance
let globalSettings = {
  blockedColor: '#ef4444',
  blockedDotSize: 8,
  showOverlaps: true
};

/* ════════════════════════════════════════
   PER PROPERTY FUNCTIONS
════════════════════════════════════════ */

function toggleSourceVisibility(id, e) {
  e.stopPropagation();
  const src = sources.find(s => s.id === id);
  if (src) {
    src.enabled = !src.enabled;
    refresh();
    saveToServer();
  }
}

function togglePropertyAvailable(sourceId, checked) {
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.showAvailableDates = checked;
    saveToServer();
    updateCalendar();
    showToast(`${src.name}: Available dates ${checked ? 'ON' : 'OFF'}`, 'info');
  }
}

function togglePropertyBlocked(sourceId, checked) {
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.showBlockedDates = checked;
    saveToServer();
    updateCalendar();
    showToast(`${src.name}: Blocked dates ${checked ? 'ON' : 'OFF'}`, 'info');
  }
}

function updatePropertyAvailableColor(sourceId, color) {
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.availableColor = color;
    saveToServer();
    updateCalendar();
    showToast(`${src.name}: Available color updated`, 'success');
  }
}

function updatePropertyBlockedColor(sourceId, color) {
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.blockedColor = color;
    saveToServer();
    updateCalendar();
    showToast(`${src.name}: Blocked color updated`, 'success');
  }
}

/* ════════════════════════════════════════
   GLOBAL SETTINGS FUNCTIONS
════════════════════════════════════════ */

function updateGlobalBlockedColor(color) {
  globalSettings.blockedColor = color;
  localStorage.setItem('globalSettings', JSON.stringify(globalSettings));
  updateCalendar();
}

function updateGlobalBlockedDotSize(size) {
  globalSettings.blockedDotSize = parseInt(size);
  localStorage.setItem('globalSettings', JSON.stringify(globalSettings));
  updateCalendar();
}

function toggleGlobalOverlapSetting() {
  const toggle = document.getElementById('show-overlap-toggle');
  if (toggle) {
    globalSettings.showOverlaps = toggle.checked;
    localStorage.setItem('globalSettings', JSON.stringify(globalSettings));
    updateCalendar();
  }
}

function loadGlobalSettings() {
  const saved = localStorage.getItem('globalSettings');
  if (saved) {
    globalSettings = { ...globalSettings, ...JSON.parse(saved) };
  }
}

/* ════════════════════════════════════════
   DEBUG FUNCTIONS
════════════════════════════════════════ */
function debugAllDates() {
  console.log('\n╔════════════════════════════════════════════════════════════╗');
  console.log('║              AVAILABLE & BLOCKED DATES REPORT              ║');
  console.log('╚════════════════════════════════════════════════════════════╝\n');

  const today = new Date();
  const todayUTC = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
  const threeMonthsLater = new Date(todayUTC);
  threeMonthsLater.setUTCMonth(todayUTC.getUTCMonth() + 3);

  console.log(`📅 Date Range: ${todayUTC.toISOString().split('T')[0]} → ${threeMonthsLater.toISOString().split('T')[0]}\n`);

  sources.forEach(src => {
    console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
    console.log(`📌 ${src.name} (${src.platform})`);
    console.log(`   Visible: ${src.enabled ? '✅' : '❌'}`);
    console.log(`   Show Available: ${src.showAvailableDates !== false ? '✅ ON' : '❌ OFF'}`);
    console.log(`   Available Color: ${src.availableColor || '#22c55e'}`);
    console.log(`   Show Blocked: ${src.showBlockedDates !== false ? '✅ ON' : '❌ OFF'}`);
    console.log(`   Blocked Color: ${src.blockedColor || globalSettings.blockedColor}\n`);

    const bookedDates = new Set();
    const blockedDatesSet = new Set();

    src.bookings.forEach(booking => {
      let startDate = new Date(Date.UTC(
        parseInt(booking.start.substring(0, 4)),
        parseInt(booking.start.substring(5, 7)) - 1,
        parseInt(booking.start.substring(8, 10))
      ));
      let endDate = new Date(Date.UTC(
        parseInt(booking.end.substring(0, 4)),
        parseInt(booking.end.substring(5, 7)) - 1,
        parseInt(booking.end.substring(8, 10))
      ));
      let currentDate = new Date(startDate);
      while (currentDate < endDate) {
        bookedDates.add(currentDate.toISOString().split('T')[0]);
        currentDate.setUTCDate(currentDate.getUTCDate() + 1);
      }
    });

    if (src.blockedDates) {
      src.blockedDates.forEach(block => {
        let startDate = new Date(Date.UTC(
          parseInt(block.start.substring(0, 4)),
          parseInt(block.start.substring(5, 7)) - 1,
          parseInt(block.start.substring(8, 10))
        ));
        let endDate = new Date(Date.UTC(
          parseInt(block.end.substring(0, 4)),
          parseInt(block.end.substring(5, 7)) - 1,
          parseInt(block.end.substring(8, 10))
        ));
        let currentDate = new Date(startDate);
        while (currentDate < endDate) {
          blockedDatesSet.add(currentDate.toISOString().split('T')[0]);
          currentDate.setUTCDate(currentDate.getUTCDate() + 1);
        }
      });
    }

    const availableDates = [];
    const blockedDatesList = [];
    const bookedDatesList = [];

    let currentDate = new Date(todayUTC);
    while (currentDate < threeMonthsLater) {
      const dateStr = currentDate.toISOString().split('T')[0];
      if (bookedDates.has(dateStr)) {
        bookedDatesList.push(dateStr);
      } else if (blockedDatesSet.has(dateStr)) {
        blockedDatesList.push(dateStr);
      } else {
        availableDates.push(dateStr);
      }
      currentDate.setUTCDate(currentDate.getUTCDate() + 1);
    }

    if (bookedDatesList.length > 0) {
      console.log(`   📅 BOOKED (${bookedDatesList.length} days):`);
      console.log(`      ${bookedDatesList.join(', ')}`);
    }

    if (blockedDatesList.length > 0) {
      console.log(`   🚫 BLOCKED (${blockedDatesList.length} days):`);
      console.log(`      ${blockedDatesList.join(', ')}`);
    }

    if (availableDates.length > 0) {
      console.log(`   ✅ AVAILABLE (${availableDates.length} days):`);
      console.log(`      ${availableDates.join(', ')}`);
    }
    console.log('');
  });
}

window.debugAllDates = debugAllDates;

/* ════════════════════════════════════════
   THEME
════════════════════════════════════════ */
function toggleTheme() {
  const html = document.documentElement;
  const isDark = html.classList.contains('dark');

  if (isDark) {
    html.classList.remove('dark');
    localStorage.setItem('theme', 'light');
    const icon = document.getElementById('themeIcon');
    if (icon) icon.setAttribute('data-lucide', 'moon');
  } else {
    html.classList.add('dark');
    localStorage.setItem('theme', 'dark');
    const icon = document.getElementById('themeIcon');
    if (icon) icon.setAttribute('data-lucide', 'sun');
  }
  lucide.createIcons();
}

function loadTheme() {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    document.documentElement.classList.add('dark');
    const icon = document.getElementById('themeIcon');
    if (icon) icon.setAttribute('data-lucide', 'sun');
  } else {
    document.documentElement.classList.remove('dark');
    const icon = document.getElementById('themeIcon');
    if (icon) icon.setAttribute('data-lucide', 'moon');
  }
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
   RENDER GLOBAL SETTINGS IN MODAL
════════════════════════════════════════ */
function renderGlobalSettings() {
  const container = document.getElementById('global-settings-container');
  if (!container) return;

  container.innerHTML = `
    <div class="space-y-4">
      <h4 class="font-bold text-sm mb-2">Global Display Settings</h4>
      
      
      
      <div class="flex items-center gap-3">
        <label class="text-sm">Blocked Dot Size:</label>
        <input type="range" id="global-blocked-dot-size" min="4" max="12" value="${globalSettings.blockedDotSize}" onchange="updateGlobalBlockedDotSize(this.value)" class="flex-1">
        <span class="text-xs w-8">${globalSettings.blockedDotSize}px</span>
      </div>
      
      <div class="border-t border-[#dce0f0] dark:border-[#2a3047] my-2"></div>
      
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i data-lucide="alert-triangle" class="w-4 h-4 text-orange-500"></i>
          <span class="text-sm">Show Overlap Alerts</span>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" id="show-overlap-toggle" class="sr-only peer" ${globalSettings.showOverlaps ? 'checked' : ''} onchange="toggleGlobalOverlapSetting()">
          <div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-orange-500"></div>
        </label>
      </div>
    </div>
  `;
  lucide.createIcons();
}

/* ════════════════════════════════════════
   GET EVENT LOGO HTML
════════════════════════════════════════ */
function getEventLogoHtml(platform) {
  const wrapStart = '<div style="width:20px;height:20px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
  const wrapEnd = '</div>';

  switch (platform) {
    case 'airbnb':
      return wrapStart + '<img src="assets/img/black-airbnb.png" style="width:12px;height:12px;object-fit:contain;" alt="Airbnb">' + wrapEnd;
    case 'vrbo':
      return wrapStart + '<img src="assets/img/logo-new.png" style="width:18px;height:18px;object-fit:contain;" alt="Vrbo">' + wrapEnd;
    case 'booking':
      return wrapStart + '<img src="assets/img/logo-booking.png" style="width:12px;height:12px;object-fit:contain;" alt="Booking">' + wrapEnd;
    default:
      return wrapStart + '<i data-lucide="calendar" style="width:12px;height:12px;color:#000;"></i>' + wrapEnd;
  }
}

/* ════════════════════════════════════════
   GENERATE AVAILABLE EVENTS
════════════════════════════════════════ */
function generateAvailableEvents() {
  const availableEvents = [];
  const today = new Date();
  const todayUTC = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
  const threeMonthsLater = new Date(todayUTC);
  threeMonthsLater.setUTCMonth(todayUTC.getUTCMonth() + 3);

  const enabledSources = sources.filter(s => s.enabled === true && s.showAvailableDates === true);

  console.log(`📊 Generating available events for ${enabledSources.length} sources...`);

  enabledSources.forEach(src => {
    const unavailableDates = new Set();

    src.bookings.forEach(booking => {
      let startDate = new Date(Date.UTC(
        parseInt(booking.start.substring(0, 4)),
        parseInt(booking.start.substring(5, 7)) - 1,
        parseInt(booking.start.substring(8, 10))
      ));
      let endDate = new Date(Date.UTC(
        parseInt(booking.end.substring(0, 4)),
        parseInt(booking.end.substring(5, 7)) - 1,
        parseInt(booking.end.substring(8, 10))
      ));

      let currentDate = new Date(startDate);
      while (currentDate < endDate) {
        const dateStr = `${currentDate.getUTCFullYear()}-${String(currentDate.getUTCMonth() + 1).padStart(2, '0')}-${String(currentDate.getUTCDate()).padStart(2, '0')}`;
        unavailableDates.add(dateStr);
        currentDate.setUTCDate(currentDate.getUTCDate() + 1);
      }
    });

    if (src.blockedDates) {
      src.blockedDates.forEach(block => {
        let startDate = new Date(Date.UTC(
          parseInt(block.start.substring(0, 4)),
          parseInt(block.start.substring(5, 7)) - 1,
          parseInt(block.start.substring(8, 10))
        ));
        let endDate = new Date(Date.UTC(
          parseInt(block.end.substring(0, 4)),
          parseInt(block.end.substring(5, 7)) - 1,
          parseInt(block.end.substring(8, 10))
        ));

        let currentDate = new Date(startDate);
        while (currentDate < endDate) {
          const dateStr = `${currentDate.getUTCFullYear()}-${String(currentDate.getUTCMonth() + 1).padStart(2, '0')}-${String(currentDate.getUTCDate()).padStart(2, '0')}`;
          unavailableDates.add(dateStr);
          currentDate.setUTCDate(currentDate.getUTCDate() + 1);
        }
      });
    }

    let currentDate = new Date(todayUTC);
    let blockStart = null;
    let blockEnd = null;
    let availableCount = 0;

    while (currentDate < threeMonthsLater) {
      const dateStr = `${currentDate.getUTCFullYear()}-${String(currentDate.getUTCMonth() + 1).padStart(2, '0')}-${String(currentDate.getUTCDate()).padStart(2, '0')}`;
      const isAvailable = !unavailableDates.has(dateStr);

      if (isAvailable) {
        availableCount++;
        if (blockStart === null) {
          blockStart = dateStr;
          blockEnd = dateStr;
        } else {
          blockEnd = dateStr;
        }
      } else {
        if (blockStart !== null) {
          const nextDay = new Date(Date.UTC(
            parseInt(blockEnd.substring(0, 4)),
            parseInt(blockEnd.substring(5, 7)) - 1,
            parseInt(blockEnd.substring(8, 10)) + 1
          ));
          const nextDayStr = `${nextDay.getUTCFullYear()}-${String(nextDay.getUTCMonth() + 1).padStart(2, '0')}-${String(nextDay.getUTCDate()).padStart(2, '0')}`;

          availableEvents.push({
            id: `available-${src.id}-${blockStart}`,
            srcId: src.id,
            title: src.name,
            start: blockStart,
            end: nextDayStr,
            color: src.availableColor || '#22c55e',
            textColor: '#ffffff',
            classNames: ['fc-event-available-block'],
            extendedProps: {
              isAvailable: true,
              srcId: src.id,
              srcName: src.name,
              srcPlatform: src.platform,
              blockStart: blockStart,
              blockEnd: blockEnd
            }
          });
          blockStart = null;
          blockEnd = null;
        }
      }
      currentDate.setUTCDate(currentDate.getUTCDate() + 1);
    }

    if (blockStart !== null) {
      const nextDay = new Date(Date.UTC(
        parseInt(blockEnd.substring(0, 4)),
        parseInt(blockEnd.substring(5, 7)) - 1,
        parseInt(blockEnd.substring(8, 10)) + 1
      ));
      const nextDayStr = `${nextDay.getUTCFullYear()}-${String(nextDay.getUTCMonth() + 1).padStart(2, '0')}-${String(nextDay.getUTCDate()).padStart(2, '0')}`;

      availableEvents.push({
        id: `available-${src.id}-${blockStart}`,
        srcId: src.id,
        title: src.name,
        start: blockStart,
        end: nextDayStr,
        color: src.availableColor || '#22c55e',
        textColor: '#ffffff',
        classNames: ['fc-event-available-block'],
        extendedProps: {
          isAvailable: true,
          srcId: src.id,
          srcName: src.name,
          srcPlatform: src.platform,
          blockStart: blockStart,
          blockEnd: blockEnd
        }
      });
    }

    console.log(`   ✅ ${src.name}: ${availableCount} available dates in next 3 months`);
  });

  console.log(`   🎯 Total available events: ${availableEvents.length}`);
  return availableEvents;
}

/* ════════════════════════════════════════
   ADD BLOCKED DATE INDICATORS
════════════════════════════════════════ */
function addBlockedDateIndicators() {
  const dayCells = document.querySelectorAll('.fc-daygrid-day');
  let totalBlockedIndicators = 0;

  dayCells.forEach(cell => {
    const dateAttr = cell.getAttribute('data-date');
    if (!dateAttr) return;
    const cellDateStr = dateAttr.split('T')[0];

    const cellDate = new Date(Date.UTC(
      parseInt(cellDateStr.substring(0, 4)),
      parseInt(cellDateStr.substring(5, 7)) - 1,
      parseInt(cellDateStr.substring(8, 10))
    ));

    const existing = cell.querySelectorAll('.blocked-indicator');
    existing.forEach(el => el.remove());

    sources.filter(s => s.enabled === true && s.showBlockedDates === true).forEach(src => {
      const isBlocked = src.blockedDates && src.blockedDates.some(block => {
        const start = new Date(Date.UTC(
          parseInt(block.start.substring(0, 4)),
          parseInt(block.start.substring(5, 7)) - 1,
          parseInt(block.start.substring(8, 10))
        ));
        const end = new Date(Date.UTC(
          parseInt(block.end.substring(0, 4)),
          parseInt(block.end.substring(5, 7)) - 1,
          parseInt(block.end.substring(8, 10))
        ));
        return cellDate >= start && cellDate < end;
      });

      if (isBlocked) {
        const indicator = document.createElement('div');
        indicator.className = 'blocked-indicator';
        const dotColor = src.blockedColor || globalSettings.blockedColor;
        indicator.style.cssText = `
          position: absolute;
          bottom: 4px;
          right: 4px;
          width: ${globalSettings.blockedDotSize}px;
          height: ${globalSettings.blockedDotSize}px;
          background: ${dotColor};
          border-radius: 50%;
          cursor: help;
          box-shadow: 0 0 0 1px rgba(255,255,255,0.5);
          z-index: 10;
        `;
        indicator.title = `${src.name}: Blocked`;
        cell.appendChild(indicator);
        totalBlockedIndicators++;
      }
    });
  });

  console.log(`🚫 Added ${totalBlockedIndicators} blocked date indicators`);
}

/* ════════════════════════════════════════
   SHOW DATE EVENT COUNT TOOLTIP
════════════════════════════════════════ */
function showDateEventCountTooltip(event, dateStr) {
  const tip = document.getElementById('tooltip');
  if (!tip) return;
  clearTimeout(tipT);

  const formattedDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });

  // Function to check if string looks like a URL or invalid (SAME as eventContent)
  const isUrlLike = (str) => {
    if (!str) return true;
    if (str === 'Guest') return true;
    if (str === 'null') return true;
    if (str === 'undefined') return true;
    if (str === '') return true;
    
    const urlPatterns = [
      /^https?:\/\//i,
      /^www\./i,
      /\.com$/i,
      /\.net$/i,
      /\.org$/i,
      /\.ics$/i,
      /\.html?$/i,
      /[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/,
      /^[a-f0-9]{32}$/i,
      /[a-zA-Z0-9_\-]{20,}/
    ];
    return urlPatterns.some(pattern => pattern.test(str));
  };

  const eventsOnDate = [];
  sources.filter(s => s.enabled).forEach(src => {
    src.bookings.forEach(booking => {
      const start = new Date(booking.start + 'T00:00:00');
      const end = new Date(booking.end + 'T00:00:00');
      const checkDate = new Date(dateStr + 'T00:00:00');
      if (checkDate >= start && checkDate < end) {
        // Determine if guest name is invalid (URL-like)
        const isInvalid = isUrlLike(booking.guest);
        // Show "Add Guest Name" if invalid, otherwise show the guest name
        const displayGuest = isInvalid ? 'Add Guest Name' : booking.guest;
        
        eventsOnDate.push({
          property: src.name,
          guest: displayGuest,
          platform: src.platform,
          color: src.color,
          isInvalid: isInvalid
        });
      }
    });
  });

  const blockedProperties = [];
  sources.forEach(src => {
    const isBlocked = src.blockedDates && src.blockedDates.some(block => {
      const start = new Date(block.start + 'T00:00:00');
      const end = new Date(block.end + 'T00:00:00');
      const checkDate = new Date(dateStr + 'T00:00:00');
      return checkDate >= start && checkDate < end;
    });
    if (isBlocked) blockedProperties.push(src.name);
  });

  let html = `<div class="text-xs font-bold uppercase tracking-wider mb-2">${formattedDate}</div>`;

  if (eventsOnDate.length > 0) {
    html += `
      <div class="mb-2">
        <div class="flex items-center gap-1 mb-1">
          <i data-lucide="calendar" class="w-3 h-3 text-primary"></i>
          <span class="text-xs font-semibold">Bookings (${eventsOnDate.length})</span>
        </div>
        <div class="space-y-1 max-h-32 overflow-y-auto">
    `;
    eventsOnDate.forEach(event => {
      const guestClass = event.isInvalid ? 'text-amber-500 italic' : '';
      html += `
        <div class="flex items-center gap-2 text-xs py-1 border-b border-[#dce0f0] dark:border-[#2a3047]">
          <div class="w-2 h-2 rounded-full" style="background: ${event.color}"></div>
          <span class="font-medium">${event.property}</span>
          <span class="text-[#5b6380] dark:text-[#8b93b5] ${guestClass}">${event.guest}</span>
        </div>
      `;
    });
    html += `</div></div>`;
  }

  if (blockedProperties.length > 0) {
    html += `
      <div class="mb-2">
        <div class="flex items-center gap-1 mb-1">
          <i data-lucide="x-circle" class="w-3 h-3 text-red-500"></i>
          <span class="text-xs font-semibold">Blocked (${blockedProperties.length})</span>
        </div>
        <div class="space-y-1">
          ${blockedProperties.map(name => `<div class="flex items-center gap-2 text-xs"><i data-lucide="x-circle" class="w-3 h-3 text-red-500"></i><span>${name}</span></div>`).join('')}
        </div>
      </div>
    `;
  }

  if (eventsOnDate.length === 0 && blockedProperties.length === 0) {
    html += `<div class="text-xs text-[#5b6380] dark:text-[#8b93b5]">No events on this date</div>`;
  }

  tip.innerHTML = html;
  lucide.createIcons();

  const x = event.clientX;
  const y = event.clientY;
  const right = window.innerWidth - x > 280;
  tip.style.left = (right ? x + 16 : x - 280) + 'px';
  tip.style.top = (y - 8) + 'px';
  tip.classList.remove('opacity-0', 'translate-y-1.5');
  tip.classList.add('opacity-100', 'translate-y-0');
}

/* ════════════════════════════════════════
   CALENDAR EVENTS
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

function makeCalEvents() {
  const vis = allVisible();
  const ovIds = new Set();

  if (globalSettings.showOverlaps) {
    detectOverlaps(vis).forEach(({ a, b }) => {
      ovIds.add(a.id);
      ovIds.add(b.id);
    });
  }

  return vis.map((b, _i) => {
    const src = sources.find(s => s.id === b.srcId);
    const isOv = ovIds.has(b.id);
    const srcIdx = sources.findIndex(s => s.id === b.srcId);
    
    // Make sure we pass all necessary data to extendedProps
    return {
      id: b.id,
      title: b.guest || 'Guest',
      start: b.start,
      end: b.end,
      color: src?.color || '#888',
      textColor: '#ffffff',
      order: srcIdx,
      classNames: isOv ? ['fc-event-overlap'] : [],
      extendedProps: {
        ...b,
        isOverlap: isOv,
        srcColor: src?.color,
        srcName: src?.name,
        srcPlatform: src?.platform,
        guest: b.guest,
        guestNameMissing: b.guestNameMissing,
        srcId: b.srcId
      }
    };
  });
}
function updateCalendar() {
  if (!calendar) return;
  calendar.removeAllEvents();

  const bookingEvents = makeCalEvents();
  let allEvents = [...bookingEvents];

  // Add available events for properties that have it enabled
  const availableEvents = generateAvailableEvents();
  allEvents = [...allEvents, ...availableEvents];

  calendar.addEventSource(allEvents);

  setTimeout(() => {
    addBlockedDateIndicators();
  }, 100);
}

/* ════════════════════════════════════════
   SOURCE FUNCTIONS - WITH 5 CONTROLS PER PROPERTY
════════════════════════════════════════ */
function renderSourceFilterList() {
  const el = document.getElementById('source-filter-list');
  if (!el) return;
  const srcCount = document.getElementById('src-count-modal');
  if (srcCount) srcCount.textContent = sources.length;

  if (!sources.length) {
    el.innerHTML = '<div class="text-center py-4 text-[#9aa0bb] dark:text-[#4e5675] text-sm">No sources added yet.</div>';
    return;
  }

  // Function to get platform logo HTML
  const getPlatformLogo = (platform) => {
    switch (platform) {
      case 'airbnb':
        return '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/black-airbnb.png" style="width:16px;height:16px;object-fit:contain;" alt="Airbnb"></div>';
      case 'vrbo':
        return '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/logo-new.png" style="width:20px;height:20px;object-fit:contain;" alt="VRBO"></div>';
      case 'booking':
        return '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/logo-booking.png" style="width:16px;height:16px;object-fit:contain;" alt="Booking.com"></div>';
      default:
        return '<div style="width:28px;height:28px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><i data-lucide="calendar" style="width:14px;height:14px;color:#000;"></i></div>';
    }
  };

  el.innerHTML = sources.map(src => `
    <div class="flex flex-col gap-2 p-3 bg-white dark:bg-[#181c27] border border-[#dce0f0] dark:border-[#2a3047] rounded-lg">
      <!-- Property Header with Logo -->
      <div class="flex items-center justify-between" style="background-color: grey">
        <div class="flex items-center gap-3">
          ${getPlatformLogo(src.platform)}
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full" style="background:${src.color}"></div>
            <span class="text-sm font-medium">${src.name}</span>
            <span class="text-xs text-[#5b6380] dark:text-[#8b93b5]">${src.bookings.length} bookings</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="editSourceModal('${src.id}',event)" class="text-[#9aa0bb] hover:text-primary" title="Edit Property">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
      
      <!-- Switch 1: Property Visibility -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" class="sr-only peer" ${src.enabled ? 'checked' : ''} onchange="toggleSourceVisibility('${src.id}',event)">
            <div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
          </label>
          <span class="text-[11px] font-medium">Visible</span>
        </div>
      </div>
      
      <!-- Available Dates Section -->
      <div class="flex items-center justify-between pt-1">
        <div class="flex items-center gap-2">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" class="sr-only peer" ${src.showAvailableDates === true ? 'checked' : ''} onchange="togglePropertyAvailable('${src.id}', this.checked)">
            <div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
          </label>
          <span class="text-[11px] font-medium">Show Available Dates</span>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-[10px] text-[#5b6380]">Color:</label>
          <input type="color" value="${src.availableColor || '#22c55e'}" onchange="updatePropertyAvailableColor('${src.id}', this.value)" class="w-6 h-6 rounded cursor-pointer border border-[#dce0f0] dark:border-[#2a3047]">
        </div>
      </div>
      
      <!-- Blocked Dates Section -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" class="sr-only peer" ${src.showBlockedDates === true ? 'checked' : ''} onchange="togglePropertyBlocked('${src.id}', this.checked)">
            <div class="w-9 h-5 bg-[#dce0f0] dark:bg-[#2a3047] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-500"></div>
          </label>
          <span class="text-[11px] font-medium">Show Blocked Dates</span>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-[10px] text-[#5b6380]">Color:</label>
          <input type="color" value="${src.blockedColor || globalSettings.blockedColor}" onchange="updatePropertyBlockedColor('${src.id}', this.value)" class="w-6 h-6 rounded cursor-pointer border border-[#dce0f0] dark:border-[#2a3047]">
        </div>
      </div>
    </div>
  `).join('');
  lucide.createIcons();
}

function renderReorderList() {
  const el = document.getElementById('reorder-list');
  if (!el) return;
  el.innerHTML = '';
  
  if (!sources.length) {
    el.innerHTML = '<div class="text-center py-4 text-[#9aa0bb] dark:text-[#4e5675] text-sm">No sources added yet.</div>';
    return;
  }

  // Function to get platform logo HTML for reorder list
  const getPlatformLogo = (platform) => {
    switch (platform) {
      case 'airbnb':
        return '<div style="width:24px;height:24px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/black-airbnb.png" style="width:14px;height:14px;object-fit:contain;" alt="Airbnb"></div>';
      case 'vrbo':
        return '<div style="width:24px;height:24px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/logo-new.png" style="width:16px;height:16px;object-fit:contain;" alt="VRBO"></div>';
      case 'booking':
        return '<div style="width:24px;height:24px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><img src="assets/img/logo-booking.png" style="width:14px;height:14px;object-fit:contain;" alt="Booking.com"></div>';
      default:
        return '<div style="width:24px;height:24px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,0.1);"><i data-lucide="calendar" style="width:12px;height:12px;color:#000;"></i></div>';
    }
  };

  sources.forEach((src, idx) => {
    const row = document.createElement('div');
    row.className = 'flex items-center gap-3 p-3 bg-[#f4f6fc] dark:bg-[#1f2436] border border-[#dce0f0] dark:border-[#2a3047] rounded-xl cursor-grab active:cursor-grabbing transition-all hover:border-[#b4bcd0] dark:hover:border-[#353d5a] group';
    row.draggable = true;
    row.dataset.idx = idx;

    row.innerHTML = `
      <div class="text-[#9aa0bb] dark:text-[#4e5675] text-lg font-bold group-hover:text-[#5b6380] dark:group-hover:text-[#8b93b5]">☰</div>
      ${getPlatformLogo(src.platform)}
      <div class="relative w-7 h-7 rounded-md overflow-hidden border border-[#dce0f0] dark:border-[#353d5a] shrink-0">
        <input type="color" value="${src.color}" data-srcid="${src.id}" onchange="updateSourceColor('${src.id}',this.value)" oninput="updateSourceColor('${src.id}',this.value)" class="absolute -inset-1 w-[calc(100%+8px)] h-[calc(100%+8px)] cursor-pointer p-0 border-none">
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-[13px] font-bold leading-tight truncate">${src.name}</div>
        <div class="text-[10px] text-[#5b6380] dark:text-[#8b93b5]">${PLAT_LABEL[src.platform] || src.platform} &middot; ${src.bookings.length} reservations</div>
      </div>
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
  lucide.createIcons();
}

function updateSourceColor(id, color) {
  const src = sources.find(s => s.id === id);
  if (src) {
    src.color = color;
    updateCalendar();
  }
}

function refresh() {
  renderSourceFilterList();
  renderReorderList();
  updateCalendar();
  updateLastUpdatedDisplay();
}

function updateLastUpdatedDisplay() {
  const lastUpdatedHeader = document.getElementById('last-updated-header');
  const lastUpdatedModal = document.getElementById('last-updated-modal');
  const text = lastRefreshTime ? `Synced ${new Date(lastRefreshTime).toLocaleTimeString()}` : 'Never synced';
  if (lastUpdatedHeader) lastUpdatedHeader.textContent = text;
  if (lastUpdatedModal) lastUpdatedModal.textContent = text;
}

function showToast(message, type = 'info') {
  const existingToast = document.getElementById('dynamic-toast');
  if (existingToast) existingToast.remove();
  const toast = document.createElement('div');
  toast.id = 'dynamic-toast';
  const bgColor = type === 'success' ? 'bg-emerald-500' : type === 'error' ? 'bg-red-500' : 'bg-primary';
  toast.className = `fixed bottom-5 right-5 z-[10000] px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 ${bgColor} text-white`;
  toast.innerHTML = `<span class="text-sm">${message}</span>`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

/* ════════════════════════════════════════
   OPEN SETTINGS
════════════════════════════════════════ */
function openSettings() {
  renderGlobalSettings();
  renderReorderList();
  renderSourceFilterList();
  const modalBg = document.getElementById('settings-modal-bg');
  if (modalBg) modalBg.classList.add('open');
}

function closeSettings() {
  const modalBg = document.getElementById('settings-modal-bg');
  if (modalBg) modalBg.classList.remove('open');
}

function applySettings() {
  refresh();
  saveToServer();
  closeSettings();
}

/* ════════════════════════════════════════
   SAVE/LOAD FUNCTIONS
════════════════════════════════════════ */
async function saveToServer() {
  try {
    await fetch('data.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sources, lastRefreshTime })
    });
  } catch (e) { console.warn(e); }
}

async function loadFromServer() {
  try {
    const r = await fetch('data.php');
    if (!r.ok) {
      console.error('Failed to load data:', r.status, r.statusText);
      showToast('Failed to load data from server', 'error');
      return;
    }
    const text = await r.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON response:', text.substring(0, 200));
      // showToast('Invalid response from server', 'error');
      return;
    }

    if (Array.isArray(data.sources)) {
      sources = data.sources;
      sources.forEach(src => {
        // Default to OFF for new properties
        if (src.showAvailableDates === undefined) src.showAvailableDates = false;
        if (src.showBlockedDates === undefined) src.showBlockedDates = false;
        if (!src.availableColor) src.availableColor = '#22c55e';
        if (!src.blockedColor) src.blockedColor = globalSettings.blockedColor;
        if (!src.blockedDates) src.blockedDates = [];
        if (src.bookings) {
          src.bookings.forEach(bk => {
            if (bk.guestNameMissing === undefined) {
              bk.guestNameMissing = !bk.guest || bk.guest === 'Guest';
            }
          });
        }
      });
    }
    if (data.lastRefreshTime) lastRefreshTime = new Date(data.lastRefreshTime);
  } catch (e) {
    console.warn('Could not load saved data:', e);
    showToast('Error loading data: ' + e.message, 'error');
  }
  refresh();
  setTimeout(() => {
    console.log('\n🎯 StaySync Loaded - Run debugAllDates() to see all available & blocked dates\n');
    debugAllDates();
  }, 500);
}

/* ════════════════════════════════════════
   REFRESH ALL SOURCES
════════════════════════════════════════ */
async function refreshAllSources() {
  if (sources.length === 0) return;

  const refreshBtn = document.getElementById('refresh-btn-header');
  const refreshIcon = document.getElementById('refresh-icon-header');
  const refreshStatus = document.getElementById('refresh-status-modal');

  if (refreshBtn) { refreshBtn.disabled = true; refreshBtn.classList.add('opacity-60'); }
  if (refreshIcon) refreshIcon.classList.add('animate-spin');
  if (refreshStatus) refreshStatus.classList.remove('hidden');

  let successCount = 0, errorCount = 0;

  for (const src of sources) {
    try {
      const result = await refreshSingleSource(src);
      if (result.success) {
        src.bookings = result.bookings;
        src.blockedDates = result.blockedDates || [];
        successCount++;
      } else {
        errorCount++;
      }
    } catch (err) {
      errorCount++;
    }
  }

  lastRefreshTime = new Date();
  refresh();
  await saveToServer();

  if (refreshBtn) { refreshBtn.disabled = false; refreshBtn.classList.remove('opacity-60'); }
  if (refreshIcon) refreshIcon.classList.remove('animate-spin');
  if (refreshStatus) refreshStatus.classList.add('hidden');

  if (errorCount > 0) {
    // showToast(`Refreshed ${successCount} calendars. ${errorCount} failed.`, 'error');
  } else {
    showToast(`Successfully refreshed ${successCount} calendar(s)`, 'success');
  }

  setTimeout(() => debugAllDates(), 500);
}

/* ════════════════════════════════════════
   REFRESH SINGLE SOURCE
════════════════════════════════════════ */
async function refreshSingleSource(src) {
  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(src.url)}&platform=${src.platform}&unit=${encodeURIComponent(src.name)}`;
    const r = await fetch(proxyUrl);
    const data = await r.json();

    if (data.success) {
      const newBookings = data.events || [];
      const newBlockedDates = data.blockedEvents || [];

      const parseDate = (dateStr) => {
        if (!dateStr) return null;
        return dateStr;
      };

      const processedBookings = newBookings.map(ev => ({
        ...ev,
        start: parseDate(ev.start),
        end: parseDate(ev.end)
      }));

      const processedBlockedDates = newBlockedDates.map(block => ({
        ...block,
        start: parseDate(block.start),
        end: parseDate(block.end)
      }));

      const existingBookingsMap = new Map();
      src.bookings.forEach(bk => existingBookingsMap.set(bk.id, bk));

      const finalBookings = processedBookings.map(ev => {
        const existing = existingBookingsMap.get(ev.id);
        if (existing) {
          const wasCustomized = existing.guest !== 'Guest' && !existing.guestNameMissing;
          return {
            ...ev,
            srcId: src.id,
            guest: wasCustomized ? existing.guest : ev.guest,
            guestNameMissing: wasCustomized ? false : (ev.guest === 'Guest'),
            notes: existing.notes || null,
            lastEdited: existing.lastEdited || null
          };
        }
        return {
          ...ev,
          srcId: src.id,
          guestNameMissing: (ev.guest === 'Guest'),
          notes: null,
          lastEdited: null
        };
      });

      return {
        success: true,
        bookings: finalBookings,
        blockedDates: processedBlockedDates
      };
    }
    return { success: false, error: data.error };
  } catch (err) {
    return { success: false, error: err.message };
  }
}

/* ════════════════════════════════════════
   EDIT FUNCTIONS
════════════════════════════════════════ */
function editSourceModal(sourceId, event) {
  event.stopPropagation();
  const src = sources.find(s => s.id === sourceId);
  if (!src) return;

  editingSourceId = sourceId;
  document.getElementById('edit-source-id').value = sourceId;
  document.getElementById('edit-source-name-input').value = src.name;
  document.getElementById('edit-source-url-input').value = src.url;
  document.getElementById('edit-source-color-input').value = src.color;
  const modalBg = document.getElementById('edit-source-modal-bg');
  if (modalBg) modalBg.classList.add('open');
}

function closeEditSourceModal() {
  const modalBg = document.getElementById('edit-source-modal-bg');
  if (modalBg) modalBg.classList.remove('open');
  editingSourceId = null;
}

function saveSourceEdit() {
  const sourceId = document.getElementById('edit-source-id').value;
  const newName = document.getElementById('edit-source-name-input').value.trim();
  const newUrl = document.getElementById('edit-source-url-input').value.trim();
  const newColor = document.getElementById('edit-source-color-input').value;
  const src = sources.find(s => s.id === sourceId);
  if (src) {
    src.name = newName;
    src.url = newUrl;
    src.color = newColor;
    refresh();
    saveToServer();
    showToast('Source updated successfully', 'success');
    closeEditSourceModal();
  }
}

function deleteSource(id, e) {
  e.stopPropagation();
  if (!confirm('Delete this source and all its bookings?')) return;
  sources = sources.filter(s => s.id !== id);
  refresh();
  saveToServer();
}

function openEditModal(bookingId, srcId) {
  const src = sources.find(s => s.id === srcId);
  const bk = src?.bookings.find(b => b.id === bookingId);
  if (!bk) return;
  
  // Use the SAME isUrlLike function from your eventContent
  const isUrlLike = (str) => {
    if (!str) return true;
    const urlPatterns = [
      /^https?:\/\//i,
      /^www\./i,
      /\.com$/i,
      /\.net$/i,
      /\.org$/i,
      /\.ics$/i,
      /\.html?$/i,
      /[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/,
      /^[a-f0-9]{32}$/i,
      /[a-zA-Z0-9_\-]{20,}/
    ];
    return urlPatterns.some(pattern => pattern.test(str));
  };
  
  // Use the SAME logic as eventContent to determine if guest name is missing
  const isGuestMissing = bk.guestNameMissing ||
    !bk.guest ||
    bk.guest === 'Guest' ||
    bk.guest === null ||
    bk.guest === '' ||
    isUrlLike(bk.guest);
  
  // Get modal elements
  const editGuest = document.getElementById('edit-guest');
  const editStart = document.getElementById('edit-start');
  const editEnd = document.getElementById('edit-end');
  const editNotes = document.getElementById('edit-notes');
  const editDates = document.getElementById('edit-dates');
  const editSrcName = document.getElementById('edit-source-name');
  const editPlatformBadge = document.getElementById('edit-platform-badge');
  const editModalTitle = document.getElementById('edit-modal-title');
  
  // Set guest name field to EMPTY if missing/invalid, otherwise show the name
  if (editGuest) {
    editGuest.value = isGuestMissing ? '' : bk.guest;
    editGuest.placeholder = 'Enter guest name...';
  }
  
  if (editStart) editStart.value = bk.start;
  if (editEnd) editEnd.value = bk.end;
  if (editNotes) editNotes.value = bk.notes || '';
  if (editDates) editDates.textContent = `${bk.start} → ${bk.end} (${nts(bk.start, bk.end)} nights)`;
  if (editSrcName) editSrcName.textContent = src?.name || '';
  
  // Update modal title - SAME logic as eventContent display
  if (editModalTitle) {
    editModalTitle.innerHTML = isGuestMissing 
      ? '<i data-lucide="user-plus" class="w-5 h-5 text-primary"></i> Add Guest Name'
      : '<i data-lucide="edit-3" class="w-5 h-5 text-primary"></i> Edit Booking';
  }
  
  // Update platform badge
  if (editPlatformBadge && src) {
    const plat = src.platform || 'other';
    editPlatformBadge.innerHTML = `${PLAT_LOGO[plat + '_small'] || PLAT_LOGO[plat] || PLAT_LOGO.other_small} ${PLAT_LABEL[plat] || 'Other'}`;
  }
  
  editingBookingId = bookingId;
  editingSrcId = srcId;
  
  const modalBg = document.getElementById('edit-modal-bg');
  if (modalBg) modalBg.classList.add('open');
  
  // Focus on guest name input if it's empty/invalid
  setTimeout(() => {
    if (editGuest && isGuestMissing) {
      editGuest.focus();
    }
  }, 100);
}

function closeEditModal() {
  const modalBg = document.getElementById('edit-modal-bg');
  if (modalBg) modalBg.classList.remove('open');
  editingBookingId = null;
  editingSrcId = null;
}

async function saveEditBooking() {
  const guest = document.getElementById('edit-guest').value.trim();
  const start = document.getElementById('edit-start').value;
  const end = document.getElementById('edit-end').value;
  const notes = document.getElementById('edit-notes').value.trim();

  // Use the SAME isUrlLike function from eventContent
  const isUrlLike = (str) => {
    if (!str) return true;
    const urlPatterns = [
      /^https?:\/\//i,
      /^www\./i,
      /\.com$/i,
      /\.net$/i,
      /\.org$/i,
      /\.ics$/i,
      /\.html?$/i,
      /[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/,
      /^[a-f0-9]{32}$/i,
      /[a-zA-Z0-9_\-]{20,}/
    ];
    return urlPatterns.some(pattern => pattern.test(str));
  };

  if (!guest || guest === '') {
    showToast('Please enter a guest name.', 'warning');
    return;
  }
  
  // Reject if guest name looks like a URL (same logic as eventContent)
  if (isUrlLike(guest)) {
    showToast('Please enter a valid guest name (not a URL or random ID).', 'warning');
    return;
  }

  if (start && end && new Date(start) >= new Date(end)) {
    showToast('Check-out date must be after check-in date', 'error');
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
  if (!confirm('Delete this booking?')) return;
  const src = sources.find(s => s.id === editingSrcId);
  if (src) {
    src.bookings = src.bookings.filter(b => b.id !== editingBookingId);
    refresh();
    await saveToServer();
    showToast('Booking deleted', 'success');
    closeEditModal();
  }
}

function contextMenuAction(action) {
  if (action === 'edit') openEditModal(contextMenuBooking.bookingId, contextMenuBooking.srcId);
  if (action === 'delete') deleteBooking();
  hideContextMenu();
}

function hideTip() {
  if (tip) {
    tipT = setTimeout(() => {
      tip.classList.add('opacity-0', 'translate-y-1.5');
      tip.classList.remove('opacity-100', 'translate-y-0');
    }, 180);
  }
}

function fmtDate(str) {
  if (!str) return '';
  const [year, month, day] = str.split('-');
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
}

function nts(s, e) {
  const start = new Date(s + 'T12:00:00Z');
  const end = new Date(e + 'T12:00:00Z');
  return Math.round((end - start) / 864e5);
}

function showTip(event, jsEvent) {
  if (!tip) return;
  clearTimeout(tipT);
  const b = event.extendedProps;

  const isUrlLike = (str) => {
    if (!str) return true;
    return /^https?:\/\//i.test(str) || /\.com$/i.test(str) || /\.ics$/i.test(str);
  };

  if (b.isAvailable) {
    const startDisplay = fmtDate(event.startStr);
    const endDisplay = b.blockEnd ? fmtDate(b.blockEnd) : '';
    tip.innerHTML = `
      <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider mb-1">
        <div class="w-2 h-2 rounded-full" style="background:#22c55e"></div>
        <span>Available</span>
      </div>
      <div class="text-sm font-bold mb-1">🟢 ${b.srcName}</div>
      <div class="text-xs text-[#5b6380] dark:text-[#8b93b5] mb-1">${startDisplay} ${endDisplay ? `→ ${endDisplay}` : ''}</div>
    `;
    const x = jsEvent.clientX, y = jsEvent.clientY;
    const right = window.innerWidth - x > 260;
    tip.style.left = (right ? x + 16 : x - 246) + 'px';
    tip.style.top = (y - 8) + 'px';
    tip.classList.remove('opacity-0', 'translate-y-1.5');
    tip.classList.add('opacity-100', 'translate-y-0');
    return;
  }

  const src = sources.find(s => s.id === b.srcId);
  const color = src?.color || '#888';
  const plat = src?.platform || 'other';
  const guestName = b.guest;
  const isGuestMissing = b.guestNameMissing || !guestName || guestName === 'Guest' || guestName === null || isUrlLike(guestName);

  const getLogoHtml = (platform) => {
    switch (platform) {
      case 'airbnb': return '<img src="assets/img/black-airbnb.png" style="width:14px;height:14px;object-fit:contain;">';
      case 'vrbo': return '<img src="assets/img/logo-new.png" style="width:16px;height:16px;object-fit:contain;">';
      case 'booking': return '<img src="assets/img/logo-booking.png" style="width:14px;height:14px;object-fit:contain;">';
      default: return '<i data-lucide="calendar" style="width:12px;height:12px;"></i>';
    }
  };

  tip.innerHTML = `
    <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider mb-1">
      <div class="w-2 h-2 rounded-full" style="background:${color}"></div>
      <span>${src?.name || PLAT_LABEL[plat]}</span>
      <span class="text-sm ml-auto">${getLogoHtml(plat)}</span>
    </div>
    <div class="text-sm font-bold mb-1 ${isGuestMissing ? 'text-amber-500' : ''}">
      ${isGuestMissing ? '👤 Add Guest Name' : guestName}
    </div>
    <div class="text-xs text-[#5b6380] dark:text-[#8b93b5] mb-1">${fmtDate(b.start)} → ${fmtDate(b.end)}</div>
    <div class="flex gap-1.5 flex-wrap">
      <div class="text-[10px] font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary">🌙 ${nts(b.start, b.end)} nights</div>
      ${b.isOverlap ? '<div class="text-[10px] font-semibold px-2.5 py-1 rounded-full bg-overlap/10 text-overlap"><i data-lucide="alert-triangle" class="w-3 w-3 inline mr-1"></i> Overlap</div>' : ''}
    </div>
  `;

  lucide.createIcons();

  const x = jsEvent.clientX, y = jsEvent.clientY;
  const right = window.innerWidth - x > 260;
  tip.style.left = (right ? x + 16 : x - 246) + 'px';
  tip.style.top = (y - 8) + 'px';
  tip.classList.remove('opacity-0', 'translate-y-1.5');
  tip.classList.add('opacity-100', 'translate-y-0');
}
function hideContextMenu() {
  const menu = document.getElementById('booking-context-menu');
  if (menu) menu.classList.add('hidden');
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

  if (!url) { showToast('Please enter an iCal URL.', 'warning'); return; }

  const btn = event.target;
  const original = btn.innerHTML;
  btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Fetching...';
  btn.disabled = true;

  try {
    const proxyUrl = `fetch_ical.php?url=${encodeURIComponent(url)}&platform=${plat}&unit=${encodeURIComponent(name)}`;
    const response = await fetch(proxyUrl);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (data.success && data.events) {
      const id = 'src-' + Date.now();
      const bookings = data.events.map(ev => ({
        id: ev.id || `${id}-${Math.random()}`,
        srcId: id,
        guest: ev.guest || 'Guest',
        start: ev.start,
        end: ev.end,
        guestNameMissing: !ev.guest || ev.guest === 'Guest',
        notes: null,
        lastEdited: null,
        platform: plat
      }));

      sources.push({
        id, name, platform: plat, color, enabled: true, url,
        showAvailableDates: false,  // Default OFF
        showBlockedDates: false,    // Default OFF
        availableColor: '#22c55e',
        blockedColor: globalSettings.blockedColor,
        bookings,
        blockedDates: data.blockedEvents || []
      });

      lastRefreshTime = new Date();
      refresh();
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

/* ════════════════════════════════════════
   RANDOM iCal URL GENERATOR
════════════════════════════════════════ */
function fillWithRandomICal() {
  const demoUrls = [
    { name: "US Holidays", url: "https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics", platform: "other" },
    { name: "UK Holidays", url: "https://calendar.google.com/calendar/ical/en.uk%23holiday%40group.v.calendar.google.com/public/basic.ics", platform: "other" },
    { name: "NBA Schedule", url: "https://www.calendarlabs.com/ical-calendar/ics/105/NBA_Schedule.ics", platform: "other" }
  ];
  const random = demoUrls[Math.floor(Math.random() * demoUrls.length)];
  document.getElementById('inp-name').value = random.name;
  document.getElementById('inp-url').value = random.url;
  document.getElementById('platform-select').value = random.platform;
  showToast(`Random ${random.platform} calendar generated!`, 'info');
}

/* ════════════════════════════════════════
   INIT
════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  loadTheme();
  loadGlobalSettings();
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
        // Handle available date blocks - WITH LOGO
        if (arg.event.extendedProps && arg.event.extendedProps.isAvailable) {
          const name = arg.event.extendedProps.srcName || 'Available';
          const platform = arg.event.extendedProps.srcPlatform || 'other';

          // Get platform logo
          let logoHtml = '';
          switch (platform) {
            case 'airbnb':
              logoHtml = '<img src="assets/img/black-airbnb.png" style="width:14px;height:14px;object-fit:contain;">';
              break;
            case 'vrbo':
              logoHtml = '<img src="assets/img/logo-new.png" style="width:18px;height:18px;object-fit:contain;">';
              break;
            case 'booking':
              logoHtml = '<img src="assets/img/logo-booking.png" style="width:14px;height:14px;object-fit:contain;">';
              break;
            default:
              logoHtml = '<i data-lucide="calendar-check" class="w-3 h-3" style="color:#fff;"></i>';
              break;
          }

          return {
            html: `<div style="display:flex; align-items:center; gap:6px; padding:2px 6px; background:${arg.backgroundColor}; border-radius:4px;">
        <div style="width:20px;height:20px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${logoHtml}</div>
        <span style="color:#fff; font-size:10px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(name)}</span>
      </div>`
          };
        }

        // Handle booking events - WITH LOGO AND GUEST NAME
        const b = arg.event.extendedProps;
        const src = sources.find(s => s.id === b?.srcId);
        const platform = src?.platform || 'other';
        let guestName = b?.guest;
        const isOv = b?.isOverlap;

        // Function to check if string looks like a URL
        const isUrlLike = (str) => {
          if (!str) return true;
          const urlPatterns = [
            /^https?:\/\//i,
            /^www\./i,
            /\.com$/i,
            /\.net$/i,
            /\.org$/i,
            /\.ics$/i,
            /\.html?$/i,
            /[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/,
            /^[a-f0-9]{32}$/i,
            /[a-zA-Z0-9_\-]{20,}/
          ];
          return urlPatterns.some(pattern => pattern.test(str));
        };

        // Check if guest name is missing, null, empty, or looks like a URL
        const isGuestMissing = b?.guestNameMissing ||
          !guestName ||
          guestName === 'Guest' ||
          guestName === null ||
          guestName === '' ||
          isUrlLike(guestName);

        // Get platform logo
        let logoHtml = '';
        switch (platform) {
          case 'airbnb':
            logoHtml = '<img src="assets/img/black-airbnb.png" style="width:12px;height:12px;object-fit:contain;">';
            break;
          case 'vrbo':
            logoHtml = '<img src="assets/img/logo-new.png" style="width:16px;height:16px;object-fit:contain;">';
            break;
          case 'booking':
            logoHtml = '<img src="assets/img/logo-booking.png" style="width:12px;height:12px;object-fit:contain;">';
            break;
          default:
            logoHtml = '<i data-lucide="calendar" style="width:12px;height:12px;color:#000;"></i>';
            break;
        }

        let displayText = '';

        // Show property name + guest name or "Add Guest Name"
        if (isGuestMissing) {
          displayText = `${src?.name || 'Property'} (Add Guest Name)`;
        } else if (guestName && guestName !== 'Guest') {
          displayText = `${src?.name || 'Property'} (${escapeHtml(guestName)})`;
        } else {
          displayText = src?.name || 'Property';
        }

        // Escape HTML to prevent XSS
        function escapeHtml(str) {
          if (!str) return '';
          return str.replace(/[&<>]/g, function (m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
          });
        }

        return {
          html: `<div style="display:flex; align-items:center; gap:6px; padding:2px 6px; background:${arg.backgroundColor}; border-radius:4px; ${isOv ? 'box-shadow:0 0 0 2px #ef4444;' : ''}">
      <div style="width:20px;height:20px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${logoHtml}</div>
      <span style="color:#fff; font-size:11px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
        ${displayText}
      </span>
      ${isOv ? '<i data-lucide="alert-triangle" class="w-3 h-3" style="color:#fff;"></i>' : ''}
    </div>`
        };
      },
      eventDidMount: (info) => {
        lucide.createIcons();
        setTimeout(() => addBlockedDateIndicators(), 10);
      },
      eventClick: ({ event }) => {
        const b = event.extendedProps;
        if (!b.isAvailable) openEditModal(b.id, b.srcId);
      },
      dayCellDidMount: ({ date, el }) => {
        const dateStr = date.toISOString().split('T')[0];
        el.addEventListener('mouseenter', (e) => {
          if (e.target.classList && e.target.classList.contains('blocked-indicator')) return;
          showDateEventCountTooltip(e, dateStr);
        });
        el.addEventListener('mouseleave', () => hideTip());
        const vis = allVisible();
        const ovDates = new Set();
        detectOverlaps(vis).forEach(({ a, b }) => {
          let d = new Date(a.start < b.start ? a.start : b.start);
          const end = new Date(a.end > b.end ? a.end : b.end);
          while (d < end) {
            ovDates.add(d.toISOString().split('T')[0]);
            d.setDate(d.getDate() + 1);
          }
        });
        if (ovDates.has(dateStr)) el.style.background = 'rgba(245,158,11,0.1)';
      }
    });
    calendar.render();
    loadFromServer();
  }
});

// Fix existing bookings with URL-like guest names
function fixExistingInvalidGuestNames() {
  let fixedCount = 0;
  
  sources.forEach(src => {
    src.bookings.forEach(booking => {
      const guest = booking.guest;
      
      // Check if guest name is a URL
      const isUrlLike = guest && (
        guest.startsWith('http') ||
        guest.includes('.com') ||
        guest.includes('.ics') ||
        /[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/.test(guest) ||
        /^[a-f0-9]{32}$/i.test(guest) ||
        guest.length > 40
      );
      
      if (isUrlLike) {
        console.log(`Fixing: "${guest.substring(0, 50)}..." → null`);
        booking.guest = null;
        booking.guestNameMissing = true;
        fixedCount++;
      }
    });
  });
  
  if (fixedCount > 0) {
    saveToServer();
    refresh();
    console.log(`✅ Fixed ${fixedCount} bookings with URL-like guest names`);
  } else {
    console.log('✅ No invalid guest names found');
  }
}

fixExistingInvalidGuestNames();

console.log('New Script Loaded');