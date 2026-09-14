# SKU Beperken & Valideren

Deze module beperkt WooCommerce-SKU's tot `a-z`, `A-Z`, `0-9`, punt en koppelteken. Spaties worden koppeltekens, ongeldige tekens vervallen, dubbele koppeltekens vallen samen. Het opschonen gebeurt live tijdens het typen én nog een keer bij het opslaan; de opgeschoonde waarde wordt daarbij op uniciteit gecontroleerd, zodat twee verschillende SKU's die na opschonen identiek worden niet stilletjes botsen. Bestaande catalogi zijn in één keer om te zetten via WP-CLI.

## Aan- en uitzetten

Modulesleutel `sku_restriction`, standaard **uit** (`pdk-theme-options/includes/class-pdk-settings.php:181-183`). Geladen via `$module_map` (`includes/class-pdk-plugin.php:26`). De module heeft **geen eigen tab** — hij staat niet in `$module_tab_labels` (`includes/class-pdk-admin.php:737-748`) en heeft ook niets in te stellen behalve aan/uit.

De module staat in `PDK_Settings::WC_MODULES` (`class-pdk-settings.php:17`). Zonder actieve WooCommerce geeft `module_unavailable()` true en levert `is_module_enabled()` altijd false, ongeacht wat er is opgeslagen (`class-pdk-settings.php:24-26`, `:46-57`); het bestand wordt dan nooit ingeladen (`class-pdk-plugin.php:88-97`) en ook het CLI-commando bestaat niet. Op de Modules-tab staat de rij gedimd met een rode melding en een uitgeschakelde checkbox (`class-pdk-admin.php:851-869`), en het opslaan van die tab slaat de module over zodat de voorkeur bewaard blijft (`class-pdk-admin.php:360-368`). WooCommerce-detectie loopt via `pdk_woocommerce_active()`, dat ook `active_plugins` raadpleegt omdat modules al vóór `plugins_loaded` laden (`includes/helpers.php:187-199`).

Binnen de module gebeurt de registratie nog eens op `plugins_loaded` prioriteit 20, achter een eigen `class_exists( 'WooCommerce' )`-check (`modules/sku-restriction/class-pdk-sku-restriction.php:21`, `:24-35`).

## Instellingen

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `enabled` | bool | `false` | Module aan/uit, in `pdk_theme_options['sku_restriction']` (`class-pdk-settings.php:181-183`) |

Verder niets. De toegestane tekenset staat vast in de code en is niet configureerbaar (`class-pdk-sku-restriction.php:43-49`).

## Hoe het werkt

De opschoonregel staat op één plek, `PDK_SKU_Restriction::sanitize()`, en doet vier stappen: witruimte → koppelteken, alles buiten `[a-zA-Z0-9.-]` weg, opeenvolgende koppeltekens samenvoegen, en tot slot koppeltekens aan begin en eind trimmen (`class-pdk-sku-restriction.php:43-49`).

Bij opslaan hangt `process_sku()` op `woocommerce_admin_process_product_object` prioriteit 20 — dus op het productobject, ná WooCommerce' eigen verwerking (`:29`, `:56-89`). Een lege SKU wordt met rust gelaten. Verandert het opschonen niets, dan stopt de methode: WooCommerce heeft de uniciteit dan zelf al gecontroleerd (`:65-68`). Verandert er wél iets, dan wordt de **opgeschoonde** waarde langs `wc_product_has_unique_sku()` gehaald. Botst die, dan wordt de oude SKU teruggezet uit `_sku` in de postmeta en verschijnt er een foutmelding via `WC_Admin_Meta_Boxes::add_error()` met de conflicterende product-ID erin — het product wordt dan niet met de nieuwe SKU opgeslagen (`:71-86`).

Aanvullend draait er een client-side filter: op `admin_footer`, alleen op het productscherm (`get_current_screen()->id === 'product'`), wordt een klein jQuery-fragment uitgestuurd dat bij elke `input` op `#_sku` dezelfde drie vervangingen toepast (`:30`, `:92-115`). Het trimmen ontbreekt daar bewust — anders kun je tijdens het typen geen `-` of `.` zetten (`:107`).

