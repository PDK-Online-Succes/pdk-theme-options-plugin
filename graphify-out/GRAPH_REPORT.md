# Graph Report - .  (2026-11-13)

## Corpus Check
- 129 files · ~266,178 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1725 nodes · 2787 edges · 131 communities (75 shown, 56 thin omitted)
- Extraction: 76% EXTRACTED · 24% INFERRED · 0% AMBIGUOUS · INFERRED: 679 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Test Image Sizes
- Css Framework Slice1
- Repository
- Capabilities
- Site Settings
- Picture Renderer
- Scan Queue
- Test Seo Health Check
- Admin
- Launch Checks
- Test Security
- Seo Meta
- Generator
- Css Framework Slice2
- Test Agent Abilities
- Settings
- Seo Health Check
- Plugin
- Agent Abilities
- Mu Installer
- Test Imgx
- Libraries
- Css Framework
- Admin 2
- Security
- Test File Integrity
- Schedule
- Admin 3
- Helpers
- Issues List Table
- Readme
- Oxygen Elements
- Csv Exporter
- Seo Health Check Brief
- Custom Fonts
- Loader
- Image Sizes
- Variants
- Pages List Table
- Scanner
- Test Delivery Time
- Css Framework Brief
- Updater
- Custom Functions
- Settings 2
- Plugin 2
- Files
- Plugin 3
- Forms
- Test Migrate Periods
- Delivery Time
- Cli
- Test Opening Hours
- Inline Edit
- Delivery Time 2
- Test Libraries
- Schedule Page
- Vacation Mode
- Custom Functions 2
- Css Framework 2
- Language Checker
- Link Checker
- Mailer
- Variants 2
- Test Oxygen Elements
- Imgx Lqip Brief
- Afbeeldingsmaten Brief
- Agent Abilities 2
- Admin 4
- Mailer 2
- Sku Restriction
- Imgx
- Critical Error Status
- Image Sizes Admin
- Imgx 2
- Test Css Framework 2
- Helpers 2
- Helpers 3
- Site Health
- Login Page
- Content Resolver
- Css Framework Brief 2
- Image Sizes 2
- Logo Pdk Online Succes
- Agent Abilities 3
- Imgx Admin
- Scan Queue 2
- Test Editor
- Critical Error
- Custom Css
- Custom Js
- Editor
- Test Css Framework Slice2 2
- Test Imgx 2
- Test Imgx 3
- Oxygen Elements 2
- Login Background
- Test Css Framework 3
- Test Imgx 4
- Test Sku Restriction
- Uninstall
- Test Css Framework 4
- Test Css Framework 5
- Test Css Framework Slice2 3
- Test Imgx 5
- Test Imgx 6
- Language Checker 2
- Login Page 2
- Test Css Framework 6
- Test Css Framework 7
- Test Css Framework Slice2 4
- Test Css Framework Slice2 5
- Test Css Framework Slice2 6
- Test Imgx 7

## God Nodes (most connected - your core abstractions)
1. `esc_html__()` - 78 edges
2. `SEOHC_Repository` - 73 edges
3. `PDK_Admin` - 52 edges
4. `PDK_Settings` - 49 edges
5. `SEOHC_Launch_Checks` - 48 edges
6. `PDK_Agent_Abilities` - 45 edges
7. `PDK_SEO_Health_Check` - 42 edges
8. `SEOHC_Settings` - 40 edges
9. `Generator` - 38 edges
10. `SEOHC_Admin` - 35 edges

## Surprising Connections (you probably didn't know these)
- `Guard against standalone plugin (defined SEOHC_VERSION, D2)` --semantically_similar_to--> `v2.10.0 Afbeeldingsmaten + IMGX modules`  [INFERRED] [semantically similar]
  docs/seo-health-check-brief.md → CHANGELOG.md
- `Content writes only on raw post_content, builder pages refused (D20)` --semantically_similar_to--> `File integrity check (SHA-256 + .bak)`  [INFERRED] [semantically similar]
  docs/seo-health-check-brief.md → README.md
- `pdk_config_code_editors()` --calls--> `get_user_by()`  [INFERRED]
  pdk-theme-options/includes/helpers.php → tests/test-file-integrity.php
- `pdk_config_code_editors()` --calls--> `is_email()`  [INFERRED]
  pdk-theme-options/includes/helpers.php → tests/test-file-integrity.php
- `seohc_uninstall_site()` --calls--> `wp_get_upload_dir()`  [INFERRED]
  pdk-theme-options/modules/seo-health-check/uninstall.php → tests/test-imgx.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Modules exposing MCP abilities via agent_abilities** — module_agent_abilities, docs_seo_health_check_brief_mcp_abilities, tests_live_css_framework_slice2_mcp_abilities, module_oxygen_element_studio, docs_css_framework_brief_fields_single_source [INFERRED 0.85]
