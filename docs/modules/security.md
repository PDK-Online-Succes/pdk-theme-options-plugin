# Security

De security-module bundelt zeven maatregelen tegen de aanvallen die op deze sites daadwerkelijk
voorkomen: backdoors die hun payload via een custom header binnensmokkelen, MU-plugins die een
scanner of hostingpartij achterlaat, plugins die nooit actief mogen zijn, verplichte plugins die
stilletjes uitvallen, wijzigingen in `mu-plugins/`, XML-RPC en de REST-routes die gebruikersnamen
weggeven. Alles zit in één klasse, `pdk-theme-options/modules/security/class-pdk-security.php`.

## Aan- en uitzetten

De module heeft **geen modulesleutel en is niet uit te zetten**. Hij staat niet in `$module_map`
maar wordt als eerste, vóór elke andere module, hard ingeladen door de plugin-orchestrator —
juist omdat de header-firewall vóór al het andere moet lopen
(`includes/class-pdk-plugin.php:46-48`). Wel bestaat er een eigen tab "Security"
(`includes/class-pdk-admin.php:732`), die altijd zichtbaar is. Geen afhankelijkheden.

Van de zeven maatregelen staan er vier vast in de code (header-firewall, MU-blacklist,
plugin-blacklist, MU-integriteitscontrole); instelbaar zijn alleen de verplichte plugins, XML-RPC
en de REST-afscherming (`class-pdk-security.php:5-19`, `includes/class-pdk-admin.php:2055-2060`).

## Instellingen

Alles staat onder de sleutel `security` in de optie `pdk_theme_options`
(`includes/class-pdk-settings.php:84-105`).

| Sleutel | Type | Standaard | Betekenis |
| --- | --- | --- | --- |
| `required_plugins` | `string[]` (slugs) | `[]` | Plugins die actief moeten blijven; uitval wordt gemaild en getoond. |
| `xmlrpc_disable` | bool | `true` | XML-RPC volledig dichtzetten. |
| `rest_require_login` | bool | `true` | Beschermde REST-routes alleen voor ingelogde gebruikers. |
| `rest_protect_routes` | `string[]` | `['/wp/v2/']` | Route-prefixen die inloggen vereisen. Blocklist, geen allowlist. |
| `rest_allow_ips` | `string[]` | `[]` | IP's die de beschermde routes zonder inloggen mogen gebruiken. |

Opslaan gebeurt in `PDK_Admin::save_security()` (`includes/class-pdk-admin.php:2223-2260`), niet
via `PDK_Settings::update()`: `array_replace_recursive()` zou lijsten per index samenvoegen,
waardoor een uitgevinkte plugin bleef staan. Bij opslaan wordt `required_plugins` gefilterd op
werkelijk geïnstalleerde slugs (`:2229-2231`), krijgt elke route-prefix een beginslash
(`:2235-2238`) en worden IP's gecontroleerd met `filter_var(..., FILTER_VALIDATE_IP)`
(`:2239-2242`). De checkbox "Deze lijst leegmaken bij opslaan" wist de log van geweigerde routes
(`:2256-2258`).

## Hoe het werkt

De constructor (`class-pdk-security.php:62-81`) draait al bij het laden van de module, dus vóór
`init`. Twee dingen gebeuren daar meteen:

- **Header-firewall** — `block_suspicious_headers()` (`:100-116`) loopt door `$_SERVER` en
  beëindigt het request met een 403 bij een headernaam die alleen uit 6-10 hextekens bestaat, of
  bij een headerwaarde waarin `eval(`, `base64_decode(`, `system(`, `exec(` of `assert(` staat.
  Dat gebeurt direct, niet op een hook: een backdoor die op `plugins_loaded` al heeft gedraaid is
  te laat om nog te blokkeren.
- **MU-plugin blacklist** — `remove_blacklisted_muplugins()` hangt aan `muplugins_loaded`
  prioriteit 1, tenzij die actie al gevuurd is; in reguliere plugin-modus is dat het geval en
  wordt de opruiming meteen uitgevoerd (`:69-73`). Verwijderd worden drie exacte bestandsnamen en
  het patroon `*.suspected` (`:40-51`, `:132-152`).

De rest hangt aan hooks:

| Hook | Prioriteit | Methode |
| --- | --- | --- |
| `admin_init` | 10 | `deactivate_dangerous_plugins()` (`:75`, `:165-196`) |
| `init` | 0 | `check_mu_integrity()` (`:76`, `:302-360`) |
| `init` | 0 | `check_required_plugins()` (`:77`, `:235-267`) |
| `init` | 0 | `harden_xmlrpc()` (`:78`, `:370-385`) |
| `admin_notices` | 10 | `show_required_plugins_notice()` (`:79`, `:270-288`) |
| `rest_authentication_errors` | 10 | `restrict_rest_api()` (`:80`, `:404-433`) |

Prioriteit 0 op `init` is gekozen zodat XML-RPC en de controles vóór de meeste plugin-code lopen.

**Plugin-blacklist** deactiveert `wp-file-manager` en `wtec-webp` op slug en toont daarna een
admin-notice (`:57-60`, `:165-196`). Alleen `active_plugins` wordt bekeken, niet
`active_sitewide_plugins` — expliciet als beperking genoteerd in de code (`:161-164`).

**Verplichte plugins**: `missing_required_plugins()` (`:207-226`) vergelijkt de ingestelde slugs
met de actieve plugins (op multisite inclusief netwerk-geactiveerde, `:216-218`) en sorteert het
resultaat, zodat twee controles vergelijkbaar zijn. `check_required_plugins()` mailt alleen als de
lijst verandert ten opzichte van de optie `pdk_missing_required_plugins`; komt alles weer goed, dan
wordt die stand gewist zodat een volgende uitval opnieuw gemeld wordt (`:235-267`).

