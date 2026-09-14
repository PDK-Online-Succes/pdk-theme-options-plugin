# Vakantiemodus

Vakantiemodus sluit de webshop tijdelijk: hij toont een melding op de shop-, product-, winkelwagen- en kassapagina, maakt producten onkoopbaar en stuurt bezoekers weg van de checkout. Wanneer dat gebeurt bepaalt de gedeelde periodelijst uit Site Instellingen: een periode met het vinkje "Webshop sluiten" zet de modus aan. Staat er geen enkele sluiting gepland, dan sluit de module-toggle zelf de shop direct — dat is de handmatige noodknop.

## Aan- en uitzetten

Modulesleutel `vacation_mode`, standaard **uit** (`pdk-theme-options/includes/class-pdk-settings.php:174-180`). Geladen via `$module_map` (`includes/class-pdk-plugin.php:24`), met een eigen tab **Vakantiemodus** zodra hij aan staat (`includes/class-pdk-admin.php:743`, `:750-753`, `:802-804`).

De module staat in `PDK_Settings::WC_MODULES` (`class-pdk-settings.php:17`). Zonder actieve WooCommerce geeft `module_unavailable()` true en levert `is_module_enabled()` altijd false, ongeacht de opgeslagen voorkeur (`class-pdk-settings.php:24-26`, `:46-57`). Het bestand wordt dan nooit ingeladen (`class-pdk-plugin.php:88-97`), er is geen tab, en de shortcode `[vakantiemelding]` bestaat niet. Op de Modules-tab staat de rij gedimd met de melding "WooCommerce is niet actief — deze module kan niet worden ingeschakeld" en een uitgeschakelde checkbox (`class-pdk-admin.php:851-869`); opslaan slaat zo'n module over zodat de voorkeur intact blijft (`class-pdk-admin.php:360-368`). Detectie loopt via `pdk_woocommerce_active()`, dat naast de klasse ook `active_plugins` raadpleegt omdat modules vóór `plugins_loaded` geladen worden (`includes/helpers.php:187-199`).

Daarbovenop wacht de module zelf op `plugins_loaded` prioriteit 20 en controleert daar nogmaals `class_exists( 'WooCommerce' )`, zodat de plugin-laadvolgorde niet uitmaakt (`modules/vacation-mode/class-pdk-vacation-mode.php:21`, `:54-57`).

## Instellingen

Onder `pdk_theme_options['vacation_mode']`; standaarden in `class-pdk-settings.php:174-180`.

| Sleutel | Type | Standaard | Betekenis |
|---|---|---|---|
| `enabled` | bool | `false` | Module aan/uit (Modules-tab) |
| `message` | HTML | `Wij zijn tijdelijk gesloten. Bedankt voor uw geduld.` | Melding; opgeslagen via `wp_kses_post`, dus HTML is toegestaan (`class-pdk-admin.php:460`) |
| `disable_add_to_cart` | bool | `true` | "In winkelwagen"-knoppen uitschakelen |
| `disable_checkout` | bool | `true` | Afrekenen blokkeren en terugsturen naar de winkel |

Periodes staan **niet** in deze module: de start- en einddatum zijn bij de migratie naar `site_settings.periods` verhuisd, met het veld `close_shop` — zie [site-settings.md](site-settings.md). De tab toont alleen de geplande sluitingen als lijst plus een link naar Site Instellingen → Afwijkende dagen (`class-pdk-admin.php:1346-1393`).

## Hoe het werkt

`is_active_now()` beslist alles (`class-pdk-vacation-mode.php:100-106`). Is er geen enkele periode met `close_shop`, dan geeft hij **true** — de shop is dan meteen dicht zolang de module aan staat. Is er wel minstens één geplande sluiting, dan volgt hij `PDK_Site_Settings::shop_closed_now()` en is de shop alleen dicht binnen zo'n periode. De admin-tab waarschuwt hier expliciet voor met een rode melding wanneer er geen sluiting gepland is (`class-pdk-admin.php:1378-1381`).

Alle WooCommerce-hooks worden pas op `plugins_loaded` prioriteit 20 geregistreerd, en alleen wanneer de modus actief is (`class-pdk-vacation-mode.php:54-89`). Buiten een vakantieperiode hangt er dus geen enkele filter in de request — geen overhead, geen bijwerkingen.

De melding komt op vier plekken via `wc_add_notice()`, telkens op prioriteit 5 zodat hij bovenaan staat: `woocommerce_before_shop_loop`, `woocommerce_before_single_product`, `woocommerce_before_cart` en `woocommerce_before_checkout_form` (`:69-72`, `:116-118`).

Add-to-cart wordt op drie niveaus dichtgezet (`:74-81`): `woocommerce_is_purchasable` retourneert onvoorwaardelijk false zodat WooCommerce de knop zelf verbergt (`:122-124`); `woocommerce_add_to_cart_validation` blokkeert directe POST's en URL- of AJAX-toevoegingen met een foutmelding (`:128-131`); en `woocommerce_loop_add_to_cart_link` vervangt de knop in overzichten door `<span class="pdk-vacation-label button disabled">Tijdelijk niet beschikbaar</span>` als zichtbare terugval (`:135-140`).

