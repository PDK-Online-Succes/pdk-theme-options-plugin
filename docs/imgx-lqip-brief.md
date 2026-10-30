# Thin Delta Brief — IMGX: LCP-waardige low-res placeholder (LQIP)

Status: concept, ter bevestiging. Bijgewerkt 2026-10-23 na antwoorden V4 en V5 en na doc-review
(herstelronde 1: C1, I1–I8, S1–S4).
Scope: delta op de IMGX-module van PDK Theme Options (huidige versie 2.11.1 —
`pdk-theme-options/pdk-theme-options-plugin.php:6`, `:24`). Geen volledige PRD: alleen wat verandert.
De werking van IMGX zelf staat in `docs/modules/imgx.md` en wordt hier niet herhaald.

**Verzoek (letterlijk):** *"toevoeging aan de imgx module — Ultra Low-res afbeelding als placeholder
die automatisch gemaakt en toegevoegd wordt. https://csswizardry.com/2023/09/the-ultimate-lqip-lcp-technique/"*,
met een screenshot van `img { …; background-repeat:no-repeat; background-size:cover; … }`.

**Antwoorden gebruiker (normatief):**
- U1 — Variant: de LCP-LQIP-techniek uit het artikel. Een extern, laag-kwaliteit afbeeldingsbestand
  met genoeg bits per pixel dat Chromium het als LCP-kandidaat telt, als `background-image` op de
  `<img>`. Uitdrukkelijk géén piepkleine inline base64-blur (herzien 2026-10-30, zie §12).
- U2 — Scope: alle afbeeldingen die IMGX nu al als bijlage herkent.
- U3 — De nieuwe instelling staat standaard UIT.
- V4 (2026-10-23) — *"Toch alle afbeeldingen"*: LQIP ook op `<img loading="lazy">`, in het besef dat
  elke afbeelding daardoor een extra, vroeg request kost. Accepteert R-101.
- V5 (2026-10-23) — LQIP-achtergrond staat los van `picture_enabled`: alleen `lqip_enabled` (plus de
  bestaande globale gates) bepaalt of hij verschijnt. Beantwoordt OQ-101.

**Feiten uit het artikel (vandaag geverifieerd):**
- F1 — Chromium telt een afbeelding pas als LCP-kandidaat vanaf 0,05 bits per pixel van de
  **weergegeven** maat. Het artikel mikt op 0,055. Minimum aantal bytes = `b × h × 0,055 / 8`.
- F2 — Opschalen kost punten: het gemeten oppervlak = `maat × min(weergave, natuurlijk) / weergave`.
  De LQIP mag dus niet kleiner zijn dan de weergavemaat.
- F3 — Het artikel gebruikt JPEG-LQIP's; kwaliteit ≈10 was in één geval de visueel acceptabele
  ondergrens. Het preloadt de LQIP (`<link rel=preload as=image fetchpriority=high>`), níet de hi-res.

---

## 1. Vastgelegde besluiten (coördinator, omkeerbaar, niet heropenen)

