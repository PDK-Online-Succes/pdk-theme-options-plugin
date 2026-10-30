# IMGX — WebP/AVIF sidecars en LQIP-placeholder

IMGX zet naast elke bestaande JPEG en PNG een WebP- en/of AVIF-versie neer (een "sidecar":
`photo.jpg` blijft staan, `photo.webp` komt ernaast) en serveert die op de frontend uit door elke
`<img>` in een `<picture>` met `<source>`-elementen te wikkelen. Het origineel wordt nooit
gewijzigd, verplaatst of verwijderd, en een variant wordt alleen aangeboden als IMGX het bestand
zelf heeft geschreven en in zijn registratie heeft staan. De module is de originele losse
IMGX-plugin, ongewijzigd overgenomen in `modules/imgx/includes/` onder de namespace `\IMGX\`
(`modules/imgx/class-pdk-imgx.php:3-13`). Sinds 2.12.0 kan de module daarnaast per afbeeldingsmaat
een LQIP-placeholder (`photo.lqip.jpg`) maken, zie "LQIP-placeholder" hieronder.

## Aan- en uitzetten

Modulesleutel `imgx`, standaard **uit** (`includes/class-pdk-settings.php:208-210`). Geladen via
`$module_map` (`includes/class-pdk-plugin.php:29`) met een eigen tab "IMGX"
(`includes/class-pdk-admin.php:746`); die tab rendert IMGX' eigen formulier inline via
`PDK_ImgX::render_inline()` (`:813-817`, `class-pdk-imgx.php:71-77`). Geen afhankelijkheden op
andere modules.

Wel één harde voorwaarde op de server: er moet een `WP_Image_Editor` (GD of Imagick) zijn die het
gekozen formaat écht kan schrijven. Kan hij dat niet, dan genereert IMGX niets en blijft de
frontend ongemoeid (`includes/class-capabilities.php:12-18`). Voor de LQIP geldt alleen dat de
editor JPEG moet kunnen schrijven (`wp_image_editor_supports( mime_type image/jpeg )`), dus die
werkt ook op een server zonder WebP/AVIF.

Draait de losse IMGX-plugin nog naast deze plugin, dan doet de module niets — te herkennen aan de
constante `IMGX_MIN_WP`, anders zou elke hook dubbel vuren (`class-pdk-imgx.php:51-60`).

## Instellingen

IMGX slaat zijn instellingen **niet** op in `pdk_theme_options` maar in een eigen optie
`imgx_settings`, via de WordPress Settings API. Reden: dat scheelt het herschrijven van de
complete registratie, en de opties overleven een import/export los van de rest
(`class-pdk-imgx.php:9-12`, `includes/class-settings.php:20-25`). In `pdk_theme_options` staat
alleen `imgx.enabled`.

| Sleutel | Type | Standaard | Betekenis |
| --- | --- | --- | --- |
| `mode` | `avif_webp` \| `avif` \| `webp` | `avif_webp` | Welke formaten gegenereerd worden. AVIF heeft voorrang boven WebP. |
| `webp_quality` | int 1-100 | `82` | Encoderkwaliteit voor WebP. |
| `avif_quality` | int 1-100 | `50` | Encoderkwaliteit voor AVIF. AVIF ligt niet op dezelfde perceptuele schaal: 45-60 ≈ JPEG 80-85. |
| `picture_enabled` | 0/1 | `1` | `<picture>`-uitvoer op de frontend. |
| `whole_page` | 0/1 | `0` | Hele pagina door een outputbuffer halen in plaats van alleen `the_content`. |
| `lqip_enabled` | 0/1 | `0` | LQIP-placeholder: per maat een `.lqip.jpg` genereren en als `background-image` op de `<img>` zetten. Los van `picture_enabled` en van de WebP/AVIF-formaten. |
| `generate_on_upload` | 0/1 | `1` | Varianten maken bij nieuwe uploads. |
| `upload_mode` | `async` \| `sync` | `async` | Genereren via cron (15 s later) of direct tijdens de upload. |
| `debug` | 0/1 | `0` | Uitgebreid loggen. Werkt alleen als ook `WP_DEBUG` aan staat. |

Bron: `includes/class-settings.php:54-65`. Sanering en clamping in `sanitize()` (`:146-187`);
onbekende modi vallen terug op de standaard. De AVIF-/WebP-kwaliteit en de modus vormen samen de
`encoding_signature()` (`:198-207`) — die vingerafdruk wordt bij de registratie opgeslagen zodat
een eerder overgeslagen variant opnieuw geprobeerd wordt als je de kwaliteit verlaagt. Met
`lqip_enabled = 1` krijgt de vingerafdruk het extra deel `lqip` (`avif_webp-82-50-lqip`); met LQIP uit
is hij byte-gelijk aan die van vóór 2.12.0.

## Hoe het werkt

### Rolverdeling tussen de klassen

`class-pdk-imgx.php` is de lijm: constanten, een autoloader die `\IMGX\Picture_Renderer` op
`includes/class-picture-renderer.php` afbeeldt, en het opstarten op `plugins_loaded`
(`:22-49`). `\IMGX\Plugin` is de composition root: hij bouwt alle services in de constructor en
registreert hun hooks in `boot()` (`includes/class-plugin.php:103-154`).

| Klasse | Rol |
| --- | --- |
| `Settings` | Getypeerde toegang tot `imgx_settings` + Settings API-registratie. Bepaalt welke formaten gevraagd én ondersteund zijn (`class-settings.php:216-252`). |
| `Capabilities` | Probeert écht een 16×16 testafbeelding te coderen en onthoudt de uitkomst in `imgx_capabilities`. Vertrouwt geen extensienamen (`class-capabilities.php:12-18`, `:150-191`). |
| `Converter` | De enige klasse die `WP_Image_Editor` aanraakt. Leest de bron, schrijft een apart doel, verifieert het resultaat (`class-converter.php:14-19`). |
| `Generator` | Per bijlage: doellijst opbouwen, per maat/formaat converteren, registratie bijwerken, en de koppeling met de WordPress-medialevenscyclus (`class-generator.php:12-15`). |
| `Variants` | De registratie: welke variantbestanden bestaan voor welke bijlage, opgeslagen als postmeta `_imgx_variants` (`class-variants.php:12-18`). |
| `Files` | Pad- en URL-helpers, sidecar-naamgeving, formaatherkenning op magic bytes, alfa-detectie van PNG-bestanden (`has_alpha()`) en de enige plek die een bestand mag verwijderen (`class-files.php:12-17`). |
| `Picture_Renderer` | De frontend-transformatie: `<img>` → `<picture>` (`class-picture-renderer.php:12-17`). |
| `Oxygen` | Koppeling met Oxygen 6/Breakdance en Oxygen Classic, die buiten `the_content` om renderen (`class-oxygen.php:12-36`). |
| `Batch_Processor` | AJAX-batchrunner voor de bestaande bibliotheek (`class-batch-processor.php:12-17`). |
| `Admin` | De instellingenpagina, de statistieken en de NextGen-kolom in de mediabibliotheek. |
| `Site_Health` | Een directe Site Health-test plus een informatiesectie (`class-site-health.php:58-65`, `:142`). |
| `CLI` | `wp imgx status` / `generate` / `delete` (`class-cli.php:15`). |
| `Logger` | Rollende foutbuffer in `imgx_recent_errors`; naar de PHP-foutlog alleen bij `WP_DEBUG` + `debug` (`class-logger.php:12-15`, `:108-117`). |

### Genereren

`Generator::register_hooks()` (`class-generator.php:81-86`):

| Hook | Prioriteit | Methode |
| --- | --- | --- |
| `wp_generate_attachment_metadata` | 20 | `on_generate_metadata()` |
| `wp_update_attachment_metadata` | 20 | `on_update_metadata()` |
| `delete_attachment` | 10 | `on_delete_attachment()` |
| `imgx_generate_variants` (cron) | 10 | `run_scheduled()` |

Prioriteit 20 zorgt dat de metadata al compleet is als IMGX hem leest. `on_generate_metadata()`
geeft de metadata onveranderd terug — de filter wordt puur als gebeurtenis gebruikt (`:121`). In
`async`-modus wordt een enkelvoudig cron-event 15 seconden later ingepland; WordPress dedupliceert
identieke events binnen tien minuten, dus een burst metadata-updates levert één run op
(`:199-217`).

Bronformaten zijn alleen `image/jpeg` en `image/png`: GIF valt af omdat animatie niet betrouwbaar
te reproduceren is, SVG omdat er niets te converteren valt (`:88-105`, filterbaar met
`imgx_source_mime_types`).

`build_targets()` bouwt de werklijst "maatsleutel => absoluut bronpad" uit de attachment-metadata
en controleert élk pad tegen het bestandssysteem; de metadata is de waarheid, er wordt niets
aangenomen (`:595-648`). Meerdere maatsleutels die naar hetzelfde bestand wijzen worden één keer
geconverteerd en daarna op alle broertjes gespiegeld (`:397-439`).

Per bijlage wordt een slot genomen via `add_option()` — dat schrijft in een kolom met een unieke
index, dus precies één gelijktijdige aanroeper wint. Sloten ouder dan 300 s gelden als verlaten
(`:26-31`, `:870-905`).

`Converter::convert()` (`class-converter.php:112-210`) weigert een doel buiten de uploadsmap,
neutraliseert tijdelijk elk `image_editor_output_format`-filter van derden (`:63-101`) en
controleert daarna wat de encoder wérkelijk heeft geschreven: het pad moet het gevraagde pad zijn,
het bestand niet leeg, en de magic bytes moeten bij het formaat passen (`:221-254`). Is de variant
groter dan of even groot als de bron, dan wordt hij weggegooid en als `larger-than-source`
overgeslagen — uitserveren zou een verslechtering zijn (`:256-274`). Die specifieke overslag wordt
opnieuw geprobeerd zodra de encoding-vingerafdruk wijzigt; een door een filter geweigerde variant
niet (`class-generator.php:316-330`).

### LQIP-placeholder

Techniek uit <https://csswizardry.com/2023/09/the-ultimate-lqip-lcp-technique/>: een extern JPEG
met genoeg bits per pixel dat Chromium het als LCP-kandidaat telt (vanaf 0,05 bpp van de
weergegeven maat), als `background-image` op de `<img>`. Het hi-res plaatje dekt hem af zodra het
geladen is. Spec: `docs/imgx-lqip-brief.md`.

**Genereren.** Met `lqip_enabled = 1` maakt `Generator` voor elke maat uit de metadata (inclusief
`full`) een `<stem>.lqip.jpg` naast het bronbestand, met exact dezelfde pixelafmeting. De
registratie `_imgx_variants` krijgt de sleutel `Files::LQIP_KEY` (`lqip`) naast `avif`/`webp`.
`Converter` schrijft hem met mime `image/jpeg` in een lus: start op q=10 (filter
`imgx_lqip_start_quality`), stappen van 5, tot het bestand ≥ breedte × hoogte × 0,055 / 8 bytes is;
plafond q=60 — een resultaat op het plafond blijft staan, ook onder het doel. `larger-than-source`
wordt één keer op het eindresultaat gecontroleerd. LQIP wordt ook gegenereerd (en door
`needs_generation()`, `generate()` en `wp imgx generate` meegeteld) als er geen WebP/AVIF-formaat
actief is. `Files::MIME_TYPES` blijft alleen `avif`/`webp`: die lijst voedt `<source type>` en de
encoder-probe.

**PNG met alfa** krijgt geen LQIP: `Files::has_alpha()` leest de ruwe bytes (IHDR-kleurtype 4 of 6,
of een `tRNS`-chunk vóór de eerste `IDAT`). De beslissing valt één keer per bijlage op het origineel (`wp_get_original_image_path()`, anders `get_attached_file()`) en geldt voor alle maten, omdat de GD-editor van WordPress PNG-submaten altijd als RGBA schrijft, ook bij een opaak origineel. Ze valt vóór elke converter-aanroep en wordt
vastgelegd als `skipped[<maat>]['lqip'] = 'has-alpha'`; die reden wordt niet opnieuw geprobeerd.

**Naamgeving en verwijderen.** `Files::sidecar_path( $bron, 'lqip', … )` geeft `x.lqip.jpg`, bij een
vreemd bestaand bestand `x.jpg.lqip.jpg` en daarna `x-<hash6>.lqip.jpg`. `Files::delete_variant()`
verwijdert naast `.webp`/`.avif` ook bestanden die op `.lqip.jpg` eindigen, nog steeds alleen binnen
de uploadsmap. `sidecar_source_still_exists()` strip het achtervoegsel `.lqip.jpg` in zijn geheel
voordat hij de bron terugzoekt, zodat `prune_stale_sizes()` een LQIP niet wist zolang zijn bron bestaat.

**Tiny-laag (sinds 2.12.1).** Onder de LQIP zit een inline base64-JPEG van 16 px (langste zijde, q=40)
als eerste, blurry frame; te klein om als LCP-kandidaat te tellen, zoals in het artikel. Eén per
bijlage, van het door WordPress geschaalde bestand (`get_attached_file()`; alfa-beslissing blijft op het origineel), in postmeta `_imgx_lqip_tiny` (`Variants::TINY_KEY`), gemaakt door
`Converter::tiny_base64()` via een tijdelijk bestand dat direct wordt verwijderd en door
`Generator::generate_tiny()` opgeslagen na `Variants::save()`. Meer dan 2048 tekens wordt niet
opgeslagen; `Variants::valid_tiny()` valideert (`^[A-Za-z0-9+/]+={0,2}$`) bij opslaan en uitserveren.
Alfa-originelen krijgen er geen. `needs_generation()` is waar zolang de tiny ontbreekt, dus batch
"ontbrekende" vult bestaande 2.12.0-bijlagen aan; `Variants::delete()` wist de meta mee. Kost
ongeveer 1 KB HTML per afbeelding. De renderer schrijft
`background-image:url("…lqip.jpg"),url("data:image/svg+xml,…")` (zonder LQIP voor die maat
alleen de tiny); de preload blijft de `.lqip.jpg`.

**Tiny als SVG (sinds 2.12.3).** De tiny is één per bijlage, maar de beeldverhouding verschilt per
maat (16×9 is breder dan 2560×1452; een thumbnail is bijgesneden). `Picture_Renderer::tiny_data_uri()`
verpakt de tiny daarom per `<img>` in een SVG met `viewBox`/`width`/`height` = de afmetingen van de
maat die bij de `src` hoort (`Variants::dimensions()`, uit de al geladen attachment-metadata).
De `<image>` erin gebruikt `preserveAspectRatio="xMidYMid slice"` (afrondingsfout wegsnijden,
bijgesneden maten gecentreerd croppen) en een `feGaussianBlur` met `stdDeviation` = langste zijde / 40
(min. 1); `feFuncA table 1 1` houdt de randen dekkend. Het SVG bevat alleen
de gevalideerde base64 en integers, zonder spaties-probleem: `%`, `<`, `>`, `#` en `"` worden
procentgecodeerd, enkele quotes blijven staan (geldig binnen `url("…")`). Onbekende afmetingen: de
gewone `data:image/jpeg;base64,…`-laag. Kost ongeveer 300 bytes extra HTML per afbeelding.

