# Voortgang — IMGX LCP-LQIP-placeholder

**Doel**: IMGX maakt per afbeeldingsmaat automatisch een LQIP (low-quality JPEG, ≥0,055 bpp) en zet die als
`background-image` op de `<img>`, volgens https://csswizardry.com/2023/09/the-ultimate-lqip-lcp-technique/.

Brief: `docs/imgx-lqip-brief.md` · Module-doc: `docs/modules/imgx.md`
Vorige ledger (Afbeeldingsmaten, uitgebracht in 2.10.0): zie git-historie van dit bestand.

## Profiel

- **Standard** — triagescore 4 (bestanden >10: 2, gedeeltelijk precedent: 1, rest-ambiguïteit: 1, onomkeerbaar: 0, parallel: 0)
- `Overgeslagen: architect` — bestaande IMGX-architectuur, uitbreiding van Generator/Renderer
- `Overgeslagen: ui-ux-designer` — één checkbox op de bestaande IMGX-tab
- `Overgeslagen: adversarial debate` — Standard draait nul cycli

## Runteller

11 (limiet 12, twee keer verhoogd door gebruiker) — backend-dev 9 (fixes 1–4, 194/2: 2 checks te streng, string blijft gesloten), tester 10, backend-dev 11 one-liner `;`→`%3B`; recheck = 12; `Overgeslagen: docs/test-plan.md — project kent die conventie niet, §11 van de brief is de traceability`; tester 6, backend-dev 7, code-review 8; elke extra rework raakt de circuit-breaker

## Infrastructuur

`Local stack: N/A — WordPress-plugin zonder externe runtime-afhankelijkheden. Zelftests via php tests/run.php; live check op dev.local.`

## Gebruikersantwoorden

| # | Vraag | Antwoord |
|---|---|---|
| V1 | Mini inline blur of LCP-LQIP uit het artikel? | LCP-LQIP (artikel) |
| V2 | Bereik | Alle IMGX-afbeeldingen |
| V3 | Standaard aan/uit | Standaard uit |
| V4 | R-101: CSS-achtergrond laadt ook bij loading=lazy — toch alle afbeeldingen? | Ja, toch alle afbeeldingen (risico geaccepteerd) |
| V5 | OQ-101: LQIP ook bij picture_enabled=0? | Ja, onafhankelijk; alleen `lqip_enabled` telt |

## Grondwaarheid

- Chromium telt een afbeelding pas als LCP-kandidaat bij ≥0,05 bits per pixel van de *weergegeven* grootte; auteur mikt op 0,055. Minimum bytes = w × h × 0,055 / 8. Upscalen verkleint het gerapporteerde oppervlak (`size × min(display, natural) / display`), dus LQIP nooit kleiner dan weergave. Bron: artikel, geverifieerd 2026-10-23.

## Besluiten (intern, omkeerbaar — gelogd)

| # | Besluit | Waarom |
|---|---|---|
| D1 | Eén LQIP-JPEG per geregistreerde maat, sidecar `naam.lqip.jpg`, geregistreerd in `_imgx_variants`; renderer kiest de LQIP van de maat van `src` | Server kent weergavegrootte niet; `src`-maat is de beste benadering en voorkomt upscalen |
| D2 | Kwaliteitslus vanaf q=10 omhoog tot bestand ≥ w×h×0,055/8 bytes, plafond q=60; startwaarde filterbaar | Artikel: minimum is ondergrens, q≈10 visueel acceptabel |
| D3 | Inline longhands op `<img>`, samengevoegd met bestaande `style` | Geen extra stylesheet; thema-`background-color` blijft intact |
| D4 | PNG met alfakanaal krijgt geen LQIP | Achtergrond zou permanent door transparantie heen schijnen |
| D5 | Preload alleen in `whole_page`-modus en alleen voor `fetchpriority=high` | Buiten de outputbuffer is `<head>` al verstuurd |
| D6 | Instelling `lqip_enabled`, standaard 0, onderdeel van `encoding_signature`; versie 2.12.0 (minor) | V3 + CLAUDE.md semver |

## Taken

