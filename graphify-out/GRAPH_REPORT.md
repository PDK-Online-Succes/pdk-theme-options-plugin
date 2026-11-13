# Graph Report - .  (2026-11-13)

## Corpus Check
- 103 files · ~194,017 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1207 nodes · 2196 edges · 90 communities (33 shown, 57 thin omitted)
- Extraction: 69% EXTRACTED · 31% INFERRED · 0% AMBIGUOUS · INFERRED: 687 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Launch Checks
- Repository
- Admin
- Site Settings
- Picture Renderer
- Mailer
- Generator
- Mu Installer
- Helpers
- Schedule
- Agent Abilities
- Image Sizes
- Css Framework
- Seo Meta
- Scan Queue
- Issues List Table
- Settings
- Scanner
- Libraries
- Loader
- Seo Health Check
- Seo Health Check Brief
- Custom Fonts
- Admin 2
- Security
- Pages List Table
- Custom Css
- Files
- Admin 3
- Readme
- Image Sizes Batch
- Oxygen Elements
- Custom Functions
- Settings 2
- Delivery Time
- Inline Edit
- Variants
- Css Framework Brief
- Plugin
- Batch Processor
- Plugin 2
- Delivery Time 2
- Link Checker
- Afbeeldingsmaten Brief
- Custom Functions 2
- Capabilities
- Converter
- Vacation Mode
- Plugin 3
- Css Framework 2
- Cli
- Language Checker
- Imgx Lqip Brief
- Agent Abilities 2
- Installer
- Updater
- Logger
- Content Resolver
- Imgx
- Critical Error Status
- Image Sizes Admin
- Csv Exporter
- Sku Restriction
- Agent Abilities 3
- Imgx 2
- Oxygen
- Site Health
- Dashboard Widget
- Scans Page
- Image Sizes 2
- Logo Pdk Online Succes
- Imgx Admin
- Login Page
- Test Editor
- Css Framework Brief 2
- Editor
- Oxygen Elements 2
- Login Background
- Critical Error
- Language Checker 2
- Login Page 2
- Helpers 2

## God Nodes (most connected - your core abstractions)
1. `SEOHC_Repository` - 73 edges
2. `PDK_Admin` - 52 edges
3. `PDK_Settings` - 49 edges
4. `SEOHC_Launch_Checks` - 48 edges
5. `PDK_Agent_Abilities` - 45 edges
6. `PDK_SEO_Health_Check` - 42 edges
7. `SEOHC_Settings` - 40 edges
8. `Generator` - 38 edges
9. `SEOHC_Admin` - 35 edges
10. `SEOHC_Scan_Queue` - 34 edges

## Surprising Connections (you probably didn't know these)
- `Guard against standalone plugin (defined SEOHC_VERSION, D2)` --semantically_similar_to--> `v2.10.0 Afbeeldingsmaten + IMGX modules`  [INFERRED] [semantically similar]
  docs/seo-health-check-brief.md → CHANGELOG.md
- `Content writes only on raw post_content, builder pages refused (D20)` --semantically_similar_to--> `File integrity check (SHA-256 + .bak)`  [INFERRED] [semantically similar]
  docs/seo-health-check-brief.md → README.md
- `v2.14.0 SEO Health Check module release` --references--> `Mailer cron fatal fix (D25)`  [EXTRACTED]
  CHANGELOG.md → docs/seo-health-check-brief.md
- `v2.14.0 SEO Health Check module release` --references--> `fputcsv explicit $escape fix (PHP 8.4+)`  [EXTRACTED]
  CHANGELOG.md → docs/progress.md
- `v2.14.0 SEO Health Check module release` --references--> `Ctrl+S guard in admin-save.js for .shc-panel (D27)`  [EXTRACTED]
  CHANGELOG.md → docs/seo-health-check-brief.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Modules exposing MCP abilities via agent_abilities** — module_agent_abilities, docs_seo_health_check_brief_mcp_abilities, module_oxygen_element_studio, docs_css_framework_brief_fields_single_source [INFERRED 0.85]