**`object-fit: contain` / `scale-down`.** Laat de tiny dan meeschalen met de afbeelding:

```css
img { background-size: contain; }
```

**Uitserveren.** `Picture_Renderer::add_lqip_style()` zet alleen `background-image:url("…")`
inline op de `<img>` (sinds 2.12.2; cover/no-repeat/center staan in `custom-style.css`, zie hieronder), voor de LQIP van de maat waarvan de basename met de `src` overeenkomt, alleen als die
geregistreerd is (en bij `imgx_verify_variant_files` ook op schijf bestaat). Ook op
`loading="lazy"`. Een bestaand `style` wordt aangevuld met behoud van zijn quote-stijl, een
ontbrekend wordt vlak voor `>`/`/>` ingevoegd; een tag met al een `background` blijft onaangeroerd.
De `src` wordt gedecodeerd, aanhalingstekens, puntkomma, backslash, haakjes, spatie en regeleinden worden
procentgecodeerd, en de hele stijlwaarde gaat door `esc_attr()`. `is_active()` vereist met LQIP aan
niet langer `picture_enabled` of formaten; de overige gates (admin, AJAX, REST, cron, WP-CLI, feeds,
embeds, AMP, `imgx_enable_picture`) blijven gelden. `wrap_img()` doet het `<picture>`-deel alleen bij
`picture_enabled = 1`; `imgx_picture_html` krijgt de `<img>` zoals die werkelijk wordt uitgevoerd.

