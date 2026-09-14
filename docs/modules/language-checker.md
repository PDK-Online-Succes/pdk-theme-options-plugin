# Language Checker (Taalcontrole / Language Cleaner)

Beheerpagina die twee soorten taalbestanden opruimt: geïnstalleerde WordPress-kerntalen die niemand meer gebruikt, en verweesde vertaalbestanden van plugins en thema's die niet meer geïnstalleerd zijn (`modules/language-checker/class-pdk-language-checker.php:2-11`). Doel is schijfruimte en overzicht in `wp-content/languages/`. De module verwijdert bestanden definitief — er is geen prullenbak en geen back-up.

## Aan- en uitzetten

- Modulesleutel: `language_checker`, standaard `false` (`includes/class-pdk-settings.php:200-202`).
- Aan/uit via de Modules-tab, label "Language Cleaner" (`includes/class-pdk-settings.php:236`).
- Heeft een eigen tab "Taalcontrole" / "Language Cleaner", alleen zichtbaar als de module aan staat (`includes/class-pdk-admin.php:33`, `:745`, `:750-754`). De tab is *standalone*: de admin-pagina opent er bewust géén wrapper-`<form>` omheen, omdat de module twee eigen formulieren rendert (`includes/class-pdk-admin.php:679-680`, `modules/language-checker/class-pdk-language-checker.php:85-88`).
- Rendering loopt via `PDK_Admin::render_tab_language_checker()`, die delegeert naar `PDK_Language_Checker::render_inline()` (`includes/class-pdk-admin.php:1402-1407`).

## Instellingen

Geen. De module heeft alleen `enabled`; alles gebeurt via knoppen op de tab.

## Hoe het werkt

Twee hooks, beide op standaardprioriteit 10 via de loader: `admin_init` → `handle_actions()` en `admin_notices` → `show_notices()` (`modules/language-checker/class-pdk-language-checker.php:21-22`). `admin_init` is gekozen omdat de afhandeling eindigt in een redirect, die vóór header-output moet gebeuren (`:25`, `:67-71`).

`handle_actions()` doet niets tenzij `$_POST['pdk_lang_action']` gezet is én de gebruiker `manage_options` heeft (`:28-30`). Daarna volgt per actie een nonce-controle: `check_admin_referer( 'pdk_lang_remove_core' )` voor kerntalen (`:36`) en `check_admin_referer( 'pdk_lang_orphan' )` voor verweesde bestanden (`:58`). Het resultaat gaat als transient `pdk_lang_notice` (30 seconden) naar het volgende scherm (`:50-54`, `:60-64`) en de gebruiker wordt teruggestuurd naar de tab (`:67-71`).

**Kerntalen verwijderen.** De lijst komt uit `wp_get_installed_translations( 'core' )`, aangevuld met de actieve locale als die er nog niet in stond (`:184-197`). In de tabel krijgt de actieve locale geen checkbox maar een streepje (`:116-120`); bovendien wordt hij bij de verwerking nogmaals expliciet overgeslagen (`:44-46`) — twee onafhankelijke barrières tegen het wegblazen van de taal van de site zelf.

Per geselecteerde locale draait `do_uninstall_language()` (`:209-237`). Die:

1. haalt de locale door `sanitize_file_name()` (`:214`), zodat er geen `../` of pad in kan zitten;
2. kijkt uitsluitend in drie mappen: `WP_LANG_DIR`, `WP_LANG_DIR/plugins` en `WP_LANG_DIR/themes` (`:216-220`);
3. gebruikt per map twee glob-patronen, `*{locale}.*` en `*{locale}-*` (`:224-227`) — dat dekt `nl_NL.mo`, `admin-nl_NL.l10n.php`, `nl_NL-{hash}.json` en `woocommerce-nl_NL-app.json`;
4. verwijdert alleen wat `is_file()` is, met onderdrukte `unlink()`, en telt de successen (`:229-233`).

Omdat de patronen de locale altijd laten volgen door een punt of een streep, raakt `nl_NL` niet aan `nl_NL_formal.mo`.

