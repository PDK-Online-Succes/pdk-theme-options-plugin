# Progress — SEO Health Check als module

**Goal**: De losse plugin `seo-health-check` (v0.10.1, `../seo-health-check`) opnemen als PDK-module, volgens het IMGX-precedent.

Branch: `feature/seo-health-check` · Brief: `docs/seo-health-check-brief.md` · Vorige ledger (CSS Framework, uitgebracht in 2.13.0): zie de git-geschiedenis van dit bestand.

## Profile

- **Standard**, triage score 4. Files >10: 2, novel pattern: 0 (precedent `modules/imgx/`), ambiguous: 1, irreversible: 1 (eigen DB-tabellen, uninstall), parallel: 0.
- `Skipped: architect` — IMGX-patroon (wrapper + ongewijzigde code in `includes/`) volstaat.
- `Skipped: ui-ux-designer` — bestaande SEOHC-schermen verhuizen naar sub-tabs in bestaande PDK-adminstijl.
- `Skipped: adversarial debate` — Standard draait nul debatcycli.
- Tester: na implementatie (één pass: zelftest + live).

## Run counter

15 (limiet 15 — verhoogd door gebruiker 2026-11-13, +1 voor minors): product-analyst brief, product-analyst patch U6–U8 + slice 2 MCP, doc-reviewer (2 blockers, 13 major, 16 minor), product-analyst rework 1/2, backend-dev slice 1, tester slice 1, backend-dev fixronde 1, code-reviewer slice 1, backend-dev slice 2, tester slice 2 (afgebroken), tester hervat (afgebroken), tester slice 2 poging 3 (opus), backend-dev fixronde slice 2, code-reviewer slice 2 + cross-cutting, backend-dev minors

## Infrastructure

`Local stack: N/A — WordPress-plugin zonder externe runtime-afhankelijkheden. Zelftests via php tests/run.php; live op dev.local (plugin als kopie in mu-plugins, Oxygen blijft actief).`

## Input inventory

- Bron: `../seo-health-check/` (36 bestanden, README.md, readme.txt, languages/seo-health-check-nl_NL.po/.mo).
- Precedent: `pdk-theme-options/modules/imgx/class-pdk-imgx.php`, `docs/modules/imgx.md`.

## User answers

