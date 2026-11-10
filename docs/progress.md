# Progress — CSS Framework module

**Goal**: Build a modern CSS framework as a new plugin module: fluid type, line-height and spacing (Utopia-style, clamp + cqi), a layout with max-width and breakout, an auto-responsive grid, MCP abilities and a sync to Oxygen 6 variables.

Branch: `css-framework` · Brief: `docs/css-framework-brief.md` · Previous ledger (IMGX LQIP, released in 2.12.x): see the git history of this file.

## Profile

- **Standard**, triage score 5. Files >10: 1, novel pattern: 1, ambiguous: 2, irreversible: 0, parallel: 1.
- `Skipped: architect` — the existing module structure is enough; precedents are `custom-css` (generated CSS) and `agent-abilities` (MCP).
- `Skipped: ui-ux-designer` — one settings tab in the existing admin style.
- `Skipped: adversarial debate` — the Standard profile runs zero debate cycles.

## Run counter

36 (limit 36): product-analyst draft, product-analyst patch with the OQ answers, doc-reviewer (1 blocker, 4 majors, 13 minors; math correct), product-analyst rework 1/2 (all findings fixed, 20 AC), product-analyst patch V7 Modern Normalize + flow rule, tester slice 1 Mode A (red for the right reason, 11 existing files green, math 26/26 + 6 extra configs), backend-dev slice 1 (H green 12/12, live 66/71), product-analyst V8 patch, tester slice 1 rework (54 expected-red), product-analyst V10/V11 (24 AC), product-analyst V12, tester rework 2 (70 expected-red, 11 files green), backend-dev rework 2 (H 12/12, live 111/111), code-reviewer slice 1 (DONE_WITH_CONCERNS: 4 important, 3 minor), frontend-dev demo page dev.local (Oxygen html-to-page, 0 mismatches at 400/1024/1600), tester slice 2 Mode A + slice 1 test fixes (84 + 12 expected-red, 11 files green), backend-dev slice 2 + review fixes + release 2.13.0, tester fix test-bug slice2:302 (`?? 'x'` can never be satisfied), code-reviewer final cross-cutting, backend-dev final fixes, tester final fixes, product-analyst brief §1c/R-CSS-10 (=21), line-height fix: product-analyst + tester + backend-dev (=24), tester Oxygen-off opt-in + demo NaN (=25), V14 package: product-analyst + tester + backend-dev (=28), tester demo clamp px/rem bug + config-safe live scripts (=29), backend-dev admin unit+help inline + measure help single source (=30), backend-dev modifier-key steps (=31), PR #10 code-review fixes backend-dev + tester (=33), open items: product-analyst brief sync + tester demo track model/AC-009 footer selectors (=35), tester demo probes outside the section (=36)

## Infrastructure

`Local stack: N/A — WordPress plugin with no external runtime dependencies. Self-tests via php tests/run.php; live check on dev.local (Oxygen 6.2-rc.1, activate it temporarily for slice 2).`

## Input inventory

- None (no mockups or brief). Reference: utopia.fyi. Oxygen source: `~/Local Sites/dev/app/public/wp-content/plugins/oxygen/`.

## User answers

