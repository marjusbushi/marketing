# Daily Basket — Product → Day Distribution

**Status:** Approved (design phase)
**Date:** 2026-04-18
**Codebases affected:** `za-dis` + `za-marketing`
**Mockups:**
- [mockup-daily-basket-day-distribution.html](../../../mockup-daily-basket-day-distribution.html) — DIS inline picker + Daily Basket panorama (side by side)
- [mockup-dis-quick-scan-basket.html](../../../mockup-dis-quick-scan-basket.html) — DIS Quick Scan workflow (palmar)

---

## 1. Context

Sot, ne `za-marketing/daily-basket`, te gjitha produktet e nje kolekcioni shfaqen te disponueshme ne **cdo dite** te asaj jave. Marketing-u zgjedh manualisht produktet kur krijon nje post; nuk ka asnje sugjerim/orientim qe i thote "keto produkte duhet te marketosh sot".

Pivot-i `distribution_week_item_group` (DIS) lidh nje grup me nje jave, por nuk ka dimension "dite". Daily basket eshte vetem nje grupim postesh per dite mbi te njejtin pool produktesh.

## 2. Goal

Te kete merch-team, kur planifikon kolekcionin ne DIS, te caktoje cdo grup produkti per nje (ose me shume) dite te kolekcionit. Marketing-u, kur hap nje dite te shportes ditore, te shoh nje **panorame** vetem te produkteve te caktuara per ate dite, me filtra dhe stats si tek merch-calendar sidebar. Caktimi eshte **orientim**, jo strict — marketing-u brenda postit zgjedh vete cilat ngjyra/variante te perdore.

## 3. Non-goals

- **Kufizim strict per marketing** — caktimi nuk pengon marketing-un te perdore produktin ne nje dite tjeter.
- **Caktim ne nivel variante/SKU** — caktimi eshte vetem ne nivel `item_group`.
- **Auto-distribute algoritem** — sistemi nuk shperndan vete produktet (refuzim eksplicit i opsioneve B/C te brainstorm-it).
- **Migrim te kolekcioneve ekzistuese** — sjellja e vjeter mbetet si fallback per to.

## 4. Architecture

### 4.1 Cross-DB layout (e pandryshuar)

- DIS = **source of truth** per: distribution_weeks, item_groups, distribution_week_item_group (klasifikim, AI looks).
- za-marketing = **konsumues** i DIS via `DisApiClient` (HTTP API te brendshme), me write-back te ri vetem per caktimin e dates (shih 4.4).
- Tabelat `daily_baskets`, `daily_basket_posts`, `daily_basket_post_products` mbeten te pandryshuara.

### 4.2 Schema change ne DIS

**Pivot e re:** `distribution_week_item_group_dates`

| kolone | tip | constraint | shenim |
|---|---|---|---|
| `id` | `bigint unsigned` | PK auto-increment | |
| `distribution_week_id` | `bigint unsigned` | FK → `distribution_weeks.id`, cascade on delete | |
| `item_group_id` | `bigint unsigned` | FK → `item_groups.id`, cascade on delete | |
| `date` | `date` | NOT NULL | dita e caktuar |
| `is_primary` | `boolean` | default `true` | `true` = caktim kryesor; `false` = re-marketing |
| `created_by` | `bigint unsigned` | nullable, indexuar | user qe e shtoi |
| `created_at` / `updated_at` | timestamp | | |

**Indekse:**
- Unique: `(distribution_week_id, item_group_id, date)` — nje grup nuk mund te caktohet 2 here per te njejten dite te te njejtit kolekcion.
- Composite indeks: `(distribution_week_id, date)` — pyetja "cilet produkte per kete dite" eshte O(log n).
- Composite indeks: `(item_group_id, date)` — pyetjen "ne cilat dite eshte ky produkt" e ben e shpejte (per UI inline).

**Migration validation:**
- `date` MUST jete brenda `[distribution_weeks.week_start, distribution_weeks.week_end]` te te njejtit week. Kontrollohet ne aplikacion (jo CHECK constraint, sepse MySQL <8.0.16 nuk e suporton).

**Pivot ekzistues `distribution_week_item_group` mbetet i pa-prekur** — klasifikimi dhe AI looks vazhdojne aty.

