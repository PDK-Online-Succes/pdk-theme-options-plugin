# SEO Health Check — on-page SEO-scan

De module is de losse plugin **SEO Health Check 0.10.1** (bron: `../seo-health-check/`), overgenomen in
`modules/seo-health-check/` met dezelfde klassen (`SEOHC_*`), tabellen, opties en text domain
`seo-health-check`. Het gedrag staat in de README van de bron en is niet gewijzigd. Deze pagina beschrijft wat
verandert doordat het een PDK-module is. Normatief: `docs/seo-health-check-brief.md`.

## Aan- en uitzetten

Modulesleutel `seo_health_check`, standaard **uit** (`includes/class-pdk-settings.php`). Geladen via `$module_map`
(`includes/class-pdk-plugin.php`); de lijm is `modules/seo-health-check/class-pdk-seo-health-check.php`. Alleen
voor beheerders (`manage_options`). De filter `seo_health_check_capability` blijft gelden voor de handlers, maar
geeft geen toegang tot de tab aan lagere rollen.

Er is geen activatiehook: de tabellen worden bij elke load aangemaakt of bijgewerkt (`seohc_db_version` = `4`).
Staat de module uit, dan wordt er niets geladen en is er niets aangemaakt.

## De tab "SEO Health"

Eén PDK-tab met zes sub-tabs, gekozen met `?page=pdk-theme-options&tab=seo_health_check&seohc_view=<view>`:

| `seohc_view` | Inhoud |
| --- | --- |
| `issues` (standaard) | Samenvatting, scanpaneel, issuelijst met filters en CSV-export |
| `pages` | Paginascores |
| `scans` | Scangeschiedenis |
| `schedule` | Automatisch scannen en rapportmail |
| `launch` | Launch checks |
| `settings` | Instellingen |

Een onbekende waarde valt terug op `issues`. Er is geen eigen top-level menu meer. Elke URL van SEOHC (redirects na een
actie, formulieren, lijsttabellen, dashboardwidget, link in de rapportmail) gaat via
`PDK_SEO_Health_Check::tab_url( $view, $args )`. Die helper werkt ook in cron.

Assets (`shc-admin`) laden alleen op deze tab; op het dashboard (`index.php`) alleen de css voor de widget. De
"items per pagina"-schermoptie hoort bij `issues` en `pages`.

Ctrl+S start hier nooit een scan of de launch checks: `assets/js/admin-save.js` doet niets als de gevonden knop in
`.shc-panel` staat. Op Settings en Automatic scan slaat Ctrl+S gewoon op.

## Overstappen van de losse plugin

De data is gedeeld (zelfde tabellen en opties), dus niets hoeft overgezet te worden.

1. Zet de module aan. Staat de losse plugin nog actief, dan doet de module niets (guard op de constante
   `SEOHC_VERSION`, gecontroleerd op `plugins_loaded`) en toont de tab een melding.
2. Deactiveer de losse plugin. De module neemt het over met dezelfde data.
3. Haal de map weg via FTP of de shell: `rm -r wp-content/plugins/seo-health-check`.

**Verwijder de losse plugin niet via Plugins → Verwijderen.** Zijn `uninstall.php` wist de gedeelde tabellen en
opties, en dus ook de data van de module. Herstel kan dan alleen via een back-up.

## Wat vervalt ten opzichte van 0.10.1

De updater (`class-seohc-updater.php`), de Plugins-links, het eigen menu, `composer.json`, `phpcs.xml.dist`,
`readme.txt` en de projectlink in "About this plugin". Vertalingen (`languages/seo-health-check-nl_NL.po/.mo`,
byte-gelijk aan de bron) laden met `load_textdomain()` via het absolute pad uit de modulemap, omdat
`load_plugin_textdomain()` in MU-modus niet werkt.

## Deactiveren en verwijderen

- **PDK deactiveren** (`PDK_Plugin::deactivate()`): de geplande hooks `seohc_process_batch`,
  `seohc_scan_single_post` en `seohc_scheduled_scan` worden afgemeld (ook met Action Scheduler). Is de module
  geladen, dan krijgt een lopende scan de status `cancelled` en verdwijnt de lock. Is de losse plugin actief, dan
  gebeurt er niets.
- **PDK verwijderen** (`uninstall.php` laadt `modules/seo-health-check/uninstall.php`): wist de drie tabellen, de
  opties (`seohc_settings`, `seohc_db_version`, `seohc_scan_state`, `seohc_scan_lock`, `seohc_launch_results`,
  `seohc_link_cache_generation`, `seohc_scan_history`, `seohc_schedule`, `seohc_report_state`), de transients
  `seohc_link_*`, `uploads/seo-health-check/`, de events en de usermeta `seohc_issues_per_page`. Op multisite voor
  alle sites. Is de losse plugin actief (elk pad dat eindigt op `/seo-health-check.php`, netwerkbreed of op de
  site), dan wordt niets gewist. `pdk_theme_options` en `imgx_settings` blijven door deze stap ongemoeid.