**Preload.** In `whole_page`-modus zoekt `filter_page()` in de uiteindelijke buffer de eerste
`<img fetchpriority="high">` waarvan `style` een `.lqip.jpg` bevat (ook binnen IMGX' eigen
`<picture>`) en zet `<link rel="preload" as="image" href="…" fetchpriority="high">` vóór `</head>`.
Nooit een preload van de hi-res of van WebP/AVIF; buiten `whole_page` is de `<head>` al verstuurd.

### Uitserveren

`Picture_Renderer::register_hooks()` (`class-picture-renderer.php:94-107`) hangt aan
`wp_get_attachment_image` (10) en aan `the_content`, `widget_text_content` en
`widget_block_content` op **prioriteit 20** — na core's `wp_filter_content_tags()` op 12, zodat
`srcset`, `sizes`, `loading`, `decoding` en `fetchpriority` al definitief zijn. Staat `whole_page`
aan, dan komt er bovendien een outputbuffer op `template_redirect` prioriteit 1, zodat hij om alle
output heen valt (`:100-106`).

De originele `<img>` wordt nooit opnieuw opgebouwd: hij wordt letterlijk in de `<picture>`
geconcateneerd, zodat elk attribuut dat een thema, plugin of paginabuilder erop zette blijft staan
(`:12-17`, `:507-509`). De klassen van de `<img>` worden sinds 2.12.2 niet meer naar de wrapper
gekopieerd (die houdt alleen `imgx-picture` en is `display: contents`); het `id` ook niet (`class-picture-renderer.php:554-557`, `:657-687`).

