# Visual Studio — Editor foto/video i integruar me AI për /marketing

**Data:** 2026-04-21
**Autor:** Marjus Bushi + Claude (Opus 4.7)
**Status:** Draft për shqyrtim
**Projekti:** za-marketing (Laravel 11)

## 1. Problema

Stafi i marketingut sot krijon përmbajtje jashtë aplikacionit:
- **Foto/Carousel/Stories** → Canva (abonime, export/import manual, pa brand kit të ndarë)
- **Reel/Video** → CapCut (vetëm në mobile/desktop, jo integrim)
- **Captions + hashtags** → manualisht ose me ChatGPT të veçantë, jo bazuar në produkte reale

Rezultati: context switching, kohë e humbur, brand drift (secili anëtar përdor font/ngjyrë pak ndryshe), dhe asnjë lidhje automatike midis daily-basket (produkti i ditës) dhe përmbajtjes përfundimtare.

## 2. Qëllimi

Një **Visual Studio i integruar brenda /marketing** që zëvendëson 80% të përdorimit të Canva + CapCut për stafin, me:

- UI-në 100% brenda aplikacionit (asnjë OAuth hand-off)
- Brand kit qendror i zbatuar automatikisht
- Lidhje e drejtpërdrejtë me daily-basket (produktet e ditës ushqejnë editor-in)
- AI asistent për caption/hashtags (Faza 1) → kreativ i plotë (Faza 2)
- Rendering server-side për video, asnjë varësi nga makina e përdoruesit
- Auto-attach i output-it te `daily_basket_posts` dhe `content_posts`

### Non-goals
- NUK po ndërtojmë CapCut/Canva klon pro (color grading, object tracking, AI auto-edit)
- NUK po bëjmë collaborative real-time editing në fazë 1 (shtohet në Fazë 3 nëse duhet)
- NUK po mbështesim formate 3D / AR / green screen tracking
- NUK po bëjmë mobile app shoqërues (web responsive mjafton)

## 3. Vendimet e marra gjatë brainstorming-ut

| Vendim | Zgjedhja | Alternativat e refuzuara |
|---|---|---|
| Niveli i ambicies | **B — Studio i plotë** | A (mini inline), C (CapCut klon) |
| Stack foto | **Polotno (~$390/vit commercial)** | Fabric.js+UI jonë, tui-image-editor, Photopea |
| Stack video (render) | **Remotion** (license Business për ekip > 3) | Shotstack cloud, Etro.js |
| Stack video (UI timeline) | **Remotion Player + RVE evaluation / timeline yni** | Ndërtim nga zero, pa RVE |
| Client-side trim | **FFmpeg.wasm** | Vetëm server-side |
| AI | **Claude API** (Sonnet 4.6) | OpenAI, brezat lokal |
| UI pattern | **Hybrid: inline + full-screen Studio** | Vetëm inline, vetëm Studio |
| AI rollout | **Faza 1 AI Light → Faza 2 AI Smart** | Direkt L2, vetëm L1 |

**Arsyeja kryesore për hybrid stack (OSS + Polotno commercial):** Polotno na kursen 2+ muaj punë UI për pjesën Canva-like dhe vjen me license që lejon whitelabel; pjesën video e kemi nën kontroll me Remotion sepse tonë na duhet flexibility e templates programatike.

## 4. Arkitektura e lartë

