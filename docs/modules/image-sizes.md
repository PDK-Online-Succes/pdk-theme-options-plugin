# Afbeeldingsmaten

WordPress registreert bij elke upload een submaat per geregistreerde afbeeldingsmaat — kernmaten,
thema-maten en maten van plugins — en op een gemiddelde site wordt de helft daarvan nooit
uitgeserveerd. Deze module toont welke maten geregistreerd zijn, laat een beheerder ze aan- en
uitzetten, eigen maten aanmaken met het voorvoegsel `pdk_`, en de bestaande mediabibliotheek in
batches opnieuw laten genereren. Code:
`pdk-theme-options/modules/image-sizes/class-pdk-image-sizes.php` plus
`includes/class-image-sizes-batch.php`; de interface staat in `includes/class-pdk-admin.php`.

## Aan- en uitzetten

Modulesleutel `image_sizes`, standaard **uit** (`includes/class-pdk-settings.php:213-217`). Geladen
via `$module_map` (`includes/class-pdk-plugin.php:30`), met een eigen tab "Afbeeldingsmaten"
(`includes/class-pdk-admin.php:747`, render op `:818-820`). Geen afhankelijkheden — WooCommerce
niet, IMGX niet. Staat IMGX uit, dan toont de tab wel een opmerking dat nieuwe maten geen
WebP/AVIF krijgen (`includes/class-pdk-admin.php:1906-1909`).

## Instellingen

Onder de sleutel `image_sizes` in de optie `pdk_theme_options`
(`includes/class-pdk-settings.php:213-217`):

| Sleutel | Type | Standaard | Betekenis |
| --- | --- | --- | --- |
| `enabled` | bool | `false` | Module aan/uit. |
| `disabled` | `string[]` | `[]` | Blocklist van uitgeschakelde maatsleutels. |
| `custom` | `array<string,{width:int,height:int,crop:bool}>` | `[]` | Eigen maten, sleutel altijd met voorvoegsel `pdk_`. |

De lopende hergeneratie staat apart, in de optie `pdk_image_sizes_batch`
(`includes/class-image-sizes-batch.php:22`).

## Hoe het werkt

**Blocklist, geen allowlist.** Een maat die niet in `image_sizes.disabled` staat is actief, ook als
hij pas later door een thema of plugin wordt geregistreerd (`class-pdk-image-sizes.php:10-13`,
`:57-59`, `:84-90`). `thumbnail` is vergrendeld en kan nooit uit: wp-admin en vrijwel elk thema
leunen erop (`:34`, `:75-77`). Dat wordt niet alleen in de UI afgedwongen maar ook in
`sanitize_disabled_list()`, die `thumbnail` er altijd uit haalt — ook uit een geprepareerde POST
(`:100-105`).

Drie hooks, geregistreerd in de constructor (`:37-45`):

| Hook | Prioriteit | Methode |
| --- | --- | --- |
| `init` | 20 | `register_custom_sizes()` |
| `intermediate_image_sizes_advanced` | 10 | `filter_generation()` |
| `image_size_names_choose` | 10 | `filter_size_choices()` |

Prioriteit 20 op `init` laat thema's en andere plugins hun `add_image_size()` eerst draaien op de
gebruikelijke momenten (`:38-40`). `register_custom_sizes()` registreert élke eigen maat, ook een
uitgeschakelde — net als WordPress zijn kernmaten altijd registreert; of hij gegenereerd wordt
bepaalt `filter_generation()` (`:148-166`). Die haalt uitgeschakelde maten uit de generatielijst
vlak voordat WordPress ze voor een nieuwe upload aanmaakt (`:176-184`).

`filter_size_choices()` voegt de eigen maten toe aan de maatkiezer van de blok-editor en is bewust
**additief**: een eerdere versie bouwde de lijst opnieuw op vanuit een vaste set van vier en gooide
daarmee keuzes weg die een filter met lagere prioriteit had toegevoegd — gemeten met een testfilter
op prioriteit 5 (`:186-205`).

