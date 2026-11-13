# Thin Delta Brief — module SEO Health Check (`seo_health_check`)

Status: concept, 2026-11-13. Verwerkt: de gebruikersantwoorden U6–U9 (U6 voegt slice 2 toe: MCP-abilities) en
doc-review herstelronde 1 (B1–B2, M1–M13, minors 1–16).

**Scope.** De losse plugin **SEO Health Check 0.10.1** (`../seo-health-check/`, `seo-health-check.php:6`, `:21`)
wordt een module van PDK Theme Options. PDK staat nu op 2.13.0 (`pdk-theme-options/pdk-theme-options-plugin.php:6`,
`:24`) en gaat naar **2.14.0**. Dit is geen volledige PRD. Het gedrag van SEOHC staat in
`../seo-health-check/README.md` en blijft hetzelfde. Deze brief gaat alleen over wat verandert doordat SEOHC een
module wordt, plus de MCP-toegang. Precedent: IMGX (`modules/imgx/class-pdk-imgx.php`, `docs/modules/imgx.md`).

**Slices.**
- **Slice 1** — SEOHC importeren als module: §1–§6, FR-001 t/m FR-009, AC-001 t/m AC-019 en AC-037.
- **Slice 2** — MCP-abilities: §7, FR-010 t/m FR-015, AC-020 t/m AC-036 en AC-038.

Slice 2 begint pas als slice 1 groen is.

**Verzoek (letterlijk):** *"importeer de seo-health-check als een module"* — en later: *"Gebruik dev.local als test
omgeving. zet hierbij dummy data als test neer."*

**Antwoorden gebruiker (normatief):**
- U1 — Alle zes SEOHC-pagina's komen in de PDK-instellingenpagina, als één tab "SEO Health" met sub-tabs. Er komt geen
  eigen top-level menu meer. Elke link, redirect, form-action, lijsttabel-URL, notice-link en widget-link wijst naar
  de nieuwe tab/sub-tab.
- U2 — Text domain `seo-health-check` blijft. De .po/.mo-bestanden laden uit de modulemap. Er veranderen geen strings.
- U3 — De PDK-uninstall wist ook alle SEOHC-data, op dezelfde manier als de losse `uninstall.php`.
- U4 — De module staat standaard uit.
- U5 — Testen gebeurt op dev.local (Local; de plugin staat als kopie in `wp-content/mu-plugins/pdk-theme-options/`,
  geladen door `00-pdk-theme-options.php`). Oxygen 6.2-rc.1 blijft actief. Er komt dummy-inhoud die elk issuetype
  triggert. De admin wordt gecontroleerd via WP-CLI.
- U6 (2026-11-13) — MCP via `agent_abilities`: lezen, scannen en bewerken, *"inclusief pagina aanpassingen zoals
  h1"*. Builderpagina's worden geweigerd. Schrijven vereist `edit_post` + `manage_options`, met een rescan na elke
  schrijfactie.
- U7 (2026-11-13) — Is de losse plugin nog actief, dan laat de PDK-uninstall de SEOHC-data staan. Dit sluit OQ-01.
- U8 (2026-11-13) — Alleen beheerders (`manage_options`), zoals in de rest van PDK. Dit is een geaccepteerd risico en
  komt in de CHANGELOG.
- U9 (2026-11-13) — De slash-bug wordt in de kopie hersteld en in de CHANGELOG vermeld. Dit sluit OQ-02.

---

## 1. Besluiten (slice 1)

Herkomst: D1–D6 komen van de coördinator, D7–D14 en D25–D26 van de analist, D27 van de coördinator. D11 en D14 zijn
door de gebruiker bevestigd. Alle besluiten zijn omkeerbaar.

| ID | Besluit | Source |
|----|---------|--------|
| D1 | **Namen blijven gelijk.** Tabellen `{prefix}seohc_issues/_pages/_links`, opties `seohc_*`, transients `seohc_link_*`, hooks `seohc_process_batch`/`seohc_scan_single_post`/`seohc_scheduled_scan`, AS-groep `seo-health-check`, acties `seohc_*`, optiegroepen, filters `seo_health_check_*` en `SEOHC_DB_VERSION = '4'`. | coördinator; `seo-health-check.php:22`, `uninstall.php:17-54` |
| D2 | **Guard tegen de losse plugin.** De module checkt `defined( 'SEOHC_VERSION' )` op `plugins_loaded`. Vóór die check definieert de module geen `SEOHC_*`-constante en laadt hij geen `SEOHC_*`-klasse.<br>Waarom: de losse plugin doet een kale `define()`/`require_once` (`seo-health-check.php:21-42`), en PDK laadt altijd eerder. Als mu-plugin, en als gewone plugin omdat `pdk-` alfabetisch vóór `seo-` komt. De modules worden ook al bij het laden van het bestand opgebouwd (`pdk-theme-options-plugin.php:57`, `class-pdk-plugin.php:89-99`).<br>Draait de losse plugin, dan toont de tab alleen een Nederlandse melding: deactiveer de losse plugin, en verwijder hem niet via het Plugins-scherm (D26). | coördinator; `class-pdk-imgx.php:51-60` |
| D3 | **Niet overgenomen:** `class-seohc-updater.php`, `seo-health-check.php` (wordt de wrapper), de Plugins-links (`class-seohc-admin.php:51`, `:140-155`), `composer.json`, `phpcs.xml.dist`, `readme.txt`, `.gitattributes`, `.gitignore` en `README.md`. De uninstall verhuist (D11). | coördinator |
| D4 | **Geen activatiehook.** De tabellen komen via de bestaande `maybe_upgrade()` bij elke load (`class-seohc-plugin.php:38`, `class-seohc-installer.php:52-56`). | coördinator |
| D5 | **Versie en documentatie.** 2.14.0 op beide plekken, CHANGELOG-kop `## [2.14.0] — 2026-11-13`, `docs/modules/seo-health-check.md` en een README-rij. Aan `pdk-mu-installer/` verandert niets. Voor slice 2: zie D24. | coördinator; `CLAUDE.md` |
| D6 | **Locatie en scope van de kopie.** De code staat in `pdk-theme-options/modules/seo-health-check/`. De klassen blijven zo ongewijzigd mogelijk: alleen wat §3 en §7.3 noemen. | coördinator |
| D7 | **Naamgeving.**<br>- Slug: `seo_health_check`; label: "SEO Health".<br>- Sub-tab-parameter: `seohc_view`, met `issues` (standaard), `pages`, `scans`, `schedule`, `launch` of `settings`. Een onbekende waarde valt terug op `issues`.<br>- Labels: de msgids uit `class-seohc-admin.php:69-74`.<br>- Botst niet met de queryvariabelen van SEOHC. | U1; `class-seohc-admin.php:204-236`, `:343`, `:389`; `class-seohc-schedule-page.php:209`, `:230` |
| D8 | **Eén URL-helper.** `PDK_SEO_Health_Check::tab_url( string $view = 'issues', array $args = [] )` op elke plek uit §2. De wrapper is altijd geladen, dus de helper werkt ook in cron. | U1; `class-pdk-imgx.php:63-68` |
| D9 | **Tab-registratie.** Toegevoegd aan `$standalone_tabs`, `MODULE_TABS` en de labels in `get_tabs()`, plus een `case` in `render_tab_content()` die `render_inline()` aanroept. | `class-pdk-admin.php:26-38`, `:737-739`, `:799-812`, `:837-893` |
| D10 | **Vertalingen.** Laden via het absolute pad `SEOHC_DIR . 'languages/'` voor `determine_locale()`. `load_plugin_textdomain()` (`class-seohc-plugin.php:65-67`) faalt in MU-modus. | U2, U5 |
| D11 | **Uninstall.** `modules/seo-health-check/uninstall.php` bevat de body van de losse `uninstall.php:13-65`, met een `function_exists`-guard. `pdk-theme-options/uninstall.php` laadt dat bestand met `require`. Twee afwijkingen:<br>(a) **Niet wissen bij een actieve losse plugin.** Als losse plugin telt elk pad dat eindigt op `/seo-health-check.php` (of dat gelijk is aan `seo-health-check.php`), dus ongeacht de mapnaam. Staat zo'n plugin in `active_sitewide_plugins`, dan wordt niets gewist. Daarnaast checkt de lus over `get_sites()` per site de `active_plugins` van die site; is de losse plugin daar actief, dan slaat de lus die site over.<br>(b) **Alle sites.** `get_sites( [ 'fields' => 'ids', 'number' => 0 ] )`. De bron gebruikt de standaardlimiet van 100. | U3, **U7** |
| D12 | **PDK deactiveren.** In `PDK_Plugin::deactivate()`, tenzij de losse plugin actief is (zelfde check als D11a):<br>- Altijd `wp_unschedule_hook()` voor de 3 hooks, plus `as_unschedule_all_actions( 'seohc_process_batch' )` als die functie bestaat. Hiervoor zijn geen SEOHC-klassen nodig. Het moet altijd, omdat `ensure_booked()` het event bij elke `init` opnieuw boekt (`class-seohc-schedule.php:35`, `:161-169`).<br>- Scanstatus `cancelled` en de lock: alleen als de module geboot is (`class-seohc-installer.php:40-46`). | U3; `class-pdk-plugin.php:184-192` |
| D13 | **Assets en screen options.** Css, js en `shcAdmin` laden op `toplevel_page_pdk-theme-options` met `tab=seo_health_check`; op `index.php` alleen de css. Dit vervangt `class-seohc-admin.php:162-176`. De screen option per pagina zit op `load-toplevel_page_pdk-theme-options`, voor `issues` en `pages` (oorspronkelijk `:78-79`). | U1 |
| D14 | **Capability.** De tab vereist `manage_options` (`class-pdk-admin.php:162`, `:725`). De filter `seo_health_check_capability` blijft daarnaast gelden voor de handlers. Wie die filter lager zet, verliest de toegang (R-03). | **U8** |
| D25 | **Fix in de mailer.** `class-seohc-mailer.php:157` gebruikt `SEOHC_Admin::MENU_SLUG`, maar die klasse laadt alleen in de admin (`class-seohc-plugin.php:49-59`). Een rapportmail vanuit cron kan daardoor een fatal geven, ook in de losse 0.10.1. `tab_url()` (D8) haalt die afhankelijkheid weg. Komt als fix in de CHANGELOG. | review minor 9 |
| D26 | **De losse plugin niet via "Verwijderen" weghalen.** Plugins → Verwijderen draait de `uninstall.php` van de losse plugin (`uninstall.php:13-65`). Die wist de gedeelde tabellen en opties, en dus ook de data van de module (D1). De juiste route is: deactiveren en daarna de map verwijderen via FTP of de shell (`rm -r wp-content/plugins/seo-health-check`), zonder dat er een uninstall draait. Deze waarschuwing staat in de CHANGELOG, in `docs/modules/seo-health-check.md`, in de melding van D2 en in UC-03. | review M1 |
| D27 | **Ctrl+S.** `assets/js/admin-save.js` klikt op `#submit` of op de eerste primaire knop (`:14-21`, `:42`). Op Issues is dat "Volledige scan starten" of "Scan annuleren" (`class-seohc-admin.php:615`, `:637`), op Launch checks "Run checks" (`class-seohc-launch-checks.php:197`). Regel: `admin-save.js` doet niets als de gevonden knop binnen `.shc-panel` staat. Ctrl+S op Settings en Automatic scan (een `options.php`-form buiten `.shc-panel`) slaat gewoon op. | coördinator (review minor 10) |

