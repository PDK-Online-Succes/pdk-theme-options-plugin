# Thin Delta Brief — module CSS Framework (`css_framework`)

Status: concept. OQ-CSS-01 t/m OQ-CSS-04 beantwoord door de gebruiker (2026-11-09) en verwerkt; doc-review
herstelronde 1 (B1, M1–M4, minors 1–13, drie lean cuts) verwerkt; gebruikersbesluiten V7 (Modern Normalize + flow),
V8 (breedtes), V9 (ruimte zonder utilityklassen bevestigd), V10 (de section is het grid), V11 (logische namen,
RTL/LTR), V12 (basis-font-size op de section), V13 (line-height met ratio) en V14 (`grid_min` in ch, veldbeschrijvingen
uit één bron; alle 2026-11-10) verwerkt, plus de live-bevindingen D-L1–D-L3 uit Slice 1 en de implementatiekeuze D-P2
(regenereren bij een nieuwe pluginversie).
Scope: nieuwe module in PDK Theme Options (huidige versie 2.12.4 —
`pdk-theme-options/pdk-theme-options-plugin.php:6`, `:24`; wordt **2.13.0**, minor volgens `CLAUDE.md`).
Geen volledige PRD: alleen wat erbij komt. Geen CI, geen release in deze opdracht.

**Verzoek (letterlijk):** *"Hypermodern CSS framework ontwikkelen voor eigen gebruik met, MCP, etc. gebruik
hiervoor CSS baseline. Ik wil het volgende makkelijk kunnen instellen. fluid Font-size met clamp en cgi; fluid
line-height op basis van de font-size; voor headings en P; Fluid margin/paddings; section > div max-width met een
fullwidth overwrite. of een grid template area met bijvoorbeeld breakout naar links en rechts. Automatische grid
responsive fallback, zonder magic numbering. Zoals utopia.fyi. Gebruik dev.local als test omgeving."*

**Antwoorden gebruiker (normatief):**
- V1 — Vorm: een nieuwe module (standaard uit) met een eigen admin-tab. De plugin genereert een CSS-bestand met
  custom properties en layout-/utilityklassen, **plus** MCP-abilities (bestaand Abilities API-patroon) om de config
  te lezen/schrijven en de tokens op te vragen.
- V2 — Fluid eenheid: **`cqi`** ("cgi" was een typo). Zonder container-voorouder valt `cqi` al terug op de small
  viewport, dus alleen `cqi`, geen aparte `vi`-terugvallaag.
- V3 — Baseline: **Newly available** is de ondergrens.
- V4 — Oxygen: de tokens **synchroniseren naar Oxygen 6-variabelen** (niet alleen de CSS laden).
- V5 — Min/max-viewport voor de fluid-berekening: **de standaard-breakpoints van Oxygen**.
- V6 — Sitebreedte (content max-width): **1440px**. Vorm en eenheid aangepast door V8.
- OQ-CSS-01 (2026-11-09) — Max-viewport = **1440** (de sitebreedte); min blijft **400** (Oxygen phone portrait).
- OQ-CSS-02 (2026-11-09) — De voorgestelde defaults zijn goedgekeurd: 18→20px, ratio 1,2→1,25, 5 omhoog / 2 omlaag,
  leading 0,5 / 0,25rem (`leading_body` door V13 naar 0,25rem), gutter `s-m`, feature-ruimte `2xl` (nu
  `popout_space`, V8), `grid_min` 16rem (door V14 nu 30ch), extra paar `s-l`.
- OQ-CSS-03 (2026-11-09) — **Automatisch, met opt-out:** elke `section` krijgt de layout, met een opt-out-klasse en een
  gedocumenteerde uitweg voor sections van derden. De vorm is door V10 vervangen: de section is zelf het grid.
- OQ-CSS-04 (2026-11-09) — Synchroniseren via de interne functie `\Breakdance\Variables\saveVariables()`, met guards:
  `function_exists`, try/catch, een mislukte sync blokkeert het opslaan nooit, en de admin toont een melding.
- V7 (2026-11-09) — Het framework **gaat ervan uit dat Oxygens optie Modern Normalize aan staat**
  (`modern_normalize_enabled`). De plugin levert geen eigen normalize mee en zet die Oxygen-optie **niet** zelf aan.
  Omdat Modern Normalize elke marge op 0 zet, geeft het framework tekstelementen een fluid **flow-regel** (verticale
  ritmiek uit de ruimte-tokens). Staat Oxygen 6 aan en Modern Normalize uit, dan toont de tab een waarschuwing.
- V8 (2026-11-09) — **Breedtes:**
  - `site_width` in **rem**, default **90** (= 1440px bij een root van 16px). `max_viewport` blijft 1440px en
    onafhankelijk; de defaults vallen samen bij een root van 16px.
  - Nieuwe instelling `measure` in **ch**, default **65**.
  - `feature_space` heet nu **`popout_space`**.
  - Banen volgens het patroon van Ryan Mulligan (https://ryanmulligan.dev/blog/layout-breakouts/, opgehaald
    2026-11-09).
- V9 (2026-11-09) — **D13 bevestigd:** margin en padding lopen via `var(--pdk-space-*)` plus de flow-regel, zonder
  utilityklassen.
- V10 (2026-11-09) — **De section is zelf het grid**, met de banen full / wide / popout / content. Elk direct kind staat
  standaard op `wide` (= sitebreedte, gecentreerd). Vervalt hierdoor: de `section > div`-max-width-regel, de
  `.pdk-full`-override op div/section, `.pdk-breakout` en de box-sizing-kwestie van die regel. `.pdk-container` blijft
  een losse container; `.pdk-off` = een section zonder grid en zonder `container-type`.
- V11 (2026-11-09) — **Logische namen voor RTL/LTR:** de eenzijdige varianten heten `-start` / `-end` in plaats van
  `-left` / `-right`. Gridlijnen volgen `direction` en writing-mode vanzelf, dus `-start` is in RTL de rechterkant. Het
  hele framework gebruikt logische eigenschappen (`margin-block-*`, `padding-inline`, `inline-size`,
  `max-inline-size`), nooit left/right/width waar een logische variant bestaat. "Breakout naar links en rechts" uit het
  verzoek wordt zo "naar de start- en de eindkant".
- V12 (2026-11-10) — **De section krijgt standaard `font-size: var(--pdk-step-0)`** (specificiteit 0). Daardoor wordt
  `ch` in de sporen (de maat) fluid, en krijgt losse tekst in een section de fluid basisgrootte. Elke klasse wint ervan.
  Dit regelt R-CSS-14.
- V13 (2026-11-10) — **Line-height = ratio × font-size + constante.** Live gemeten (Playwright): `calc(1em + leading)`
  gaf een `h1` van 1,07 × font-size (61,04px → 65,04px). De inkt van de letters (ascent + descent ≈ 1,17em) stak dan
  buiten de regelbox, en descenders overlapten de volgende regel. Nieuw: twee ratio-instellingen
  (`lh_heading_ratio` 1,1, `lh_body_ratio` 1,3), `leading_body` gaat van 0,5 naar **0,25rem**, en de basisregels worden
  `calc(var(--pdk-lh-…) * 1em + var(--pdk-leading-…))`.
- V14 (2026-11-10, na handmatige controle door de gebruiker: builder-canvas OK, foutmeldingen duidelijk,
  Modern Normalize-waarschuwing OK):
  - **`grid_min` in ch**, default **30**, bereik 10–80, max. 2 decimalen; token `--pdk-grid-min: 30ch`. In een section
    is de font-size `step-0`, dus de kaartbreedte schaalt mee met de fluid tekst (net als `measure`), en tekstkaarten
    breken op een zinnige tekstbreedte.
  - **Veldbeschrijvingen uit één bron:** elke instelling krijgt een Nederlands label, een eenheid, één zin uitleg met
    een voorbeeld, en een groep. Die tekst staat op één plek in de code en voedt zowel de admin-tab als de
    `description` in het MCP-schema; `get-css-framework` geeft hem terug als `fields`.

---

## 1. Geverifieerde uitgangspositie

### 1a. Plugin (precedenten)

| Punt | Bewijs |
|------|--------|
| Modules laden alleen als ze aan staan; uit = klasse niet geladen | `includes/class-pdk-plugin.php:17-32` (`$module_map`), `:88-99` |
| Defaults + modulelabels/-omschrijvingen | `includes/class-pdk-settings.php:109-255`, `:261-281`, `:283-303` |
| Oxygen-afhankelijke modules | `includes/class-pdk-settings.php:34` (`OXYGEN6_MODULES`), `:46-54`; `includes/helpers.php:213-232` (`pdk_oxygen6_active()`) |
| Tabs per module; deeplink valt terug als module uit staat | `includes/class-pdk-admin.php:26-37` (`MODULE_TABS`), `:687-689`, `:692` (`$standalone_tabs`) |
| Opslaan-handler: `manage_options` + nonce, `switch` op `pdk_tab` | `includes/class-pdk-admin.php:305-359` |
| Meldingen via query-arg: `error` (rawurlencode) bij redirect, getoond door `render_notices()` (alleen `saved` en `error`) | `includes/class-pdk-admin.php:541-544`, `:725-734` |
| Custom CSS: enqueue op `wp_enqueue_scripts` prioriteit 10, handle `pdk-custom-style`, zonder deps | `modules/custom-css/class-pdk-custom-css.php:14`, `:29-34`; `PDK_STORAGE_DIR`/`URL` `pdk-theme-options-plugin.php:32-33` |
| `pdk_write_storage_file()` eist de code-cap en schrijft `.bak` + hash | `includes/helpers.php:75-78`, `:106-116` |
| Abilities: categorie `pdk-theme-options`, Oxygen-abilities alleen als de andere module-klasse bestaat | `modules/agent-abilities/class-pdk-agent-abilities.php:27`, `:118-121`; `get-site-info` op `manage_options` `:109` |
| Uninstall verwijdert `pdk_theme_options` en losse plugin-opties, laat uploads staan | `uninstall.php:5-6`, `:16`, `:20-28` |
| Zelftests: één subproces per `tests/test-*.php`, assertions aan | `tests/run.php:16-29` |

### 1b. Oxygen 6.2-rc.1 (bron: `wp-content/plugins/oxygen/` op dev.local)

| Feit | Bewijs |
|------|--------|
| Standaard-breakpoints (id, previewbreedte, `maxWidth`): `breakpoint_base` Desktop `100%` (geen max); `breakpoint_tablet_landscape` 1024 / 1119; `breakpoint_tablet_portrait` 768 / 1023; `breakpoint_phone_landscape` 480 / 767; `breakpoint_phone_portrait` 400 / 479 | `plugin/config/builtin-breakpoints.php:5-19` |
| Eigen breakpoints komen uit de voorkeuren en worden toegevoegd | `plugin/config/breakpoints.php:10-21` |
| **Normalize** (unlayered): `normalize.min.css` zet `h1,h2,h3,h4,h5,h6{line-height:1.2}` en `h1{font-size:2em;margin:.67em 0}`; met de instelling `modern_normalize_enabled` komt `modern-normalize.min.css` | `plugin/themeless/normalize.min.css:1`; `plugin/themeless/util.php:59-74` |
| **Modern Normalize** (bron `modern-normalize.css`, upstream v3.0.1 plus Oxygen-toevoegingen vanaf `:228`): `html{line-height:1.4}` (`:233-236`), `h1…h6{line-height:1.2}` (`:249-256`), `img{max-inline-size:100%;…}` (`:258-263`), en de reset `*,::after,::before,::backdrop,::file-selector-button{margin:0;padding:0}` op specificiteit 0, **unlayered** (`:265-278`). Een regel in een cascade layer verliest daarvan altijd (live gemeten, D-L1) | `plugin/themeless/modern-normalize.css:228-278` |
| De instelling: `modern_normalize_enabled` (type bool, tab `advanced`); lezen met `\Breakdance\Settings\get_bool_setting()`, die een `InvalidArgumentException` gooit als de definitie geen bool is | `plugin/settings/options.php:50`, `:234-243`; `plugin/themeless/util.php:59-62` |
| Aanzetten gebeurt in Oxygen → Settings → tab **Advanced** → **Modern Normalize** → Enable. Oxygens eigen toelichting waarschuwt dat dit op bestaande sites de weergave verandert | `plugin/admin/settings-page/tabs/advanced.php:16`, `:30`, `:94-108` |
| De normalize-`<link>` wordt **letterlijk vóór `wp_head()`** geprint (themeless/zero-theme), dus vóór elk ge-enqueued stylesheet | `plugin/themeless/util.php:23-26`, `:79-82` |
| Oxygen schakelt het WordPress-thema **altijd** uit en zet zijn eigen thema `oxygen-zero` in de plaats (filters op `template`/`stylesheet` en de `pre_option_*`-varianten; `is_theme_disabled()` geeft in Oxygen-modus altijd `true`). Dat thema heeft geen `theme.json` en geen eigen kopstijlen | `plugin/themeless/theme-disabler/theme-disabler.php:12-13`, `:28-48`, `:59-63`; `plugin/themeless/themes/oxygen-zero/` (geen `theme.json`) |
| Oxygens eigen CSS (globale instellingen, presets, variabelen, selectors, post-CSS) komt via een placeholder op `wp_head` prioriteit **1000000**, dus ná elk ge-enqueued stylesheet | `plugin/render/capture-wordpress-output.php:17`, `:39-64`, `:232-308` |
| Variabelen staan in de globale optie `variables_json_string`; optienaam = `meta_prefix` + veld = **`oxygen_variables_json_string`**; collecties in `oxygen_variables_collections_json_string` | `plugin/variables/variables.php:63`, `:94`; `plugin/data/api/options.php:22-26`, `:46-59`; `plugin/util/bdox.php:87` |
| Ruw lezen zonder filter: `\Breakdance\Data\get_global_option( 'variables_json_string' )` (JSON-gedecodeerd; `false` bij ontbreken **of** decodeerfout); bestaan los te testen met `global_option_exists()`; oude installaties kunnen nog een JSON-string bevatten | `plugin/data/api/options.php:36-39`, `:46-59`; `plugin/data/api/helpers.php:32-46`; `plugin/variables/variables.php:68-72` |
| Vorm per variabele: `{id, cssVariableName, label, value, type, dynamicData, collection}` | `plugin/psalm-types/z-z-misc.php:32` |
| `getVariables()` past de filter `breakdance_variables` → `oxygen_variables` toe (niet geschikt als bron voor schrijven) | `plugin/variables/variables.php:80`; `plugin/breakdance-oxygen/actions-filters.php:21-33` |
| Schrijven: `\Breakdance\Variables\saveVariables( string $json )`. Doet `str_replace("{}", "null", $data)`, dan `json_decode`, en schrijft `$variablesData['variables']` en `['collections']` **zonder controle**. Een kapotte payload wordt `null` en **wist alle variabelen zonder exceptie**. Revisie + `generateCacheForGlobalSettings()` alleen bij verschil. Altijd geladen (niet afhankelijk van Oxygen-MCP) | `plugin/variables/save.php:32-58`; `plugin/variables/base.php:3-5`; `plugin/base.php:125` |
| Uitvoer: `:root { --<cssVariableName>: <value.style of value>; }`, daarna `replaceVariablesInString()` (alleen `{var-<id>}`-tokens) en `formatCss()` = matthiasmullie-minifier, dus de tekst in het bestand is **niet** byte-gelijk aan de opgeslagen waarde | `plugin/variables/template/global.twig:1-5`, `macro.twig:6`, `:29`; `plugin/variables/variables.php:38-53`; `plugin/breakdance-oxygen/selectors.php:249-271`; `plugin/render/format-css.php:12-22`; `plugin/render/cache.php:270-271` |
| `unit`-waarde die de builder kan lezen: `{number, unit: 'custom', style}` voor een vrije CSS-expressie | `plugin/mcp/design/css-utils.php:306-326`; `plugin/mcp/tools/variables.php:342-384` |
| Oxygens eigen insert: overschrijft op `cssVariableName` en behoudt het id; nieuwe id's = `wp_generate_uuid4()`; namen zonder voorloop-`--` | `plugin/mcp/tools/variables.php:446-454`, `:487-490`, `:521-541`, `:566-573` |
| Die ability bestaat alleen als Oxygen-MCP aan staat; rechten = `manage_options` | `plugin/mcp/base.php:7-9`, `:35`; `plugin/mcp/tools/core.php:85-88` |
| Een "section" in Oxygen 6 is een Container met tag `section`. De Container voegt geen wrapper toe en heeft **geen** standaard-CSS of standaard-eigenschappen: flex, gap, marges, padding en typografie bestaan alleen als de gebruiker ze in de builder zet | `subplugins/oxygen-elements/elements/Container/element.php:21-29`, `:66-74`; `html.twig:1`; `default.css` en `css.twig` (allebei leeg) |
| Builder-canvas is een frontend-request met `?breakdance_iframe` | `plugin/util/is-request-from-builder-iframe.php:8-16` |

### 1c. WordPress-core op dev.local (7.1.4, `wp-includes/version.php:19`)

| Feit | Bewijs |
|------|--------|
| theme.json-elementstijlen voor `h1`–`h6` krijgen een **kale elementselector** (0,0,1), geen `:root :where()` | `wp-includes/class-wp-theme-json.php:4132-4142` |
| De `theme.json` van core zelf stijlt van alle elementen **alleen `button`**; er staan geen kopstijlen in | `wp-includes/theme.json:407-435` |
| `wp_enqueue_global_styles` hangt aan `wp_enqueue_scripts` (prioriteit 10, door core geregistreerd vóór elke plugin) en `wp_footer` 1 | `wp-includes/default-filters.php:651-652` |
| Classic themes met on-demand-assets krijgen op `wp_enqueue_scripts` een placeholder-handle `wp-global-styles-placeholder`; de in de footer geprinte global styles worden op die plek in de HEAD teruggezet | `wp-includes/script-loader.php:2559-2598` |
| **Live gemeten op dev.local (2026-11-10):** **zonder** Oxygen staan de global styles in de head, vóór `css-framework.css`. **Met** Oxygen actief print WordPress `global-styles-inline-css` in de **footer**, ná `css-framework.css` en `custom-style.css` | meting coördinator |