### 4.3 API ne DIS

**1) Endpoint i ri per caktim manual (UI inline):**
```
POST   /api/internal/distribution-weeks/{week}/groups/{group}/dates
       body: { date: "2026-04-24", is_primary: true }
       returns: { id, date, is_primary }

DELETE /api/internal/distribution-weeks/{week}/groups/{group}/dates/{dateId}
       returns: { success: true }
```

**2) Endpoint i ri per Quick Scan (bulk save):**
```
POST   /api/internal/distribution-weeks/{week}/quick-scan
       body: {
         date: "2026-04-24",
         item_group_ids: [101, 102, 103, ...],
         auto_attach_to_collection: true   // shton ne distribution_week_item_group nese mungon
       }
       returns: {
         saved: 4,
         added_to_collection: 1,           // sa u shtuan ne kolekcion automatikisht
         skipped_duplicates: 0,
         errors: []
       }
```

**3) Barcode lookup — riperdorim endpoint-in ekzistues, e zgjerojme:**

Ekziston tashme `GET /management/distributable-items/search-barcode?barcode=XXX` ([ItemsController::searchDistributableItemsByBarcode](../../../../za-dis/app/Http/Controllers/Management/ItemsController.php:2318)).

Sot kthen:
```json
{ "success": true, "data": { "cf_group": "ZA-001", "total_skus": 4 } }
```

E zgjerojme te ktheje **edhe `item_group_id`** dhe `name`, `image_url`, `classification` qe quick-scan UI t'i ka per cdo skanim:
```json
{
  "success": true,
  "data": {
    "cf_group": "ZA-001",
    "total_skus": 4,
    "item_group_id": 101,
    "name": "Bluze veri klasik",
    "image_url": "https://web-cdn.../...jpg",
    "classification": "best_seller",
    "vendor_name": "Levi's",
    "avg_price": 45,
    "total_stock": 42
  }
}
```

Ndryshimi eshte **non-breaking** — fushat ekzistuese mbeten te njejta. Permission-i `ItemPolicy::VIEW_ANY_GROUPED` mbetet i njejti.

**4) `GET /api/internal/distribution-weeks/{week}` zgjerohet** — `item_groups[].assigned_dates` shtohet:
```json
{
  "id": 5,
  "name": "Whispers of April",
  "item_groups": [
    {
      "id": 101,
      "name": "Bluze veri klasik",
      "assigned_dates": [
        { "id": 1, "date": "2026-04-24", "is_primary": true },
        { "id": 2, "date": "2026-04-28", "is_primary": false }
      ],
      ...
    }
  ]
}
```

### 4.4 Cross-DB write nga za-marketing

za-marketing **nuk shkruan** ne pivot-in e ri. Caktimi behet ekskluzivisht ne DIS UI; za-marketing vetem **lexon** datat permes API-t te zgjeruar.

### 4.5 Filtrimi ne za-marketing

`DailyBasketController::loadCollectionProducts()` zgjerohet me parameter `?date=YYYY-MM-DD`. Kur thirret me date, kthehen vetem produktet me `assigned_dates` qe permbajne ate date.

`DailyBasketController::show()` thirr `loadCollectionProducts($weekId, $date)` ne vend te `loadCollectionProducts($weekId)`.

**Backwards-compat fallback:** Nese pivot-i `distribution_week_item_group_dates` ka **0 caktime** per `week_id` te dhene, kthen pool-in e plote (sjellja aktuale). Pasi te dale ndokush nje caktim, kalon ne mode "vetem te caktuarat".

---

## 5. UX flows

### 5.1 DIS — Inline date picker per produkt

Vendndodhja: `resources/views/.../merch-calendar/_partials/collection-sidebar.blade.php` (DIS).

Per cdo karte produkti ne sidebar:
- Sot: `[image] [emer + meta] [cmim + stok]`
- E re: + rresht ne fund `📅 [date pill] [+1 re-marketing]`

State 1 — pa caktim: butoni `📅 + Cakto dite per kete produkt` (i tere width). Klik → mini-calendar dropdown qe shfaq vetem ditet brenda `[week_start, week_end]` te kolekcionit. Klik nje dite → POST endpoint, pill mbushet, butoni `+1` shfaqet.