| ID | Besluit |
|----|---------|
| D1 | Eén LQIP-JPEG per geregistreerde bijlagemaat (incl. `full`), als sidecar naast de bron (`photo-1024x768.lqip.jpg`), volgens de bestaande naamgeving van `Files::sidecar_path()` (`class-files.php:154-176`). Opgenomen in de bestaande registratie `_imgx_variants` onder de sleutel `Files::LQIP_KEY = 'lqip'`; alleen uitgeserveerd als hij geregistreerd is én bestaat (zelfde vertrouwensregel als WebP/AVIF). De renderer kiest de LQIP van de maat waarvan de basename overeenkomt met de `src` van de `<img>`. |
| D2 | Kwaliteitslus: start op q=10, ophogen tot de bestandsgrootte ≥ `b × h × 0,055 / 8` bytes, plafond q=60 (resultaat op het plafond blijft staan, ook onder het doel). Startkwaliteit filterbaar. De lus woont in `Converter` (I3). |
| D3 | Inline longhand-CSS op de `<img>`: `background-image`, `background-size:cover`, `background-repeat:no-repeat`, `background-position:center`. Samengevoegd met een bestaand `style`-attribuut, dat nooit wordt overschreven. Ontbreekt `style`, dan wordt het vlak vóór `>` of `/>` ingevoegd; een bestaand attribuut behoudt zijn quote-stijl (S4). Geen nieuw stylesheet. |
| D4 | PNG-bronnen met alfakanaal krijgen geen LQIP; vastgelegd als `skipped[<maat>]['lqip'] = 'has-alpha'`, niet opnieuw te proberen (I1). **Alfa-detectie (I2):** een PNG heeft alfa als het IHDR-kleurtype (byte 25 van het bestand) 4 of 6 is, óf als er een `tRNS`-chunk vóór de eerste `IDAT` staat. Uitgevoerd door een `Files`-helper op ruwe bytes. De beslissing valt één keer per bijlage op het origineel (`wp_get_original_image_path()`, anders `get_attached_file()`) en geldt voor alle maten, omdat de GD-editor van WordPress PNG-submaten altijd als RGBA schrijft, ook bij een opaak origineel. |
| D5 | `<link rel=preload>` voor de LQIP alleen in `whole_page`-modus en alleen voor `<img>` met `fetchpriority="high"` (buiten de buffer is de `<head>` al verstuurd). Eigen pass in `filter_page()` over de uiteindelijke buffer: de eerste `<img>` met `fetchpriority="high"` waarvan `style` `background-image:url(… .lqip.jpg)` bevat — ook binnen IMGX' eigen `<picture>` — levert één `<link rel=preload as=image href=… fetchpriority=high>` vóór `</head>` (I4). |
| D6 | Nieuwe instelling `lqip_enabled` in `imgx_settings` (0/1, standaard 0). `encoding_signature()` krijgt alleen bij `lqip_enabled === 1` het extra deel `lqip` (`$parts[] = 'lqip'`); met LQIP uit blijft de vingerafdruk byte-gelijk aan nu (`avif_webp-82-50`), zodat `tests/test-imgx.php:391` en `:395` ongewijzigd groen blijven (C1). Verwijderen via de bestaande paden (bijlage verwijderen, batch `delete`, `wp imgx delete`). Minor-bump naar 2.12.0 op beide plekken in `pdk-theme-options/pdk-theme-options-plugin.php` + CHANGELOG-kop `## [2.12.0] — 2026-10-23`. |
| D7 | ROLE-002 blijft in de brief; geen apart `docs/use-cases.md`, omdat de passieve bezoeker geen use cases uitvoert (coördinator, 2026-10-23). |
| D8 | Converter-pad (I3): de LQIP wordt door `Converter` geschreven met mime `image/jpeg`. Capability-check via `wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) )`, niet via de WebP/AVIF-probe. De kwaliteit komt uit de D2-lus (bijv. `$context['quality']`). De uitvoer wordt geverifieerd met `Files::sniff_format() === 'jpeg'`. `larger-than-source` wordt één keer gecontroleerd, op het eindresultaat van de lus. `Files::MIME_TYPES` blijft alleen `avif`/`webp`, omdat die lijst `<source type>` en de encoder-probe voedt. |
| D9 | `wrap_img()` krijgt een eigen `picture_enabled`-check voor het `<picture>`-deel (gedragswijziging voor directe aanroepers → CHANGELOG-notitie). `imgx_picture_html` krijgt de `<img>` zoals die werkelijk wordt uitgevoerd (met LQIP-style); docblock aanpassen (S3). |

---

## 2. Geverifieerde uitgangspositie — waar de huidige code LQIP blokkeert

De taak vraagt expliciet: geeft de renderer nu al op als er geen varianten zijn? **Ja, op vier
plekken.** Een JPEG-only site (geen WebP/AVIF-ondersteuning, of modus gefilterd naar leeg) krijgt
vandaag dus nergens een wijziging. Daarnaast blokkeert `picture_enabled = 0` alles. LQIP moet langs
al deze plekken heen (V5):

| Plek | Huidig gedrag |
|------|---------------|
| `class-picture-renderer.php:765-767` (`is_active()`) | `false` als `picture_enabled` uit staat. |
| `class-picture-renderer.php:793-795` (`is_active()`) | `false` als `enabled_formats()` leeg is. Beide gates blokkeren ook de Oxygen-paden, die eerst `is_active()` vragen (`class-oxygen.php:97`, `:123`). |
| `class-picture-renderer.php:459-463` | Terug zonder wijziging als `Variants::lookup_map()` leeg is. |
| `class-picture-renderer.php:465-469` | Terug als `formats_for()` (alleen `avif`/`webp`, `:750-752`) leeg is. |
| `class-picture-renderer.php:502-504` | Terug als er geen enkele `<source>` uitkomt. |

Generatiekant, dezelfde blokkade: `Generator::needs_generation()` (`class-generator.php:271-275`),
`Generator::generate()` (`:373-379`) en `wp imgx generate` (`class-cli.php:125-127`) stoppen als er
geen WebP/AVIF-formaat actief is. `formats_for()` laat alleen `avif`/`webp` door (`:254-256`).

Andere relevante vaste punten:

- **Verwijdergarantie**: `Files::VARIANT_EXTENSIONS` is alleen `webp`/`avif` (`class-files.php:23`) en
  `Files::delete_variant()` weigert al het andere (`:120-139`). Een `.lqip.jpg` kan nu dus niet
  verwijderd worden — en de garantie dat geen codepad een origineel kan wissen moet blijven gelden.
- **Bron-terugzoeking**: `sidecar_source_still_exists()` (`class-generator.php:833-858`) herkent alleen
  `webp`/`avif`-extensies; voor een `.lqip.jpg` geeft hij `false`, waardoor `prune_stale_sizes()`
  (`:780-783`) een LQIP zou kunnen wissen terwijl de bron nog bestaat.
- **Registratie**: `Variants::normalize()` gooit elke formaatsleutel weg die niet in
  `Files::MIME_TYPES` staat (`class-variants.php:143-145`), in zowel `sizes` als `skipped`. Een
  `lqip`-sleutel overleeft nu niet. `Files::MIME_TYPES` voedt ook `<source type>`
  (`class-picture-renderer.php:492`) en de encoder-probe (`class-capabilities.php:201`); daarom krijgt
  LQIP een eigen sleutelconstante (D8) en blijft `MIME_TYPES` ongewijzigd.
