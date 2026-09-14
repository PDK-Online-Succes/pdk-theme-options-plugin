# PDK Theme Options

Centrale WordPress-beheerplug-in voor PDK Online Succes klanten. Vervangt acht afzonderlijke plugins door één modulaire oplossing. De meeste modules zijn in- of uitschakelbaar via PDK Tools → Modules; drie zijn altijd actief.

**Vereisten:** WordPress 6.0+, PHP 8.0+  
**Optioneel:** WooCommerce 7.0+ (voor Vakantiemodus, Levertijden en SKU Beperken & Valideren)

---

## Installatie

### Standaard (reguliere plugin)

1. Kopieer de `pdk-theme-options/` map naar `wp-content/plugins/`
2. Activeer de plugin via *Plugins → Geïnstalleerde plugins*
3. Ga naar *PDK Tools → Rechten* en wijs code-editor-rechten toe aan de gewenste gebruikers

### Must-Use Plugin via de PDK MU Installer (aanbevolen voor productie)

Must-Use plugins zijn altijd actief en kunnen niet per ongeluk worden uitgeschakeld — maar WordPress kan ze zelf niet installeren of bijwerken. De micro-plugin `pdk-mu-installer/` doet dat wel.

1. Zip de map `pdk-mu-installer/` en upload hem via *Plugins → Nieuwe plugin → Plugin uploaden*
2. Activeer **PDK MU Installer**
3. Ga naar *Plugins → PDK MU-plugin* en klik op **Installeren**

De installer haalt de laatste GitHub-release op, pakt hem uit naar `wp-content/mu-plugins/pdk-theme-options/` en schrijft de loader `pdk-theme-options.php` ernaast. Daarna meldt hij nieuwe releases in de admin; bijwerken is één klik. De installer mag actief blijven; deactiveren stopt de MU-plugin niet (gebruik daarvoor **MU-plugin verwijderen**).

### Must-Use Plugin handmatig

1. Kopieer de `pdk-theme-options/` map naar `wp-content/mu-plugins/pdk-theme-options/`
2. Maak `wp-content/mu-plugins/pdk-theme-options.php` met daarin:
   ```php
   <?php
   defined( 'ABSPATH' ) || exit;
   require_once __DIR__ . '/pdk-theme-options/pdk-theme-options-plugin.php';
   ```
   MU-plugins laden geen submappen; dit bestandje laadt de plugin in.
3. De plugin laadt automatisch — geen activatie nodig

> **Let op:** In MU-modus werkt de ingebouwde GitHub-updater niet (WordPress volgt MU-plugins niet). Update via de PDK MU Installer of handmatig.

### Kinsta-hosting

Op Kinsta staat de mu-plugins map op: `/app/mu-plugins/`

---

## Modules

Per module staat er een referentiedocument in [`docs/modules/`](docs/modules/) — instellingen, hooks, randgevallen en gemaakte keuzes, bedoeld voor PDK-ontwikkelaars. Deze tabel is de index; klik de modulenaam door voor de details.

### Altijd actief

Niet uitschakelbaar; deze drie worden onvoorwaardelijk geladen.

| Module | Omschrijving |
|---|---|
| [**Security**](docs/modules/security.md) | Header-firewall, MU- en plugin-blacklist, integriteitscontrole op `mu-plugins/`, bewaking van verplichte plugins, XML-RPC dichtzetten en REST-routes afschermen |
| [**Critical Error Status**](docs/modules/critical-error-status.md) | Stuurt HTTP 500 terug bij fatale PHP-fouten (SEO-vriendelijk) |
| [**Site Instellingen**](docs/modules/site-settings.md) | Favicon, klantgegevens, logo, social media, openingstijden en afwijkende dagen — opvraagbaar in thema's |

### Optioneel (per module in-/uitschakelbaar)