| # | Vraag | Antwoord |
|---|---|---|
| U1 | Admin-plek | Alles in de PDK-tab (6 pagina's als sub-tabs), geen eigen top-level menu |
| U2 | Taal | Text domain `seo-health-check` + .po/.mo behouden, geladen vanuit de modulemap |
| U3 | Uninstall | PDK-uninstall wist ook SEOHC-tabellen, opties, transients, cron, rapportmap |
| U4 | Standaard | Module standaard uit |
| U6 | MCP | Abilities in agent-abilities: lezen (issues, scores, scanhistorie, launch checks), scan starten, bewerken (alt, SEO-titel, meta description) én pagina-aanpassingen zoals H1 |
| U7 | OQ-01 uninstall terwijl losse plugin actief | Data laten staan |
| U8 | Rechten | Alleen beheerders (`manage_options`); melden in CHANGELOG |
| U9 | OQ-02 slash-bug | Herstellen in de kopie, vermelden in CHANGELOG |
| U5 | Test | dev.local als testomgeving, met dummy data. Geen pdkmin-wachtwoord gegeven: admin via WP-CLI checken |

## Decisions log

- Tabel-, optie-, hook- en transientnamen (`seohc_*`) ongewijzigd: overstappende sites houden hun scanhistorie.
- Module doet niets als de losse plugin actief is (guard zoals IMGX).
- Eigen SEOHC-updater vervalt (PDK-updater dekt de plugin). Composer/phpcs/readme.txt niet mee.
- Tabellen aanmaken/upgraden via `seohc_db_version`-check bij laden (mu-plugin: geen activation hook).
- Versie 2.14.0 (minor: nieuwe module).

## Task table

| # | Taak | Agent | Status | Evidence |
|---|---|---|---|---|
| 1 | Delta-brief | product-analyst | DONE_WITH_CONCERNS | 19 AC, 15 issuetypes in dummy-data S00–S17; OQ-01 open; guard moet vóór define/require (D2) |
| 1b | Brief-patch U6–U8, slice 2 MCP (36 AC, 12 abilities) | product-analyst | DONE_WITH_CONCERNS | R-10 Oxygen-meta onbekend, R-09 geen SEO-plugin mogelijk |
| 2 | Review brief | doc-reviewer | DONE_WITH_CONCERNS | B1 slash-marker zonder backslash, B2 AC-017 hangt van slice 2 af; M1–M13 (revisie, kses, alt-sanitize, builder alt, scannable, REST-route, WP-CLI admin-bootstrap) |
| 2b | Brief rework 1/2 | product-analyst | DONE_WITH_CONCERNS | 38 AC (slice 1: 001–019+037, slice 2: 020–036+038), D1–D28; REST-route en Oxygen 6-meta live vast te stellen |
| 2c | Recheck brief | — | Skipped | coordinator grep: B1 (chr(92)-marker, wp_slash-seed :187-198), B2 (AC-017 zonder slice 2), OQ's gesloten; volledige recheck overgeslagen om runbudget te sparen, code-review vangt rest |
| 3 | Slice 1: import + wiring | backend-dev | DONE_WITH_CONCERNS | lint 0 fail; run.php 12/13 (out-of-scope: css-framework-slice2 hardcodet 2.13.0); dev.local: off=geen tabellen, on=3 tabellen db v4, 6 views renderen, 0 oude slugs, deactivate ruimt cron; Oxygen 6-meta = `_oxygen_data` |
| 4 | Slice 1: zelftest + live dev.local met dummy data | tester | DONE_WITH_CONCERNS | run.php 14/14 groen (seohc 72 checks); live 18/20 AC PASS; FAIL AC-014 (`uninstall.php:60-65` glob mist `.htaccess`), FAIL AC-010 (fputcsv deprecation :75/:92). Dummy data S00–S18b + A01 staat op dev.local, scan 28/28, 76 issues, alle 15 types; cleanup `seohc-seed.php cleanup` |
| 4b | Fixronde 1: uninstall-dotfiles + fputcsv | backend-dev | DONE | run.php 14/0; seohc-admin ALL PASS, mail ALL PASS, uninst-verify ALL PASS (module uit tijdens verify); reseed 21 items, check ALL PASS 28/27/76/70 |
| 5 | Review slice 1 | code-reviewer | DONE | geen bevindingen ≥75; onder drempel: multisite usermeta, netwerk-deactivatie alleen huidige site |
| 5b | Demo slice 1 | gebruiker | aangeboden | wp-admin → PDK Theme Options → SEO Health; feedback nog open |
| 6 | Slice 2: MCP-abilities | backend-dev | DONE_WITH_CONCERNS | run.php 14/0; 12 abilities live; REST `/wp-abilities/v1/abilities/<name>/run` (GET readonly, POST writes) 200/403/405; writes + weigeringen OK, revisies OK, 21710 ongewijzigd. Agent Connector vervangt permission_callback → edit_post-check ook in write-callbacks. Scan nu 29/79 (1 onverklaarde extra pagina) |
| 7 | Slice 2: tests live | tester | BLOCKED (poging 3) | run.php 14/0 (seohc 170 checks); live 205 PASS / 3 FAIL: AC-030 alt-write zonder post_id of op builderpagina maakt gepubliceerde kopie (save_alt(…,0) → get_post(0) valt terug op global $post van de scan). Rank Math: update-meta succes 18/18, AC-005 met rankmath ALL PASS, dummy-helper maskeerde Rank Math (gefixt). 29e pagina = kopie S09 door dit defect, opgeruimd. Minor: REST-domeinfouten geven 500 |
| 7b | Fixronde slice 2: alt-kopie + REST-status | backend-dev | DONE (coordinator-geverifieerd) | save_alt/update_alt_in_content: post_id<=0 = alleen media; resolver herstelt global $post; seo_error zet 4xx via SEO_STATUS (default 422). Coordinator: run.php 14/0; seed→check ALL PASS→seohc-abilities 208 PASS / 0 FAIL (exit 0), 'geen nieuwe posts'; eindstaat 28/27/76/70, Rank Math + Oxygen actief. NB: seohc-abilities.php is niet herhaalbaar zonder reseed (AC-024 verwacht eerste scan) |
| 8b | Minors + get_post(0)-helper | backend-dev | DONE (coordinator-geverifieerd) | wp_slash in save_alt/save_meta (inline-edit:215,:330); unsupported_block vóór mismatch; safe_get_post() :569-571. Coordinator: run.php 14/0; seed→check ALL PASS→abilities 208/0 exit 0; alt `C:\pad` via ability: meta en content gelijk; eindstaat 28/27/76/70; Oxygen + Rank Math actief; mu-plugins in sync |
| 8 | Review slice 2 + cross-cutting | code-reviewer | DONE | 0 critical/important; minor: (1) update_post_meta unslasht alt/meta (inline-edit:215,:330, ook in 0.10.1) → wp_slash; (2) set_heading_level checkt mismatch vóór unsupported_block (class-pdk-seo-health-check.php:250-255) |
| 5 | Review | code-reviewer | pending | |

## Fix queue (na tester slice 1)

- `modules/seo-health-check/includes/class-seohc-csv-exporter.php:75, :92` — `fputcsv()` zonder `$escape`-argument geeft E_DEPRECATED op PHP 8.4+ (dev.local draait 8.5). Gemeld door een andere sessie; bevestigd via grep. Mee in de backend-dev-fixronde. Juiste fix: alle vijf posities expliciet, `fputcsv( $out, $row, ',', '"', '\\' )` (huidig gedrag behouden) — NIET de voorgestelde `fputcsv( $out, $row, ',', '\\' )`: het 4e argument is `$enclosure`, dat zou de CSV met backslashes omsluiten.

- Bevestigd door tester (AC-014): uninstall verwijdert `uploads/seo-health-check/` niet (17/18 checks). Bevestigen met tester-evidence vóór de fixronde.

## Open questions

| ID | Vraag | Trigger | Status |
|---|---|---|---|
| OQ-01 | Uninstall terwijl losse plugin actief | — | answered (U7) |
| OQ-02 | Slash-bug in `update_alt_in_content()` herstellen in de kopie (D22)? | before Slice 2 | answered: ja, herstellen + CHANGELOG (U9) |

## Session state

- Phase 5 (rapport). Alle AC's groen, minors opgelost. Module + agent_abilities AAN op dev.local; Rank Math actief. Local stack N/A.
- Gecommit (c8065d2) en PR #11 naar main geopend. Geen tag/release.
- Next: gebruiker reviewt PR #11 en de tab in wp-admin; daarna merge + tag v2.14.0 + release (niet pre-release).