De checkout wordt eveneens gelaagd geblokkeerd (`:83-89`): `template_redirect` stuurt bezoekers van de kassa terug naar de winkelpagina — behalve op de `order-received`-endpoint, want die bestelling is al geplaatst (`:143-154`); daarnaast plaatsen `woocommerce_check_cart_items` en `woocommerce_checkout_process` een foutmelding, wat een submit ook zonder redirect tegenhoudt (`:156-158`).

De shortcode `[vakantiemelding]` en `do_action( 'pdk_vakantiemelding' )` zijn in de constructor geregistreerd, dus vóór `maybe_init()`. Daarom laadt `render_notice()` de instellingen desnoods zelf in (`:37-44`) en geeft hij een lege string zolang de modus niet actief is (`:46-51`).

## Bestanden en gegevens

Alleen `pdk_theme_options['vacation_mode']` — drie instellingen plus de toggle. Geen eigen opties, postmeta, bestanden of cron-events. De datums staan in `site_settings.periods` en worden daar opgeslagen (`class-pdk-admin.php:456-466`). Bij uninstall verdwijnt `pdk_theme_options` en daarmee ook deze instellingen (`pdk-theme-options/uninstall.php:16-18`).

De migratie van de oude `start_date`/`end_date` naar een periode met `close_shop` gebeurt eenmalig in `PDK_Site_Settings::migrate_periods()` (`modules/site-settings/class-pdk-site-settings.php:289-318`).

## Voor themaontwikkelaars

- Shortcode `[vakantiemelding]` en template-hook `do_action( 'pdk_vakantiemelding' )`, geregistreerd via `pdk_register_frontend_output()` (`includes/helpers.php:158-168`). Uitvoer: `<div class="pdk-vacation-notice">…</div>`, of een lege string zolang de modus niet actief is (`class-pdk-vacation-mode.php:26-33`, `:46-51`).
- De vervangen loop-knop krijgt `<span class="pdk-vacation-label button disabled">` (`:135-140`). De plugin levert geen frontend-CSS; opmaak gaat via Custom CSS op `.pdk-vacation-notice` en `.pdk-vacation-label`.
- Wil je zelf bepalen of de shop dicht is, gebruik dan `PDK_Site_Settings::shop_closed_now()` of `has_shop_closures()` — `is_active_now()` is privé (`:100`).

Er zijn geen eigen filters of acties om het gedrag aan te passen; de standaard WooCommerce-filters (`woocommerce_is_purchasable` enzovoort) kun je met een hogere prioriteit overrulen.

## Grenzen en valkuilen

- **Zonder geplande periode is de shop meteen dicht.** Wie de module "alvast aanzet" voor de zomervakantie sluit de webshop op dat moment (`:100-103`). Plan eerst een periode met "Webshop sluiten", zet daarna pas de toggle om.
- `is_active_now()` wordt één keer geëvalueerd op `plugins_loaded`. Een periode die om middernacht ingaat werkt pas bij de volgende request; er is geen cron of cache-invalidatie. Paginacaches kunnen de oude staat langer vasthouden.
- `disable_purchasable()` geeft onvoorwaardelijk false voor **alle** producten — er is geen uitzonderingslijst (`:122-124`). Onkoopbare producten verdwijnen ook uit sommige widgets en feeds.
- De checkout-redirect vuurt op `template_redirect` met `exit`. Betaalgateways die op de kassa-URL terugkeren zonder de `order-received`-endpoint te gebruiken lopen tegen een redirect aan (`:143-154`).
- Bestaande bestellingen, e-mails en my-account-pagina's worden niet aangeraakt; alleen nieuwe aankopen zijn geblokkeerd.
- De melding gaat door `wp_kses_post()` bij zowel opslaan als uitvoer (`class-pdk-admin.php:460`, `class-pdk-vacation-mode.php:117`), dus scripts en iframes in het bericht overleven het niet.
- De shortcode gebruikt `$this->settings`; wordt hij aangeroepen vóór `maybe_init()` — of zonder dat die ooit draaide — dan laadt hij de instellingen zelf opnieuw uit de database (`:37-44`).
- De melding verschijnt via `wc_add_notice()`; een thema dat `woocommerce_output_all_notices()` niet aanroept op de betreffende template toont hem niet (`:116-118`). Gebruik dan de shortcode of de template-hook.

## Zelftest

```
php tests/run.php
```

Er is geen eigen testbestand voor deze module. De beslislogica die hij gebruikt is wél gedekt: `tests/test-opening-hours.php:147-152` controleert `has_shop_closures()` en `shop_closed_now()` binnen en buiten een periode met `close_shop`, en `tests/test-migrate-periods.php:90-127` dekt de verhuizing van `start_date`/`end_date` naar een periode met `close_shop`, inclusief het samenvoegen met een bestaande periode en een vakantiemodus zónder datums (die blijft handmatig sluiten). De WooCommerce-hooks zelf zijn niet getest.
