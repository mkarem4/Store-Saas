# Dashboard UI review checklist

Review of every Filament page in the three panels (admin, business, staff), done 2026-11-23.
"Checked" means opened in the browser in Arabic (RTL); the mobile column is a 390 px width check.

## Shared (all panels)

| Area | Change | Status |
|---|---|---|
| Shell | One layout for all panels: quiet sidebar on a neutral canvas, page as one white panel, no topbar. Admin accent is monochrome; store panels keep ink blue | Done, checked light and dark |
| Type | Geist for Latin and figures, Readex Pro for Arabic | Done |
| Brand | Mark with the panel's first letter beside the panel or store name | Done |
| Account menu | Language switch (EN / العربية) moved into the account menu | Done |
| Selects and dates | Filament dropdowns and calendars everywhere, no native controls; required selects have no clear button | Done |
| Modal buttons | The submit button repeats the action's own label instead of "Submit" (إرسال) | Done |
| Lists | Search, filters and sort persist when you come back from a record | Done |
| Forms | Warning before leaving a page with unsaved changes | Done |
| Navigation | SPA mode, so pages change without a full reload. Language, impersonation, receipts and exports still do a full load | Done, checked |
| Motion | Shared duration and easing tokens. Color and shadow transitions on controls, a slide for the mobile sidebar, `prefers-reduced-motion` respected | Done |
| Dark mode | Admin primary buttons turn white so they stay visible; avatars get a ring | Done, checked |
| Translation | 38 strings missing from Filament's own Arabic files added under `lang/vendor`; labels derived from field names now go through the translator | Done |

## Pages

| Panel | Page | Issues found | Status | Checked | Mobile |
|---|---|---|---|---|---|
| Admin | Dashboard | Five stats wrapped into two rows; they also polled every 5 s | Fixed | Yes | Yes |
| Admin | Stores (list) | Name and type showed twice (EN, AR) | Fixed | Yes | Yes |
| Admin | Store create / edit | Contact section cramped in 3 columns | Fixed (2 columns) | Yes | Yes |
| Admin | Business types | "Order" read as "الطلب" (purchase order) | Fixed ("ترتيب العرض") | Yes | — |
| Admin | Users, Customers, Payout requests, Tax rates, Audit log, Platform settings | Filter labels in English ("Status", "Role", "Country") | Fixed | Yes | — |
| Admin | Roles (Shield) | Platform roles had no effect: only the owner can enter the admin panel and the owner passes every check | Removed from the admin panel | — | — |
| Business | Dashboard, Reports | — | OK | Yes | Yes |
| Business | POS | Weight, options and discount modals said "Submit"; cart offset was built for the old topbar | Fixed | Yes | Yes |
| Business | Products (list / create / edit) | Relation tables showed English model names ("variant", "serial number", "option group") | Fixed | Yes | Yes |
| Business | Orders (view) | Crashed with a server error (`TextEntry::description` doesn't exist in Filament 5) | Fixed (`belowContent`) | Yes | Yes |
| Business | Orders (list) | Tabs had an untranslated label for screen readers | Fixed | Yes | — |
| Business | Sales, Returns | — | OK | Yes | Yes |
| Business | Categories | Sort field label | Fixed | Yes | — |
| Business | Wallet | Empty state read "لا يوجد wallet transactions" | Fixed, plus a description | Yes | Yes |
| Business | Gold prices | Table model label in English | Fixed | Yes | — |
| Business | Vehicles (edit) | Costs table read "إضافة vehicle cost" | Fixed | Yes | — |
| Business | Rentals, Employees, Attendance, Payroll, Expenses, Suppliers, Clients, Inquiries, Stock, Receiving, Withdrawals, Payout accounts, Settings | — | OK | Yes | Partly |
| Staff | Time clock, POS, My hours, Sell vehicle | Table model labels in English | Fixed | Yes | Yes |

## Not verified or left as is

- Creating, editing and deleting records was not run end to end in the browser. Those flows are covered by the Pest suite (100 tests).
- FilePond's hidden "Files" label for screen readers is hard-coded inside the library.
- Product and variant names in the demo data ("Regular", "12pcs") are store data, not interface text.
- "SKU", "VIN" and "Excel" are kept as recognised technical terms.