**Aanmaken en verwijderen** lopen via `admin-post.php`
(`includes/class-pdk-admin.php:60-62`). `handle_image_size_create()` valideert naam en afmetingen
en controleert op botsingen met zowel de kale naam als de voorvoegde sleutel, zodat een eigen maat
"large" net zo goed geweigerd wordt als een botsende `pdk_`-maat (`:1959-2021`, logica in
`class-pdk-image-sizes.php:120-130`). Minstens één van breedte/hoogte moet positief zijn; 0 betekent
proportioneel, net als `medium_large` (`:139-142`). Een bestaande maat is niet te bewerken — alleen
te verwijderen en opnieuw aan te maken (`includes/class-pdk-admin.php:2000`).

**Opslaan van de blocklist** gebeurt niet via `PDK_Settings::update()`, omdat
`array_replace_recursive()` lijsten per index samenvoegt en een weer geactiveerde maat dan uit zou
blijven staan (`includes/class-pdk-admin.php:1948-1953`).

**De duallistbox** is met de hand gebouwd, twee `<select multiple>` met verplaatsknoppen, geen
JS-library (`modules/image-sizes/assets/js/image-sizes-admin.js:1-51`). Vlak voor het versturen worden alle opties in
"Uitgeschakeld" geselecteerd, want een `<select multiple>` post alleen de geselecteerde opties
(`:53-60`). Vergrendelde opties worden nooit verplaatst (`:18-19`).

**Hergeneratie in batches** zit in `PDK_Image_Sizes_Batch`, hetzelfde patroon als IMGX'
Batch_Processor: state in één optie, 3 bijlagen per stap, 15 seconden tijdslimiet per stap
(`includes/class-image-sizes-batch.php:22-37`). Drie AJAX-endpoints —
`pdk_image_sizes_batch_start` / `_step` / `_cancel` (`:39-43`) — elk achter `manage_options` plus
nonce (`:45-52`). Twee modi: `missing` slaat bijlagen over die geen actieve maat missen, `all`
bouwt bij elke bijlage de volledige maatset opnieuw op (`:34`, `:150-187`). Starten terwijl er al
een run loopt geeft een 409 en laat de bestaande status ongemoeid (`:65-74`).

Per bijlage wordt `wp_generate_attachment_metadata()` gedraaid en het resultaat opgeslagen met
`wp_update_attachment_metadata()` (`:167-179`). Dat laatste is bewust de normale WordPress-weg: het
laat IMGX' eigen filter op `wp_update_attachment_metadata` vuren zodra die module actief is, zonder
dat deze klasse ook maar één regel IMGX-code aanroept (`:9-14`, `:176-178`). Een bijlage waarvan het
bronbestand ontbreekt of waarvan het genereren mislukt levert een foutregel op en stopt de run
niet (`:159-174`); de foutlijst wordt op de laatste 50 afgekapt (`:189`).

`needs_generation()` telt een ontbrekende maat alleen mee als het origineel groot genoeg is om die
maat te leveren — anders zou WordPress hem toch overslaan en zou de run nooit "klaar" raken
(`:199-227`).

De UI-assets en de batch-state worden alleen op deze tab ingeladen, achter een
`class_exists( 'PDK_Image_Sizes_Batch' )` zodat een handmatige `?tab=image_sizes` op een
uitgeschakelde module geen fatale fout geeft (`includes/class-pdk-admin.php:256-289`). Een
onderbroken run wordt bij het laden van de tab meegegeven aan de browser via
`shape_for_browser()` (`:276-280`, `class-image-sizes-batch.php:274-278`).

## Bestanden en gegevens

Op schijf schrijft de module zelf niets. Hergeneratie laat WordPress submaatbestanden in de
uploadsmap schrijven, via de gewone kernfuncties.

In de database: `image_sizes.disabled` en `image_sizes.custom` binnen `pdk_theme_options`, plus de
eigen optie `pdk_image_sizes_batch` met de lopende run (`class-image-sizes-batch.php:22`). Die optie
wordt gewist zodra een run klaar is of wordt afgebroken (`:112-113`, `:125`).

**Uitzetten van een maat verwijdert niets van schijf** en stopt alleen de generatie voor nieuwe
uploads; bestaande bestanden en bestaande metadata blijven ongemoeid
(`class-pdk-image-sizes.php:16-18`). Ook een afgebroken run laat al gegenereerde bestanden staan
(`class-image-sizes-batch.php:121`).