## MCP-abilities

Met de modules SEO Health Check en AI-agent toegang (`agent_abilities`) aan, én de module zelf geboot (niet bij
een actieve losse plugin), registreert `PDK_Agent_Abilities::register_seo_health_abilities()` 12 abilities
`pdk-theme-options/seo-*` (inputs: `docs/modules/agent-abilities.md`). Alles vereist `manage_options`; schrijven ook
`edit_post`. Na elke schrijfactie scant de module de post opnieuw.

**Weigerregels voor content-writes** (`seo-set-heading-level`, `seo-insert-h1` en het content-deel van
`seo-update-image-alt`; `PDK_SEO_Health_Check::editable_reason()`), in deze volgorde. Een fout wijzigt nooit iets:

1. post bestaat niet: `pdk_seo_not_found`; post niet scanbaar (niet gepubliceerd of posttype niet gescand): `pdk_seo_not_scannable`
2. `post_content` is leeg (de resolver scant dan de gerenderde pagina), of er staat builder-meta op de post:
   `pdk_seo_builder_content`. Sleutels: `ct_builder_shortcodes`, `ct_builder_json` (Oxygen Classic), `_oxygen_data`
   (Oxygen 6, vastgesteld op dev.local), `_breakdance_data`, `_elementor_edit_mode`, `_bricks_page_content_2`,
   `_fl_builder_enabled`, `_et_pb_use_builder` = `on` (Divi). Builder-meta wordt alleen op bestaan gecontroleerd
3. instelling Content source is `rendered`: `pdk_seo_rendered_source`
4. gebruiker mist `unfiltered_html` (kses zou de hele content filteren): `pdk_seo_unfiltered_html`
5. revisies staan uit (`wp_revisions_enabled()`): `pdk_seo_revisions_disabled`
6. de kop staat in een ander blok dan `core/heading` (ook `core/html`): `pdk_seo_unsupported_block`

Daarna, per ability: `pdk_seo_heading_mismatch` (de tekst op die index wijkt af van `expected_text`),
`pdk_seo_theme_h1` (instelling "theme outputs H1" aan) en `pdk_seo_h1_exists`. Vóór het schrijven komt er een
revisie met de oude content als er nog geen is; opslaan gaat via `wp_update_post( wp_slash( … ) )`.

**Alt-tekst op builderpagina's (D28).** De mediabibliotheek wordt altijd bijgewerkt; het content-deel wordt
overgeslagen als regel 1 t/m 5 faalt voor `post_id`. Het antwoord bevat dan `content_skipped` met `not_scannable`,
`builder_content`, `rendered_source`, `unfiltered_html` of `revisions_disabled`. De tekst gaat door
`sanitize_text_field()`. Meta-writes (`seo-update-meta`) kennen geen builder-weigering; zonder Yoast of Rank Math
geeft `seo-update-meta` `pdk_seo_no_seo_plugin`.

Koppen worden uit de ruwe `post_content` gelezen; koppen uit shortcodes of dynamische blokken ontbreken (R-08).
Alleen het niveau van een kop is te wijzigen (de tags en bij `core/heading` het attribuut `level`, bij level 2
weggelaten); de rest van de content blijft byte-gelijk. `seo-insert-h1` voegt vooraan een H1 toe, als heading-blok
bij blokcontent.

## Fix ten opzichte van 0.10.1

De link in de rapportmail gebruikte `SEOHC_Admin::MENU_SLUG`, maar `SEOHC_Admin` laadt alleen in de admin; een mail
vanuit cron kon een fatal geven. Nu `tab_url()`.

`SEOHC_Inline_Edit::update_alt_in_content()` riep `wp_update_post()` aan zonder `wp_slash()`, waardoor backslashes
in de post_content verdwenen bij het opslaan van een alt-tekst (D22/U9). Nu met `wp_slash()`. Verder zijn
`save_meta()` en `save_alt()` `public` (hergebruik door de abilities) en kent `SEOHC_Repository::where()` een
`post_id`-filter.

## Testen op dev.local

De scripts staan in `tests/live/` (`seohc-dummy-mu.php` als mu-plugin `seohc-dummy.php`, `seohc-seed.php`,
`seohc-check.php`, `seohc-admin.php`; zie de brief, §5 en §6). Kopieer eerst `pdk-theme-options/` naar
`wp-content/mu-plugins/pdk-theme-options/`; draai met WP-CLI vanuit `app/public`. De unit-checks draaien met
`php tests/run.php` (`tests/test-seo-health-check.php`).