**Waarom die footer-plek met Oxygen onschadelijk is:** Oxygen vervangt altijd het thema door `oxygen-zero`, dat geen
`theme.json` heeft (§1b). De global styles komen dan alleen uit core's `theme.json`, en die stijlt alleen `button`
(`theme.json:407-435`). Er staan dus geen kop- of `p`-regels in die later geprinte global styles, en de basisregels van
het framework worden niet overschreven. Zonder Oxygen staan de global styles vóór het framework, dus daar winnen de
basisregels door de volgorde (§4).

**Conclusie Oxygen-sync:** er is **geen gedocumenteerde publieke PHP-API** voor het schrijven van variabelen.
Gekozen route (OQ-CSS-04): de interne functie `\Breakdance\Variables\saveVariables()`, met de guards uit §5,
inclusief bescherming tegen het stille wissen. Restrisico R-CSS-01. De filter `oxygen_variables` is afgewezen (D12).

### 1d. Utopia-wiskunde (uit eigen kennis van utopia.fyi / utopia-core — **door de tester numeriek te verifiëren** in de calculator op utopia.fyi met 400→1440, 18→20, 1,2→1,25)

Alle maten in px, uitvoer in rem met **1rem = 16px**:

- Typestap *n* (n mag negatief zijn): `minSize = fontMin × ratioMin^n`, `maxSize = fontMax × ratioMax^n`.
- Ruimte-stap (t-shirt): `min = round(fontMin × m)`, `max = round(fontMax × m)` (afronden op hele px), met
  `m` = 3xs 0,25 · 2xs 0,5 · xs 0,75 · s 1 · m 1,5 · l 2 · xl 3 · 2xl 4 · 3xl 6.
- Paar `a-b` (one-up = buren in de reeks, custom = vrij): `min = min(a)`, `max = max(b)`.
- `slope = (maxSize − minSize) / (maxVw − minVw)`; de `cqi`-coëfficiënt is `slope × 100`.
- `intercept = minSize − minVw × slope` (px), uitgevoerd als `intercept / 16` rem.
- Waarde: `clamp( lo/16 rem, intercept/16 rem ± |slope×100| cqi, hi/16 rem )`, met `lo = min(minSize, maxSize)` en
  `hi = max(minSize, maxSize)` (anders is `clamp()` ongeldig bij een dalende stap).
- **Notatie (vast):** afronden op 4 decimalen met PHP `round()` (een half weg van nul), nullen achteraan weg
  (`1.125rem`, niet `1.1250rem`). Een waarde die na afronden nul is, wordt `0`, ook als hij negatief was (nooit
  `-0`). De intercept heeft een eigen teken (`-0.5rem` mag). De helling volgt met de operator `+` of `-` en de
  absolute waarde (`11.0544rem - 10.0459cqi`, nooit `+ -10.0459cqi`); helling nul geeft `+ 0cqi`. Het enige halve
  geval in de tabel: `step--2` min = 0,78125 → `0.7813`.

#### Normatieve testvector — defaults: viewport 400→1440 (Δ = 1040), font 18→20, ratio 1,2→1,25

Typestappen (`--pdk-step-N`):

| N | min px | max px | Δ px | slope×100 (cqi) | intercept px → rem | `clamp()` |
|---|--------|--------|------|-----------------|--------------------|-----------|
| −2 | 12,5 | 12,8 | 0,3 | 0,0288 | 12,3846 → 0.774 | `clamp(0.7813rem, 0.774rem + 0.0288cqi, 0.8rem)` |
| −1 | 15 | 16 | 1 | 0,0962 | 14,6154 → 0.9135 | `clamp(0.9375rem, 0.9135rem + 0.0962cqi, 1rem)` |
| 0 | 18 | 20 | 2 | 0,1923 | 17,2308 → 1.0769 | `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)` |
| 1 | 21,6 | 25 | 3,4 | 0,3269 | 20,2923 → 1.2683 | `clamp(1.35rem, 1.2683rem + 0.3269cqi, 1.5625rem)` |
| 2 | 25,92 | 31,25 | 5,33 | 0,5125 | 23,87 → 1.4919 | `clamp(1.62rem, 1.4919rem + 0.5125cqi, 1.9531rem)` |
| 3 | 31,104 | 39,0625 | 7,9585 | 0,7652 | 28,0430 → 1.7527 | `clamp(1.944rem, 1.7527rem + 0.7652cqi, 2.4414rem)` |
| 4 | 37,3248 | 48,8281 | 11,5033 | 1,1061 | 32,9004 → 2.0563 | `clamp(2.3328rem, 2.0563rem + 1.1061cqi, 3.0518rem)` |
| 5 | 44,7898 | 61,0352 | 16,2454 | 1,5621 | 38,5415 → 2.4088 | `clamp(2.7994rem, 2.4088rem + 1.5621cqi, 3.8147rem)` |

Ruimte-stappen (`--pdk-space-X`):

| X | min px | max px | Δ px | slope×100 (cqi) | intercept px → rem | `clamp()` |
|---|--------|--------|------|-----------------|--------------------|-----------|
| 3xs | 5 | 5 | 0 | 0 | 5 → 0.3125 | `clamp(0.3125rem, 0.3125rem + 0cqi, 0.3125rem)` |
| 2xs | 9 | 10 | 1 | 0,0962 | 8,6154 → 0.5385 | `clamp(0.5625rem, 0.5385rem + 0.0962cqi, 0.625rem)` |
| xs | 14 | 15 | 1 | 0,0962 | 13,6154 → 0.851 | `clamp(0.875rem, 0.851rem + 0.0962cqi, 0.9375rem)` |
| s | 18 | 20 | 2 | 0,1923 | 17,2308 → 1.0769 | `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)` |
| m | 27 | 30 | 3 | 0,2885 | 25,8462 → 1.6154 | `clamp(1.6875rem, 1.6154rem + 0.2885cqi, 1.875rem)` |
| l | 36 | 40 | 4 | 0,3846 | 34,4615 → 2.1538 | `clamp(2.25rem, 2.1538rem + 0.3846cqi, 2.5rem)` |
| xl | 54 | 60 | 6 | 0,5769 | 51,6923 → 3.2308 | `clamp(3.375rem, 3.2308rem + 0.5769cqi, 3.75rem)` |
| 2xl | 72 | 80 | 8 | 0,7692 | 68,9231 → 4.3077 | `clamp(4.5rem, 4.3077rem + 0.7692cqi, 5rem)` |
| 3xl | 108 | 120 | 12 | 1,1538 | 103,3846 → 6.4615 | `clamp(6.75rem, 6.4615rem + 1.1538cqi, 7.5rem)` |

Paren (`--pdk-space-a-b`; 8 one-up + het custom paar `s-l`):

| Paar | min px | max px | Δ px | slope×100 (cqi) | intercept px → rem | `clamp()` |
|------|--------|--------|------|-----------------|--------------------|-----------|
| 3xs-2xs | 5 | 10 | 5 | 0,4808 | 3,0769 → 0.1923 | `clamp(0.3125rem, 0.1923rem + 0.4808cqi, 0.625rem)` |
| 2xs-xs | 9 | 15 | 6 | 0,5769 | 6,6923 → 0.4183 | `clamp(0.5625rem, 0.4183rem + 0.5769cqi, 0.9375rem)` |
| xs-s | 14 | 20 | 6 | 0,5769 | 11,6923 → 0.7308 | `clamp(0.875rem, 0.7308rem + 0.5769cqi, 1.25rem)` |
| s-m | 18 | 30 | 12 | 1,1538 | 13,3846 → 0.8365 | `clamp(1.125rem, 0.8365rem + 1.1538cqi, 1.875rem)` |
| m-l | 27 | 40 | 13 | 1,25 | 22 → 1.375 | `clamp(1.6875rem, 1.375rem + 1.25cqi, 2.5rem)` |
| l-xl | 36 | 60 | 24 | 2,3077 | 26,7692 → 1.6731 | `clamp(2.25rem, 1.6731rem + 2.3077cqi, 3.75rem)` |
| xl-2xl | 54 | 80 | 26 | 2,5 | 44 → 2.75 | `clamp(3.375rem, 2.75rem + 2.5cqi, 5rem)` |
| 2xl-3xl | 72 | 120 | 48 | 4,6154 | 53,5385 → 3.3462 | `clamp(4.5rem, 3.3462rem + 4.6154cqi, 7.5rem)` |
| s-l | 18 | 40 | 22 | 2,1154 | 9,5385 → 0.5962 | `clamp(1.125rem, 0.5962rem + 2.1154cqi, 2.5rem)` |

Rekenvoorbeeld `step-0`: slope = 2 / 1040 = 0,00192308 → ×100 = **0.1923**; intercept = 18 − 400 × 0,00192308 =
17,23077 px → /16 = 1,076923 → **1.0769**; lo = 18/16 = **1.125**, hi = 20/16 = **1.25**.

Dalende stap (notatie-check): met `ratio_min = 1.5`, `ratio_max = 1.1`, verder de defaults, is `step-5` 136,6875 →
32,2102 px; slope = −104,4773 / 1040 = −0,1004589 → ×100 = −10,0459; intercept = 136,6875 + 400 × 0,1004589 =
176,8711 px → 11,0544 rem. Resultaat: **`clamp(2.0131rem, 11.0544rem - 10.0459cqi, 8.543rem)`**.

#### Line-height bij de defaults (V13)

`line-height = ratio × font-size + leading`, met ratio 1,1 (koppen) en 1,3 (`p`) en leading 0,25rem = 4px:

| Element | Venster | font-size | line-height | verhouding |
|---------|---------|-----------|-------------|------------|
| `h1` (`step-5`) | 400 | 44,7904px (2.7994rem) | 1,1 × 44,7904 + 4 = **53,2694px** | ≈ 1,19 |
| `h1` | 1600 | 61,0352px (3.8147rem) | 1,1 × 61,0352 + 4 = **71,1387px** | ≈ 1,17 |
| `h6` (`step-0`) | 400 | 18px | 1,1 × 18 + 4 = **23,8px** | ≈ 1,32 |
| `h6` | 1600 | 20px | 1,1 × 20 + 4 = **26px** | 1,3 |
| `p` (`step-0`) | 400 | 18px | 1,3 × 18 + 4 = **27,4px** | ≈ 1,52 |
| `p` | 1600 | 20px | 1,3 × 20 + 4 = **30px** | 1,5 |

### 1e. Baseline-status van alle gebruikte CSS (V3)

Uit eigen kennis (web-features / MDN). **Niet live gecontroleerd** — de tester controleert vóór Slice 2 elke
regel op webstatus.dev (R-CSS-04). Alle features zitten boven de Newly-ondergrens, dus V3 is ruim gehaald.

| Feature | web-features-id | Status |
|---------|-----------------|--------|
| `calc()` (ook gemengde eenheden zoals `rem − ch`, en een getal × `1em`) | `calc` | Widely available (al jaren) |
| `clamp()`, `min()`, `max()` (ook binnen `minmax()`) | `min-max-clamp` | Widely available (sinds 2020) |
| Containerqueries + `container-type` + `cqi` | `container-queries` | Baseline 2023 (Newly februari 2023), Widely sinds 2025 |
| Terugval van `cqi` zonder container op de small viewport | spec CSS Containment 3, §container-lengths | spec (bevestigt V2) |
| `:where()` / `:not()` met selectorlijst | `where`, `not` | Widely |
| Grid met benoemde lijnen, `minmax()`, `repeat(auto-fit, …)`, `fr`; kolommen volgen `direction` (RTL) | `grid` | Widely |
| Lengte-eenheid `ch` | niet opgezocht (CSS Values 3) | Widely (eigen kennis) |
| Custom properties (ook als track-lijst in `grid-template-columns`) | `custom-properties` | Widely |
| Logische eigenschappen (`inline-size`, `margin-block`) | `logical-properties` | Widely |

Bewust **niet** gebruikt (nergens voor nodig): cascade layers (`@layer`, afgeschaft door D-L1), `round()`,
`@property`, container style queries, subgrid, `text-wrap`, en fysieke eigenschappen (`left`/`right`/`width`/
`margin-top` …) waar een logische variant bestaat (V11). Zo hangt niets af van een feature die maar net Baseline is.

---

## 2. Wat erbij komt, en waar

| Bestand | Wijziging | Source |
|---------|-----------|--------|
| `modules/css-framework/class-pdk-css-framework.php` (nieuw) | Pure generator (config → tokens → CSS-string), validatie, de veldbeschrijvingen als één statische bron `PDK_CSS_Framework::fields()` (§3a), schrijven van het bestand, register/enqueue (§4), Oxygen-sync (pure merge + guarded `saveVariables`, §5), Modern Normalize-check voor de tab-melding (§4a), versiecheck voor regeneratie (D-P2) | V1, V2, V4, V7, V8, V10–V14, OQ-CSS-04, D-P2 |
| `includes/class-pdk-plugin.php:17-32` | `'css_framework' => modules/css-framework/class-pdk-css-framework.php, PDK_CSS_Framework` | V1 |
| `includes/class-pdk-settings.php:109-255`, `:261-303` | Blok `css_framework` (§3), label "CSS Framework", omschrijving. **Niet** in `OXYGEN6_MODULES`: de module werkt ook zonder Oxygen | V1, D2 |
| `includes/class-pdk-admin.php:26-37`, `:740+` (`get_tabs()`), `:787+` (`render_tab_content()`), `:315-352` (`handle_save()`), `:725-734` (`render_notices()`) | Tab "CSS Framework" in `MODULE_TABS`, gewone wrapper-form (niet standalone), velden gegroepeerd en beschreven uit `fields()` (§3a), `case 'css_framework'`: valideren → opslaan → bestand genereren → Oxygen-sync. Validatiefout → redirect met `error` (bestaand patroon `:541-544`); sync-fout → redirect met `saved=1` **plus** nieuw query-arg `warning`, dat `render_notices()` als `notice-warning` toont. De tab zelf toont de Modern Normalize-waarschuwing (§4a) | V1, V7, V14, OQ-CSS-04 |
| `modules/custom-css/class-pdk-custom-css.php:29-34` | Deps van `pdk-custom-style` = `[ 'pdk-css-framework' ]` als `wp_style_is( 'pdk-css-framework', 'registered' )`, anders `[]` (B1: custom-style.css wint altijd van de basisregels) | B1 |
| `modules/agent-abilities/class-pdk-agent-abilities.php:118-121` | Twee abilities, alleen als `class_exists( 'PDK_CSS_Framework' )` (§6); `description` per schema-eigenschap uit `fields()`; één regel in `get-site-info` → `other_output` | V1, V14, D10 |
| `uninstall.php` (na `:28`) | `delete_option( 'pdk_css_framework_built' )` (D-P2). De instellingen zelf staan in `pdk_theme_options`, dat op `:16` al verwijderd wordt; `css-framework.css` blijft in uploads staan, net als de andere klantbestanden (`:5-6`) | D-P2 |
| `tests/test-css-framework.php` (nieuw) | H-checks uit §7 | — |
| `tests/live/css-framework-demo.php` | Demo-pagina; leidt zijn verwachtingen live af uit de tokens (§13). Geen framework-AC | V14 |
| `docs/modules/css-framework.md` (nieuw), `CHANGELOG.md`, `pdk-theme-options-plugin.php:6`, `:24` | Slice 2. De moduledoc bevat de veldtabel (§3a), de banen van de section en hun klassen (met de start/end-betekenis in RTL), de basis-font-size van de section, de line-height-formule met de erfenis-kanttekening, de opt-out, de uitweg-snippet voor sections van derden, de laadvolgorde, de flow-regel en de Modern Normalize-aanname, inclusief het verschuiven van bestaande sites (§4a) | `CLAUDE.md`, OQ-CSS-03, V7, V8, V10–V14 |

---

## 3. Instellingen — `pdk_theme_options['css_framework']`

Alle waarden numeriek of uit een vaste lijst. Er komt **geen vrije tekst** in de CSS (veiligheidsgrens, §6).
Is één veld ongeldig, dan wordt **niets** opgeslagen, krijgt de gebruiker een foutmelding met de veldnaam, en
blijven het oude bestand en de oude Oxygen-variabelen staan.

**Validatieregels (één functie voor admin en MCP):**
- *Float-velden:* eerst `is_numeric()`, dan pas casten naar `(float)`, daarna `is_finite()`, daarna het bereik.
  `'1e999'` (INF), `'NAN'`, `''` en arrays worden geweigerd.
- *`measure`, `grid_min`, `lh_heading_ratio` en `lh_body_ratio`:* float-velden met hoogstens **2 decimalen**;
  `65.25`, `30.5` en `1.15` mogen, `65.125`, `30.125` en `1.125` worden geweigerd (niet afgerond).
- *Int-velden:* een PHP-int, of een string die volledig matcht op `/^-?\d+$/`, dan casten naar `(int)` en het bereik
  controleren. Decimalen (`'400.5'`, `400.5`) worden geweigerd, niet afgerond.
- *Bool-velden (`flow_enabled`):* `true`/`false`, `1`/`0` of `'1'`/`'0'`; al het andere wordt geweigerd. Een
  ontbrekend vinkje in het admin-formulier telt als `false`.
- *Enum-velden (`gutter`, `popout_space`, `flow_space`, `flow_heading_space`):* alleen een tokennaam die de
  **nieuwe** config oplevert: de 9 stappen, de 8 one-up-paren en de custom paren uit de nieuwe `space_pairs`.
- *`space_pairs`:* one-up-paren en dubbele entries worden **stil weggelaten** (lean cut). Elke andere ongeldige entry
  (onbekende naam, verkeerde volgorde, meer dan 10) laat de **hele save** weigeren.
- *Onbekende sleutels* (ook de oude naam `feature_space`) vallen weg; via MCP weigert het schema ze al (§6).