**Constanten in de wrapper**, pas gezet na de guard:
- `SEOHC_VERSION = PDK_PLUGIN_VERSION`
- `SEOHC_DB_VERSION = '4'`
- `SEOHC_FILE = <wrapper>`
- `SEOHC_DIR = <modulemap>/`
- `SEOHC_URL = PDK_PLUGIN_URL . 'modules/seo-health-check/'`

Gevolg: de wrapper heeft geen `PluginURI`, dus de projectlink in "About this plugin" verdwijnt
(`class-seohc-settings.php:527-531`).

---

## 2. Geverifieerde plekken die een oude slug gebruiken

Elk van deze plekken gaat via `tab_url()` (D8).

| Bestand:regel | Wat | Nieuwe view |
|---------------|-----|-------------|
| `admin/class-seohc-admin.php:313` | `redirect_with_notice()` | `issues` + `seohc_notice` |
| `admin/class-seohc-admin.php:450` | hidden `page` (`esc_attr( self::MENU_SLUG )`) | hidden `page`, `tab`, `seohc_view=issues` |
| `admin/class-seohc-admin.php:521`, `:524`, `:534` | regel met wijzigingen | `issues` + `seohc_status` |
| `admin/class-seohc-admin.php:546` | "See all scans" | `scans` |
| `admin/class-seohc-admin.php:577` | hidden `page` (`esc_attr( self::PAGES_SLUG )`) | hidden `page`, `tab`, `seohc_view=pages` |
| `admin/class-seohc-admin.php:142` | Plugins-link | vervalt (D3) |
| `admin/class-seohc-pages-list-table.php:203-208` | `'page' => SEOHC_Admin::MENU_SLUG` | `issues` |
| `admin/class-seohc-scans-page.php:38`, `:157` | links naar Issues | `issues` |
| `admin/class-seohc-dashboard-widget.php:76`, `:79`, `:82`, `:101`, `:161` | widget | `issues` / `pages` / `scans` |
| `admin/class-seohc-schedule-page.php:258` | redirect na de testmail | `schedule` + `seohc_notice` |
| `class-seohc-launch-checks.php:161` | redirect na het draaien | `launch` |
| `class-seohc-mailer.php:157` | link in de mail | `issues` (D25) |

Wat al werkt: paginering en sortering van `WP_List_Table`, de views via `remove_query_arg()`
(`class-seohc-issues-list-table.php:119`, `class-seohc-pages-list-table.php:111`) en de `_wp_http_referer` van
`options.php`. Er zijn geen bulkacties. De links naar andere plugins in `class-seohc-forms.php:119`, `:167` en `:217`
blijven staan.

---

## 3. Bestandsindeling en wijzigingen

```
pdk-theme-options/modules/seo-health-check/
  class-pdk-seo-health-check.php   NIEUW  guard (D2), constanten, requires (seo-health-check.php:27-42 zonder updater),
                                          boot, is_active(), tab_url(), render_inline(), enqueue + screen options (D13);
                                          slice 2: kop-helpers (D20, D21)
  uninstall.php                    NIEUW  body van ../seo-health-check/uninstall.php:13-65 + D11 (a)(b)
  includes/*.php, includes/admin/*.php   KOPIE  zonder class-seohc-updater.php
  assets/admin.css, admin.js       KOPIE  ongewijzigd
  languages/seo-health-check-nl_NL.po/.mo, index.php   KOPIE byte-gelijk
```

**Wijzigingen in de kopie (slice 1).** Verder blijft alles gelijk.
- `class-seohc-plugin.php`: `SEOHC_Updater::init()` vervalt (`:45`); D10 (`:65-67`).
- `admin/class-seohc-admin.php`: geen menu meer (`:35`, `:57-80`); D13; `action_links` vervalt; URL's volgens §2.
- Overige admin-klassen, `class-seohc-launch-checks.php` en `class-seohc-mailer.php`: alleen de URL's volgens §2.

**PDK (slice 1).**
- `class-pdk-plugin.php`: `$module_map` en D12.
- `class-pdk-settings.php`: default `enabled = false`, een label en een NL-omschrijving.
- `class-pdk-admin.php`: D9.
- `assets/js/admin-save.js`: D27.
- `uninstall.php`: `require` van het modulebestand.
- `pdk-theme-options-plugin.php`, `CHANGELOG.md`, `README.md` en `docs/modules/seo-health-check.md`: D5, D25, D26.

**Tests** (buiten de zipball, `tests/run.php:7-9`):

| Bestand | Dekt |
|---------|------|
| `tests/test-seo-health-check.php` (H) | AC-001, AC-015, AC-016 (H), AC-017, AC-018, AC-019, AC-035, AC-036, AC-037 (H) |
| `tests/test-agent-abilities.php` (H, bestaand) | AC-020 |
| `tests/live/seohc-dummy-mu.php` | op dev.local als `wp-content/mu-plugins/seohc-dummy.php` (§5) |
| `tests/live/seohc-seed.php` | seed; met argument `cleanup` ruimt hij op |
| `tests/live/seohc-check.php` | AC-005; exit 0 = geslaagd |
| `tests/live/seohc-admin.php` | AC-002 (admin-deel), AC-008 t/m AC-011, AC-037 (L); bootstrap: zie §6 |
| `tests/live/seohc-abilities.php` (slice 2) | AC-021 t/m AC-034 en AC-038 |

---

## 4. Rolmodel

| ROLE-ID | Naam | Soort | Auth | Grens | Bron |
|---------|------|-------|------|-------|------|
| ROLE-001 | WordPress-beheerder | human | sessie + `manage_options` | site-breed | `class-pdk-admin.php:162`, `class-seohc-plugin.php:80` |
| ROLE-002 | Achtergrondwerk (WP-Cron of Action Scheduler) | system | n.v.t. | intern | `class-seohc-scan-queue.php:266-300`, `class-seohc-schedule.php:29-35` |
| ROLE-003 | AI-agent via MCP | system | logt in als een gebruiker met `manage_options` (via de MCP-adapter); per post ook `edit_post` | alleen via de abilities | U6; `class-pdk-agent-abilities.php:5-13` |

Er is één menselijke rol. Daarom staan de use cases in §9 en is er geen `docs/use-cases.md`. Een permissiematrix is
niet van toepassing, ook omdat er maar één menselijke rol is. De toegang van ROLE-003 is vastgelegd in AC-021, AC-022
en AC-038.

---

## 5. Dummy data (dev.local)

**Feiten uit de bron:**
- Er zijn 15 issuetypes (`class-seohc-issue-types.php:25-84`).
- De provider is Yoast, Rank Math of `none` (`class-seohc-seo-meta.php:20-28`). Wat dev.local heeft, is onbekend.
- `custom_description` komt alleen uit de meta van Yoast of Rank Math (`:87-89`, `:112-114`). Zonder die meta krijgt
  elke post `description_missing` (`class-seohc-scanner.php:134-141`).
- Met `theme_outputs_h1 = 1` telt de scanner er een H1 bij (`:277-280`). De seed zet die instelling daarom op 0.
- `rendered_fetch_failed`: de pagina wordt alleen opgehaald bij lege content met `auto` (`class-seohc-content-resolver.php:31-52`).
- Links naar bestanden in uploads worden op schijf gecontroleerd (`class-seohc-link-checker.php:130-134`).
- `image_too_large` gaat op bestandsgrootte (`class-seohc-scanner.php:192-242`).
- Een pagina telt niet als wees als hij de voorpagina, de berichtenpagina of een menu-item is (`class-seohc-repository.php:462-470`).
- Alt-tekst is alleen te bewerken voor een bijlage (`class-seohc-inline-edit.php:68-75`).

