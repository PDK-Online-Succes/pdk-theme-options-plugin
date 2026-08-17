# PDK Theme Options

Centrale WordPress-beheerplug-in voor PDK Online Succes klanten. Vervangt acht afzonderlijke plugins door één modulaire oplossing. Elke module is in- of uitschakelbaar via PDK Tools → Modules.

**Vereisten:** WordPress 6.0+, PHP 8.0+  
**Optioneel:** WooCommerce 7.0+ (voor functies in Custom Functions)

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

### Altijd actief

| Module | Omschrijving |
|---|---|
| **Critical Error Status** | Stuurt HTTP 500 terug bij fatale PHP-fouten (SEO-vriendelijk) |
| **Site Instellingen** | Favicon, klantgegevens, logo, social media, openingstijden en afwijkende dagen — opvraagbaar in thema's |

### Optioneel (per module in-/uitschakelbaar)

| Module | Omschrijving |
|---|---|
| **Custom Functions** | Eigen PHP-functies laden vanuit `uploads/pdk-theme-options/custom-functions.php`; WordPress-hulpprogramma's; WooCommerce productcategorie-uitbreidingen |
| **Custom CSS** | Eigen CSS laden vanuit `uploads/pdk-theme-options/custom-style.css` |
| **Custom JS** | Eigen JavaScript laden vanuit `uploads/pdk-theme-options/custom-script.js` |
| **Custom Fonts** | `@font-face` CSS genereren vanuit `uploads/fonts/`; upload en beheer via admin |
| **Login Page** | Aangepaste loginpagina met PDK-logo en achtergrond |
| **Vakantiemodus** | Winkelwagen en/of checkout blokkeren tijdens een periode met "Webshop sluiten" (WooCommerce) |
| **Levertijden** | Levertijdtekst per weekdag met cutoff-tijd en product-uitzondering; shortcode `[levertijd]` (WooCommerce) |
| **SKU Beperken & Valideren** | SKU's beperkt tot `a-z A-Z 0-9 . -`; automatisch opschonen, duplicaten geblokkeerd, WP-CLI-conversie (WooCommerce) |
| **Language Cleaner** | Geïnstalleerde kerntalen verwijderen; verweesde vertaalbestanden opsporen |

---

## Bestanden buiten de plugin-map

Clientbestanden staan in `wp-content/uploads/pdk-theme-options/` en worden **niet** overschreven bij plugin-updates:

| Bestand | Gebruik |
|---|---|
| `custom-functions.php` | Eigen PHP-functies |
| `custom-style.css` | Eigen CSS |
| `custom-script.js` | Eigen JavaScript |

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
[bloginfo key=""] → WordPress bloginfo-waarden
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

## Beveiliging

### Code-editor-rechten (`pdk_edit_custom_code`)

De PHP/CSS/JS-editor is **niet** toegankelijk voor alle beheerders. Gebruikers moeten expliciet worden aangewezen via *PDK Tools → Rechten*.

> **Reden:** beheerders-accounts kunnen zijn gecompromitteerd. Code-schrijftoegang geeft directe servertoegang. Wijs zo min mogelijk gebruikers aan.

### Font-upload-rechten

Fontbestanden uploaden en verwijderen vereist `manage_options`. Dit is de standaard WordPress-beheerdercapabiliteit.

---

## Updates

De plugin controleert automatisch op nieuwe releases via GitHub Releases (`PDK-Online-Succes/pdk-theme-options-plugin`). Updates zijn beschikbaar via het standaard WordPress-updatemechanisme.

In Must-Use modus is de automatische updater uitgeschakeld — updates handmatig installeren.

---

## Licentie

GPL-2.0-or-later — zie [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