**Basis-CSS.** IMGX zet inline alleen `background-image` op de `<img>`; de rest van de opmaak komt
uit `custom-style.css`. Een nieuwe installatie krijgt dit blok daar automatisch (aangemaakt door
`PDK_Plugin::default_css()`):

```css
img {
	font-style: italic;
	background-repeat: no-repeat;
	background-size: cover;
	background-position: center;
	background-origin: content-box;
	background-clip: content-box;
	shape-margin: 1rem;
	max-width: 100%;
	height: auto;
	vertical-align: middle;
}
picture {
	display: contents;
}
```

`background-origin` en `background-clip: content-box` (sinds 2.12.4) houden de LQIP binnen de
content-box, waar ook de foto staat. Zonder `origin` komt hij in de padding terecht; zonder `clip`
loopt hij bij een bijsnede (`object-fit: cover` in een andere verhouding, bv. `aspect-ratio: 1/1`) aan
één as de padding in. Let op: `background-clip` geldt ook voor een `background-color` op de `<img>`.
Wil je een gekleurde lijst in de padding, teken die dan met een inset-schaduw zo dik als de padding:

```css
.fotolijst {
	padding: 3px;
	border: 1px solid #e2e2e2;
	box-shadow: inset 0 0 0 3px #fff;
}
```