| Sleutel | Type | Default | Bereik | Source |
|---------|------|---------|--------|--------|
| `enabled` | bool | `false` | — (via de Modules-tab) | V1 |
| `min_viewport` | int px | **400** | 200–2000, `< max_viewport` | V5: kleinste previewbreedte, `breakpoint_phone_portrait` (`builtin-breakpoints.php:18`); gebruikersantwoord OQ-CSS-01 (2026-11-09) |
| `max_viewport` | int px | **1440** | 200–3840 | gebruikersantwoord OQ-CSS-01 (2026-11-09). Onafhankelijk van `site_width` (V8); bij een root van 16px vallen de defaults samen (1440px = 90rem) |
| `font_min` / `font_max` | float px | 18 / 20 | 8–64 | gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `ratio_min` / `ratio_max` | float | 1.2 / 1.25 | 1.0–2.0 | gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `steps_up` / `steps_down` | int | 5 / 2 | 0–8 / 0–4 | gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `lh_heading_ratio` | float | **1.1** | 1.0–2.0, max. 2 decimalen | V13 (2026-11-10) |
| `lh_body_ratio` | float | **1.3** | 1.0–2.0, max. 2 decimalen | V13 (2026-11-10) |
| `leading_heading` | float rem | 0.25 | 0–2 | verzoek "fluid line-height op basis van de font-size"; gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `leading_body` | float rem | **0.25** | 0–2 | idem; default verlaagd van 0.5 door V13 (2026-11-10) |
| `site_width` | float **rem** | **90** | 20–240 | V6, V8 (2026-11-09): 90rem = 1440px bij een root van 16px. Dit is de breedte van de **wide-baan** (V10); de gutter komt er buiten, in de full-sporen |
| `measure` | float **ch** | **65** | 20–120, max. 2 decimalen | V8 (2026-11-09). Gemeten in de basis-font-size van de section (V12) |
| `gutter` | enum ruimte-token | `s-m` | tokennaam van de nieuwe config | gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `popout_space` | enum ruimte-token | `2xl` | idem | gebruikersantwoord OQ-CSS-02 (2026-11-09); hernoemd van `feature_space` door V8 (D-L3) |
| `grid_min` | float **ch** | **30** | 10–80, max. 2 decimalen | verzoek "zonder magic numbering": de enige drempel, instelbaar; V14 (2026-11-10): ch in plaats van rem, schaalt mee met de fluid tekst |
| `space_pairs` | string[] | `['s-l']` | `a-b` met a, b uit de t-shirtreeks, a vóór b, max. 10 | verzoek "Zoals utopia.fyi"; gebruikersantwoord OQ-CSS-02 (2026-11-09) |
| `flow_enabled` | bool | `true` | — | V7 |
| `flow_space` | enum ruimte-token | `s` | tokennaam van de nieuwe config | V7 |
| `flow_heading_space` | enum ruimte-token | `l` | idem | V7 |