State 2 — me caktim primary: pill `[E Hene · 24 Pri ×]` + butoni `+ dite re-marketing`. Klik mbi `×` → DELETE. Klik mbi pill → menu (Ndrysho / Hiq).

State 3 — me primary + re-marketing: pill primary (blu) + pill ri-marketim (portokalli, ikona 🔁). Cdo pill ka `×` te vetin.

**Refuzimi sjell error inline ne kartë**, jo modal — UX i qete.

### 5.2 DIS — Quick Scan (palmar)

Vendndodhja: faqe e re ne DIS, e arritshme nga butoni `📷 Quick Scan` ne header te collection-sidebar.

Routes te reja:
```
GET  /merch-calendar/quick-scan?week={id}&date={ymd}   → page
```

Faqja:
- **Top bar:** Back / Title / Cancel / Save
- **Context bar:** Collection selector + Day selector (default sot ose dita e parë e javes)
- **Scan input:** input i madh, `autofocus`, listener per `keydown Enter` → process barcode
- **Last-scan feedback** (4 raste): success / already-in-list / not-in-collection (auto-add) / not-found
- **Scanned list:** rreshta cards me numer renditjeje, image, kod, klasifikim, cmim, butoni Hiq. Karta e re ka flash jeshil.
- **Sticky save bar** poshte: counter + Save button.

**Sjellja e skanimit:**
1. Barcode → `GET /api/internal/items/by-barcode/{barcode}` → merr `item_group_id`.
2. Kontrollon nese eshte tashme ne lokal state (warning, kapercehet).
3. Shton ne lokal state (jo direkt ne DB) — **i tere sesioni eshte unsaved deri te Save**.
4. Visual feedback me animation 1.2s.

**Save:**
- POST `/api/internal/distribution-weeks/{week}/quick-scan` me te gjitha `item_group_ids` + `date`.
- Backend hap nje DB transaction, per cdo grup:
  - Nese grupi **eshte tashme** ne `distribution_week_item_group` → insert me `is_primary=true`.
  - Nese grupi **NUK eshte** ne `distribution_week_item_group` → e shton (auto-attach), pastaj insert me `is_primary=false` (i shenjuar si **re-marketing** sepse nuk ishte planifikuar fillimisht per kete kolekcion).
  - ON DUPLICATE KEY → skip silently.
- Kthen permbledhje (saved, added_to_collection_as_remarketing, skipped_duplicates).

**Pse re-marketing per produktet jashte kolekcionit:** kur skanon nje produkt qe nuk ishte planifikuar per kete kolekcion, biznesi i trajton si "rifurnizim/re-promovim" — keshtu e bejme te dukshem ne UI me badge `🔁 Ri-marketim`, jo si caktim primary.

**Edge cases te trajtuara:**
- Barcode jo i njohur → error inline, sesioni vazhdon.
- Produkt tashme i caktuar per kete dite → skipped silently me toast "tashme i caktuar".
- Connection failure ne save → state ruhet ne `localStorage` qe te mos humbase puna.

### 5.3 za-marketing — Daily Basket Panorama

Vendndodhja: `resources/views/daily-basket/index.blade.php`.

Sot: kur user-i kliko nje dite te `db-days`, hapet vetem Kanban-i. Sidebar nuk ekziston per kolekcionin.

E re: pasi user-i klikon nje dite, nje **butoni "📦 Panorama produktet"** (ose ikon ne header) hap nje sidebar 420px te djathte (re-use ~90% te `collection-sidebar.blade.php` te merch-calendar):

- **Header:** "E Hene · 24 Pri 2026" + emer kolekcioni + butoni Mbyll
- **Mini strip:** ditet e javes me numer postesh per cdo dite (re-use logjike nga `dbDays`)
- **Stats 4 kolona:** Caktuar sot / Stok / Vlere / Posts
- **Filter tabs:** Te gjitha · Best · Karrem · Fashion · Plotesues
- **Karta produkti:**
  - Image + emer + kod + klasifikim badge
  - Badge e re `🔁 Ri-marketim` nese `is_primary=false` per kete dite
  - Badge e re `✓ Ne N posts` (jeshil) ose `Pa post ende` (gri me dash)