**Verweesde bestanden.** `find_orphaned_files()` bouwt een lijst bekende slugs uit `get_plugins()` — zowel de mapnaam als de bestandsnaam zonder `.php` — en uit `wp_get_themes()` (`:244-250`). Vervolgens scant hij `WP_LANG_DIR/plugins` en `WP_LANG_DIR/themes` op `*.{mo,po}` (`:257`), strijkt het locale-achtervoegsel van de bestandsnaam met een regex (bewust vóór het lowercasen, anders matcht `_[A-Z]{2}` niet meer, `:260-266`) en beschouwt alles waarvan de resterende slug onbekend is als verweesd (`:268-270`). De UI toont eerst de volledige lijst met `basename()` per bestand (`:160-164`); pas daarna verschijnt de knop. `do_remove_orphaned()` draait dezelfde detectie opnieuw en unlinkt (`:277-285`).

## Bestanden en gegevens

Op schijf: verwijdert bestanden in `WP_LANG_DIR`, `WP_LANG_DIR/plugins` en `WP_LANG_DIR/themes`. Buiten die drie mappen wordt niets aangeraakt en er wordt nooit iets geschreven.

In de database: alleen de transient `pdk_lang_notice` met een levensduur van 30 seconden (`:50-54`), die direct na het tonen verwijderd wordt (`:75-79`). De module heeft geen eigen optie behalve `language_checker.enabled` binnen `pdk_theme_options`, dat bij uninstall met de rest van de optie verdwijnt (`uninstall.php:16`). Verwijderde taalbestanden komen niet terug bij het deïnstalleren van de plugin.

## Grenzen en valkuilen

- **Onomkeerbaar.** Geen back-up, geen bevestigingsdialoog — één klik op de knop en de bestanden zijn weg. Terugkrijgen kan alleen door de vertaling opnieuw te laten installeren door WordPress of de betreffende plugin.
- **MU-plugins staan niet in `get_plugins()`.** De bekende-slugs-lijst komt uitsluitend uit `get_plugins()` en `wp_get_themes()` (`:244-250`). Vertaalbestanden van must-use plugins worden daardoor als verweesd aangemerkt en verwijderd. Op een site met de PDK MU-installer is dat het eerste om te controleren in de getoonde lijst.
- Gedeactiveerde plugins en niet-actieve thema's staan wél in `get_plugins()` / `wp_get_themes()` en zijn dus veilig.
- **Orphan-detectie ziet alleen `.mo` en `.po`** (`:257`). Bijbehorende `.json`- en `.l10n.php`-bestanden van diezelfde verwijderde plugin blijven staan. Kerntalen verwijderen pakt die formaten wél (`:224-227`).
- De regex die het locale-achtervoegsel afhaalt, verwacht het patroon `-xx_XX` (`:262-266`). Een bestandsnaam die daar niet aan voldoet, houdt zijn achtervoegsel en matcht dan niet met een bekende slug — vals positief. Lees de lijst dus altijd voordat je op verwijderen klikt.
- `unlink()` is onderdrukt met `@` (`:230`, `:280`): bij ontbrekende schrijfrechten mislukt het stil en meldt de teller simpelweg een lager aantal. Blijft de teller op 0 staan terwijl er wel bestanden in de lijst stonden, kijk dan naar bestandsrechten.
- Er is geen capability-controle in `render_inline()` zelf; die zit in de omliggende pagina (`includes/class-pdk-admin.php:666-668`) en in `handle_actions()` (`:28`).
- Het "alles selecteren"-vinkje is inline JavaScript zonder null-check op het element (`:138-144`); het bestaat alleen wanneer er kerntalen zijn (`:101`).

## Zelftest

De module heeft geen eigen zelftest — in `tests/` staat geen `test-language-checker.php`. `php tests/run.php` draait de bestaande suite maar dekt deze module niet. Test met de hand op een wegwerpsite: controleer dat de actieve locale geen checkbox heeft en dat de getoonde verweesde-lijst geen MU-plugin-vertalingen bevat.
