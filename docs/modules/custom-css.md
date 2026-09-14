# Custom CSS

De module laadt het klantbestand `custom-style.css` uit de storage-map op de frontend. Het bestand staat buiten de pluginmap en overleeft daarmee plugin-updates en themawissels (`modules/custom-css/class-pdk-custom-css.php:17-35`).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `custom_css` (`includes/class-pdk-plugin.php:19`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php:153-155`) |
| Eigen tab | Ja — "Custom CSS", alleen als de module aan staat (`includes/class-pdk-admin.php:739`, `includes/class-pdk-admin.php:750-754`) |

## Instellingen

Geen, buiten de aan/uit-schakelaar. De tab bevat de CodeMirror-editor voor het bestand (`includes/class-pdk-admin.php:790-792`).

## Hoe het werkt

De constructor haakt `enqueue()` op `wp_enqueue_scripts` met de standaardprioriteit 10 (`modules/custom-css/class-pdk-custom-css.php:13-15`). Die methode slaat over als het bestand niet bestaat of leeg is (`:20-22`), slaat over als de integriteitscontrole aanslaat (`:25-27`), en registreert anders de stylesheet onder de handle `pdk-custom-style` met `filemtime()` als versie voor cache-busting (`:29-34`).

Omdat het op `wp_enqueue_scripts` gebeurt zonder afhankelijkheden, laadt het bestand in de gewone volgorde van de wachtrij — een themastijl die later wordt ingeschreven, komt er dus ná.

## Bestanden en gegevens

| | |
|---|---|
| Klantbestand | `wp-content/uploads/pdk-theme-options/custom-style.css` |
| Back-up | `custom-style.css.bak` (`includes/helpers.php:104-106`) |
| Aangemaakt bij | activatie en eerste admin-bezoek (`includes/class-pdk-plugin.php:125-128`, `includes/class-pdk-plugin.php:179-182`) |
| Bewerken mag | alleen met `pdk_edit_custom_code` (`includes/class-pdk-admin.php:518-520`, `includes/class-pdk-admin.php:1126-1134`) |

Het bestand valt onder de integriteitscontrole: het staat in `pdk_code_files()` (`includes/helpers.php:272-274`) en wordt bij een afwijkende vingerafdruk niet uitgeserveerd. Het mechanisme staat beschreven in [custom-functions.md](custom-functions.md#integriteitscontrole--het-mechanisme).

**Bij uninstall** blijft het bestand staan; alleen opties en capability verdwijnen (`uninstall.php:5-6`, `uninstall.php:16-17`).

## Grenzen en valkuilen

- **Een leeg bestand laadt niet.** `0 === filesize()` is een harde stop, dus de handle `pdk-custom-style` bestaat dan niet en `wp_add_inline_style( 'pdk-custom-style', … )` vanuit een thema doet niets (`:20-22`).
- **Geen `deps`.** De stylesheet is niet aan een themastijl gekoppeld; wie zeker wil zijn dat hij later komt, moet in het thema de volgorde regelen.
- **Alleen frontend.** `wp_enqueue_scripts` vuurt niet in de admin en niet op de loginpagina.
- **Wijzigen buiten de editor om stopt het laden** — zie de integriteitscontrole. Een CSS-bestand dat plots niet meer laadt na een FTP-deploy is bijna altijd dit.

## Zelftest

```
php tests/run.php
```

Geen aparte test voor deze module. `tests/test-file-integrity.php` dekt de gedeelde schrijf- en controlelaag waar `custom-style.css` onder valt.