- **IMGX LQIP three-layer placeholder stack** — docs_imgx_lqip_brief_lcp_lqip_technique, docs_imgx_lqip_brief_quality_loop, docs_imgx_lqip_brief_tiny_base64_layer, docs_imgx_lqip_brief_lqip_preload, readme_imgx_base_css [EXTRACTED 1.00]
- **MCP ability surface exposed to AI agents** — docs_modules_agent_abilities_read_custom_code, docs_modules_agent_abilities_write_custom_code, docs_modules_agent_abilities_get_site_info, docs_modules_css_framework_css_framework_abilities, docs_modules_agent_abilities_oxygen_element_abilities, docs_modules_agent_abilities_seo_health_check_abilities [EXTRACTED 1.00]
- **Customer files in storage dir guarded by integrity check** — docs_modules_custom_css_pdk_custom_css, docs_modules_custom_js_pdk_custom_js, docs_modules_custom_functions_pdk_custom_functions, docs_modules_libraries_pdk_libraries, docs_modules_custom_functions_integrity_check, docs_modules_custom_functions_storage_dir [INFERRED 0.85]
- **Shared periods list consumers** — docs_modules_site_settings_pdk_site_settings, docs_modules_delivery_time_pdk_delivery_time, docs_modules_vacation_mode_pdk_vacation_mode, docs_modules_agent_abilities_get_site_info [EXTRACTED 1.00]
- **Importing a standalone plugin as a PDK module (IMGX precedent, SEOHC)** — imgx_precedent_pattern, module_imgx, module_seo_health_check, docs_seo_health_check_brief_standalone_plugin_guard, docs_seo_health_check_brief_names_unchanged, seo_health_check_standalone_plugin [INFERRED 0.85]
- **PDK Online Succes logo lockup: mark, wordmark, tagline and shared palette** — pdk_theme_options_assets_img_logo_pdk_online_succes_wordmark_pdk, pdk_theme_options_assets_img_logo_pdk_online_succes_tagline_online_succes, pdk_theme_options_assets_img_logo_pdk_online_succes_rocket_mark, pdk_theme_options_assets_img_logo_pdk_online_succes_brand_palette [EXTRACTED 1.00]

## Communities (131 total, 56 thin omitted)

### Community 0 - "Test Image Sizes"
Cohesion: 0.06
Nodes (14): PDK_Image_Sizes_Batch, PDK_Loader, Batch_Processor, add_image_size(), check_ajax_referer(), PDK_Loader, PDK_Settings, wp_count_attachments() (+6 more)

### Community 3 - "Css Framework Slice1"
Cohesion: 0.12
Nodes (37): ac007(), ac009(), ac019(), ac020(), ac020Custom(), ac022(), ac024(), acEdge() (+29 more)

### Community 5 - "Capabilities"
Cohesion: 0.09
Nodes (7): Capabilities, Converter, Logger, remove_filter(), wp_get_image_editor(), wp_image_editor_supports(), wp_tempnam()

### Community 6 - "Site Settings"
Cohesion: 0.07
Nodes (7): PDK_Settings, pdk_day_labels(), pdk_client_logo_url(), pdk_company_address(), pdk_opening_hours_html(), pdk_site_setting(), PDK_Site_Settings

### Community 7 - "Picture Renderer"
Cohesion: 0.11
Nodes (5): Oxygen, Picture_Renderer, is_embed(), is_feed(), wp_doing_ajax()

### Community 8 - "Scan Queue"
Cohesion: 0.07
Nodes (4): SEOHC_Installer, SEOHC_Scan_Queue, is_admin(), as_unschedule_all_actions()

### Community 9 - "Test Seo Health Check"
Cohesion: 0.07
Nodes (7): has_blocks(), PDK_Admin, PDK_Loader, SEOHC_Installer, SEOHC_Scanner, SEOHC_Settings, WP_Error

### Community 12 - "Test Security"
Cohesion: 0.07
Nodes (5): firewall_op(), PDK_Loader, PDK_Security_Test, PDK_Settings, WP_Error

### Community 13 - "Seo Meta"
Cohesion: 0.09
Nodes (5): SEOHC_SEO_Meta, metadata_exists(), WP_Post, wp_revisions_enabled(), wp_strip_all_tags()

### Community 14 - "Generator"
Cohesion: 0.15
Nodes (4): Generator, do_action(), get_post_mime_type(), wp_get_original_image_path()

