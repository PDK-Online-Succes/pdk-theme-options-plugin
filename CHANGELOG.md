# Changelog

Alle noemenswaardige wijzigingen in PDK Theme Options worden hier bijgehouden.

## [2.3.0] — 2026-08-17

### AI-agent toegang tot eigen PHP, CSS en JS (MCP)

- Nieuwe module *AI-agent toegang (MCP)* (standaard uit): registreert twee abilities via de WordPress Abilities API (WP 6.9+) — `pdk-theme-options/read-custom-code` en `pdk-theme-options/write-custom-code`, met `file` = `php` | `css` | `js`
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
