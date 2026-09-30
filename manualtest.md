# Manual test checklist

These checks are pending and must be completed in an authenticated browser.

- [ ] At 375px wide, open and close the sidebar on one Guest Admin page and one Cleaning Ops checklist page using the same control; confirm neither page has horizontal scrolling.
- [ ] On a Cleaning Ops checklist page, exercise the photo/camera flow and confirm the captured photo is saved and displayed.
- [ ] Check the browser console on one Guest Admin page and one Cleaning Ops checklist page; confirm there are no JavaScript errors.

## D008 acceptance (user-run browser checks)

- [ ] Sign in as admin, company, owner, manager, staff, housekeeper, and viewer; verify each lands on `/dashboard`, sees only appropriate links, and every visible link works without a 403 or redirect loop.
- [ ] Compare light/dark with default, very light, and very dark brand colors on `/dashboard`, `/admin/properties`, a property edit page, a session checklist, `/admin/settings`, and `/login`.
- The automated screenshot attempt captured `/tmp/d008-screenshots/001-light-admin-login-page-desktop.png`, then stopped because the local preview credentials were unavailable. No authenticated screenshots or role click-throughs are claimed.