| Module | Omschrijving |
|---|---|
| [**Custom Functions**](docs/modules/custom-functions.md) | Eigen PHP-functies laden vanuit `uploads/pdk-theme-options/custom-functions.php`; WordPress-hulpprogramma's; WooCommerce productcategorie-uitbreidingen |
| [**Custom CSS**](docs/modules/custom-css.md) | Eigen CSS laden vanuit `uploads/pdk-theme-options/custom-style.css` |
| [**Custom JS**](docs/modules/custom-js.md) | Eigen JavaScript laden vanuit `uploads/pdk-theme-options/custom-script.js` |
| [**Custom Fonts**](docs/modules/custom-fonts.md) | `@font-face` CSS genereren vanuit `uploads/fonts/`; upload en beheer via admin |
| [**Libraries (JS/CSS)**](docs/modules/libraries.md) | Kant-en-klare JS- en CSS-bestanden uit `uploads/pdk-theme-options/libraries/` op de frontend laden — Glide.js, Swiper, Splide |
| [**Login Page**](docs/modules/login-page.md) | Aangepaste loginpagina met PDK-logo en achtergrond |
| [**Vakantiemodus**](docs/modules/vacation-mode.md) | Winkelwagen en/of checkout blokkeren tijdens een periode met "Webshop sluiten" (WooCommerce) |
| [**Levertijden**](docs/modules/delivery-time.md) | Levertijdtekst per weekdag met cutoff-tijd en product-uitzondering; shortcode `[levertijd]` (WooCommerce) |
| [**SKU Beperken & Valideren**](docs/modules/sku-restriction.md) | SKU's beperkt tot `a-z A-Z 0-9 . -`; automatisch opschonen, duplicaten geblokkeerd, WP-CLI-conversie (WooCommerce) |
| [**Language Cleaner**](docs/modules/language-checker.md) | Geïnstalleerde kerntalen verwijderen; verweesde vertaalbestanden opsporen |
| [**AI-agent toegang (MCP)**](docs/modules/agent-abilities.md) | Eigen PHP, CSS en JS lees- en schrijfbaar maken voor een AI-agent via de Abilities API; vereist een gebruiker met code-editor-rechten |
| [**IMGX**](docs/modules/imgx.md) | WebP- en AVIF-versies naast bestaande JPEG's en PNG's zetten en uitserveren via `<picture>`; originelen blijven ongewijzigd |
| [**Afbeeldingsmaten**](docs/modules/image-sizes.md) | Geregistreerde afbeeldingsmaten bekijken, aan/uit zetten, eigen maten aanmaken en de mediabibliotheek in batches opnieuw genereren |

Modules met (WooCommerce) worden niet geladen zolang WooCommerce niet actief is, ook niet als de toggle aan staat.

---

## Bestanden buiten de plugin-map

Clientbestanden staan in `wp-content/uploads/pdk-theme-options/` en worden **niet** overschreven bij plugin-updates:

| Bestand | Gebruik |
|---|---|
| `custom-functions.php` | Eigen PHP-functies |
| `custom-style.css` | Eigen CSS |
| `custom-script.js` | Eigen JavaScript |
| `libraries/` | Geüploade JS/CSS-bibliotheken |

Fontbestanden staan in `wp-content/uploads/fonts/`:  
Bestandsnaamconventie: `FamilyName-Weight.woff2` (bijv. `Raleway-Bold.woff2`)

---

## Hulpfuncties voor thema's

Beschikbaar als de **Site Instellingen**-module actief is:

```php
// Klantgegevens
pdk_site_setting( 'company_name' )    // Bedrijfsnaam
pdk_site_setting( 'company_phone' )   // Telefoonnummer
pdk_site_setting( 'company_email' )   // E-mailadres
pdk_client_logo_url()                 // URL van het klantlogo
pdk_company_address()                 // Volledig adres als string
pdk_opening_hours_html()              // Openingstijden als HTML-tabel

// Social media
pdk_site_setting( 'social_instagram' )
pdk_site_setting( 'social_facebook' )
pdk_site_setting( 'social_linkedin' )
pdk_site_setting( 'social_twitter' )
pdk_site_setting( 'social_youtube' )
pdk_site_setting( 'social_tiktok' )
```