Kopstijl-mapping zonder instelling (D7): `hN` = `step-max(steps_up − (N−1), 0)`; `p` = `step-0`; de section zelf =
`step-0` (V12).
Getallen in tokens (`site_width`, `measure`, `grid_min`, leadings, ratio's) volgen dezelfde notatie als §1d: nullen
achteraan weg. Voorbeelden: `90` → `90rem`, `75.5` → `75.5rem`, `65` → `65ch`, `60.25` → `60.25ch`, `30` → `30ch`,
`30.5` → `30.5ch`, `1.10` → `1.1`. De ratio-tokens zijn eenheidloos.

Losse optie (geen instelling): `pdk_css_framework_built` = de pluginversie waarmee `css-framework.css` het laatst is
gegenereerd (D-P2).

### 3a. Veldbeschrijvingen — normatieve tekst (V14, D29)

Eén bron in de code: `PDK_CSS_Framework::fields()` (statische methode) geeft per sleutel
`['label' => …, 'unit' => …, 'help' => …, 'group' => …]` terug, in deze volgorde. De backend-dev neemt de teksten
hieronder **letterlijk** over (in `__()`, textdomain `pdk-theme-options`).

- De **admin-tab** toont de velden per groep, in de groepsvolgorde Viewport → Typografie → Regelhoogte → Ruimte →
  Layout → Flow → Grid. Per veld staan het label, de eenheid en de help-tekst erbij.
- In het **MCP-schema** van `update-css-framework` is de `description` van elke eigenschap gelijk aan de `help`.
- **`get-css-framework`** geeft `fields` terug: dezelfde array.
- `enabled` hoort er niet bij (die staat op de Modules-tab).

| Sleutel | Groep | Label | Eenheid | Help (uitleg + voorbeeld) |
|---------|-------|-------|---------|---------------------------|
| `min_viewport` | Viewport | Kleinste schermbreedte | px | Bij deze schermbreedte staan tekst en ruimte op hun kleinste maat; nog smaller verandert er niets meer. Bijvoorbeeld 400 (Oxygen Phone Portrait). |
| `max_viewport` | Viewport | Grootste schermbreedte | px | Bij deze schermbreedte staan tekst en ruimte op hun grootste maat; nog breder verandert er niets meer. Bijvoorbeeld 1440, gelijk aan de sitebreedte. |
| `font_min` | Typografie | Basisgrootte klein scherm | px | Lettergrootte van gewone tekst op het kleinste scherm; alle kop- en ruimtestappen rekenen hiervanuit. Bijvoorbeeld 18. |
| `font_max` | Typografie | Basisgrootte groot scherm | px | Lettergrootte van gewone tekst op het grootste scherm. Bijvoorbeeld 20. |
| `ratio_min` | Typografie | Schaalfactor klein scherm | factor | Hoeveel keer groter elke kopstap is dan de vorige, op het kleinste scherm. Bijvoorbeeld 1.2. |
| `ratio_max` | Typografie | Schaalfactor groot scherm | factor | Hoeveel keer groter elke kopstap is dan de vorige, op het grootste scherm; hoger dan klein geeft meer contrast op groot scherm. Bijvoorbeeld 1.25. |
| `steps_up` | Typografie | Stappen omhoog | aantal | Aantal grotere stappen boven de basisgrootte; h1 gebruikt de hoogste stap. Bijvoorbeeld 5. |
| `steps_down` | Typografie | Stappen omlaag | aantal | Aantal kleinere stappen onder de basisgrootte, voor kleine tekst zoals bijschriften. Bijvoorbeeld 2. |
| `lh_heading_ratio` | Regelhoogte | Regelhoogte koppen | factor | Regelhoogte van h1–h6 is deze factor × de lettergrootte, plus de extra regelhoogte; verhoog hem bij fonts met lange uitlopers. Bijvoorbeeld 1.1. |
| `lh_body_ratio` | Regelhoogte | Regelhoogte tekst | factor | Regelhoogte van alinea's is deze factor × de lettergrootte, plus de extra regelhoogte. Bijvoorbeeld 1.3. |
| `leading_heading` | Regelhoogte | Extra regelhoogte koppen | rem | Vaste toeslag op de regelhoogte van koppen; geeft kleine koppen relatief meer lucht dan grote. Bijvoorbeeld 0.25. |
| `leading_body` | Regelhoogte | Extra regelhoogte tekst | rem | Vaste toeslag op de regelhoogte van alinea's. Bijvoorbeeld 0.25. |
| `space_pairs` | Ruimte | Extra ruimteparen | lijst | Eigen ruimtetokens die van de ene stap naar een verdere stap groeien, naast de vaste buurparen; schrijf als klein-groot. Bijvoorbeeld s-l. |
| `gutter` | Ruimte | Zijmarge | ruimtetoken | Minimale ruimte tussen de inhoud en de schermrand, en de tussenruimte in .pdk-grid. Bijvoorbeeld s-m. |
| `popout_space` | Ruimte | Uitsteek popout | ruimtetoken | Hoe ver .pdk-popout aan elke kant buiten de tekstkolom steekt. Bijvoorbeeld 2xl. |
| `site_width` | Layout | Sitebreedte | rem | Maximale breedte van de inhoud; elk direct kind van een section staat standaard op deze breedte. Bijvoorbeeld 90 (= 1440px). |
| `measure` | Layout | Leesbreedte | ch | Breedte van de tekstkolom .pdk-content; 60–75 is prettig leesbaar. Bijvoorbeeld 65. |
| `flow_enabled` | Flow | Verticale ritmiek | aan/uit | Zet automatische ruimte tussen opeenvolgende koppen, alinea's en lijsten aan. Bijvoorbeeld aan. |
| `flow_space` | Flow | Ruimte tussen tekstblokken | ruimtetoken | Ruimte boven een tekstelement dat direct op een ander volgt. Bijvoorbeeld s. |
| `flow_heading_space` | Flow | Ruimte boven tussenkoppen | ruimtetoken | Ruimte boven een h2–h4 die direct op tekst volgt. Bijvoorbeeld l. |
| `grid_min` | Grid | Minimale kaartbreedte | ch | Smalste breedte van een kaart in .pdk-grid; past er geen kaart meer naast, dan schuift hij naar een nieuwe rij. Bijvoorbeeld 30. |

Toegestane waarden voor `unit`: `px`, `rem`, `ch`, `factor`, `aantal`, `ruimtetoken`, `lijst`, `aan/uit`. Toegestane
waarden voor `group`: `Viewport`, `Typografie`, `Regelhoogte`, `Ruimte`, `Layout`, `Flow`, `Grid`.

---

## 4. Gegenereerde CSS — `uploads/pdk-theme-options/css-framework.css`

**Geen cascade layers (D-L1). Alleen logische eigenschappen (V11).** Het bestand bevat geen enkele `@layer` en geen
fysieke eigenschap (`left`, `right`, `width`, `margin-top`, `padding-left`, …) waar een logische variant bestaat. Het
bestaat uit vier blokken, in deze vaste volgorde: (1) tokens op `:root`, (2) basisregels op kale elementselectors,
(3) flow-regels, (4) layoutregels. Elke selector in blok 3 en 4 heeft specificiteit 0 (alles wat een klasse of
element noemt, staat in `:where()`).

Normatieve vorm bij de defaults (`clamp()`-waarden staan volledig in §1d). Afkorting in dit document:
`T` = `h1, h2, h3, h4, h5, h6, p, ul, ol, blockquote, figure, pre, table`, en `S` = `section:not(.pdk-off)`. In het
bestand staan beide voluit.

```css
/* (1) Tokens */
:root {
  --pdk-step--2: clamp(…); /* t/m */ --pdk-step-0: clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem); /* … */ --pdk-step-5: clamp(…);
  --pdk-space-3xs: …; /* … */ --pdk-space-3xl: …;           /* 9 stappen */
  --pdk-space-3xs-2xs: …; /* … */ --pdk-space-2xl-3xl: …;   /* 8 one-up-paren */
  --pdk-space-s-l: …;                                        /* custom paren */
  --pdk-site-width: 90rem;
  --pdk-measure: 65ch;
  --pdk-gutter: var(--pdk-space-s-m);
  --pdk-popout-space: var(--pdk-space-2xl);
  --pdk-grid-min: 30ch;
  --pdk-lh-heading: 1.1;
  --pdk-lh-body: 1.3;
  --pdk-leading-body: 0.25rem;
  --pdk-leading-heading: 0.25rem;
  --pdk-flow-space: var(--pdk-space-s);
  --pdk-flow-heading-space: var(--pdk-space-l);
}

/* (2) Basisstijlen: kale elementselectors (0,0,1) — zie "Overschrijven" (B1) en "Line-height" (V13). */
h1 { font-size: var(--pdk-step-5); } /* … */ h6 { font-size: var(--pdk-step-0); }
h1, h2, h3, h4, h5, h6 { line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading)); }
p { font-size: var(--pdk-step-0); line-height: calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body)); }

/* (3) Flow (V7): alleen met flow_enabled. Specificiteit 0, ná Modern Normalize — zie §4a. */
:where(T) { margin-block: 0; }
:where(T) + :where(T) { margin-block-start: var(--pdk-flow-space); }
:where(T) + :where(h2, h3, h4) { margin-block-start: var(--pdk-flow-heading-space); }
:where(.pdk-grid, .pdk-flow-off) > * { margin-block: 0; }

/* (4) Layout (D-L1, V10, V11, V12): de section is het grid. Alles specificiteit 0, ná normalize, basis en flow. */
:where(.pdk-container) { container-type: inline-size; }
:where(S) {
  --pdk-bo-content: min(var(--pdk-measure), 100% - var(--pdk-gutter) * 2);
  --pdk-bo-popout: minmax(0, var(--pdk-popout-space));
  --pdk-bo-wide: minmax(0, max(0px, (var(--pdk-site-width) - var(--pdk-measure)) / 2 - var(--pdk-popout-space)));
  --pdk-bo-full: minmax(var(--pdk-gutter), 1fr);
  font-size: var(--pdk-step-0);
  container-type: inline-size;
  display: grid;
  grid-template-columns:
    [full-start] var(--pdk-bo-full)
    [wide-start] var(--pdk-bo-wide)
    [popout-start] var(--pdk-bo-popout)
    [content-start] var(--pdk-bo-content) [content-end]
    var(--pdk-bo-popout) [popout-end]
    var(--pdk-bo-wide) [wide-end]
    var(--pdk-bo-full) [full-end];
}
:where(S) > * { grid-column: wide; }
:where(S) > :where(.pdk-content) { grid-column: content; }
:where(S) > :where(.pdk-popout) { grid-column: popout; }
:where(S) > :where(.pdk-popout-start) { grid-column: popout-start / content-end; }
:where(S) > :where(.pdk-popout-end) { grid-column: content-start / popout-end; }
:where(S) > :where(.pdk-wide-start) { grid-column: wide-start / content-end; }
:where(S) > :where(.pdk-wide-end) { grid-column: content-start / wide-end; }
:where(S) > :where(.pdk-full) { grid-column: full; }
:where(S) > :where(.pdk-full-start) { grid-column: full-start / wide-end; }
:where(S) > :where(.pdk-full-end) { grid-column: wide-start / full-end; }

:where(.pdk-grid) {
  display: grid;
  gap: var(--pdk-gutter);
  grid-template-columns: repeat(auto-fit, minmax(min(var(--pdk-grid-min), 100%), 1fr));
}
```

Let op de twee soorten namen: `full-start` (zonder `pdk-`) is een **gridlijn**, `.pdk-full-start` is een **klasse**
voor een kind. De klasse loopt van de lijn `full-start` tot de lijn `wide-end`.

- **Overschrijven en laadvolgorde (B1, D-L1):**
  - **Waarom geen lagen (D-L1):** Oxygens Modern Normalize zet `*{margin:0;padding:0}` unlayered (§1b). Een regel in
    een cascade layer verliest daar altijd van, wat de specificiteit ook is (live gemeten in Slice 1). Daarom staat
    alles unlayered.
  - **Tokens** op `:root`. Oxygens `:root`-variabelen worden later geprint en winnen bij gelijke naam (R-CSS-06).
  - **Basisregels** voor `h1`–`h6` en `p`: kale elementselectors (0,0,1). Ze winnen van de (eerder geprinte)
    normalize (`h1{font-size:2em}`, `h1…h6{line-height:1.2}`, §1b) en, zonder Oxygen, van theme.json-kopstijlen (kale
    `h1{…}`, §1c).
  - **Flow en layout:** specificiteit 0, gelijk aan de `*`-reset. Ze winnen omdat ze later geprint worden. Elke
    selector met een klasse (Oxygen, `custom-style.css`) wint van hen, ook voor de `font-size` van de section (V12), en
    binnen het framework beslist de volgorde: de baan-klassen staan ná `> *`.
  - De printvolgorde ligt vast:
    1. Oxygens normalize, letterlijk vóór `wp_head()` (`themeless/util.php:23-26`);
    2. **zonder Oxygen:** core's `global-styles`/placeholder in de head (`wp_enqueue_scripts` 10, §1c). **Met Oxygen**
       print WordPress `global-styles-inline-css` in de **footer**, dus ná stap 3 en 4 (live gemeten, §1c). Dat is
       onschadelijk: met `oxygen-zero` als thema bevat die alleen core's `button`-stijl, geen kop- of `p`-regels;
    3. **`css-framework.css`**: geregistreerd op `wp_enqueue_scripts` prioriteit 9, ge-enqueued op prioriteit **20**,
       zodat hij in de queue ná core's prioriteit-10-enqueue staat;
    4. **`custom-style.css`**: krijgt `pdk-css-framework` als dependency (§2), dus WordPress print het framework
       altijd eerst, en eigen CSS wint;
    5. Oxygens eigen globale CSS en element-CSS op `wp_head` 1000000 (§1b): ontwerpinstellingen in de builder winnen
       altijd (klasse-selectors en later).
  - Per element werkt ook bijvoorbeeld `style="--pdk-grid-min: 20ch"`, `style="--pdk-measure: 50ch"` (op de section)
    of `style="--pdk-lh-heading: 1.05"`.
- **Line-height (V13, D6, D27):** `line-height = ratio × font-size + leading`, als
  `calc(var(--pdk-lh-…) * 1em + var(--pdk-leading-…))`.
  - **Waarom een ratio:** de inkt van een letter (ascent + descent) is bij gangbare fonts ongeveer 1,17em. De vorige
    formule `calc(1em + leading)` gaf een `h1` van 1,07 × font-size (live gemeten: 61,04px → 65,04px), waardoor
    descenders over de volgende regel vielen. Met een ratio van minstens 1,1 plus de constante is de regelbox nooit
    kleiner dan de inkt: `h1` ≈ 1,17–1,19, `h6` ≈ 1,3, `p` ≈ 1,5 (tabel in §1d).
  - **Waarom de constante blijft:** `+ leading` geeft kleine letters relatief meer lucht en grote letters relatief
    minder. Zo blijft de line-height "op basis van de font-size" (het verzoek): de verhouding daalt vanzelf naarmate
    de letter groeit, en koppen zijn strakker dan `p`, zonder tweede clamp-reeks.
  - **Erfenis (R-CSS-03):** `calc(… em …)` wordt berekend tot een **lengte**, en die lengte erft door naar kinderen.
    Een kind met een andere `font-size` (bijvoorbeeld een `<small>` of `<span style="font-size:2em">` in een `p`)
    krijgt dus de line-height van de ouder in px, niet een herberekende. Uitweg: een klasse op dat kind met een eigen
    `line-height` (bijvoorbeeld een eenheidloze waarde), want elke klasse wint van de kale elementselector.
  - De section zelf krijgt geen line-height (Oxygens `html{line-height:1.4}` blijft gelden voor losse tekst).
- **De section is het grid (V10):** elke `section` zonder `.pdk-off` is een grid met zeven sporen en vier banen, van
  buiten naar binnen:

  | Baan | Breedte | Klasse op het kind |
  |------|---------|--------------------|
  | `full` | rand tot rand van de section | `.pdk-full` |
  | `wide` | `site_width`, gecentreerd; minimaal `gutter` vrij aan elke kant | standaard voor elk direct kind |
  | `popout` | content + aan elke kant `popout_space` | `.pdk-popout` |
  | `content` | `min(measure, 100% − 2 × gutter)` | `.pdk-content` |

  - Het wide-spoor is `(site_width − measure) / 2 − popout_space`, ondergrens 0. Content + 2 × popout + 2 × wide is
    dus precies `site_width`.
  - Op een smal scherm krimpen het popout- en het wide-spoor naar 0 (`minmax(0, …)`). De full-sporen houden minimaal
    `gutter` (`minmax(gutter, 1fr)`). Er staat geen `@media` in.
  - **Gevolg:** een gewone Oxygen-opbouw `section > div` ziet eruit als met de oude max-width-regel: de div staat op
    `wide`, maximaal de sitebreedte en gecentreerd, met de gutter gegarandeerd in de full-sporen. Er is geen padding
    en geen `box-sizing` meer nodig (de box-sizing-kwestie vervalt, V10).
  - Een achtergrond op de section loopt vanzelf rand tot rand; het kind hoeft daar niets voor te doen.
- **Eenzijdige varianten (D21, V11):** `-start` breekt uit naar de **start-kant**, `-end` naar de **eindkant** (in LTR
  links en rechts, in RTL andersom; gridlijnen volgen `direction` vanzelf). De andere kant lijnt uit op de baan waar het
  kind normaal naast staat:

  | Klasse | `grid-column` | Andere kant lijnt uit op |
  |--------|---------------|--------------------------|
  | `.pdk-full-start` / `.pdk-full-end` | `full-start / wide-end` / `wide-start / full-end` | `wide` (de standaardbaan) |
  | `.pdk-wide-start` / `.pdk-wide-end` | `wide-start / content-end` / `content-start / wide-end` | `content` (de tekstkolom) |
  | `.pdk-popout-start` / `.pdk-popout-end` | `popout-start / content-end` / `content-start / popout-end` | `content` |

  - **Waarom:** het is Ryan Mulligans principe (één kant breekt uit, de andere blijft in lijn met de rest). Een
    full-variant wordt gebruikt tussen gewone wide-blokken, dus de binnenkant lijnt uit op `wide`. Popout- en
    wide-varianten zijn beelden naast lopende tekst in `.pdk-content`, dus die lijnen uit op de tekstkolom.
  - **Geen `.pdk-content-start` / `-end`:** content is de binnenste baan. Er is niets smallers om op uit te lijnen,
    en "een content-brede kolom aan de start-kant van wide" is niet uit te drukken in lijnnamen zonder extra spoor. Wie
    dat wil, gebruikt `.pdk-wide-start` (tekstkolom plus de ruimte aan de start-kant).
  - **Geen `.pdk-wide`:** wide is al de standaard.
- **Basis-font-size van de section (V12):** de section krijgt `font-size: var(--pdk-step-0)` (specificiteit 0).
  - **Gevolgen:** losse tekst in een section (een `li`, een `div` met tekst, een knop zonder eigen maat) krijgt de fluid
    basisgrootte. `ch` in de sporen (`--pdk-measure`) en in het auto-grid (`--pdk-grid-min`) wordt in die grootte
    gemeten, dus leesbreedte en kaartbreedte groeien mee met de tekst: 65ch bij 18px is smaller dan 65ch bij 20px.
  - **Waar de `cqi` van die font-size tegen wordt gemeten:** een element kan zichzelf niet bevragen. `--pdk-step-0`
    bevat `cqi`, en die `cqi` in de `font-size` van de section wordt gemeten op de **dichtstbijzijnde container
    erboven**, niet op de section zelf. Voor een section op het hoogste niveau is dat de small viewport (V2). De
    basisgrootte van een topniveau-section volgt dus de viewport. Dat is dezelfde schaal als `p` buiten een section, en
    bij de defaults identiek tussen 400 en 1440px.
  - **Geneste sections:** een section binnen een andere section heeft de buitenste section als container. Zijn
    basisgrootte (en dus zijn `ch`-maat) volgt de breedte van de **buitenste** section. Is de buitenste section breder
    dan `max_viewport`, dan is die grootte gewoon het maximum (20px); is hij smaller, dan krimpt de binnenste section
    mee. De `p` en de koppen **in** de binnenste section meten wel op de binnenste section.
  - **Overerving:** de section geeft een berekende px-waarde door aan zijn kinderen. Kinderen met een eigen
    `font-size` (`p`, `h1`–`h6` uit blok 2, of iets in Oxygen) rekenen hun eigen `cqi` opnieuw uit tegen de section.
  - **Overschrijven:** elke typografie-klasse in Oxygen of in `custom-style.css` wint (0 vs. ≥ 0,1,0).
- **Containers en `cqi`:**
  - De section is ook een container (`container-type: inline-size`), en `.pdk-container` blijft een losse container
    (alleen `container-type`) voor `cqi` buiten sections, bijvoorbeeld een kaart of kolom.
  - `cqi` in de koppen, alinea's en flow-marges **in** de section wordt gemeten op de breedte van de section.
  - De eigen eigenschappen van de section (`font-size`, en de sporen met `--pdk-gutter` en `--pdk-popout-space`) kan
    de section niet tegen zichzelf oplossen. Ze worden gemeten op de dichtstbijzijnde container erboven: bij een
    section op topniveau de small viewport, bij een geneste section de buitenste section.
  - Tekst buiten elke container (ook in een `.pdk-off`-section) valt terug op de small viewport.
  - `ch` in `--pdk-measure` wordt in `grid-template-columns` opgelost tegen de font-size van de section (het
    grid-element), één keer voor alle kolommen, niet per kind. Het bezwaar van Josh Comeau
    (joshwcomeau.com/css/full-bleed/) gaat over `max-width` in `ch` op elk kind afzonderlijk, en speelt hier dus niet.
    Door V12 is die font-size `step-0`, dus de maat is fluid.
- **Auto-grid (V14):** `auto-fit` (lege sporen klappen in, dus weinig items vullen de rij); de enige drempel is
  `--pdk-grid-min` (standaard **30ch**), en `min(…, 100%)` voorkomt overflow op schermen die smaller zijn dan die
  drempel.
  - `ch` in `--pdk-grid-min` wordt opgelost tegen de font-size van het `.pdk-grid`-element zelf. In een section erft
    dat `step-0`, dus de kaartbreedte schaalt mee met de fluid tekst (zoals `measure`), en tekstkaarten breken op een
    zinnige tekstbreedte.
  - Buiten een section volgt `ch` de geërfde font-size van het grid-element (meestal die van `body`/root), en is de
    drempel dus niet fluid (R-CSS-17).
  - Een `.pdk-grid` als direct kind van een section staat gewoon op `wide` (of een andere baan via een tweede klasse).
- **Opt-out en uitwegen (OQ-CSS-03):**
  - `.pdk-off` op de section: geen grid, geen `container-type`, geen basis-font-size; kinderen krijgen geen baan. De
    flow-regel geldt wel (die hangt niet aan de section).
  - **Eigen `display` in Oxygen:** zet de gebruiker op een section zelf `display: flex` (een klasse-selector), dan wint
    die van het framework. De banen werken dan niet meer, maar `container-type` en de basis-font-size blijven. Dat is de
    keuze van de gebruiker; voor een volledig vrije section is `.pdk-off` bedoeld.
  - **Geneste sections:** een `section` binnen een section staat op `wide` en is zelf weer een grid. Zijn full-sporen
    houden opnieuw minimaal een gutter, dus de inhoud krijgt aan elke kant **twee gutters**. Dat is bewust; wie dat niet
    wil, zet `.pdk-off` op de binnenste section of `.pdk-full` op de binnenste section zelf. Zie hierboven voor de
    font-size van geneste sections.
  - **Uitweg voor sections van derden** (WooCommerce, formulieren, page builders), waar je geen klasse kunt toevoegen:
    elke regel met een klasse wint van specificiteit 0. De snippet komt in `docs/modules/css-framework.md` en in de
    helptekst van de tab:
    ```css
    /* Voorbeeld: alle sections binnen .woocommerce ongemoeid laten */
    .woocommerce section { display: block; container-type: normal; font-size: inherit; }
    ```
  - **Grid-gedrag van de kinderen:** floats werken niet op grid-items, en marges tussen de kinderen van de section
    klappen niet samen. De flow-regel gebruikt alleen `margin-block-start`, dus er wordt niets verdubbeld.
  - **Geen `min-inline-size: 0` op de kinderen (D23):** dat is niet nodig. De automatische minimumgrootte van een
    grid-item geldt alleen als het item een spoor met `auto` als minimum overspant (CSS Grid, "automatic minimum size
    of grid items"), en geen van onze sporen heeft dat (`min()`, `minmax(0, …)`, `minmax(gutter, 1fr)`). Een kind
    groeit dus nooit door zijn inhoud breder dan zijn baan. Te brede inhoud (een lange URL in `pre`, een te brede
    tabel) loopt zichtbaar over binnen de baan; dat lost de gebruiker op met `overflow-wrap` of `overflow`.
  - Padding die de gebruiker in Oxygen op de section zet, komt bij de gutter op: de sporen liggen binnen de
    content-box van de section.

### 4a. Modern Normalize en de flow-regel (V7)

- **Aanname:** Oxygens Modern Normalize staat aan. Die zet op elk element `margin: 0; padding: 0` op specificiteit 0,
  unlayered (`modern-normalize.css:265-278`). De plugin levert zelf geen normalize en zet de Oxygen-optie niet aan (V7).
- **Flow-regel (alleen met `flow_enabled`):** tekstelementen krijgen verticale ritmiek uit de ruimte-tokens, in Utopia-
  en Every Layout-stijl (de "owl"-selector):
  - `:where(T) { margin-block: 0 }`: alle tekstelementen starten op 0. Met Modern Normalize is dat al zo. Zonder
    Oxygen wint deze regel ook van de browserstijlen (auteursregels gaan altijd boven de user-agent). Met de default
    normalize wint die normalize `h1{margin:.67em 0}` (0,0,1) wel (D-L2, R-CSS-13).
  - `:where(T) + :where(T)`: een tekstelement direct na een ander tekstelement krijgt `--pdk-flow-space` erboven.
  - `:where(T) + :where(h2, h3, h4)`: een tussenkop direct na een tekstelement krijgt `--pdk-flow-heading-space`.
    Deze regel staat ná de vorige en wint dus bij gelijke specificiteit.
  - `:where(.pdk-grid, .pdk-flow-off) > *`: binnen het auto-grid en binnen een container met `.pdk-flow-off` geen
    flow-marges. Deze regel staat als laatste in het flow-blok en wint dus.
- **Waarom zo (de specificiteit klopt):** de reset van Modern Normalize is `*` = 0 en unlayered. De flow-regels zijn
  ook 0 en unlayered, en winnen door de volgorde: het framework print ná de normalize (§4, stap 1 vs. 3). Elke klasse,
  Oxygen-ontwerpinstelling of regel in `custom-style.css` wint nog steeds (hogere specificiteit of later). De
  layoutregels (blok 4) zetten geen `margin-block`, dus ze botsen niet met de flow. De global styles die met Oxygen in
  de footer komen (§1c), bevatten alleen de `button`-stijl van core en raken de flow dus ook niet.
- **Verfijning van het voorstel, met reden:**
  - De tussenkopregel is beperkt tot een **tekst**-voorganger (`:where(T) +`), niet `:where(*) +`. Met `*` zou een
    `h2` direct na een `div`, afbeelding of Oxygen-container ook de grote marge krijgen, en dat botst met
    flex-containers die hun ruimte via `gap` regelen.
  - Alleen `margin-block-start` op aangrenzende broers, nooit `-end`: geen dubbele marges en geen marge op het eerste
    of laatste kind, dus geen extra ruimte binnen de padding van een section of kaart.
- **Botsingscheck:**
  - *Section-grid:* zet `font-size`, `display`, `container-type`, `grid-template-columns` op de section en
    `grid-column` op de kinderen; de flow zet alleen `margin-block` op tekstelementen. Tekstelementen die direct in de
    section staan zijn grid-items: hun marges klappen niet samen, maar er staat alleen een start-marge, dus de ritmiek
    klopt. Geen botsing.
  - *`.pdk-grid`:* werkt met `gap`. Een flow-marge op het 2e en volgende item zou de rijen scheef trekken, daarom de
    reset `:where(.pdk-grid) > *`.
  - *Oxygen-elementen:* de Container heeft geen standaard-CSS of -eigenschappen (§1b, `element.php:66-74`). Ruimte die
    de gebruiker in de builder zet, staat in klasse-selectors die later worden geprint, en wint dus van de flow. De
    enige botsing: een container waarop de gebruiker zelf flex/grid met `gap` zet en waarin tekstelementen direct
    naast elkaar staan; dan tellen `gap` en de flow-marge op. Uitweg: de klasse `.pdk-flow-off` op die container, of een
    marge in Oxygen (R-CSS-12).
- **Melding op de tab:** staat Oxygen 6 aan (`pdk_oxygen6_active()`) en geeft
  `\Breakdance\Settings\get_bool_setting( 'modern_normalize_enabled' )` `false`, dan toont de tab bovenaan een
  `notice-warning`:

  > *"Het CSS Framework gaat uit van Oxygens Modern Normalize (een reset die alle standaardmarges en -padding van de
  > browser weghaalt; de flow-regel bouwt daarop). Zet hem aan via Oxygen → Settings → Advanced → Modern Normalize →
  > Enable. Let op: op een bestaande site verschuift daardoor content die op browsermarges leunt."*

  De check staat achter `function_exists` en in een `try/catch ( \Throwable )` (`get_bool_setting()` kan gooien,
  `options.php:238-240`). Bij een fout of met Oxygen inactief: geen melding. De check is alleen-lezen en wijzigt niets
  aan de Oxygen-optie. Dezelfde check levert `oxygen.modern_normalize` in de MCP-uitvoer (§6, D26).

**Opslag en laden:**
- Het bestand wordt **direct geschreven** naar `PDK_STORAGE_DIR . 'css-framework.css'`, na
  `pdk_ensure_storage_dir()`, en dus niet via `pdk_write_storage_file()` (die eist de code-cap en schrijft
  `.bak`/hash, `helpers.php:75-78`, `:106-116`). De inhoud komt volledig uit gevalideerde getallen (D3).
- Opnieuw gegenereerd (D4, D-P2):
  - bij elke geslaagde save (admin-tab of MCP);
  - bij de registratie als het bestand ontbreekt;
  - bij de registratie als de optie `pdk_css_framework_built` niet gelijk is aan `PDK_PLUGIN_VERSION`. Na een
    plugin-update krijgt het bestand zo de nieuwe generatorlogica, zonder dat iemand hoeft op te slaan.

  Na elke regeneratie wordt `pdk_css_framework_built` op `PDK_PLUGIN_VERSION` gezet; `uninstall.php` verwijdert de
  optie. Er wordt niet bij elke request gegenereerd.
- Register op `wp_enqueue_scripts` prioriteit **9**, enqueue op prioriteit **20**, handle `pdk-css-framework`,
  versie `filemtime` (D5). De handle wordt alleen geregistreerd als het bestand bestaat (na eventuele regeneratie).
  Zo kan custom-style nooit afhangen van een handle die er niet is, want WordPress slaat een stylesheet met een
  ontbrekende dependency over. Het builder-canvas is een frontend-request (`?breakdance_iframe`), dus het stylesheet
  hoort daar ook te laden. AC-CSS-017 controleert dat (door de gebruiker handmatig bevestigd, V14).

---

## 5. Oxygen-sync (Slice 2)

- **Wanneer:** na elke geslaagde save (admin of MCP), dus pas nadat de instellingen en het bestand zijn opgeslagen.
  Alleen als `pdk_oxygen6_active()` én `function_exists( '\Breakdance\Variables\saveVariables' )` en
  `function_exists( '\Breakdance\Data\get_global_option' )` (OQ-CSS-04). Anders: status `skipped`, met de melding
  "Oxygen 6 niet actief — alleen het CSS-bestand bijgewerkt".
- **Bron voor de merge:** de **ruwe** optie via `\Breakdance\Data\get_global_option( 'variables_json_string' )`, niet
  de gefilterde `getVariables()` (anders schrijven we variabelen van andere filters de database in). Is het resultaat
  een string (oud formaat, `variables.php:68-72`), dan wordt die nog eens JSON-gedecodeerd. Geldt
  `global_option_exists()` niet, dan is het startpunt `[]`. Bestaat de optie wel maar is het resultaat geen array,
  dan krijgt de sync status `error` en wordt er **niets** geschreven.
- **Wat:** elk token uit het `:root`-blok van §4 wordt een variabele `{id, cssVariableName: 'pdk-step-0', label: 'PDK
  step 0', type: 'unit', value: {number: <expr>, unit: 'custom', style: <expr>}, collection: 'PDK Framework'}`. Dat
  zijn: steps, spaces, paren, `site-width`, `measure`, `gutter`, `popout-space`, `grid-min` (nu `30ch`), de twee
  line-height-ratio's (`lh-heading`, `lh-body`), de twee leadings en de twee flow-spaces. Ook de eenheidloze ratio's
  gaan als `unit` met `unit: 'custom'` mee (één vorm voor alle tokens; `style` wordt letterlijk uitgevoerd,
  `macro.twig:6`). De interne spoorvariabelen `--pdk-bo-*` staan op de section, niet op `:root`, en worden **niet**
  gesynchroniseerd. De vorm volgt `css-utils.php:325`. Een nieuwe variabele krijgt `id = wp_generate_uuid4()`, de naam
  staat **zonder** voorloop-`--` (zoals Oxygens eigen insert, `variables.php:487-490`, `:566-573`). Er is geen
  `dynamicData`-sleutel. De tokenlijst voor het bestand, Oxygen en MCP komt uit één bron.
- **Idempotent (pure merge, testbaar zonder Oxygen):** overschrijf op `cssVariableName` met **behoud van het
  bestaande id**, voeg nieuwe toe aan het eind, laat vreemde variabelen en hun volgorde ongemoeid, en voeg de collectie
  `PDK Framework` toe als die ontbreekt. Roep `saveVariables()` alleen aan als het resultaat verschilt van wat er
  staat. Een tweede sync zonder wijziging schrijft dus niets.
- **Bescherming tegen stil wissen (M3):** `saveVariables()` controleert zijn invoer niet (§1b). Daarom eerst:
  1. `$payload = wp_json_encode( [ 'variables' => …, 'collections' => … ] )`; is dat `false`, dan status `error` en
     niet aanroepen;
  2. de payload bevat **nooit** `{}`: onze entries hebben geen lege objecten en lege lijsten worden als `[]`
     gecodeerd. Bevat hij toch `{}`, dan status `error` en niet aanroepen;
  3. simuleer wat Oxygen doet, `json_decode( str_replace( '{}', 'null', $payload ), true )`, en eis dat het resultaat
     een array is met `variables` en `collections`, allebei arrays. Anders status `error` en niet aanroepen;
  4. pas dan `saveVariables( $payload )`.
- **Guards (OQ-CSS-04):** stappen 1–4 en het lezen staan in een `try { … } catch ( \Throwable $e )`. Een `\Exception`
  of `\Error` (bijv. `TypeError`) daarbinnen geeft status `error` met de foutmelding. De instellingen en het bestand
  blijven opgeslagen. Admin: redirect met `saved=1&warning=<rawurlencode(melding)>`, getoond als `notice-warning`
  ("Instellingen opgeslagen, Oxygen-sync mislukt: …"). MCP: `oxygen.status = 'error'` met `oxygen.message`.
- **Verouderd** (bijv. `steps_up` 5 → 3, waardoor `pdk-step-4`/`-5` niet meer gegenereerd worden): blijft in Oxygen
  staan en wordt **nooit** verwijderd, maar ook niet gemeld (lean cut). Verwijderen zou `{var-<id>}`-verwijzingen in de
  builder stil breken.
- **Richting:** eenrichtingsverkeer, PDK is de bron. Een waarde die in de builder is aangepast wint op de frontend
  (Oxygens `:root` komt later, bij gelijke specificiteit) tot de volgende PDK-save hem overschrijft. Dat staat in de
  admin-tekst.
- **Module uit:** de variabelen in Oxygen blijven staan (de module-code wordt dan niet eens geladen,
  `class-pdk-plugin.php:92-94`); pagina's houden hun tokenwaarden, alleen de layoutklassen, basisregels en flow
  verdwijnen. Verwijderen kan via Oxygens eigen UI of `oxygen/delete-css-variables`.

---

## 6. MCP-abilities (Slice 2)

Ze staan in de module agent-abilities en worden alleen geregistreerd als `class_exists( 'PDK_CSS_Framework' )`,
hetzelfde patroon als de Element Studio-abilities (`class-pdk-agent-abilities.php:118-121`). Beide modules moeten
dus aan staan; staat er één uit, dan bestaat de ability niet. `meta`: `show_in_rest`, `mcp.public`.

| Ability | Input | Output | Annotaties |
|---------|-------|--------|-----------|
| `pdk-theme-options/get-css-framework` | `{}` (geen `properties`-sleutel, `additionalProperties: false`) | `{config: {…§3}, fields: {sleutel: {label, unit, help, group}}, tokens: [{name: '--pdk-step-0', value: 'clamp(…)'}], classes: [...], css_url, oxygen: {active: bool, modern_normalize: bool\|null}}` | readonly, niet-destructief, idempotent |
| `pdk-theme-options/update-css-framework` | `{config: object}` volgens het schema hieronder; ontbrekende sleutels houden hun huidige waarde | `{saved: true, config, tokens, oxygen: {status: 'synced'\|'unchanged'\|'skipped'\|'error', message?: string}}` of `WP_Error 'pdk_invalid_config'` met per veld de fout | niet-readonly, **destructief** (overschrijft Oxygen-variabelen), idempotent |

`fields` is exact `PDK_CSS_Framework::fields()` (§3a, de 21 sleutels).

`classes` is precies (13): `pdk-off`, `pdk-container`, `pdk-content`, `pdk-popout`, `pdk-popout-start`,
`pdk-popout-end`, `pdk-wide-start`, `pdk-wide-end`, `pdk-full`, `pdk-full-start`, `pdk-full-end`, `pdk-grid`,
`pdk-flow-off`. Geen enkele klassenaam bevat `left` of `right` (V11).

**`oxygen.modern_normalize` (D26):** de sleutel staat er **altijd**. Waarde `true`/`false` is de stand van
`modern_normalize_enabled`. De waarde is `null` als Oxygen inactief is, of als `get_bool_setting()` ontbreekt of gooit
(§4a). Zo kan een agent "uit" (`false`) onderscheiden van "onbekend" (`null`).

**Input-schema van `update-css-framework`:** `{type: object, additionalProperties: false, required: [config],
properties: {config: {type: object, additionalProperties: false, properties: {min_viewport, max_viewport,
steps_up, steps_down: {type: integer}; font_min, font_max, ratio_min, ratio_max, lh_heading_ratio, lh_body_ratio,
leading_body, leading_heading, grid_min, site_width, measure: {type: number}; gutter, popout_space, flow_space,
flow_heading_space: {type: string}; flow_enabled: {type: boolean}; space_pairs: {type: array, items: {type: string},
maxItems: 10}}}}}`. **Elke eigenschap onder `config` krijgt `description` = de `help` uit `fields()`** (§3a), zodat een
agent dezelfde uitleg ziet als de beheerder (D29). De oude sleutel `feature_space` staat er niet in en wordt dus door
het schema geweigerd. Het schema is de eerste poort. De servervalidatie uit §3 draait daarna altijd nog (het schema
alleen wordt niet vertrouwd; bijvoorbeeld de 2-decimalenregel staat alleen in de servervalidatie).

**Rechten: `current_user_can( 'manage_options' )`** voor beide abilities. Waarom niet `PDK_CAP_EDIT_CODE`: dit zijn
instellingen, geen code. Het is dezelfde poort als de admin-tab (`class-pdk-admin.php:306`) en als Oxygens eigen
variabele-abilities (`core.php:85-88`). Die afweging klopt alleen omdat er geen vrije tekst in de CSS komt: alleen
getallen binnen bereik en namen uit een vaste lijst (§3).

---

## 7. Acceptatiecriteria

**H** = `php tests/run.php` (stubs, geen WordPress). **L** = live op dev.local (http://dev.local, plugin als kopie
in mu-plugins — eerst de gewijzigde bestanden kopiëren; WP-CLI; beheerder `pdkmin`). Oxygen 6.2-rc.1 staat op
dev.local aan; voor de checks met "Oxygen inactief" eerst `wp plugin deactivate oxygen` en daarna weer
`wp plugin activate oxygen`. Elke L-check vermeldt of Oxygen aan of uit staat. Pixelwaarden met ±0,05px tolerantie,
breedtes uit probes met ±0,5px (tenzij anders vermeld). De probes in de testpagina's gebruiken zelf ook logische
eigenschappen (`inline-size`).

### Slice 1 — kern

**FR-CSS-01 Module en instellingen** (Must) — Source: V1, V5, V6, V7, V8, V13, V14, OQ-CSS-01, OQ-CSS-02
- **AC-CSS-001** (H): `css_framework` staat in `$module_map` en in `MODULE_TABS`; `get_defaults()['css_framework']`
  is exact de defaultkolom van §3. Dat is onder meer: `enabled === false`, `min_viewport === 400`,
  `max_viewport === 1440`, `site_width == 90`, `measure == 65`, `grid_min == 30`, `lh_heading_ratio == 1.1`,
  `lh_body_ratio == 1.3`, `leading_heading == 0.25`, `leading_body == 0.25`, `popout_space === '2xl'`,
  `flow_enabled === true`, `flow_space === 's'`, `flow_heading_space === 'l'`, en **geen** sleutel `feature_space`.
  `module_missing_dependency( 'css_framework' ) === ''` zonder actieve Oxygen.
- **AC-CSS-002** (H): de validatie geeft elk van deze gevallen terug als fout met de veldnaam, en laat de opgeslagen
  config dan ongewijzigd:
  - `min_viewport` ≥ `max_viewport`; elk veld buiten bereik; `ratio_min = 0.9`;
  - `site_width = 19.9` en `site_width = 240.5`; `measure = 121`;
  - `measure = 65.125` (meer dan 2 decimalen);
  - `grid_min = 9.99` en `grid_min = 80.01` (buiten bereik); `grid_min = 30.125` (meer dan 2 decimalen);
  - `lh_heading_ratio = 0.99` en `lh_heading_ratio = 2.01` (buiten bereik); `lh_body_ratio = 0.99` en
    `lh_body_ratio = 2.01`; `lh_heading_ratio = 1.125` en `lh_body_ratio = 1.125` (meer dan 2 decimalen);
  - `font_min = '1;}body{display:none'`; `font_min = '1e999'` (INF); `font_min = 'NAN'`;
  - `max_viewport = '1440.5'` en `steps_up = 2.5` (decimaal in een int-veld);
  - `gutter = 'huge'`; `gutter = 's-l'` met `space_pairs = []` (het token bestaat in de nieuwe config niet);
  - `popout_space = 's-l'` met `space_pairs = []`;
  - `space_pairs = ['s-l', 'l-s']` en `['x-y']` en `['<script>']`: de hele save wordt geweigerd.

  Daarnaast:
  - `space_pairs = ['m-l', 's-l', 's-l']` slaagt en wordt opgeslagen als `['s-l']` (one-up en dubbel stil weggelaten);
  - `site_width = 75.5` en `measure = 60.25` slagen en geven de tokens `--pdk-site-width: 75.5rem` en
    `--pdk-measure: 60.25ch`;
  - `grid_min = 30.5` slaagt en geeft `--pdk-grid-min: 30.5ch`; `grid_min = 10` en `grid_min = 80` slagen (randen);
  - `lh_heading_ratio = 1.15` en `lh_body_ratio = 2` slagen en geven `--pdk-lh-heading: 1.15` en `--pdk-lh-body: 2`;
  - numerieke strings (`'18'`) worden als getal geaccepteerd;
  - onbekende sleutels, waaronder `feature_space`, vallen weg.

**FR-CSS-02 Fluid tokens volgens Utopia** (Must) — Source: verzoek "fluid Font-size met clamp", "Fluid margin/paddings", "Zoals utopia.fyi"; V2
- **AC-CSS-003** (H): met de defaults (400→1440, 18→20, 1,2→1,25) levert de generator voor **elke** rij in de drie
  tabellen van §1d (8 typestappen, 9 ruimte-stappen, 8 one-up-paren, `s-l`) exact de `clamp()`-string uit de
  laatste kolom. Met `ratio_min = 1.5`, `ratio_max = 1.1` (verder de defaults) is `step-5` exact
  `clamp(2.0131rem, 11.0544rem - 10.0459cqi, 8.543rem)`. Geen enkele uitvoer bevat `-0` of `+ -`.
  (De tester controleert de tabel op utopia.fyi met dezelfde invoer; die toont `vi`/`vw` waar wij `cqi` schrijven.)
- **AC-CSS-004** (H): de `:root`-tokenset bij de defaults is precies `step--2…step-5`, 9 ruimte-stappen,
  8 one-up-paren, `space-s-l` en de **11** vaste tokens uit §4: `--pdk-site-width: 90rem`, `--pdk-measure: 65ch`,
  `--pdk-gutter`, `--pdk-popout-space`, `--pdk-grid-min: 30ch`, `--pdk-lh-heading: 1.1`, `--pdk-lh-body: 1.3`,
  `--pdk-leading-body: 0.25rem`, `--pdk-leading-heading: 0.25rem`, en de twee flow-spaces (die laatste twee ook met
  `flow_enabled = false`). Elke naam komt één keer voor; er is geen `--pdk-feature-space` en geen `--pdk-bo-*` op
  `:root`; `--pdk-grid-min` eindigt nooit op `rem`; `--pdk-step-0` is
  `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)`; de CSS bevat geen `vw`, `vi` of `@media` (V2, "zonder magic
  numbering").

**FR-CSS-03 Basisstijlen koppen en p** (Must) — Source: verzoek "fluid line-height op basis van de font-size; voor headings en P"; B1; D-L1; V13
- **AC-CSS-005** (H): het bestand bevat nergens `@layer`. De blokken staan in de volgorde tokens (`:root`) →
  basisregels → flow → layout. De basisregels gebruiken precies de selectors `h1` … `h6`, `h1, h2, h3, h4, h5, h6` en
  `p` (geen `:where()`, geen klasse). `h1` = `step-5` t/m `h6` = `step-0`, `p` = `step-0`, en de twee
  line-height-regels staan er letterlijk:
  `h1, h2, h3, h4, h5, h6 { line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading)); }` en
  `p { font-size: var(--pdk-step-0); line-height: calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body)); }`. De
  oude vorm `calc(1em + var(--pdk-leading-…))` komt niet meer voor. Met `steps_up = 3` krijgen `h1`/`h2`/`h3`
  `step-3`/`-2`/`-1` en `h4`–`h6` `step-0`.
- **AC-CSS-020** (L). Testpagina met `<section><div><h1>Kop</h1><h6>Klein</h6><p>Tekst</p></div></section>`; met Oxygen
  actief als HTML Code-element in een Oxygen-pagina. Drie runs:
  - **primair (V7):** Oxygen actief met Modern Normalize **aan**;
  - Oxygen actief met de default normalize;
  - Oxygen inactief.

  In **elke** run, gemeten met `getComputedStyle` (±0,05px; zie de tabel in §1d):
  - vensterbreedte 400: `h1` `font-size` 44,7904px, `line-height` **53,2694px**; `h6` 18px / **23,8px**; `p` 18px /
    **27,4px**;
  - vensterbreedte 1600: `h1` 61,0352px / **71,1387px**; `h6` 20px / **26px**; `p` 20px / **30px**;
  - de `line-height` van `h1` is bij beide breedtes groter dan 1,15 × zijn `font-size` (de regelbox is groter dan de
    inkt);
  - met `h1 { font-size: 10px; }` in `custom-style.css` (module Custom CSS aan) is `h1` 10px (eigen CSS wint).

  **Alleen** in de runs met Modern Normalize aan en met Oxygen inactief: `h1` heeft als eerste kind
  `margin-block-start` 0px. In de run met de default normalize wordt de marge niet gecontroleerd; die blijft `.67em`
  (D-L2, R-CSS-13).

**FR-CSS-04 Layout: de section als grid** (Must) — Source: verzoek "section > div max-width met een fullwidth overwrite", "grid template area met … breakout naar links en rechts", "Automatische grid responsive fallback"; V6; V8; V10; V11; V12; V14; OQ-CSS-03; D-L1
- **AC-CSS-006** (H): het layoutblok staat ná het flow-blok, zonder `@layer`, en bevat letterlijk de regels uit §4:
  - `:where(.pdk-container)` met alleen `container-type: inline-size`;
  - `:where(section:not(.pdk-off))` met precies: de vier variabelen `--pdk-bo-content`, `--pdk-bo-popout`,
    `--pdk-bo-wide` en `--pdk-bo-full` letterlijk zoals §4, **`font-size: var(--pdk-step-0)`** (V12),
    `container-type: inline-size`, `display: grid`, en de `grid-template-columns` met de lijnnamen `full-`, `wide-`,
    `popout-` en `content-start`/`-end`, in die volgorde;
  - `:where(section:not(.pdk-off)) > *` met `grid-column: wide`, en daarna de negen kindregels uit §4 in die volgorde
    (`content`, `popout`, `popout-start`, `popout-end`, `wide-start`, `wide-end`, `full`, `full-start`, `full-end`),
    elk als `:where(section:not(.pdk-off)) > :where(.pdk-…)` met exact de `grid-column` uit de tabel in §4;
  - `:where(.pdk-grid)` met `repeat(auto-fit, minmax(min(var(--pdk-grid-min), 100%), 1fr))`;
  - het bestand bevat **niet**: `pdk-breakout`, `pdk-feature`, `--pdk-bo-feature`, `max-inline-size`, `box-sizing`,
    `padding-inline` of `min-inline-size`;
  - het bestand bevat **geen** fysieke eigenschap of fysieke klassenaam (V11): geen match op
    `/(^|[\s;{])(left|right|width|margin-(top|bottom|left|right)|padding-(top|bottom|left|right))\s*:/` en geen
    `-left`/`-right` in een klassenaam.
- **AC-CSS-007** (L, Oxygen **inactief**, `<html dir="ltr">`, standaardthema van dev.local, root-font-size 16px).
  Testpagina met:
  - probes **vóór** de section (zelfde context als de sporen van de section):
    `<i id="g" style="display:block;inline-size:var(--pdk-gutter)"></i><i id="ps" style="display:block;inline-size:var(--pdk-popout-space)"></i>`;
  - `<section id="s"><div id="d"><p id="p1">…</p><div class="pdk-container" style="inline-size:800px"><p id="p2">…</p></div><div id="gr" class="pdk-grid"><div>1</div><div>2</div><div>3</div><div>4</div></div></div><div id="mp" style="overflow:hidden"><i id="ch" style="display:block;font-style:normal;inline-size:1ch"></i><i id="m" style="display:block;font-style:normal;inline-size:var(--pdk-measure)"></i></div><div id="c" class="pdk-content"></div><div id="po" class="pdk-popout"></div><div id="pst" class="pdk-popout-start"></div><div id="wst" class="pdk-wide-start"></div><div id="wen" class="pdk-wide-end"></div><div id="f" class="pdk-full"></div><div id="fst" class="pdk-full-start"></div><div id="fen" class="pdk-full-end"></div></section>`.
    `#ch` en `#m` staan in een kind van de section, erven dus de font-size van de section, en zitten in een
    `overflow:hidden`-wrapper zodat een maat breder dan de wide-baan geen horizontale scroll geeft. `font-style:normal`
    voorkomt dat de cursieve `<i>` een andere `ch` meet. `#gr` zit in `#d` en erft dus dezelfde font-size als `#ch`;
  - een `<p id="p0">` buiten elke section;
  - een `<section class="pdk-off"><div id="o">…</div></section>`.

  Gemeten met `getComputedStyle` en `getBoundingClientRect` in Chrome (`w(x)` = breedte; in LTR is `L(x)` de
  start-rand en `R(x)` de eindrand):
  - **Type, venster 400:** `#p1` en `#p0` 18px.
  - **Type, venster 1600** (sectionbreedte ≥ 1440): `#p1` en `#p0` 20px. `#p2` is **18,769px**, want `cqi` volgt de
    800px-container (de viewport zou 20px geven).
  - **Section:** `#s` heeft `display: grid` en `container-type: inline-size`, en geen padding of marge.
  - **Basis-font-size en maat (V12):** de berekende `font-size` van `#s` en `#ch` is 18px bij venster 400 en 20px bij
    venster 1600. `w(#m)` = 65 × `w(#ch)` (±1px), bij 400 én bij 1600; `w(#m)` bij 1600 is dus groter dan bij 400.
  - **Venster 1600:**
    - wide (standaard): `w(#d)` = `min( w(#s) − 2 × w(#g), 1440 )`, gecentreerd in `#s`;
    - content: `w(#c)` = `min( w(#m), w(#s) − 2 × w(#g) )`, gecentreerd;
    - popout: `w(#po)` = `w(#c) + 2 × w(#ps)`, gecentreerd; `#pst` van `L(#po)` tot `R(#c)`;
    - wide-varianten: `#wst` van `L(#d)` tot `R(#c)`; `#wen` van `L(#c)` tot `R(#d)`;
    - full: `w(#f)` = `w(#s)`; `#fst` van `L(#s)` tot `R(#d)`; `#fen` van `L(#d)` tot `R(#s)`.
  - **Venster 400:** `w(#c)` = `w(#po)` = `w(#d)` = `w(#s) − 2 × w(#g)` (popout- en wide-sporen zijn 0, de maat past
    niet), en `w(#f)` = `w(#s)`.
  - **Auto-grid (V14):** met `gap` = de berekende `column-gap` van `#gr` en `k` = 30 × `w(#ch)` is het aantal kolommen
    van `#gr` bij 400 én 1600 gelijk aan `min( 4, max( 1, floor( (w(#gr) + gap) / (k + gap) ) ) )`. Bij 400 is dat 1.
  - **Opt-out:** de `.pdk-off`-section heeft `display: block`, `container-type: normal` en een `font-size` gelijk aan
    die van `body`; `w(#o)` = de breedte van die section.
  - **Geen overflow:** bij 320 is `document.documentElement.scrollWidth ≤ clientWidth`.
  - **Fixed:** een `position: fixed`-element binnen een section blijft vast aan de viewport (R-CSS-02).
- **AC-CSS-024** (L, Oxygen **inactief**, `<html dir="rtl">`, venster 1600). Dezelfde testpagina als AC-CSS-007,
  alleen met `dir="rtl"` op `<html>`:
  - `#fst` (`.pdk-full-start`) staat aan de **rechter**rand: `R(#fst)` = `R(#s)`, en `L(#fst)` = `L(#d)`;
  - `#fen` (`.pdk-full-end`) staat aan de linkerrand: `L(#fen)` = `L(#s)`, en `R(#fen)` = `R(#d)`;
  - `#wst` loopt van `R(#d)` tot `L(#c)`, en `#pst` van `R(#po)` tot `L(#c)` (de start-kant is rechts);
  - de breedtes van `#d`, `#c`, `#po` en `#f` zijn gelijk aan die in AC-CSS-007 bij dezelfde vensterbreedte.
- **AC-CSS-019** (L, Oxygen inactief): de uitweg-snippet uit §4, in `custom-style.css` gezet (module Custom CSS aan),
  geeft een `<div class="woocommerce"><section><div>` `display: block`, `container-type: normal` en de `font-size` van
  `.woocommerce` op de section, en de div is even breed als die section. Zonder de snippet heeft dezelfde section
  `display: grid`, `container-type: inline-size` en de `step-0`-grootte.

**FR-CSS-08 Flow-regel en Modern Normalize** (Must) — Source: V7; verzoek "Fluid margin/paddings"; `modern-normalize.css:265-278`
- **AC-CSS-021** (H):
  - Met de defaults bevat het bestand, zonder `@layer`, ná de basisregels en vóór het layoutblok, precies de vier
    flow-regels uit §4, in die volgorde. Elke selector staat volledig in `:where()` (de lijst `T` voluit), en de
    marges verwijzen naar `var(--pdk-flow-space)` en `var(--pdk-flow-heading-space)` via `margin-block` /
    `margin-block-start` (geen `margin-top`).
  - Met `flow_enabled = false` ontbreken alle vier, en staan de tokens `--pdk-flow-*` er nog wel.
  - Validatie: `flow_space = 'huge'` en `flow_heading_space = 's-l'` met `space_pairs = []` geven elk een fout met de
    veldnaam; `flow_enabled = 'ja'` geeft een fout.
- **AC-CSS-022** (L, Oxygen **actief**, Modern Normalize **aan**). Testpagina in een Oxygen-pagina (HTML Code-element):
  - `<section id="s"><i id="fs" style="display:block;inline-size:var(--pdk-flow-space)"></i><i id="fh" style="display:block;inline-size:var(--pdk-flow-heading-space)"></i><div id="d"><p id="a">…</p><p id="b">…</p><h2 id="h">…</h2><ul id="u"><li>…</li></ul></div><p id="x">…</p><p id="y" class="pdk-content">…</p></section>`
    (`#fs`/`#fh` staan als kind in de section en meten dus tegen de section, net als de marges);
  - een `.pdk-grid` met 3 `<p>`'s;
  - een `<div class="pdk-flow-off" style="display:flex;flex-direction:column;gap:10px"><p>…</p><p id="c">…</p></div>`.

  Gemeten bij vensterbreedte 400 én 1600:
  - `#b` `margin-block-start` = breedte van `#fs` (verwacht 18px bij 400 en 20px bij 1600);
  - `#h` `margin-block-start` = breedte van `#fh` (verwacht 36px / 40px);
  - `#u` `margin-block-start` = breedte van `#fs`; `#a` (eerste kind) `margin-block-start` 0px;
  - `#y` (een `p` direct na de `p` `#x`, beide grid-items van de section) heeft `margin-block-start` = breedte van
    `#fs`, en de verticale afstand tussen de onderkant van `#x` en de bovenkant van `#y` is precies die marge (geen
    verdubbeling);
  - de `line-height` van `#a` is 27,4px bij 400 en 30px bij 1600 (V13; de flow verandert de line-height niet);
  - elk `<p>` in de `.pdk-grid` en `#c` hebben `margin-block-start` 0px;
  - `#d` staat nog steeds op de wide-baan uit AC-CSS-007 (geen botsing met de layout).
- **AC-CSS-023** (H + L):
  - **H** (stubs voor `pdk_oxygen6_active()` en `\Breakdance\Settings\get_bool_setting()`). De tab-render bevat een
    `notice-warning` met de tekst "Modern Normalize" en het pad "Oxygen → Settings → Advanced" als Oxygen actief is en
    de setting `false` geeft. Geen melding als de setting `true` geeft, als Oxygen inactief is, of als de stub een
    `\InvalidArgumentException` gooit; in dat laatste geval ook geen fatale fout. De render roept nooit een
    schrijvende Oxygen-functie aan.
  - **L** (Oxygen actief): Modern Normalize uitzetten via Oxygen → Settings → Advanced, dan de tab herladen: de melding
    staat er. Aanzetten en herladen: de melding is weg.
    `wp eval 'var_dump( \Breakdance\Settings\get_bool_setting( "modern_normalize_enabled" ) );'` bevestigt telkens de
    stand. Met Oxygen gedeactiveerd: geen melding. (Door de gebruiker handmatig bevestigd, V14.)

**FR-CSS-05 Bestand, enqueue, admin-tab** (Must) — Source: V1; `class-pdk-custom-css.php:14`, `:29-34`; B1; D-P2
- **AC-CSS-008** (H):
  - een save als gebruiker **zonder** `PDK_CAP_EDIT_CODE` (met `manage_options`) schrijft
    `PDK_STORAGE_DIR/css-framework.css` met exact de generator-uitvoer; er ontstaat geen `.bak` en geen hash in
    `pdk_file_hashes`; `css-framework.css` staat niet in `pdk_watched_files()`;
  - ontbreekt het bestand bij de registratie, dan wordt het opnieuw gegenereerd;
  - is `pdk_css_framework_built` leeg of ongelijk aan `PDK_PLUGIN_VERSION`, dan wordt het bestand bij de registratie
    opnieuw gegenereerd en staat de optie daarna op `PDK_PLUGIN_VERSION`. Is hij gelijk en bestaat het bestand, dan
    wordt er niets geschreven (de `filemtime` blijft gelijk) (D-P2);
  - een ongeldige save laat het bestand byte-gelijk;
  - `uninstall.php` bevat `delete_option( 'pdk_css_framework_built' )`.
- **AC-CSS-009** (H + L):
  - **H:** de module registreert `pdk-css-framework` op `wp_enqueue_scripts` prioriteit 9 (versie = `filemtime`) en
    enqueuet hem op prioriteit 20. `PDK_Custom_CSS::enqueue()` geeft `pdk-custom-style` de deps
    `['pdk-css-framework']` als die handle geregistreerd is, en `[]` als dat niet zo is.
  - **L, Oxygen inactief:** in `curl -s http://dev.local/` staan de stijlregels in de head in deze volgorde (per
    regelnummer): `global-styles` of de gehoiste global styles, `css-framework.css?ver=`, `custom-style.css`.
  - **L, Oxygen actief:** in de head staan normalize, daarna `css-framework.css?ver=`, daarna `custom-style.css`.
    `global-styles-inline-css` mag in de **footer** staan, ná die twee (§1c). Dat telt als geslaagd, mits die inline
    stijl geen selector `h1`–`h6` of `p` bevat (`grep` op het footer-blok).
  - In beide gevallen: `grep -c "css-framework.css?ver="` geeft 1 met de module aan, en 0 met de module uit. Met de
    module uit laadt `custom-style.css` nog steeds.
- **AC-CSS-010** (L): de tab "CSS Framework" is alleen zichtbaar met de module aan (anders valt `?tab=css_framework`
  terug op Modules). Geldig opslaan → melding "opgeslagen" en een nieuwe `filemtime`. Ongeldig opslaan → een
  `notice-error` met de veldnaam (query-arg `error`) en een ongewijzigde md5 van het bestand (foutmeldingen door de
  gebruiker handmatig als duidelijk bevestigd, V14). De tab toont:
  - per token de naam, min px, max px en de `clamp()`-waarde;
  - de velden per groep met label, eenheid en help-tekst (AC-CSS-025);
  - de banen van de section met hun klassen (de 13 uit §6, met de uitleg dat `-start`/`-end` in RTL omdraaien) en de
    uitweg-snippet (§4).

**FR-CSS-09 Veldbeschrijvingen uit één bron** (Must) — Source: V14 (2026-11-10); §3a
- **AC-CSS-025** (H + L):
  - **H, de bron:** `PDK_CSS_Framework::fields()` bevat precies de 21 sleutels uit §3a (alle instellingen behalve
    `enabled`), in de volgorde van de tabel. Elke entry heeft een niet-lege `label`, `unit`, `help` en `group`, letterlijk
    gelijk aan §3a; `unit` komt uit de lijst `px`, `rem`, `ch`, `factor`, `aantal`, `ruimtetoken`, `lijst`, `aan/uit`, en
    `group` uit `Viewport`, `Typografie`, `Regelhoogte`, `Ruimte`, `Layout`, `Flow`, `Grid`. Elke sleutel uit
    `fields()` bestaat in `get_defaults()['css_framework']`, en omgekeerd (behalve `enabled`).
  - **H, het MCP-schema:** in het input-schema van `update-css-framework` heeft elke eigenschap onder `config` een
    niet-lege `description`, en die is **gelijk** aan `fields()[sleutel]['help']`. Er is geen eigenschap zonder entry in
    `fields()`.
  - **H, de read-ability:** `get-css-framework` geeft `fields`, en die is gelijk aan `fields()`.
  - **H, de tab-render:** de HTML van de tab bevat de zeven groepskoppen in de volgorde Viewport → Typografie →
    Regelhoogte → Ruimte → Layout → Flow → Grid. Per veld staan het label, de eenheid en de help-tekst uit `fields()`
    in de HTML (ge-escaped), en het veld staat onder de kop van zijn groep.
  - **L (Oxygen actief):** op de tab staan de groepen en per veld label + eenheid + uitleg zichtbaar; bijvoorbeeld bij
    "Leesbreedte" de eenheid "ch" en de tekst "Breedte van de tekstkolom .pdk-content; 60–75 is prettig leesbaar.
    Bijvoorbeeld 65."

### Slice 2 — MCP, Oxygen, release

**FR-CSS-06 MCP-abilities** (Must) — Source: V1; V14; `class-pdk-agent-abilities.php:118-121`, `:109`
- **AC-CSS-011** (H): met `PDK_CSS_Framework` geladen registreert `register_abilities()` `get-css-framework`
  (readonly) en `update-css-framework` (destructief, niet readonly), beide met een permission callback die `false`
  geeft zonder `manage_options` en `true` met `manage_options`, ook zonder code-cap. Het input-schema van
  `update-css-framework` heeft `additionalProperties: false` op beide niveaus en de types uit §6 (`site_width`,
  `measure`, `grid_min`, `lh_heading_ratio` en `lh_body_ratio` als `number`, `popout_space` als `string`, geen
  `feature_space`). Zonder de klasse wordt geen van beide geregistreerd.
- **AC-CSS-012** (H):
  - `get-css-framework` geeft `config`, `fields` (AC-CSS-025), `tokens` (elk alleen `name` en `value`; `--pdk-step-0`
    met de waarde uit AC-CSS-004, `--pdk-site-width` = `90rem`, `--pdk-measure` = `65ch`, `--pdk-grid-min` = `30ch`,
    `--pdk-lh-heading` = `1.1`, `--pdk-lh-body` = `1.3`), `classes` (precies de 13 uit §6, geen enkele met `left` of
    `right`) en `css_url`.
  - `oxygen.modern_normalize` bestaat altijd: `true`/`false` volgens de stub met Oxygen actief, en `null` met Oxygen
    inactief of als de stub gooit (D26).
  - `update-css-framework` met `{config: {steps_up: 3}}` slaat op, regenereert het bestand en geeft tokens zonder
    `step-4`/`step-5`; met `{config: {lh_heading_ratio: 1.2}}` geeft hij `--pdk-lh-heading` = `1.2`; met
    `{config: {grid_min: 24}}` geeft hij `--pdk-grid-min` = `24ch`.
  - Met een ongeldige config (zoals in AC-CSS-002) geeft hij `WP_Error 'pdk_invalid_config'` met de fout per veld.
    Config en bestand blijven dan ongewijzigd.
- **AC-CSS-013** (L, Oxygen inactief):
  - `wp eval 'echo wp_json_encode( wp_get_ability( "pdk-theme-options/get-css-framework" )->execute( [] ) );' --user=pdkmin`
    geeft JSON met `tokens` en `fields`.
  - Een tijdelijke redacteur: `wp user create pdk-cssfw-test pdk-cssfw-test@example.test --role=editor --porcelain`
    geeft een `<id>`. Hetzelfde `wp eval` met `--user=<id>` wordt geweigerd. Daarna `wp user delete <id> --yes`.
  - Met de module uit bestaat de ability niet.

**FR-CSS-07 Oxygen-variabelen** (Must) — Source: V4, OQ-CSS-04; §1b, §5
- **AC-CSS-014** (H): de pure merge:
  - een lege lijst → alle `:root`-tokens toegevoegd (incl. `pdk-site-width`, `pdk-measure`, `pdk-popout-space`,
    `pdk-grid-min`, `pdk-lh-heading`, `pdk-lh-body`; geen `pdk-bo-*`) met een UUID4-id, een naam zonder `--`,
    `type 'unit'`, value `{number, unit: 'custom', style}`, collectie `PDK Framework`, en geen `dynamicData`;
  - de merge nogmaals op zijn eigen uitvoer → identieke array en `changed === false`;
  - een bestaande `pdk-step-0` met id `abc` → id `abc` blijft, de waarde wordt bijgewerkt;
  - een bestaande `pdk-grid-min` met `style` `16rem` (oude waarde) → id blijft, `style` wordt `30ch`;
  - vreemde variabelen blijven staan, in dezelfde volgorde;
  - een `pdk-step-5` die niet meer gegenereerd wordt, blijft onveranderd staan.
- **AC-CSS-015** (H): in elk geval hieronder zijn de instellingen opgeslagen, is het bestand geregenereerd, en komt er
  geen uncaught exception uit de save:
  - zonder actieve Oxygen, of zonder `saveVariables`: status `skipped`, `saveVariables` wordt niet aangeroepen;
  - een `saveVariables`-stub die een `\Exception` gooit: status `error`, en `message` bevat de exceptietekst;
  - een stub die een `\Error` (`TypeError`) gooit: hetzelfde;
  - een vreemde variabele met een ongeldige UTF-8-label (`"\xB1"`), waardoor `wp_json_encode` `false` geeft: status
    `error`, `saveVariables` wordt **niet** aangeroepen;
  - een ruwe optie die bestaat maar geen array oplevert: status `error`, `saveVariables` wordt niet aangeroepen;
  - de admin-redirect bevat in de foutgevallen `saved=1` én `warning=…`, en `render_notices()` toont een `notice-warning`.
- **AC-CSS-016** (L, Oxygen actief):
  - Na een save is in `wp option get oxygen_variables_json_string --format=json` de `style` van `pdk-step-0`
    byte-gelijk aan `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)`, die van `pdk-site-width` aan `90rem`, die van
    `pdk-measure` aan `65ch`, die van `pdk-grid-min` aan `30ch`, en die van `pdk-lh-heading` / `pdk-lh-body` aan `1.1`
    / `1.3`.
  - Een tweede save zonder wijziging levert dezelfde id's en hetzelfde aantal entries op.
  - Oxygens variabelenbestand op de frontend bevat `--pdk-step-0:`. De tekst wordt niet byte-vergeleken, want Oxygen
    minifiet (`format-css.php:12-22`).
  - De uiteindelijke waarde wordt gecontroleerd via het effect: met css-framework.css geblokkeerd in DevTools (alleen
    Oxygens definitie actief) heeft een `<p style="font-size:var(--pdk-step-0)">` in een `<div style="container-type:inline-size">`
    bij venster 400 18px en bij 1600 20px, en `<h1 style="font-size:var(--pdk-step-5)">` daarin bij 1600 61,0352px.
  - In de builder toont het variabelenpaneel de collectie "PDK Framework".
- **AC-CSS-017** (L, Oxygen actief): het builder-canvas (`?breakdance_iframe`) laadt `css-framework.css`
  (Network-tab): een section met een `.pdk-full`- en een `.pdk-content`-kind ziet er in de builder uit zoals op de
  frontend. (Door de gebruiker handmatig bevestigd, V14.)
- **AC-CSS-018** (L, Oxygen actief): de module uitzetten → geen `pdk-css-framework` op de frontend; de
  `pdk-*`-variabelen staan nog in `oxygen_variables_json_string` (D12).

**Release-check (geen AC, wel blokkerend):**
- 2.13.0 op `pdk-theme-options-plugin.php:6` en `:24`;
- CHANGELOG-kop `## [2.13.0] — <releasedatum>`, met de nieuwe dependency van `pdk-custom-style` vermeld;
- `docs/modules/css-framework.md` in de stijl van de andere moduledocs (inclusief de veldtabel uit §3a, de banen van
  de section en hun klassen met de bron van het patroon, de eenzijdige varianten met hun RTL-gedrag (D21, V11), de
  basis-font-size van de section en wat die betekent voor geneste sections (V12), de line-height-formule met de waarden
  uit §1d en de erfenis-kanttekening (V13), de `ch`-kaartbreedte van het auto-grid (V14), de `ch`- en
  `cqi`-kanttekeningen, de opt-out- en uitweg-snippet, de laadvolgorde zonder lagen (incl. de footer-plek van de
  global styles met Oxygen), de flow-regel, de Modern Normalize-aanname met de waarschuwing dat bestaande sites
  verschuiven, de Utopia-formules met de tabel uit §1d, en het Oxygen-risico);
- de `other_output`-regel in `get-site-info`;
- de Baseline-tabel uit §1e is op webstatus.dev gecontroleerd en de datum staat in de PR.

---

## 8. Rollen en use cases

| ROLE-ID | Naam | Soort | Auth | Grens | Bron |
|---------|------|-------|------|-------|------|
| ROLE-001 | WordPress-beheerder | human | sessie + `manage_options` | site-breed | `class-pdk-admin.php:306` |
| ROLE-002 | AI-agent via MCP | system | WP-gebruiker + `manage_options` | site-breed | V1 |
| ROLE-003 | Bezoeker | human | geen | publiek | passief, ziet alleen de uitvoer |

ROLE-003 voert geen handelingen uit (zelfde keuze als D7 in `docs/imgx-lqip-brief.md`), dus de use cases blijven in
deze brief en er komt geen `docs/use-cases.md`.

**UC-CSS-01 | Actor: ROLE-001 | Framework instellen**
- Trigger: de beheerder zet de module aan en opent de tab CSS Framework.
- Hoofdstroom: (1) per groep de velden lezen (label, eenheid, uitleg met voorbeeld) en waarden aanpassen, ook de line-height-ratio's en de kaartbreedte (2) opslaan (3) bestand gegenereerd, tokentabel bijgewerkt (4) met Oxygen actief: variabelen gesynchroniseerd.
- Alternatief: A1 (4) Oxygen inactief → sync overgeslagen, met melding; A2 (1) Oxygen actief en Modern Normalize uit → waarschuwing met het pad naar de Oxygen-instelling; A3 na een plugin-update → het bestand wordt vanzelf opnieuw gegenereerd (D-P2).
- Foutpad: E1 (2) ongeldig veld → foutmelding, niets veranderd; E2 (4) sync faalt, gooit een exceptie of zou de variabelen wissen → niet geschreven, save blijft geslaagd, waarschuwingsmelding.
- Covers: AC-CSS-001, AC-CSS-002, AC-CSS-008, AC-CSS-010, AC-CSS-015, AC-CSS-016, AC-CSS-018, AC-CSS-023, AC-CSS-025

**UC-CSS-02 | Actor: ROLE-001 | Pagina opbouwen met tokens en de banen van de section**
- Trigger: de beheerder bouwt in Oxygen (of in eigen HTML) een section met content.
- Hoofdstroom: (1) koppen, `p` en losse tekst in de section schalen fluid, met een line-height die nooit kleiner is dan de inkt, ook met Oxygens normalize (2) elk direct kind van de section staat vanzelf op de wide-baan: maximaal de sitebreedte, gecentreerd, met gutter (3) tekst krijgt vanzelf verticale ritmiek via de flow-regel (4) waar nodig een kind op `.pdk-content` (leesbreedte, groeit mee met de tekst), `.pdk-popout`, `.pdk-full` of een eenzijdige variant (`-start`/`-end`), en `.pdk-grid` voor kaarten (kaartbreedte in ch, groeit mee met de tekst) (5) overige ruimte via `var(--pdk-space-*)` of de Oxygen-variabelen.
- Alternatief: A1 (2) een section moet vrij blijven → `.pdk-off`; A2 (2) een section van een andere plugin → de uitweg-snippet in `custom-style.css`; A3 (2) eigen `display: flex` op de section in Oxygen → de banen vervallen, bewust; A4 (4) een RTL-site → `-start` staat vanzelf rechts; A5 (1) eigen kopstijl, eigen font-size of eigen line-height → `custom-style.css` of de Oxygen-klasse wint; A6 (3) een flex-container met `gap` → `.pdk-flow-off`.
- Postconditie: builder en frontend zien er hetzelfde uit; geen horizontale scroll op 320px.
- Covers: AC-CSS-003, AC-CSS-004, AC-CSS-005, AC-CSS-006, AC-CSS-007, AC-CSS-009, AC-CSS-017, AC-CSS-019, AC-CSS-020, AC-CSS-021, AC-CSS-022, AC-CSS-024

**UC-CSS-03 | Actor: ROLE-002 | Config lezen en aanpassen via MCP**
- Hoofdstroom: (1) `get-css-framework`, inclusief `fields` met de uitleg per instelling (2) `update-css-framework` met een gedeeltelijke config; het schema beschrijft elk veld met dezelfde uitleg (3) bestand en Oxygen bijgewerkt.
- Foutpad: E1 (2) ongeldig of onbekende sleutel → `pdk_invalid_config` of schemafout, niets veranderd; E2 geen `manage_options` → geweigerd.
- Covers: AC-CSS-011, AC-CSS-012, AC-CSS-013, AC-CSS-025

---

## 9. Vastgelegde besluiten (omkeerbare interne defaults)

| ID | Besluit |
|----|---------|
| D1 | Voorvoegsel `pdk-` voor variabelen en klassen; interne spoorvariabelen `--pdk-bo-*` op de section. Vast in de code, geen instelling, en los van Oxygens eigen `css_prefix`. Geen cascade layers (D-L1). Omkeerbaar tot de release; daarna breaking (major) |
| D2 | Modulesleutel `css_framework`, label/tab "CSS Framework", standaard uit, géén Oxygen-afhankelijkheid |
| D3 | Bestand `css-framework.css` direct geschreven, niet via `pdk_write_storage_file()`, niet in de integriteitscontrole: de inhoud is volledig afgeleid van gevalideerde getallen |
| D4 | Regenereren bij elke save, bij een ontbrekend bestand en bij een nieuwe pluginversie (D-P2); niet per request |
| D5 | Register op `wp_enqueue_scripts` 9, enqueue op 20, handle `pdk-css-framework`, versie `filemtime`; `pdk-custom-style` krijgt hem als dependency zodra hij geregistreerd is (B1) |
| D6 | Line-height = `ratio × font-size + leading` (V13, D27), aparte ratio en leading voor koppen en `p`, op kale elementselectors (B1). De section krijgt geen line-height |
| D7 | Kopstijl-mapping afgeleid van `steps_up`, geen instelling. `h1`–`h6` en `p` krijgen font-size en line-height (verzoek: "voor headings en P"), met specificiteit 0,0,1: ze winnen van normalize en theme.json, en verliezen van `custom-style.css`, Oxygen-ontwerpinstellingen en alles met een klasse. Daarnaast krijgt de section een basis-font-size op specificiteit 0 (V12, D25) |
| D8 | 1rem = 16px; notatie volgens §1d (4 decimalen, PHP `round()`, nooit `-0`, helling als `+ x`/`- x`); ruimte-stappen afgerond op hele px (Utopia) |
| D9 | Eén read- en één write-ability; geen aparte tokens-ability, geen `min_px`/`max_px` in de MCP-uitvoer (lean cut; de admin-tab toont ze wel) |
| D10 | De abilities staan in agent-abilities, voorwaardelijk op `class_exists( 'PDK_CSS_Framework' )` |
| D11 | Permission `manage_options` (§6) |
| D12 | Oxygen-sync via `saveVariables()` (OQ-CSS-04), niet via de filter `oxygen_variables`: een gefilterde variabele komt via een builder-save (`save.php:52`) en via Oxygens eigen insert-ability (`variables.php:441`, `:631`) toch in de optie terecht, en het cachebestand ontstaat alleen bij `generateCacheForGlobalSettings()`. De merge leest de ruwe optie. Alleen `:root`-tokens, geen `--pdk-bo-*`. Verder: eenrichtingsverkeer, overschrijven op naam met behoud van id, nooit verwijderen (ook niet verouderd, en geen melding daarvan), niets opruimen als de module uitgaat |
| D13 | **Ruimte (bevestigd door de gebruiker, V9, 2026-11-09):** margin en padding lopen via `var(--pdk-space-*)` of de Oxygen-variabelen, plus de flow-regel (D17) voor de verticale ritmiek van tekst, **zonder** spacing-utilityklassen (geen `.p-s`, `.mt-l`). Containers: de section automatisch (opt-out `.pdk-off`), de rest opt-in via `.pdk-container` |
| D14 | Min/max-viewport: statische defaults (400 uit Oxygens ingebouwde breakpoints, 1440 px); eigen Oxygen-breakpoints worden niet gevolgd (R-CSS-08). `max_viewport` staat los van `site_width` (V8) |
| D15 | Opt-out-klasse heet `.pdk-off`: geen grid, geen `container-type`, geen basis-font-size. Alle section-regels hebben specificiteit 0 (OQ-CSS-03, V10) |
| D16 | Sync-meldingen via query-arg: `error` voor validatiefouten (bestaand), nieuw `warning` naast `saved=1` voor een mislukte sync |
| D17 | Flow-regel (V7, bevestigd met V9): alles in `:where()` (specificiteit 0), geprint ná de normalize en vóór de layout. Alleen `margin-block-start` tussen **aangrenzende tekstelementen** (lijst `T`). De tussenkopregel geldt alleen na een tekst-voorganger (niet na `*`). Reset binnen `.pdk-grid` en `.pdk-flow-off`. Standaard aan (`flow_enabled`), tokens `s` / `l` |
| D18 | Modern Normalize (V7): het framework gaat ervan uit; de plugin levert geen eigen normalize en zet de Oxygen-optie niet aan. Alleen een waarschuwing op de tab, bij Oxygen actief en de optie uit (§4a) |
| D19 | Breedtes (V8): `site_width` in rem (float, default 90) = breedte van de wide-baan; `measure` in ch (float, default 65, max. 2 decimalen) = de content-baan, gemeten in de basis-font-size van de section (V12); `popout_space` als ruimte-token (default `2xl`). Bij een root van 16px is 90rem = 1440px = `max_viewport` |
| D20 | **De section is het grid (V10).** Elke `section:not(.pdk-off)` krijgt `container-type`, `display: grid` en zeven sporen (full / wide / popout / content, volgens Ryan Mulligan). Elk direct kind staat standaard op `wide`, waardoor `section > div` eruitziet als met de oude max-width-regel, zonder padding of box-sizing. Vervallen: de `section > div`-regel, de `.pdk-full`-override op div/section, `.pdk-breakout` en de box-sizing-afweging daarvan |
| D21 | **Eenzijdige varianten (V11 voor de namen):** `-start` = X-start tot de binnenrand van de referentiebaan, `-end` = vanaf die binnenrand tot X-end. Referentiebaan voor `full` = `wide` (de standaardbaan; full-varianten staan tussen wide-blokken), voor `wide` en `popout` = `content` (beelden naast lopende tekst). Geen `content-start`/`-end` (content is de binnenste baan; niets om op uit te lijnen) en geen `.pdk-wide` (dat is de standaard) |
| D22 | **Naamgeving:** de baan heet `wide` (was feature); de spoorvariabele heet `--pdk-bo-wide` (was `--pdk-bo-feature`), de lijnnamen `wide-start`/`wide-end`. Geen `.pdk-feature*`-klassen meer. Niet breaking (nooit uitgebracht) |
| D23 | **Geen `min-inline-size: 0` op de kinderen:** de automatische minimumgrootte van grid-items geldt alleen bij een spoor met `auto` als minimum, en die hebben we niet (`min()`, `minmax(0, …)`, `minmax(gutter, 1fr)`). Een kind groeit dus niet door zijn inhoud over zijn baan heen. Te brede inhoud loopt zichtbaar over; dat is aan de gebruiker (`overflow-wrap`/`overflow`) |
| D24 | **Logisch, niet fysiek (V11):** klassen heten `-start`/`-end`, nooit `-left`/`-right`. In de CSS staan alleen logische eigenschappen (`inline-size`, `margin-block*`, `container-type`, `grid-column` met benoemde lijnen); geen `left`, `right`, `width` of fysieke marges/padding. Gridlijnen volgen `direction`, dus RTL werkt zonder extra regel (AC-CSS-024). Niet breaking: de `-left`/`-right`-namen zijn nooit uitgebracht |
| D25 | **Basis-font-size op de section (V12, 2026-11-10):** `:where(section:not(.pdk-off))` krijgt `font-size: var(--pdk-step-0)` op specificiteit 0. De maat in `ch` wordt daardoor fluid, en losse tekst krijgt de basisgrootte. De `cqi` in die font-size wordt gemeten op de dichtstbijzijnde container **boven** de section (small viewport op topniveau, de buitenste section bij nesting). Elke typografie-klasse wint. Vervangt de opt-in-tip uit de vorige versie van R-CSS-14 |
| D26 | **`oxygen.modern_normalize` in de MCP-uitvoer:** de sleutel staat er altijd; `true`/`false` is de stand van de Oxygen-optie, `null` betekent onbekend (Oxygen inactief, functie ontbreekt of gooit). Zelfde guarded check als de tab-melding (§4a) |
| D27 | **Line-height met ratio (V13, 2026-11-10):** `h1`–`h6`: `calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading))`; `p`: `calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body))`. Nieuwe instellingen `lh_heading_ratio` (1.1) en `lh_body_ratio` (1.3), bereik 1.0–2.0, max. 2 decimalen, als eenheidloze `:root`-tokens `--pdk-lh-heading`/`--pdk-lh-body` (gesynchroniseerd als `unit` met `custom`-stijl, zoals de andere tokens). `leading_body` 0.5 → 0.25rem. Reden: met `calc(1em + leading)` was een `h1` 1,07 × zijn font-size en vielen descenders over de volgende regel (live gemeten); een ratio ≥ 1,1 plus de constante houdt de regelbox altijd groter dan de inkt (≈ 1,17em), en de constante houdt kleine letters luchtig en grote compact |
| D28 | **`grid_min` in ch (V14, 2026-11-10):** float, default 30, bereik 10–80, max. 2 decimalen; token `--pdk-grid-min: 30ch`. In een section is de font-size `step-0`, dus de kaartbreedte schaalt mee met de fluid tekst, net als `measure`, en tekstkaarten breken op een zinnige tekstbreedte. Niet breaking: de rem-variant is nooit uitgebracht; de sync overschrijft een bestaande `pdk-grid-min` op naam (AC-CSS-014) |
| D29 | **Veldbeschrijvingen uit één bron (V14, 2026-11-10):** `PDK_CSS_Framework::fields()` levert per instelling `label`, `unit`, `help` en `group` (tekst in §3a). De admin-tab groepeert en beschrijft de velden daarmee, het MCP-schema gebruikt `help` als `description` per eigenschap, en `get-css-framework` geeft het geheel terug als `fields`. Eén plek, zodat beheerder en agent nooit verschillende uitleg zien |
| D-L1 | **Geen cascade layers** (live-bevinding Slice 1). Modern Normalize `*{margin:0;padding:0}` is unlayered en wint van elke gelaagde regel; gemeten in Slice 1: een gelaagde layoutregel deed niets (marge 0, links 0 bij 1600px). Tokens op `:root`; basisregels op kale elementen (0,0,1); flow en layout unlayered op specificiteit 0, geprint ná de normalize en in de volgorde tokens → basis → flow → layout. Elke klasse wint van flow en layout |
| D-L2 | Met de **default** normalize (Modern Normalize uit) blijft de `h1`-marge `.67em`: `h1{margin:.67em 0}` (0,0,1) wint van de flow-reset in `:where()` (0). Geaccepteerd, omdat V7 Modern Normalize aanneemt. AC-CSS-020 controleert in dat geval alleen font-size en line-height (R-CSS-13) |
| D-L3 | `feature_space` heet `popout_space`. Niet breaking: de naam is nooit uitgebracht (alleen in Slice 1 op dev.local); de oude sleutel valt bij validatie stil weg en het MCP-schema weigert hem |
| D-P2 | **Regenereren bij een nieuwe pluginversie:** de losse optie `pdk_css_framework_built` bewaart de `PDK_PLUGIN_VERSION` van de laatste generatie. Is die bij de registratie anders (of leeg), dan wordt `css-framework.css` opnieuw gegenereerd en de optie bijgewerkt. Zo krijgt een site na een update de nieuwe generatorlogica zonder handmatig opslaan. `uninstall.php` verwijdert de optie |

---

## 10. Open vragen

Alle vier beantwoord door de gebruiker op 2026-11-09 en verwerkt. V7–V14 zijn besluiten van de gebruiker, en
D-L1–D-L3 en D-P2 zijn live-bevindingen of implementatiekeuzes; geen van beide zijn open vragen.

| ID | Vraag | Antwoord | Verwerkt in |
|----|-------|----------|-------------|
| OQ-CSS-01 | `max_viewport` = 1120 (begin van Oxygens Desktop-breakpoint) of 1440 (sitebreedte)? | **Beantwoord: 1440**; min blijft 400 | §3, §1d (hele tabel herberekend), AC-CSS-001, -003, -004, -007, -020, §13 |
| OQ-CSS-02 | Akkoord met de voorgestelde defaults (18→20px, ratio 1,2→1,25, 5 omhoog / 2 omlaag, leading 0,5 / 0,25rem, gutter `s-m`, feature `2xl`, `grid_min` 16rem, paar `s-l`)? | **Beantwoord: goedgekeurd** (feature-ruimte heet nu `popout_space`, V8; `leading_body` is door V13 0,25rem; `grid_min` is door V14 30ch) | §3 (Source-kolom) |
| OQ-CSS-03 | Alle sections automatisch, of alleen opt-in? | **Beantwoord: automatisch met opt-out** (`.pdk-off`) en een gedocumenteerde uitweg voor sections van derden; vorm door V10: de section is het grid | §4, AC-CSS-006, -007, -010, -019, D13, D15, D20, R-CSS-02 |
| OQ-CSS-04 | Sync via de interne `\Breakdance\Variables\saveVariables()`? | **Beantwoord: ja**, met guards: `function_exists`, try/catch, een mislukte sync blokkeert opslaan nooit, admin-melding | §1b, §5, §6, AC-CSS-015, R-CSS-01, D16 |

---

## 11. Risico's

| ID | Risico | Mitigatie / verificatie |
|----|--------|-------------------------|
| R-CSS-01 | **Interne Oxygen-API.** `saveVariables()` controleert zijn invoer niet en kan **alle variabelen stil wissen** (`save.php:35-52`); de optievorm en de `unit`-waardevorm zijn niet gedocumenteerd en kunnen in een Oxygen-update veranderen | Bewust gekozen (OQ-CSS-04). De vier stappen uit §5 (encode ≠ false, geen `{}`, simulatie van Oxygens decode, dan pas aanroepen) plus `function_exists` en `catch ( \Throwable )`. De sync draait pas ná het opslaan, dus een fout blokkeert nooit de instellingen of het bestand; de admin krijgt een waarschuwing (AC-CSS-015). Het CSS-bestand definieert alle tokens zelf ook. Live getest op 6.2-rc.1 (AC-CSS-016); na elke Oxygen-update AC-CSS-016 opnieuw draaien |
| R-CSS-02 | **Elke section wordt een grid en een container, met een basis-font-size (V10, V12).** Gevolgen: (a) een section met eigen `display: flex` in Oxygen wint en heeft dan geen banen meer (keuze van de gebruiker); (b) floats werken niet op de kinderen; (c) marges tussen de kinderen klappen niet samen; (d) inline-size-containment laat een section zonder eigen breedte in een flex-rij of als inline-block inklappen; (e) markup van plugins (WooCommerce, formulieren) met `<section>` krijgt ook banen en de basisgrootte; (f) geneste sections zijn opnieuw een grid, met een dubbele gutter; (g) mogelijk effect op `position: fixed` of sticky binnen de section; (h) omdat de layout specificiteit 0 heeft (D-L1), wint een **later** geprinte `*`-reset van een andere plugin er ook van | Geaccepteerd als gekozen gedrag. (a) en (e): `.pdk-off` per section, of een klasse-regel in `custom-style.css` voor sections van derden (§4, ook `font-size: inherit`). (c): de flow gebruikt alleen `margin-block-start`, dus niets verdubbelt (AC-CSS-022). (f): `.pdk-off` of `.pdk-full` op de binnenste section. Geverifieerd in AC-CSS-007 (banen, basisgrootte, opt-out, fixed), AC-CSS-024 (RTL) en AC-CSS-019 (uitweg-snippet); in de moduledoc beschreven |
| R-CSS-03 | **De line-height erft als lengte (V13).** `calc(var(--pdk-lh-…) * 1em + …)` wordt op `h1`–`h6` en `p` berekend tot een vaste lengte, en die lengte erft door. Een kind met een andere `font-size` (een `<small>`, een `<span style="font-size:2em">`, een icoon met eigen grootte) krijgt dus de line-height van de ouder in px, niet een die bij zijn eigen grootte past. Een veel groter kind kan dan buiten de regelbox steken | Geaccepteerd: alleen gezet op `h1`–`h6` en `p` (D7), en inline kinderen zijn meestal kleiner (dan is het effect ruimer, niet afgekapt). **Uitweg:** een klasse op dat kind met een eigen `line-height` (bijvoorbeeld een eenheidloze waarde als `1.2`); elke klasse wint van de kale elementselector. Vermeld in de moduledoc |
| R-CSS-04 | **Baseline-status uit eigen kennis** (§1e), niet live gecontroleerd | Release-check: elke regel controleren op webstatus.dev. Er staat geen feature onder Widely/2023 in de lijst, dus het restrisico is laag |
| R-CSS-05 | **Utopia-formules en de verwachte strings uit eigen kennis** (§1d, AC-CSS-003) | De tester rekent de tabel na op utopia.fyi (400→1440, 18→20, 1,2→1,25) voordat AC-CSS-003 telt. Let op het halve geval `step--2` min 0,78125: wij ronden met PHP `round()` naar `0.7813` |
| R-CSS-06 | **Tokens op twee plekken** (bestand + Oxygen). Een aanpassing in de builder wint op de frontend tot de volgende PDK-save | Bewust (D12); de admintekst zegt "pas tokens hier aan, niet in Oxygen" |
| R-CSS-07 | **Builder-canvas laadt het stylesheet niet** | AC-CSS-017, door de gebruiker handmatig bevestigd (V14). Faalt die na een Oxygen-update, dan volgt eerst onderzoek naar de iframe-hook; de tokens zelf komen via de sync toch in de builder |
| R-CSS-08 | **Eigen Oxygen-breakpoints** worden niet gevolgd (D14) | Min/max zijn instelbaar op de tab. Geaccepteerd |
| R-CSS-09 | **Gelijktijdige save** van builder-variabelen en PDK-sync: de laatste schrijver wint | Kleine kans (alleen beheerders); geaccepteerd. Oxygens design-lock (`design-lock.php:34-41`) bestaat alleen met Oxygen-MCP |
| R-CSS-10 | **Kopstijlen in het `style.css` van een classic theme.** Een thema enqueuet meestal op `wp_enqueue_scripts` 10 ná de plugins. Met Custom CSS aan wordt het framework (als dependency) op de plek van `pdk-custom-style` geprint, dus vóór het thema; een kale `h1{…}` in het thema wint dan | Niet van toepassing met Oxygen: Oxygen vervangt het thema altijd door `oxygen-zero` (`theme-disabler.php:35-42`, `:61`), zonder themastylesheet met kopstijlen en zonder `theme.json`. Daarom is ook de footer-plek van `global-styles-inline-css` met Oxygen actief onschadelijk (live gemeten, §1c): die bevat alleen de `button`-stijl van core (`theme.json:407-435`). Uitweg zonder Oxygen: de waarde in `custom-style.css` zetten. Geaccepteerd; vermeld in de moduledoc |
| R-CSS-11 | **Bestaande sites verschuiven als Modern Normalize aangaat** (V7). De reset haalt alle marges en padding weg, ook buiten tekst (lijsten verliezen hun inspringing, formulieren en eigen markup die op browserdefaults leunen veranderen). Oxygen waarschuwt hier zelf voor (`advanced.php:107`) | Dat is een handeling van de gebruiker; de plugin zet de optie nooit zelf aan (D18). De tab-melding (§4a) en de moduledoc noemen het verschuiven expliciet; advies: eerst op een kopie of staging aanzetten en de belangrijkste pagina's nalopen. Zonder Modern Normalize blijft de flow voor de tekstelementen grotendeels gelijk, maar andere elementen houden hun browsermarges |
| R-CSS-12 | **Flow-marge plus `gap`.** Een Oxygen-container waarop de gebruiker zelf flex/grid met `gap` zet, met tekstelementen direct naast elkaar: `gap` en flow-marge tellen op | `.pdk-flow-off` op die container, of een marge in Oxygen (klasse wint). `.pdk-grid` is al uitgezonderd. Geverifieerd in AC-CSS-022 |
| R-CSS-13 | **Default normalize houdt de `h1`-marge** (D-L2). Met Modern Normalize uit wint `h1{margin:.67em 0}` (0,0,1) van de flow-reset (0); een `h1` heeft dan `.67em` boven en onder | Geaccepteerd: V7 neemt Modern Normalize aan, en de tab waarschuwt als die uit staat (AC-CSS-023). AC-CSS-020 controleert in die run alleen font-size en line-height. Uitweg zonder Modern Normalize: `h1 { margin-block: 0 }` in `custom-style.css` |
| R-CSS-14 | **De section meet zichzelf niet (V12).** De basis-font-size (en dus de `ch`-maat) en de gutter/popout-sporen van een section rekenen hun `cqi` uit tegen de dichtstbijzijnde container **boven** de section: de small viewport op topniveau, de buitenste section bij nesting. Een smalle geneste section in een brede buitenste section krijgt dus de grootte die bij de buitenste breedte hoort, niet bij zijn eigen breedte. Losse tekst en `p`/koppen **in** de section meten wel op de section zelf, dus die twee kunnen in een geneste section licht verschillen | Geaccepteerd: CSS laat een element zichzelf niet bevragen, en een extra wrapper zou de markup veranderen. Op topniveau is het effect nul (viewport ≈ sectionbreedte; bij de defaults is de grootte tussen 400 en 1440px identiek). Wie het anders wil, zet een eigen font-size op de section (een klasse wint, D25). AC-CSS-007 meet de maat met een `1ch`-probe binnen de section, dus de test volgt precies wat de sporen gebruiken. In de moduledoc beschreven |
| R-CSS-15 | **Verwarring tussen gridlijn en klasse.** `full-start` is een lijnnaam, `.pdk-full-start` een klasse; wie zelf `grid-column: full-start` schrijft, krijgt alleen de lijn (één rand), niet de baan | Benoemd in §4 en in de moduledoc ("lijn `full-start`" vs. "klasse `.pdk-full-start`"). Geaccepteerd: de namen volgen bewust het patroon van Ryan Mulligan |
| R-CSS-16 | **Inktmaat verschilt per font (V13).** De ondergrens "regelbox ≥ inkt" gaat uit van ascent + descent ≈ 1,17em. Een font met lange ascenders/descenders (scriptfonts, sommige displayfonts) kan met ratio 1,1 alsnog net buiten de regelbox komen bij grote koppen | De ratio's zijn instelbaar (`lh_heading_ratio` tot 2.0), en de help-tekst noemt het (§3a). AC-CSS-020 controleert met het standaardfont dat `h1` > 1,15 × font-size blijft. Geaccepteerd; in de moduledoc staat het advies om de ratio te verhogen bij zulke fonts |
| R-CSS-17 | **`grid_min` in ch buiten een section (V14).** `ch` wordt opgelost tegen de font-size van het `.pdk-grid`-element. Buiten een section is dat de geërfde (niet-fluid) letter, dus de kaartbreedte schaalt daar niet mee. Een font met een brede of smalle `0` geeft ook een andere kaartbreedte dan dezelfde waarde in rem | Geaccepteerd: in een section (het normale gebruik) schaalt hij wel mee, en per grid is hij te overschrijven met `style="--pdk-grid-min: 20ch"` of een klasse. AC-CSS-007 meet het aantal kolommen met een `1ch`-probe in dezelfde context, dus de test hangt niet van het font af. In de moduledoc beschreven |
| R-CSS-18 | **Veldteksten lopen uiteen** (tab, schema, documentatie) als er later een tweede kopie ontstaat | D29: één bron `fields()`. AC-CSS-025 vergelijkt schema-`description`, `get`-uitvoer en tab-HTML met `fields()`, zodat een afwijkende kopie de zelftest laat falen. De moduledoc neemt de tabel uit §3a over |

---

## 12. Won't Have (deze release)

- Spacing-utilityklassen, typografie-utilities voor andere elementen dan `h1`–`h6`/`p` en de section, en een aparte `.flow`/stack-klasse: de automatische flow-regel dekt tekst (D7, D13, D17; bevestigd door V9).
- Een eenheidloze line-height op de basisregels (die zou wel per kind meeschalen, maar mist de constante die kleine letters lucht geeft; V13 kiest bewust voor ratio + constante, met de klasse-uitweg uit R-CSS-03).
- Vertalingen van de veldteksten naar andere talen dan Nederlands in deze release (de teksten staan wel in `__()`, dus vertalen kan later).
- Een eigen normalize, of de Oxygen-optie Modern Normalize automatisch aanzetten (D18).
- Cascade layers (D-L1), een `vi`-terugvallaag (V2), `@media`-breakpoints in het framework, `round()`, `@property`, style queries, subgrid (§1e).
- Een `section > div`-max-width-regel, `.pdk-breakout`, `.pdk-feature*`, `.pdk-content-start`/`-end` en `.pdk-wide` (V10, D20–D22).
- Klassen of eigenschappen met `left`/`right`/`width` (V11, D24).
- `min-inline-size: 0` op de kinderen van de section (D23).
- Een extra wrapper of container om de section, zodat de section zijn eigen breedte zou kunnen bevragen (R-CSS-14).
- Vrije CSS of vrije tokennamen via de tab of MCP (veiligheidsgrens, §6).
- Bidirectionele sync, verwijderen of melden van verouderde Oxygen-variabelen, of opruimen bij module uit (D12).
- `min_px`/`max_px` in de MCP-uitvoer (D9).
- Een instelbare lijst met uitgezonderde selectors voor sections van derden: een klasse-regel in `custom-style.css` dekt dat (OQ-CSS-03).
- Een migratie van `feature_space` naar `popout_space` of van `grid_min` in rem naar ch (nooit uitgebracht, D-L3, D28).
- Eigen Oxygen-breakpoints volgen (D14); een kleur- of fontfamiliesysteem.
- CI, een release publiceren.

---

## 13. Gereed wanneer

Een beheerder op dev.local zet de module aan, ziet op de tab per groep elk veld met label, eenheid en uitleg
(AC-CSS-025), past de schaal aan, en ziet op een pagina dat koppen, `p` en losse tekst in een section fluid schalen
tussen 400 en 1440 en de container volgen, met een line-height die nooit kleiner is dan de inkt (`h1` 53,2694 /
71,1387px, `p` 27,4 / 30px), ook boven Oxygens normalize, terwijl `custom-style.css` blijft winnen (AC-CSS-007,
AC-CSS-020). Met Modern Normalize aan krijgt tekst een fluid verticale ritmiek uit de ruimte-tokens, en verschijnt er
een waarschuwing zodra Modern Normalize uit staat (AC-CSS-022, AC-CSS-023). Elke section is een grid: een gewone
`section > div` staat op de wide-baan (maximaal 90rem, gecentreerd, met gutter), en kinderen kunnen naar de
content-baan (65ch, meegroeiend met de tekst), de popout- of de full-baan, ook eenzijdig naar de start- of eindkant, en
dat klopt in LTR én RTL (AC-CSS-024). Er is een `.pdk-off`-opt-out en een uitweg-snippet (AC-CSS-019), en het
auto-grid werkt zonder `@media`, met een kaartbreedte van 30ch die meegroeit met de tekst. Na een plugin-update wordt
het bestand vanzelf opnieuw gegenereerd (AC-CSS-008). Met Oxygen geactiveerd staan dezelfde tokens als variabelen in de
builder en laadt het canvas de klassen (AC-CSS-016, AC-CSS-017), en een AI-agent kan via MCP de config lezen en
wijzigen, met dezelfde veldbeschrijvingen als de beheerder (AC-CSS-013, AC-CSS-025). `php tests/run.php` is groen
voor AC-CSS-001 t/m -006, -008, -011, -012, -014, -015, -021, -025, en de H-helft van -009 en -023.
Externe afhankelijkheden: Oxygen 6.2-rc.1 op dev.local (lokaal, geen account) en de Abilities API van WordPress.
Geen enkele AC vereist een externe dienst.

**Demo-pagina (`tests/live/css-framework-demo.php`, V14):** de demo leidt zijn verwachtingen **live in JavaScript af uit
de tokens op `:root`**. Hij leest `--pdk-step-*` en `--pdk-space-*` (parseert de `clamp()`-strings), `--pdk-lh-*`,
`--pdk-leading-*`, `--pdk-measure`, `--pdk-site-width` en `--pdk-grid-min`, en vergelijkt de gemeten waarden daarmee.
Na een wijziging op de tab klopt de demo dus vanzelf met de nieuwe instellingen, zonder dat de verwachte getallen in de
demo hoeven te worden aangepast. Dit is een hulpmiddel voor de handmatige controle en geen framework-AC.