- **IMGX LQIP three-layer placeholder stack** — docs_imgx_lqip_brief_lcp_lqip_technique, docs_imgx_lqip_brief_quality_loop, docs_imgx_lqip_brief_tiny_base64_layer, docs_imgx_lqip_brief_lqip_preload, readme_imgx_base_css [EXTRACTED 1.00]
- **MCP ability surface exposed to AI agents** — docs_modules_agent_abilities_read_custom_code, docs_modules_agent_abilities_write_custom_code, docs_modules_agent_abilities_get_site_info, docs_modules_css_framework_css_framework_abilities, docs_modules_agent_abilities_oxygen_element_abilities, docs_modules_agent_abilities_seo_health_check_abilities [EXTRACTED 1.00]
- **Customer files in storage dir guarded by integrity check** — docs_modules_custom_css_pdk_custom_css, docs_modules_custom_js_pdk_custom_js, docs_modules_custom_functions_pdk_custom_functions, docs_modules_libraries_pdk_libraries, docs_modules_custom_functions_integrity_check, docs_modules_custom_functions_storage_dir [INFERRED 0.85]
- **Shared periods list consumers** — docs_modules_site_settings_pdk_site_settings, docs_modules_delivery_time_pdk_delivery_time, docs_modules_vacation_mode_pdk_vacation_mode, docs_modules_agent_abilities_get_site_info [EXTRACTED 1.00]
- **Importing a standalone plugin as a PDK module (IMGX precedent, SEOHC)** — imgx_precedent_pattern, module_imgx, module_seo_health_check, docs_seo_health_check_brief_standalone_plugin_guard, docs_seo_health_check_brief_names_unchanged, seo_health_check_standalone_plugin [INFERRED 0.85]
- **PDK Online Succes logo lockup: mark, wordmark, tagline and shared palette** — pdk_theme_options_assets_img_logo_pdk_online_succes_wordmark_pdk, pdk_theme_options_assets_img_logo_pdk_online_succes_tagline_online_succes, pdk_theme_options_assets_img_logo_pdk_online_succes_rocket_mark, pdk_theme_options_assets_img_logo_pdk_online_succes_brand_palette [EXTRACTED 1.00]

## Communities (90 total, 57 thin omitted)

### Community 2 - "Admin"
Cohesion: 0.11
Nodes (3): PDK_Admin, pdk_config_code_editors(), pdk_map_code_cap()

### Community 3 - "Site Settings"
Cohesion: 0.07
Nodes (7): PDK_Settings, pdk_woocommerce_active(), pdk_client_logo_url(), pdk_company_address(), pdk_opening_hours_html(), pdk_site_setting(), PDK_Site_Settings

### Community 8 - "Helpers"
Cohesion: 0.17
Nodes (13): pdk_code_files(), pdk_current_user_can_edit_code(), pdk_ensure_storage_dir(), pdk_file_is_tampered(), pdk_is_library_file(), pdk_is_oxygen_element_file(), pdk_maybe_create_storage_file(), pdk_seed_file_hashes() (+5 more)

### Community 17 - "Scanner"
Cohesion: 0.13
Nodes (4): DOMDocument, WP_Post, WP_Post, SEOHC_Scanner

### Community 21 - "Seo Health Check Brief"
Cohesion: 0.18
Nodes (18): v2.14.0 SEO Health Check module release, dev.local test site (Local, Oxygen 6.2-rc.1), Progress ledger: SEO Health Check as module, fputcsv explicit $escape fix (PHP 8.4+), Thin Delta Brief: SEO Health Check module, Ctrl+S guard in admin-save.js for .shc-panel (D27), Do not delete standalone plugin via Plugins > Delete (D26), SEOHC dummy data S00-S18b on dev.local (+10 more)

### Community 26 - "Custom Css"
Cohesion: 0.12
Nodes (3): PDK_Loader, PDK_Custom_CSS, PDK_Custom_JS

### Community 29 - "Readme"
Cohesion: 0.16
Nodes (16): CHANGELOG, REST route prefix protection (denylist, not allowlist), v2.11.0 Oxygen Element Studio module, v2.8.0 Libraries module (JS/CSS files), v2.9.0 Security: XML-RPC off, /wp/v2/ behind login, Libraries (JS/CSS) module, Oxygen Element Studio module, Security module (always active) (+8 more)