```
┌─────────────────────────────────────────────────────────────────┐
│                      /marketing (Laravel + Blade)                │
│                                                                   │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐         │
│  │ daily-basket │   │ content-     │   │ /marketing/  │         │
│  │ (plan grid)  │──▶│  planner     │   │  studio      │         │
│  │ inline edit  │   │ (schedule)   │   │ full-screen  │         │
│  └──────┬───────┘   └──────▲───────┘   └──────┬───────┘         │
│         │                   │                   │                 │
│         ▼                   │                   ▼                 │
│  ┌──────────────────────────────────────────────────────┐        │
│  │           Editor Orchestrator (JS, në browser)        │        │
│  │  ┌──────────────┐    ┌──────────────┐                │        │
│  │  │  Polotno     │    │ Remotion     │                │        │
│  │  │  (foto/      │    │ Player +     │                │        │
│  │  │   carousel)  │    │ timeline     │                │        │
│  │  └──────┬───────┘    └──────┬───────┘                │        │
│  │         │   FFmpeg.wasm     │                         │        │
│  │         │   (client trim)   │                         │        │
│  └─────────┼───────────────────┼─────────────────────────┘        │
│            │                   │                                  │
│            ▼                   ▼                                  │
│  ┌──────────────────────────────────────────────────────┐        │
│  │            Backend Services (PHP)                     │        │
│  │  AIContentService → Claude API                        │        │
│  │  RenderService    → Remotion Node worker (queue)      │        │
│  │  BrandKitService  → singleton cached                  │        │
│  │  TemplateService  → CRUD + JSON metadata              │        │
│  │  AssetService     → upload, media library             │        │
│  └──────────────────────────────────────────────────────┘        │
│                              │                                    │
│                              ▼                                    │
│  ┌──────────────────────────────────────────────────────┐        │
│  │         MySQL (za_marketing DB) + MinIO/S3 storage    │        │
│  └──────────────────────────────────────────────────────┘        │
└───────────────────────────────────────────────────────────────────┘
       ▲                                  ▲
       │                                  │
   cross-DB                           Horizon queue
   (DIS users,                        (render jobs)
    DIS products)
```

## 5. Stack-u teknik

### 5.1 Backend (Laravel — ekzistues)
- **Laravel 11 + PHP 8.3** (njësoj si aplikacioni aktual)
- **MySQL** `za_marketing` DB (njësoj si daily-basket)
- **Laravel Horizon** për queue jobs (render)
- **Spatie permissions** (roli i ri: `marketing.visual-studio.*`)

### 5.2 Frontend (të reja)
- **Polotno** (~$390/vit commercial license — verifiko saktësisht çmimin në kohën e blerjes) — foto/carousel editor
- **Remotion 4+** — video rendering engine + Player component. License: falas për individë dhe kompani me < 4 punonjës; për Zero Absolute me ekip marketing të dedikuar duhet Business license (~$290–$900/vit sipas madhësisë — verifiko në javë 1)
- **React 18** për Visual Studio SPA (ekziston Blade për pjesën tjetër; Studio do të jetë island, mount në Blade view)
- **FFmpeg.wasm 0.12+** — client-side trim/merge
- **Tailwind 4** (ekzistues)
- **Auth:** SPA përdor session cookie ekzistues (Laravel Sanctum session guard), jo token-based

### 5.3 AI (Faza 1 → 2)
- **Claude API (Sonnet 4.6)** — caption + hashtags në Fazë 1; full draft package në Fazë 2
- **Budget:** ~$40/muaj (Fazë 1), ~$120/muaj (Fazë 2)

### 5.4 Render worker
- **Node.js 20 + Remotion CLI** në një queue worker të dedikuar (Horizon custom queue `video-render`)
- **FFmpeg native** për post-processing (watermark, optimizim, thumbnail)
- **Dispatch flow:** Laravel Job → dispatch `RenderVideoJob` → Horizon → Node script → MinIO upload → update DB → notify user

## 6. Database schema — shtesat e reja

### 6.1 `marketing_brand_kit` (singleton — 1 rresht)
```sql
CREATE TABLE marketing_brand_kit (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  colors JSON,               -- {primary, secondary, accent, neutral, text}
  typography JSON,            -- {display, body, mono} me family + weights
  logo_variants JSON,         -- {dark, light, transparent, icon} me paths
  watermark JSON,             -- {path, position, opacity, scale}
  voice_sq TEXT,              -- voice/tone për caption shqip
  voice_en TEXT,              -- voice/tone për caption anglisht
  caption_templates JSON,     -- {hook_patterns, cta_patterns}
  default_hashtags JSON,      -- array
  music_library JSON,         -- [{id, path, mood, genre, bpm, duration}]
  aspect_defaults JSON,       -- [{post_type, aspect}]
  updated_by BIGINT UNSIGNED,
  created_at, updated_at
);
```
**Cache:** 60s (BrandKitService::get()). Vetëm 1 rresht kurrë.