**Dev-hulp `seohc-dummy.php`.** Werkt alleen op posts met de meta `_seohc_dummy`:
- De filter `seo_health_check_seo_meta` (`class-seohc-seo-meta.php:77`) zet `description` en
  `custom_description = true` als de post de meta `_seohc_dummy_description` heeft.
- `pre_http_request` geeft een `WP_Error` voor URL's met `seohc-dummy-s17`.

**Testinstellingen** (de rest blijft standaard): `theme_outputs_h1 = 0`, `max_image_kb = 300`, `max_title_length = 100`.

**Bestanden:**
- `uploads/seohc-dummy/groot.jpg`: 400 KB willekeurige bytes, geen bijlage.
- `uploads/seohc-dummy/klein.jpg`: een echte JPEG van 1×1 px (GD), via `wp_insert_attachment` geregistreerd als
  bijlage **A01**, zonder alt en met `_seohc_dummy = A01`.
- Niet in `uploads/seo-health-check/`, want die map wordt bij de uninstall gewist.

**Alle S-posts:**
- status `publish`, prefix "SEOHC dummy", meta `_seohc_dummy = <S-ID>`;
- aangemaakt met `wp_insert_post( wp_slash( $postarr ) )`;
- de seed is idempotent: hij verwijdert eerst alles met `_seohc_dummy`.

**Slash-markering (B1).** S09, S18 en S18b bevatten dit blok met **letterlijke backslashes**:

    <!-- wp:paragraph {"placeholder":"a<BS>u0026b"} --><p>C:<BS>pad</p><!-- /wp:paragraph -->

`<BS>` staat in dit document voor één backslash. De seed bouwt de markering met `chr( 92 )`, zodat ze niet afhangt van
escaping in dit document of in een editor:

    $bs = chr( 92 );
    $marker = '<!-- wp:paragraph {"placeholder":"a' . $bs . 'u0026b"} --><p>C:' . $bs . 'pad</p><!-- /wp:paragraph -->';

`seohc-check.php` en `seohc-abilities.php` controleren eerst dat de `post_content` van S09, S18 en S18b `$marker`
letterlijk bevat. Ontbreekt de markering, dan exit 1. Zo kan de D22-test echt falen.

| S-ID | Type | Inhoud (kern) | Moet minstens opleveren |
|------|------|---------------|-------------------------|
| S00 | post | "SEOHC dummy S00 in orde": één `<h1>`, daarna `<h2>`/`<h3>`, ≥350 woorden, een unieke beschrijving van ≤160 tekens, geen afbeeldingen of links | **0 open issues** |
| S01 | post | lege `post_title` | `title_missing` |
| S02 | post | titel van 150 tekens | `title_too_long` |
| S03 + S04 | post | gelijke titel "SEOHC dummy dubbele titel" | `title_duplicate` op beide |
| S05 | post | geen `_seohc_dummy_description` | `description_missing` |
| S06 | post | beschrijving van 200 tekens | `description_too_long` |
| S07 + S08 | post | gelijke beschrijving van 120 tekens | `description_duplicate` op beide |
| S09 | post | `<img src="<url van klein.jpg>">` zonder alt, plus de markering | `image_missing_alt` met `object_id` = A01 |
| S10 | post | `<img src=".../seohc-dummy/groot.jpg" alt="Groot">` | `image_too_large` |
| S11 | post | klassieke HTML zonder `<h1>` | `h1_missing` |
| S12 | post | twee `<h1>`'s plus een link naar S00 | `h1_multiple` |
| S13 | post | klassiek: `<h1>Intro</h1><h2>Deel</h2><h4>Detail</h4>` | `heading_skip` |
| S14 | post | ongeveer 20 woorden | `thin_content` |
| S15 | post | `<a href=".../uploads/seohc-dummy/bestaat-niet.pdf">` | `broken_internal_link` |
| S16 | page | geen inkomende links, niet in een menu | `no_incoming_links` |
| S17 | page | slug `seohc-dummy-s17`, lege content | `rendered_fetch_failed` |
| S18 | post | `<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Blok intro</h1><!-- /wp:heading -->` + `<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4><!-- /wp:heading -->` + `<!-- wp:html --><h3>In html-blok</h3><!-- /wp:html -->` + de markering | `heading_skip` (slice 2) |
| S18b | post | alleen `<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Blok detail</h4><!-- /wp:heading -->` + de markering | `h1_missing` (slice 2) |

Extra issues op S-posts zijn toegestaan; alleen S00 moet leeg zijn. De bestaande content van dev.local wordt
meegescand, maar de checks kijken alleen naar de S-posts.

**Builderpagina voor slice 2.** Daarvoor gebruiken we een bestaande Oxygen 6-pagina op dev.local; het ID komt in de
evidence. Welke meta-sleutel daarbij hoort, wordt vastgesteld zoals D20(c) beschrijft.

---

## 6. Acceptatiecriteria — slice 1

**Verificatie.**
- **H** = `php tests/run.php`.
- **L** = live op dev.local. Kopieer eerst `pdk-theme-options/` naar de mu-plugins-kopie en zet `seohc-dummy.php`
  erbij. `wpl` is de WP-CLI van Local.

**Admin-bootstrap (M10).** Geldt voor AC-002 (admin-deel), AC-008 t/m AC-011 en AC-037.
Commando: `wpl --user=1 --exec='define("WP_ADMIN", true);' eval-file tests/live/seohc-admin.php`. Het script doet in
deze volgorde:
1. `require_once ABSPATH . 'wp-admin/includes/admin.php';`
2. `$_GET`/`$_REQUEST` vullen met `page=pdk-theme-options`, `tab`, `seohc_view` en eventuele filters, plus
   `$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $_GET );`
3. `$GLOBALS['pagenow'] = 'admin.php'; $GLOBALS['plugin_page'] = 'pdk-theme-options'; $GLOBALS['hook_suffix'] = 'toplevel_page_pdk-theme-options';`
4. `do_action( 'admin_menu' );`, daarna `do_action( 'admin_init' );`
5. `set_current_screen( 'toplevel_page_pdk-theme-options' );`
6. `do_action( 'load-toplevel_page_pdk-theme-options' );`
7. `do_action( 'admin_enqueue_scripts', 'toplevel_page_pdk-theme-options' );`
8. Met output buffering: `do_action( 'toplevel_page_pdk-theme-options' );`. `add_menu_page()` hangt de render-callback
   aan deze hook.

Een `set_error_handler()` vangt notices en warnings op. Elke opgevangen melding laat de check falen.

### FR-001 — Module en standaard (Must) — Source: U4, D7, D9
- **AC-001** (H): `get_defaults()['seo_health_check']['enabled'] === false`. Het label en de omschrijving bestaan, en
  `$module_map` wijst naar een bestand dat bestaat.
- **AC-002** (L). Voorwaarde: dev.local had nog nooit SEOHC-data, dus dit draait vóór de eerste keer aanzetten en vóór
  AC-012. Met de module uit geldt:
  - `SHOW TABLES LIKE '%seohc%'` geeft niets terug;
  - `wpl option get seohc_db_version` geeft een fout;
  - `class_exists('SEOHC_Plugin', false)` is false;
  - de cron-lijst bevat geen `seohc_`;
  - in de admin-bootstrap staat in `$GLOBALS['menu']` geen `seo-health-check`, en in
    `$GLOBALS['submenu']['pdk-theme-options']` geen `pdk-theme-options&tab=seo_health_check`.

### FR-002 — Laden en schema (Must) — Source: D2, D4, D6, D10
- **AC-003** (L): na `PDK_Settings::update(['seo_health_check'=>['enabled'=>true]])` en een nieuwe `wpl eval` geldt:
  - de 3 tabellen bestaan, met de kolommen uit `class-seohc-installer.php:75-121`;
  - `seohc_db_version` is `'4'`;
  - `seohc_settings` heeft 15 sleutels;
  - `SEOHC_FILE` en `SEOHC_DIR` liggen onder `…/mu-plugins/pdk-theme-options/modules/seo-health-check/`;
  - `class_exists('SEOHC_Updater')` is false.
- **AC-004** (L): na `DROP TABLE {prefix}seohc_links` en `delete_option('seohc_db_version')` staat de tabel er bij de
  volgende `wpl eval` weer.

### FR-003 — Scannen end-to-end (Must) — Source: U5, §5
- **AC-005** (L): draai eerst `seohc-seed.php`, daarna `seohc-check.php`. Het checkscript:
  1. controleert de markering (§5) en stopt met exit 1 als die ontbreekt;
  2. start de scan met `SEOHC_Scan_Queue::start()`;
  3. meldt dat er een batch klaarstaat: `as_has_scheduled_action('seohc_process_batch')` als `use_action_scheduler()`
     waar is, anders `wp_next_scheduled('seohc_process_batch')`;
  4. herhaalt daarna, met 1 s pauze en hoogstens 300 s: `wp_cache_delete( 'seohc_scan_state', 'options' )` (minor 1)
     en `process_batch()`, tot de status `done` is;
  5. print de provider en het mechanisme.

  Exit 0 alleen als:
  - de status `done` is en `processed === total`;
  - elke S-ID, behalve S18 en S18b, een open issue van het verwachte type heeft;
  - S00 nul open issues heeft;
  - alle 15 typen van `SEOHC_Issue_Types::all()` gedekt zijn.

  Dit moet bij elke provider slagen.
- **AC-006** (L): met `rescan_on_save = 1` en een `<h1>` in S11 via `wp_update_post( wp_slash( … ) )` geeft
  `wp_next_scheduled('seohc_scan_single_post', [S11])` een tijdstip. Na `wpl cron event run seohc_scan_single_post`
  heeft `h1_missing` van S11 een `resolved_at`.
