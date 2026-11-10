# Live-checklist Slice 1 — CSS Framework (dev.local)

Normatief: `docs/css-framework-brief.md` (AC-CSS-007, -009, -010, -019, -020, -022, -023, L-delen). Slice 2 (L: -013, -016, -017, -018) valt hier buiten.
Draai dit pas na de implementatie en na het kopiëren van de plugin.

## 0. Voorbereiding (bash, vanuit de repo-root)

```bash
# Plugin is een KOPIE in mu-plugins: na elke wijziging opnieuw kopiëren.
SITE="$HOME/Local Sites/dev/app/public"
rm -rf "$SITE/wp-content/mu-plugins/pdk-theme-options"
cp -r pdk-theme-options "$SITE/wp-content/mu-plugins/pdk-theme-options"

# WP-CLI met Local's PHP en de php.ini van de site (dev = run-id 3APS_fKWy; MySQL-poort kan wisselen, Local moet draaien).
export LOCAL_PHP="$HOME/AppData/Roaming/Local/lightning-services/php-8.5.12+0/bin/win64/php.exe"
export LOCAL_PHP_INI="$HOME/AppData/Roaming/Local/run/3APS_fKWy/conf/php/php.ini"
export WPCLI="C:/Program Files (x86)/Local/resources/extraResources/bin/wp-cli/wp-cli.phar"
wpl() { "$LOCAL_PHP" -c "$LOCAL_PHP_INI" "$WPCLI" --path="$SITE" "$@"; }

wpl plugin list --name=oxygen --field=status      # beginstand noteren (normaal: inactive)
wpl eval 'echo PDK_PLUGIN_VERSION;'               # controle dat de kopie geladen is
```

Module aan en bestand laten genereren (zonder de tab, voor de scriptdraai):

```bash
wpl eval '$o=get_option("pdk_theme_options",[]); $o["css_framework"]["enabled"]=true; update_option("pdk_theme_options",$o); echo "aan";'
curl -s http://dev.local/ > /dev/null              # register() regenereert css-framework.css als het ontbreekt
ls -l "$SITE/wp-content/uploads/pdk-theme-options/css-framework.css"
```

## 1. Geautomatiseerd: Playwright-script

**Oxygen wordt nooit standaard uitgezet.** Het script draait alles met Oxygen **actief** (Modern Normalize aan; AC-007, -024, -019, -020, -022, -009). De Oxygen-inactief-varianten (AC-007/-019/-020/-009 zonder Oxygen) draaien alleen met `PDK_LIVE_OXYGEN_OFF=1`; zonder die variabele print het script één regel dat ze zijn overgeslagen. De beginstand van Oxygen wordt in `finally` hersteld.

Playwright staat niet standaard globaal. Eenmalig, gepind (het script zoekt in `$PLAYWRIGHT_DIR`, de globale npm-root, `./node_modules` en `tests/live/node_modules`, en stopt met een duidelijke melding als het ontbreekt):

```bash
npm i --prefix "$TEMP/pw" playwright@1.49.1 && npx --prefix "$TEMP/pw" playwright install chromium
export PLAYWRIGHT_DIR="$TEMP/pw/node_modules/playwright"
```

