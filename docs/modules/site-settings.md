# Site Instellingen

Site Instellingen vervangt de oude Carbon Fields-integratie en beheert alles wat site-breed vastligt: favicon, klantlogo, bedrijfs- en contactgegevens, social-links, openingstijden per weekdag en de afwijkende periodes (kerst, zomerperiode, bedrijfsvakantie). De periodelijst is de gedeelde bron: dezelfde rijen sturen de openingstijden-tabel, de niet-verzenddagen van Levertijden en de sluiting van de Vakantiemodus aan, zodat een klant een sluitingsdag maar op één plek invult.

## Aan- en uitzetten

Geen modulesleutel en geen toggle — de module wordt onvoorwaardelijk ingeladen in `PDK_Plugin::init()` (`pdk-theme-options/includes/class-pdk-plugin.php:55-56`), dus vóór en los van `$module_map`. De Modules-tab meldt dit ook expliciet als "altijd ingeschakeld" (`pdk-theme-options/includes/class-pdk-admin.php:836-839`). De tab **Site Instellingen** staat altijd in de tabbalk (`class-pdk-admin.php:729-734`) met vijf sub-secties in één formulier: Basis, Klantgegevens, Openingstijden, Afwijkende dagen, Social Media (`class-pdk-admin.php:892-898`). Eén keer opslaan bewaart alle secties. Geen WooCommerce-afhankelijkheid.

De constructor roept `migrate_periods()` direct aan, niet op een hook, omdat Vakantiemodus en Levertijden de periodes al vóór `init` uitlezen (`modules/site-settings/class-pdk-site-settings.php:20-22`).

## Instellingen

Alles staat onder `pdk_theme_options['site_settings']`; standaarden in `includes/class-pdk-settings.php:110-145`.

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `favicon_url` | URL | `''` | Favicon, uitgestuurd in `<head>` |
| `disable_page_editor` | bool | `false` | Blokkeert Gutenberg op posttype `page` |
| `client_logo` | URL | `''` | Klantlogo voor thema-gebruik |
| `company_name` | string | `''` | Bedrijfsnaam |
| `company_street` / `company_number` | string | `''` | Straat en huisnummer |
| `company_zipcode` / `company_city` | string | `''` | Postcode en plaats |
| `company_phone` | string | `''` | Telefoonnummer |
| `company_email` | e-mail | `''` | E-mailadres (`sanitize_email`, `class-pdk-admin.php:441`) |
| `social_facebook`, `social_instagram`, `social_linkedin`, `social_twitter`, `social_youtube`, `social_tiktok` | URL | `''` | Social-profielen |
| `opening_hours` | array 1-7 | ma-vr 07:00-17:30, za 08:00-12:30, zo gesloten | Per weekdag `closed`/`open`/`close` |
| `periods` | lijst | `[]` | Afwijkende periodes, zie hieronder |

Sanitatie bij opslaan: URL-velden via `esc_url_raw`, tekstvelden via `sanitize_text_field`, tijden alleen geaccepteerd als ze aan `/^\d{2}:\d{2}$/` voldoen — anders leeg (`class-pdk-admin.php:380-388`, `:431-450`).

### De gedeelde `periods`-structuur

Elke rij is een associatieve array met zes sleutels (`class-pdk-site-settings.php:170-177`):

| Sleutel | Type | Betekenis |
|---|---|---|
| `from` | `Y-m-d` | Begindatum; rijen zonder `from` worden overgeslagen |
| `to` | `Y-m-d` | Einddatum, inclusief; leeg of vóór `from` → gelijk aan `from` (`class-pdk-admin.php:401-404`) |
| `label` | string | Omschrijving, bv. "Kerst"; verschijnt in de melding boven de openingstijden |
| `open` / `close` | `HH:MM` | Afwijkende tijden; leeg = gesloten. Eén van beide leeg telt ook als gesloten, en bij het opslaan worden ze dan allebei leeggemaakt |
| `close_shop` | bool | Sluit de webshop via de Vakantiemodus tijdens deze periode |