- **AC-007** (L): na `update_option('seohc_schedule', ['enabled'=>1,'days'=>['mon','tue','wed','thu','fri','sat','sun'],'time'=>'03:00','email'=>'','keep'=>1])`
  ligt `wp_next_scheduled('seohc_scheduled_scan')` in de toekomst.

### FR-004 — Admin als PDK-tab (Must) — Source: U1, D7–D9, D13, D27, §2
- **AC-008** (L, admin-bootstrap):
  - in `$GLOBALS['menu']` staat geen `seo-health-check`;
  - `$GLOBALS['submenu']['seo-health-check']` bestaat niet;
  - `$GLOBALS['submenu']['pdk-theme-options']` bevat `pdk-theme-options&tab=seo_health_check` met het label "SEO Health".
- **AC-009** (L, admin-bootstrap, na seed en scan; bij `issues` met `s=SEOHC dummy`, minor 6). Voor alle zes views
  geldt:
  - er is geen notice of warning opgevangen;
  - de actieve tab is "SEO Health";
  - er zijn zes links met `tab=seo_health_check&seohc_view=<v>`;
  - er staat geen `class="pdk-settings-form"` in de HTML.

  Per view moet de HTML ook het volgende bevatten:

  | View | Moet bevatten |
  |------|---------------|
  | `issues` | `id="shc-scan-panel"`, `value="seohc_start_scan"` en de titel van S12 |
  | `pages` | `id="seohc-pages-search-search-input"` |
  | `scans` | `class="wp-list-table widefat striped seohc-runs"` |
  | `schedule` | `name="option_page" value="seohc_schedule_group"` |
  | `launch` | `value="seohc_run_launch_checks"` |
  | `settings` | `value="seohc_settings_group"`, `name="seohc_settings[post_types][]"` en een `_wp_http_referer` met `seohc_view=settings` |
- **AC-010** (L, admin-bootstrap):
  - **Geen oude slug.** De regex `page=seo-health-check|name="page" value="seo-health-check` heeft 0 treffers in de
    HTML van AC-009, in `SEOHC_Dashboard_Widget::render()` (met en zonder data) en in de mailbody van
    `SEOHC_Mailer::send_report(true)` (opgevangen via `pre_wp_mail`).
  - **Redirects.** `handle_start_scan`, `handle_cancel_scan`, `handle_rescan_post`, `SEOHC_Launch_Checks::handle_run`
    en `SEOHC_Schedule_Page::handle_test` sturen door naar `tab_url()`, met respectievelijk `issues`, `issues`,
    `issues`, `launch` en `schedule`, en houden `seohc_notice` vast. De redirect wordt opgevangen via `wp_redirect`
    met een exception; de nonce staat in `$_REQUEST['_wpnonce']`.
  - **Paginering en filters.** Met `seohc_issues_per_page = 1` en `seohc_pages_per_page = 1` bevatten de links
    `paged=2`, `orderby=` en `issue_type=` op Issues, en `paged=2` en `band=` op Pages, allemaal
    `page=pdk-theme-options&tab=seo_health_check&seohc_view=<v>`.
  - **GET-forms.** Ze bevatten de hidden velden `page`, `tab` en `seohc_view`.
  - **Mail zonder admin-context** (minor 9). `wpl --user=1 eval` **zonder** `WP_ADMIN` roept
    `SEOHC_Mailer::send_report(true)` aan, met `pre_wp_mail` als vanger. Er volgt geen fatal, en de body bevat
    `tab=seo_health_check&seohc_view=issues`.
- **AC-011** (L, admin-bootstrap):
  - `shc-admin` staat in de wachtrij, css én js, met `src` onder `…/modules/seo-health-check/assets/`;
  - met `tab=modules` staat hij er niet in;
  - op `'index.php'` alleen de css;
  - per pagina: `seohc_issues_per_page` bij `issues` en `seohc_pages_per_page` bij `pages`;
  - `has_action` is waar voor `wp_ajax_seohc_scan_status`, `wp_ajax_seohc_inline_save` en `admin_post_seohc_export`;
  - `has_action( 'wp_dashboard_setup', [ 'SEOHC_Dashboard_Widget', 'register' ] )` is waar (minor 7).
- **AC-037** (H + L, D27):
  - **H:** `admin-save.js` heeft vóór `button.click()` de guard `closest('.shc-panel')`.
  - **L:** in de HTML van AC-009 staan de knoppen `seohc_start_scan` en `seohc_run_launch_checks` binnen `.shc-panel`.
    De opslagknoppen van `settings` en `schedule` staan daar niet in.
  - **Optioneel (Playwright in de temp-prefix):** Ctrl+S op Issues laat `seohc_scan_state` ongewijzigd.

### FR-005 — Guard en overstap (Must) — Source: D1, D2, D26
- **AC-012** (L):
  1. Zet de module uit. Kopieer `../seo-health-check/` naar `wp-content/plugins/` en activeer hem. Draai
     `seohc-check.php` en noteer `summary()`.
  2. Zet de module aan. Controleer:
     - `SEOHC_FILE` staat onder `/plugins/seo-health-check/`;
     - er staat geen "already defined" of "Cannot declare class" in de uitvoer of in `debug.log`;
     - er hangt precies één callback aan `seohc_process_batch` (prioriteit 10);
     - de tab toont de melding uit D2, met de waarschuwing uit D26;
     - er is geen `shc-scan-panel`.
  3. Voer `wpl plugin deactivate seo-health-check` uit. `SEOHC_FILE` staat dan in de modulemap, `summary()` is gelijk
     aan stap 1 en `seohc_db_version` is `'4'`.
  4. Verwijder de map met `rm -r` (D26). `summary()` is nog steeds gelijk.

### FR-006 — Vertaling (Must) — Source: U2, D10
- **AC-013** (L), met `nl_NL`:
  - `__('Missing H1','seo-health-check') === 'H1 ontbreekt'`;
  - `__('Automatic scan','seo-health-check') === 'Automatisch scannen'` (`.po:308-309`, `:762-763`);
  - de HTML van Issues bevat "Volledige scan starten" (`:1295-1296`);
  - de sha1 van de .po en de .mo is gelijk aan die van de bron.

### FR-007 — Uninstall (Must) — Source: U3, U7, D11
- **AC-014** (L):
  - **Voorbereiding:** de tabellen bevatten data, en er bestaan een transient `seohc_link_*`, een bestand in
    `uploads/seo-health-check/`, een geboekte `seohc_scheduled_scan` en de usermeta `seohc_issues_per_page`.
  - **Uitvoeren:** `wpl eval 'define("WP_UNINSTALL_PLUGIN", true); require WPMU_PLUGIN_DIR . "/pdk-theme-options/modules/seo-health-check/uninstall.php";'`.
  - **Moet weg zijn:** de tabellen, de 9 opties uit `uninstall.php:41`, de transients, de usermeta, de 3 hooks en de map.
  - **Moet blijven:** `pdk_theme_options` en `imgx_settings`.
- **AC-015** (H), met stubs voor `$wpdb`, `get_option` en `get_sites`:
  - `pdk-theme-options/uninstall.php` laadt het modulebestand met `require`;
  - `seo-health-check/seo-health-check.php` in `active_plugins`: 0× DROP;
  - `seohc-oud/seo-health-check.php` (andere mapnaam, M2): 0× DROP;
  - niet actief: precies 3× DROP;
  - multisite met twee sites, losse plugin actief op site 2: 3× DROP, alleen voor site 1;
  - netwerkbreed actief: 0×;
  - `get_sites` wordt aangeroepen met `'number' => 0`.

### FR-008 — PDK deactiveren (Should) — Source: D12
- **AC-016** (H + L):
  - **L, in één proces:** start een scan, boek `seohc_scheduled_scan` en roep `PDK_Plugin::deactivate()` aan. Daarna
    geldt in hetzelfde proces:
    - `wp_next_scheduled` is false voor de 3 hooks;
    - met Action Scheduler is ook `as_has_scheduled_action('seohc_process_batch')` false;
    - `seohc_scan_lock` is weg;
    - de status is `cancelled`.
  - **H:**
    - als de module niet geboot is, worden de hooks toch afgemeld en blijft `seohc_scan_state` ongewijzigd;
    - als de losse plugin actief is, wordt niets afgemeld.

### FR-009 — Release en documentatie (Must) — Source: D5, D14, D25, D26, U8, `CLAUDE.md`
- **AC-017** (H, `tests/test-seo-health-check.php`):
  - `Version: 2.14.0` staat op `pdk-theme-options-plugin.php:6`, `'2.14.0'` op `:24`;
  - de eerste versiekop in `CHANGELOG.md` is `## [2.14.0] — 2026-11-13`;
  - de CHANGELOG noemt:
    - de module, standaard uit;
    - de tab met 6 sub-tabs;
    - de overstap, met de waarschuwing uit D26;
    - dat de updater en de Plugins-links vervallen;
    - dat de module alleen voor beheerders is (`manage_options`), ook als de filter lager staat (U8);
    - de mailer-fix (D25);
    - dat de installer ongewijzigd blijft.
- **AC-018** (H, `tests/test-seo-health-check.php`):
  - de README heeft de rij `[**SEO Health Check**](docs/modules/seo-health-check.md)`;
  - dat doc bestaat en beschrijft de tab, de overstap met de waarschuwing uit D26, wat de uninstall wist, de
    dev.local-scripts en de bron (0.10.1).