- **Converter**: weigert onbekende/niet-geprobede formaten (`class-converter.php:121-134`) en gooit een
  variant ≥ de bron weg als `larger-than-source` (`:265-274`). Kwaliteit komt uit de instellingen
  (`:286-313`), er is geen lus.
- **Opnieuw proberen**: `skip_is_retryable()` probeert alleen `larger-than-source` opnieuw
  (`class-generator.php:328-330`); `has-alpha` blijft dus vanzelf overgeslagen (D4).
- **Markup**: de `<img>` wordt nu letterlijk overgenomen (`class-picture-renderer.php:507-509`). LQIP
  wijzigt dat: alleen het `style`-attribuut van de `<img>` verandert, verder niets.
- **Preload-aangrijppunt**: `filter_page()` ziet het complete document (`class-picture-renderer.php:134-150`).
  De buffer wordt geregistreerd op `whole_page` alleen, los van `picture_enabled` (`:104-106`).

---

## 3. Wat verandert, en waar

| Bestand | Wijziging | Source |
|---------|-----------|--------|
| `modules/imgx/includes/class-settings.php` | `lqip_enabled` in `defaults()` (`:54-65`, waarde 0) en `sanitize()` (`:146-172`, 0/1). `encoding_signature()` (`:198-207`): `'lqip'` als extra deel alleen bij `lqip_enabled === 1`; uit = ongewijzigd | U3, D6 (C1) |
| `modules/imgx/includes/class-admin.php` | Eén checkbox voor `lqip_enabled`, Nederlandse tekst, in een bestaande sectie (`register_fields()`, `:97-183`). De waarschuwing op `:315-317` ("er wordt niets omgezet") verschijnt alleen nog als ook `lqip_enabled` uit staat | U3, D6, V5 (I8) |
| `modules/imgx/includes/class-files.php` | Constante `LQIP_KEY = 'lqip'`; `MIME_TYPES` blijft ongewijzigd. Sidecar-naam `<stem>.lqip.jpg` met dezelfde botsingsterugval als `sidecar_path()`. `delete_variant()` accepteert uitsluitend namen die op `.lqip.jpg` eindigen binnen de uploadsmap. Nieuwe helper voor alfa-detectie op PNG-bytes (D4) | D1, D4, D6, D8 (I2, I3) |
| `modules/imgx/includes/class-variants.php` | `normalize()` laat `Files::LQIP_KEY` toe in `sizes` (basename) én in `skipped` (reden); `lookup_map()` levert hem mee | D1, D4 (I1) |
| `modules/imgx/includes/class-converter.php` | LQIP-pad volgens D8: mime `image/jpeg`, eigen capability-check, D2-lus in de converter, kwaliteit als parameter, `sniff_format === 'jpeg'`, `larger-than-source` één keer op het eindresultaat; zelfde pixelafmeting als de bronmaat | D2, D8 (I3) |
| `modules/imgx/includes/class-generator.php` | LQIP-generatie ook zonder actief WebP/AVIF-formaat (`:271-275`, `:373-379`); `has-alpha`-overslag (D4); `sidecar_source_still_exists()` (`:833-858`) stript het achtervoegsel `.lqip.jpg` in zijn geheel vóór het terugzoeken van de bron | D1, D4, §2 (I1, I8) |
| `modules/imgx/includes/class-cli.php` | `generate` stopt niet meer op `:125-127` als alleen LQIP actief is | D6, §2 |
| `modules/imgx/includes/class-picture-renderer.php` | Achtergrond-CSS op de `<img>` (D3), ook zonder `<picture>`/`<source>`. `is_active()` vereist bij `lqip_enabled = 1` niet langer `picture_enabled` (`:765-767`) of formaten (`:793-795`); de overige gates blijven. `wrap_img()` krijgt een eigen `picture_enabled`-check voor het `<picture>`-deel (D9). `imgx_picture_html` (`:511-519`) krijgt de werkelijk uitgevoerde `<img>`, docblock bijgewerkt. Preload als eigen pass in `filter_page()` (D5). CSS-URL volgens NFR-104 | D1, D3, D5, D9, V5, §2 (I4, I5, S3) |
| `modules/imgx/includes/class-site-health.php` | Tekst op `:93` ("er worden dus geen afbeeldingen gegenereerd") klopt niet meer bij een JPEG-only server met LQIP aan: dan noemen dat LQIP's wel gegenereerd worden. `lqip_enabled` als veld in de debug-info (`:145-180`) | D6 (S1) |
| `pdk-theme-options/uninstall.php` | Commentaar `:30-32` noemt ook `.lqip.jpg` als bestand dat bewust blijft staan | D6 (S1) |
| `tests/test-imgx.php` | Nieuwe checks voor AC-101, AC-102, AC-105, AC-106, AC-108, AC-111, plus de zelftesthelften van AC-104 (alfa-helper met bytefixtures, `has-alpha`-registratie, `needs_generation()`), AC-107 (`is_active()`, `transform()`) en AC-109 (`needs_generation()`). Stubs aanpassen: `is_admin()` en `is_feed()` lezen een global in plaats van vast `false` (`:49`, `:52`); `add_action()` (`:29`) registreert in `$GLOBALS['imgx_actions']`. `REST_REQUEST` is een constante en niet terug te zetten binnen één PHP-proces; die gate gaat naar de live-check (I6) | (I6) |
| `docs/modules/imgx.md` | Instellingentabel, sidecar-naamgeving, nieuwe filter; `:19-21` (encoder-voorwaarde: LQIP vereist alleen JPEG-schrijven), `:184` en `:265` (`.lqip.jpg` blijft bij uninstall staan) | (I8, S1) |
| `pdk-theme-options/pdk-theme-options-plugin.php` + `CHANGELOG.md` | 2.12.0 op `:6` en `:24`, kop `## [2.12.0] — 2026-10-23`. CHANGELOG vermeldt ook de gedragswijziging van `wrap_img()` voor directe aanroepers (D9) | D6, D9, `CLAUDE.md` |

