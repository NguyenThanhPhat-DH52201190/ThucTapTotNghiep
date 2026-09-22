# Responsive UI

The authenticated pages share `layouts/app.blade.php`, `public/css/responsive.css`
and `public/js/responsive.js`. Login and registration use the same stylesheet.
The PDF template has its own print layout.

- Below 992 px, navigation uses Bootstrap offcanvas (button, backdrop, Escape,
  focus management). Desktop navigation scrolls independently.
- Forms and toolbars wrap on small screens. Modals keep their header/footer
  visible and scroll their body within the viewport height.
- Wide tables scroll inside their container; missing legacy wrappers are added
  on page load. Overflowing tables are keyboard-focusable.
- Master Plan stops pinning columns below 1200 px so they cannot obscure the
  other columns. Native horizontal scrolling remains available.
- Material autocomplete is constrained to the viewport. Image previews already
  support tap as well as hover.

## Deployment

Deploy both `public/css/responsive.css` and `public/js/responsive.js` together
with the updated Blade templates, then run `php artisan view:clear`.
No new production dependency, migration or npm build is required. Asset URLs
include the file modification time to refresh browser caches on deployment.

## Manual acceptance checks

Automated checks on local rendered data: 52 pages at 320, 375, 768, 1024
and 1440 px, with no document horizontal overflow; 19 modals at 320 px stayed
inside the viewport without horizontal body overflow. Menu open/Escape close
was exercised in headless Edge. Existing PHP suite: 55 tests, 419 assertions.
This does not replace touch/virtual-keyboard checks on physical phones.

Use 320/375 px phones, a 768 px tablet, a 1024 px laptop and a 1440 px desktop.

1. Open/close navigation with the menu button, backdrop and Escape; tab through
   its links. Resize across 992 px while the menu is open.
2. Review OCS, BOM, NORM, Material Master, Inventory, Stock Records, Procurement,
   Master Plan, Production Planning, MRP, Shop Floor and Finance/Revenue.
   Only the table should scroll horizontally, not the whole document.
3. Create/edit forms: labels, inputs, validation messages and save buttons remain
   reachable with a phone keyboard visible. Test long codes and descriptions.
4. Open Material, taxonomy, mapping, inventory and Receive goods modals. Scroll
   to the last field, add receipt rows, and reach Save/Post receipt and Close.
5. Scroll Master Plan to the final columns; tap an image trigger in OCS/BOM/NORM;
   check BOM material suggestions near the right edge of the screen.
6. Check login/registration, pagination and report charts. Confirm desktop
   tables, sidebar and actions remain usable.

Existing unrelated routes found during the page audit: `/admin/dashboard`
references missing view `admin.dashboard`; `/admin/finance/revenue` references
missing controller method `revenueReport`. These require separate functional fixes.