Bij uninstall wordt `pdk_image_sizes_batch` verwijderd; de instellingen verdwijnen met
`pdk_theme_options` (`uninstall.php:25-28`).

## Voor themaontwikkelaars

Publieke statische methoden op `PDK_Image_Sizes` (`class-pdk-image-sizes.php`):

| Methode | Betekenis |
| --- | --- |
| `disabled(): string[]` | Uitgeschakelde maatsleutels (`:57`) |
| `custom(): array` | Eigen maten met afmetingen (`:66`) |
| `registered(): array` | Alles wat WordPress kent, via `wp_get_registered_image_subsizes()` (`:71`) |
| `is_enabled( string $key ): bool` | `thumbnail` altijd true, verder de blocklist (`:84`) |
| `is_locked()` / `is_custom()` | Vergrendeld resp. `pdk_`-voorvoegsel (`:75`, `:79`) |
| `key_exists()` / `colliding_key()` | Botsingscontrole bij aanmaken (`:108`, `:120`) |
| `generate_key()` / `has_valid_dimensions()` | Sleutel- en afmetingvalidatie (`:133`, `:140`) |

Op `PDK_Image_Sizes_Batch`: `current_state()`, `shape_for_browser()`, `needs_generation()`,
`count_attachments()` en `run_step()` (`class-image-sizes-batch.php:55`, `:274`, `:199`, `:256`,
`:136`).

De module registreert zelf **geen** eigen filters, acties of shortcodes. Wil je van buitenaf
ingrijpen, dan doe je dat op de WordPress-hooks waar de module zelf ook aan hangt.

## Grenzen en valkuilen

- **Uitzetten ruimt niets op.** Bestaande submaatbestanden blijven op schijf staan en blijven in de
  metadata; de module heeft geen opruimfunctie (`class-pdk-image-sizes.php:16-18`).
- **`thumbnail` kan niet uit** — bewust, op twee plekken afgedwongen (`:75-77`, `:100-105`).
- **Een eigen maat is niet te bewerken.** Verwijderen en opnieuw aanmaken is de enige weg
  (`includes/class-pdk-admin.php:2000`). Verwijderen haalt de registratie weg, niet de al
  gegenereerde bestanden.
- **Alleen `image/jpeg`, `image/png`, `image/gif` en `image/webp` worden geteld en verwerkt** door
  de batch (`class-image-sizes-batch.php:37`). AVIF-bijlagen en andere mimetypes komen er niet in
  voor.
- **De batchtelling gebruikt `wp_count_attachments()` maar de slices een offset-query.** Verandert
  de bibliotheek tijdens een lange run (uploads, verwijderingen), dan schuiven de offsets en kan
  een bijlage overgeslagen of dubbel verwerkt worden.
- **Mode `all` is duur**: die bouwt bij élke bijlage de volledige maatset opnieuw op, en met IMGX
  actief hangt daar ook nog WebP/AVIF-generatie aan. 3 bijlagen per stap, 15 seconden per stap —
  reken op uren voor een grote bibliotheek.
- **Er is geen opruiming van verweesde submaten na hergeneratie**: `wp_generate_attachment_metadata()`
  schrijft nieuwe bestanden, oude blijven staan. Niet geverifieerd in de code hier — het is
  WordPress-kerngedrag en de module doet er niets aan.
- **De IMGX-koppeling is impliciet.** Er is geen aanroep van IMGX-code; alles loopt via
  `wp_update_attachment_metadata()`. Verandert IMGX zijn hook, dan stopt de samenwerking stil
  (`class-image-sizes-batch.php:9-14`).

## Zelftest

```
php tests/test-image-sizes.php
```

Of samen met alle andere: `php tests/run.php`. Gedekt zijn de blocklist-semantiek, dat
`sanitize_disabled_list()` `thumbnail` er altijd uit haalt, `colliding_key()`,
`has_valid_dimensions()`, `is_custom()`, dat `filter_generation()` alleen uitgezette maten
weglaat, de regressietest dat `filter_size_choices()` additief is, en van de batch: modusvalidatie,
weigeren van een tweede gelijktijdige start, en dat een falende bijlage de run niet stopt
(`tests/test-image-sizes.php:146-355`).