Nieuwe filter: startkwaliteit van de lus (D2), naamgeving volgens bestaand patroon
(`imgx_webp_quality`/`imgx_avif_quality`), bijvoorbeeld `imgx_lqip_start_quality`.

Bestaande filters gelden ook voor LQIP: `imgx_should_render_picture` (`class-picture-renderer.php:455`)
schakelt per afbeelding ook de achtergrond uit, `imgx_enable_picture` (`:804`) per request,
`imgx_should_generate_image` (`class-generator.php:512`) met `$format = 'lqip'`.

---

## 4. Rolmodel

| ROLE-ID | Naam | Soort | Auth | Grens | Bron |
|---------|------|-------|------|-------|------|
| ROLE-001 | WordPress-beheerder | human | sessie + `manage_options` | site-breed | `class-batch-processor.php:86-106` |
| ROLE-002 | Bezoeker frontend | human | geen | publiek | U2 (ziet alleen de uitvoer, heeft geen acties) |

ROLE-002 is een passieve bezoeker die geen handelingen in het product uitvoert. Volgens D7 blijven de
use cases in deze brief (§7) en komt er geen `docs/use-cases.md`.

---

## 5. Acceptatiecriteria

Verificatie: **H** = zelftest `php tests/run.php` (kale PHP met WP-stubs); **L** = live op dev.local
(Oxygen 6.2-rc.1; de plugin staat daar als kopie in mu-plugins, dus eerst de gewijzigde module
daarheen kopiëren).

### FR-101 — Instelling (Must Have) — Source: U3, D6
- **AC-101** (H): `Settings::defaults()['lqip_enabled'] === 0`; `sanitize()` maakt van `'1'`/`'on'`
  een `1` en van ontbrekend/leeg een `0`. Met `lqip_enabled = 0` en standaardinstellingen is
  `encoding_signature() === 'avif_webp-82-50'` (byte-gelijk aan nu; `tests/test-imgx.php:391` en `:395`
  slagen ongewijzigd); met `lqip_enabled = 1` en verder gelijke instellingen is hij
  `'avif_webp-82-50-lqip'`.
- **AC-102** (H + L): Gegeven `lqip_enabled = 0` en een registratie die wél `lqip`-entries bevat,
  wanneer de renderer een fixture-`<img>` verwerkt, dan is de uitvoer byte-gelijk aan de uitvoer
  zonder `lqip`-entries, en alle bestaande checks in `tests/test-imgx.php` slagen ongewijzigd. Live:
  met de instelling uit levert `wp imgx generate --ids=<id> --force` geen enkel `*.lqip.jpg` op.

### FR-102 — Generatie (Must Have) — Source: U1, U2, D1, D2, D4, D8, F1, F2
- **AC-103** (L): Gegeven `lqip_enabled = 1` en een JPEG-bijlage, wanneer `wp imgx generate --ids=<id>`
  draait, dan staat er voor elke maat in de metadata (incl. `full`) een `<stem>.lqip.jpg` naast het
  bronbestand van die maat, met JPEG-magic-bytes (`Files::sniff_format() === 'jpeg'`), met exact
  dezelfde pixelbreedte en -hoogte als dat bronbestand, en geregistreerd in `_imgx_variants` onder
  `sizes[<maat>]['lqip']`. Voor elk bestand geldt `filesize ≥ ceil(b × h × 0,055 / 8)`, óf het is even
  groot (bytes) als de referentiecodering op het plafond q=60, gemaakt met:
  `wp eval '$e = wp_get_image_editor( "<pad-bron>" ); $e->set_quality( 60 ); $r = $e->save( get_temp_dir() . "lqip-ref.jpg", "image/jpeg" ); echo filesize( $r["path"] );'`.
  Met `imgx_lqip_start_quality` op 30 gefilterd is geen enkel LQIP-bestand kleiner dan zijn
  q=30-codering (zelfde commando met `set_quality( 30 )`).