- **AC-019** (H):
  - `tab_url()` geeft per view de juiste URL, en `issues` bij een onbekende view;
  - een statische scan van de modulemap vindt geen van deze patronen (minor 5):
    - `page=seo-health-check`
    - `'admin.php?page=' . (self|SEOHC_Admin|SEOHC_Settings|SEOHC_Schedule)::`
    - `seo-health-check-launch`
    - `'page' => SEOHC_Admin::MENU_SLUG`
    - `esc_attr( self::MENU_SLUG )`
    - `esc_attr( self::PAGES_SLUG )`
    - `SEOHC_Admin::` in `class-seohc-mailer.php`
  - `php tests/run.php` eindigt met "0 mislukt".

---

## 7. Slice 2 — MCP-abilities (U6)

### 7.1 Besluiten (slice 2)

| ID | Besluit | Source |
|----|---------|--------|
| D15 | **Waar de abilities komen.** In `PDK_Agent_Abilities::register_seo_health_abilities()`, zoals de CSS Framework-abilities (`class-pdk-agent-abilities.php:118-121`, `:134-187`).<br>- Category: de bestaande `pdk-theme-options` (`:27`); "pdk" bestaat niet.<br>- Namen: `pdk-theme-options/seo-<actie>`.<br>- Meta: `show_in_rest`, `mcp.public` en annotaties zoals op `:148-152`.<br>- De omschrijving van de module `agent_abilities` (`class-pdk-settings.php:324`) noemt de SEO-abilities en dat ze alleen voor beheerders zijn (minor 15).<br>- `site_info()` meldt ze, zoals Element Studio (`:437-439`). | U6 |
| D16 | **Wanneer ze geregistreerd worden.** Alleen als `class_exists('PDK_SEO_Health_Check') && PDK_SEO_Health_Check::is_active()`. Onder de guard (D2) is `is_active()` false. Staat `agent_abilities` uit, dan bestaat er geen registrar. | U6 |
| D17 | **Rechten.** Altijd `manage_options` (U8). Schrijven vereist ook `edit_post` op de post. Bij een alt-tekst is `edit_post` nodig op de bijlage, en op de post als `post_id` is meegegeven. Dit staat in de `permission_callback`. De checks in de broncode blijven daarnaast gelden (`class-seohc-inline-edit.php:211-213`, `:311-313`). | U6, U8 |
| D18 | **Rescan na elke schrijfactie.** `SEOHC_Scanner::scan_post()` + `SEOHC_Repository::flag_duplicates()`, zoals in `class-seohc-inline-edit.php:179-181`. Het antwoord: `{ saved, post_id, score, issues:[{id, issue_type, details}], content_skipped? }`. | U6 |
| D19 | **Hergebruik van de inline edit.** `SEOHC_Inline_Edit::save_meta()` en `save_alt()` worden `public`; de logica verandert niet. `seo-list-issues`, `seo-update-meta` en `seo-update-image-alt` laden eerst `includes/admin/class-seohc-inline-edit.php` met `require_once`. Dat bestand laadt normaal alleen bij `is_admin()` (`class-seohc-plugin.php:49-52`), en `seo-list-issues` heeft `fields_by_issue_type()` nodig (minor 12). De fout `seohc_no_seo_plugin` (`:315-318`) wordt `pdk_seo_no_seo_plugin`. | U6 |
| D20 | **Content-writes: alleen raw `post_content`, en alleen als alles klopt.** Geldt voor `seo-set-heading-level`, `seo-insert-h1` en het content-deel van `seo-update-image-alt`. De checks, in deze volgorde:<br>(0) `get_post()` is null → `pdk_seo_not_found`. `SEOHC_Scanner::is_scannable()` is false → `pdk_seo_not_scannable` (M8).<br>(a) `trim(wp_strip_all_tags(post_content))` is leeg → `pdk_seo_builder_content`. De resolver scant dan de gerenderde pagina (`class-seohc-content-resolver.php:34-36`).<br>(b) `content_source = rendered` → `pdk_seo_rendered_source` (`:32-33`).<br>(c) Er staat builder-meta op de post → `pdk_seo_builder_content`, met een melding die naar de builder verwijst. Sleutels: Oxygen Classic `ct_builder_shortcodes`/`ct_builder_json`, Elementor `_elementor_edit_mode`, Bricks `_bricks_page_content_2`, Beaver Builder `_fl_builder_enabled`, Divi `_et_pb_use_builder` = `on`. Voor Oxygen 6/Breakdance zijn `_breakdance_data` en `_oxygen_data` kandidaten. Welke sleutel Oxygen 6.2-rc.1 schrijft, is **niet geverifieerd**: de implementatie stelt dat vast met `wpl post meta list <id>` op een bestaande Oxygen 6-pagina en legt het vast in de code en de evidence (minor 11). Builder-meta wordt alleen op bestaan gecontroleerd, nooit gelezen of geschreven.<br>(d) `current_user_can('unfiltered_html')` is false → `pdk_seo_unfiltered_html`. Anders filtert kses bij het opslaan de hele content (M5).<br>(e) `wp_revisions_enabled( $post )` is false → `pdk_seo_revisions_disabled` (M4).<br>(f) Blok-content: een kop is alleen bewerkbaar als het binnenste blok eromheen `core/heading` is. In elk ander blok (ook `core/html`) geeft `seo-get-headings` `editable = false, reason = unsupported_block`, en een schrijfactie geeft `pdk_seo_unsupported_block` (M9).<br>**Revisie vóór het schrijven (M4).** Heeft geen enkele revisie dezelfde `post_content` als de huidige, dan eerst `wp_save_post_revision( $post_id )`. Daarna `wp_update_post( wp_slash( [ 'ID' => …, 'post_content' => … ] ) )`. | U6, review M4, M5, M8, M9 |
| D21 | **Vorm van de kop-abilities.**<br>- `seo-get-headings` geeft `[ {index, level, text, block, editable, reason} ]` voor alle `<h1>`–`<h6>` in de raw `post_content`, in documentvolgorde. Koppen in HTML-commentaar tellen niet mee.<br>- `seo-set-heading-level`: `post_id`, `index`, `expected_text`, `level`.<br>- `seo-insert-h1`: `post_id`, `text`.<br>**Tekst normaliseren (minor 13).** Zowel `text` als de vergelijking met `expected_text` gebruiken `trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) )`, en dat geldt ook voor de input.<br>**Niveau wijzigen.** Alleen de open- en sluittag van die ene kop veranderen. Bij `core/heading` verandert ook het attribuut `level` in de commentaarregel: bij level 2 wordt het verwijderd (de standaard van de core-serializer), anders wordt het gezet.<br>**H1 invoegen, exacte markup (M13).** Met blokken (`has_blocks()`) komt vooraan: `<!-- wp:heading {"level":1} -->` + `"\n"` + `<h1 class="wp-block-heading">TEKST</h1>` + `"\n"` + `<!-- /wp:heading -->` + `"\n\n"`. Klassiek: `<h1>TEKST</h1>` + `"\n"`. Daarbij is `TEKST` = `esc_html( sanitize_text_field( $text ) )`.<br>**Weigervolgorde bij invoegen:** eerst `theme_outputs_h1 = 1` → `pdk_seo_theme_h1` (`class-seohc-scanner.php:277-290`), daarna een bestaande H1 → `pdk_seo_h1_exists`.<br>**Waarom deze vorm:**<br>(1) Er komt geen vrije HTML binnen, en de rest blijft byte-gelijk.<br>(2) `expected_text` werkt als optimistische lock: wijkt de kop af, dan `pdk_seo_heading_mismatch`.<br>(3) De blokken blijven geldig.<br>(4) Er is altijd een revisie met de oude content (D20).<br>(5) Het dekt `h1_multiple`, `heading_skip` en `h1_missing`.<br>**Bewust niet:** koptekst wijzigen, koppen verwijderen, shortcode-content. | U6 |
| D22 | **Slash-bug herstellen.** `update_alt_in_content()` roept `wp_update_post()` aan zonder `wp_slash()` (`class-seohc-inline-edit.php:267-272`). `wp_insert_post()` haalt slashes uit de invoer, dus backslashes in de content verdwijnen. De kopie krijgt `wp_slash()`. Komt in de CHANGELOG als fix ten opzichte van 0.10.1. | **U9** |
| D23 | **Filter op post_id.** `SEOHC_Repository::where()` krijgt een `post_id`-clausule (`class-seohc-repository.php:736-770`). Status `open` (in de bron `''`), `new`, `unchanged` of `resolved`. "Fixed" is `resolved` (`class-seohc-admin.php:535`). `new_since = scan_started_at()` (`:253-255`). | U6 |
| D24 | **Versie.** Gaan beide slices samen uit, dan blijft het 2.14.0. Is slice 1 al eerder uitgebracht, dan wordt slice 2 versie 2.15.0, met een eigen CHANGELOG-kop. | `CLAUDE.md` |
| D28 | **Alt-tekst op builderpagina's (M7).** De mediabibliotheek wordt altijd bijgewerkt. Het content-deel wordt overgeslagen als een check uit D20(0)–(e) faalt voor `post_id`; het antwoord bevat dan `content_skipped` met de reden (`builder_content`, `rendered_source`, `unfiltered_html`, `revisions_disabled` of `not_scannable`). Overslaan gebeurt via `save_alt( $attachment_id, $value, 0 )`: de broncode checkt dan `edit_post` op post 0 en slaat de content over (`:217-219`). De tekst gaat eerst door `sanitize_text_field()` (M6). Meta-writes kennen geen builder-weigering. | coördinator (review M6, M7) |

### 7.2 Abilities

