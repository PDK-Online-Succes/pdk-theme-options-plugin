# Oxygen Element Studio

Element Studio is de "builder voor builder-elementen" in Oxygen 6: een paneel in de builder dat controls, Twig-HTML en CSS omzet in een PHP-elementklasse op schijf. Standaard biedt het alleen de first-party mappen van Oxygen aan als opslaglocatie, en die worden bij elke Oxygen-update overschreven. Deze module registreert een eigen locatie "PDK Elementen" buiten die mappen, in `uploads/pdk-theme-options/oxygen-elements/` (`modules/oxygen-elements/class-pdk-oxygen-elements.php:1-22`).

Referenties: [Element Studio overview](https://oxygenbuilder.com/documentation/developers/element-studio/overview-6/), [Initial setup](https://oxygenbuilder.com/documentation/developers/element-studio/initial-setup/), [soflyy/breakdance-custom-elements](https://github.com/soflyy/breakdance-custom-elements) (het boilerplate-plugin waarvan deze module het patroon overneemt).

## Aan- en uitzetten

| | |
|---|---|
| Modulesleutel | `oxygen_elements` (`includes/class-pdk-plugin.php`, `$module_map`) |
| Standaard | `enabled => false` (`includes/class-pdk-settings.php`, `get_defaults()`) |
| Eigen tab | Ja — "Element Studio", alleen als de module aan staat. Alleen-lezen, dus in `$standalone_tabs` (`includes/class-pdk-admin.php`, `render_settings_page()`) |
| Vereist | Oxygen 6.x (of Breakdance). Zonder actieve Oxygen 6 is de toggle op de Modules-tab uitgegrijsd met de melding "Oxygen 6 is niet actief", geeft `is_module_enabled()` `false` en wordt de klasse niet geladen — zelfde mechanisme als de WooCommerce-modules (`PDK_Settings::OXYGEN6_MODULES`, `module_missing_dependency()`, `pdk_oxygen6_active()` in `includes/helpers.php`). Oxygen Classic 4.x telt niet: die heeft `oxygen/functions.php` als hoofdbestand, Oxygen 6 `oxygen/plugin.php` |

## Instellingen

Geen. De locatie, de namespace en het label staan vast in de code (`PDK_Oxygen_Elements::dir()`, `::NAMESPACE_`, `register_save_locations()`).

## Hoe het werkt

De constructor haakt `register_save_locations()` op `breakdance_loaded` met prioriteit **9** (`class-pdk-oxygen-elements.php`, constructor). Dat getal is niet vrij: Oxygen scant op prioriteit 10 alle geregistreerde locaties met `glob( WP_PLUGIN_DIR . '/' . <locatie> . '/*/*.php' )` en include't wat het vindt (`oxygen/plugin/element-studio/load.php`, `include_elements()`). Een registratie op `oxygen_loaded` vuurt pas ná die scan en is altijd te laat.

`register_save_locations()` doet drie aanroepen van `\Breakdance\ElementStudio\registerSaveLocation()`, één per type:

| argument | waarde | betekenis |
|---|---|---|
| `$directoryPath` | `../uploads/pdk-theme-options/oxygen-elements/elements` (resp. `macros`, `presets`) | relatief aan `WP_PLUGIN_DIR`, zonder voorloopslash; berekend door `relative_path()` |
| `$namespace` | `PdkElements` | PHP-namespace van elke gegenereerde klasse én voorvoegsel van de slug; mag niet `OxygenElements` of `EssentialElements` zijn |
| `$type` | `element` / `macro` / `preset` | één aanroep per type |
| `$label` | `PDK Elementen` | wat de keuzelijst "Select a saving location" toont |
| `$onlyForAdvancedUsers` | `false` | Oxygens eigen locaties geven `true` |

Vooraf maakt `ensure_dirs()` de drie submappen aan; Element Studio zou dat anders pas bij de eerste save doen.

Een element "Docs Demo Card" komt in `elements/Docs_Demo_Card/` met `element.php`, `html.twig`, `css.twig` en `default.css`. De klasse heet `PdkElements\DocsDemoCard` en dat is ook de slug (`slug()` in de gegenereerde klasse geeft `__CLASS__` terug). Bestanden die op `ssr.php` eindigen slaat Oxygens scan over; die laadt hij pas bij het renderen.

### Waarom dit in uploads werkt

De bestanden staan in `wp-content/uploads/pdk-theme-options/oxygen-elements/`, naast `custom-functions.php`. Alles wat Oxygen met een opslaglocatie doet begint met `WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $directoryPath` — opslaan (`save.php`), laden (`load.php`), verwijderen (`delete.php`), macro's en presets — zonder te controleren of het resultaat binnen de plugins-map blijft. Een relatief pad als `../uploads/pdk-theme-options/oxygen-elements/elements` werkt dus overal. `base()` berekent het generiek, één keer per request: het splitst `realpath( WP_PLUGIN_DIR )` en `realpath( dir() )`, haalt het gemeenschappelijke begin eraf en zet een `..` per overgebleven pluginmap-segment ervoor (hoofdletterongevoelig op Windows). Daarna een rondreis: `realpath( WP_PLUGIN_DIR . '/' . $rel )` moet weer op de opslagmap uitkomen, anders (symlink op de plugins-map) zet `base()` `$error` en registreert de module niets. `relative_path( $type )` plakt er `/elements`, `/macros` of `/presets` achter. Zo klopt het ook bij een aangepaste `UPLOADS` of `WP_PLUGIN_DIR`.

Eén obstakel: de gegenereerde `element.php` roept zelf `registerElementForEditing( "PdkElements\X", getDirectoryPathRelativeToPluginFolder( __DIR__ ) )` aan, en die hulpfunctie haalt alleen `realpath( WP_PLUGIN_DIR ) . '/'` van het pad af. Buiten de plugins-map blijft het volledige absolute pad over; "Open element" stuurt dat terug naar `save_element`/`delete_element`, die er `WP_PLUGIN_DIR` voor plakken, en opslaan faalt. Daarom haakt `fix_element_paths()` op `breakdance_loaded` prioriteit **11** (na Oxygens scan op 10): voor elke entry in `ElementStudioController::getInstance()->elements` waarvan het pad met onze `elements/`-map begint, wordt dat absolute begin vervangen door het relatieve pad (met `/` als scheiding, zoals Oxygen zelf). Dat is de `ponytail:`-notitie bij `fix_element_paths()`.

De map heeft geen plugin-header, de installer pakt alleen `pdk-theme-options/` uit de zipball en Oxygen-updates overschrijven alleen Oxygens eigen mappen. `ensure_dirs()` roept `pdk_ensure_storage_dir()` aan, zodat `.htaccess` en `index.php` in de opslagmap staan.

## Bestanden en gegevens

| Waar | Wat |
|---|---|
| `wp-content/uploads/pdk-theme-options/oxygen-elements/elements/<Naam>/` | `element.php`, `html.twig`, `css.twig`, `default.css` per element |
| `wp-content/uploads/pdk-theme-options/oxygen-elements/macros/<naam>.twig` | Twig-macro's |
| `wp-content/uploads/pdk-theme-options/oxygen-elements/presets/<Naam>.php` | preset-secties (herbruikbare control-groepen) |
| Database | niets, behalve `pdk_theme_options['oxygen_elements']['enabled']` |

Bij verwijderen van de plugin blijft de map staan (`uninstall.php` raakt hem niet), net als de andere klantbestanden in uploads. Element Studio zelf heeft een Delete-knop die een elementmap zonder prullenbak verwijdert — zet de map in versiebeheer.

## De tab

`render_tab_oxygen_elements()` (`includes/class-pdk-admin.php`) toont:

1. een waarschuwing als de map niet schrijfbaar is;
2. hoe je Element Studio opent: in de builder `Ctrl+Alt+E` (macOS `Cmd+Alt+E`) of via het instellingenmenu, dan "New element" en locatie "PDK Elementen";
3. per type een tabel van wat `scan()` op schijf vindt — elementen met hun slug (gelezen uit `element.php`, zodat hij ook klopt na een hernoemde map), macro's en presets met hun relatieve pad.

Aanmaken gebeurt in de builder; bestaande bestanden zijn vanuit de tab te bewerken (zie "Bewerken vanuit de tab").

## Voor themaontwikkelaars

- `PDK_Oxygen_Elements::dir()` — absolute map (`PDK_STORAGE_DIR . 'oxygen-elements/'`), met afsluitende separator.
- `PDK_Oxygen_Elements::relative_path( 'element' )` — het pad dat Oxygen kent, relatief aan de plugins-map; `null` zonder gemeenschappelijk begin.
- `PDK_Oxygen_Elements::parse( 'elements/Naam/html.twig' )` — alleen syntaxcontrole: `[ 'type', 'name', 'file', 'rel' ]` of `null`, zonder te kijken of het bestand bestaat.
- `PDK_Oxygen_Elements::scan()` — `[ 'element' => [...], 'macro' => [...], 'preset' => [...] ]` met namen zonder pad en extensie.
- `PDK_Oxygen_Elements::slug( 'Docs_Demo_Card' )` — `PdkElements\DocsDemoCard`, of `''` als het element niet bestaat.
- `PDK_Oxygen_Elements::builder_active()` — `true` zodra `\Breakdance\ElementStudio\registerSaveLocation()` bestaat.

Een element gebruiken in code gaat zoals elk Oxygen-element: via de slug in de MCP-tools, nesting-regels of dynamic data.

## Bewerken vanuit de tab

Op de tab "Element Studio" toont elk element zijn bestanden (`element.php`, `html.twig`, `css.twig`, `default.css`, voor zover aanwezig) met grootte en een link "Bewerken"; macro's en presets hebben die link ook. Die opent dezelfde CodeMirror-editor als de Libraries-tab (`?tab=oxygen_elements&edit=<Naam>/<bestand>`). De bundel kent alleen de talen php, css en javascript: `css.twig` en `default.css` krijgen css, de rest php-modus (kleurt HTML en Twig-tags redelijk, geen echte Twig-syntax).

- **Rechten.** Opslaan vraagt `PDK_CAP_EDIT_CODE`, net als de code-tabs. Zonder die rechten ziet iemand de lijst zonder links.
- **Pad-validatie.** `PDK_Oxygen_Elements::file_rel_path()` accepteert alleen `elements/<Naam>/<een van de vier bestanden>`, `macros/<naam>.twig` en `presets/<Naam>.php` (naam: `[A-Za-z0-9_-]+`) en alleen als het bestand al bestaat: bewerken, nooit aanmaken. `..`, backslashes en andere extensies geven `null`.
- **Opslaan** gaat via `pdk_write_storage_file()`: een `.php`-bestand met een syntaxfout wordt geweigerd. Er wordt **geen** `.bak` geschreven: Element Studio beheert deze bestanden en herschrijft ze zonder de back-up bij te werken, dus een vergelijking zou misleiden. Gebruik versiebeheer (git) om terug te gaan.
- **Geen integriteitshash.** Element Studio schrijft dezelfde bestanden, dus een opgeslagen hash zou later als "gewijzigd buiten de editor" lezen. `pdk_store_file_hash()` slaat `oxygen-elements/` over en de bestanden staan niet in `pdk_watched_files()`.
- **Valkuil: twee schrijvers.** Element Studio en deze editor schrijven dezelfde bestanden. Open het element na bewerken hier opnieuw in de builder, anders overschrijft de builder je wijziging bij zijn volgende save. Een PHP-syntaxfout in `element.php` wordt geweigerd; een Twig-fout niet en verschijnt pas in de builder.

## Via MCP

De module AI-agent toegang kan deze bestanden lezen en bewerken, maar geen elementen aanmaken: een element moet eerst in de builder bestaan (Element Studio → New element → opslaglocatie "PDK Elementen"), anders weigert de ability met `element_not_found`. Zie [agent-abilities.md](agent-abilities.md#element-studio-bestanden).

## Grenzen en valkuilen

- **Oxygen 6 only.** Oxygen Classic 4.x heeft geen Element Studio; daar doet de module niets. Breakdance werkt wel (zelfde engine, zelfde hook).
- **Namespace is vast.** Verander je `NAMESPACE_`, dan kennen bestaande elementen (met `namespace PdkElements;` in hun `element.php`) de nieuwe naam niet; Oxygen laadt ze nog wel, maar nieuwe elementen krijgen een andere prefix. Niet doen na de eerste save.
- **De module laadt niets zelf.** Staat Oxygen 6 wel geïnstalleerd maar is de module uit, dan worden de elementen niet geladen en verdwijnen ze uit de Add-panel; pagina's die ze gebruiken renderen die elementen niet. Aanzetten herstelt dat zonder dataverlies.
- **Elementen die Oxygen niet kan laden breken de builder.** Een syntaxfout in `element.php` (handmatig bewerkt) geeft een fatale fout op `breakdance_loaded`. Oxygens eigen advies: commit na elk afgerond element.
- **Leunt op een publieke `ElementStudioController::$elements`.** Dat is zo in Oxygen 6.2. Maakt Oxygen die eigenschap private of verandert de structuur van de entries, dan werkt "Open element → Save" niet meer voor elementen in uploads; de uitweg is terug naar een locatie in de plugins-map (daar is `fix_element_paths()` niet nodig).
- **Plugins en uploads op verschillende schijven.** Is er geen gemeenschappelijk pad (een aangepaste `UPLOADS` of `WP_PLUGIN_DIR` op een andere schijf), dan geeft `relative_path()` `null`: de module registreert niets en de tab toont een foutmelding (`PDK_Oxygen_Elements::$error`).
- **Directe HTTP-toegang tot `element.php`** geeft een fatale fout (de Oxygen-functies bestaan dan niet) — geen lek, wel een 500 in de logs. Dat geldt voor elk Oxygen-element.

## Zelftest

`php tests/test-oxygen-elements.php` — 31 controles: hooks en prioriteiten (9 en 11), drie registraties met het juiste pad/namespace/vlag, submappen aangemaakt, het opgegeven pad komt via `WP_PLUGIN_DIR` op dezelfde map uit als `dir()` (precies wat Oxygens glob doet), scannen van elementen/macro's/presets (lege elementmap genegeerd), slug uit `element.php`, `builder_active()`, `relative_path()`, `parse()`- en `file_rel_path()`-validatie (traversal, newline, onbekende bestandsnaam, macro/preset) en het padherstel van `fix_element_paths()` (eigen element wordt relatief, vreemd element blijft ongemoeid).