- **AC-104** (H + L, gesplitst): Gegeven een PNG-bijlage met alfakanaal, dan ontstaat er geen
  `.lqip.jpg`, staat `skipped[<maat>]['lqip'] === 'has-alpha'` in de registratie, en geeft
  `needs_generation()` na één `generate()` `false` voor die bijlage (`has-alpha` is niet retryable).
  Een PNG zonder alfakanaal krijgt wel een LQIP. Een LQIP die na de lus ≥ de bron uitvalt wordt
  weggegooid en als `skipped[<maat>]['lqip'] = 'larger-than-source'` vastgelegd (bestaande regel,
  `class-converter.php:265-274`). Zelftest: de alfa-helper met bytefixtures — IHDR-kleurtype 6 → alfa,
  4 → alfa, 2 zonder `tRNS` → geen alfa, 2 met `tRNS` vóór `IDAT` → alfa, 2 met `tRNS` ná `IDAT` →
  geen alfa, een JPEG → geen alfa — plus de `has-alpha`-registratie en `needs_generation()`, met `imgx_enabled_formats` gefilterd naar `[]`, zodat alleen het LQIP-pad draait; de alfa-beslissing valt vóór elke converter-aanroep. Live: de
  echte codering van beide PNG-soorten.
- **AC-112** (L): Gegeven `lqip_enabled = 1` en `generate_on_upload = 1`, wanneer een JPEG wordt
  geüpload in `upload_mode = sync`, dan staat er direct na de upload voor elke maat een `.lqip.jpg`;
  en in `upload_mode = async`, na `wp cron event run imgx_generate_variants`, eveneens. Beide gevallen
  slagen ook op een JPEG-only server, gesimuleerd met `add_filter( 'imgx_enabled_formats', '__return_empty_array' )`
  in een mu-plugin: dan ontstaan alleen `.lqip.jpg`-bestanden en geen fout in `imgx_recent_errors`.

### FR-103 — Bestanden en verwijderen (Must Have) — Source: D1, D6, `class-files.php:20-23`, `:120-139`
- **AC-105** (H): `sidecar_path` voor LQIP geeft `…/photo-1024x768.lqip.jpg` bij een vrije map; bij een
  vreemd bestaand `photo.lqip.jpg` valt hij terug op `photo.jpg.lqip.jpg` respectievelijk
  `photo-<hash6>.lqip.jpg`. `Files::delete_variant()` verwijdert een `x.lqip.jpg` binnen uploads en
  weigert (bestand blijft staan): `photo.jpg`, `photo.jpeg`, `photo.png`, een `.lqip.jpg` buiten de
  uploadsmap. `Variants::normalize()` behoudt een `lqip`-basename in `sizes` en een `lqip`-reden in
  `skipped`, en verwerpt een `lqip`-waarde met een pad erin. `sidecar_source_still_exists()` geeft
  `true` voor `photo-1024x768.lqip.jpg` zolang `photo-1024x768.jpg` bestaat, `true` voor
  `photo.jpg.lqip.jpg` zolang `photo.jpg` bestaat, en `false` als de bron weg is en alleen de LQIP
  over is — `prune_stale_sizes()` verwijdert die LQIP dan. Optioneel (L): een geüpload origineel met
  de naam `hero.lqip.jpg` overleeft `wp imgx delete --ids=<eigen id> --yes`, omdat alleen
  geregistreerde basenames worden verwijderd (`class-generator.php:692-704`).
- **AC-109** (H + L, gesplitst): Gegeven een bijlage met alleen WebP/AVIF geregistreerd en daarna
  `lqip_enabled = 1`, dan geeft `needs_generation()` `true` (H), en batchmodus `missing` maakt de
  LQIP's aan (L). Bijlage permanent verwijderen, batch `delete` en `wp imgx delete --ids=<id> --yes`
  verwijderen elk `.lqip.jpg` van die bijlage; het originele bestand en de WordPress-submaten staan er
  daarna nog (L).

### FR-104 — Uitvoer op de frontend (Must Have) — Source: U1, U2, V4, V5, D1, D3, D9, §2
- **AC-106** (H): Gegeven een registratie met `lqip` voor de basename van de `src`, dan bevat het
  `style` van de `<img>` precies `background-image:url("<lqip-url>")`, `background-size:cover`,
  `background-repeat:no-repeat` en `background-position:center`, waarbij `<lqip-url>` de `src` is met
  alleen de basename vervangen (`Files::swap_basename_in_url()`) en gecodeerd volgens NFR-104. Een
  bestaand `style="border:0"` blijft volledig staan naast de nieuwe declaraties, en een bestaand
  `style='border:0'` houdt zijn enkele quotes. Zonder `style`-attribuut wordt het ingevoegd vlak vóór
  `>` respectievelijk `/>`, en de rest van de tag is byte-gelijk. Bevat het bestaande `style` al
  `background` of `background-image`, dan wordt er niets toegevoegd. Heeft de `src`-maat geen
  `lqip`-entry, dan geen achtergrond. Met `imgx_verify_variant_files` aan en het bestand afwezig: geen
  achtergrond. Een `<img>` die al in een `<picture>` stond: geen wijziging (bestaande regel,
  `class-picture-renderer.php:219-222`, `:688-719`). Geen `<source type="…">` verwijst ooit naar een
  `.lqip.jpg`. Een `<img loading="lazy">` krijgt de achtergrond net zo goed (V4). `imgx_picture_html`
  ontvangt als tweede argument de `<img>` mét LQIP-style (D9).