### Community 37 - "Css Framework Brief"
Cohesion: 0.21
Nodes (14): v2.13.0 CSS Framework module release, Thin Delta Brief: CSS Framework, Line-height = ratio x font-size + leading (V13), Logical properties only, -start/-end for RTL (V11), Assumes Oxygen Modern Normalize + flow rule (V7), No cascade layers (D-L1), Oxygen variables sync via saveVariables() with guards (OQ-CSS-04), Regenerate on plugin version change (D-P2, pdk_css_framework_built) (+6 more)

### Community 41 - "Delivery Time 2"
Cohesion: 0.19
Nodes (12): Levertijden doc, PDK_Delivery_Time, Per-weekday shipping cutoff, PDK_Settings::WC_MODULES WooCommerce gate, Site Instellingen doc, PDK_Site_Settings, Shared periods list (afwijkende periodes), SKU Beperken doc (+4 more)

### Community 43 - "Afbeeldingsmaten Brief"
Cohesion: 0.21
Nodes (11): v2.10.0 Afbeeldingsmaten + IMGX modules, Thin Delta Brief: Afbeeldingsmaten, Resumable batch regeneration (pdk_image_sizes_batch), Blocklist on/off pattern (store what is OFF), Hand-built duallistbox (no JS library, B5), Custom sizes with pdk_ prefix, not editable (B4, B8), thumbnail size locked (B3), Deletion guarantee (only .webp/.avif/.lqip.jpg in uploads, NFR-102) (+3 more)

### Community 44 - "Custom Functions 2"
Cohesion: 0.20
Nodes (12): pdk_write_storage_file(), Custom CSS doc, PDK_Custom_CSS, Custom Fonts doc, Auto @font-face generation from filenames, PDK_Custom_Fonts, Custom PHP Functions doc, File integrity check (SHA-256 pdk_file_hashes) (+4 more)

### Community 50 - "Css Framework 2"
Cohesion: 0.29
Nodes (10): CSS Framework doc, get-css-framework / update-css-framework abilities, Flow rule (vertical rhythm), Line-height ratio (ink guard), Unlayered CSS (no @layer), Oxygen Modern Normalize, PDK_CSS_Framework, Section-as-grid layout (full/wide/popout/content lanes) (+2 more)

### Community 53 - "Imgx Lqip Brief"
Cohesion: 0.36
Nodes (9): v2.12.0-2.12.4 IMGX LQIP releases, Thin Delta Brief: IMGX LQIP, CSS Wizardry LCP-worthy LQIP technique (>=0.055 bpp), LQIP preload in whole_page mode (D5), PNG alpha detection, has-alpha skip (D4), LQIP quality loop q=10..60 in Converter (D2), Tiny base64/SVG blur layer under LQIP (T1-T6, S1-S5), wrap_img() picture_enabled check behavior change (D9) (+1 more)

### Community 54 - "Agent Abilities 2"
Cohesion: 0.50
Nodes (9): Agent Abilities (MCP) doc, get-site-info ability, PDK_Agent_Abilities, pdk_edit_custom_code capability, pdk_register_frontend_output(), read-custom-code ability, Agent trust boundary, WordPress Abilities API (+1 more)

### Community 59 - "Imgx"
Cohesion: 0.25
Nodes (8): SEO Health Check abilities (seo-*), IMGX doc, LQIP placeholder, PDK_ImgX, WebP/AVIF sidecar files + <picture> wrapping, SEO Health Check doc, PDK_SEO_Health_Check, SEO Health Check standalone plugin (SEOHC_*)

### Community 60 - "Critical Error Status"
Cohesion: 0.29
Nodes (8): Critical Error Status doc, Always-on (non-toggleable) module, handle_shutdown() fatal-error 500 handler, PDK_Critical_Error, Security doc, Header firewall, PDK_Security, REST route protection (/wp/v2/ prefix)

### Community 61 - "Image Sizes Admin"
Cohesion: 0.50
Nodes (5): render(), request(), setBusy(), start(), step()