Voor bestaande catalogi registreert de module het WP-CLI-commando `pdk sku-convert`, alleen wanneer `WP_CLI` gedefinieerd is (`:32-34`). Het commando haalt in één query alle `product` en `product_variation` posts met een niet-lege `_sku` op (`:127-133`) en loopt ze af. Onveranderde SKU's worden geteld en overgeslagen; SKU's die na opschonen leeg zouden worden krijgen een waarschuwing en blijven staan (`:153-158`). Conflicten worden op twee manieren gedetecteerd: tegen de database (`wc_product_has_unique_sku()`) én tegen wat eerder in dezelfde run al geclaimd is, zodat twee bronwaarden die naar dezelfde opgeschoonde SKU convergeren niet allebei doorgaan (`:160-170`). Zonder `--live` is het een dry-run; met `--live` worden de wijzigingen via `wc_get_product()` + `set_sku()` + `save()` weggeschreven (`:176-182`). Achteraf volgt een telling van ongewijzigd, gewijzigd, conflicten en leeggelopen SKU's (`:185-189`).

## Bestanden en gegevens

De module schrijft zelf niets weg behalve de toggle in `pdk_theme_options['sku_restriction']`. De SKU's zelf zijn WooCommerce-gegevens: postmeta `_sku`, beheerd via de WooCommerce CRUD. Geen eigen tabellen, opties, transients of cron-events.

Bij uninstall verdwijnt `pdk_theme_options` en daarmee de toggle (`pdk-theme-options/uninstall.php:16-18`). Door de CLI-conversie gewijzigde SKU's blijven zoals ze zijn — dat is een eenmalige, onomkeerbare databewerking.

## Voor themaontwikkelaars

- `PDK_SKU_Restriction::sanitize( string $sku ): string` is publiek en statisch, zonder afhankelijkheid van WordPress- of WooCommerce-functies. Bruikbaar in een importscript om vooraf dezelfde regel toe te passen (`class-pdk-sku-restriction.php:43-49`).
- WP-CLI: `wp pdk sku-convert` (dry-run) en `wp pdk sku-convert --live` (`:10-13`, `:33`).

Verder geen shortcodes, hooks of filters — de module heeft geen frontend-uitvoer en registreert niets via `pdk_register_frontend_output()`.

## Grenzen en valkuilen

- **De validatie geldt alleen voor het admin-productscherm.** `woocommerce_admin_process_product_object` vuurt niet bij imports, REST-API-schrijfacties of programmatische `$product->save()`-aanroepen; die routes kunnen nog steeds een SKU met spaties of leestekens wegschrijven (`:29`).
- Bij een conflict wordt de oude SKU teruggezet met `get_post_meta( $id, '_sku', true )` — bij een **nieuw** product bestaat die meta nog niet, dus dan wordt de SKU leeg (`:75`).
- De JS-filter draait alleen op `screen->id === 'product'` (`:93-96`). Het SKU-veld van variaties en het quick-edit-veld worden dus niet live opgeschoond; daar vangt alleen de server-side check af — en quick edit gaat niet door `woocommerce_admin_process_product_object`.
- De client-side filter herschrijft `this.value` tijdens het typen; de cursor springt daarbij naar het einde van het veld zodra er een teken gewijzigd is (`:108-110`).
- Punten worden bewust behouden, maar opeenvolgende punten worden níét samengevoegd — alleen koppeltekens (`:46`).
- De CLI-conversie is onomkeerbaar en raakt ook variaties. Draai altijd eerst de dry-run en maak een databasedump; conflicten en leeglopende SKU's worden overgeslagen en moeten handmatig opgelost worden (`:156`, `:168`).
- De CLI-query is een directe `$wpdb`-query zonder paginering: op zeer grote catalogi laadt hij alle rijen in één keer in het geheugen (`:127-133`).
- Verandert het opschonen niets aan de SKU, dan doet de module niets — ook geen extra uniciteitscontrole. Die gevallen laat hij bewust aan WooCommerce over (`:65-68`).

## Zelftest

```
php tests/run.php
```

`tests/test-sku-restriction.php` test uitsluitend `PDK_SKU_Restriction::sanitize()`: behoud van punten, spaties die koppeltekens worden en dubbele koppeltekens die samenvallen, ongeldige tekens die vervallen terwijl hoofdletters blijven staan, koppeltekens die aan begin en eind getrimd worden, en een invoer die alleen uit ongeldige tekens bestaat en dus leeg terugkomt — het geval dat de aanroepers expliciet overslaan. De uniciteitscontrole en de CLI-conversie zijn niet gedekt; die vragen een draaiende WooCommerce.
