# CSS Framework

Fluid typografie en ruimte in de stijl van [Utopia](https://utopia.fyi) (`clamp()` met `cqi`), basisstijlen voor koppen en `p`, verticale ritmiek tussen tekstelementen en een layout waarin de `section` zelf het grid is (banen full / wide / popout / content, naar het patroon van [Ryan Mulligan](https://ryanmulligan.dev/blog/layout-breakouts/)). Alles komt uit één gegenereerd bestand, `uploads/pdk-theme-options/css-framework.css`, en de tokens worden — met Oxygen 6 actief — gesynchroniseerd naar Oxygen-variabelen. Een AI-agent kan de configuratie lezen en wijzigen via twee abilities.

Normatief ontwerp: `docs/css-framework-brief.md`.

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `css_framework` (`includes/class-pdk-plugin.php`, `$module_map`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php`, `get_defaults()`) |
| Eigen tab | Ja — "CSS Framework", alleen als de module aan staat (`includes/class-pdk-admin.php`, `MODULE_TABS`, `render_tab_content()`) |
| Vereist | Niets. Zonder Oxygen werkt alleen het bestand; de Oxygen-sync wordt dan overgeslagen. De module staat bewust **niet** in `PDK_Settings::OXYGEN6_MODULES` |

## Instellingen

Opgeslagen in `pdk_theme_options['css_framework']`. Elke waarde is een getal binnen een bereik of een naam uit een vaste lijst; er komt nooit vrije tekst in de CSS. Eén ongeldig veld = er wordt **niets** opgeslagen, de gebruiker krijgt een foutmelding met de veldnaam en het oude bestand blijft staan (`PDK_CSS_Framework::validate()`, `::save()`).

| Sleutel | Type | Standaard | Bereik |
|---|---|---|---|
| `min_viewport` / `max_viewport` | int px | 400 / 1440 | 200–2000 (kleiner dan max) / 200–3840 |
| `font_min` / `font_max` | float px | 18 / 20 | 8–64 |
| `ratio_min` / `ratio_max` | float | 1.2 / 1.25 | 1–2 |
| `steps_up` / `steps_down` | int | 5 / 2 | 0–8 / 0–4 |
| `leading_body` / `leading_heading` | float rem | 0.25 / 0.25 | 0–2 (vaste toeslag bovenop de verhouding) |
| `lh_heading_ratio` / `lh_body_ratio` | float | 1.1 / 1.3 | 1–2, hoogstens 2 decimalen (regelhoogte-verhouding voor koppen / `p`) |
| `site_width` | float rem | 90 | 20–240 (breedte van de wide-baan; 90rem = 1440px bij een root van 16px) |
| `measure` | float ch | 65 | 20–120, hoogstens 2 decimalen |
| `gutter` | ruimte-token | `s-m` | een stap, one-up-paar of eigen paar |
| `popout_space` | ruimte-token | `2xl` | idem (hernoemd van `feature_space`; de oude sleutel valt als onbekend weg) |
| `grid_min` | float **ch** | 30 | 10–80, hoogstens 2 decimalen (minimale kaartbreedte van `.pdk-grid`; token `--pdk-grid-min: 30ch`) |
| `space_pairs` | lijst `a-b` | `['s-l']` | max. 10, a vóór b in 3xs…3xl; one-up-paren en dubbelen vallen stil weg |
| `flow_enabled` | bool | `true` | `true`/`false`/`1`/`0`/`'1'`/`'0'`; een ontbrekend vinkje is `false` |
| `flow_space` / `flow_heading_space` | ruimte-token | `s` / `l` | idem `gutter` |

**Uitleg per veld.** Label, eenheid, helptekst (met voorbeeld) en groep van elk veld staan op één plek: `PDK_CSS_Framework::fields()` (`modules/css-framework/class-pdk-css-framework.php`). De tab toont de velden per groep (Viewport, Typografie, Regelhoogte, Ruimte, Layout, Flow, Grid), de `description` van elke eigenschap in het schema van `update-css-framework` is dezelfde helptekst en `get-css-framework` geeft de lijst terug als `fields`. De tabel hierboven geeft alleen type, standaard en bereik; de teksten staan bewust niet nog een keer in dit document.

**Let op `grid_min`:** sinds 2.13.0 (nog niet uitgebracht) in `ch` in plaats van `rem`. Een eerder opgeslagen waarde (bijvoorbeeld 16) is nog steeds een geldig getal in 10–80 en blijft staan, maar wordt nu als `ch` gelezen; controleer de waarde op de tab.

Validatieregels: float-velden eerst `is_numeric()`, dan `(float)`, dan `is_finite()`, dan bereik (`'1e999'`, `'NAN'`, `''`, arrays worden geweigerd). Int-velden alleen een int of een string die helemaal `/^-?\d+$/D` matcht (`400.5` wordt geweigerd, niet afgerond). De `/D` is nodig: zonder staat `$` een afsluitende newline toe (`"s-l\n"`).

`config()` valideert ook de **opgeslagen** optie per veld: een oude of corrupte waarde (bijvoorbeeld een bereik dat later is aangepast) valt terug op de default van dat veld, geldige velden blijven. De CSS kan zo nooit rommel of een `TypeError` opleveren.

## Tokens

`PDK_CSS_Framework::tokens()` is de ene bron voor het bestand, de tab, de MCP-uitvoer en de Oxygen-sync: een lijst `['name' => '--pdk-step-0', 'value' => 'clamp(...)']`.

Utopia-wiskunde, 1rem = 16px:

- Typestap *n*: `min = font_min × ratio_min^n`, `max = font_max × ratio_max^n`.
- Ruimtestap: `min = round(font_min × m)`, `max = round(font_max × m)` met `m` = 3xs 0,25 · 2xs 0,5 · xs 0,75 · s 1 · m 1,5 · l 2 · xl 3 · 2xl 4 · 3xl 6. Paar `a-b`: `min(a)` tot `max(b)`.
- `slope = (max − min) / (max_viewport − min_viewport)`; waarde `clamp(lo rem, intercept rem ± slope×100 cqi, hi rem)` met `lo`/`hi` = de kleinste/grootste van min en max (zodat een dalende stap een geldige `clamp()` blijft).
- Notatie: 4 decimalen met PHP `round()`, nullen achteraan weg, nooit `-0`, de helling als `+ x` of `- x`, locale-onafhankelijk (`number_format` met expliciete scheidingstekens).

Bij de defaults is `--pdk-step-0` `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)`. De vaste tokens zijn `--pdk-site-width`, `--pdk-measure`, `--pdk-gutter`, `--pdk-popout-space`, `--pdk-grid-min`, `--pdk-leading-body`, `--pdk-leading-heading`, `--pdk-lh-heading`, `--pdk-lh-body`, `--pdk-flow-space` en `--pdk-flow-heading-space`. De `--pdk-bo-*`-variabelen horen bij de section en staan niet op `:root`.

`cqi` zonder container-voorouder valt terug op de small viewport (CSS Containment 3), dus er is geen aparte `vi`-laag. Er staat geen `@media` in het bestand.

## Het bestand

`css-framework.css` heeft **geen** `@layer` en alleen logische eigenschappen (`margin-block`, `inline-size`, `grid-column` met benoemde lijnen). Vier blokken, in vaste volgorde (`::generate()`):

1. tokens op `:root`;
2. basisregels op kale elementselectors: `h1`…`h6` krijgen `step-5`…`step-0` (afgeleid van `steps_up`), `p` `step-0`, met `line-height: calc(verhouding * 1em + leading)` (zie "Regelhoogte");
3. de flow-regels (alleen met `flow_enabled`);
4. de layout, alles in `:where()` en dus specificiteit 0.

Waarom geen lagen: Oxygens Modern Normalize zet `*{margin:0;padding:0}` unlayered. Een regel in een cascade layer verliest daar altijd van, wat de specificiteit ook is; `margin-inline: auto` en de gutter gingen verloren. Alles staat daarom unlayered en wint van de normalize door de volgorde: het bestand wordt later geprint.

### De section is het grid

Elke `section` zonder `.pdk-off` en zonder `hidden`-attribuut is een grid met zeven sporen en vier banen (`full`, `wide`, `popout`, `content`), met `container-type: inline-size` en `font-size: var(--pdk-step-0)`. Een direct kind staat standaard op `wide` (= `site_width`, gecentreerd, minimaal `gutter` vrij aan elke kant). Op een smal scherm krimpen popout en wide naar 0; de full-sporen houden minimaal `gutter`.

De sporen zijn begrensd door `site_width`: `content = min(measure, site_width, 100% − 2 × gutter)`, `popout = minmax(0, min(popout_space, max(0, (site_width − measure) / 2)))` en `wide = minmax(0, max(0, (site_width − measure) / 2 − popout_space))`. Zo is content + 2 × popout + 2 × wide nooit breder dan `site_width`: is `site_width` kleiner dan `measure`, dan is content gelijk aan `site_width` en zijn popout en wide 0; ligt hij tussen `measure` en `measure + 2 × popout_space`, dan krijgt popout het restant en is wide 0.

- **`hidden`:** een `section[hidden]` blijft verborgen. Auteurs-`display: grid` wint anders van de `display: none` van de browser (`[hidden]`), dus de selector sluit `[hidden]` uit (specificiteit blijft 0).
- **`.pdk-grid` op een section:** de auto-fit-kolommen vervangen dan de benoemde sporen, dus de baanregels (`> *` op `wide` en de baanklassen) gelden niet op `section.pdk-grid`. Baanklassen doen binnen een `.pdk-grid`-section niets; de kinderen worden gewoon kaarten.

| Klasse (op een direct kind) | Baan |
|---|---|
| (geen) | `wide` |
| `.pdk-content` | de tekstkolom, `min(measure, 100% − 2 × gutter)` |
| `.pdk-popout` | content plus `popout_space` aan elke kant |
| `.pdk-full` | rand tot rand |
| `.pdk-popout-start` / `.pdk-popout-end` | popout aan één kant, de andere kant lijnt uit op content |
| `.pdk-wide-start` / `.pdk-wide-end` | wide aan één kant, de andere kant op content |
| `.pdk-full-start` / `.pdk-full-end` | full aan één kant, de andere kant op wide |

`-start` en `-end` zijn logisch: gridlijnen volgen `direction`, dus in RTL draaien ze om (live gemeten in AC-CSS-024). Er is bewust geen `.pdk-content-start`/`-end` en geen `.pdk-wide`.

Overig: `.pdk-container` (losse container voor `cqi` buiten sections, alleen `container-type`), `.pdk-grid` (auto-fit grid met `--pdk-grid-min` in `ch`, zonder `@media`), `.pdk-flow-off` (geen flow-marges tussen de kinderen) en `.pdk-off`.

### Regelhoogte

Koppen: `line-height: calc(var(--pdk-lh-heading) * 1em + var(--pdk-leading-heading))`; `p`: `calc(var(--pdk-lh-body) * 1em + var(--pdk-leading-body))`. De verhouding (standaard 1,1 en 1,3) schaalt mee met de fluid `font-size`; de leading (rem) is een kleine vaste toeslag. Voorbeeld bij de defaults: h1 53,27px @400 en 71,14px @1600, `p` 27,4px / 30px.

Waarom: de eerste versie gebruikte `calc(1em + 0.25rem)`, bij een h1 van 61px slechts 1,07 × de lettergrootte. Letterstaarten (descenders) en -koppen raakten dan de volgende regel ("ink guard"). Een verhouding van minstens 1,1 houdt de inkt van opeenvolgende regels uit elkaar.

Let op de overerving: `calc()` met `em` wordt als **lengte** geërfd. Een kind met een andere `font-size` binnen een `p` of kop (bijvoorbeeld een `small` of `span`) krijgt dus de regelhoogte in px van de ouder, niet de verhouding. Geef zo'n element een klasse met een eigen `line-height`.

### Basisgrootte van de section en `measure`

`65ch` in `grid-template-columns` wordt opgelost tegen de `font-size` van de section zelf. Daarom zet het framework `font-size: var(--pdk-step-0)` op de section (V12): `measure` volgt zo de fluid `p`-grootte en niet de geërfde letter van `body`. Voor een eigen basisgrootte zet je `--pdk-measure` of de font-size van die ene section zelf.

### Nesten

Een `section` binnen een section staat op `wide` en is zelf weer een grid; zijn full-sporen houden opnieuw minimaal een gutter, dus er staan aan elke kant **twee gutters**. Bewust. Wie dat niet wil zet `.pdk-off` op de binnenste section of `.pdk-full` op de binnenste section zelf. De gutter van een section op topniveau wordt tegen de viewport gemeten (de section kan zichzelf niet bevragen), die van een geneste section tegen de buitenste.

### Opt-out en uitwegen

- `.pdk-off` op een section: geen grid en geen `container-type`; de flow-regel geldt wel.
- Een eigen `display` in Oxygen (een klasse-selector) wint van het framework; de banen werken dan niet meer, `container-type` blijft.
- **Sections van derden** (WooCommerce, formulieren) waar je geen klasse kunt toevoegen: elke selector met een klasse wint van specificiteit 0. Zet in Custom CSS:

```css
.woocommerce section { display: block; container-type: normal; font-size: inherit; }
```

`font-size: inherit` haalt ook de basisgrootte van de section weer weg (V12).

- Floats werken niet op grid-items en marges tussen de kinderen van de section klappen niet samen; de flow gebruikt alleen `margin-block-start`, dus er wordt niets verdubbeld.

## Flow-regel en Modern Normalize

Het framework **gaat ervan uit dat Oxygens Modern Normalize aan staat** (`modern_normalize_enabled`, Oxygen → Settings → Advanced). Die reset zet elke marge op 0; de flow-regel bouwt daarop. De plugin levert geen eigen normalize en zet de Oxygen-optie nooit zelf aan. Staat Oxygen 6 aan en Modern Normalize uit, dan toont de tab een `notice-warning` (`PDK_CSS_Framework::modern_normalize_state()`, `::render_inline()`); de check is alleen-lezen en vangt elke `\Throwable`, want `get_bool_setting()` gooit een `InvalidArgumentException` als de definitie geen bool is. **Let op:** Modern Normalize aanzetten op een bestaande site verschuift alles wat op browsermarges leunt (lijsten, formulieren, eigen markup). Test eerst op een kopie.

De vier flow-regels (alleen met `flow_enabled`), allemaal `:where()`:

| Regel | Effect |
|---|---|
| `:where(T)` | `margin-block: 0` |
| `:where(T) + :where(T)` | `margin-block-start: var(--pdk-flow-space)` |
| `:where(T) + :where(h2, h3, h4)` | `margin-block-start: var(--pdk-flow-heading-space)` |
| `:where(.pdk-grid, .pdk-flow-off) > *` | `margin-block: 0` |

`T` = `h1, h2, h3, h4, h5, h6, p, ul, ol, blockquote, figure, pre, table`. Met de default normalize (Modern Normalize uit) blijft `h1{margin:.67em 0}` (0,0,1) winnen van de flow-reset (specificiteit 0); dat is geaccepteerd.

## Laadvolgorde en overschrijven

`PDK_CSS_Framework::register()` registreert de handle `pdk-css-framework` op `wp_enqueue_scripts` prioriteit **9**, `enqueue()` zet hem op prioriteit **20** in de wachtrij (na core's global styles op 10). `PDK_Custom_CSS::enqueue()` (`modules/custom-css/class-pdk-custom-css.php`) geeft `pdk-custom-style` de dependency `['pdk-css-framework']` zodra die handle geregistreerd is, anders `[]`. Eigen CSS wint dus altijd van het framework. Volgorde in de `<head>`: normalize (alleen Oxygen), `css-framework.css`, `custom-style.css`; Oxygens eigen CSS komt op `wp_head` 1000000 en wint van alles met dezelfde specificiteit. **Met Oxygen actief** print WordPress `global-styles-inline-css` in de footer, dus ná het framework. Dat is onschadelijk: Oxygens thema `oxygen-zero` heeft geen theme.json (`plugin/themeless/theme-disabler/theme-disabler.php:35-42,61`) en de `theme.json` van WordPress zelf stijlt alleen `button`. Zonder Oxygen staan de global styles op prioriteit 10, vóór het framework.

**Risico R-CSS-10:** met Custom CSS aan wordt het framework (als dependency) op de plek van `custom-style.css` geprint. Een kale elementregel in het `style.css` van een classic theme (dat meestal op prioriteit 10 ná de plugins enqueuet) kan dan van de basisregels winnen. Uitweg: de waarde in `custom-style.css` zetten.

Het stylesheet wordt alleen geregistreerd als het bestand bestaat, zodat `custom-style.css` nooit op een ontbrekende dependency wacht (WordPress slaat dan het hele stylesheet over).

## Regenereren

Het bestand wordt **direct** geschreven (`PDK_CSS_Framework::write_file()`, na `pdk_ensure_storage_dir()`), niet via `pdk_write_storage_file()`: de inhoud komt volledig uit gevalideerde getallen, dus geen code-cap, geen `.bak` en geen hash; het staat niet in `pdk_watched_files()`. Opnieuw gegenereerd wordt het:

- bij elke geslaagde save (admin of MCP);
- bij de registratie als het bestand ontbreekt (na een geslaagde regeneratie volgt ook de Oxygen-sync, zodat de variabelen een plugin-update meemaken; nooit fataal en zonder uitvoer op de frontend);
- bij de registratie als de optie `pdk_css_framework_built` niet gelijk is aan `PDK_PLUGIN_VERSION` (D-P2). Zo komt gewijzigde generator-uitvoer na een plugin-update vanzelf op de site, zonder dat iemand hoeft op te slaan. De optie wordt pas gezet als het schrijven lukte; bij een gelijke versie en een bestaand bestand gebeurt er geen schrijfactie.

Kan het bestand niet worden geschreven (uploads niet schrijfbaar), dan zet `register()` de transient `pdk_css_framework_write_failed` (10 minuten) en probeert het niet bij elk request opnieuw; een geslaagde save wist de transient. `update-css-framework` geeft dan `WP_Error 'pdk_file_write_failed'` (status 500) in plaats van `pdk_invalid_config`.

Bij verwijderen van de plugin blijft het bestand in uploads staan, net als de andere klantbestanden; `uninstall.php` verwijdert wel de optie `pdk_css_framework_built` (en, zoals altijd, `pdk_theme_options`).

## MCP-abilities

Alleen geregistreerd als `class_exists( 'PDK_CSS_Framework' )`, dus de modules CSS Framework **en** AI-agent toegang moeten aan staan (`modules/agent-abilities/class-pdk-agent-abilities.php`, `register_css_framework_abilities()`). Beide eisen `manage_options` — instellingen, geen code, dus niet de code-cap; dat is verantwoord omdat er alleen getallen en vaste namen in de CSS komen. Beide hebben `show_in_rest` en `mcp.public`.

| Ability | Input | Output |
|---|---|---|
| `pdk-theme-options/get-css-framework` | geen (`additionalProperties: false`) | `config`, `tokens` (`name`, `value`), `classes` (de 13), `css_url`, `oxygen` (`active`, `modern_normalize` of `null`). Readonly, idempotent |
| `pdk-theme-options/update-css-framework` | `{config: {…}}`, getypeerd schema met `additionalProperties: false` op beide niveaus; ontbrekende sleutels houden hun waarde | `saved`, `config`, `tokens`, `oxygen` (`status`, `message?`) of `WP_Error 'pdk_invalid_config'` met `['errors' => veld => melding]`. **Destructief** (overschrijft Oxygen-variabelen), idempotent |

Het schema is de eerste poort; de servervalidatie van de admin (`validate()`) draait daarna altijd. `get-site-info` noemt het bestand in `other_output.css_framework`.

## Oxygen-sync

Na elke geslaagde save (admin of MCP), pas nadat de instellingen en het bestand zijn opgeslagen, roept `save()` `PDK_CSS_Framework::sync_oxygen()` aan. Eenrichtingsverkeer: PDK is de bron; een in de builder aangepaste waarde wint op de frontend tot de volgende PDK-save. Pas tokens dus hier aan, niet in Oxygen.

Oxygen heeft **geen publieke PHP-API** voor het schrijven van variabelen. De sync gebruikt de interne `\Breakdance\Variables\saveVariables()`:

1. Alleen als `pdk_oxygen6_active()` en `saveVariables`, `get_global_option` en `global_option_exists` bestaan; anders status `skipped` ("Oxygen 6 niet actief — alleen het CSS-bestand bijgewerkt").
2. Lezen van de **ruwe** optie `variables_json_string` (en `variables_collections_json_string`) met `get_global_option()`, nooit de gefilterde `getVariables()` (dan zouden variabelen van andere filters in de database belanden). Een oud formaat (JSON-string) wordt gedecodeerd. Bestaat de optie maar geeft ze geen array, dan status `error` en wordt er niets geschreven.
3. `merge_oxygen_variables()` (puur, testbaar zonder Oxygen): van een bestaande `pdk-*` variabele (match op `cssVariableName`) worden alleen `type` en `value` bijgewerkt; `id`, `label`, collectie en extra sleutels blijven staan, en een bestaande variabele zonder `id` krijgt `wp_generate_uuid4()`. Nieuwe tokens komen aan het eind (`id = wp_generate_uuid4()`, naam zonder voorloop-`--`, `type unit`, `value {number, unit: custom, style}`, label `PDK step 0`, collectie `PDK Framework`). Vreemde variabelen blijven ongemoeid en er wordt **nooit** iets verwijderd (ook niet een verouderde `pdk-step-5` na `steps_up` 3: dat zou `{var-<id>}`-verwijzingen breken). `changed` is alleen waar als een waarde of id echt verschilt.
4. Is er niets veranderd, dan status `unchanged` en wordt `saveVariables()` niet aangeroepen.

### Risico en bescherming tegen stil wissen

`saveVariables()` controleert zijn invoer niet: het doet `str_replace( '{}', 'null', … )`, `json_decode` en schrijft het resultaat zonder controle, dus een kapotte payload wist **alle** variabelen zonder exceptie. Daarom vóór de aanroep, in een `try` met `catch ( \Throwable )`:

1. `wp_json_encode()` mag niet `false` geven. NB: de echte `wp_json_encode()` saneert ongeldige UTF-8 zelf en geeft dan geen `false`; de guard blijft als vangnet (en omdat een kapotte string zo stil wordt "gerepareerd").
2. De payload bevat nooit `{}` (ook niet letterlijk in een label). Reden: `saveVariables()` vervangt **elke** `{}` in de payload door `null`, ook in de teksten van andere variabelen; doorschrijven zou die beschadigen, dus de sync stopt met status `error`. **Risico:** lege JSON-objecten in andere Oxygen-variabelen worden na decode en encode `[]` en leiden zo (via de decode als array) tot deze fout; de sync schrijft dan niets.
3. Controleer de decode: `json_decode( $payload, true )` (zonder `{}` verandert Oxygens `str_replace` niets) moet een array zijn met `variables` en `collections`, allebei arrays.
4. Pas dan `saveVariables( $payload )`.

Een fout geeft status `error` met de tekst; de instellingen en het bestand blijven opgeslagen. In de admin volgt een redirect met `saved=1&warning=…` en `render_notices()` toont een `notice-warning` ("Instellingen opgeslagen, Oxygen-sync mislukt: …"). Het restrisico (een interne API die in een Oxygen-update kan veranderen) is bewust genomen; draai na elke Oxygen-update de live-check opnieuw (`tests/live/css-framework-slice2.mjs`). Het CSS-bestand definieert alle tokens zelf ook, dus de frontend blijft werken.

Module uit: de variabelen in Oxygen blijven staan; alleen de klassen, basisregels en flow verdwijnen.

## Voor themaontwikkelaars

- `PDK_CSS_Framework::config()` — de volledige, gevalideerde configuratie.
- `PDK_CSS_Framework::tokens( $config )` — de tokenlijst (`name`, `value`; fluid tokens hebben ook `min`/`max` in px).
- `PDK_CSS_Framework::save( $input )` — valideren, schrijven, opslaan en synchroniseren; geeft `saved`, `config`, `errors` en `oxygen`.
- Per element: `style="--pdk-grid-min: 24ch"` of `style="--pdk-measure: 50ch"` (op de section).

## Wat de module niet doet

Geen spacing-utilityklassen (`.mt-l`), geen eigen normalize, geen `@media`-breakpoints, geen vrije CSS of tokennamen via tab of MCP, en geen eigen Oxygen-breakpoints: min en max viewport zijn instelbaar op de tab.
