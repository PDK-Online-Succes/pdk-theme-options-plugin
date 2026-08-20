# Changelog

Alle noemenswaardige wijzigingen in PDK Theme Options worden hier bijgehouden.

## [2.5.0] — 2026-08-20

### Echte code-editor: CodeMirror 6

- De textarea voor PHP, CSS en JS is vervangen door CodeMirror 6: syntax-kleuring, regelnummers, code vouwen, haakjes-matching, meerdere cursors, zoeken/vervangen (Ctrl+F), autocompletion en undo/redo
- **Geen linters** — de editor kleurt, hij keurt niet af. CSS Nesting, PHP 8.5 en ES2026 leveren dus nooit een valse foutmelding. De echte PHP-syntaxcontrole gebeurt bij het opslaan server-side met `token_get_all()`, dus met de PHP-versie van de site zelf
- De textarea blijft achter de schermen bestaan en loopt mee: opslaan, Ctrl+S en terugvallen zonder JavaScript werken ongewijzigd
- Bijwerken naar de nieuwste CodeMirror: `npm run update` in de repo-root (bouwt de bundel opnieuw en draait de rooktest). Klanten hebben geen build-stap: `assets/js/editor.bundle.js` staat gebouwd in de repo
- De bundel (672 kB) laadt alleen op de drie code-tabs, niet op de rest van de admin

### Diff-weergave

- Knop *Vergelijk met laatst opgeslagen versie* op elke code-tab: zij-aan-zij vergelijking tussen de `.bak` (laatst via de editor opgeslagen) en de huidige inhoud, met ongewijzigde blokken ingeklapt
- De integriteitsmelding heeft een knop *Bekijk de wijziging* die direct in die vergelijking opent — zo zie je precies wat er buiten de editor om is bijgeschreven
- Op een gemanipuleerd bestand opent de vergelijking automatisch

### Overig

- Nieuw in de repo-root: `package.json` en `src/` (buildbronnen). De MU-installer pakt alleen `pdk-theme-options/` uit, dus klanten krijgen ze niet mee
- Rooktest voor de editor: `npm test`

## [2.4.0] — 2026-08-20

### Code-editor-rechten vastzetten in wp-config.php