| Ability (`pdk-theme-options/…`) | Input (`additionalProperties: false`) | Doet | Bron | readonly / destructive |
|---|---|---|---|---|
| `seo-get-status` | — | `get_state()` + `summary()` + provider + `background` | `class-seohc-scan-queue.php:43-59`, `:293-300`; `class-seohc-repository.php:991` | true / false |
| `seo-list-issues` | `issue_type` (enum), `status` (`open`, `new`, `unchanged` of `resolved`), `post_id`, `severity`, `page` ≥1, `per_page` 1–100 | `{ total, page, per_page, items:[ id, post_id, post_title, issue_type, label, severity, details, object_id, first_seen, resolved_at, editable_field ] }` | `:779-818`; `class-seohc-inline-edit.php:36-52` | true / false |
| `seo-list-pages` | `band` (enum), `post_type`, `page`, `per_page` 1–100 | `get_pages()` + `count_pages()` | `:882`, `:923-960` | true / false |
| `seo-get-scan-history` | — | `run_history()` | `:1066-1069` | true / false |
| `seo-get-launch-checks` | — | geeft `seohc_launch_results` terug, of `{time:null, results:[]}`; draait de checks niet | `class-seohc-launch-checks.php:17`, `:152-159` | true / false |
| `seo-get-headings` | `post_id` | D20 + D21 | — | true / false |
| `seo-start-scan` | — | `start()`; bij false `pdk_seo_scan_running` | `class-seohc-scan-queue.php:94-122` | false / false |
| `seo-rescan-post` | `post_id` | `pdk_seo_not_found` als de post niet bestaat, `pdk_seo_not_scannable` als hij niet scanbaar is, anders `scan_single()` | `:214-217`; `class-seohc-scanner.php:22-26` | false / false |
| `seo-update-meta` | `post_id`, `field` (`seo_title` of `meta_description`), `value` (≤500) | `sanitize_text_field()` + `save_meta()` + D18 | D19; `:159`, `:310-327` | false / true |
| `seo-update-image-alt` | `attachment_id`, `value` (≤125), `post_id` (optioneel) | D28 + D18 | D19, D22, D28 | false / true |
| `seo-set-heading-level` | `post_id`, `index` ≥0, `expected_text`, `level` 1–6 | D20 + D21 + D18 | — | false / true |
| `seo-insert-h1` | `post_id`, `text` 1–200 | D20 + D21 + D18 | — | false / true |

Een fout wijzigt nooit iets. De foutcodes zijn:
- `pdk_seo_no_seo_plugin`
- `pdk_seo_builder_content`
- `pdk_seo_rendered_source`
- `pdk_seo_unfiltered_html`
- `pdk_seo_revisions_disabled`
- `pdk_seo_unsupported_block`
- `pdk_seo_heading_mismatch`
- `pdk_seo_h1_exists`
- `pdk_seo_theme_h1`
- `pdk_seo_not_scannable`
- `pdk_seo_not_found`
- `pdk_seo_scan_running`

Weigert de permission callback, dan komt de foutcode uit de Abilities API zelf.

### 7.3 Bestanden en wijzigingen (slice 2)

- `class-pdk-agent-abilities.php`: D15/D16, de callbacks en `site_info()`.
- `class-pdk-settings.php:324`: de omschrijving (D15).
- `class-pdk-seo-health-check.php`: `is_active()` en de kop-helpers `headings()`, `editable_reason()`,
  `set_heading_level()` en `insert_h1()`, als pure functies op strings.
- Kopie:
  - `admin/class-seohc-inline-edit.php`: `public` (D19) en `wp_slash()` (D22);
  - `class-seohc-repository.php`: de `post_id`-clausule (D23).
- Docs: `docs/modules/agent-abilities.md` en `docs/modules/seo-health-check.md`.
- Tests: zie §3.

### 7.4 Acceptatiecriteria — slice 2

**Voorwaarden:**
- de modules `seo_health_check` en `agent_abilities` staan aan;
- `function_exists('wp_get_ability')` is waar;
- de seed is opnieuw gedraaid (met de controle op de markering) en `seohc-check.php` is geslaagd.

**Uitvoering:** binnen `wpl --user=1 eval-file tests/live/seohc-abilities.php`, met
`wp_get_ability('pdk-theme-options/<naam>')->execute( $input )`.

**Begrippen:**
- **"Ongewijzigd"** — de md5 van `post_content` is gelijk, de md5 van `serialize( get_post_meta( $id ) )` is gelijk,
  het aantal revisies is gelijk en de issue-rijen zijn gelijk.
- **"Revisie met oude content"** — één van de `wp_get_post_revisions( $id )` heeft een `post_content` die byte-gelijk
  is aan de content van vóór de aanroep.

#### FR-010 — Registratie en toegang (Must) — Source: U6, U8, D15–D17
- **AC-020** (H, `tests/test-agent-abilities.php`). De stub van `wp_register_ability` (`:183-185`) bewaart naast de
  naam ook `$args` (minor 3).
  - Met `is_active()` true:
    - precies 12 namen, met category `pdk-theme-options`, `show_in_rest` en `mcp.public`;
    - `readonly` true voor de 6 lees-abilities en false voor de 6 schrijf-abilities;
    - `destructive` true voor `update-meta`, `update-image-alt`, `set-heading-level` en `insert-h1`.
  - Zonder de klasse, of met `is_active()` false: geen enkele `seo-*`-naam.
- **AC-021** (L): `wp_get_ability('pdk-theme-options/seo-list-issues')` is `null` in deze drie gevallen:
  - `seo_health_check` staat uit;
  - alleen `agent_abilities` staat uit;
  - de losse plugin is actief (minor 15). Kopieer daarvoor de map opnieuw en activeer hem. Deactiveer hem daarna en
    verwijder de map met `rm -r` (D26).

  Met alles weer aan bestaan alle 12 abilities.
- **AC-022** (L): permissies.
  - **Zonder beheerrechten.** Maak een tijdelijke gebruiker `seohc-editor` met de rol editor. Met
    `--user=seohc-editor` geeft elke ability een `WP_Error`. S13, S11, S05 en `seohc_scan_state` blijven ongewijzigd.
    De foutcode komt van de Abilities API en is **niet in de PDK-repo te vinden**. Bij de eerste run wordt de code
    vastgesteld en in de evidence gezet; daarna controleert het script op precies die code (minor 4).
  - **Admin zonder `edit_post`.** Met een `map_meta_cap`-filter die `edit_post` op S13 weigert, geven
    `set-heading-level` en `update-meta` op S13 dezelfde code. S13 blijft ongewijzigd.
  - Ruim de gebruiker daarna op.
- **AC-038** (L, REST, M12).
  - **Route vaststellen.** `CHANGELOG.md:341` noemt alleen de namespace `wp-abilities/v1`; pad en methode zijn **niet
    geverifieerd**. Het script zoekt daarom in `rest_get_server()->get_routes()` de `/run`-route onder
    `wp-abilities/v1` en print die met de methoden. Verwacht: `/wp-abilities/v1/abilities/<naam>/run`.
  - **Als user 1:** `rest_do_request()` voor `seo-get-status` op die route geeft 200, met dezelfde velden als AC-023.
  - **Als `seohc-editor`:** na `wp_set_current_user( <seohc-editor> )` geeft dezelfde aanroep 403.
  - De route en de methode die werkelijk gevonden zijn, gelden en komen in de evidence.
  - Een echte MCP-client valt buiten scope (R-12).

#### FR-011 — Lezen (Must) — Source: U6, D23
- **AC-023** (L) `seo-get-status`:
  - `status`, `processed`, `total`, `started_at` en `finished_at` zijn gelijk aan `get_state()`;
  - `summary` is gelijk aan `summary()`;
  - `provider` is gelijk aan `provider_label()`;
  - `background` volgt `use_action_scheduler()`.
- **AC-024** (L) `seo-list-issues`:
  - `{issue_type:'heading_skip'}` geeft alleen dat type, met S13;
  - `{post_id:S03}` geeft alleen S03, met `title_duplicate`;
  - `{per_page:2, page:2}` geeft ≤2 resultaten, en `total` is gelijk aan `count_issues()`;
  - `{per_page:500}` en `{issue_type:'bestaat_niet'}` geven een schemafout;
  - `image_missing_alt` heeft `editable_field 'alt'`, `h1_missing` heeft `null`;
  - na de eerste scan bevat `{status:'new'}` de open S-issues;
  - na een tweede scan zonder wijzigingen staan ze onder `unchanged` en niet meer onder `new`;
  - na AC-032 bevat `{status:'resolved', post_id:S13}` het issue `heading_skip`.
- **AC-025** (L) `seo-list-pages`:
  - S00 heeft score 100;
  - `{band:<band van S14>}` bevat S14;
  - paginering werkt zoals bij AC-024.
- **AC-026** (L):
  - `seo-get-scan-history` geeft evenveel items als `run_history()`, en minstens 1.
  - `seo-get-launch-checks`:
    - na `delete_option('seohc_launch_results')`: `{time:null, results:[]}`;
    - na `update_option('seohc_launch_results',['time'=>time(),'results'=>SEOHC_Launch_Checks::run()])`: 27
      resultaten, elk met `id`, `phase`, `label`, `status` en `message` (`class-seohc-launch-checks.php:93-121`).

#### FR-012 — Scans (Must) — Source: U6
- **AC-027** (L) `seo-start-scan`:
  - bij status `idle` of `done`: succes, en de status wordt `running`;
  - een tweede keer: `pdk_seo_scan_running`;
  - daarna loopt de scan door tot `done`.
- **AC-028** (L), met `rescan_on_save = 0`:
  - vul S14 aan tot 350 woorden met `wp_update_post( wp_slash( … ) )`;
  - `seo-rescan-post {post_id:S14}` geeft een antwoord zonder `thin_content`, en de rij heeft `resolved_at`;
  - op een concept en op A01: `pdk_seo_not_scannable`, en de bestaande rijen blijven staan;
  - op een niet-bestaand ID: `pdk_seo_not_found`.

