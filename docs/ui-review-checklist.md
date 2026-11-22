# Dashboard UI review checklist

This follows `docs/design-prompt.md`: Shopify Admin / Polaris-style layout with Yulio's identity, built on Filament 5.
Last pass on 2026-11-23. "Checked" means opened in the browser.

## Shared components

| Area | Decision | Status |
|---|---|---|
| Shell | Quiet sidebar (248 px) on a neutral canvas, page as one white panel, no second topbar. Language and account sit in the account menu | Done, checked in Arabic, English, light, dark, 390 / 768 / 1440 px |
| Colors | Canvas `#F4F4F5`, surface white, Zinc neutrals. Primary is ink `#1E40AF` for stores and staff, `#18181B` for the admin. In dark mode the primary becomes `#93C5FD` with `#172554` text for stores, and white with dark text for the admin | Done |
| Type | Geist for Latin and figures, Readex Pro for Arabic. Arabic tables, inputs and sidebar at 15 px | Done |
| Controls | Fields and buttons at 40 px, 44 px on touch screens. 8 px corners for controls, 12 px for containers | Done |
| Forms | Create and edit pages capped near 1040 px. Settings pages use the Polaris pattern of title and description beside the fields | Done |
| Tables | One empty state that tells "nothing yet" apart from "no results for this search or filter", with a clear button. Search, filters and sort persist | Done, test added |
| Record view | Viewing from a list opens a side drawer that follows RTL / LTR | Done |
| Modal buttons | The submit button repeats the action's own label instead of "Submit" | Done |
| Values | Phone numbers isolated LTR (`App\Support\Ltr`) so they don't reverse inside Arabic | Done, checked |
| Motion | Tokens of 150 / 200 / 260 ms with enter and exit easing. Color and shadow transitions only, no page-level transform (it breaks fixed modals). `prefers-reduced-motion` respected | Done |
| Navigation | SPA mode. Language, impersonation, receipts and exports still do a full load | Done |

## Reference screens

| Screen | What changed | Checked |
|---|---|---|
| App orders (list) | Tabs: "Needs action" and "In progress" with real counts. Default is "Needs action" when there are pending orders, otherwise "In progress". Columns: number, time with exact-time tooltip, customer with phone, fulfillment, total with item count, payment (card icon) kept apart from order status. Accept now confirms that the card is charged and shows the amount. Each tab has its own empty state. Polling and the chime are unchanged | Yes, light and dark |
| Product form | Main column holds Details, Media, Price & stock (on create) and Specifications. Side column holds Status and Organisation. "Store-type fields" renamed "Specifications". Type fields that duplicate real product columns (prep time, availability, add-ons) are no longer shown twice | Yes |
| POS | Catalog about 2/3 and receipt about 1/3, mirrored by language. On phones a sticky bar shows the item count and total and jumps to the cart. On devices with a mouse, the search field gets focus back after each add, so scanning continues; touch screens skip this to avoid popping the keyboard | Yes, desktop and 390 px |

## Other pages

| Panel | Page | Change | Checked |
|---|---|---|---|
| Admin | Dashboard, Stores, Business types, Users, Customers, Payout requests, Tax rates | Earlier pass covered the shell, filters and duplicate names. Phone numbers now LTR | Yes |
| Admin | Store create | Owner help text no longer shows the `/business` URL | Yes |
| Admin | Platform settings | Polaris settings layout with a description per section | Yes |
| Admin | Audit log | Event keys (`vehicle.sold`) and class names (`Business #1`) shown as words in both languages (`lang/*/activity.php`). "Record" column renamed "العنصر". Own empty state | Yes. No local events exist, so the drawer with real data was not seen |
| Admin | Roles (Shield) | Not registered in the admin panel; it was removed in the previous round and not brought back | — |
| Business | Store settings | Settings layout. Opening hours collapse to one column on phones | Yes |
| Business | Inquiries | Details open in a 2-column drawer. Status stays editable inline | Yes |
| Business | Clients, Suppliers | Phone LTR | Yes |
| Business | Rentals | Deposit was already kept apart from revenue ("Held, not revenue") | Yes |
| Business | Remaining pages | Shared rules applied, no page-specific issue found | Yes, sweep |
| Staff | Time clock, POS, My hours, Sell vehicle | Larger touch targets. No cost or profit shown (rule 6) | Yes |

Sweep: 49 page URLs × Arabic at 768 px and English at 1440 px. All returned 200, with no raw translation keys and no horizontal overflow.

## Translation log

| Screen | Visible text | Source | Fix | Verified |
|---|---|---|---|---|
| Orders | "مصرح به" (Authorized) | `PaymentStatus` enum | Plain states: "المبلغ محجوز / مدفوع / أُلغي الحجز / فشل الدفع / بانتظار الدفع" | Arabic and English |
| POS phone bar | "صنفان" for one item | 5-form Arabic plural (Laravel uses 6) | Explicit ranges `{0}…{1}…{2}…[3,10]…[11,*]` | tinker for 1 / 2 / 3 / 11 |
| Audit log | `vehicle.sold`, `Business #1`, `stores` | Raw keys and class names | `lang/{en,ar}/activity.php` | Code. No local events to see |
| Audit log | "تسجيل" for Record | Ambiguous key | "Affected item" / "العنصر" | Arabic |
| Store create | `/business` in help text | Technical URL | "store dashboard" / "لوحة المحل" | Arabic |
| Settings, orders, product form | New descriptions and labels | New strings | Added to `lang/ar.json` | Arabic |
| Earlier round | 38 Filament strings, filter labels, model labels, modal buttons | `lang/vendor`, `translateLabel()` | See earlier entries in git history | Sweep |

Kept as is: SKU, VIN, Excel and USD (recognised terms); user names such as "Platform Owner" (account data, not interface text); product names from demo data.

## Not verified or left as is

- Creating, editing, deleting and money flows were not run end to end in the browser; the 101 Pest tests cover them.
- The audit-log drawer with real events (none in the local database).
- Dark mode was checked on the order list, admin lists and POS, not on every page.
- FilePond's hidden "Files" label for screen readers is inside the library.