### Community 15 - "Css Framework Slice2"
Cohesion: 0.15
Nodes (25): css-framework-demo Oxygen page, abilities(), ac013(), ac016(), ac018(), begin, byName(), cfgDefaults() (+17 more)

### Community 16 - "Test Agent Abilities"
Cohesion: 0.08
Nodes (4): PDK_Loader, DateTimeZone, WP_Error, wp_timezone()

### Community 17 - "Settings"
Cohesion: 0.09
Nodes (3): SEOHC_Settings, get_post_type(), wp_parse_url()

### Community 18 - "Seo Health Check"
Cohesion: 0.09
Nodes (3): PDK_SEO_Health_Check, PDK_Loader, SEOHC_Dashboard_Widget

### Community 19 - "Plugin"
Cohesion: 0.12
Nodes (8): PDK_Plugin, PDK_Loader, self, pdk_ensure_storage_dir(), pdk_maybe_create_storage_file(), pdk_theme_options(), add_option(), flush_rewrite_rules()

### Community 20 - "Agent Abilities"
Cohesion: 0.10
Nodes (3): pdk_frontend_outputs(), PDK_Agent_Abilities, PDK_Loader

### Community 23 - "Libraries"
Cohesion: 0.10
Nodes (3): pdk_file_is_tampered(), PDK_Libraries, PDK_Loader

### Community 24 - "Css Framework"
Cohesion: 0.09
Nodes (3): PDK_CSS_Framework, PDK_Loader, wp_generate_uuid4()

### Community 26 - "Security"
Cohesion: 0.12
Nodes (4): PDK_Security, PDK_Loader, remove_action(), wp_mail()

### Community 27 - "Test File Integrity"
Cohesion: 0.09
Nodes (4): get_user_by(), is_email(), PDK_Oxygen_Elements, WP_Error

### Community 30 - "Helpers"
Cohesion: 0.19
Nodes (12): pdk_code_files(), pdk_config_code_editors(), pdk_current_user_can_edit_code(), pdk_is_library_file(), pdk_is_oxygen_element_file(), pdk_map_code_cap(), pdk_seed_file_hashes(), pdk_storage_rel_path() (+4 more)

### Community 32 - "Readme"
Cohesion: 0.15
Nodes (19): CHANGELOG, REST route prefix protection (denylist, not allowlist), v2.10.0 Afbeeldingsmaten + IMGX modules, v2.11.0 Oxygen Element Studio module, v2.8.0 Libraries module (JS/CSS files), v2.9.0 Security: XML-RPC off, /wp/v2/ behind login, Afbeeldingsmaten (image_sizes) module, IMGX module (WebP/AVIF + LQIP) (+11 more)

### Community 35 - "Seo Health Check Brief"
Cohesion: 0.18
Nodes (18): v2.14.0 SEO Health Check module release, dev.local test site (Local, Oxygen 6.2-rc.1), Progress ledger: SEO Health Check as module, fputcsv explicit $escape fix (PHP 8.4+), Thin Delta Brief: SEO Health Check module, Ctrl+S guard in admin-save.js for .shc-panel (D27), Do not delete standalone plugin via Plugins > Delete (D26), SEOHC dummy data S00-S18b on dev.local (+10 more)

### Community 36 - "Custom Fonts"
Cohesion: 0.11
Nodes (3): PDK_Custom_Fonts, PDK_Loader, WP_Theme_JSON_Data

### Community 37 - "Loader"
Cohesion: 0.19
Nodes (3): PDK_Loader, PDK_Loader, deactivate_plugins()

### Community 39 - "Variants"
Cohesion: 0.14
Nodes (3): Variants, delete_post_meta(), update_post_meta()

### Community 42 - "Test Delivery Time"
Cohesion: 0.13
Nodes (6): current_time(), PDK_Loader, PDK_Settings, PDK_Site_Settings, DateTimeZone, wp_timezone()

### Community 43 - "Css Framework Brief"
Cohesion: 0.22
Nodes (15): v2.13.0 CSS Framework module release, Thin Delta Brief: CSS Framework, Line-height = ratio x font-size + leading (V13), Logical properties only, -start/-end for RTL (V11), Assumes Oxygen Modern Normalize + flow rule (V7), No cascade layers (D-L1), Oxygen variables sync via saveVariables() with guards (OQ-CSS-04), Regenerate on plugin version change (D-P2, pdk_css_framework_built) (+7 more)

### Community 44 - "Updater"
Cohesion: 0.17
Nodes (3): PDK_Updater, PDK_Loader, get_bloginfo()

### Community 45 - "Custom Functions"
Cohesion: 0.13
Nodes (3): PDK_Custom_Functions, is_user_logged_in(), WP_Term