#### FR-013 — SEO-titel, beschrijving en alt-tekst (Must) — Source: U6, D18, D19, D22, D28
- **AC-029** (L) `seo-update-meta`:
  - **Provider `none`:** `{post_id:S05, field:'meta_description', value:'…'}` geeft `pdk_seo_no_seo_plugin`. De melding
    noemt Yoast SEO en Rank Math, en S05 blijft ongewijzigd.
  - **Met Yoast of Rank Math:**
    - de waarde staat in `_yoast_wpseo_metadesc` of `rank_math_description`;
    - `<b>Tekst</b>` wordt opgeslagen als `Tekst`;
    - het antwoord bevat geen `description_missing` voor S05;
    - `value:''` verwijdert de meta;
    - `field:'seo_title'` op S02 werkt hetzelfde.
  - Heeft dev.local geen provider, installeer dan tijdelijk Yoast met `wpl plugin install wordpress-seo --activate`
    (vraagt internet) en verwijder het daarna weer. Lukt dat niet, test dan alleen de tak `none` (R-09).
- **AC-030** (L) `seo-update-image-alt`:
  - `{attachment_id:A01, value:'Klein <b>voorbeeld</b>', post_id:S09}`:
    - `_wp_attachment_image_alt` wordt `'Klein voorbeeld'` (M6);
    - de `<img>` in S09 krijgt `alt="Klein voorbeeld"`;
    - de markering blijft byte-gelijk (D22);
    - er is een revisie met de oude content;
    - het antwoord bevat geen `image_missing_alt` en geen `content_skipped`.
  - Met `post_id` = de Oxygen 6-pagina (D28, M7):
    - de mediabibliotheek is bijgewerkt;
    - `content_skipped:'builder_content'`;
    - de Oxygen-pagina blijft ongewijzigd.
  - `attachment_id = S09` (geen bijlage) geeft `pdk_seo_not_found`, en de post blijft ongewijzigd.

#### FR-014 — Koppen in de content (Must) — Source: U6, D20, D21
- **AC-031** (L) `seo-get-headings`:

  | Post / situatie | Verwacht |
  |-----------------|----------|
  | S13 | `[{0,1,'Intro'},{1,2,'Deel'},{2,4,'Detail'}]`, `block` false, `editable` true |
  | S18 | drie koppen; de koppen in `core/heading` zijn `editable`; `In html-blok` geeft `editable` false en `reason:'unsupported_block'` (M9) |
  | S17 en de Oxygen 6-pagina | `editable` false, `reason:'builder_content'` |
  | `content_source = rendered` | S13 geeft `reason:'rendered_source'` (instelling daarna terugzetten) |
- **AC-032** (L) `seo-set-heading-level`:
  - **Klassiek:** `{post_id:S13, index:2, expected_text:'Detail', level:3}`:
    - de diff tussen oude en nieuwe `post_content` bestaat uit precies twee wijzigingen: `<h4>` wordt `<h3>` en
      `</h4>` wordt `</h3>`;
    - er is een revisie met de oude content;
    - het antwoord bevat geen `heading_skip`.
  - **Blok:** `{post_id:S18, index:1, expected_text:'Blok detail', level:2}`:
    - `parse_blocks()` geeft `(attrs['level'] ?? 2) === 2`;
    - `innerHTML` bevat `<h2 class="wp-block-heading">`;
    - de markering blijft byte-gelijk.
  - **Dubbele H1:** `{post_id:S12, index:1, …, level:2}` lost `h1_multiple` op.
  - **Weigeren.** De post blijft telkens ongewijzigd:
    - verkeerde `expected_text`: `pdk_seo_heading_mismatch`;
    - `index` buiten bereik: `pdk_seo_not_found`;
    - `level` 7: schemafout;
    - S18, index 2 (in `core/html`): `pdk_seo_unsupported_block`;
    - een concept: `pdk_seo_not_scannable` (M8);
    - een niet-bestaande post: `pdk_seo_not_found`;
    - met een `map_meta_cap`-filter die `unfiltered_html` weigert: `pdk_seo_unfiltered_html` (M5);
    - met `add_filter('wp_revisions_to_keep', fn() => 0)`: `pdk_seo_revisions_disabled` (M4).
- **AC-033** (L) `seo-insert-h1`, in deze volgorde:
  1. Zet `theme_outputs_h1 = 1`. Op S11 geeft dat `pdk_seo_theme_h1`, en S11 blijft ongewijzigd. Zet de instelling
     daarna terug op 0.
  2. **S11** (klassiek), `text:'Nieuwe <b>kop</b>'`:
     - `post_content` begint met `<h1>Nieuwe kop</h1>` + `"\n"`;
     - er is een revisie met de oude content;
     - `h1_missing` is opgelost.
  3. **S18b** (blokken), `text:'Blok kop'`:
     - het eerste blok van `parse_blocks()` is `core/heading`, met `attrs['level'] === 1`;
     - `innerHTML` bevat `<h1 class="wp-block-heading">Blok kop</h1>`;
     - `post_content` begint met de markup uit D21;
     - de markering blijft byte-gelijk;
     - er is een revisie met de oude content;
     - `h1_missing` is opgelost.
  4. **S00** (heeft al een H1): `pdk_seo_h1_exists`.
- **AC-034** (L): builder-weigering. `seo-set-heading-level` en `seo-insert-h1` op S17 en op de Oxygen 6-pagina geven
  `pdk_seo_builder_content`, met een melding die de builder of de editor noemt. Beide posts blijven ongewijzigd, ook
  hun builder-meta.
- **AC-035** (H, `tests/test-seo-health-check.php`): kop-helpers op string-fixtures:
  - `headings()` slaat commentaar over;
  - normalisatie: `<h2>A &amp; <em>b</em> </h2>` geeft `'A & b'`;
  - `set_heading_level()`:
    - werkt op klassieke HTML en op `core/heading`;
    - level 2 verwijdert het attribuut, level 3 zet het;
  - een kop in `core/html`, of in `core/group` > `core/html`, geeft `unsupported_block`;
  - de rest blijft byte-gelijk, inclusief de markering (opgebouwd met `chr(92)`);
  - een mismatch of een ongeldige index geeft een `WP_Error`;
  - `insert_h1()` geeft met en zonder blokken exact de markup uit D21;
  - bij `'Nieuwe <b>kop</b>'` wordt de HTML eerst gestript door `sanitize_text_field()` en daarna geëscaped met `esc_html()`;
  - `editable_reason()` geeft een reden bij lege content en bij elke buildersleutel uit D20(c).

#### FR-015 — Documentatie slice 2 (Must) — Source: D22, D24, U9
- **AC-036** (H, `tests/test-seo-health-check.php`):
  - `docs/modules/agent-abilities.md` noemt de 12 abilities met hun inputs;
  - `docs/modules/seo-health-check.md` noemt de weigerregels uit D20 en D28;
  - de CHANGELOG-kop uit D24 noemt de SEO-abilities en de slash-fix (D22, U9);
  - `class-pdk-settings.php:324` noemt de SEO-abilities;
  - `php tests/run.php` eindigt met "0 mislukt".

AC-037 en AC-038 zijn in deze herstelronde toegevoegd. Bestaande ID's zijn niet hernummerd.

---

## 8. Niet-functioneel

- **NFR-01** — Platte PHP, geen composer en geen build. PHP 8.0 of hoger.
- **NFR-02** — Geen nieuwe of gewijzigde SEOHC-strings. Nieuwe PDK-teksten zijn Nederlands, in textdomain `pdk-theme-options`.
- **NFR-03** — De bestaande nonces en capability-checks blijven (`class-seohc-admin.php:301-305`, `:364-367`). Daarbij
  komen `manage_options` en, voor schrijfacties, `edit_post` en `unfiltered_html` (D17, D20).
- **NFR-04** — Na alle L-checks is Oxygen 6.2-rc.1 nog actief.
- **NFR-05** — Builder-meta wordt nooit aangeraakt. Van elke content-wijziging bestaat een revisie met de oude content.

---

## 9. Use cases (één menselijke rol)

**UC-01 | Actor: ROLE-001 | Site scannen en issues afhandelen**
- Hoofdstroom:
  1. De beheerder opent Issues en start een scan.
  2. ROLE-002 scant op de achtergrond.
  3. De beheerder filtert, sorteert, pagineert en exporteert, en blijft daarbij op de sub-tab.
- A1: cron werkt niet. De AJAX-poll neemt het over (`class-seohc-admin.php:369-372`).
- A2: Ctrl+S start geen scan (D27).
- E1: de losse plugin is actief. De pagina toont een melding (D2).
- Covers: AC-005, AC-008, AC-009, AC-010, AC-011, AC-013, AC-037

**UC-02 | Actor: ROLE-001 | Automatisch scannen, launch checks en instellingen**
- Hoofdstroom: de beheerder slaat iets op of voert een actie uit, en komt terug op dezelfde sub-tab met een melding.
- Covers: AC-007, AC-009, AC-010

**UC-03 | Actor: ROLE-001 | Overstappen van de losse plugin**
- Hoofdstroom:
  1. De beheerder zet de module aan. De pagina toont een melding.
  2. Hij deactiveert de losse plugin.
  3. Hij verwijdert de map via FTP of met `rm -r`, **niet** via Plugins → Verwijderen (D26).
  4. De module werkt verder met dezelfde data.
- E1 (stap 3): hij klikt toch op Verwijderen. De uninstall van de losse plugin wist de gedeelde data (R-11).
- Covers: AC-012