### 6.2 `marketing_templates`
```sql
CREATE TABLE marketing_templates (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120),
  slug VARCHAR(120) UNIQUE,
  kind ENUM('photo','carousel','reel','video','story'),
  engine ENUM('polotno','remotion'),    -- cili editor e hap
  source JSON,                           -- Polotno JSON OSE Remotion TSX path
  metadata JSON,                         -- për Claude: {use_case, fits_products, aspect, duration, notes}
  thumbnail_path VARCHAR(500),
  is_system BOOLEAN DEFAULT 0,           -- seed vs user-created
  is_active BOOLEAN DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at, updated_at,
  INDEX (kind, is_active),
  INDEX (engine)
);
```

### 6.3 `marketing_creative_briefs`
```sql
CREATE TABLE marketing_creative_briefs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  daily_basket_post_id BIGINT UNSIGNED NULL,   -- optional link back
  template_id BIGINT UNSIGNED NULL,
  post_type VARCHAR(20),
  aspect VARCHAR(10),                          -- "1:1", "9:16", "4:5"
  duration_sec INT UNSIGNED NULL,
  caption_sq TEXT NULL,
  caption_en TEXT NULL,
  hashtags JSON NULL,                          -- array
  music_id VARCHAR(100) NULL,                  -- referencë nga brand_kit.music_library
  script JSON NULL,                            -- [{time, text, cta?}]
  media_slots JSON NULL,                       -- [{slot, media_id|product_image}]
  suggested_time DATETIME NULL,
  source ENUM('manual','ai-light','ai-smart'), -- për tracking
  ai_prompt_version VARCHAR(20) NULL,          -- për A/B të prompteve
  state JSON,                                   -- Polotno/Remotion full state
  render_job_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED,
  created_at, updated_at,
  INDEX (daily_basket_post_id),
  INDEX (source)
);
```
**Shënim:** Në Fazë 1, AI mbush vetëm `caption_sq`, `caption_en`, `hashtags`. Fushat e tjera mbeten `null` dhe user i plotëson. Në Fazë 2, AI mbush gjithçka. **Zero migration mes fazave.**

### 6.4 `marketing_render_jobs`
```sql
CREATE TABLE marketing_render_jobs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  creative_brief_id BIGINT UNSIGNED,
  status ENUM('queued','rendering','completed','failed'),
  output_path VARCHAR(500) NULL,
  output_thumbnail VARCHAR(500) NULL,
  output_duration_seconds INT NULL,
  output_size_bytes BIGINT NULL,
  error_message TEXT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at, updated_at,
  INDEX (status, created_at)
);
```

### 6.5 `marketing_assets` (për template assets + brand media)
```sql
CREATE TABLE marketing_assets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  kind ENUM('sticker','music','font','logo','watermark','template-asset'),
  name VARCHAR(180),
  path VARCHAR(500),
  mime_type VARCHAR(80),
  duration_seconds INT NULL,           -- për muzikë
  metadata JSON NULL,                   -- {mood, bpm} për muzikë etj.
  created_at, updated_at,
  INDEX (kind)
);
```

### 6.6 Shtesa te `daily_basket_posts`
Kolonë e re (migration e re):
```sql
ALTER TABLE daily_basket_posts
  ADD COLUMN creative_brief_id BIGINT UNSIGNED NULL AFTER content_post_id,
  ADD FOREIGN KEY (creative_brief_id) REFERENCES marketing_creative_briefs(id) ON DELETE SET NULL;
```

## 7. User flows

### 7.1 Flow kryesor — quick create nga daily-basket
**Shënim për media ekzistuese:** Nëse postit i janë ngarkuar tashmë media në `daily_basket_post_media` (nga flow-i aktual), këto importohen automatikisht si shtresa fillestare në editor (Polotno për foto, Remotion Player për video).