- **Klik karta:** modal i njejte si tek merch-calendar (zoom + detaje)

**Empty state** (per kolekcionet pa caktime — fallback aktiv):
> "Caktimi behet nga merch-i ne DIS. Nese kolekcioni ende s'ka caktime, kjo zone shfaq **te gjithe** produktet e kolekcionit."

---

## 6. Edge cases & decisions

| Rast | Vendim |
|---|---|
| Kolekcion pa caktime ende | Fallback: shfaq pool-in e plote (backwards-compat) |
| Dite jashte kolekcionit zgjedhet ne picker | UI nuk e lejon (disabled) |
| I njejti grup caktohet 2 here per te njejten dite | Unique index e ndalon, UI fsheh duplicate-t |
| Nje dite ka 0 produkte caktuar | Shfaq mesazh "Asnje produkt per kete dite" + buton "Hap shporten te plotë" |
| Quick scan: produkt jashte kolekcionit | Auto-attach ne kolekcion + cakto per diten me `is_primary=false` (re-marketing) |
| Quick scan: ndryshim dite ne mes te sesionit | UI nuk e lejon — sesioni eshte per **nje dite te vetme**. Per dite tjeter, ruaj sesionin aktual dhe hap nje te ri. |
| Quick scan: connection lost ne mes | localStorage ruan state-in deri ne save te suksesshem |
| User heq caktimin e fundit te nje produkti per ate dite | Pivot row fshihet; produkti nuk shfaqet me ne panorame |
| Re-marketing pa primary | Lejohet (rast i rralle, p.sh. produkti caktohet vetem per re-promovim) |
| Daily basket post permban produkt qe nuk eshte i caktuar per ate dite | Lejohet — caktimi eshte orientim, jo ndalim |

---

## 7. Implementation outline

Pjesa **DIS (`za-dis`):**

1. Migration: `create_distribution_week_item_group_dates_table`
2. Model: `DistributionWeekItemGroupDate` me relacionet
3. Controller: `MerchCalendarApiController::storeGroupDate`, `destroyGroupDate`, `quickScanSave`, `lookupByBarcode`
4. Route: shtohen ne routes ekzistuese te brendshme
5. View update: `_partials/collection-sidebar.blade.php` — shtohet date-row per cdo karte
6. View e re: `merch-calendar/quick-scan.blade.php` — full-screen workflow
7. Test: feature tests per cdo endpoint + edge cases

Pjesa **za-marketing:**

1. `DailyBasketController::loadCollectionProducts($weekId, $date = null)` — filter optional
2. `DailyBasketController::show()` thirr filtrimin me date
3. `DisApiClient::getWeek()` — verifiko qe `assigned_dates` vjen ne payload
4. View update: `daily-basket/index.blade.php` — shtohet sidebar panorama (re-use sidebar-in e merch-calendar)
5. Test: backwards-compat me kolekcione ekzistuese + me kolekcione te reja

---

## 8. Out of scope / future

- **Bulk-edit dite** ne quick scan (zgjedh disa produkte → ndrysho dite).
- **Auto-suggestions** bazuar ne shitjet ose AI looks.
- **Caktim ne nivel variante/ngjyre.**
- **Audit trail** per kush e caktoi/hoqi (mund te shtohet `created_by`, por logjika historike jo).
- **Notification** per marketing kur merch ndryshon caktim per nje dite te ardhshme.

---

## 9. Decisions resolved (post brainstorm 2026-04-18)

1. ~~Barcode → item_group lookup~~ → **Ekziston** `GET /management/distributable-items/search-barcode`. E zgjerojme me `item_group_id` + meta-data (shih 4.3.3). Non-breaking change.
2. ~~Quick scan auto-attach~~ → **Po**: produkti shtohet ne kolekcion **dhe behet automatikisht re-marketing** (`is_primary=false`). UI e shenon me badge `🔁 Ri-marketim`.
3. ~~Multi-day session~~ → **Jo**: nje sesion = nje dite. UI nuk e lejon ndryshimin e dites pasi te kete filluar skanimi; ruajtja mbyll sesionin, butoni "Hap diten e radhes" hap sesion te ri.