**MU-integriteit**: sha256 per `*.php` in `mu-plugins/`, baseline in de optie `pdk_mu_hashes`
(bewust niet in `mu-plugins/` zelf) en maximaal één controle per uur via de transient
`pdk_mu_integrity_checked` (`:302-338`). De eerste run legt de baseline vast zonder te mailen
(trust-on-first-use). Bij een afwijking wordt de baseline meteen bijgewerkt, anders volgt elk uur
dezelfde mail (`:337-338`).

**XML-RPC**: `xmlrpc_enabled` op `__return_false` is niet genoeg — pingback blijft dan werkbaar —
dus wordt ook `xmlrpc_methods` leeggemaakt, de `X-Pingback`-header verwijderd en `rsd_link` uit
`wp_head` gehaald (`:366-385`).

**REST-afscherming**: `restrict_rest_api()` laat een eerdere authenticatie-uitkomst (`true` of een
`WP_Error`) ongemoeid (`:406-408`), controleert de route uit
`$GLOBALS['wp']->query_vars['rest_route']` tegen de prefixen, hoofdletterongevoelig via `stripos`
omdat WordPress zijn routes ook zo matcht (`:442-450`), en honoreert de IP-whitelist (`:422-424`).
Geweigerde routes worden onthouden in `pdk_rest_blocked_routes`, hoogstens één schrijfactie per
route per uur en één nieuwe route per uur, met een maximum van 20 (`:35`, `:459-485`). De route
wordt op 100 tekens afgekapt en van regeleindes ontdaan, anders schrijft een bezoeker zijn eigen
regels in de foutlog (`:460-462`).

Alle meldingen gaan met het voorvoegsel `[PDK Security]` naar `error_log()`.

## Bestanden en gegevens

Op schijf schrijft de module niets; hij *verwijdert* alleen geblacklistte bestanden uit
`mu-plugins/` (`:139-151`).

In de database:

| Optie | Inhoud |
| --- | --- |
| `pdk_mu_hashes` | sha256 per bestandsnaam in `mu-plugins/` (`:26`) |
| `pdk_missing_required_plugins` | laatst gemelde lijst met uitgevallen verplichte plugins (`:29`) |
| `pdk_rest_blocked_routes` | route => unix-tijd van laatste weigering, max. 20 (`:32-35`) |
| transient `pdk_mu_integrity_checked` | rem van één uur op de integriteitscontrole (`:309`) |

De instellingen zelf staan onder `security` in `pdk_theme_options`. Alle drie de opties worden bij
uninstall verwijderd (`uninstall.php:20-23`), net als `pdk_theme_options` zelf (`:16`).

## Voor themaontwikkelaars

Drie publieke statische methoden, bedoeld voor de admin-tab maar bruikbaar van buiten:

- `PDK_Security::missing_required_plugins(): string[]` — slugs die verplicht zijn maar niet actief
  (`:207`).
- `PDK_Security::blocked_routes(): array<string,int>` — geweigerde REST-routes, nieuwste eerst
  (`:492`).
- `PDK_Security::clear_blocked_routes(): void` — wist die lijst (`:500`).

De module registreert zelf geen eigen filters of acties.

## Grenzen en valkuilen

- **Niet uit te zetten.** Zit je vast door de header-firewall of de plugin-blacklist, dan is de
  enige weg de code aanpassen — er is geen schakelaar. De blacklists zijn constanten (`:40-60`).
- **`client_ip()` leest alleen `REMOTE_ADDR`** (`:512-514`). Achter Cloudflare of een reverse proxy
  is dat het IP van de proxy en is de whitelist onbruikbaar. Zo genoteerd in de code.
- **Multisite en de plugin-blacklist**: netwerk-geactiveerde plugins worden niet gedeactiveerd
  (`:161-164`). De controle op verplichte plugins kijkt wél naar `active_sitewide_plugins`
  (`:216-218`) — die twee lopen dus niet gelijk.
- **Een eigen route-prefix kan de site breken.** `/wc/` of `/` toevoegen zet betaalwebhooks,
  checkout-blocks en formulieren dicht. De lijst met geweigerde routes op de tab is precies
  bedoeld om dat te zien voordat een klant belt (`:452-458`).
- **Tijdens een scan verdwijnt een echte nieuwe weigering uit beeld**: is er dit uur al iets
  gelogd, dan komt een onbekende route er niet bij (`:470-478`). Wis de lijst om weer te zien wat
  er nu misgaat.
- **De MU-baseline wordt bijgewerkt bij élke afwijking**, dus ook bij een kwaadaardige wijziging.
  De mail is de melding; de tweede keer is het stil.
- **Blacklisted MU-plugins komen terug** als de hostingpartij ze opnieuw plaatst; ze worden dan
  elke pageload opnieuw verwijderd.

## Zelftest

```
php tests/test-security.php
```

Of samen met alle andere: `php tests/run.php`. Gedekt zijn de header-firewall, de MU-blacklist in
zowel MU- als plugin-modus, de plugin-blacklist, het mailgedrag bij verplichte plugins (inclusief
het niet-herhalen en het opnieuw melden na herstel), de MU-integriteitscontrole met baseline en
uurrem, de REST-afscherming (hoofdletters, eigen prefixen, whitelist, niet-overrulen van eerdere
authenticatie), het onthouden van geweigerde routes met de scanrem en de grens van 20, en XML-RPC
aan/uit (`tests/test-security.php:168-472`).
