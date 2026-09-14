# Custom PHP Functions

De module laadt het klantbestand `custom-functions.php` uit de storage-map buiten de pluginmap, zodat eigen PHP-code een plugin-update én een themawissel overleeft — het alternatief, `functions.php` van het thema, verdwijnt bij allebei. Daarnaast registreert de module een vaste set WordPress- en WooCommerce-aanpassingen die op elke PDK-site van toepassing zijn (`modules/custom-functions/class-pdk-custom-functions.php:22-45`).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `custom_functions` (`includes/class-pdk-plugin.php:18`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php:150-152`) |
| Eigen tab | Ja — "PHP Functions", alleen zichtbaar als de module aan staat (`includes/class-pdk-admin.php:738`, `includes/class-pdk-admin.php:750-754`) |

Staat de module uit, dan wordt de klasse niet ingeladen (`includes/class-pdk-plugin.php:87-98`) en draaien ook de vaste optimalisaties hieronder niet.

## Instellingen

Alleen de aan/uit-schakelaar. De tab bevat geen opties maar de code-editor voor het bestand (`includes/class-pdk-admin.php:786-789`).

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `custom_functions.enabled` | bool | `false` | Module laden ja/nee (`includes/class-pdk-settings.php:150-152`) |

Opslaan gebeurt via `admin_post_pdk_save_settings` → `save_code_file()`, dat eerst de code-capability afdwingt (`includes/class-pdk-admin.php:517-540`).

## Hoe het werkt

Het klantbestand wordt ingeladen op `after_setup_theme` met prioriteit 99 (`modules/custom-functions/class-pdk-custom-functions.php:24`) — laat genoeg dat thema en plugins geregistreerd zijn, vroeg genoeg om nog op `init` en later te kunnen haken. `load_custom_functions()` slaat over als het bestand niet bestaat of als de integriteitscontrole aanslaat, en doet anders `include_once` (`modules/custom-functions/class-pdk-custom-functions.php:51-66`).

De vaste aanpassingen worden in de constructor bedraad:

- `wp_enqueue_scripts` — `wp-block-library`, `wp-block-library-theme` en `wc-blocks-style` uit de wachtrij halen (`:27`, `:72-76`); Dashicons deregistreren voor uitgelogde bezoekers (`:28`, `:78-84`).
- `upload_mimes` + `wp_check_filetype_and_ext` — SVG-uploads toestaan. WordPress controleert SVG niet via finfo, dus het type wordt op extensie goedgekeurd (`:30-31`, `:86-103`).
- `admin_init` — WP File Manager deactiveren als die actief is, met een admin-notice (`:29`, `:105-117`).
- Vier filters die automatische update-mails en de WP Mail SMTP-samenvatting uitzetten, direct met `__return_false`/`__return_true` (`:34-37`).
- Shortcodes `[pdk_year]` en `[bloginfo name="..."]` (`:40-41`, `:123-131`).
- WooCommerce-onderdelen op `plugins_loaded` prioriteit 20, achter een `class_exists( 'WooCommerce' )`-controle — uitgesteld omdat WC bij het laden van de module nog niet bestaat (`:44`, `:137-164`).

De WooCommerce-tak voegt een term-meta `short_description` toe aan productcategorieën (formulier, opslag en `register_meta` met `wp_kses_post`-sanitisatie, `:143-157`), strip het voorvoegsel uit archieftitels via `get_the_archive_title` (`:160`, `:204-206`) en eist bij de kassa een cijfer in `billing_address_1` als huisnummercontrole (`:163`, `:209-220`).

### Integriteitscontrole — het mechanisme

Dit is het zwaarste geval van de vijf bestandsladende modules: `custom-functions.php` wordt uitgevoerd, niet alleen uitgeserveerd. Een backdoor die zichzelf aan dat bestand toevoegt, draait bij elke paginaweergave. Daarom houdt de plugin een vingerafdruk bij van wat er via de editor is opgeslagen.

1. **Vingerafdruk vastleggen.** Elke schrijfactie via `pdk_write_storage_file()` eindigt met `pdk_store_file_hash()`, dat een SHA-256 van de inhoud in de optie `pdk_file_hashes` zet (`includes/helpers.php:112`, `includes/helpers.php:296-300`). Dat gebeurt ook bij het aanmaken van een leeg bestand (`includes/helpers.php:64`) en bij een library-upload (`includes/class-pdk-admin.php:1724-1727`).
2. **Schrijven mag niet iedereen.** `pdk_write_storage_file()` weigert zonder `pdk_edit_custom_code` en geeft een `WP_Error` terug (`includes/helpers.php:75-78`). Voor `.php`-bestanden draait daarna `token_get_all( $content, TOKEN_PARSE )`; een `ParseError` blokkeert het opslaan met regelnummer en melding, want een syntaxfout hier haalt de hele site neer (`includes/helpers.php:87-101`). Pas daarna wordt de oude versie naar `<bestand>.bak` gekopieerd en de nieuwe weggeschreven (`includes/helpers.php:104-110`).
3. **Controleren.** `pdk_file_is_tampered()` normaliseert de bestandsnaam, haalt de opgeslagen hash op en vergelijkt met `hash_file()` via `hash_equals()`. Zonder opgeslagen hash — een bestaande installatie die nog nooit heeft opgeslagen — geldt het bestand als vertrouwd; een ontbrekend bestand ook (`includes/helpers.php:309-323`).
4. **Wat er onder valt.** `pdk_watched_files()` levert de drie editor-bestanden uit `pdk_code_files()` (`custom-functions.php`, `custom-style.css`, `custom-script.js`, `includes/helpers.php:272-274`) plus elk geüpload library-bestand, maar alleen als `PDK_Libraries` bestaat — dus als de Libraries-module aan staat (`includes/helpers.php:331-341`). `pdk_tampered_files()` filtert daar de afwijkers uit (`includes/helpers.php:344-346`).
5. **Gevolg.** Elke consument controleert zelf vóór het laden: deze module slaat `include_once` over (`modules/custom-functions/class-pdk-custom-functions.php:61-63`), Custom CSS en Custom JS enqueuen niet, Libraries slaat het bestand over. Er is geen centrale afhandeling — de controle staat per module.
6. **Melden en oplossen.** `show_integrity_notice()` toont op elke adminpagina een foutmelding per afwijkend bestand met een "Bekijk de wijziging"-knop die de editor met `?diff=1` opent (`includes/class-pdk-admin.php:73-115`). Twee acties, beide achter `pdk_edit_custom_code` en een nonce: **Herstel back-up** schrijft `<bestand>.bak` terug en kopieert daarna het herstelde bestand over de `.bak` heen, zodat een tweede klik niet de hack terugzet; **Wijziging vertrouwen** legt de huidige inhoud vast als nieuwe vingerafdruk (`includes/class-pdk-admin.php:122-153`). Een gebruiker zonder de capability ziet de melding wel, maar geen knoppen (`includes/class-pdk-admin.php:99-102`).
7. **Bestaande installaties.** `pdk_seed_file_hashes()` draait op `admin_init` en legt eenmalig een baseline vast als de optie nog niet bestaat — trust-on-first-use, dus controleer de bestanden één keer handmatig na een update (`includes/helpers.php:353-364`, `includes/class-pdk-plugin.php:78`).

## Bestanden en gegevens

| | |
|---|---|
| Klantbestand | `wp-content/uploads/pdk-theme-options/custom-functions.php` (`pdk-theme-options-plugin.php:32`) |
| Back-up | `custom-functions.php.bak` — de vorige opgeslagen versie (`includes/helpers.php:104-106`) |
| Aangemaakt bij | activatie én eerste admin-bezoek, met een placeholder-header (`includes/class-pdk-plugin.php:121-124`, `includes/class-pdk-plugin.php:175-178`) |
| Bewerken mag | alleen met `pdk_edit_custom_code` (`includes/class-pdk-admin.php:518-520`, `includes/class-pdk-admin.php:1126-1134`) |
| Tab zien mag | `manage_options` (`includes/class-pdk-admin.php:666-668`) |

De storage-map krijgt bij aanmaak een `.htaccess` die directorylisting uitzet en directe toegang tot `.php` en `.bak` blokkeert — zonder die regel is de broncode van `custom-functions.php` gewoon via de browser op te vragen — plus een lege `index.php` (`includes/helpers.php:36-48`).

De capability wordt bewust níét aan alle beheerders gegeven: alleen aan de gebruiker die activeert, respectievelijk de eerste beheerder die de admin bezoekt (`includes/class-pdk-plugin.php:138-147`, `includes/class-pdk-plugin.php:190-198`). `pdk_current_user_can_edit_code()` heeft geen administrator-fallback (`includes/helpers.php:20-22`). Staat `PDK_CODE_EDITORS` in `wp-config.php`, dan is die lijst leidend boven alles in de database, afgedwongen via `map_meta_cap` zodat ook multisite-superbeheerders geblokkeerd worden (`includes/helpers.php:212-265`).

**Bij uninstall** blijft `custom-functions.php` staan — de klantbestanden worden bewust niet verwijderd (`uninstall.php:5-6`). Wel weg: `pdk_theme_options`, `pdk_file_hashes` en de capability bij alle rollen en gebruikers (`uninstall.php:16-17`, `uninstall.php:59-75`).

## Voor themaontwikkelaars

- Shortcode `[pdk_year]` — het huidige jaartal (`modules/custom-functions/class-pdk-custom-functions.php:123-125`).
- Shortcode `[bloginfo name="..."]` — wrapper om `get_bloginfo()`, standaard `name` (`:128-131`).
- Term-meta `short_description` op `product_cat`, te lezen met `get_term_meta( $term_id, 'short_description', true )` (`:148-157`).
- Alles wat in `custom-functions.php` staat is gewone PHP in de globale scope; het bestand draait op `after_setup_theme` prio 99.

Via de module Agent Abilities is hetzelfde bestand lees- en schrijfbaar voor een AI-agent; schrijven loopt door dezelfde `pdk_write_storage_file()` inclusief syntaxcontrole (`modules/agent-abilities/class-pdk-agent-abilities.php:13`, `modules/agent-abilities/class-pdk-agent-abilities.php:145`).

## Grenzen en valkuilen

- **Prioriteit 99 op `after_setup_theme` is de ondergrens.** Alles wat eerder vuurt (`plugins_loaded`, vroege `after_setup_theme`-hooks) is vanuit dit bestand niet meer te bereiken.
- **Een `include_once`-fout is fataal.** De syntaxcontrole bij het opslaan vangt parse-fouten af, maar niet een fatale runtime-fout (dubbele functienaam, aanroep van een niet-bestaande klasse). Herstel dan via FTP of via de Critical Error Status-module.
- **Geen vingerafdruk = vertrouwd.** Wie de optie `pdk_file_hashes` uit de database verwijdert, zet de controle effectief uit tot de volgende `pdk_seed_file_hashes()` — die legt dan de huidige, mogelijk al gewijzigde inhoud vast als baseline (`includes/helpers.php:309-315`, `includes/helpers.php:353-364`).
- **Deploy per FTP telt als manipulatie.** Elke wijziging buiten de editor om — rsync, git, WP-CLI — zet het bestand op "gewijzigd" en stopt het laden tot iemand op "Wijziging vertrouwen" klikt.
- **SVG-uploads staan open.** De module keurt SVG goed op extensie en saneert de inhoud niet (`:86-103`); een SVG met script erin komt zo in de mediabibliotheek.
- **WP File Manager wordt stilzwijgend gedeactiveerd** bij elk adminbezoek — iemand die hem opnieuw activeert ziet hem meteen weer uitgaan (`:105-117`).
- **De kassavalidatie leest `billing_address_1` rechtstreeks uit `$_POST`** en eist alleen dat er ergens een cijfer in staat; met de checkout-blocks in plaats van de klassieke shortcode-checkout vuurt `woocommerce_checkout_process` niet.

## Zelftest

```
php tests/run.php
```

`tests/test-file-integrity.php` dekt het mechanisme uit dit document, met `custom-functions.php` als proefbestand: opslaan verlegt de baseline (`tests/test-file-integrity.php:99-104`), een directe schrijfactie wordt gedetecteerd (`:107-109`), herstel uit de back-up maakt het weer schoon (`:112-114`), een syntaxfout wordt geweigerd (`:117`), zonder baseline geldt alles als vertrouwd tot seeding (`:120-125`), en de `.htaccess` blokkeert `.php` én `.bak` (`:128`). Ook de voorrang van `PDK_CODE_EDITORS` wordt gecontroleerd (`:93-96`). Er is geen aparte test voor de WordPress- en WooCommerce-aanpassingen van deze module.
