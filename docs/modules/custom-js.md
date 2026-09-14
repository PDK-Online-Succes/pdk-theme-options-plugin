# Custom JavaScript

De module laadt het klantbestand `custom-script.js` onderaan de frontend-pagina. Het bestand staat buiten de pluginmap en overleeft daarmee plugin-updates en themawissels (`modules/custom-js/class-pdk-custom-js.php:17-36`).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `custom_js` (`includes/class-pdk-plugin.php:20`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php:156-158`) |
| Eigen tab | Ja — "Custom JS", alleen als de module aan staat (`includes/class-pdk-admin.php:740`, `includes/class-pdk-admin.php:750-754`) |

## Instellingen

Geen, buiten de aan/uit-schakelaar. De tab bevat de CodeMirror-editor voor het bestand (`includes/class-pdk-admin.php:793-795`).

## Hoe het werkt

De constructor haakt `enqueue()` op `wp_enqueue_scripts` met prioriteit 10 (`modules/custom-js/class-pdk-custom-js.php:13-15`). De methode stopt bij een ontbrekend of leeg bestand (`:20-22`) en bij een afwijkende vingerafdruk (`:25-27`). Anders wordt het script geregistreerd onder de handle `pdk-custom-script`, met `filemtime()` als versie en `true` als laatste argument — dus in de footer, zodat de DOM al staat en het script het renderen niet blokkeert (`:29-35`).

## Bestanden en gegevens

| | |
|---|---|
| Klantbestand | `wp-content/uploads/pdk-theme-options/custom-script.js` |
| Back-up | `custom-script.js.bak` (`includes/helpers.php:104-106`) |
| Aangemaakt bij | activatie en eerste admin-bezoek (`includes/class-pdk-plugin.php:129-132`, `includes/class-pdk-plugin.php:183-186`) |
| Bewerken mag | alleen met `pdk_edit_custom_code` (`includes/class-pdk-admin.php:518-520`, `includes/class-pdk-admin.php:1126-1134`) |

Het bestand staat in `pdk_code_files()` (`includes/helpers.php:272-274`) en wordt bij manipulatie niet uitgeserveerd; het mechanisme staat in [custom-functions.md](custom-functions.md#integriteitscontrole--het-mechanisme).

**Bij uninstall** blijft het bestand staan (`uninstall.php:5-6`).

## Grenzen en valkuilen

- **Geen `deps`, dus geen jQuery-garantie.** Het script wordt zonder afhankelijkheden ingeschreven; wie `jQuery` gebruikt, moet erop vertrouwen dat het thema die al inlaadt, of het in een `DOMContentLoaded`-handler zetten.
- **Een leeg bestand laadt niet**, dus `wp_add_inline_script( 'pdk-custom-script', … )` heeft dan geen doel (`:20-22`).
- **Alleen frontend** — `wp_enqueue_scripts` vuurt niet in de admin.
- **Geen syntaxcontrole bij opslaan.** De controle in `pdk_write_storage_file()` geldt alleen voor `.php` (`includes/helpers.php:87-101`); een kapotte JS wordt gewoon opgeslagen en uitgeserveerd.
- **Wijzigen buiten de editor om stopt het laden** — zie de integriteitscontrole.

## Zelftest

```
php tests/run.php
```

Geen aparte test voor deze module. `tests/test-file-integrity.php` dekt de gedeelde schrijf- en controlelaag waar `custom-script.js` onder valt.
