# Agent Abilities (AI-agent toegang, MCP)

Deze module publiceert drie *abilities* via de WordPress Abilities API waarmee een externe AI-agent de drie klantbestanden `custom-functions.php`, `custom-style.css` en `custom-script.js` kan lezen en overschrijven, plus één ability die de site-context (bedrijfsgegevens, openingstijden, beschikbare helpers en de live gerenderde frontend-HTML) teruggeeft. De plugin spreekt zelf géén MCP-protocol; een MCP-server-plugin publiceert geregistreerde abilities als tools (`modules/agent-abilities/class-pdk-agent-abilities.php:3-13`). Dit is de enige module die schrijftoegang tot uitvoerbare code aan een geautomatiseerde partij geeft — lees "Trust boundary" voordat je hem aanzet.

## Aan- en uitzetten

- Modulesleutel: `agent_abilities`, standaard `false` (`includes/class-pdk-settings.php:203-205`).
- Aan/uit via de Modules-tab; label "AI-agent toegang (MCP)" (`includes/class-pdk-settings.php:237`).
- **Geen eigen tab.** De module staat niet in `PDK_Admin::MODULE_TABS` (`includes/class-pdk-admin.php:26-36`) en niet in de tab-labels (`includes/class-pdk-admin.php:737-748`). Er is dus niets te configureren in de admin.
- Uit betekent echt uit: `load_modules()` laadt het bestand niet eens, dus de abilities worden nooit geregistreerd (`includes/class-pdk-plugin.php:87-98`).

## Instellingen

Alleen `enabled`. Verder heeft de module geen instellingen.

| sleutel | type | standaard | betekenis |
|---|---|---|---|
| `agent_abilities.enabled` | bool | `false` | module laden en de abilities registreren |

## Hoe het werkt

De constructor hangt twee callbacks op de hooks van de Abilities API: `wp_abilities_api_categories_init` → `register_category()` en `wp_abilities_api_init` → `register_abilities()`, beide op de standaardprioriteit 10 via de loader (`modules/agent-abilities/class-pdk-agent-abilities.php:32-33`, `includes/class-pdk-loader.php:17-25`). Bestaat de Abilities API niet (WordPress < 6.9, of geen API-plugin), dan vuren die hooks nooit en doet de module niets — dat is bewust de enige versiecontrole (`modules/agent-abilities/class-pdk-agent-abilities.php:30-31`).

Geregistreerd worden drie abilities in de categorie `pdk-theme-options` (`:37-40`), alle drie met `meta.show_in_rest => true` en `meta.mcp.public => true`, dus zichtbaar als REST-resource en als MCP-tool:

| ability | permission_callback | annotations |
|---|---|---|
| `pdk-theme-options/read-custom-code` | `pdk_current_user_can_edit_code` (`:63`) | readonly, niet-destructief (`:68`) |
| `pdk-theme-options/write-custom-code` | `pdk_current_user_can_edit_code` (`:94`) | **destructief** (`:99`) |
| `pdk-theme-options/get-site-info` | `current_user_can( 'manage_options' )` (`:109`) | readonly (`:114`) |

`read()` vertaalt de input-enum (`php`/`css`/`js`) naar een vaste bestandsnaam en geeft de inhoud terug, of een lege string als het bestand nog niet bestaat (`:124-134`). `write()` doet dezelfde vertaling en delegeert naar `pdk_write_storage_file()` (`:137-152`). `site_info()` bouwt één antwoord met bedrijfsgegevens, social links, openingstijden, periodes, de status van elke module, de PHP-helpers en per geregistreerde frontend-uitvoer de markup-beschrijving én de *nu* gerenderde HTML (`:158-262`).

### Trust boundary — wat een agent wél en níét kan

De sleutel is de capability `pdk_edit_custom_code` (constante `PDK_CAP_EDIT_CODE`, `pdk-theme-options-plugin.php:36`). De agent logt in als een gewone WordPress-gebruiker; die gebruiker heeft de capability nodig.

- **Geen administrator-fallback.** `pdk_current_user_can_edit_code()` controleert uitsluitend de capability; ook een administrator zonder die cap wordt geweigerd (`includes/helpers.php:13-22`).
- Toekennen gebeurt per gebruiker via de Rechten-tab (`includes/class-pdk-admin.php:2269-2311`); die tab haalt de cap eerst van álle rollen en álle gebruikers af en geeft hem daarna alleen aan de aangevinkte gebruikers (`includes/class-pdk-admin.php:486-513`). Bij installatie krijgt alleen de activerende/eerste beheerder de cap (`includes/class-pdk-plugin.php:138-147`, `:190-198`).
- Staat `PDK_CODE_EDITORS` in `wp-config.php`, dan is de lijst vastgezet en doet de Rechten-tab niets meer (`includes/helpers.php:205-215`, `includes/class-pdk-admin.php:479-483`).
- **Dubbele controle bij schrijven**: naast de `permission_callback` checkt `pdk_write_storage_file()` de capability opnieuw (`includes/helpers.php:75-78`). De zelftest dekt dit pad (`tests/test-agent-abilities.php:182-183`).

Wat een agent **wel** kan met die cap:

- De drie bestanden volledig lezen en volledig overschrijven. Er is geen patch-modus: `write` vervangt de hele inhoud (`:74`, `:144-151`).
- Willekeurige PHP neerzetten in `custom-functions.php`. Staat de module Custom PHP Functions aan, dan wordt dat bestand op `after_setup_theme` prioriteit 99 ge-`include`d op élke request (`modules/custom-functions/class-pdk-custom-functions.php:24`, `:51-66`). Schrijftoegang hier is dus in de praktijk code-uitvoering op de server. Hetzelfde geldt, minder ernstig, voor de JS die bij elke bezoeker in de footer terechtkomt.
- Met `manage_options` alle bedrijfsgegevens, openingstijden en de lijst met ingeschakelde modules opvragen — `get-site-info` vereist géén code-cap (`:109`).

Wat een agent **niet** kan:

- Andere bestanden benaderen. Alleen de drie sleutels uit `self::FILES` worden geaccepteerd; alles anders geeft `pdk_unknown_file` terug vóór enige bestandsoperatie (`:21-25`, `:265-280`). Padtraversal is bovendien afgevangen in `pdk_storage_rel_path()`, dat alles tot `basename()` reduceert (`includes/helpers.php:283-288`). Getest met `../wp-config` (`tests/test-agent-abilities.php:123`).
- Bestanden verwijderen of hernoemen — er is geen delete-ability.
- Kapotte PHP opslaan: `pdk_write_storage_file()` draait `token_get_all( $content, TOKEN_PARSE )` en weigert bij een `ParseError`, zonder het bestaande bestand aan te raken (`includes/helpers.php:87-101`; getest in `tests/test-agent-abilities.php:134-136`).
- De vorige versie onherstelbaar wissen: vóór het schrijven wordt een `.bak`-kopie gemaakt (`includes/helpers.php:104-106`). Let op: één niveau diep, elke nieuwe write overschrijft de back-up.
- Instellingen wijzigen, plugins installeren of gebruikers aanmaken — daar is geen ability voor.

## Bestanden en gegevens

Geschreven wordt uitsluitend in `PDK_STORAGE_DIR` = `wp-content/uploads/pdk-theme-options/` (`pdk-theme-options-plugin.php:32`): het doelbestand plus `<bestand>.bak`. Die map krijgt een `.htaccess` die directe toegang tot `.php` en `.bak` blokkeert en directory listing uitzet (`includes/helpers.php:39-48`).

In de database schrijft elke write een SHA-256 vingerafdruk weg in de optie `pdk_file_hashes` (`includes/helpers.php:112`, `:296-300`). Dat is de integriteitsboekhouding: `custom-functions.php` wordt niet ge-`include`d als de hash niet meer klopt (`modules/custom-functions/class-pdk-custom-functions.php:58-63`). Een bestand dat de agent via deze ability schrijft, krijgt dus een geldige hash en wordt gewoon geladen — de integriteitscontrole beschermt tegen wijzigingen *buiten* de plugin om, niet tegen de agent.

Bij uninstall blijven de bestanden in de storage-map bewust staan (`uninstall.php:5-6`); `pdk_theme_options` en `pdk_file_hashes` worden verwijderd (`uninstall.php:16-17`) en de capability wordt van alle rollen en gebruikers afgehaald (`uninstall.php:59-75`).

## Voor themaontwikkelaars

- `pdk_register_frontend_output( $name, $render, $markup )` — wat je hiermee registreert, verschijnt automatisch in `get-site-info` onder `frontend_output`, inclusief live gerenderde HTML (`includes/helpers.php:158-168`, `modules/agent-abilities/class-pdk-agent-abilities.php:244-262`).
- `PDK_Agent_Abilities::CATEGORY` = `pdk-theme-options` — de prefix van alle ability-namen (`:27`).
- `pdk_current_user_can_edit_code()` — dezelfde poortwachter als de code-editor (`includes/helpers.php:20-22`).

## Grenzen en valkuilen

- `get-site-info` rendert alle geregistreerde frontend-uitvoer daadwerkelijk uit (`:248`). Een trage of context-afhankelijke render (productpagina) levert een lege string of vertraging op; dat is geen fout.
- De ability-beschrijvingen zijn prompt-instructies aan de agent ("lees eerst", "codeer niets hard", `:74`, `:105`). Het zijn aanwijzingen, geen afdwingbare regels — een agent die ze negeert wordt nergens tegengehouden.
- `write` is geannoteerd als `idempotent` (`:99`) terwijl hij de vorige inhoud vernietigt; ga er niet vanuit dat een retry veilig is als de agent tussentijds iets anders schreef.
- De REST-afscherming van de Security-module beschermt alleen routes met prefix `/wp/v2/` (`includes/class-pdk-settings.php:102`, `modules/security/class-pdk-security.php:442-449`). Ability-endpoints vallen daar niet onder; hun beveiliging komt volledig van de `permission_callback`.
- Zet je de module Custom PHP Functions uit, dan blijft de agent PHP kunnen schrijven — het draait alleen niet. Het bestand blijft staan en gaat draaien zodra iemand de module weer aanzet.
- De capability is per gebruiker, niet per applicatiewachtwoord: een agent-account dat mag schrijven, mag dat via elke inlogmethode.

## Zelftest

```
php tests/run.php          # alles
php tests/test-agent-abilities.php
```

Gedekt: onbekende bestandssleutel en padtraversal geven `pdk_unknown_file` (`tests/test-agent-abilities.php:122-123`), niet-bestaand bestand leest als lege string (`:126`), schrijven/teruglezen (`:129-130`), kapotte PHP wordt geweigerd zonder het bestaande bestand te overschrijven (`:133-136`), de opbouw van `site_info()` inclusief live gerenderde `html_now` (`:139-179`), en weigeren van `write` zonder capability (`:182-183`).
