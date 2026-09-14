# Levertijden

Levertijden toont op een productpagina wanneer een bestelling verzonden wordt: per weekdag is instelbaar of er die dag verzonden wordt en tot welk tijdstip (de cutoff). Vóór de cutoff op een verzenddag verschijnt de "vandaag verzonden"-tekst, daarna wordt de eerstvolgende verzenddag opgezocht — waarbij uitgevinkte weekdagen én gesloten periodes uit Site Instellingen overgeslagen worden. Per product kan een afwijkende tekst ingevuld worden die altijd voorrang heeft.

## Aan- en uitzetten

Modulesleutel `delivery_time`, standaard **uit** (`pdk-theme-options/includes/class-pdk-settings.php:184-185`). Geladen via `$module_map` (`includes/class-pdk-plugin.php:25`) en krijgt een eigen tab **Levertijden** zodra de module aan staat (`includes/class-pdk-admin.php:744`, `:750-753`); de tab-inhoud komt uit `PDK_Delivery_Time::render_inline()` (`class-pdk-admin.php:805-808`).

De module staat in `PDK_Settings::WC_MODULES` en heeft dus WooCommerce nodig (`class-pdk-settings.php:17`). Zonder actieve WooCommerce geeft `module_unavailable()` true, waardoor `is_module_enabled()` altijd false teruggeeft ongeacht de opgeslagen voorkeur (`class-pdk-settings.php:24-26`, `:46-57`). Gevolg: het klassebestand wordt nooit ingeladen (`class-pdk-plugin.php:88-97`), er is geen tab, er is geen shortcode, en op de Modules-tab staat de rij gedimd met een rode melding en een uitgeschakelde checkbox (`class-pdk-admin.php:851-869`). Het opslaan van de Modules-tab slaat zulke modules over, zodat de opgeslagen voorkeur niet stiekem op "uit" wordt gezet (`class-pdk-admin.php:360-368`). WooCommerce-detectie gebeurt via `pdk_woocommerce_active()`, dat naast `class_exists( 'WooCommerce' )` ook de lijst met actieve plugins raadpleegt omdat modules al vóór `plugins_loaded` geladen worden (`includes/helpers.php:187-199`).

Binnen de module wordt het productveld nog eens extra achter een eigen `class_exists( 'WooCommerce' )`-check gezet, op `plugins_loaded` prioriteit 20 (`modules/delivery-time/class-pdk-delivery-time.php:30`, `:33-40`).

## Instellingen

Onder `pdk_theme_options['delivery_time']`; standaarden in `class-pdk-settings.php:184-198`.

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `enabled` | bool | `false` | Module aan/uit |
| `days[1..7]['enabled']` | bool | ma-vr en zo `true`, za `false` | Verzendt deze weekdag (1 = maandag) |
| `days[1..7]['cutoff']` | `HH:MM` | ma-vr `22:00`, za `17:00`, zo `17:00` | Tot welk tijdstip een bestelling dezelfde dag meegaat |
| `text_before` | string | `Voor {cutoff} uur besteld, {dag} verzonden (indien op voorraad)` | Tekst vóór de cutoff; tags `{cutoff}`, `{dag}` |
| `text_after` | string | `Na {cutoff} uur besteld? Verzending op {volgende_dag}.` | Tekst ná de cutoff of op een niet-verzenddag; tags `{cutoff}`, `{volgende_dag}` |

Per product: postmeta `pdk_edt` ("Levertijd (uitzondering)"), een vrij tekstveld op het tabblad Belasting van het productscherm (`class-pdk-delivery-time.php:16`, `:169-177`).

Niet-verzenddagen staan **niet** in deze module. Ze komen uit `site_settings.periods` — zie [site-settings.md](site-settings.md). De tab toont daarvoor alleen een uitleg met een link naar Site Instellingen → Afwijkende dagen (`class-pdk-delivery-time.php:264-276`).

## Hoe het werkt

`general_text()` is de kern (`class-pdk-delivery-time.php:73-118`). Hij bepaalt de huidige dagindex met `date('N')` op `current_time( 'Y-m-d' )` in `wp_timezone()`, en vergelijkt de huidige tijd als `H:i`-string met de cutoff — een stringvergelijking die klopt omdat beide `HH:MM` zijn (`:78-88`). Is vandaag een verzenddag, is het nog vóór de cutoff én valt vandaag niet in een gesloten periode, dan wordt `text_before` gevuld met de cutoff en het woord "vandaag".

Anders loopt hij maximaal 60 kalenderdagen vooruit en slaat elke dag over die niet ingeschakeld is of in een gesloten periode valt (`:98-114`). De eerste dag die overblijft vult `{volgende_dag}` als "zondag 16-8" (kleine letter dagnaam plus `j-n`). De harde bovengrens van 60 dagen voorkomt een oneindige lus wanneer alle weekdagen uitstaan of een periode maandenlang sluit; in dat geval komt er een lege string terug en toont de shortcode niets (`:116-118`).

Let op de asymmetrie: `{cutoff}` in `text_after` gebruikt de cutoff van **vandaag**, niet die van de gevonden verzenddag (`:109`). Tijden worden NL-geschreven met een punt: `22:00` → `22.00` (`:120-123`).

`is_closed_period()` is de enige koppeling met de gedeelde periodelijst: hij vraagt `PDK_Site_Settings::active_period()` op en beschouwt de dag als dicht wanneer `open` óf `close` leeg is (`:133-137`). Een periode met afwijkende tijden — zoals een zomerperiode — blokkeert dus niets en laat de weekdagregel intact.