- **AC-107** (H + L, gesplitst): Gegeven `imgx_enabled_formats` gefilterd naar `[]` (JPEG-only server),
  `lqip_enabled = 1` en een registratie met alleen `lqip`-entries, dan geeft `is_active()` `true`, en
  `transform()` levert een kale `<img>` (geen `<picture>`) mét de achtergrond-CSS uit AC-106. Zelftest:
  `is_active()` en `transform()`. Live: met dezelfde filter maakt `wp imgx generate --ids=<id>` de
  LQIP's zonder foutmelding.
- **AC-111** (H + L, gesplitst): Gegeven `picture_enabled = 0`, `lqip_enabled = 1` en een registratie met
  WebP/AVIF én `lqip` voor de `src`-maat, wanneer `filter_content()`, `filter_attachment_image()` en een
  directe aanroep van `wrap_img()` een fixture verwerken, dan is de `<img>` niet in een `<picture>`
  gewikkeld, staat er geen `<source>`, en draagt de `<img>` de achtergrond-CSS uit AC-106 (V5, D9). Met
  `picture_enabled = 0` en `lqip_enabled = 0` is de uitvoer byte-gelijk aan de invoer. De overige gates
  van `is_active()` blijven ook bij `lqip_enabled = 1` gelden. Zelftest: `is_admin()` en `is_feed()`
  via de stub-global op `true`, en `imgx_enable_picture` → `false`, leveren elk geen achtergrond. Live:
  een REST-request (`/wp-json/wp/v2/posts/<id>`, `content.rendered`) bevat geen `.lqip.jpg`.

### FR-105 — Preload (Should Have) — Source: D5, F3
- **AC-108** (H): Gegeven `whole_page = 1` en een document met één `<img fetchpriority="high">` en één
  zonder, beide met LQIP, dan bevat de uitvoer van `filter_page()` precies één
  `<link rel="preload" as="image" href="<lqip-url>" fetchpriority="high">` vóór `</head>`, voor de
  eerste afbeelding; nooit een preload voor een hi-res- of WebP/AVIF-URL. Tweede fixture: de
  `<img fetchpriority="high">` staat al in een door IMGX gemaakte `<picture class="imgx-picture">`
  met LQIP-style op de `<img>` — ook dan precies één preload voor die LQIP. Dezelfde invoer via
  `filter_content()` levert geen enkele preload op. Met `whole_page = 0` wordt `start_buffer` niet
  geregistreerd (controle via `$GLOBALS['imgx_actions']`) en komt er dus ook geen preload.

### FR-106 — Builders en echt LCP-effect (Must Have) — Source: U1, verzoek, `class-oxygen.php:63-128`
- **AC-110** (L): Gegeven `lqip_enabled = 1` op dev.local en een Oxygen 6-pagina met een hero-afbeelding
  uit de mediabibliotheek, wanneer de pagina in Chrome laadt met netwerkthrottling "Slow 4G", dan heeft
  de gerenderde `<img>` de achtergrond-CSS uit AC-106, en logt
  `new PerformanceObserver(l => console.log(l.getEntries().map(e => e.url))).observe({type: 'largest-contentful-paint', buffered: true})`
  minstens één entry waarvan de URL op `.lqip.jpg` eindigt. In de builder-canvas zelf verschijnt geen
  achtergrond (bestaande regel, `class-oxygen.php:119-121`).

**Release-check (geen AC, wel blokkerend):** versie 2.12.0 op `pdk-theme-options-plugin.php:6` en
`:24`, CHANGELOG-kop `## [2.12.0] — 2026-10-23` met de `wrap_img()`-gedragswijziging (D6, D9,
`CLAUDE.md`).

---

## 6. Niet-functioneel

- **NFR-101** — Stack ongewijzigd: platte PHP, geen composer/npm, geen build-stap, geen nieuw
  stylesheet of script (D3).
- **NFR-102** — Verwijdergarantie blijft: geen codepad kan een bestand verwijderen dat niet op
  `.webp`, `.avif` of `.lqip.jpg` eindigt of buiten de uploadsmap ligt (AC-105).
- **NFR-103** — Alle beheerteksten Nederlands via `__()`/`esc_html__()`, textdomain `pdk-theme-options`.
- **NFR-104** — Uitvoer-escaping (I5): de `src` wordt eerst gedecodeerd met `wp_specialchars_decode()`
  (zoals `parse_srcset()`, `class-picture-renderer.php:627`). De LQIP-URL wordt geschreven als
  `url("…")`, waarbij `"`, `;`, `\`, `(`, `)`, `'`, spatie en regeleinden percent-gecodeerd worden
  (`%22`, `%3B`, `%5C`, `%28`, `%29`, `%27`, `%20`, `%0A`/`%0D`). Daarna gaat de complete waarde van het
  `style`-attribuut door `esc_attr()`. AC-106-fixture: een `src` met elk van die tekens kan de
  CSS-declaratie of het attribuut niet openbreken.

---

## 7. Use cases

**UC-101 | Actor: ROLE-001 | LQIP aanzetten en de bibliotheek bijwerken**
- Trigger: beheerder vinkt de LQIP-instelling aan op de IMGX-tab en slaat op
- Hoofdstroom: (1) instelling aan (2) beheerder start batch `missing` of `wp imgx generate`
  (3) elke bijlage krijgt per maat een `.lqip.jpg` (4) nieuwe uploads krijgen ze automatisch, sync of async