### Community 51 - "Test Migrate Periods"
Cohesion: 0.15
Nodes (5): current_time(), PDK_Loader, PDK_Settings, DateTimeZone, wp_timezone()

### Community 52 - "Delivery Time"
Cohesion: 0.19
Nodes (12): Levertijden doc, PDK_Delivery_Time, Per-weekday shipping cutoff, PDK_Settings::WC_MODULES WooCommerce gate, Site Instellingen doc, PDK_Site_Settings, Shared periods list (afwijkende periodes), SKU Beperken doc (+4 more)

### Community 54 - "Test Opening Hours"
Cohesion: 0.17
Nodes (5): current_time(), PDK_Loader, PDK_Settings, DateTimeZone, wp_timezone()

### Community 57 - "Test Libraries"
Cohesion: 0.17
Nodes (3): PDK_Loader, PDK_Settings, sanitize_title()

### Community 60 - "Custom Functions 2"
Cohesion: 0.22
Nodes (11): pdk_write_storage_file(), Custom CSS doc, Custom Fonts doc, Auto @font-face generation from filenames, PDK_Custom_Fonts, Custom PHP Functions doc, File integrity check (SHA-256 pdk_file_hashes), PDK_Custom_Functions (+3 more)

### Community 61 - "Css Framework 2"
Cohesion: 0.25
Nodes (11): CSS Framework doc, get-css-framework / update-css-framework abilities, Flow rule (vertical rhythm), Line-height ratio (ink guard), Unlayered CSS (no @layer), Oxygen Modern Normalize, PDK_CSS_Framework, Section-as-grid layout (full/wide/popout/content lanes) (+3 more)

### Community 66 - "Test Oxygen Elements"
Cohesion: 0.20
Nodes (3): ElementStudioController, PDK_Loader, self

### Community 67 - "Imgx Lqip Brief"
Cohesion: 0.36
Nodes (9): v2.12.0-2.12.4 IMGX LQIP releases, Thin Delta Brief: IMGX LQIP, CSS Wizardry LCP-worthy LQIP technique (>=0.055 bpp), LQIP preload in whole_page mode (D5), PNG alpha detection, has-alpha skip (D4), LQIP quality loop q=10..60 in Converter (D2), Tiny base64/SVG blur layer under LQIP (T1-T6, S1-S5), wrap_img() picture_enabled check behavior change (D9) (+1 more)

### Community 68 - "Afbeeldingsmaten Brief"
Cohesion: 0.25
Nodes (8): Thin Delta Brief: Afbeeldingsmaten, Resumable batch regeneration (pdk_image_sizes_batch), Blocklist on/off pattern (store what is OFF), Hand-built duallistbox (no JS library, B5), Custom sizes with pdk_ prefix, not editable (B4, B8), thumbnail size locked (B3), Deletion guarantee (only .webp/.avif/.lqip.jpg in uploads, NFR-102), IMGX\Batch_Processor (batch pattern precedent)

### Community 69 - "Agent Abilities 2"
Cohesion: 0.50
Nodes (9): Agent Abilities (MCP) doc, get-site-info ability, PDK_Agent_Abilities, pdk_edit_custom_code capability, pdk_register_frontend_output(), read-custom-code ability, Agent trust boundary, WordPress Abilities API (+1 more)

### Community 70 - "Admin 4"
Cohesion: 0.22
Nodes (5): Exception, SEOHC_Test_Redirect, PDK_Test_Redirect, PDK_Test_Redirect, PDK_Test_Json_Halt

### Community 74 - "Imgx"
Cohesion: 0.25
Nodes (8): SEO Health Check abilities (seo-*), IMGX doc, LQIP placeholder, PDK_ImgX, WebP/AVIF sidecar files + <picture> wrapping, SEO Health Check doc, PDK_SEO_Health_Check, SEO Health Check standalone plugin (SEOHC_*)

### Community 75 - "Critical Error Status"
Cohesion: 0.29
Nodes (8): Critical Error Status doc, Always-on (non-toggleable) module, handle_shutdown() fatal-error 500 handler, PDK_Critical_Error, Security doc, Header firewall, PDK_Security, REST route protection (/wp/v2/ prefix)

### Community 76 - "Image Sizes Admin"
Cohesion: 0.50
Nodes (5): render(), request(), setBusy(), start(), step()

### Community 78 - "Test Css Framework 2"
Cohesion: 0.25
Nodes (8): compact_css(), css_find(), css_has(), css_layer(), css_parse(), css_plain_find(), css_unlayered(), sel_plain()

