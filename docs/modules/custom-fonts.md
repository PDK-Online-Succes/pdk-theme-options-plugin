# Custom Fonts

De module scant `wp-content/uploads/fonts/` recursief op fontbestanden, leidt familie, gewicht en stijl af uit de bestandsnaam en genereert daaruit de `@font-face`-regels — inline in de `<head>` of als gecacht CSS-bestand. Zo hoeft niemand handmatig `@font-face`-blokken te onderhouden, en de fonts staan buiten de pluginmap en overleven een update (`modules/custom-fonts/class-pdk-custom-fonts.php:2-15`).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `custom_fonts` (`includes/class-pdk-plugin.php:21`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php:159-163`) |
| Eigen tab | Ja — "Fonts", alleen als de module aan staat (`includes/class-pdk-admin.php:741`, `includes/class-pdk-admin.php:750-754`). De tab heeft eigen formulieren en staat in `$standalone_tabs` (`includes/class-pdk-admin.php:679`) |

## Instellingen

Alle drie in de optie `pdk_theme_options` onder `custom_fonts` (`includes/class-pdk-settings.php:159-163`), te bedienen op de Fonts-tab (`includes/class-pdk-admin.php:1198-1228`).

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `enabled` | bool | `false` | Module laden ja/nee |
| `display` | string | `swap` | Waarde van `font-display` in elke `@font-face` (`modules/custom-fonts/class-pdk-custom-fonts.php:41`, `:346`) |
| `css_output` | string | `inline` | `inline` = `<style>` in de head, `file` = `<link>` naar een gecacht bestand (`:47-55`) |

Opslaan loopt via de eigen handler `admin_post_pdk_save_fonts_display`, met `manage_options` en nonce; `css_output` wordt tegen de lijst `[inline, file]` gecontroleerd (`includes/class-pdk-admin.php:546-571`).

## Hoe het werkt

`output_font_css()` hangt op zowel `wp_head` als `admin_head`, beide op prioriteit 5 — vroeg, zodat de fonts vóór de themastijlen gedefinieerd zijn, en ook in de admin zodat de blok-editor de families kan tonen (`modules/custom-fonts/class-pdk-custom-fonts.php:28-29`, `:39-61`). Zijn er geen fonts, dan gebeurt er niets (`:43-45`).

**Scannen.** `collect_fonts_grouped()` loopt met een `RecursiveDirectoryIterator` door de fontmap, houdt alleen de extensies `woff2`, `woff`, `ttf` en `otf` over, en groepeert naar `[familie][gewicht/stijl][formaat] => url`. Elk padsegment wordt afzonderlijk `rawurlencode`-d, zodat submappen en spaties in namen werken; de familienamen worden natuurlijk gesorteerd (`:128-165`). `scan_fonts()` doet hetzelfde werk maar levert per familie een platte variantenlijst met bestandsnaam, grootte en een `?v=<mtime>`-URL voor de admin-tabel (`:173-217`).

**Naam ontleden.** `parse_font_name()` haalt eerst de stijl uit de naam (`italic`/`oblique`), daarna het gewicht via een geordende reeks regexes. De volgorde telt: specifieker vóór minder specifiek, zodat `ExtraLight` niet als `Light` eindigt, en de woordgrenzen zorgen dat `SemiCondensed` niet als gewicht `600` wordt gelezen. Variable fonts worden herkend aan `VariableFont` of `[wght]` en krijgen het pseudogewicht `var` (`:228-279`). Wat overblijft na het strippen van `-webfont` en de optische-as-suffixen is de familienaam; is dat leeg, dan valt hij terug op de volledige basisnaam (`:264-277`).

**CSS bouwen.** `build_all_font_faces()` schrijft per familie en per gewicht/stijl één `@font-face`, met alle beschikbare formaten in één `src:`-regel in de voorkeursvolgorde van `FORMATS` (woff2 eerst). Variable fonts krijgen `font-weight: 1 1000` (`:320-351`, `:22`).

**Bestand of inline.** Bij `css_output = file` schrijft `get_or_generate_css_file()` het resultaat naar `uploads/fonts/pdk-custom-fonts.css`, maar alleen als de inhoud is veranderd — vergeleken via een crc32-hash — en geeft de URL terug met die hash als `?ver=`. Is de map niet beschrijfbaar, dan geeft de methode `null` terug en valt `output_font_css()` stil terug op inline CSS (`:364-382`, `:49-60`).

**Gutenberg.** `theme_json_inject_fonts()` haakt rechtstreeks (niet via de loader) op `wp_theme_json_data_theme` en voegt elke gevonden familie toe aan `settings.typography.fontFamilies`, met een uit de naam afgeleide slug, zodat de fonts in de blok-editor te kiezen zijn (`:32`, `:67-91`).

## Bestanden en gegevens

| | |
|---|---|
| Fontmap | `wp-content/uploads/fonts/` — afgeleid van `wp_get_upload_dir()['basedir']` (`:97-105`) |
| Gegenereerde CSS | `uploads/fonts/pdk-custom-fonts.css` (`:25`, `:364-382`) |
| Naamconventie | `FamilyName-Weight.ext`, bijv. `Raleway-Bold.woff2`, `Montserrat-LightItalic.ttf`, `Raleway[wght].woff2` (`:9-14`, `:221`) |

### Upload en verwijderen — de trust boundary

Beide handlers vragen **`manage_options`** plus een nonce — niet de code-capability, anders dan bij Libraries (`includes/class-pdk-admin.php:573-578`, `includes/class-pdk-admin.php:627-632`).

Bij uploaden (`includes/class-pdk-admin.php:573-625`):

1. Eén bestand per keer, en alleen bij `UPLOAD_ERR_OK` (`:585-588`).
2. **Extensiecontrole tegen `PDK_Custom_Fonts::allowed_extensions()`** — dat is dezelfde constante `FORMATS`: `woff2`, `woff`, `ttf`, `otf` (`:590-600`, `modules/custom-fonts/class-pdk-custom-fonts.php:22`, `:107-110`). Dit is wat afdwingt dat er niets anders in de map belandt; het `accept`-attribuut op het formulier is alleen comfort (`includes/class-pdk-admin.php:1307`).
3. Bestandsnaam saneren met `basename()` + `preg_replace( '/[^a-zA-Z0-9._-]/', '' )` (`:602-607`).
4. Map aanmaken indien nodig, daarna `move_uploaded_file()` (`:609-621`).

Bij verwijderen wordt naast de nonce gecontroleerd op geldige extensie, `realpath()` binnen de fontmap (padtraversal) én `is_file()` (`includes/class-pdk-admin.php:645-653`).

**De fontbestanden vallen níét onder de integriteitscontrole.** `pdk_watched_files()` kent alleen de drie editor-bestanden en de libraries (`includes/helpers.php:331-341`); de fontmap staat bovendien buiten `PDK_STORAGE_DIR` en krijgt dus ook niet de beschermende `.htaccess` uit `pdk_ensure_storage_dir()` (`includes/helpers.php:27-51`). Zie [custom-functions.md](custom-functions.md#integriteitscontrole--het-mechanisme) voor wat dat mechanisme wél dekt.

**Bij uninstall** blijven de fonts en het gegenereerde CSS-bestand staan; `uninstall.php` raakt `uploads/fonts/` niet aan en verwijdert alleen opties en de capability (`uninstall.php:15-33`, `uninstall.php:59-75`).

## Voor themaontwikkelaars

Publieke statische methoden op `PDK_Custom_Fonts`:

| Methode | Levert |
|---|---|
| `get_font_families()` | platte lijst met familienamen (`:117-119`) |
| `collect_fonts_grouped()` | `[familie][gewicht/stijl][formaat] => url` (`:128-165`) |
| `scan_fonts()` | per familie een variantenlijst met `weight`, `style`, `src`, `format`, `file`, `size`, `basename` (`:173-217`) |
| `build_all_font_faces( $fonts, $display )` | de `@font-face`-CSS als string (`:320-351`) |
| `parse_font_name( $basename )` | `[ family, weight, style ]` (`:228-279`) |
| `weight_label( $weight, $style )` | leesbaar label, bijv. "Semi Bold Italic" (`:282-301`) |
| `font_dir()` / `font_url()` | pad en URL van de fontmap (`:97-105`) |
| `allowed_extensions()` / `get_format( $ext )` | toegestane extensies, en de CSS-`format()`-naam (`:107-110`, `:303-311`) |

Gebruik in CSS simpelweg de familienaam zoals hij in de admin-tabel staat: `font-family: 'Raleway', sans-serif;`. De module registreert geen shortcodes, filters of acties; ze haakt zelf op `wp_theme_json_data_theme`.

## Grenzen en valkuilen

- **De bestandsnaam is de configuratie.** Er is geen handmatige override: heet een bestand `Raleway-SemiCondensed.woff2`, dan bepaalt de regex wat familie en gewicht worden (`:228-262`). Hernoemen is de enige correctie.
- **Elke map-scan draait per request.** `collect_fonts_grouped()` wordt bij elke `wp_head` én `admin_head` opnieuw uitgevoerd; er is geen transient-cache. Bij veel fonts in diepe submappen is `css_output = file` de goedkopere optie, maar ook dan blijft de scan zelf draaien (`:39-61`).
- **`display` wordt niet tegen een lijst gecontroleerd.** `css_output` wel (`includes/class-pdk-admin.php:553-557`), `display` gaat alleen door `sanitize_key()` en `esc_attr()` (`includes/class-pdk-admin.php:561`, `:346`). Een onzinwaarde levert een `font-display`-regel op die de browser negeert.
- **Twee opslagpaden voor `display`.** Naast de eigen handler kent `handle_save()` nog een `custom_fonts`-tak die alleen `display` wegschrijft (`includes/class-pdk-admin.php:328-330`, `includes/class-pdk-admin.php:469-477`); de Fonts-tab zelf post naar de eigen handler.
- **Het CSS-bestand wordt niet opgeruimd** wanneer je terugschakelt naar inline of het laatste font verwijdert; `pdk-custom-fonts.css` blijft in de fontmap staan (`:364-382`).
- **Geen schrijfrechten = stille terugval** naar inline CSS, zonder melding aan de beheerder (`:369-371`, `:49-55`).
- **Uploaden vraagt alleen `manage_options`**, terwijl een library-upload de code-capability vereist. Fontbestanden zijn geen uitvoerbare code, maar het verschil is goed om te weten wanneer je rechten uitdeelt (`includes/class-pdk-admin.php:574`, `includes/class-pdk-admin.php:1625`).
- **Uploaden kan alleen in de hoofdmap**, terwijl de scan wél recursief is: submappen vul je via FTP of de bestandsbeheerder (`includes/class-pdk-admin.php:616`, `:138-142`).
- **Gelijknamige bestanden overschrijven elkaar** zonder waarschuwing (`includes/class-pdk-admin.php:618`).

## Zelftest

```
php tests/run.php
```

Er is geen zelftest voor deze module; `tests/` bevat geen `test-custom-fonts.php`. De naamontleding in `parse_font_name()` en de CSS-generatie zijn dus niet geautomatiseerd gedekt — controleer na het toevoegen van een nieuwe familie handmatig de kolom "Variant" op de Fonts-tab (`includes/class-pdk-admin.php:1265-1270`).