Shortcodes (actief als **Custom Functions** ingeschakeld is):

```
[pdk_year]        → huidig jaar (voor © in de footer)
[bloginfo name=""] → WordPress bloginfo-waarden
```

### Frontend-uitvoer: shortcode of template-hook

Elke module die iets aan de voorkant toont, is op twee manieren aan te roepen — dezelfde uitvoer, dezelfde escaping:

| Module | Shortcode | Template-hook |
|---|---|---|
| Site Instellingen | `[openingstijden]` | `do_action( 'pdk_openingstijden' )` |
| Levertijden | `[levertijd]` | `do_action( 'pdk_levertijd' )` |
| Vakantiemodus | `[vakantiemelding]` | `do_action( 'pdk_vakantiemelding' )` |

Attributen geef je aan de hook mee als array:

```php
do_action( 'pdk_openingstijden', [ 'title' => 'Openingstijden bouwshop' ] );
// gelijk aan: [openingstijden title="Openingstijden bouwshop"]
```

Een eigen module aansluiten kost één regel in de constructor:

```php
pdk_register_frontend_output( 'mijn_blok', [ $this, 'render' ] );
// levert [mijn_blok] én do_action( 'pdk_mijn_blok' )
```

---

## Afwijkende dagen

Kerst, oud en nieuw, een zomerperiode of een bedrijfsvakantie vul je op één plek in: *PDK Tools → Site Instellingen → Afwijkende dagen*. Die lijst stuurt drie dingen tegelijk aan, zodat dezelfde datum nooit op meerdere plekken bijgehouden hoeft te worden.

| Periode | Openingstijden | Levertijden | Webshop |
|---|---|---|---|
| Tijden leeg gelaten | Toont "Gesloten" op die dagen | Telt als niet-verzenddag, levertijd schuift op | Blijft open |
| Afwijkende tijden ingevuld | Toont die tijden | Verzendt volgens de gewone weekdagregel | Blijft open |
| "Webshop sluiten" aangevinkt | Volgt de ingevulde tijden | Volgt de ingevulde tijden | Dicht tijdens de periode |

De openingstijden-tabel kijkt zeven dagen vooruit: elke weekdag krijgt de eerstvolgende concrete datum, dus een periode landt op de juiste rij. Loopt er een periode, dan komt er een regel boven de tabel: *"Let op: afwijkende openingstijden i.v.m. Kerst"*.

Voor thema's:

```php
PDK_Site_Settings::active_period();              // periode van vandaag, of null
PDK_Site_Settings::active_period( '2026-12-25' ); // periode op een datum
PDK_Site_Settings::is_closed_on( '2026-12-25' );  // gesloten? periode wint van de weekdag
```

> **Vakantiemodus:** staat de module aan zonder dat er ergens "Webshop sluiten" is aangevinkt, dan is de webshop meteen dicht — zo blijft de toggle bruikbaar om handmatig te sluiten. Zodra er wél een sluitingsperiode staat, geldt alleen die periode.

---

## Code-editor

De tabs *PHP Functions*, *Custom CSS* en *Custom JS* gebruiken **CodeMirror 6**: syntax-kleuring, regelnummers, vouwen, haakjes-matching, meerdere cursors, zoeken/vervangen (Ctrl+F) en autocompletion. Ctrl+S slaat op.

Bewust **zonder linters**: de editor kleurt, hij keurt niet af. CSS Nesting, PHP 8.5 of ES2026 leveren dus nooit een valse foutmelding over "ongeldige" syntax. De echte controle gebeurt waar hij hoort:

- **PHP** — bij het opslaan server-side met `token_get_all()`, dus met de PHP-versie van de site zelf. Een parse-fout wordt geweigerd vóór het schrijven, zodat een typefout de site nooit plat legt
- **CSS/JS** — de browser is de enige waarheid; wat hij begrijpt, werkt

### Diff-weergave