De `wpl`-helper in het script filtert PHP-startupwaarschuwingen (Local's php.ini print een `php_imagick.dll`-Warning op stdout).

```bash
node tests/live/css-framework-slice1.mjs            # alles: AC-007, -019, -020, -022, -009
node tests/live/css-framework-slice1.mjs 007 020    # of een deel
```

Het script (Playwright globaal, Chromium aanwezig):
- zet Oxygen per case aan/uit (`wp plugin activate|deactivate oxygen`) en herstelt de beginstand in een `finally` (normaal: Oxygen **actief**; ook de module-stand `css_framework` wordt in `finally` hersteld);
- zet Modern Normalize via `\Breakdance\Settings\set_setting( 'modern_normalize_enabled', … )` en herstelt de beginwaarde;
- zet markup in de `<body>` van de frontend (de stylesheets uit `<head>` blijven gelden), meet met `getComputedStyle` en `getBoundingClientRect`, tolerantie ±0,05 px;
- zet voor AC-019/-020 tijdelijk CSS in `custom-style.css` + Custom CSS-module aan en zet beide terug;
- eindigt met exitcode 1 bij een mislukte check.

Dekking per AC (zoals in de brief):

| AC | Oxygen | Wat het script meet |
|----|--------|---------------------|
| AC-CSS-007 | **inactief**, `dir=ltr` | type @400/@1600 (`#p1`,`#p0` 18/20px; `#p2` 18,769px; V12: losse tekst in de section 18/20px); `#s` is `display: grid` + `container-type: inline-size`, geen padding/marge; banen bij 1600 met probes vóór de section (`#g`, `#ps`, `#m`): wide = `min(w(#s) − 2·w(#g), 1440)` gecentreerd, content = `min(65·1ch-probe, w(#s) − 2·w(#g))` (V12: = 65 × de 1ch-probe in de section ±1px), popout = content + 2·`#ps`, `#pst`/`#wst`/`#wen`/`#fst`/`#fen` van/tot de juiste randen, full = sectionbreedte; bij 400 `#c` = `#po` = `#d` = `w(#s) − 2·w(#g)` en `#f` = `#s`; `.pdk-off` = `display: block`, `container-type: normal`, font-size = body; V12: `#m` en `#ch` in een overflow-wrapper in de section: font-size 18/20px, w(#m) = 65 × w(#ch) ±1px, w(#m) @1600 > @400; grid 4 kolommen @1600 / 1 @400 (ingeklapte `0px`-auto-fit-sporen tellen niet mee); `scrollWidth ≤ clientWidth` @320 op een eigen pagina; `position: fixed` blijft aan de viewport |
| AC-CSS-024 | **inactief**, `dir=rtl`, 1600 | dezelfde pagina: `.pdk-full-start` aan de **rechter**rand (`R(#fst)=R(#s)`, `L(#fst)=L(#d)`), `.pdk-full-end` links, `#wst` van `R(#d)` tot `L(#c)`, `#pst` van `R(#po)` tot `L(#c)`; breedtes gelijk aan LTR |
| AC-CSS-019 | **inactief** | zonder snippet `display: grid` + `inline-size`; met de uitweg-snippet (`.woocommerce section { display: block; container-type: normal; font-size: inherit; }`) `display: block`, `normal`, de font-size van `.woocommerce` (31px in de test; zonder snippet step-0 = 20px) en de div even breed als de section; een gewone section blijft grid |
| AC-CSS-020 | actief + Modern Normalize **aan** (primair), actief + default normalize, inactief | `h1` 44,7904 / 61,0352px met line-height 53,2694 / 71,1387px (V13: 1,1 × font-size + 4px; ink-guard ≥ 1,15 × font-size), `h6` 18/23,8 en 20/26, `p` 18/27,4 en 20/30 (1,3 × font-size + 4px; ratio's en leadings uit `config()`), `h1` margin-block-start 0, `custom-style.css` `h1 { font-size: 10px }` wint; bij de default normalize alleen font-size en line-height (D-L2: h1-marge niet gecontroleerd) |
| AC-CSS-022 | **actief**, Modern Normalize **aan** | `#b`/`#u` = flow-space-probe (18/20px), `#h` = flow-heading-probe (36/40px), `#a` 0, `<p>` in `.pdk-grid` en `#c` in `.pdk-flow-off` 0, `#y` (p direct na p `#x`, beide grid-items) = flow-space en de afstand `#x`→`#y` is precies die marge; D-L1-regressie: `#d` staat met Modern Normalize aan op de wide-baan (breedte `min(w(#s) − 2·w(#g), 1440)`, gecentreerd) |
| AC-CSS-009 (L) | inactief én actief | aantal `css-framework.css?ver=` = 1 (0 met module uit), volgorde normalize (alleen actief) < framework < custom-style (positie in de volledige HTML); `<style id="global-styles-inline-css">` staat met Oxygen **inactief** vóór het framework, met Oxygen **actief** in de footer (ná het framework en ná `</main>`; onschadelijk, oxygen-zero heeft geen theme.json); custom-style laadt ook met module uit |

NB bij AC-CSS-020: de brief noemt een HTML Code-element in een Oxygen-pagina; het script injecteert dezelfde markup in de frontend-`<body>`. Dat meet hetzelfde (alle stylesheets uit `<head>` zijn aanwezig). Wil je het de letterlijke route, bouw dan één Oxygen-pagina met de markup en draai de metingen met die URL in `open()`.

## 2. Handmatig / WP-CLI-controles (niet in het script)

### AC-CSS-009 (L) — volgorde en aantal in de HTML

```bash
# Oxygen inactief:
wpl plugin deactivate oxygen
curl -s http://dev.local/ | grep -n -o 'id=.global-styles-inline-css\|css-framework.css?ver=\|custom-style.css'   # global styles staan inline: <style id="global-styles-inline-css">
curl -s http://dev.local/ | grep -c 'css-framework.css?ver='          # verwacht 1
# Oxygen actief (normalize erbij):
wpl plugin activate oxygen
curl -s http://dev.local/ | grep -n -o 'normalize[^"]*\.css\|id=.global-styles-inline-css\|css-framework.css?ver=\|custom-style.css'
# Module uit: 0 keer, custom-style.css laadt nog steeds
wpl eval '$o=get_option("pdk_theme_options",[]); $o["css_framework"]["enabled"]=false; update_option("pdk_theme_options",$o);'
curl -s http://dev.local/ | grep -c 'css-framework.css?ver='          # verwacht 0
curl -s http://dev.local/ | grep -c 'custom-style.css'                  # verwacht >= 1 (Custom CSS aan, bestand niet leeg)
# Herstellen:
wpl eval '$o=get_option("pdk_theme_options",[]); $o["css_framework"]["enabled"]=true; update_option("pdk_theme_options",$o);'
wpl plugin deactivate oxygen
```

### AC-CSS-010 (L) — admin-tab (inloggen als `pdkmin` via het normale loginformulier; wachtwoord bij de gebruiker)

1. `http://dev.local/wp-admin/admin.php?page=pdk-theme-options&tab=css_framework` met de module **uit** → valt terug op Modules.
2. Module aan (Modules-tab) → tab "CSS Framework" zichtbaar.
3. Bestandsstempel vóór/na een geldige save:
   ```bash
   F="$SITE/wp-content/uploads/pdk-theme-options/css-framework.css"
   stat -c '%Y' "$F"; md5sum "$F"        # vóór
   ```
   Geldig opslaan (bijv. `steps_up` 4) → melding "Instellingen opgeslagen" en `stat -c '%Y'` is nieuw; `--pdk-step-5` is uit het bestand.
4. Ongeldig opslaan (`min_viewport` = 3000) → `notice-error` met `min_viewport` in de tekst; `md5sum "$F"` is gelijk aan de vorige waarde.
5. De tab toont per token naam, min px, max px en `clamp()`, plus `.pdk-off`, `.pdk-full`, `.pdk-flow-off` en de uitweg-snippet (`.woocommerce section { container-type: normal; }`).
6. Zet daarna `steps_up` terug op 5 (de defaults) zodat de scriptdraai de verwachte waarden ziet.

### AC-CSS-023 (L) — Modern Normalize-melding (Oxygen actief)

```bash
wpl plugin activate oxygen
wpl eval '\Breakdance\Settings\set_setting("modern_normalize_enabled", false);'
wpl eval 'var_dump( \Breakdance\Settings\get_bool_setting( "modern_normalize_enabled" ) );'   # bool(false)
# Tab herladen (ingelogd): notice-warning met "Modern Normalize" en "Oxygen → Settings → Advanced" staat er.
wpl eval '\Breakdance\Settings\set_setting("modern_normalize_enabled", true);'
wpl eval 'var_dump( \Breakdance\Settings\get_bool_setting( "modern_normalize_enabled" ) );'   # bool(true)
# Tab herladen: melding weg.
wpl plugin deactivate oxygen
# Tab herladen met Oxygen inactief: geen melding.
```

De volgende stap in de brief gebeurt via Oxygens UI (Settings → Advanced → Modern Normalize → Enable); `set_setting` is de CLI-equivalent en volstaat voor de melding. De tab-render zelf mag de Oxygen-optie nooit wijzigen: controleer na het herladen dat `get_bool_setting` dezelfde waarde teruggeeft als vóór het laden.

## 3. Opruimen

```bash
wpl plugin list --name=oxygen --field=status          # moet gelijk zijn aan de beginstand (standaard: inactive)
wpl eval '\Breakdance\Settings\set_setting("modern_normalize_enabled", false);'   # alleen als dat de beginstand was en Oxygen actief is; het script doet dit zelf
```

Het script zet `custom-style.css` en de Custom CSS-module terug naar hun beginstand; controleer dat met `wpl eval 'echo PDK_Settings::is_module_enabled("custom_css") ? "aan":"uit";'`.

## 4. Demo-pagina (visuele check op de frontend)

Oxygen-pagina `http://dev.local/css-framework-demo/` met alle framework-elementen, per element de verwachte waarde (uit `PDK_CSS_Framework::tokens()`) en een live meetwaarde (groen = klopt, rood = afwijking; balk bovenaan telt afwijkingen). V14: alle verwachte waarden en labels worden in de browser uit de `:root`-tokens berekend (clamp-parser, `--pdk-lh-*`, `--pdk-leading-*`, grid-min in `ch` via probes), dus na een instellingswijziging volstaat een paginavernieuwing; alleen `data-vmin`/`data-vmax` en de lijst met stappen zijn gebakken en vragen regeneratie. AC-007 verwacht het aantal grid-kolommen uit `--pdk-grid-min` (30ch). (Her)bouwen, na `wpl`-setup uit §0, met Oxygen actief:

```bash
wpl --user=1 eval-file tests/live/css-framework-demo.php   # idempotent: verwijdert de pagina en bouwt hem opnieuw via oxygen/create-post + html-to-page + edit-post (JS-element)
```
Bekijk op 400/1024/1600 px; `#demo-root` heeft `data-bad`/`data-tot` (aantal afwijkingen/controles).