Bestaande bestanden worden nooit overschreven. Draait een site al op 2.12.x met LQIP aan, doe dan
zelf:

1. Plak het blok hierboven in `custom-style.css` via PDK Tools → Custom CSS (de code-editor). Niet via
   FTP: dan klopt de opgeslagen hash niet meer en wordt het hele bestand als gewijzigd geblokkeerd.
2. Zet de module Custom CSS aan op de Modules-tab. Die staat sinds 2.12.2 alleen op nieuwe
   installaties standaard aan; bestaande sites houden hun opgeslagen keuze.

Zonder deze stappen tegelt de LQIP en vult hij het vlak niet meer.

Een bijlage-ID komt uit de `wp-image-<id>`-class. Ontbreekt die — paginabuilders renderen hun eigen
markup — dan wordt de `src` in één gebatchte query teruggezocht naar `_wp_attached_file`
(`:263-309`, `:364-397`, maximaal 200 paden per query). Beide routes eindigen in dezelfde
controle: er wordt alleen een variant uitgeserveerd voor een bestandsnaam die in de registratie van
de gevonden bijlage staat, dus een verkeerde gok levert geen wijziging op (`:186-198`,
`:580-618`). Afbeeldingen die al in een `<picture>` staan worden overgeslagen; vervangingen worden
van achter naar voren toegepast zodat de offsets geldig blijven (`:246-258`).

`is_active()` is bewust niet gememoiseerd: conditionele tags als `is_feed()` worden pas betrouwbaar
na de hoofdquery (`:755-805`). De transformatie staat uit in admin, AJAX, REST, cron, WP-CLI,
feeds, embeds en AMP-requests.

`Oxygen` vangt de twee generaties Oxygen op die geen van IMGX' normale hooks raken: Oxygen 6 en
Breakdance via `breakdance_render_rendered_html` (prioriteit 20), Oxygen Classic 4.x via
`ct_before_builder` (prioriteit 20), waar de global `$template_content` het onderscheppingspunt is
(`class-oxygen.php:63-102`). Requests vanuit de builder-canvas worden overgeslagen, anders ziet de
ontwerper iets anders dan hij bewerkt (`:130-162`).

### Bestaande bibliotheek verwerken