Elke code-tab heeft de knop *Vergelijk met laatst opgeslagen versie*: een zij-aan-zij vergelijking tussen `<bestand>.bak` (de vorige versie) en de huidige inhoud, met ongewijzigde blokken ingeklapt. Bij een gemanipuleerd bestand opent die vergelijking automatisch — zie [Integriteitscontrole](#integriteitscontrole-van-de-code-bestanden).

### Editor bijwerken

De gebouwde bundel `pdk-theme-options/assets/js/editor.bundle.js` staat in de repo — klanten hebben dus **geen** build-stap. Alleen om te updaten:

```bash
npm install     # eenmalig
npm run update  # npm update + bundel opnieuw bouwen + rooktest
```

`package.json`, `src/` en `node_modules/` blijven in de repo-root; de MU-installer pakt alleen `pdk-theme-options/` uit, dus klanten krijgen ze niet mee. Bump daarna de plugin-versie zodat browsers de nieuwe bundel ophalen.

---

## Beveiliging

### Code-editor-rechten (`pdk_edit_custom_code`)

De PHP/CSS/JS-editor is **niet** toegankelijk voor alle beheerders. Gebruikers moeten expliciet worden aangewezen via *PDK Tools → Rechten*.

> **Reden:** beheerders-accounts kunnen zijn gecompromitteerd. Code-schrijftoegang geeft directe servertoegang. Wijs zo min mogelijk gebruikers aan.

### Rechten vastzetten in `wp-config.php` (aanbevolen)

```php
define( 'PDK_CODE_EDITORS', '1,7' );          // gebruikers-ID's
define( 'PDK_CODE_EDITORS', 'luuk,beheer' );  // of logins / e-mailadressen
```

Staat deze constante gedefinieerd, dan bepaalt **alleen wp-config.php** wie code mag bewerken. De Rechten-tab wordt read-only en capabilities in de database — ook een `pdk_edit_custom_code` die een aanvaller aan de administrator-rol toevoegt — worden genegeerd. Ook multisite-superbeheerders vallen hieronder.

### Integriteitscontrole van de code-bestanden

Bij elke opslag via de editor legt de plugin een SHA-256-vingerafdruk vast en bewaart hij de vorige versie als `.bak`. Wijkt de inhoud daarna af, dan is het bestand buiten de editor om gewijzigd:

- `custom-functions.php` wordt **niet meer ingeladen** — een bijgeschreven backdoor draait dus nooit
- `custom-style.css` en `custom-script.js` worden niet meer uitgeserveerd
- Beheerders zien een melding met *Herstel back-up* of *Wijziging vertrouwen*

Wijzig je bewust via SFTP of WP-CLI, kies dan *Wijziging vertrouwen* — dan wordt de nieuwe inhoud de baseline.

> Bij het bijwerken vanaf een oudere versie wordt de huidige inhoud éénmalig als vertrouwd vastgelegd. Controleer die bestanden dus één keer na de update.

Aanvullend in `wp-config.php`, buiten deze plugin om:

```php
define( 'DISALLOW_FILE_EDIT', true );  // schakelt de WordPress thema-/plugin-editor uit
define( 'DISALLOW_FILE_MODS', true );  // blokkeert ook plugin-/thema-installaties
```

`DISALLOW_FILE_MODS` schakelt ook de GitHub-updater van deze plugin uit; gebruik die alleen op sites die je handmatig of via deploys bijwerkt.

Zelftests staan in `tests/` in de repo-root en draaien met `php tests/run.php`; los draaien kan met `php tests/test-file-integrity.php`.

### Font-upload-rechten

Fontbestanden uploaden en verwijderen vereist `manage_options`. Dit is de standaard WordPress-beheerdercapabiliteit.

---

## Updates

De plugin controleert automatisch op nieuwe releases via GitHub Releases (`PDK-Online-Succes/pdk-theme-options-plugin`). Updates zijn beschikbaar via het standaard WordPress-updatemechanisme.

In Must-Use modus is de automatische updater uitgeschakeld — updates handmatig installeren.

---

## Licentie

GPL-2.0-or-later — zie [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