### Community 70 - "Image Sizes 2"
Cohesion: 0.33
Nodes (6): Afbeeldingsmaten doc, Blocklist-not-allowlist pattern, PDK_Image_Sizes, PDK_Image_Sizes_Batch, Libraries (JS/CSS) doc, PDK_Libraries

### Community 71 - "Logo Pdk Online Succes"
Cohesion: 0.53
Nodes (6): PDK Brand Palette (indigo #2a2070, orange #d37500, amber-to-plum and green-to-yellow gradients), PDK Online Succes Logo (SVG), Rising Rocket/Arrow Mark with Shadow Ellipse, ONLINE SUCCES Tagline (orange lettering), Resolution-independent Vector Branding Asset, PDK Wordmark (dark blue letterforms)

### Community 72 - "Imgx Admin"
Cohesion: 0.80
Nodes (5): render(), request(), setBusy(), start(), step()

### Community 74 - "Test Editor"
Cohesion: 0.33
Nodes (4): bundle, dom, textarea, toggle

### Community 75 - "Css Framework Brief 2"
Cohesion: 0.40
Nodes (5): PDK_CSS_Framework::fields() single source for help text (V14), PDK_CSS_Framework validate() (shared admin + MCP), Mailer cron fatal fix (D25), PDK_SEO_Health_Check::tab_url() helper (D8), AI-agent toegang (MCP / Abilities API) module

### Community 77 - "Editor"
Cohesion: 0.50
Nodes (4): baseExtensions(), initEditor(), LANGUAGES, theme

### Community 78 - "Oxygen Elements 2"
Cohesion: 0.67
Nodes (4): Oxygen Element Studio abilities (list/read/write-oxygen-element-file), Oxygen Element Studio doc, PDK Elementen Element Studio save location, PDK_Oxygen_Elements

### Community 79 - "Login Background"
Cohesion: 0.67
Nodes (4): Login Background Image, Brand Gradient Swoosh Motif, White-Label Login Screen Branding, Negative Space Form Area

## Knowledge Gaps
- **35 isolated node(s):** `LANGUAGES`, `theme`, `bundle`, `dom`, `textarea` (+30 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **57 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SEOHC_Repository` connect `Repository` to `Inline Edit`, `Dashboard Widget`, `Scans Page`, `Mailer`, `Schedule`, `Agent Abilities`, `Link Checker`, `Seo Meta`, `Repository 2`, `Issues List Table`, `Scan Queue`, `Scanner`, `Seo Health Check`, `Installer`, `Pages List Table`, `Content Resolver`, `Admin 3`, `Csv Exporter`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **Why does `PDK_Loader` connect `Custom Css` to `Custom Functions`, `Imgx 2`, `Admin`, `Site Settings`, `Plugin`, `Login Page`, `Critical Error`, `Loader`, `Language Checker`, `Updater`, `Security`, `Image Sizes Batch`, `Sku Restriction`?**
  _High betweenness centrality (0.063) - this node is a cross-community bridge._
- **Why does `PDK_Agent_Abilities` connect `Agent Abilities` to `Agent Abilities 3`, `Repository`, `Inline Edit`, `Site Settings`, `Css Framework`, `Seo Meta`, `Scan Queue`, `Repository 2`, `Scanner`, `Seo Health Check`, `Helpers 2`, `Custom Css`, `Oxygen Elements`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Are the 266 inferred relationships involving `self` (e.g. with `.handle_install()` and `.handle_refresh()`) actually correct?**
  _`self` has 266 INFERRED edges - model-reasoned connections that need verification._
- **Are the 37 inferred relationships involving `SEOHC_Repository` (e.g. with `.register_seo_health_abilities()` and `.seo_get_scan_history()`) actually correct?**
  _`SEOHC_Repository` has 37 INFERRED edges - model-reasoned connections that need verification._
- **Are the 36 inferred relationships involving `PDK_Settings` (e.g. with `.get_tabs()` and `.handle_image_size_create()`) actually correct?**
  _`PDK_Settings` has 36 INFERRED edges - model-reasoned connections that need verification._
- **What connects `LANGUAGES`, `theme`, `bundle` to the rest of the system?**
  _35 weakly-connected nodes found - possible documentation gaps or missing edges._