**UC-04 | Actor: ROLE-001 | Module of plugin weghalen**
- Hoofdstroom:
  - module uit: niets meer geladen;
  - PDK gedeactiveerd: het werk stopt;
  - PDK verwijderd: de data is weg, behalve als de losse plugin nog actief is.
- Covers: AC-002, AC-014, AC-016

**UC-05 | Actor: ROLE-003 | Issues laten oplossen door een AI-agent**
- Eigenaar is ROLE-001; de agent logt in als een beheerder.
- Hoofdstroom:
  1. De agent haalt de status op en start zo nodig een scan.
  2. Hij vraagt de issues op.
  3. Per issue past hij koppen, meta of de alt-tekst aan.
  4. Elk antwoord bevat de issues na de rescan.
- A1: builderpagina. Content-writes worden geweigerd; bij een alt-tekst wordt alleen de media bijgewerkt.
- E1: geen SEO-plugin. Fout `pdk_seo_no_seo_plugin`.
- E2: de kop is intussen gewijzigd. Fout `pdk_seo_heading_mismatch`.
- E3: geen rechten. De Abilities API geeft een fout; via REST is dat 403.
- Covers: AC-022, AC-023, AC-024, AC-025, AC-026, AC-027, AC-028, AC-029, AC-030, AC-031, AC-032, AC-033, AC-034, AC-038

**Vrijgesteld** (infra en release): AC-001, AC-003, AC-004, AC-006, AC-015, AC-017, AC-018, AC-019, AC-020, AC-021,
AC-035, AC-036.

**Permissiematrix:** n.v.t., want er is één menselijke rol (§4).

**Indeling per slice.**
- **Slice 1:** AC-001 t/m AC-019 en AC-037.
- **Slice 2:** AC-020 t/m AC-036 en AC-038.

---

## 10. Open vragen

| ID | Vraag | Status |
|----|-------|--------|
| OQ-01 | Data laten staan bij een actieve losse plugin? | **Gesloten: ja** (U7). D11. |
| OQ-02 | Slash-bug herstellen in de kopie? | **Gesloten: ja, met vermelding in de CHANGELOG** (U9). D22. |

Er staan geen vragen meer open.

---

## 11. Scope-alternatieven

| Alternatief | Oordeel |
|-------------|---------|
| A — Klassen ongewijzigd laten en de oude URL's doorsturen | Afgewezen. U1 wil geen eigen menu, en de oude URL's blijven dan in mails en forms staan. |
| B — Kopie met één URL-helper; de namen blijven gelijk | **Gekozen.** Kleinste diff, de data is overdraagbaar en het volgt het IMGX-precedent. Kost: met de hand gelijk houden met de bron (R-04). |
| C — Hernoemen naar `pdk_seohc_*` | Afgewezen. De historie gaat verloren en de Settings API moet herschreven worden (`class-pdk-imgx.php:9-12`). |
| S2-A — Eén generieke content-write | Afgewezen. Er komt vrije HTML binnen, blokken kunnen ongeldig worden en er wordt veel tegelijk overschreven. |
| S2-B — Gerichte abilities met een lock (D21) | **Gekozen.** Kleine diffs, altijd een revisie. Kost: koptekst wijzigen en builder-content bewerken kan niet. |
| S2-C — Builderdata bewerken | Afgewezen (U6). |

---

## 12. Negatieve scenario's

| Scenario | Gedrag | AC |
|----------|--------|----|
| De losse plugin is actief en de module staat aan | De module doet niets en toont een melding. Geen fatal error, geen abilities. | AC-012, AC-021 |
| De losse plugin wordt geactiveerd terwijl de module draait | Fout in de activatie-sandbox. De plugin blijft inactief; er gaat geen data verloren (R-01). | — |
| **De losse plugin wordt verwijderd via Plugins → Verwijderen** | Zijn `uninstall.php` wist de gedeelde data. Waarschuwingen staan in de CHANGELOG, de docs, de melding (D2) en UC-03 (D26, R-11). | AC-012 stap 4, AC-017, AC-018 |
| Er gaat een rapportmail uit vanuit cron | Geen fatal error meer (D25). | AC-010 |
| Ctrl+S op Issues of Launch checks | Er start geen scan en er draaien geen checks (D27). | AC-037 |
| Er is geen SEO-plugin | De scan werkt via de dev-hulp. `update-meta` weigert. | AC-005, AC-029 |
| Er is geen Action Scheduler of cron | Terugval op WP-Cron; de driver draait de batches zelf. | AC-005 |
| Rechten, `edit_post` of `unfiltered_html` ontbreken | `WP_Error` of 403; er verandert niets. | AC-022, AC-032, AC-038 |
| Builderpagina, lege content, of een kop buiten `core/heading` | Weigeren. Bij een alt-tekst wordt alleen de media bijgewerkt. | AC-030, AC-031, AC-032, AC-034 |
| Revisies staan uit | `pdk_seo_revisions_disabled` | AC-032 |
| De kop is gewijzigd sinds het lezen | `pdk_seo_heading_mismatch` | AC-032 |
| Er loopt al een scan | `pdk_seo_scan_running` | AC-027 |
| De post is niet scanbaar of bestaat niet | `pdk_seo_not_scannable` of `pdk_seo_not_found` | AC-028, AC-032 |
| De module gaat uit terwijl er een scan geboekt staat | Losse events vuren één keer zonder dat er iets op reageert (R-02). PDK deactiveren meldt ze af. | AC-016 |
| De tabellen zijn handmatig gewist | Ze worden opnieuw aangemaakt. | AC-004 |

---

## 13. Risico's

| ID | Risico | Mitigatie / acceptatie |
|----|--------|------------------------|
| R-01 | Een activatiefout als de losse plugin wordt geactiveerd terwijl de module aan staat. | Geaccepteerd. De volgorde staat in het doc. |
| R-02 | Staat de module uit, dan blijven geboekte losse events staan tot ze vuren. `ensure_booked()` draait dan niet. | Geaccepteerd; ze vuren één keer. PDK deactiveren meldt ze altijd af (D12, AC-016). |
| R-03 | Wie een lagere `seo_health_check_capability` heeft ingesteld, verliest de toegang. | **Geaccepteerd door de gebruiker (U8).** Staat in de CHANGELOG (AC-017). |
| R-04 | De kopie loopt achter op de bron. | §2, §3 en §7.3, en AC-019. |
| R-05 | `uninstall.php` draait niet in MU-modus. | AC-014 test het modulebestand direct. |
| R-06 | Twee `<h1>`'s en verspringende notices. | Cosmetisch; geaccepteerd. |
| R-07 | De bron wist `seohc_pages_per_page` niet. | Bewust 1-op-1 overgenomen (U3). |
| R-08 | Koppen worden uit de raw content gelezen; koppen uit shortcodes of dynamische blokken ontbreken. | Geaccepteerd. Het antwoord toont wat na de rescan overblijft (D18). |
| R-09 | Zonder provider en zonder internet wordt alleen de weigertak van `update-meta` live getest. | Het opslagpad is ongewijzigde broncode (`:310-327`). Restrisico komt in de evidence. |
| R-10 | De Oxygen 6-sleutel is niet geverifieerd. | Wordt op dev.local vastgesteld (D20c). AC-034 gebruikt een echte Oxygen 6-pagina. D20(a) vangt lege content af. |
| R-11 | Een beheerder verwijdert de losse plugin via het Plugins-scherm en wist zo de data. | Waarschuwingen (D26). Geen technische blokkade, want het is de uninstall van de bron. Herstel kan alleen via een back-up. |
| R-12 | Geen check met een echte MCP-client; de proxy van local-dev-wp is onbereikbaar. | Geaccepteerd. Gedekt via `execute()` en de REST-route (AC-038). |
| R-13 | De route en de permissiefoutcode van de Abilities API zijn niet in de repo geverifieerd. | Bij de eerste run vaststellen en in de evidence zetten (AC-022, AC-038). |

---

## 14. Won't Have

- Een eigen menu, redirects vanaf de oude slugs of Plugins-links.
- De updater van de bron.
- Hernoemde namen.
- Wijzigingen aan SEOHC-strings.
- Toegang voor rollen zonder `manage_options`.
- Wijzigingen aan checks of scores, op de fixes D22 en D25 na.
- Wijzigingen aan `pdk-mu-installer/`.
- Voor slice 2:
  - builderdata bewerken;
  - koptekst wijzigen of koppen verwijderen;
  - vrije content wegschrijven;
  - thin content of links oplossen;
  - launch checks of instellingen via MCP;
  - CSV-export via MCP;
  - een echte MCP-client.

---

## 15. Gereed wanneer

**Slice 1**
- `php tests/run.php` geeft "0 mislukt", voor AC-001, AC-015, AC-016 (H), AC-017 t/m AC-019 en AC-037 (H).
- Op dev.local zijn afgevinkt: AC-002 t/m AC-014, AC-016 (L) en AC-037 (L). Dit gebeurt met de seed uit §5, en
  Oxygen blijft daarbij actief.
- In de tab vindt een scan van de dummy-inhoud alle 15 issuetypes.

**Slice 2**
- `php tests/run.php` geeft "0 mislukt" voor AC-020, AC-035 en AC-036.
- `seohc-abilities.php` eindigt met exit 0 voor AC-021 t/m AC-034 en AC-038.
- In de evidence staan: de provider, het ID van de Oxygen 6-pagina en de builder-sleutel, de REST-route en de
  permissiefoutcode.
- Voor AC-029 met Yoast is internet nodig; anders geldt R-09.

**Externe afhankelijkheden:** geen. Alles draait tegen WordPress core, de Abilities API (WP 6.9+), de lokale database
en de uploads. Yoast is optioneel en wordt alleen tijdelijk geïnstalleerd.