- Alternatief: A1 (stap 3) PNG met alfa → `has-alpha`, blijft overgeslagen; A2 (stap 3) server zonder
  WebP/AVIF → toch LQIP's
- Foutpad: E1 (stap 3) LQIP ≥ bron → als `larger-than-source` overgeslagen, run gaat door
- Postconditie: registratie bevat `lqip` per maat
- Covers: AC-101, AC-103, AC-104, AC-107, AC-109, AC-112

**UC-102 | Actor: ROLE-002 | Pagina met placeholder laden**
- Trigger: bezoeker opent een pagina met bijlage-afbeeldingen (`the_content`, Oxygen 6/Breakdance,
  Oxygen Classic, of `whole_page`)
- Hoofdstroom: (1) `<img>` komt binnen met LQIP als achtergrond (2) LQIP wordt geschilderd en telt als
  LCP-kandidaat (3) hi-res (of WebP/AVIF) dekt hem af
- Alternatief: A1 `whole_page` + `fetchpriority="high"` → LQIP ook gepreload; A2 `picture_enabled = 0`
  → geen `<picture>`, wel de LQIP-achtergrond op de kale `<img>`
- Foutpad: E1 LQIP niet geregistreerd of ontbreekt op schijf → `<img>` ongewijzigd
- Covers: AC-102, AC-106, AC-108, AC-110, AC-111

**UC-103 | Actor: ROLE-001 | LQIP's verwijderen**
- Trigger: bijlage permanent verwijderen, batch `delete` of `wp imgx delete`
- Postconditie: geen `.lqip.jpg` meer van die bijlage; originelen intact
- Covers: AC-105, AC-109

Permissiematrix: n.v.t. — ROLE-002 voert geen handelingen uit (D7), beheerhandelingen zijn bestaand
IMGX-gedrag achter `manage_options` + nonce (`class-batch-processor.php:86-106`).

---

## 8. Open vragen

| ID | Vraag | Raakt | Status |
|----|-------|-------|--------|
| OQ-101 | Moet de LQIP-achtergrond ook verschijnen als `picture_enabled = 0`? | AC-107, AC-111 | **Beantwoord: ja** (V5, gebruiker, 2026-10-23). LQIP-uitvoer hangt alleen af van `lqip_enabled` plus de bestaande globale gates (alleen frontend-requesttypes, builder-canvas overslaan, filters per afbeelding/request). `<picture>` blijft `picture_enabled` + formaten vereisen. Verwerkt in §2, §3, D9, AC-111. |

Geen open vragen meer.

---

## 9. Risico's

