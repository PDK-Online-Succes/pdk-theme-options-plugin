# Libraries (JS/CSS)

De module laadt losse JS- en CSS-bestanden uit `uploads/pdk-theme-options/libraries/` op de frontend — bedoeld voor kant-en-klare bibliotheken als Glide.js, Swiper of Splide. Uploaden, bewerken, aan/uit zetten en verwijderen gebeurt op de Libraries-tab; de bestanden staan buiten de pluginmap en overleven een update (`modules/libraries/class-pdk-libraries.php:3-10`).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `libraries` (`includes/class-pdk-plugin.php:22`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php:164-169`) |
| Eigen tab | Ja — "Libraries", alleen als de module aan staat (`includes/class-pdk-admin.php:742`, `includes/class-pdk-admin.php:750-754`). De tab heeft eigen formulieren en staat daarom in `$standalone_tabs` (`includes/class-pdk-admin.php:679`) |

## Instellingen

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `libraries.enabled` | bool | `false` | Module laden ja/nee |
| `libraries.disabled` | string[] | `[]` | Bestandsnamen die **niet** geladen worden (`includes/class-pdk-settings.php:166-168`) |

Beide staan in de optie `pdk_theme_options`. De lijst wordt opgeslagen op de Libraries-tab (`includes/class-pdk-admin.php:1588-1600`).

### Waarom een blocklist en geen allowlist

Opgeslagen wordt wat níét laadt, niet wat wél laadt (`modules/libraries/class-pdk-libraries.php:80-90`, `includes/class-pdk-settings.php:166-167`). Gevolg: een nieuw geüpload bestand laadt meteen, zonder dat iemand er eerst een vinkje bij hoeft te zetten. Bij een allowlist zou elke upload standaard dood in de map liggen, met als voorspelbare uitkomst dat iemand "de library werkt niet" meldt terwijl het bestand er gewoon staat.

De keerzijde is afgevangen: een naam die ooit is uitgezet blijft anders in de lijst staan, waardoor een later opnieuw geüpload bestand met dezelfde naam niet zou laden. `forget_disabled()` haalt de naam daarom uit de lijst bij zowel upload als verwijdering (`modules/libraries/class-pdk-libraries.php:92-110`, `includes/class-pdk-admin.php:1720`, `includes/class-pdk-admin.php:1747`).

`save_libraries()` schrijft de lijst bewust niet via `PDK_Settings::update()`: `array_replace_recursive()` voegt lijsten per index samen, waardoor een weer ingeschakeld bestand uit zou blijven (`includes/class-pdk-admin.php:1596-1600`).

## Hoe het werkt

De constructor haakt `enqueue()` op `wp_enqueue_scripts` met standaardprioriteit (`modules/libraries/class-pdk-libraries.php:16-18`). Die methode loopt over `scan()` en slaat per bestand over wanneer:

1. de extensie niet laadbaar is — `.map` staat wel in de map, maar wordt nooit ingeladen (`:41-43`, `:119`);
2. het bestand in de uit-lijst staat (`:88-90`, `:119`);
3. het onleesbaar of leeg is (`:125-127`);
4. de vingerafdruk niet klopt (`:131-133`).

Wat overblijft krijgt de handle `pdk-lib-<sanitize_title van de bestandsnaam zonder extensie>` (`:112-115`) en `filemtime()` als versie, zodat een nieuwe upload de browsercache omzeilt (`:136`). CSS gaat via `wp_enqueue_style` in de head, JS via `wp_enqueue_script` met `true` in de footer (`:138-143`).

`scan()` doet één `glob()` per toegestane extensie en sorteert het resultaat alfabetisch (`:66-78`). **De laadvolgorde is die sortering**, dus een cijferprefix (`10-swiper.min.js`, `20-slider-init.js`) is de manier om afhankelijkheden af te dwingen (`:60-64`).

De integriteitscontrole gebruikt `rel_path()` — `libraries/<bestand>` — als sleutel (`:53-56`), en `pdk_watched_files()` neemt de libraries alleen mee als de klasse bestaat, dus als de module aan staat (`includes/helpers.php:334-338`). Het mechanisme zelf staat in [custom-functions.md](custom-functions.md#integriteitscontrole--het-mechanisme).

## Bestanden en gegevens

| | |
|---|---|
| Map | `wp-content/uploads/pdk-theme-options/libraries/` (`modules/libraries/class-pdk-libraries.php:45-51`, `includes/helpers.php:10`) |
| Toegestaan bij upload | `js`, `css`, `map` (`modules/libraries/class-pdk-libraries.php:27-29`) |
| Daadwerkelijk ingeladen | `js`, `css` (`:31-34`) |
| Back-up | `<bestand>.bak` bij bewerken via de editor (`includes/helpers.php:104-106`) |

### Upload — de trust boundary

Een JS-bestand uploaden is code op de site zetten, dus dat vraagt dezelfde rechten als de code-editor, niet alleen `manage_options`. `handle_library_upload()` eist `PDK_CAP_EDIT_CODE` en een nonce (`includes/class-pdk-admin.php:1624-1629`); hetzelfde geldt voor `save_libraries()`, dat bewerken, verwijderen en de uit-lijst afhandelt (`includes/class-pdk-admin.php:1565-1568`). Wie de capability mist, ziet het uploadformulier niet en krijgt uitgeschakelde vinkjes (`includes/class-pdk-admin.php:1424`, `includes/class-pdk-admin.php:1449-1451`, `includes/class-pdk-admin.php:1491`).

Per bestand doet `store_library_upload()` (`includes/class-pdk-admin.php:1688-1730`):

1. `basename()` + `preg_replace( '/[^a-zA-Z0-9._-]/', '' )` op de naam. Bewust geen `sanitize_file_name()`: die maakt van `glide.min.js` een `glide.min_.js` (`:1689-1692`).
2. Weigeren bij een uploadfout, een lege naam of een naam die met een punt begint (`:1695-1703`).
3. **Extensiecontrole tegen de allowlist `js`/`css`/`map`** — dit is wat voorkomt dat er een `.php` in de map belandt (`:1705-1712`). Het `accept`-attribuut op het formulier is alleen comfort (`:1457`); de serverzijde beslist.
4. `is_uploaded_file()` + `move_uploaded_file()` (`:1714-1717`).
5. `forget_disabled()` en `pdk_store_file_hash()` op de nieuwe inhoud — vanaf dat moment telt elke wijziging buiten de editor om als manipulatie (`:1720-1727`).

Bewerken en verwijderen accepteren alleen namen die daadwerkelijk in `scan()` voorkomen, wat `../`-trucs uitsluit (`includes/class-pdk-admin.php:1610-1612`, `includes/class-pdk-admin.php:1740-1742`). Schrijven loopt via `pdk_write_storage_file()`, waarvan `pdk_storage_rel_path()` alleen de ene libraries-submap doorlaat en de rest tot een kale bestandsnaam terugbrengt (`includes/helpers.php:283-288`).

De `.htaccess` in de storage-map blokkeert directe toegang tot `.php` en `.bak` en zet directorylisting uit; hij geldt ook voor de libraries-submap (`includes/helpers.php:36-43`).

**Bij uninstall** blijven de geüploade bestanden staan — de hele klantmap wordt bewust niet opgeruimd (`uninstall.php:5-6`). De uit-lijst verdwijnt met `pdk_theme_options`, de vingerafdrukken met `pdk_file_hashes` (`uninstall.php:16-17`).

## Voor themaontwikkelaars

Publieke statische methoden op `PDK_Libraries`, bruikbaar zonder instantie:

| Methode | Levert |
|---|---|
| `PDK_Libraries::scan()` | alle geüploade bestandsnamen, alfabetisch (`:66-78`) |
| `PDK_Libraries::dir()` / `::url()` | pad en URL van de libraries-map (`:45-51`) |
| `PDK_Libraries::handle( $bestand )` | de WordPress-handle, bruikbaar als `deps` van een eigen script (`:112-115`) |
| `PDK_Libraries::is_enabled( $bestand )` | staat het bestand aan (`:88-90`) |
| `PDK_Libraries::is_enqueueable( $bestand )` | wordt de extensie ingeladen (`:41-43`) |
| `PDK_Libraries::rel_path( $bestand )` | `libraries/<bestand>`, de sleutel van de integriteitscontrole (`:53-56`) |

Voorbeeld: `wp_enqueue_script( 'mijn-init', …, [ PDK_Libraries::handle( '10-glide.min.js' ) ] );`. Er zijn geen filters of acties in deze module.

## Grenzen en valkuilen

- **Laadvolgorde = alfabetische bestandsnaam.** Zonder cijferprefix laadt `slider-init.js` vóór `swiper.min.js` (`:60-64`).
- **Alles laadt overal.** Elk ingeschakeld bestand komt op elke frontend-pagina; er is geen per-pagina-selectie.
- **`glob()` is hoofdlettergevoelig op Linux.** De patronen zijn `*.js`, `*.css` en `*.map` (`:69-70`); een bestand `GLIDE.JS` komt niet in `scan()` en is dus onzichtbaar in de admin én op de frontend.
- **Sourcemaps hebben geen vinkje.** Ze staan in de lijst maar zijn niet laadbaar; ze horen er alleen om een 404 in de browserconsole te voorkomen (`:20-29`, `includes/class-pdk-admin.php:1485-1493`).
- **Gelijke namen overschrijven elkaar.** `move_uploaded_file()` schrijft over een bestaand bestand heen zonder waarschuwing (`includes/class-pdk-admin.php:1714`), en de vingerafdruk wordt meteen bijgewerkt.
- **Een leeg bestand wordt stil overgeslagen** (`:125-127`) — een mislukte upload van 0 bytes ziet er in de lijst gewoon uit.
- **Een bestand dat buiten de editor om verandert laadt niet meer**, met een rode markering in de lijst (`includes/class-pdk-admin.php:1499-1501`). Dat treft ook bestanden die via FTP worden bijgewerkt.
- **De libraries vallen alleen onder de integriteitscontrole zolang de module aan staat**, want `pdk_watched_files()` kijkt naar `class_exists( 'PDK_Libraries' )` (`includes/helpers.php:334-338`).

## Zelftest

```
php tests/run.php
```

Losse run: `php tests/test-libraries.php`. Gedekt: lege map, filtering op extensie en alfabetische volgorde (`tests/test-libraries.php:74-85`), CSS als style en JS als script in de footer met de juiste handle, URL en `filemtime`-versie (`:91-99`), een `.php` in de map wordt genegeerd (`:100`), uitzetten laat het bestand op schijf staan maar stopt het laden (`:104-115`), `forget_disabled()` zet het weer aan (`:118-122`), sourcemaps staan in de lijst maar laden niet (`:130-143`), een gemanipuleerd bestand wordt overgeslagen terwijl de rest doorlaadt (`:150-161`) en een leeg bestand wordt overgeslagen (`:167-171`).