| # | Question | Answer |
|---|---|---|
| V1 | Form | Module + admin tab + MCP abilities |
| V2 | Fluid unit | cqi ("the fallback of cqi is already vi") |
| V3 | Baseline | Newly available |
| V4 | Oxygen | Sync to Oxygen 6 variables |
| V5 | Default viewports | Keep Oxygen's default viewports (breakpoints) |
| V6 | Site width | 1440px |
| V8 | Widths (2026-11-09) | site_width in rem (90rem), new `measure` 65ch as the content track of the breakout grid; tracks full/feature/popout/content following ryanmulligan.dev/blog/layout-breakouts; feature edge = site width. Josh Comeau's reason for avoiding ch (font-size of the element itself) does not apply to a grid track |
| V9 | D13 spacing (2026-11-09) | Confirmed: margins/paddings via `var(--pdk-space-*)` plus the flow rule, no utility classes |
| V10 | Section grid (2026-11-09) | The section itself is the grid (lines full / wide = site width / popout / content = measure); the `section > div` rule and `.pdk-breakout` are dropped; children default to `wide` |
| V11 | RTL/LTR (2026-11-09) | Breakout variants `-start`/`-end` instead of left/right; logical properties throughout |
| V12 | Fluid measure (2026-11-10) | The section gets `font-size: var(--pdk-step-0)` by default, so 65ch scales with it |
| V13 | Line-height (2026-11-10) | Measured with Playwright: h1 lh = 1.07 × fs, glyph descenders overlap the next text. New formula `calc(ratio·1em + leading)`: headings 1.1em + 0.25rem, body 1.3em + 0.25rem; new settings `lh_heading_ratio`/`lh_body_ratio` |
| V14 | User feedback after manual checks (2026-11-10) | Builder canvas OK; error messages clear; warning OK. Changes: grid_min in **ch** (default 30, range 10–80); admin fields grouped with a Dutch name + unit + explanation; the same text as a `description` per field in the MCP schema (single source); demo reads its expectations live from the CSS tokens (stale after a settings change), tidier padding, clearer grid explanation |
| V15 | Admin layout (2026-11-10) | Unit and explanation inline, right after the field; the extra measure sentence goes into fields() so admin = MCP |
| V16 | Step sizes (2026-11-10) | Float fields: plain 1, Ctrl 0.1, Alt 0.01; factor fields: plain 0.1, Ctrl/Alt 0.01; integer fields always 1 without a modifier |
| V7 | Modern Normalize | Framework assumes Oxygen's Modern Normalize option is on (no own normalize, plugin does not turn the option on); flow rule with space tokens + admin warning when the option is off |

## Tasks

| # | Subtask | Agent | Status | Evidence |
|---|---|---|---|---|
| 1 | Delta brief + Oxygen ground truth | product-analyst | done | 7 FR, 19 AC (slice 1: 001–010, 019; slice 2: 011–018), all OQs answered, vectors 400→1440 recomputed by hand |
| 2 | Brief review | doc-reviewer | done | B1, M1–M4, 13 minors; rework 1 processed; recheck by tester + code-review in slice 1 |
| 3 | Slice 1 — core (AC 001–010, 019–023) | tester A → backend-dev (incl. live) → code-reviewer; Mode B slice 1 merges with tester slice 2 | in progress | brief: 8 FR, 23 AC |
| 4 | Slice 2 — MCP + Oxygen sync + version 2.13.0 + slice 1 fixes | tester / backend-dev / code-reviewer | review DONE_WITH_CONCERNS | H 13/13 green; live slice 2 15/15; slice 1 123/124 (global-styles in the footer with Oxygen active); demo 0/94 mismatches |

## Decisions

- D-P1 (user, 2026-11-10): the slice 1 review fixes go along with the slice 2 dispatches (tester + backend-dev), with one combined code-review at the end. This is a deliberate deviation from the DoD 'slice 1 review DONE before slice 2'. Debt to close in slice 2: review findings 1–7 (space_pairs regex /D, snippet font-size: inherit + AC-019 font-size check, AC-009 inline global-styles check, regeneration on PDK_PLUGIN_VERSION change, validated config() fallback to defaults, measure hint, live-script restore in finally + missing checks).
- D-P2 (coordinator): regenerate the CSS file when the stored `pdk_css_framework_built` != PDK_PLUGIN_VERSION (reversible).
- Demo page on dev.local requested by the user (2026-11-10), re-creatable via tests/live/css-framework-demo.php.

- D-L1 (coordinator, after live finding Concern 3): no cascade layers at all. Layout rules unlayered in `:where()` (specificity 0), after normalize and the flow rules. Reason: an unlayered `*{margin:0;padding:0}` from Modern Normalize beats every layered rule, so `margin-inline:auto` and the gutter were lost.
- D-L2: with the default normalize (Modern Normalize off), the h1 margin stays .67em. Accepted, because V7 assumes Modern Normalize; AC-020 only checks font-size/line-height in that case.
- D-L3: `feature_space` is renamed to `popout_space` (not yet released, so not breaking).

- Version 2.13.0 (minor: new module).

## Open questions

| OQ | Trigger | Status | Answer |
|---|---|---|---|
| OQ-CSS-01 | before Slice 1 | answered | Max viewport **1440** (site width); min 400 (Oxygen phone portrait) |
| OQ-CSS-02 | before Slice 1 | answered | Proposed defaults approved |
| OQ-CSS-03 | before Slice 1 | answered | Automatic `section` container-type + `section > div` max-width, opt-out class |
| OQ-CSS-04 | before Slice 2 | answered | Sync via the internal `saveVariables()`, guarded |