- Nieuwe constante `PDK_CODE_EDITORS` (gebruikers-ID's, logins of e-mailadressen, komma-gescheiden). Is die gedefinieerd, dan is wp-config leidend: capabilities uit de database worden genegeerd en de Rechten-tab is read-only
- Werkt via `map_meta_cap`, dus ook multisite-superbeheerders passeren de controle niet meer
- **Bugfix:** een beheerder uitvinken in de Rechten-tab had geen effect als de capability op de administrator-*rol* stond (oudere installaties) — `remove_cap()` op een gebruiker haalt een rol-capability niet weg. Opslaan verwijdert de capability nu ook van alle rollen

### Integriteitscontrole van custom PHP, CSS en JS

- Bij elke opslag wordt een SHA-256-vingerafdruk vastgelegd en de vorige versie bewaard als `.bak`
- Wijkt een bestand daarna af, dan wordt `custom-functions.php` niet meer ingeladen en worden CSS/JS niet meer uitgeserveerd — een backdoor die zichzelf bijschrijft draait dus nooit
- Beheerders krijgen een melding met *Herstel back-up* of *Wijziging vertrouwen* (voor bewuste wijzigingen via SFTP of WP-CLI)
- Bestaande installaties: de huidige inhoud wordt éénmalig als vertrouwd vastgelegd — controleer de bestanden één keer na deze update
- `.htaccess` in de storage-map blokkeert nu ook `.bak`-bestanden (voorheen alleen `.php`) en gebruikt `Require all denied` voor Apache 2.4. Wordt bij deze update automatisch herschreven
- Zelftest: `php includes/test-file-integrity.php`

### Documentatie

- README: `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` als aanvullende hardening, plus uitleg over `PDK_CODE_EDITORS` en de integriteitscontrole

## [2.3.1] — 2026-08-17

### De agent weet nu wat de plugin op de frontend rendert

- Nieuwe ability `pdk-theme-options/get-site-info`: bedrijfsgegevens, social media, openingstijden, afwijkende periodes, of het vandaag gesloten is, welke modules aan staan, en de bestaande helpers (`pdk_site_setting()`, `pdk_company_address()`, `pdk_opening_hours_html()`, `PDK_Site_Settings::*`) en shortcodes
- Per frontend-uitvoer (`[openingstijden]`, `[levertijd]`, `[vakantiemelding]`) de shortcode, de template-hook, de HTML-opbouw met CSS-klassen én `html_now`: de HTML zoals die er op dat moment daadwerkelijk uitkomt. Ook de uitvoer buiten shortcodes staat erin — favicon, de handles `pdk-custom-style` en `pdk-custom-script`, het `@font-face`-blok, de loginpagina en de term-meta `short_description`
- De markup-beschrijving staat als derde argument bij `pdk_register_frontend_output()`, dus náást de render-code; nieuwe modules die die helper gebruiken verschijnen automatisch in de ability. Nieuw: `pdk_frontend_outputs()` geeft dat register terug
- De schrijf-ability verwijst naar `get-site-info`, zodat een agent gegevens ophaalt en bestaande shortcodes hergebruikt in plaats van hard codeert, en CSS op de juiste klassen zet
- De agent kan de openingstijden en klantgegevens alleen lézen — wijzigen blijft aan de klant via *Site Instellingen*

### Site Instellingen

- Favicon en logo kiezen uit de mediabibliotheek, met voorbeeldweergave en een "Verwijderen"-knop. De waarde blijft een URL, dus `pdk_client_logo_url()` en de favicon-output werken ongewijzigd en bestaande instellingen blijven staan
- De regel "Gebruik pdk_site_setting(...)" stond onder élk sub-tabblad; hij staat nu alleen onder Klantgegevens, en noemt ook `pdk_company_address()`

## [2.3.0] — 2026-08-17

### AI-agent toegang tot eigen PHP, CSS en JS (MCP)

- Nieuwe module *AI-agent toegang (MCP)* (standaard uit): registreert `pdk-theme-options/read-custom-code` en `pdk-theme-options/write-custom-code` via de WordPress Abilities API (WP 6.9+), met `file` = `php` | `css` | `js`
- Een MCP-server publiceert die automatisch als tools, via `meta.mcp.public = true` zoals Agent Connector en de WordPress MCP Adapter verwachten. De plugin spreekt zelf geen MCP-protocol
- Ook bereikbaar over REST onder `wp-abilities/v1` (`meta.show_in_rest = true`)
- Toegang loopt via de bestaande capability `pdk_edit_custom_code`: de gebruiker waarmee de agent inlogt moet code-editor rechten hebben (Rechten-tab). Beheerder zijn is niet genoeg
- Zelftest: `php modules/agent-abilities/test-agent-abilities.php`

### Gewijzigd

- `pdk_write_storage_file()` weigert PHP met een syntaxfout (`token_get_all` met `TOKEN_PARSE`) en meldt regelnummer + fout. Geldt voor élke schrijver — dus ook de admin-code-editor kan de site niet meer platleggen met een typefout

## [2.2.0] — 2026-08-17

### Afwijkende dagen: één lijst voor openingstijden, levertijden en vakantiemodus

- Nieuwe sub-tab *Site Instellingen → Afwijkende dagen*: periodes met van/tot, omschrijving, afwijkende openingstijden en een vinkje "Webshop sluiten"
- Kerst, oud en nieuw, zomerperiodes en bedrijfsvakanties worden nog maar op één plek ingevuld; voorheen kostte een sluiting twee invoerplekken en waren afwijkende openingstijden helemaal niet mogelijk
- Openingstijden per periode: tijden leeg = gesloten, tijden ingevuld = afwijkende openstelling
- De openingstijden-tabel kijkt zeven dagen vooruit, zodat een periode op de juiste weekdag landt, met een melding erboven: "Let op: afwijkende openingstijden i.v.m. Kerst"
- Verzenden wordt afgeleid en heeft geen eigen instelling meer: een gesloten periode telt als niet-verzenddag, een periode met afwijkende tijden verzendt gewoon door
- Nieuwe API voor thema's: `PDK_Site_Settings::active_period()`, `::matching_periods()`, `::is_closed_on()`

### Gewijzigd

- **Levertijden:** de tabel "Uitzonderingsdata" is vervallen; die data staat nu bij Afwijkende dagen, met een verwijzing op de tab
- **Vakantiemodus:** start- en einddatum zijn vervallen; de tab toont de geplande sluitingen uit de periodelijst. Staat de module aan zonder enige sluitingsperiode, dan is de webshop direct dicht — gelijk aan het oude gedrag zonder datums
- Bestaande uitzonderingsdata en vakantiedatums worden automatisch omgezet naar periodes bij de eerste keer laden; vielen ze samen, dan worden het één rij

## [2.1.0] — 2026-08-17

### PDK MU Installer (nieuwe micro-plugin)

- Losse plugin `pdk-mu-installer/` — te uploaden via *Plugins → Nieuwe plugin*
- Installeert PDK Theme Options als must-use plugin vanuit de laatste GitHub-release, inclusief de loader `mu-plugins/pdk-theme-options.php`
- Meldt nieuwe releases in de admin; bijwerken en opnieuw installeren met één knop, plus "MU-plugin verwijderen"
- Versievergelijking op de `Version:`-header van de geïnstalleerde MU-plugin tegen de laatste release-tag; release-info 12 uur gecached
- Vereist directe schrijftoegang (`FS_METHOD` direct); hosts die FTP-gegevens eisen krijgen een duidelijke foutmelding

### Admin: Ctrl+S opslaan

- `Ctrl+S` (of `Cmd+S`) slaat op waar je op dat moment in zit: bericht, pagina, WooCommerce-product of instellingenpagina
- Werkt op elke admin-pagina, niet alleen op de PDK-tabs
- Op een concept wordt "Concept opslaan" gebruikt, nooit "Publiceren"
- In de blok-editor doet het script niets — die heeft z'n eigen Ctrl+S

### Site Instellingen: sub-tabs

- Basis / Klantgegevens / Openingstijden / Social Media staan nu op aparte sub-tabs
- Alle secties blijven in één formulier: één keer opslaan bewaart alles
- Gekozen sub-tab wordt onthouden per sessie; een `#anker` in de URL wint daarvan

### Site Instellingen: openingstijden

- Openingstijden per weekdag (van/tot of "Gesloten"), instelbaar onder *PDK Tools → Site Instellingen*
- Uitvoer via `[openingstijden]`, `do_action( 'pdk_openingstijden' )` of `pdk_opening_hours_html()`
- Een half ingevulde dag (alleen "van" of alleen "tot") telt als gesloten — nooit een halve tijdsaanduiding op de site

### Frontend-uitvoer via shortcode én template-hook

- Nieuwe helper `pdk_register_frontend_output( $naam, $callback )`: één registratie levert zowel `[naam]` als `do_action( 'pdk_naam' )`
- Toegepast op Levertijden (`[levertijd]`), Vakantiemodus (`[vakantiemelding]`, nieuw) en Openingstijden
- Dagnamen staan nu centraal in `pdk_day_labels()` in plaats van per module

### Module: SKU Beperken & Valideren (nieuw)

- Overgenomen uit de losse plugin `wc-sku-beperking.php` — dat bestand is uit de repo verwijderd
- **Punt toegevoegd aan de toegestane tekens**: `a-z A-Z 0-9 . -` (was zonder punt)
- Opschonen bij opslaan én live tijdens typen; botst de opgeschoonde SKU met een bestaande, dan blijft de oude SKU staan met een foutmelding
- WP-CLI-conversie hernoemd naar `wp pdk sku-convert` (`--live` om te schrijven)
- Vereist WooCommerce — valt onder dezelfde afhankelijkheidsbewaking

### Module: Levertijden (nieuw)

- Verzenddagen met eigen cutoff-tijd per weekdag; tekst instelbaar met de tags `{cutoff}`, `{dag}` en `{volgende_dag}`
- Uitzonderingsdata (bijv. kerst, bedrijfsuitje) — enkele dag of periode, worden overgeslagen bij het bepalen van de eerstvolgende verzenddag
- Product-uitzondering via het bestaande veld `pdk_edt` heeft voorrang op de algemene instellingen
- Shortcode `[levertijd]` voor de productpagina
- Instellingen onder *PDK Tools → Levertijden* (niet meer onder WooCommerce → Levertijden)

### WooCommerce-afhankelijkheid

- Modules die WooCommerce nodig hebben (`vacation_mode`, `delivery_time`) worden niet geladen en tonen geen tab zolang WooCommerce inactief is — voorkomt fatale fouten
- De toggle van zo'n module is uitgeschakeld in de Modules-tab; de opgeslagen voorkeur blijft behouden en werkt weer zodra WooCommerce actief is
- `pdk_woocommerce_active()` valt terug op de lijst met actieve plugins, omdat modules al vóór `plugins_loaded` geladen worden

## [2.0.0] — 2026-07-30

Volledige herstructurering: acht afzonderlijke plugins samengebracht in één modulaire plugin.

### Samengevoegde plugins

| Oud | Functionaliteit |
|---|---|
| `pdk-critical-error-status` | HTTP 500 bij fatale PHP-fouten |
| `pdk-theme-options-plugin` (v1) | Carbon Fields klantgegevens, social media |
| `custom-functions` | PHP-hulpfuncties, SVG-uploads, WooCommerce-uitbreidingen |
| `custom-css` | Eigen CSS via editor |
| `custom-js` | Eigen JavaScript via editor |
| `ma-custom-fonts` | @font-face CSS generatie |
| `custom-login-page` | Aangepaste loginpagina |
| `language-checker` | Taalbestandsbeheer |

### Nieuw

- **Modulaire architectuur** — elke module is afzonderlijk in- of uitschakelbaar
- **Must-Use plugin ondersteuning** — `mu-loader.php` voor MU-installatie; automatische eerste-keer-initialisatie via `admin_init`
- **GitHub Releases updater** (`PDK_Updater`) — automatische updates via WordPress update-mechanisme (niet actief in MU-modus)
- **Custom capability** `pdk_edit_custom_code` — code-editor-rechten los van `administrator`, per gebruiker instelbaar via *PDK Tools → Rechten*

### Module: Custom Fonts

- Fontbestanden scannen vanuit `wp-content/uploads/fonts/`
- Automatische weight/style-detectie uit bestandsnaam (`FamilyName-Bold.woff2`)
- `@font-face` CSS gegenereerd op `wp_head` en `admin_head`
- **Nieuw:** Admin-tab toont fontnamen gegroepeerd per familie met live preview
- **Nieuw:** Upload-formulier in admin (alleen `woff2`, `woff`, `ttf`, `otf`; vereist `manage_options`)
- **Nieuw:** Verwijderknop per fontbestand met bestandsgrootte-weergave
- Configureerbare `font-display` strategie (auto / block / swap / fallback / optional)
- **Nieuw:** CSS-uitvoer kiezen: inline in `<head>` of gecached extern bestand (`pdk-custom-fonts.css`)
- **Nieuw:** Gutenberg-integratie — custom fonts beschikbaar in de blok-editor font picker (via `wp_theme_json_data_theme`)
- **Nieuw:** Variable font-ondersteuning (`VariableFont` of `[wght]` in bestandsnaam → `font-weight: 1 1000`)
- **Nieuw:** Multi-source `@font-face` — woff2 + ttf van dezelfde variant gecombineerd in één `src:`-regel
- **Fix:** Woordgrens-regex voor gewichtdetectie — `SemiCondensed` werd eerder fout als gewicht 600 herkend

### Module: Custom Functions

- Eigen `custom-functions.php` laden vanuit `wp-content/uploads/pdk-theme-options/`
- Block Library CSS en Dashicons uitschakelen voor niet-ingelogde gebruikers
- Automatisch deactiveren van WP File Manager plugin
- SVG-uploads toestaan met correcte MIME-type-validatie
- Automatische plugin/thema-update e-mails uitschakelen
- WP Mail SMTP-samenvattingsmail uitschakelen
- Shortcodes: `[pdk_year]`, `[bloginfo]`
- WooCommerce: korte beschrijving op productcategorieën, checkout-adresvalidatie, archief-titelopmaak

### Module: Site Instellingen

- Favicon-URL instelling
- Gutenberg uitschakelen voor paginatype *Pagina*
- Klantgegevens: naam, straat + huisnummer, postcode + stad, telefoon, e-mail
- Klantlogo-URL
- Social media: Facebook, Instagram, LinkedIn, X/Twitter, YouTube, TikTok
- Hulpfuncties: `pdk_site_setting()`, `pdk_client_logo_url()`, `pdk_company_address()`

### Module: Login Page

- PDK-logo (SVG) en achtergrond (PNG) ingebundeld in plugin — geen externe afhankelijkheden
- Inline CSS (geen apart bestand) voor robuustere caching
- Donkerpaarse navigatielinks met witte gloed voor leesbaarheid op lichte achtergrond

### Module: Language Cleaner

- Geïnstalleerde WordPress-kerntalen weergeven via `wp_get_installed_translations('core')`
- Talen verwijderen inclusief alle bijbehorende bestanden:
  - `.mo`, `.po`, `.l10n.php` (patroon `*{locale}.*`)
  - Gehashte JSON-bestanden (patroon `*{locale}-*`)
  - Bestanden in `WP_LANG_DIR`, `WP_LANG_DIR/plugins/` en `WP_LANG_DIR/themes/`
- Verweesde vertaalbestanden opsporen (plugins/thema's die niet meer geïnstalleerd zijn)
  - Regex voor locale-achtervoegsel toegepast vóór `strtolower()` (correcte `_[A-Z]{2}` matching)
- Actieve taal kan niet worden verwijderd

### Module: Vakantiemodus

- Vrije melding (HTML toegestaan)
- Optioneel: winkelwagen uitschakelen
- Optioneel: checkout uitschakelen (redirect naar winkel)
- Start- en einddatum (automatisch activeren/deactiveren)

### Module: Critical Error Status

- HTTP 500 bij fatale PHP-fouten (i.p.v. standaard 200 met foutpagina)
- Altijd actief, niet uitschakelbaar

### Technisch

- Geen externe afhankelijkheden (geen Carbon Fields, geen Composer)
- Native WordPress Settings API
- `PDK_Loader` — alle hooks verzameld, één keer geregistreerd via `run()`
- Clientbestanden in `wp-content/uploads/pdk-theme-options/` (blijven bij plugin-updates)
- WooCommerce HPOS-compatibiliteit gedeclareerd

---

## [1.x] — voor 2026

Zie afzonderlijke repositories van de acht component-plugins.
