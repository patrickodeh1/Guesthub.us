# StaySync — Multi-Platform Rental Calendar

## Demo (directly in browser)
Open the `index.html` file in your browser → click **Load Sample Data** to see the full demo.

## Deploy to PHP Server

### Requirements
- PHP 7.4+
- `allow_url_fopen = On` (in php.ini)
- cURL or `file_get_contents` enabled

### How to Upload
1. Upload all files to your hosting (cPanel/Niagahoster/etc)
2. Open `index.html` in your browser
3. Click **+ Add iCal** → enter the iCal URL from Airbnb/VRBO/Booking.com

### How to Get iCal URL from Platforms

**Airbnb:**
Go to → Hosting → Calendar → Availability → Export Calendar → Copy .ics link

**VRBO:**
Go to → Dashboard → Calendar → Calendar Synchronization → Export .ics

**Booking.com:**
Go to → Property → Calendar → iCal Export

## Features
- ✅ Multi-platform visual calendar
- ✅ Automatic overlap detection (yellow color + alert)
- ✅ Guest names from each platform
- ✅ Only display active reservations (blocked dates ignored)
- ✅ Per-platform toggle (on/off)
- ✅ Monthly statistics
- ✅ Views: Month / Week / List
- ✅ Tooltip details on event hover

## Stack
- **Frontend:** Pure HTML + CSS + JavaScript + FullCalendar.js 6
- **Backend:** PHP (server-side iCal fetch, bypass CORS)
- **Database:** Not required