1. User te `/marketing/daily-basket?date=2026-12-15` sheh plan grid me produktin e ditës
2. Kliko qelizë bosh → modal "Krijo post" hapet
3. User zgjedh `post_type` (ose AI sugjeron bazuar në produkt)
4. **AI Light (Fazë 1):** User shton media manualisht → shkruan ose klikon "Gjenero caption me AI" → Claude kthen caption_sq + caption_en + hashtags
5. **AI Smart (Fazë 2):** User kliko "Gjenero draft" → Claude kthen template + caption + music + script + slots. Editor hapet me draft të ngarkuar
6. User editon në Polotno (foto) ose Remotion Player + timeline (video)
7. Kliko "Save" → state ruhet në `creative_briefs.state` (JSON)
8. Nëse video → dispatch `RenderVideoJob` → queue → Remotion render → MP4 i ruajtur → notify user
9. Output i bashkangjitet `daily_basket_post_media` + `creative_brief_id` lidhet me post
10. User e kalon postin në stage "scheduling" në daily-basket → `content_post` krijohet me referencë te rendered media

### 7.2 Flow sekondar — nga Studio i dedikuar
`/marketing/studio` është faqe React SPA. Mund të hapet:
- Direkt (për krijim template, brand kit editim, ose krijim post pa produkt)
- Nga daily-basket me butonin "Open in Studio ↗" (pas edit inline) → hap të njëjtin creative brief full-screen
- Studio ruan në të njëjtën tabelë — inline editor dhe Studio ndajnë të njëjtin state

### 7.3 Flow Brand Kit
`/marketing/settings/brand-kit` (vetëm role Manager+):
- Tab "Colors" — palette me color picker
- Tab "Typography" — dropdown google fonts + custom upload
- Tab "Logo" — upload 4 variante
- Tab "Voice" — text area për ton sq + en
- Tab "Music" — upload + tag (mood/BPM/duration)
- Cache invalidohet në save

## 8. AI — Faza 1 vs Faza 2

### 8.1 AI Light (Fazë 1)
**Endpoints:**
- `POST /marketing/api/ai/caption` — merr `{product_id, post_type, language}`, kthen `{caption_sq, caption_en, hashtags}`
- `POST /marketing/api/ai/rewrite` — merr `{text, tone, language}`, kthen `{text}`

**Prompt:**
```
Ti je asistent për marketingun e Zero Absolute.
Brand voice (sq): {brand_kit.voice_sq}
Brand voice (en): {brand_kit.voice_en}
Produkti: {product.name} · {product.price}€ · {product.description}
Tipi i postit: {post_type}
Kthe JSON: {caption_sq, caption_en, hashtags: [...max 8]}
```

### 8.2 AI Smart (Fazë 2)
**Endpoint:**
- `POST /marketing/api/ai/draft-package` — merr `{daily_basket_post_id OR product_id, post_type?, goal?}`, kthen full CreativeBrief JSON

**Prompt shtesa:**
- Templates library me metadata → Claude zgjedh `template_id`
- Music library me tags → Claude zgjedh `music_id`
- Historik posts me reach → few-shot për style

**Versioning:** `ai_prompt_version` në tabelë për A/B testing dhe rollback.

## 9. Integrimi me ekzistuesen

| Ekzistues | Si integrohet Visual Studio |
|---|---|
| `daily_basket_posts` | Shton `creative_brief_id` FK; inline editor hapet nga plan grid |
| `daily_basket_post_media` | Output nga render auto-attach këtu |
| `content_posts` (content-planner) | Kur post kalon në stage "scheduling", `content_post.media` marrë nga creative brief |
| Shporta panorama / meta-tokens | Asnjë impact |
| Meta/TikTok sync | Publikim shkon njësoj si sot (publish flow i content-planner nuk ndryshon) |

## 10. Faza e shtrirjes (rollout)

### Faza 1 — MVP (javë 1–6) → 1 plan AntTech
**Deliverable:** Stafi krijon foto + video brenda appit, me brand kit, ruhen, skedulohen.

Komponentë:
- [ ] Migration-et (5 tabela të reja + 1 alter)
- [ ] Models + services (BrandKit, Template, CreativeBrief, RenderJob, Asset)
- [ ] `/marketing/settings/brand-kit` UI (admin)
- [ ] Polotno integrim me brand kit injection
- [ ] Remotion Player në React + timeline minimal (evaluate RVE, decide)
- [ ] FFmpeg.wasm worker për client trim
- [ ] Node Remotion render worker + Horizon job
- [ ] 5–8 seed templates (reel-product, carousel-drop, quote-static, story-sale, reel-quote, carousel-how-to)
- [ ] Daily-basket: inline editor në modal e postit
- [ ] `/marketing/studio` faqe full-screen
- [ ] Auto-attach te daily_basket_post_media + link te content_post
- [ ] AI Light: `/api/ai/caption` + `/api/ai/rewrite` + "Gjenero caption" buton
- [ ] Tests: feature tests për flow kryesor + render job