Halve invoer bestaat niet: is één van `open`/`close` ongeldig, dan worden beide leeggemaakt en telt de periode als gesloten (`class-pdk-admin.php:409-414`). Periodes mogen overlappen; `matching_periods()` geeft alles terug wat op een datum valt, `active_period()` neemt daarvan de vroegst begonnene — de lijst is gesorteerd op `from` (`class-pdk-site-settings.php:180`, `:191-207`).

De drie afgeleide vragen die andere modules stellen:

- **Gesloten op datum X?** `is_closed_on()` — een periode zonder tijden telt als dicht, anders beslist de weekdagregel (`:237-248`).
- **Webshop nu dicht?** `shop_closed_now()` — waar wanneer een lopende periode `close_shop` heeft (`:210-218`).
- **Staat er überhaupt een sluiting gepland?** `has_shop_closures()` (`:221-229`).

## Hoe het werkt

De favicon wordt op `wp_head` prioriteit 1 uitgestuurd, zodat de `<link rel="icon">` zo hoog mogelijk in de head staat (`class-pdk-site-settings.php:24`, `:42-52`). Het uitschakelen van de pagina-editor gebeurt op `init`: pas daar wordt de filter `use_block_editor_for_post_type` toegevoegd, en alleen voor posttype `page` (`:25`, `:55-66`).

De openingstijden-tabel koppelt elke weekdag aan de eerstvolgende concrete datum via `upcoming_week()` — vandaag plus nul t/m zes dagen, geïndexeerd op `date('N')` (`:332-343`). Daardoor landt een afwijkende periode op de juiste rij. Per rij overschrijft een lopende periode de weekdagregel volledig; lege periodetijden betekenen "Gesloten" (`:113-119`). Labels van gevonden periodes verzamelen zich in één melding boven de tabel: "Let op: afwijkende openingstijden i.v.m. …" (`:139-148`). Een dag geldt als gesloten bij `closed`, maar ook bij een half ingevulde tijd (`:122`).

`migrate_periods()` verhuist eenmalig de oude `delivery_time.exceptions` en `vacation_mode.start_date`/`end_date` naar `site_settings.periods` (`:256-325`). Oude uitzonderingsdata waren altijd volledig dicht en krijgen dus lege tijden (`:283-284`); de vakantieperiode krijgt `close_shop = true` en wordt samengevoegd met een uitzonderingsrij met exact dezelfde datums in plaats van een tweede rij op te leveren (`:299-317`). De functie leest bewust `get_option()` in plaats van `PDK_Settings::get()`: die laatste geeft een lege array als de optie nog niet bestaat, en zou de migratie de optie laten aanmaken — dan denkt `maybe_first_run()` dat de plugin al geïnitialiseerd is en blijven standaardwaarden en storage-bestanden achterwege (`:257-263`, vgl. `class-pdk-plugin.php:166-168`).

Opslaan gaat via `PDK_Admin::save_site_settings()`, dat bewust `array_merge` op de hele `site_settings`-array gebruikt en niet `PDK_Settings::update()`: die laatste merget recursief, waardoor verwijderde periode-rijen zouden blijven staan (`class-pdk-admin.php:426-431`). Rijen met het vinkje `delete` worden overgeslagen (`:392-394`).

## Bestanden en gegevens

Alles staat in de ene optie `pdk_theme_options`, onder de sleutel `site_settings` (`class-pdk-settings.php:10`). De module schrijft geen eigen opties, geen postmeta en geen bestanden; de favicon en het logo zijn URL's naar de mediabibliotheek, de bijbehorende attachments blijven eigendom van WordPress. Bij uninstall verdwijnt `pdk_theme_options` in zijn geheel (`pdk-theme-options/uninstall.php:16-18`), dus ook de klantgegevens, openingstijden en periodes; geüploade media blijven staan.