### Community 79 - "Helpers 2"
Cohesion: 0.29
Nodes (3): pdk_oxygen6_active(), pdk_woocommerce_active(), wp_json_encode()

### Community 80 - "Helpers 3"
Cohesion: 0.29
Nodes (4): pdk_register_frontend_output(), PDK_Loader, PDK_Loader, PDK_Loader

### Community 84 - "Css Framework Brief 2"
Cohesion: 0.33
Nodes (6): PDK_CSS_Framework::fields() single source for help text (V14), PDK_CSS_Framework validate() (shared admin + MCP), Mailer cron fatal fix (D25), PDK_SEO_Health_Check::tab_url() helper (D8), AI-agent toegang (MCP / Abilities API) module, get-css-framework / update-css-framework abilities check

### Community 85 - "Image Sizes 2"
Cohesion: 0.33
Nodes (6): Afbeeldingsmaten doc, Blocklist-not-allowlist pattern, PDK_Image_Sizes, PDK_Image_Sizes_Batch, Libraries (JS/CSS) doc, PDK_Libraries

### Community 86 - "Logo Pdk Online Succes"
Cohesion: 0.53
Nodes (6): PDK Brand Palette (indigo #2a2070, orange #d37500, amber-to-plum and green-to-yellow gradients), PDK Online Succes Logo (SVG), Rising Rocket/Arrow Mark with Shadow Ellipse, ONLINE SUCCES Tagline (orange lettering), Resolution-independent Vector Branding Asset, PDK Wordmark (dark blue letterforms)

### Community 88 - "Imgx Admin"
Cohesion: 0.80
Nodes (5): render(), request(), setBusy(), start(), step()

### Community 90 - "Test Editor"
Cohesion: 0.33
Nodes (4): bundle, dom, textarea, toggle

### Community 95 - "Editor"
Cohesion: 0.50
Nodes (4): baseExtensions(), initEditor(), LANGUAGES, theme

### Community 97 - "Test Imgx 2"
Cohesion: 0.40
Nodes (5): lq_img_tag(), lq_style(), t_layers(), t_svg(), t_svg_ok()

### Community 99 - "Oxygen Elements 2"
Cohesion: 0.67
Nodes (4): Oxygen Element Studio abilities (list/read/write-oxygen-element-file), Oxygen Element Studio doc, PDK Elementen Element Studio save location, PDK_Oxygen_Elements

### Community 100 - "Login Background"
Cohesion: 0.67
Nodes (4): Login Background Image, Brand Gradient Swoosh Motif, White-Label Login Screen Branding, Negative Space Form Area

### Community 107 - "Test Css Framework 5"
Cohesion: 0.67
Nodes (3): ref_clamp(), ref_n(), ref_tokens()

### Community 109 - "Test Imgx 5"
Cohesion: 0.67
Nodes (3): check(), lq_call(), lq_check()

## Knowledge Gaps
- **53 isolated node(s):** `LANGUAGES`, `theme`, `bundle`, `dom`, `textarea` (+48 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **56 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `esc_html__()` connect `Admin 2` to `Site Settings`, `Test Seo Health Check`, `Admin`, `Settings`, `Seo Health Check`, `Mu Installer`, `Libraries`, `Css Framework`, `Security`, `Schedule`, `Admin 3`, `Helpers`, `Issues List Table`, `Csv Exporter`, `Custom Fonts`, `Loader`, `Pages List Table`, `Updater`, `Plugin 2`, `Cli`, `Delivery Time 2`, `Schedule Page`, `Vacation Mode`, `Admin 5`, `Mailer 2`, `Site Health`?**
  _High betweenness centrality (0.161) - this node is a cross-community bridge._
- **Why does `delete_transient()` connect `Cli` to `Test Image Sizes`, `Test Css Framework Slice2`, `Updater`, `Mu Installer`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._
- **Why does `PDK_Test_Redirect` connect `Admin 4` to `Test Css Framework`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **Are the 77 inferred relationships involving `esc_html__()` (e.g. with `.guard()` and `.maybe_show_notice()`) actually correct?**
  _`esc_html__()` has 77 INFERRED edges - model-reasoned connections that need verification._
- **Are the 37 inferred relationships involving `SEOHC_Repository` (e.g. with `.register_seo_health_abilities()` and `.seo_get_scan_history()`) actually correct?**
  _`SEOHC_Repository` has 37 INFERRED edges - model-reasoned connections that need verification._
- **Are the 36 inferred relationships involving `PDK_Settings` (e.g. with `.get_tabs()` and `.handle_image_size_create()`) actually correct?**
  _`PDK_Settings` has 36 INFERRED edges - model-reasoned connections that need verification._