### Faza 2 — AI Smart + Template library (javë 7–9) → plan i ri AntTech
- [ ] `AIContentService::generateDraftPackage()`
- [ ] Template metadata JSON për AI
- [ ] Music library me tags (UI + DB)
- [ ] "Apply AI Draft" buton në editor
- [ ] Prompt versioning + eval framework
- [ ] User-created templates (save as template nga Studio)

### Faza 3 — Polish + Scale (javë 10–12+) → plan i ri AntTech
- [ ] A/B variant generation
- [ ] Performance feedback loop (analytics → AI)
- [ ] Bulk generation ("10 posts për Drop 3")
- [ ] Editor analytics dashboard
- [ ] Auto-schedule sugjerime
- [ ] Transitions & effects library
- [ ] Custom fonts upload
- [ ] Collaborative editing (comments, approvals)

## 11. Risk & trade-offs

| Risk | Ndikimi | Mitigimi |
|---|---|---|
| Polotno license (~$390/vit) | Kosto e vazhdueshme | Ka opsion fallback me Fabric.js pure nëse kërkohet kursim |
| Remotion license për ekip > 3 | Kosto fikse | Verifiko saktësisht në javë 1 para purchase; tier Business $290+/vit |
| RVE ende i ri (OSS) | Bug / feature mungesa | Evaluation në javë 2; nëse jo gati, ndërtojmë timeline tonë mbi Remotion Player (~2 javë shtesë) |
| FFmpeg.wasm i ngadaltë për video të gjata | UX bad për > 30sec | Kufizim në 30s në client, > 30s shkon në server-side |
| Claude API cost spike | Buxheti API | Rate limiting per user + monthly quota; alarm në $150/muaj |
| Brand voice drift në AI | Output jo-branded | Voice prompt i fuqishëm + user review obligator në Fazë 1 |
| Node render worker crash | Video jo gati | Horizon retry 3x; failure → user notify + manual fallback |
| Migrations te prod DB | Downtime | Migrations aditive (nuk preken kolona ekzistuese); deploy gjatë low traffic |

## 12. Success criteria

**Fazë 1 (MVP):**
- Stafi krijon ≥ 50% të posteve brenda appit (jo më Canva/CapCut)
- Koha mesatare krijim-posti bie ≥ 30% (matur me `creative_briefs.created_at` deri `daily_basket_posts.stage=scheduling`)
- 0 bugs kritike në render pipeline për 2 javë
- < 5s load time për editor

**Fazë 2 (AI Smart):**
- ≥ 70% e posteve nis me "Apply AI Draft"
- User override rate < 40% (d.m.th. AI output pranohet shpesh)
- Caption quality score nga user ≥ 4/5 (feedback inline)

**Fazë 3 (Scale):**
- Stafi nuk përdor më Canva/CapCut (< 10% fallback)
- ROI i matshëm: kursim X orë/javë për team
- System mbështet ≥ 10 përdorues aktivë njëherësh

## 13. Open decisions deferred

- **RVE vs timeline yni** — vendoset në javë 2 pas spike
- **Custom fonts upload nga user** — Fazë 3 (legal/licensing complexity)
- **Collaborative real-time editing** — Fazë 3+ (jo prioritet)
- **Mobile responsive Studio** — Fazë 3; Fazë 1 kufizohet në desktop/tablet landscape
- **Export për platforma jo-social (email, website)** — jo në scope fillestar

## 14. Next actions

1. Shqyrtim i këtij spec-i nga user
2. Aprovim ose revizion
3. Krijim i **Fazë 1 plan** në AntTech (audit plan → ~15–20 dev tasks me dependencies → audit dev → test dev)
4. Nisje e punës tasks-ko-tasks në rendin e dependency-ve
5. Fazat 2 dhe 3 marrin plan të vetin kur afrohet koha