## Voor themaontwikkelaars

| Functie | Teruggave |
|---|---|
| `pdk_site_setting( $key )` | Eén site-instelling, ge-escaped via `esc_html` (`class-pdk-site-settings.php:355-357`) |
| `pdk_client_logo_url()` | Logo-URL via `esc_url`, bedoeld voor `src=` (`:362-364`) |
| `pdk_company_address()` | "Straat 12, 1234 AB Plaats", lege delen weggefilterd (`:370-381`) |
| `pdk_opening_hours_html( $title = '' )` | De openingstijden-tabel als HTML-string (`:387-389`) |
| `PDK_Site_Settings::periods()`, `matching_periods()`, `active_period()`, `shop_closed_now()`, `has_shop_closures()`, `is_closed_on()`, `opening_hours()` | Publieke statics, gebruikt door Levertijden en Vakantiemodus |

Uit `includes/helpers.php`: `pdk_day_labels()` geeft Nederlandse dagnamen geïndexeerd 1-7, gelijk aan `date('N')` (`helpers.php:132-142`).

Frontend-uitvoer is via `pdk_register_frontend_output()` op twee manieren beschikbaar (`helpers.php:158-168`): shortcode `[openingstijden title="…"]` en template-hook `do_action( 'pdk_openingstijden', [ 'title' => '…' ] )`. De HTML: optionele `<h3 class="pdk-openingstijden__title">`, optionele `<p class="pdk-openingstijden__notice">`, dan `<table class="pdk-openingstijden">` met zeven `<tr class="pdk-openingstijden__row">` (gesloten dagen krijgen `--closed` erbij). De plugin laadt geen eigen frontend-CSS; opmaak doe je via Custom CSS op deze klassen (`class-pdk-site-settings.php:28-38`).

## Grenzen en valkuilen

- De tabel toont altijd de **komende** zeven dagen vanaf vandaag, ma t/m zo gerenderd. Een periode die over drie weken begint verandert de tabel niet — pas als hij binnen zeven dagen valt (`:332-343`).
- Bij overlappende periodes wint de vroegst begonnene voor tijden en label; voor `close_shop` telt echter élke overlappende rij mee (`:210-218`). Twee overlappende periodes kunnen dus tegelijk "open volgens periode A" en "webshop dicht via periode B" opleveren.
- Een `to` vóór `from` wordt stil teruggezet naar `from` in plaats van geweigerd (`class-pdk-admin.php:402-404`). De invoerder ziet geen foutmelding.
- `pdk_site_setting()` geeft `esc_html`-uitvoer terug; gebruik hem dus niet binnen een attribuut waar `esc_attr` nodig is, en niet dubbel escapen.
- `migrate_periods()` draait bij élke pageload, maar valt meteen terug zodra `site_settings.periods` bestaat (`:268-270`). Verwijder je die sleutel handmatig uit de database, dan draait de migratie opnieuw en kan hij oude sleutels herintroduceren.
- `is_closed_on()` bouwt een `DateTimeImmutable` uit de meegegeven datum; een ongeldige datumstring werpt een exception in plaats van `false` terug te geven (`:245`).

## Zelftest

```
php tests/run.php
```

Twee bestanden raken deze module. `tests/test-opening-hours.php` dekt de standaardweek, het per-veld overschrijven van opgeslagen waarden, "gesloten" dat wint van ingevulde tijden, de halve tijd als gesloten, het ge-escapete titel-attribuut, een gesloten kerstperiode met melding, een periode mét tijden die ook zondag opent, en periodes buiten de komende week. `tests/test-migrate-periods.php` dekt de acht migratiepaden: uitzonderingsdata, vakantiedatums, samenvoegen van identieke periodes, alleen-startdatum, vakantiemodus zonder datums, eenmalig draaien, een schone bestaande site, en de optie die nog helemaal niet bestaat.