## Session state

- Phase 5 (report). Slices 1+2 done, final review fixed, local proof green (see Local proof).
- Debt: AC-009 with Oxygen: the brief also requires 'no h1–h6/p selector in the footer global-styles'; the live script only checks the position (minor).
- Next: manual checks by the user (AC-017 builder, AC-010/023 admin UI), then commit + PR + release v2.13.0 on the user's go.

## Concerns

- With Oxygen active, WP prints `global-styles-inline-css` in the footer, after css-framework.css (brief §1c is wrong). theme.json element rules (h1) could win from the base rules. It has no effect on dev.local (AC-020 green). Final review: harmless (oxygen-zero has no theme.json; core theme.json only styles button). Document it, no code fix; adjust the live check.
- Final review (run 18): I1 Oxygen sync drops the user's collection list when the collections option is empty/null (seed from the variables, as Oxygen does); I2 uninstall leaves `pdk_css_framework_built` behind. Minor: M1 doc head order + R-CSS-10, M2 abilities notes text, M3 `is_string` on `$_GET['warning'|'error']`, M4 `??` fallback test 682 + MCP partial save with a non-default value, M5 slice2.mjs restores agent_abilities, M6 redundant check in config(). Circuit breaker hit at 18/18.

- R-CSS-05: **resolved** (2026-11-09). utopia-core 1.6.0 (npm, scratchpad, with user approval) `calculateTypeScale`/`calculateSpaceScale` with relativeTo `container`, 400→1440, 18→20, 1.2→1.25, custom `s-l`: all 26 clamp strings match the brief/test table character for character, including step--2 `0.7813rem`.
- Brief V12 patch (2026-11-10): AC-007 probes `#ch`/`#m` inside the section with `font-style:normal` + an `overflow:hidden` wrapper. The tester's live script uses its own 1ch probe inside the section; that is equivalent, but check `font-style:normal` if the ch measurement mismatches live.

## Local proof (coordinator, 2026-11-10)

- `php tests/run.php` → 13 files, 0 failed.
- Plugin copied to mu-plugins; dev.local: Oxygen active, Modern Normalize true, PDK_PLUGIN_VERSION 2.13.0, `pdk_css_framework_built` 2.13.0.
- `node tests/live/css-framework-slice1.mjs` → 124 checks, 0 failed (Oxygen restored to active).
- `node tests/live/css-framework-slice2.mjs` → 15 checks, 0 failed (Oxygen and agent_abilities restored).
- Demo page rebuilt (page 21682, `warnings: []`); mismatch bar 0/94 at 400, 1024 and 1600, no horizontal scroll.
- Still manual (user): AC-017 builder canvas, admin-UI steps for AC-010/AC-023.

## Local proof V13 (coordinator, 2026-11-10)

- `php tests/run.php` → 13/13 green. Live slice 2 → 16/16.
- Live slice 1 → 130/136: the 6 failures were p line-height 31.4/34, caused by the saved dev.local option `leading_body` 0.5 (old default, valid so kept). After `PDK_CSS_Framework::save(['leading_body'=>'0.25'])` (sync: synced) the demo measures: h1 53.27/71.14 (1.17–1.19), h6 23.8/26, p 27.4/30 (1.5), 1024 also correct; screenshot shows no ink overlap.
- Demo: 2 of 101 mismatches = a generator bug (lh expected NaN in the `.pdk-container` section, the ratio/leading args are missing); the measured value there is correct (h1 1.1×47.91+4 = 56.70).
- User: "En weer is oxygen uitgeschakeld" — slice1.mjs turns Oxygen off for the 'Oxygen inactief' cases during the run (restored afterwards: active). Proposal: make Oxygen-off cases opt-in.

## Local proof final (coordinator, 2026-11-10)

- `php tests/run.php` → 13/13 green.
- Live slice 1 (default, Oxygen stays active) → 120 checks, 0 failed; Oxygen-off cases skipped (opt-in `PDK_LIVE_OXYGEN_OFF=1`).
- Live slice 2 → 16 checks, 0 failed.
- Oxygen status polled every 5 s during both runs: 14× `active`, never off.
- Demo page 21690: 0/103 mismatches at 400/1024/1600, no horizontal scroll; h1 1.17–1.19, h6 1.30–1.32, p 1.50–1.52.
- Next: manual checks by the user (AC-017 builder, AC-010/023 admin UI), then commit/PR/release on the user's go.