De frontend-uitvoer is geregistreerd via `pdk_register_frontend_output()` in de constructor (`:20-27`), dus zowel `[levertijd]` als `do_action( 'pdk_levertijd' )` werken. De shortcode leest de globale `$product`; buiten een productcontext geeft hij een lege string (`:143-148`). Is er productmeta `pdk_edt`, dan wint die altijd van de berekende tekst (`:150-155`).

Het productveld hangt op `woocommerce_product_options_tax` (render) en `woocommerce_process_product_meta` (opslaan) (`:38-39`). Bij opslaan wordt niets gedaan als het veld niet is meegestuurd — dat behoudt de bestaande waarde bij bijvoorbeeld quick edit (`:181-183`). Nonce-verificatie gebeurt door WooCommerce zelf vóór die hook (`:180`).

Opslaan vanaf de tab gaat via `PDK_Delivery_Time::save()`, aangeroepen uit `PDK_Admin::handle_save()` achter een `class_exists`-check (`class-pdk-admin.php:323-327`). De methode vervangt het hele `delivery_time`-blok in plaats van `PDK_Settings::update()` te gebruiken, want die merget recursief en zou een uitgevinkte verzenddag ingeschakeld laten (`class-pdk-delivery-time.php:218-227`). De `enabled`-vlag wordt daarbij expliciet overgenomen omdat hij op de Modules-tab hoort (`:221`). Een cutoff die niet aan `/^\d{2}:\d{2}$/` voldoet valt terug op de standaardwaarde van die dag (`:212`).

## Bestanden en gegevens

Instellingen staan in `pdk_theme_options['delivery_time']`; per-productuitzonderingen in postmeta `pdk_edt`, weggeschreven via de WooCommerce CRUD (`$product->update_meta_data()`, `:191-192`). Geen eigen tabellen, geen bestanden, geen transients.

Bij uninstall verdwijnt `pdk_theme_options` en daarmee de module-instellingen (`pdk-theme-options/uninstall.php:16-18`). De postmeta `pdk_edt` wordt **niet** opgeruimd — die blijft bij de producten staan.

## Voor themaontwikkelaars

- Shortcode `[levertijd]` en template-hook `do_action( 'pdk_levertijd' )`, beide geregistreerd via `pdk_register_frontend_output()` (`includes/helpers.php:158-168`).
- Uitvoer: `<h4 class="eta__title">Levertijd: </h4>` gevolgd door `<p class="eta__content">…</p>` (`class-pdk-delivery-time.php:161-162`). De plugin levert geen CSS mee; opmaak via Custom CSS op die twee klassen.
- `PDK_Delivery_Time::general_text()` is publiek en statisch — bruikbaar als je de tekst zonder de HTML-wrapper wilt. Geeft een lege string als er geen verzenddag te vinden is.
- `PDK_Delivery_Time::day_labels()` is een dunne wrapper om `pdk_day_labels()` uit `includes/helpers.php:132-142`.
- `PDK_Delivery_Time::PRODUCT_META` is de metasleutel (`pdk_edt`) voor wie de uitzondering programmatisch wil zetten.

Er zijn geen filters of acties om de berekening aan te passen.

## Grenzen en valkuilen

- Buiten een productpagina geeft `[levertijd]` niets terug: er is dan geen globale `$product` (`:144-148`). In de shoploop werkt hij wel, want daar zet WooCommerce `$product` per item.
- De cutoff-vergelijking is een stringvergelijking op `H:i`. Een cutoff `9:00` (zonder voorloopnul) zou fout vergelijken — de tab dwingt `<input type="time">` af en de opslag valideert op `\d{2}:\d{2}`, maar programmatisch gezette waarden worden niet gecontroleerd (`:86`, `:212`).
- `{cutoff}` in de "na"-tekst is de cutoff van vandaag, niet die van de gevonden verzenddag. Op een zondag met cutoff 17:00 die naar maandag verwijst staat er dus 17.00, niet 22.00 (`:109`).
- Sluit een periode alles langer dan 60 dagen weg, dan toont de shortcode niets in plaats van een melding (`:98`, `:116-118`).
- De berekening heeft geen cache en draait per aanroep, inclusief het "Voorbeeld nu"-regeltje onderaan de admin-tab (`:296-299`).
- Een periode met afwijkende tijden telt níét als sluitingsdag — wie de webshop wil laten stoppen met verzenden moet de tijden in die periode leeglaten (`:133-137`).
- Producten met een gevulde `pdk_edt` negeren alles: cutoff, weekdagen en periodes. Een vergeten uitzondering blijft dus een verouderde levertijd tonen (`:150-155`).

## Zelftest

```
php tests/run.php
```

`tests/test-delivery-time.php` dekt acht gevallen rond `general_text()`: vóór de cutoff op een verzenddag, ná de cutoff met een uitgevinkte zaterdag, een gesloten periode die de zondag wegschuift naar maandag, een periode mét tijden die niets blokkeert, een gesloten dag die "vandaag verzonden" uitsluit, een niet-verzenddag die de cutoff van die dag zelf gebruikt, geen enkele verzenddag (lege tekst, geen oneindige lus) en een periode van 60+ dagen (idem). De shortcode, het productveld en het opslaan in de admin zijn niet gedekt.