`Batch_Processor` biedt drie AJAX-endpoints — `imgx_batch_start` / `_step` / `_cancel` — achter
`manage_options` (filterbaar met `imgx_batch_capability`) plus nonce
(`class-batch-processor.php:75-106`). Drie modi: `missing`, `all` (verwijdert bestaande varianten
en bouwt opnieuw op) en `delete` (`:43`, `:222-253`). State staat in de optie `imgx_batch_state`
zodat een gesloten browsertab geen voortgang kost; een stap stopt na 15 seconden of 3 bijlagen
(`:23-38`). De knoppen staan op de IMGX-tab (`class-admin.php:472-485`).

Voor grote bibliotheken is WP-CLI de praktische weg: `wp imgx status`, `wp imgx generate
[--ids=] [--force] [--batch=n]` en `wp imgx delete [--ids=] [--yes]` (`class-cli.php:67-211`).

## Bestanden en gegevens

**Op schijf**: per bijlage, per maat en per formaat één sidecar, in dezelfde map als het
bronbestand. Naamgeving bij voorkeur `photo.webp`; omdat `photo.jpg` en `photo.png` in één map
kunnen bestaan en beide `photo.webp` zouden willen, valt IMGX terug op `photo.jpg.webp` en
uiteindelijk op `photo-<hash6>.webp` (`class-files.php:141-176`). Een LQIP heet `photo.lqip.jpg` (terugval `photo.jpg.lqip.jpg`, dan `photo-<hash6>.lqip.jpg`). Alleen
`.webp`, `.avif` en `.lqip.jpg` binnen de uploadsmap mogen ooit worden geschreven of verwijderd; `Files::delete_variant()` weigert al het
andere, zodat geen enkel codepad een origineel kan wissen (`:20-23`, `:111-139`).

**In de database**:

| Sleutel | Inhoud |
| --- | --- |
| postmeta `_imgx_variants` | De registratie per bijlage: alleen basenames, plus `signature`, `generated` en `version` (`class-variants.php:24-29`, `:111-159`) |
| optie `imgx_settings` | De instellingen (`class-settings.php:20`) |
| optie `imgx_capabilities` | Uitkomst van de encoder-probe, met omgevingsvingerafdruk (`class-capabilities.php:24`, `:306-336`) |
| optie `imgx_batch_state` | Lopende batch (`class-batch-processor.php:23`) |
| optie `imgx_recent_errors` | Laatste 30 fouten (`class-logger.php:21-26`) |
| opties `imgx_lock_<id>` | Per-bijlage generatieslot, TTL 300 s (`class-generator.php:26-31`) |
| transient `imgx_stats` | Statistieken van de tab, 300 s (`class-admin.php:25-30`) |

**Opruimen**: `delete_attachment` verwijdert de opgenomen sidecars (`class-generator.php:176-187`).
Bij deactivering van de plugin worden de geplande generatietaken uit de cron-tabel gehaald
(`includes/class-pdk-plugin.php:152-160`). Bij uninstall verdwijnen alle opties, de transient, de
`imgx_lock_%`-rijen en alle `_imgx_variants`-postmeta — maar **de gegenereerde bestanden blijven
bewust staan** (ook de `.lqip.jpg`-bestanden): honderdduizenden bestanden verwijderen in een
uninstall-hook loopt in een timeout.
Draai eerst "Alle gegenereerde bestanden verwijderen" of `wp imgx delete`
(`uninstall.php:30-57`).

## Voor themaontwikkelaars

Toegang tot de services loopt via `\IMGX\Plugin::instance()`, met getters `settings()`,
`capabilities()`, `converter()`, `generator()`, `renderer()`, `admin()` en `logger()`
(`class-plugin.php:156-229`). De renderer is expliciet publiek zodat een thema of builder markup
kan wikkelen die IMGX zelf niet ziet:
`\IMGX\Plugin::instance()->renderer()->wrap_img( $html, $attachment_id, 'mijn-builder' )`
(`class-picture-renderer.php:430-441`). Sinds 2.12.0 hangt het `<picture>`-deel van `wrap_img()` zelf
van `picture_enabled` af (voorheen alleen de filter-aanroepers): een directe aanroep met
`picture_enabled = 0` levert dus geen `<picture>` meer, wel een `<img>` met LQIP-achtergrond als
`lqip_enabled` aan staat.

Filters:

| Filter | Waar |
| --- | --- |
| `imgx_enabled_formats` | Globale formaatlijst (`class-settings.php:242-247`) |
| `imgx_formats` | Formaten per bijlage, met context `generate` of `render` (`class-generator.php:245-252`) |
| `imgx_source_mime_types` | Welke bronmimetypes varianten krijgen (`class-generator.php:97-102`) |
| `imgx_should_generate_image` | Eén specifieke variant overslaan, ook de LQIP met `$format = 'lqip'` (`class-generator.php:504-512`) |
| `imgx_webp_quality` / `imgx_avif_quality` | Kwaliteit per bijlage en maat (`class-converter.php:286-310`) |
| `imgx_lqip_start_quality` | Startkwaliteit van de LQIP-lus, standaard 10 (`class-converter.php`, `lqip_start_quality()`) |
| `imgx_keep_larger_variant` | Variant behouden die groter is dan de bron (`class-converter.php:256-265`) |
| `imgx_enable_picture` | Transformatie per request uitzetten, ook de LQIP-achtergrond (`class-picture-renderer.php:797-804`) |
| `imgx_should_render_picture` | Per afbeelding niet wikkelen, en geen LQIP-achtergrond (`class-picture-renderer.php:448-455`) |
| `imgx_picture_class` | Klassen op de `<picture>`-wrapper (`class-picture-renderer.php:682`) |
| `imgx_picture_html` | De uiteindelijke markup; het tweede argument is de `<img>` zoals uitgevoerd, mét LQIP-style (`class-picture-renderer.php:511-519`) |
| `imgx_verify_variant_files` | Elk variantbestand bij het renderen op schijf controleren (`class-picture-renderer.php:481`) |
| `imgx_batch_size` | Bijlagen per batchstap, 1-50 (`class-batch-processor.php:204-210`) |
| `imgx_batch_capability` | Vereiste capability voor de batch (`class-batch-processor.php:87-92`) |

Acties:

| Actie | Waar |
| --- | --- |
| `imgx_before_save` | Vlak vóór het wegschrijven, met de geconfigureerde `WP_Image_Editor` (`class-converter.php:172-183`) |
| `imgx_after_generate_variant` | Na een geschreven en geverifieerde variant (`class-generator.php:579-587`) |
| `imgx_generation_error` | Variant kon niet gemaakt worden (`class-generator.php:543-551`) |
| `imgx_after_generate_attachment` | Na één verwerkte bijlage, met tellers (`class-generator.php:453-459`) |
| `imgx_after_delete_variants` | Na het verwijderen van de sidecars van een bijlage (`class-generator.php:663-669`) |

Vanuit de PDK-kant: `PDK_ImgX::tab_url( array $args = [] )` geeft de URL van de IMGX-tab
(`class-pdk-imgx.php:63-68`).

## Grenzen en valkuilen

- **Geen `.htaccess`-rewrite, geen content-negotiation.** Alles hangt aan de `<picture>`-markup.
  Komt een afbeelding op een plek waar IMGX de HTML niet ziet — een paginabuilder zonder
  koppeling, een e-mailtemplate, een JSON-API — dan wordt gewoon het origineel geserveerd. De
  `whole_page`-buffer is de terugval, maar staat standaard uit.
- **`whole_page` filtert alleen complete HTML-documenten** (`</html>` moet erin staan) en slaat
  feeds, embeds, robots en JSON-requests over (`class-picture-renderer.php:120-148`). Een
  paginabuilder die zijn output op een andere manier wegschrijft valt daar buiten.
- **AVIF-codering is traag.** Op een gedeelde host kan één grote AVIF meerdere seconden kosten. Met
  `upload_mode = sync` zit die tijd in de upload zelf; `async` is daarom de standaard.
- **Cron is geen garantie.** Op een site zonder werkende WP-Cron blijven de geplande
  generatie-events staan en gebeurt er niets. Symptoom: uploads krijgen geen varianten terwijl de
  instellingen kloppen. Draai dan `wp imgx generate`.
- **De capability-probe wordt gecached** in `imgx_capabilities`, met een vingerafdruk over
  IMGX-versie, WP-versie, PHP-versie, editorklasse en Imagick/GD-versies
  (`class-capabilities.php:306-336`). Een serverwijziging die daar niet in zit — bijvoorbeeld een
  herbouwde libavif-delegate onder hetzelfde versienummer — wordt pas opgemerkt na "Serverondersteuning
  opnieuw controleren" of `wp imgx status`.
