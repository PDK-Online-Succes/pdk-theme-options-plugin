# Live-checklist Slice 2 — CSS Framework (dev.local)

Normatief: `docs/css-framework-brief.md` (AC-CSS-013, -016, -017, -018; L-delen). Voorbereiding (kopie naar mu-plugins, `LOCAL_PHP`, `LOCAL_PHP_INI`, `PLAYWRIGHT_DIR`, `wpl`-helper): zie `css-framework-slice1.md` §0. De plugin-kopie moet 2.13.0 zijn (`wpl eval 'echo PDK_PLUGIN_VERSION;'`).

**Oxygen blijft ACTIEF** (wens van de gebruiker, sinds 2026-11-10). Het script zet Oxygen alleen aan als het uit staat en herstelt de beginstand, dus normaal blijft het actief. Zet Oxygen bij de handmatige stappen nooit uit.

## 1. Geautomatiseerd

```bash
node tests/live/css-framework-slice2.mjs          # AC-013, -016, -018
node tests/live/css-framework-slice2.mjs 016
```

| AC | Wat het script meet |
|----|---------------------|
| AC-CSS-013 | `get-css-framework` als `pdkmin` geeft JSON met `tokens`; een tijdelijke redacteur (`pdk-cssfw-test`, wordt altijd verwijderd) wordt geweigerd; met de module uit bestaat de ability niet (module wordt teruggezet) |
| AC-CSS-016 | na `save()`: in `oxygen_variables_json_string` is `pdk-step-0` byte-gelijk aan `clamp(1.125rem, 1.0769rem + 0.1923cqi, 1.25rem)`, `pdk-site-width` = `90rem`, `pdk-measure` = `65ch`, V13: `pdk-lh-heading` = `1.1`, `pdk-lh-body` = `1.3` en het aantal `pdk-*`-entries = `count( tokens() )` (nu 37); geen `pdk-bo-*`, namen zonder `--`, geen `dynamicData`; tweede save: zelfde id's en aantal; collectie `PDK Framework`; `--pdk-step-0:` in Oxygens variabelenbestand (niet byte-vergeleken); effect met `css-framework.css` geblokkeerd (Playwright route-abort): p met `var(--pdk-step-0)` = 18px @400 en 20px @1600, h1 `step-5` = 61,0352px @1600 |
| AC-CSS-018 | module uit: geen `pdk-css-framework` in de HTML; de `pdk-*`-variabelen staan nog in `oxygen_variables_json_string` |

## 2. Handmatig

### AC-CSS-016 — builder-paneel
Open een pagina in de Oxygen-builder (ingelogd als `pdkmin`) → Variabelen: de collectie **PDK Framework** staat er met `pdk-step-0` e.d.

### AC-CSS-017 — builder-canvas laadt het framework
1. Open in de builder een pagina met een `section` met een `.pdk-full`- en een `.pdk-content`-kind (bijv. de demo-pagina `/css-framework-demo/`, zie slice1.md §4).
2. DevTools → Network, filter `css-framework`: het verzoek `css-framework.css?ver=…` komt uit het canvas-iframe (`?breakdance_iframe`) en geeft 200.
3. Het `.pdk-full`-kind loopt in het canvas van rand tot rand van de section en het `.pdk-content`-kind is 65ch breed, gecentreerd, net als op de frontend.
Faalt dit: onderzoek de iframe-hook (R-CSS-07); de tokens komen via de sync toch in de builder.

### AC-CSS-015 (sync-fout, optioneel live)
Niet live te forceren zonder de Oxygen-code te wijzigen; het gedrag staat in `php tests/run.php` (stubs). Controleer live alleen het succespad: na een admin-save op de tab verschijnt geen `notice-warning`.

## 3. Opruimen

```bash
wpl plugin list --name=oxygen --field=status                 # moet 'active' zijn
wpl user list --field=user_login | grep pdk-cssfw-test       # moet leeg zijn
wpl eval 'echo PDK_Settings::is_module_enabled("css_framework") ? "aan":"uit";'   # = beginstand
```
