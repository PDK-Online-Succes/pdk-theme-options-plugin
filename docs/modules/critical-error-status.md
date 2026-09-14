# Critical Error Status

Zorgt dat een pagina die door een fatale PHP-fout afbreekt, een HTTP 500 terugstuurt in plaats van een 200 met een halve of lege pagina, en logt de fout naar `debug.log` wanneer `WP_DEBUG_LOG` aan staat (`modules/critical-error-status/class-pdk-critical-error.php:3-10`). Foutdetails gaan nooit naar de browser. Dat de statuscode klopt is wat monitoring, uptime-checks en zoekmachines nodig hebben om een kapotte pagina als kapot te herkennen.

## Aan- en uitzetten

**Niet uit te zetten — met opzet.** De module staat niet in `$module_map` (`includes/class-pdk-plugin.php:14-31`), en heeft geen sleutel in de defaults (`includes/class-pdk-settings.php:199`). Hij stáát wel in de Modules-lijst — met een label (`includes/class-pdk-settings.php:242`), een omschrijving (`:263`) en een vaste, uitgeschakelde toggle — zodat in één oogopslag zichtbaar is wat er draait. Hij wordt onvoorwaardelijk geladen in `PDK_Plugin::init()`, direct na de Security-module en vóór de optionele modules (`includes/class-pdk-plugin.php:50-52`). De toggle is dus zichtbaar maar niet te bedienen, en er is geen eigen tab.

Waarom zo:

- Optionele modules worden pas geladen na het uitlezen van de opties (`includes/class-pdk-plugin.php:81-82`, `:87-98`). Een handler die juist moet werken wanneer er iets misgaat, mag niet afhangen van een optie die op dat moment misschien niet gelezen kan worden.
- De module verandert geen zichtbaar gedrag en heeft geen instellingen; er is niets waar een klant tussen zou willen kiezen. Een verkeerde statuscode is een defect, geen voorkeur.
- De kosten zijn één `error_get_last()` per request (`:22`).

## Instellingen

Geen. De module leest geen enkele plugin-optie. Het enige dat het gedrag beïnvloedt is de WordPress-constante `WP_DEBUG_LOG`, die bepaalt of er gelogd wordt (`:40`).

## Hoe het werkt

De constructor registreert bewust **niet** via de `PDK_Loader` maar met een directe `add_action()` op `shutdown`, prioriteit `PHP_INT_MAX` (`:16-19`). Twee redenen, beide in de code toegelicht: via de loader worden hooks pas bij `run()` geregistreerd (`includes/class-pdk-plugin.php:84`, `includes/class-pdk-loader.php:38-45`), terwijl deze handler er absoluut moet staan; en `PHP_INT_MAX` zet hem achteraan, zodat alle andere shutdown-callbacks eerst hun werk hebben gedaan.

`handle_shutdown()` is defensief opgebouwd (`:21-50`):

1. `error_get_last()` — geen laatste fout, dan meteen terug (`:22-26`).
2. Alleen deze typen tellen als fataal: `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR` (`:28-32`). Waarschuwingen en notices worden genegeerd.
3. `status_header( 500 )`, maar alleen als `headers_sent()` false is (`:35-37`). Is de output al onderweg, dan is de code niet meer te wijzigen en gebeurt er niets.
4. Loggen uitsluitend wanneer `WP_DEBUG_LOG` gedefinieerd én waar is (`:40`). De regel bevat een UTC-tijdstempel, de melding, het bestand en het regelnummer, met prefix `[PDK Critical Error]` (`:41-48`).

Er wordt niets naar de browser geschreven — de bezoeker ziet het foutscherm van WordPress of van de webserver, nu met de juiste statuscode.

## Bestanden en gegevens

Geen opties, geen transients, geen bestanden in de storage-map. De enige schrijfactie is `error_log()` naar de bestemming die PHP/WordPress kent, en alleen bij `WP_DEBUG_LOG` (`:40-48`). Uninstall hoeft dus niets op te ruimen, en `uninstall.php` noemt de module niet.

## Grenzen en valkuilen

- **Grep op `[PDK Critical Error]`** als je in `debug.log` zoekt (`:42`). Staat er niets, dan is `WP_DEBUG_LOG` uit — niet: er was geen fout.
- De tijdstempel is `gmdate()`, dus **UTC** (`:43`). In de Nederlandse zomer scheelt dat twee uur met de rest van je logs.
- Vindt de fatale fout plaats nadat er al output is verstuurd, dan blijft de statuscode staan op wat hij was (`:35`). Een 200 met een afgebroken pagina is dus nog steeds mogelijk.
- De handler hangt aan de WordPress-`shutdown`-action. Breekt een request af vóórdat WordPress zover geladen is dat die action bestaat, dan draait hij niet.
- `error_get_last()` kijkt naar de laatste fout van het request, ook een fout die verderop al afgehandeld is. In de praktijk filtert de typelijst (`:28`) dat afdoende, maar een `trigger_error( ..., E_USER_ERROR )` in eigen code levert bewust ook een 500 op.
- De module vervangt WordPress' eigen fatal-error-afhandeling niet en schakelt die ook niet uit; hij vult alleen de statuscode aan waar die ontbreekt.

## Zelftest

Geen — er is geen `tests/test-critical-error-status.php` en de logica bestaat uit twee guards rond PHP-ingebouwde functies. Handmatig te controleren op een testomgeving: zet `WP_DEBUG_LOG` aan, veroorzaak een fatale fout (bijvoorbeeld een aanroep van een niet-bestaande functie in een testplugin) en controleer `curl -I` op een 500 plus de regel met `[PDK Critical Error]` in `debug.log`.
