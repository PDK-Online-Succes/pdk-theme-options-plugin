# Login Pagina (PDK-stijl)

Zet de vaste PDK Online Succes-huisstijl op `wp-login.php`: achtergrondfoto, PDK-logo in plaats van het WordPress-logo, oranje accentkleur (`#d65c00`) op knoppen, focus-randen en links. Alles is hardgecodeerd; er valt niets in te stellen (`modules/login-page/class-pdk-login-page.php:4-12`).

## Aan- en uitzetten

- Modulesleutel: `login_page`, standaard `false` (`includes/class-pdk-settings.php:171-173`).
- Aan/uit via de Modules-tab, label "Login Pagina (PDK-stijl)" (`includes/class-pdk-settings.php:232`).
- **Geen eigen tab**: de sleutel staat niet in `PDK_Admin::MODULE_TABS` (`includes/class-pdk-admin.php:26-36`) en niet in de tab-labels (`includes/class-pdk-admin.php:737-748`) — er is niets te configureren.
- Uit betekent dat de klasse niet geladen wordt en er geen enkele hook gezet wordt (`includes/class-pdk-plugin.php:87-98`).

## Instellingen

Geen. In de defaults staat dat expliciet vermeld (`includes/class-pdk-settings.php:170`). Wil je andere kleuren of een ander logo, dan is de module aanpassen of uitzetten de enige weg.

## Hoe het werkt

De constructor registreert drie hooks via de loader, alle drie op standaardprioriteit 10 (`modules/login-page/class-pdk-login-page.php:18-22`, `includes/class-pdk-loader.php:17-35`):

| hook | callback | effect |
|---|---|---|
| `login_head` (action) | `output_inline_css()` | schrijft een inline `<style id="pdk-login-inline">` in de `<head>` van de loginpagina (`:24-112`) |
| `login_headerurl` (filter) | `logo_url()` | het logo linkt naar `https://pdk.nl` in plaats van wordpress.org (`:114-116`) |
| `login_headertext` (filter) | `logo_title()` | de logotekst wordt "Mogelijk gemaakt door PDK Online Succes" (`:118-120`) |

`output_inline_css()` bouwt de twee asset-URL's uit `PDK_PLUGIN_URL` (`pdk-theme-options-plugin.php:27`) en haalt ze door `esc_url()` vóór ze in de CSS belanden (`:25-26`). De stijlregels dekken: achtergrondfoto op `body.login` met `cover`/`fixed` (`:29-35`), het logo als `background-image` op `body.login h1 a` in 300×90 px (`:37-45`), formulierrand en schaduw (`:51-55`), focus-kleuren op tekst-, wachtwoord- en e-mailvelden (`:61-66`), de primaire knop inclusief hover/focus (`:68-82`), de navigatielinks met witte text-shadow voor leesbaarheid op de foto (`:84-96`), een oranje SVG-vinkje als `content` op een aangevinkte checkbox (`:98-100`), de privacylink (`:102-105`) en een media query die de bovenmarge weghaalt onder 550 px hoogte (`:107-109`).

Inline CSS in `login_head` in plaats van een enqueue is de simpelste route die op elke server werkt: er is geen extra request en geen afhankelijkheid van de stylesheet-volgorde. De keerzijde staat onder "Grenzen en valkuilen".

## Bestanden en gegevens

De module schrijft niets naar schijf of database. De twee assets komen met de plugin mee en staan in de plugin-map, dus buiten `PDK_STORAGE_DIR`:

- `pdk-theme-options/assets/img/logo-pdk-online-succes.svg`
- `pdk-theme-options/assets/img/login-background.png`

Bij een plugin-update worden ze meegeleverd en overschreven; ze overleven dus geen lokale aanpassing. Bij uninstall verdwijnen ze met de plugin-map; er is geen module-specifieke opruiming nodig (`uninstall.php:1-11`).

## Voor themaontwikkelaars

De module registreert geen eigen functies, filters of acties. Wat je van buiten kunt doen, zijn de standaard-WordPress-filters die hij zelf ook gebruikt: haak op `login_headerurl` of `login_headertext` met een prioriteit hoger dan 10 om de PDK-waarden te overschrijven, of op `login_head` met een latere prioriteit om CSS ná `#pdk-login-inline` te plaatsen (`modules/login-page/class-pdk-login-page.php:19-21`). De `<style>`-tag heeft een vaste id, `pdk-login-inline` (`:28`), zodat je hem in de browser eenduidig kunt herkennen.

## Grenzen en valkuilen

- **De CSS is niet te filteren.** Hij wordt rechtstreeks geëchood, niet enqueued, dus er is geen handle om te dequeuen en geen filter om hem aan te passen. De enige uitschakelknop is de module zelf.
- Kleuren, logo en achtergrond zijn hardgecodeerd. Een klant met een eigen huisstijl op de loginpagina moet deze module uit laten — hij is bedoeld als PDK-merkuiting, niet als white-label-functie.
- Werkt alleen op `wp-login.php`. Een thema of plugin met een eigen inlogformulier (bijvoorbeeld WooCommerce "Mijn account") vuurt `login_head` niet en blijft ongewijzigd.
- `background-attachment: fixed` (`:34`) werkt op mobiele browsers niet betrouwbaar; verwacht daar een niet-vastgezette achtergrond.
- De achtergrond is een PNG (`:26`), die bij elk loginscherm wordt opgehaald; op een trage verbinding zie je de pagina eerst zonder foto.
- Het logoblok is 300×90 px met `background-size: contain` (`:37-45`). Vervang je de SVG door iets met andere verhoudingen, dan schaalt hij binnen die doos en ontstaat er witruimte.
- `logo_url()` en `logo_title()` vervangen de standaard "Powered by WordPress"-link; bezoekers van het loginscherm worden bij een klik op het logo naar pdk.nl gestuurd (`:114-120`).

## Zelftest

De module heeft geen zelftest — in `tests/` staat geen `test-login-page.php`, en er valt weinig te asserten aan statische CSS. Controleer met de hand: open `wp-login.php`, kijk of `<style id="pdk-login-inline">` in de bron staat en of beide asset-URL's een 200 teruggeven.