| Stap | Agent | Status | Evidence |
|---|---|---|---|
| Thin delta brief | product-analyst | DONE | 11 AC (AC-101–111), OQ-101 beantwoord (V5), R-101 geaccepteerd (V4), D7 use cases inline |
| Brief-review | doc-reviewer | DONE_WITH_CONCERNS | 1 Critical (signature breekt bestaande tests), 8 Important, 4 Suggesties — alle verwerkt in rework 1/2 |
| Brief-rework 1 | product-analyst | DONE | 14 bevindingen verwerkt; nieuw D8, D9, AC-112, R-106; 12 AC |
| Brief-recheck 1 | doc-reviewer | DONE_WITH_CONCERNS → geaccepteerd | C1, I1–I8, S1–S4 opgelost. Rest: AC-104 (H) precondition `imgx_enabled_formats`→[] — niet-materieel, doorgegeven aan tester; backend-dev past brieftekst aan (geen rework 2) |
| Tests Mode A | tester | DONE | tests/test-imgx.php: 129 passed / 59 failed (alle 59 expected-red LQIP), baseline 88/0 intact; naamaannames 1–4 vastgepind door coördinator |
| Implementatie | backend-dev | DONE_WITH_CONCERNS | run.php 11/0 mislukt (coördinator herhaald: groen), test-imgx 188/0; 14 bestanden; versie 2.12.0; concern: kwaliteitslus niet live gedraaid (AC-103 L) |
| Fix-tests | tester | DONE_WITH_CONCERNS | esc_attr-stub = WP (geen dubbel-encode); +8 checks; 191/5 rood — wachten op bronfix (#1 uitbraak, #4 highx); #2 niet testbaar in harness → live |
| Fixes 1–4 + `;`→`%3B` | backend-dev | DONE | 196/0; WP 7.1.3 quality-reset geverifieerd (class-wp-image-editor.php:410-419) |
| Review-recheck | code-reviewer | DONE | alle 4 fixes correct, geen regressies |
| Code review | code-reviewer | DONE_WITH_CONCERNS | 0 Critical; Important: (1) url()-uitbraak via `&amp;quot;` want echte esc_attr dubbel-encodeert niet (stub wel), (2) wees-.lqip.jpg bij mislukte latere pass; Suggestie: (3) PNG-bron mogelijk q82 door WP≥6.1 quality-reset, (4) fetchpriority-regex losjes |

## Sessiestatus

- Fase: 2 — dispatch · Slice: 0 · Local stack: N/A
- Circuit-breaker 8/8: gebruiker koos fix-ronde 1–4, plafond 11 → 12
- Herdispatch: backend-dev 3/3, tester 2/3
- Code-review recheck (run 12): DONE — fixes 1–4 correct, geen regressies. run.php 11/0, test-imgx 196/0
- Open (live, dev.local): AC-103, AC-110, AC-112 + L-helften AC-102/104/107/109/111; review-#2 (wees-bestand) en #3 (PNG-kwaliteit) live bevestigen; R-101 request-telling
- Vervolgtaken: R-106 (verouderde varianten na beeldbewerking); WebP/AVIF-kwaliteit waarschijnlijk ook gereset door WP ≥6.1 get_output_format (niet geverifieerd)
- Live check (run 13, tester): AC-102/103/104/107/109/110/111/112 + preload PASS; LCP = `lqip-hero.lqip.jpg` @1480 ms vs controle avif @2280 ms; R-101: 9 extra requests op 10 afbeeldingen. BLOCKED (login): builder-canvas, batch-UI, anonieme HTTP REST. Niet geraakt: q=60-plafondtak, Oxygen Classic
- Bug live gevonden: opake PNG-submaten worden als `has-alpha` overgeslagen — WP's GD schrijft submaten als RGBA (colortype 6). Alleen `full` krijgt LQIP
- Gebruiker: PNG-fix + commit/PR. Besluit D4b: alfa één keer per bijlage op het origineel bepalen. backend-dev (run 14, incl. live) + tester (run 15) parallel
- PNG-fix DONE: alfa op origineel (`original_has_alpha()`), 202/0, live opake PNG 24 gegenereerd/0 overgeslagen, alfa-PNG alle maten has-alpha
- dev.local opgeruimd door coördinator: `imgx_settings` terug naar origineel (lqip stond nog aan), LQIP's van bijlagen 11/14 verwijderd, WebP/AVIF opnieuw gegenereerd, 0 `.lqip.jpg`
- Commit 246b529 op feature/imgx-lqip, PR #4 geopend
- PR #4 gemerged (7cae98d), tag v2.12.0, release gepubliceerd als latest (niet pre-release). Taak afgerond
