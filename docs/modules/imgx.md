# IMGX — WebP/AVIF sidecars

IMGX zet naast elke bestaande JPEG en PNG een WebP- en/of AVIF-versie neer (een "sidecar":
`photo.jpg` blijft staan, `photo.webp` komt ernaast) en serveert die op de frontend uit door elke
`<img>` in een `<picture>` met `<source>`-elementen te wikkelen. Het origineel wordt nooit
gewijzigd, verplaatst of verwijderd, en een variant wordt alleen aangeboden als IMGX het bestand
zelf heeft geschreven en in zijn registratie heeft staan. De module is de originele losse
IMGX-plugin, ongewijzigd overgenomen in `modules/imgx/includes/` onder de namespace `\IMGX\`
(`modules/imgx/class-pdk-imgx.php:3-13`).

## Aan- en uitzetten

Modulesleutel `imgx`, standaard **uit** (`includes/class-pdk-settings.php:208-210`). Geladen via
`$module_map` (`includes/class-pdk-plugin.php:29`) met een eigen tab "IMGX"
(`includes/class-pdk-admin.php:746`); die tab rendert IMGX' eigen formulier inline via
`PDK_ImgX::render_inline()` (`:813-817`, `class-pdk-imgx.php:71-77`). Geen afhankelijkheden op
andere modules.

Wel één harde voorwaarde op de server: er moet een `WP_Image_Editor` (GD of Imagick) zijn die het
gekozen formaat écht kan schrijven. Kan hij dat niet, dan genereert IMGX niets en blijft de
frontend ongemoeid (`includes/class-capabilities.php:12-18`).

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
| `generate_on_upload` | 0/1 | `1` | Varianten maken bij nieuwe uploads. |
| `upload_mode` | `async` \| `sync` | `async` | Genereren via cron (15 s later) of direct tijdens de upload. |
| `debug` | 0/1 | `0` | Uitgebreid loggen. Werkt alleen als ook `WP_DEBUG` aan staat. |

Bron: `includes/class-settings.php:54-65`. Sanering en clamping in `sanitize()` (`:146-187`);
onbekende modi vallen terug op de standaard. De AVIF-/WebP-kwaliteit en de modus vormen samen de
`encoding_signature()` (`:198-207`) — die vingerafdruk wordt bij de registratie opgeslagen zodat
een eerder overgeslagen variant opnieuw geprobeerd wordt als je de kwaliteit verlaagt.

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
| `Files` | Pad- en URL-helpers, sidecar-naamgeving, formaatherkenning op magic bytes, en de enige plek die een bestand mag verwijderen (`class-files.php:12-17`). |
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

### Uitserveren

`Picture_Renderer::register_hooks()` (`class-picture-renderer.php:94-107`) hangt aan
`wp_get_attachment_image` (10) en aan `the_content`, `widget_text_content` en
`widget_block_content` op **prioriteit 20** — na core's `wp_filter_content_tags()` op 12, zodat
`srcset`, `sizes`, `loading`, `decoding` en `fetchpriority` al definitief zijn. Staat `whole_page`
aan, dan komt er bovendien een outputbuffer op `template_redirect` prioriteit 1, zodat hij om alle
output heen valt (`:100-106`).

De originele `<img>` wordt nooit opnieuw opgebouwd: hij wordt letterlijk in de `<picture>`
geconcateneerd, zodat elk attribuut dat een thema, plugin of paginabuilder erop zette blijft staan
(`:12-17`, `:507-509`). De klassen van de `<img>` worden wél gekopieerd naar de wrapper, omdat het
wikkelen de `<img>` een niveau verplaatst en layout-CSS anders op het verkeerde element werkt; het
`id`-attribuut bewust niet (`:522-542`).

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
uiteindelijk op `photo-<hash6>.webp` (`class-files.php:141-176`). Alleen `.webp` en `.avif` binnen
de uploadsmap mogen ooit worden geschreven of verwijderd; `Files::delete_variant()` weigert al het
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
bewust staan**: honderdduizenden bestanden verwijderen in een uninstall-hook loopt in een timeout.
Draai eerst "Alle gegenereerde bestanden verwijderen" of `wp imgx delete`
(`uninstall.php:30-57`).

## Voor themaontwikkelaars

Toegang tot de services loopt via `\IMGX\Plugin::instance()`, met getters `settings()`,
`capabilities()`, `converter()`, `generator()`, `renderer()`, `admin()` en `logger()`
(`class-plugin.php:156-229`). De renderer is expliciet publiek zodat een thema of builder markup
kan wikkelen die IMGX zelf niet ziet:
`\IMGX\Plugin::instance()->renderer()->wrap_img( $html, $attachment_id, 'mijn-builder' )`
(`class-picture-renderer.php:430-441`).

Filters:

| Filter | Waar |
| --- | --- |
| `imgx_enabled_formats` | Globale formaatlijst (`class-settings.php:242-247`) |
| `imgx_formats` | Formaten per bijlage, met context `generate` of `render` (`class-generator.php:245-252`) |
| `imgx_source_mime_types` | Welke bronmimetypes varianten krijgen (`class-generator.php:97-102`) |
| `imgx_should_generate_image` | Eén specifieke variant overslaan (`class-generator.php:504-512`) |
| `imgx_webp_quality` / `imgx_avif_quality` | Kwaliteit per bijlage en maat (`class-converter.php:286-310`) |
| `imgx_keep_larger_variant` | Variant behouden die groter is dan de bron (`class-converter.php:256-265`) |
| `imgx_enable_picture` | Transformatie per request uitzetten (`class-picture-renderer.php:797-804`) |
| `imgx_should_render_picture` | Per afbeelding niet wikkelen (`class-picture-renderer.php:448-455`) |
| `imgx_picture_class` | Klassen op de `<picture>`-wrapper (`class-picture-renderer.php:558-573`) |
| `imgx_picture_html` | De uiteindelijke markup (`class-picture-renderer.php:511-519`) |
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
- **Klassen worden gekopieerd naar de `<picture>`.** Een klasse met marges, padding of border werkt
  daardoor op zowel wrapper als afbeelding en telt dubbel. Gebruik `imgx_picture_class` om zo'n
  klasse van de wrapper te halen (`class-picture-renderer.php:533-537`).
- **Statistieken zijn een full table scan** over de `_imgx_variants`-meta, vijf minuten gecached
  (`class-admin.php:624-658`). Op een zeer grote bibliotheek is de eerste laadbeurt van de tab traag.
- **Batch-offsets schuiven** als er tijdens een lange run geüpload of verwijderd wordt: de telling
  komt uit `wp_count_attachments()`, de slices uit een offset-query
  (`class-batch-processor.php:260-304`, `:267-286`).
- **`Settings::install_defaults()` wordt nergens aangeroepen** (geverifieerd: enige voorkomen is de
  definitie zelf, `class-settings.php:72`). De standaardwaarden komen in plaats daarvan bij elke
  leesactie over de opgeslagen waarden heen (`:113-125`), dus functioneel maakt het niets uit.
- **Uninstall laat de gegenereerde bestanden staan.** Verwijder ze vooraf, anders blijven ze in de
  uploadsmap achter (`uninstall.php:30-32`).

## Zelftest

```
php tests/test-imgx.php
```

Of samen met alle andere: `php tests/run.php`. De test draait op kale PHP met een handvol
WordPress-stubs, dus zonder WordPress. Gedekt zijn de sidecar-naamgeving, de verwijdergaranties van
`Files::delete_variant()`, formaatherkenning op magic bytes, de srcset-mapping (inclusief het laten
vallen van kandidaten zonder variant), de markup-omzetting met nesting-preventie, het terugzoeken
van builder-`<img>`s naar een bijlage inclusief batching en het weigeren van vreemde hosts en
data-URI's, de klassenoverdracht naar de wrapper, de sanering van instellingen en registratie, en
het gemeten scenario van de progressieve metadata-opslag van `wp_create_image_subsizes()`
(`tests/test-imgx.php:2-8`, `:206-510`).