| ID | Risico | Mitigatie / acceptatie |
|----|--------|------------------------|
| R-101 | **Lazy afbeeldingen laden hun LQIP toch direct.** Een CSS-achtergrond op een gerenderd element wordt opgehaald ongeacht `loading="lazy"`. Scope U2 (alle bijlagen) betekent dat elke afbeelding op de pagina een extra request kost (orde 5 kB voor 1024×768 bij 0,055 bpp). | **Expliciet geaccepteerd door de gebruiker** (V4, 2026-10-23: *"Toch alle afbeeldingen"*). Verifiëren op dev.local: aantal `.lqip.jpg`-requests in DevTools op een pagina met ≥10 afbeeldingen noteren in de PR. Per afbeelding uit te zetten via `imgx_should_render_picture`. |
| R-102 | **srcset kiest een grotere kandidaat dan `src`.** D1 neemt de LQIP van de `src`-maat; toont de browser een grotere srcset-kandidaat, dan wordt de LQIP opgeschaald en kan hij door F2 onder de 0,05 bpp zakken en geen LCP-kandidaat zijn. | Geaccepteerd (D1). AC-110 meet het echte effect op de hero; faalt dat, dan is dat input voor een vervolg (zie Won't Have). |
| R-103 | **Oxygen Classic niet live te testen.** dev.local draait Oxygen 6.2-rc.1. | Classic loopt via dezelfde `transform()` (`class-oxygen.php:101`) en wordt gedekt door AC-106/AC-107/AC-111 (H). Restrisico geaccepteerd. |
| R-104 | **`<img>` wordt niet meer letterlijk overgenomen.** Het `style`-attribuut verandert; thema-CSS met `background` op `img` kan conflicteren. | Inline wint van stylesheet-`background`; een bestaand inline `background` wordt gerespecteerd (AC-106). Per afbeelding uit te zetten via `imgx_should_render_picture`. |
| R-105 | **`picture_enabled = 0` betekent niet meer "frontend ongemoeid".** Wie `<picture>` uitzet en LQIP aan laat, krijgt nog steeds een gewijzigde `<img>` (V5). | Bewust (V5). De admintekst bij beide instellingen vermeldt dat ze los van elkaar werken; AC-111 legt het gedrag vast. |
| R-106 | **Verouderde variant na afbeelding bewerken (bestaande bug).** `generate_one()` beschouwt een geregistreerde variant als klaar zolang het bestand bestaat (`class-generator.php:475-483`), ook als de bron intussen in de WordPress-editor is bewerkt. Dat raakt WebP/AVIF nu al, en straks ook de LQIP. | Geaccepteerd, buiten scope van deze release (S2). Vervolgwerk: bij gewijzigde bron opnieuw genereren. Omweg tot dan: `wp imgx generate --ids=<id> --force`. |

---

## 10. Won't Have (deze release)

- LQIP per **weergavemaat** (aparte bestanden per srcset-breedte of per `sizes`-breakpoint) — D1.
- LQIP in **AVIF of WebP** — F3, D1.
- **Inline base64-blur** of SVG-blur placeholders — U1 (vervallen voor de tiny-laag, zie §12).
- **Preload buiten `whole_page`**, of preload van de hi-res — D5, F3.
- Een apart **stylesheet** of JS (bijv. fade-in, verwijderen van de achtergrond na laden) — D3.
- LQIP voor **niet-bijlagen** (externe afbeeldingen, CSS-achtergronden, `<img>` die al in een
  `<picture>` staan) — U2.
- LQIP voor **PNG met alfa** — D4.
- Uitgebreide **statistieken** voor LQIP op de IMGX-tab of in de mediakolom, buiten wat vanzelf
  meetelt via `Variants::count_files()`.
- Een instelbaar bpp-doel of instelbaar plafond in de admin — alleen de startkwaliteit is filterbaar (D2).
- Het oplossen van R-106 (verouderde variant na bewerken) — S2.

---

## 11. Gereed wanneer

Een beheerder op dev.local zet LQIP aan, draait batch `missing`, en een bezoeker ziet op een
Oxygen 6-pagina een `.lqip.jpg` als LCP-entry (AC-110), terwijl met de instelling uit niets
verandert (AC-102). `php tests/run.php` is groen voor AC-101, AC-105, AC-106, AC-108 en de
zelftesthelften van AC-102, AC-104, AC-107, AC-109 en AC-111. De live-helften van AC-102, AC-104,
AC-107, AC-109 en AC-111, plus AC-103, AC-110 en AC-112, zijn op dev.local afgevinkt.
Externe afhankelijkheden: geen. Alles draait tegen WordPress core, GD/Imagick en de lokale uploadsmap;
geen AC vereist een extern account.

---

## 12. Aanvulling 2026-10-30: tiny base64-laag

Bron: gebruikersfeedback 2026-10-30 ("de low res is niet zoals het voorbeeld van CSS Wizardry: blurry afbeelding"). Het artikel gebruikt drie lagen: tiny inline base64 (direct een blurry eerste frame, geen LCP-kandidaat) → LQIP-bestand (LCP) → hi-res. Keuze van de gebruiker: gelaagd zoals het artikel, de LQIP blijft. Dit vervangt voor de tiny-laag de Won't-Have "Inline base64-blur" (U1) hierboven.

| ID | Besluit | Bron |
|----|---------|------|
| T1 | Eén tiny per bijlage (niet per maat), van het origineel (`wp_get_original_image_path()`, anders `get_attached_file()`); langste zijde 16 px, JPEG op vaste kwaliteit 40 (gepind via `wp_editor_set_quality`/`jpeg_quality`), kale base64 in postmeta `_imgx_lqip_tiny`; geschreven via een tijdelijk bestand dat direct wordt verwijderd | gebruikersfeedback 2026-10-30 |
| T1b | Bron van de tiny is `get_attached_file()` (het door WordPress geschaalde bestand), niet het ongeschaalde origineel: zelfde resultaat van 16 px met veel minder decodegeheugen bij enorme originelen. De alfa-beslissing blijft op het origineel (`original_has_alpha()`). Wijzigt de bron van T1. | code review 2026-10-30 |
| T2 | Base64 > 2048 tekens wordt niet opgeslagen (debug-log); validatie `^[A-Za-z0-9+/]+={0,2}$` bij opslaan en bij uitserveren | gebruikersfeedback 2026-10-30 |
| T3 | Zelfde schakelaar en levenscyclus als de LQIP: alleen met `lqip_enabled = 1`, geen tiny voor alfa-originelen, gemaakt in dezelfde `generate()`-ronde; `needs_generation()` is waar zolang de tiny ontbreekt (niet voor alfa), zodat batch "ontbrekende" 2.12.0-bijlagen aanvult; `delete`-paden wissen de meta, `--force`/"alles" maakt opnieuw | gebruikersfeedback 2026-10-30 |
| T4 | `background-image:url("<lqip>"),url("data:image/jpeg;base64,<tiny>")`; zonder LQIP voor die maat alleen de tiny; alle bestaande escaping- en samenvoegregels ongewijzigd. De meta komt uit de postmeta-cache die `Variants::get()` al vult: geen extra query per afbeelding | gebruikersfeedback 2026-10-30 |
| T5 | Preload ongewijzigd: nog steeds de `.lqip.jpg` | gebruikersfeedback 2026-10-30 |
| T6 | Versie 2.12.1 (patch, op verzoek gebruiker) | gebruikersfeedback 2026-10-30 |

Gevolg: ongeveer 1 KB extra HTML per afbeelding. Bekende rest: mislukt of overschrijdt de tiny de grens, dan blijft de meta leeg en meldt `needs_generation()` de bijlage bij elke batch "ontbrekende" opnieuw.