## V14 local round (coordinator, 2026-11-10)

- `php tests/run.php` 13/13 green; Oxygen 16× active during the runs.
- dev.local has the user setting `font_min = 16` (keep it, user's answer) → the live scripts with fixed default expectations fail (slice1 14/122, slice2 2/16): an environment issue, not a code bug. Fix: the scripts temporarily put the defaults in place and restore the user config in finally.
- Demo bug: tests/live/css-framework-demo.php:179 multiplies the cqi term (already px) by 16 → wrong expectation at small widths. Being fixed by the tester (run 29).
- Debt: the admin tab appends an extra sentence to the measure help (for an old test) → admin and MCP descriptions differ slightly for `measure`.

## Final local round (coordinator, 2026-11-10, after run 30)

- `php tests/run.php` → 13/13 green.
- Live slice 1 → 122/122; live slice 2 → 16/16. Both scripts kept the user config (font_min 16), temporarily applied the defaults and restored the config afterwards (checked: font_min=16).
- Oxygen 16× active during the runs.
- Demo page 21698 (with the user config font_min 16): 0/109 at 400 and 1600; 3/109 at 1024 = lane start/width off by 0.3–0.75px. The demo's own JS model of the grid distribution at intermediate widths (already flagged as a limitation by the demo author); the real browser layout is the measured value. Not a framework bug; residual debt for the demo.
- Status: ready for the user's review of the admin tab (V15) and the go for commit/PR/release v2.13.0.

## V16 check (coordinator, 2026-11-10)

- `php tests/run.php` 13/13 green.
- Tab HTML rendered via `render_inline()` and tested with Playwright (keyboard ArrowUp with/without Ctrl/Alt): 11/11 cases correct (measure 65 → 66 / 65.1 / 65.01; lh_heading_ratio 1.1 → 1.2 / 1.11 / 1.11; site_width Ctrl 90.1; font_min Alt 16.01; min_viewport always 401). After keyup step = any; 65.25 typed in by hand is valid. Spinner clicks and the mouse wheel use the same pointerdown/wheel handler but were not tested automatically (admin needs a login).
- Debt: no H check for the data-pdk-step attributes (tests read-only that round).

## PR #10 (2026-11-10)

- Branch pushed, PR https://github.com/PDK-Online-Succes/pdk-theme-options-plugin/pull/10. Diff checked for passwords/secrets: clean.
- /code-review: 10 findings. Being fixed: sync on regeneration, merge instead of replace, missing id, trim space_pairs, write-failure transient + `pdk_file_write_failed`, CLASSES constant, dead code. Documented: the '{}' abort (protects against Oxygen's str_replace) and {}→[] for other variables.
- External review (user, 2026-11-10), 3× P2, reproduced in Chromium: (1) `<section hidden>` becomes visible through display:grid; (2) `.pdk-grid` on a section → children keep grid-column: wide → implicit columns; (3) site_width does not cap content + popout when site_width is small. Added to the running PR fix round (backend-dev + tester, runs 32/33). Brief AC-006 (exact rules) must follow afterwards.
- Local round after the review fixes (2026-11-10): `php tests/run.php` 13/13; live slice1 127/127 (including site_width 20 cap, hidden, section.pdk-grid; config and site_width 90 restored), slice2 16/16; Oxygen 17× active; demo 0/109 at 400 and 1600, 3/109 at 1024 (known demo model). User config font_min 16 kept.
- Open debt: brief AC-006 (exact section/lane rules + capped tracks) does not yet reflect the review fixes; run 34 (product-analyst) has not been approved.
- Open items processed (2026-11-10, runs 34–36): brief synced with the code after PR #10 (D30–D35, R-CSS-19/20, §14 review findings resolved; typo `PDK_PDK_` fixed by the coordinator). Demo probes for gutter/popout/site moved outside the section → demo 0/111 at 400/800/1024/1280/1600. AC-009 checks that the footer global-styles contain no h1–h6/p selector (pass). Live slice1 128/128.
- AC-007 #s2/#s3 (site cap) checked by hand by the coordinator via Playwright with an inline `--pdk-site-width` on an extra section (1600px, measure 700.8px): 40rem → wide/popout/content 640/640/640; 65ch+2rem → 732.8/732.8/700.8; 90rem → 1440/860.8/700.8. All within site/measure. The live script itself covers the site_width 20 case; #s2/#s3 are not scripted (small debt).