- **`is_intermediate_metadata_save()` leest de call stack** op zoek naar
  `wp_create_image_subsizes()` (`class-generator.php:710-737`). Hernoemt WordPress die functie ooit,
  dan valt die bescherming weg. Er is een tweede, onafhankelijke waarborg die op het bestandssysteem
  kijkt of de bron van een sidecar nog bestaat (`:819-858`), maar reken niet op de eerste alleen.
- **`prune_stale_sizes()` doet niets als er géén doel gevonden wordt**, bewust: een offload-plugin
  of verplaatste uploadsmap zou anders de hele registratie wissen en de `<picture>`-uitvoer stil
  laten vallen terwijl de bestanden er gewoon staan (`class-generator.php:750-760`).
- **Klassen worden sinds 2.12.2 niet meer naar de `<picture>` gekopieerd.** De wrapper is
  `display: contents`; thema's of builders die de wrapper via de gekopieerde klasse opmaakten,
  moeten de `<img>` selecteren. `imgx_picture_class` blijft bestaan (standaard alleen `imgx-picture`).
- **Statistieken zijn een full table scan** over de `_imgx_variants`-meta, vijf minuten gecached
  (`class-admin.php:624-658`). Op een zeer grote bibliotheek is de eerste laadbeurt van de tab traag.
- **Batch-offsets schuiven** als er tijdens een lange run geüpload of verwijderd wordt: de telling
  komt uit `wp_count_attachments()`, de slices uit een offset-query
  (`class-batch-processor.php:260-304`, `:267-286`).
- **`Settings::install_defaults()` wordt nergens aangeroepen** (geverifieerd: enige voorkomen is de
  definitie zelf, `class-settings.php:72`). De standaardwaarden komen in plaats daarvan bij elke
  leesactie over de opgeslagen waarden heen (`:113-125`), dus functioneel maakt het niets uit.
- **Uninstall laat de gegenereerde bestanden staan** (ook `.lqip.jpg`). Verwijder ze vooraf, anders
  blijven ze in de uploadsmap achter (`uninstall.php:30-32`).
- **R-101: elke afbeelding kost een extra, vroeg request.** Een CSS-achtergrond wordt opgehaald
  ongeacht `loading="lazy"`, en de LQIP staat op alle bijlage-afbeeldingen (orde 5 kB voor
  1024×768). Per afbeelding uit te zetten met `imgx_should_render_picture`.
- **R-102: srcset kan een grotere kandidaat kiezen dan `src`.** De LQIP hoort bij de `src`-maat;
  toont de browser een grotere kandidaat, dan wordt de LQIP opgeschaald en kan hij onder 0,05 bpp
  zakken en dus geen LCP-kandidaat zijn.
- **R-106: verouderde variant na het bewerken van een afbeelding.** `generate_one()` beschouwt een
  geregistreerde variant als klaar zolang het bestand bestaat, ook als de bron intussen in de
  WordPress-editor is bewerkt. Dat geldt voor WebP/AVIF en nu ook voor de LQIP. Omweg:
  `wp imgx generate --ids=<id> --force`.
- **`picture_enabled = 0` laat de frontend niet meer altijd ongemoeid** als `lqip_enabled` aan staat:
  de `<img>` krijgt dan nog steeds de achtergrond-style. Thema-CSS met `background` op `img` kan
  daarmee botsen; een inline `background` op de tag zelf wordt gerespecteerd.

## Zelftest

```
php tests/test-imgx.php
```

Of samen met alle andere: `php tests/run.php`. De test draait op kale PHP met een handvol
WordPress-stubs, dus zonder WordPress. Gedekt zijn de sidecar-naamgeving, de verwijdergaranties van
`Files::delete_variant()`, formaatherkenning op magic bytes, de srcset-mapping (inclusief het laten
vallen van kandidaten zonder variant), de markup-omzetting met nesting-preventie, het terugzoeken
van builder-`<img>`s naar een bijlage inclusief batching en het weigeren van vreemde hosts en
data-URI's, de wrapper-klasse (geen klassenoverdracht), de sanering van instellingen en registratie, de LQIP (instelling en vingerafdruk, naamgeving en
verwijderen, alfa-detectie, `has-alpha`-registratie, achtergrond-style en escaping, onafhankelijkheid
van `picture_enabled`, preload), en het gemeten scenario van de progressieve metadata-opslag van `wp_create_image_subsizes()`
(`tests/test-imgx.php:2-8`, `:206-510`).
