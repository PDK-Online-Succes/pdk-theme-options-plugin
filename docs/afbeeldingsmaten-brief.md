# Thin Delta Brief — module Afbeeldingsmaten (`image_sizes`)

Status: concept, ter bevestiging. Herzien na review (herstelronde 1).
Scope: delta op de bestaande plugin PDK Theme Options (huidige versie 2.10.0 —
`pdk-theme-options/pdk-theme-options-plugin.php:6`, `:24`). Dit is geen volledige PRD; alleen wat
verandert staat hier.

"Afbeeldingsmaten" = WordPress image sizes (`thumbnail`, `medium`, `large`, ...). Nadrukkelijk geen
bestandsformaten — WebP/AVIF is en blijft IMGX.

---

## 1. Scope-delta

### Wat erbij komt

| Waar | Wijziging |
|------|-----------|
| `pdk-theme-options/modules/image-sizes/` | Nieuwe module `PDK_Image_Sizes` |
| `includes/class-pdk-plugin.php:17-30` (`$module_map`) | Regel `image_sizes` erbij |
| `includes/class-pdk-settings.php:77-212` (`get_defaults()`) | Blok `image_sizes` erbij, met `disabled` en `custom` (B10) |
| `includes/class-pdk-settings.php:218-233` / `:235-250` | Label "Afbeeldingsmaten" + omschrijving |
| `includes/class-pdk-admin.php:26-35` (`MODULE_TABS`) | `image_sizes` erbij — zonder die regel valt een deeplink naar de tab bij een uitgeschakelde module niet terug op Modules (`:635`) |
| `includes/class-pdk-admin.php:698-708` (`get_tabs()`) + `:733-778` (`render_tab_content()`) | Tab "Afbeeldingsmaten", alleen zichtbaar als de module aan staat |
| `includes/class-pdk-admin.php:640` (`$standalone_tabs`) | `image_sizes` erbij — de tab heeft eigen formulieren en AJAX, net als `imgx` |
| `pdk-theme-options/uninstall.php` | `delete_option( 'pdk_image_sizes_batch' )` erbij. De instellingen zelf staan in `pdk_theme_options`, dat op `:16` al verwijderd wordt (B10) |
| `CHANGELOG.md` + versieheaders | Minor bump (nieuwe module), conform `CLAUDE.md` |

### Wat expliciet NIET verandert

- **Geen wijziging aan IMGX.** IMGX blijft alleen `.webp`/`.avif`-sidecars maken naast wat WordPress
  al heeft gegenereerd; `build_targets()` leest `$metadata['sizes']`
  (`modules/imgx/includes/class-generator.php:603-646`). IMGX genereert geen WordPress-maten en gaat
  dat ook niet doen.
- **Geen aanpassing van WordPress-defaults in-place.** `thumbnail`, `medium`, `medium_large`, `large`
  en de -`1536x1536`/`2048x2048`-maten worden nooit van afmeting veranderd door deze module. Wie een
  andere afmeting wil: bestaande maat uitzetten, eigen maat aanmaken (B6, voorvoegsel volgens B4).
- **Geen bewerkfunctie voor eigen maten.** Een eigen maat wijzig je niet; je verwijdert hem en maakt
  een nieuwe aan (B8). Zelfde logica als B6.
- **Geen automatische bestandsverwijdering.** Een maat uitzetten verwijdert niets van schijf (B2).
- **Geen opruimfunctie in deze release.** Opruimen van bestanden van uitgezette maten valt buiten
  deze release (B7, Won't Have).
- **Geen nieuwe afmetingen als eis.** De getallen 1024x1024 / 600x600 / 300x300 uit het oorspronkelijke
  verzoek waren voorbeelden — letterlijk: *"wat aangegeven was waren voorbeelden de defaults hoeven
  niet aangepast te worden"*.
- **Geen JS-library.** De duallistbox wordt met de hand gebouwd (B5): twee `<select multiple>` plus
  verplaatsknoppen. Geen nieuwe afhankelijkheid, geen build-stap.
- **Geen WP-CLI-commando** (B12), geen frontend-uitvoer, geen srcset-beheer.
- **Geen gedragsverandering aan de module-toggle.** Staat de module uit, dan filtert hij niets en
  genereert WordPress weer alles — standaardgedrag van elke module in deze plugin.

---

## 2. Rolmodel

| ROLE-ID | Naam | Soort | Auth | Grens | Bron |
|---------|------|-------|------|-------|------|
| ROLE-001 | WordPress-beheerder | human | sessie + `manage_options` | site-breed | `includes/class-pdk-admin.php:151-160`, `:264-269`, `:627-629` |

Eén menselijke rol, dus de use cases staan hieronder in deze brief. Er komt geen
`docs/use-cases.md`. Er is geen tweede rol en dus geen permissiematrix.

---

## 3. Geverifieerde uitgangspositie

Geregistreerde maten op de testsite, gemeten door de coördinator op local-dev.local via
`wp_get_registered_image_subsizes()`:

| Sleutel | Breedte x hoogte | crop |
|---------|------------------|------|
| `thumbnail` | 150x150 | true |
| `medium` | 300x300 | false |
| `medium_large` | 768x0 | false |
| `large` | 1024x1024 | false |
| `1536x1536` | 1536x1536 | false |
| `2048x2048` | 2048x2048 | false |

Verder vastgesteld:

- Nieuwe WordPress-maten voor bestaande bijlagen aanmaken vereist `wp_generate_attachment_metadata()`
  opnieuw draaien. Dat bestaat nog niet in dit project — geverifieerd: geen enkele aanroep in
  `pdk-theme-options/`.
- `thumbnail` is niet veilig uit te zetten: wp-admin en vrijwel elk thema gebruiken die maat (B3).
- Precedent voor een aan/uit-lijst als blocklist: `PDK_Libraries::disabled()`
  (`modules/libraries/class-pdk-libraries.php:84-86`) — opgeslagen wordt wat UIT staat, zodat nieuw
  opgedoken items standaard AAN zijn.
- Precedent voor een hervatbare batch met voortgang en annuleren: `IMGX\Batch_Processor`
  (`modules/imgx/includes/class-batch-processor.php:18-195`) — state in één optie
  (`STATE_OPTION = 'imgx_batch_state'`, `:23`), AJAX-stappen, `DEFAULT_BATCH_SIZE = 3` (`:33`),
  `STEP_TIME_LIMIT` van 15 s (`:38`), modi `missing`/`all` (`:43`), `ajax_cancel()`. De hergeneratie
  hieronder volgt ditzelfde patroon, met een eigen optie `pdk_image_sizes_batch` (B9, B10).

---

## 4. Functionele eisen

Prioritering: MoSCoW. Elke eis heeft een herkomst (Source).

### FR-001 — Opsomming van geregistreerde maten
**Prioriteit:** Must Have
**Source:** verzoek, letterlijk *"Opsomming Afbeeldingsformaten (Large (1024×1024), Medium (600×600),
and Small (300×300))"*

- **AC-001**: Gegeven een beheerder op de tab Afbeeldingsmaten, wanneer de pagina laadt, dan toont de
  pagina elke maat uit `wp_get_registered_image_subsizes()` met sleutel, breedte, hoogte en of
  bijsnijden aan staat — inclusief `thumbnail`, `medium`, `medium_large`, `large`, `1536x1536` en
  `2048x2048` op een standaard-WordPress zonder extra thema-maten.
- **AC-002** *(Could Have)*: Gegeven een maat waarvan de sleutel met `pdk_` begint, wanneer de lijst
  rendert, dan staat achter de sleutel de badge-tekst "eigen" en een verwijderknop; bij elke maat
  zonder `pdk_`-voorvoegsel ontbreken die badge en die knop.

### FR-002 — Aan/uit zetten via duallistbox
**Prioriteit:** Must Have
**Source:** verduidelijking 1, letterlijk *"in de opties laten kiezen welke aanstaan en welke niet
doormiddel van een duallistbox"*; B5 (handgebouwd)

- **AC-003**: Gegeven de tab Afbeeldingsmaten, wanneer die rendert, dan staan er twee
  `<select multiple>`-lijsten ("Actief" / "Uitgeschakeld") met verplaatsknoppen ertussen, en bevat
  `wp_scripts()->queue` op deze tab geen andere niet-core-handle dan het modulescript zelf.
- **AC-004**: Gegeven een maat in "Actief", wanneer de beheerder hem naar "Uitgeschakeld" verplaatst
  en opslaat, dan staat die sleutel na herladen in de lijst "Uitgeschakeld" en is hij opgeslagen in
  de optie `pdk_theme_options` onder `image_sizes.disabled` (B10).
- **AC-005**: Gegeven een maat die op geen van beide lijsten voorkomt (bijvoorbeeld pas geregistreerd
  door een nieuw thema), wanneer de pagina laadt, dan verschijnt hij in "Actief" — de opgeslagen
  waarde is een blocklist, geen allowlist, net als `PDK_Libraries::disabled()`
  (`modules/libraries/class-pdk-libraries.php:84-86`).
- **AC-028**: Gegeven een maat die in `image_sizes.disabled` staat en die daarna door een thema of
  plugin opnieuw met `add_image_size()` wordt geregistreerd, wanneer de pagina laadt en wanneer een
  afbeelding wordt geüpload, dan staat die maat nog steeds in "Uitgeschakeld" en ontbreekt hij in de
  metadata van de nieuwe bijlage — de blocklist wint (B14).

### FR-003 — `thumbnail` vergrendeld
**Prioriteit:** Must Have
**Source:** B3

- **AC-006**: Gegeven de tab Afbeeldingsmaten, wanneer die rendert, dan staat `thumbnail` in de lijst
  "Actief", is de optie niet selecteerbaar of te verplaatsen, en staat er een zichtbare toelichting
  waarom.
- **AC-007**: Gegeven een POST waarin `thumbnail` handmatig in de uitgeschakelde lijst is gezet
  (bijvoorbeeld via een aangepaste request), wanneer die wordt opgeslagen, dan wordt `thumbnail`
  server-side uit de blocklist verwijderd en blijft de maat actief.

### FR-004 — Eigen maat aanmaken
**Prioriteit:** Must Have
**Source:** B6 + B4

- **AC-008**: Gegeven een formulier met naam, breedte, hoogte en bijsnijden, wanneer de beheerder een
  geldige maat opslaat, dan bestaat er een maat met sleutel `pdk_<gesaneerde-naam>` in
  `image_sizes.custom`, is die zichtbaar in FR-001's opsomming, en is hij geregistreerd via
  `add_image_size()` bij de volgende paginaload.
- **AC-009**: Gegeven een naam die na sanering botst met een bestaande sleutel (eigen maat of core-maat),
  wanneer de beheerder opslaat, dan wordt niets aangemaakt en toont de pagina een foutmelding die de
  botsende sleutel noemt.
- **AC-010**: Gegeven een breedte en hoogte die beide 0 of niet-numeriek zijn, wanneer de beheerder
  opslaat, dan wordt niets aangemaakt en toont de pagina een validatiefout.
- **AC-027**: Gegeven een bestaande eigen maat `pdk_x`, wanneer een redacteur een afbeelding in de
  blok-editor selecteert, dan staat "pdk_x" als keuze in de maatkiezer — de module voegt zijn eigen
  maten toe via het filter `image_size_names_choose` (B15).

### FR-005 — Eigen maat verwijderen, niet bewerken
**Prioriteit:** Must Have
**Source:** verzoek, letterlijk *"ontkoppelen/aanmaken"*; B2 (bestanden blijven staan); B8 (niet
bewerkbaar)

- **AC-011**: Gegeven een eigen maat `pdk_x`, wanneer de beheerder hem verwijdert en bevestigt, dan
  is de sleutel weg uit `image_sizes.custom`, wordt hij niet meer geregistreerd, en zijn er geen
  bestanden van schijf verwijderd.
- **AC-012**: Gegeven een maat die niet met `pdk_` begint, wanneer een verwijderverzoek voor die
  sleutel binnenkomt, dan weigert de server de actie en verandert er niets — core- en thema-maten
  zijn niet verwijderbaar via deze module.
- **AC-030**: Gegeven een bestaande eigen maat `pdk_x`, wanneer de tab rendert, dan is er geen
  bewerk- of wijzigbesturing voor breedte, hoogte of bijsnijden van die maat, en wanneer een POST
  toch een gewijzigde definitie voor een bestaande sleutel aanbiedt, dan blijft de opgeslagen
  definitie in `image_sizes.custom` ongewijzigd (B8).

### FR-006 — Hergeneratie in batches
**Prioriteit:** Must Have
**Source:** verzoek, letterlijk *"regenerate afbeeldingen in nieuwe formaten"*; patroon overgenomen
van `modules/imgx/includes/class-batch-processor.php:18-195`

- **AC-013**: Gegeven bijlagen in de mediabibliotheek, wanneer de beheerder hergeneratie start, dan
  verwerkt elke AJAX-stap ten hoogste 3 bijlagen (stapgrootte conform IMGX' `DEFAULT_BATCH_SIZE`,
  `class-batch-processor.php:33`) en keert binnen 15 s terug (`:38`), toont de pagina na elke stap
  verwerkt/totaal en een percentage zonder herladen, stopt Stoppen de verwerking na de lopende stap
  met behoud van de al gegenereerde bestanden, en toont de pagina na het sluiten en heropenen van de
  tab de opgeslagen voortgang uit `pdk_image_sizes_batch` met de mogelijkheid te hervatten vanaf de
  laatste offset.
- **AC-016**: Gegeven een testbijlage van 2000x1500 px en de actieve maten `thumbnail`, `medium`,
  `large` en `2048x2048`, wanneer hergeneratie die bijlage heeft verwerkt, dan bevat
  `wp_get_attachment_metadata()['sizes']` sleutels voor `thumbnail`, `medium` en `large`, geen sleutel
  `2048x2048` (WordPress slaat submaten groter dan het origineel over) en geen sleutel voor een maat
  die in `image_sizes.disabled` staat.
- **AC-026**: Gegeven de keuze tussen de modi `missing` en `all` bij het starten, wanneer de
  beheerder `missing` kiest, dan worden alleen bijlagen verwerkt waarvan minstens één actieve,
  toepasbare maat ontbreekt in de metadata, en wanneer hij `all` kiest, dan wordt elke bijlage
  verwerkt; in beide gevallen wordt per bijlage de volledige maatset opnieuw gegenereerd (B13).
- **AC-029**: Gegeven een bijlage waarvan het bronbestand ontbreekt of onleesbaar is, wanneer de run
  die bijlage bereikt, dan wordt de bijlage-ID met de fout in de voortgangsstatus vastgelegd en
  getoond, gaat de run door met de volgende bijlage, en eindigt de run met een samenvatting waarin
  het aantal fouten staat.
- **AC-031**: Gegeven een run die volgens `pdk_image_sizes_batch` bezig is, wanneer een tweede start
  wordt aangevraagd (andere tab of andere beheerder), dan wordt die geweigerd met een melding dat er
  al een run loopt en wordt de bestaande status niet overschreven (B9).

### FR-007 — Uitgezette maten worden niet meer gegenereerd
**Prioriteit:** Must Have
**Source:** B2

- **AC-017**: Gegeven dat `medium_large` uitgeschakeld is, wanneer een nieuwe JPEG wordt geüpload,
  dan bevat de metadata van die bijlage geen sleutel `medium_large`, en staat er geen bijbehorend
  bestand in de uploadmap van die bijlage. De module bereikt dit door het filter
  `intermediate_image_sizes_advanced`; IMGX hangt pas ná de generatie aan
  `wp_generate_attachment_metadata` op prioriteit 20
  (`modules/imgx/includes/class-generator.php:82`), dus de volgorde is onproblematisch.

### FR-008 — Bestaande bestanden blijven bij uitzetten
**Prioriteit:** Must Have
**Source:** B2

- **AC-019**: Gegeven een bijlage die al een `medium_large`-bestand heeft, wanneer `medium_large`
  wordt uitgeschakeld en de instellingen worden opgeslagen, dan bestaat dat bestand nog steeds op
  schijf en is de metadata van die bijlage ongewijzigd.

### FR-010 — Samenwerking met IMGX-sidecars
**Prioriteit:** Must Have
**Source:** `modules/imgx/includes/class-generator.php:82-86`, `:603-646`; B1

- **AC-023**: Gegeven dat IMGX actief is en hergeneratie een nieuwe maat toevoegt aan een bijlage,
  wanneer de metadata wordt opgeslagen, dan krijgt die nieuwe maat een `.webp`/`.avif`-sidecar zonder
  extra handeling — IMGX hangt aan `wp_update_attachment_metadata`
  (`modules/imgx/includes/class-generator.php:83`).

---

## 5. Niet-functionele eisen

- **NFR-001** — Stack ongewijzigd: PHP 8.0+, WordPress 6.0+, platte PHP-klassen, geen build-stap,
  geen composer- of npm-afhankelijkheid. De duallistbox draait op vanilla JS (B5).
- **NFR-002** — Elke schrijvende actie (opslaan, aanmaken, verwijderen, hergeneratie) eist
  `manage_options` plus een nonce, conform `includes/class-pdk-admin.php:264-269` en
  `modules/imgx/includes/class-batch-processor.php:100-106`.
- **NFR-003** — Een batchstap keert terug binnen een vast tijdvenster om `max_execution_time` niet te
  raken; IMGX hanteert 15 s (`class-batch-processor.php:38`). Deze module hanteert dezelfde grens.
- **NFR-004** — Alle teksten door `__()`/`esc_html__()` in textdomain `pdk-theme-options`, Nederlands,
  zoals de rest van de plugin.

---

## 6. Use cases (ROLE-001, enige menselijke rol)

**UC-001 | Actor: ROLE-001 | Maten bekijken en aan/uit zetten**
- Trigger: beheerder opent PDK Tools > Afbeeldingsmaten
- Preconditie: module `image_sizes` staat aan op de Modules-tab
- Hoofdstroom: (1) pagina toont de opsomming en de duallistbox (2) beheerder verplaatst maten
  (3) beheerder slaat op (4) pagina herlaadt met de nieuwe verdeling
- Alternatief: A1 (stap 2) `thumbnail` is niet te verplaatsen, met toelichting; A2 (stap 4) een thema
  registreert een uitgezette maat opnieuw -> die blijft uitgeschakeld
- Foutpad: E1 (stap 3) POST met `thumbnail` in de blocklist wordt server-side gecorrigeerd
- Postconditie: `image_sizes.disabled` weerspiegelt de keuze; er is niets van schijf verwijderd
- Covers: AC-001, AC-002, AC-003, AC-004, AC-005, AC-006, AC-007, AC-019, AC-028

**UC-002 | Actor: ROLE-001 | Eigen maat aanmaken en weer verwijderen**
- Trigger: beheerder wil een afmeting die WordPress niet levert
- Preconditie: als het een vervanging is voor een core-maat, is die core-maat eerst uitgezet via UC-001
- Hoofdstroom: (1) beheerder vult naam, breedte, hoogte en bijsnijden in (2) systeem maakt `pdk_<naam>`
  aan (3) de maat verschijnt in de opsomming en in de maatkiezer van de editor (4) later verwijdert de
  beheerder hem weer
- Alternatief: A1 (stap 2) naam botst -> foutmelding, niets aangemaakt; A2 (stap 3) beheerder wil de
  afmeting anders -> geen bewerkfunctie, hij verwijdert en maakt opnieuw aan
- Foutpad: E1 (stap 2) breedte en hoogte beide leeg/0 -> validatiefout; E2 (stap 4) verwijderverzoek
  voor een niet-`pdk_`-sleutel wordt geweigerd
- Postconditie: `image_sizes.custom` klopt; bestanden op schijf zijn niet aangeraakt
- Covers: AC-008, AC-009, AC-010, AC-011, AC-012, AC-027, AC-030

**UC-003 | Actor: ROLE-001 | Bibliotheek hergenereren naar de huidige maten**
- Trigger: beheerder heeft maten aan- of uitgezet of een eigen maat aangemaakt
- Preconditie: er zijn bijlagen in de mediabibliotheek
- Hoofdstroom: (1) beheerder kiest modus `missing` of `all` en start (2) systeem verwerkt in stappen
  met voortgang (3) run eindigt met een samenvatting
- Alternatief: A1 (stap 2) beheerder klikt Stoppen -> verwerking stopt, gegenereerde bestanden blijven;
  A2 (stap 2) tab gesloten en later heropend -> voortgang hervat; A3 (stap 1) er loopt al een run ->
  start geweigerd met melding
- Foutpad: E1 (stap 2) een bijlage faalt -> fout wordt vastgelegd en getoond, de run gaat door
- Postconditie: verwerkte bijlagen hebben metadata voor elke actieve, toepasbare maat; IMGX-sidecars
  volgen
- Covers: AC-013, AC-016, AC-023, AC-026, AC-029, AC-031

**UC-004 | Actor: ROLE-001 | Nieuwe upload volgt de maatinstellingen**
- Trigger: beheerder uploadt een afbeelding
- Preconditie: minstens één maat staat uit
- Hoofdstroom: (1) upload (2) WordPress genereert maten (3) uitgezette maten ontbreken in de metadata
- Postconditie: alleen actieve maten bestaan voor de nieuwe bijlage
- Covers: AC-017

---

## 7. Vastgelegde besluiten (gegeven, niet ter discussie)

Dit is de enige plek waar een besluit voluit staat; elders in dit document wordt er zonder
aanhalingstekens naar verwezen met zijn ID.

| ID | Besluit | Herkomst |
|----|---------|----------|
| B1 | Eigen module `image_sizes`, geen IMGX-tab — maatinstellingen blijven gelden als IMGX uit gaat | opdracht |
| B2 | Een maat uitzetten stopt generatie voor NIEUWE uploads en sluit hem uit bij hergeneratie. Bestaande bestanden worden NIET automatisch verwijderd. Opruimen is een aparte, expliciete actie | opdracht |
| B3 | `thumbnail` staat in de lijst maar is vergrendeld | opdracht |
| B4 | Eigen maten krijgen voorvoegsel `pdk_` | opdracht |
| B5 | Duallistbox met de hand gebouwd, geen JS-library | opdracht |
| B6 | WP-defaults nooit in-place wijzigen: uitzetten + nieuwe aanmaken | verduidelijking 2 |
| B7 | Opruimen van bestanden van uitgezette maten valt buiten deze release (Won't Have). B2 begrenst het uitzetten; het is geen opdracht om een verwijderfunctie te bouwen | coördinator, herstelronde 1 |
| B8 | Eigen maten zijn niet bewerkbaar: verwijderen en opnieuw aanmaken | coördinator, herstelronde 1 |
| B9 | Eén globale runstatus in de eigen optie `pdk_image_sizes_batch`; een tweede gelijktijdige start wordt geweigerd met een melding. Patroon: `imgx_batch_state` (`modules/imgx/includes/class-batch-processor.php:23`) | coördinator, herstelronde 1 |
| B10 | Instellingen staan in `pdk_theme_options` onder sleutel `image_sizes`, met `disabled` (maatsleutels) en `custom` (definities). Alleen de vluchtige batchstatus staat in `pdk_image_sizes_batch`; alleen die hoeft aan `uninstall.php` toegevoegd (`uninstall.php:16` verwijdert `pdk_theme_options` al) | coördinator, herstelronde 1 |
| B11 | `image_sizes` wordt opgenomen in `PDK_Admin::MODULE_TABS` (`includes/class-pdk-admin.php:26-35`, gebruikt op `:635`) | coördinator, herstelronde 1 |
| B12 | Geen WP-CLI-ontsluiting; hergeneratie loopt uitsluitend via de admin | coördinator, herstelronde 1 |
| B13 | Hergeneratie kent de modi `missing` en `all`, en verwerkt per bijlage altijd de volledige maatset | coördinator, herstelronde 1 (sluit OQ-001, OQ-002) |
| B14 | Een uitgezette maat die opnieuw wordt geregistreerd blijft uit — de blocklist wint | coördinator, herstelronde 1 (sluit OQ-003) |
| B15 | Eigen maten verschijnen in de maatkiezer van de editor via `image_size_names_choose` | coördinator, herstelronde 1 (sluit OQ-005) |

---

## 8. Open vragen

Geen. OQ-001 t/m OQ-006 zijn beslist en verwerkt in B7 en B12 t/m B15.

---

## 9. Risico's

| ID | Risico | Ernst | Mitigatie / verificatie |
|----|--------|-------|-------------------------|
| R-002 | **Hergeneratie op een grote bibliotheek loopt vast** (time-out, geheugen, of uren doorlooptijd). `wp_generate_attachment_metadata()` is per bijlage aanzienlijk zwaarder dan IMGX' sidecar-conversie. | hoog | Batch met opgeslagen state en tijdslimiet per stap (AC-013, NFR-003), overgenomen van `class-batch-processor.php:38,203-258`; stopknop en hervatten binnen AC-013; modus `missing` (AC-026) beperkt het werk tot wat ontbreekt. Verifieer op een bibliotheek van realistische omvang, niet op vijf testafbeeldingen. Resterende bovengrens: zonder CLI (B12) vergt een zeer grote bibliotheek meerdere admin-sessies, waarbij de beheerder de tab open moet houden per sessie. Geaccepteerd risico. |
| R-003 | **Gelijktijdige runs.** Hergeneratie tegelijk met een IMGX-batch, of twee beheerders tegelijk. | midden | Eén globale runstatus in `pdk_image_sizes_batch` (B9); een tweede start wordt geweigerd (AC-031). Verifieer door in twee browsertabs achtereenvolgens te starten: de tweede krijgt de melding en de status van de eerste run blijft intact. |
| R-006 | **Onverwacht `thumbnail`-achtig effect bij andere maten.** `medium` en `large` worden door de editor en door WooCommerce-thema's gebruikt; uitzetten kan zichtbare gaten geven zonder dat de module dat kan weten. | midden | Geen technische blokkade (alleen `thumbnail` is vergrendeld, B3). Wel een waarschuwing bij het uitzetten van core-maten. Geaccepteerd risico. |

---

## 10. Gereed wanneer

De module is af wanneer een beheerder met `manage_options` op een echte WordPress-installatie met een
gevulde mediabibliotheek:

1. de opsomming ziet en maten aan/uit kan zetten, ook als een thema een uitgezette maat opnieuw
   registreert (AC-001 t/m AC-007, AC-028),
2. een eigen `pdk_`-maat kan aanmaken, terugziet in de editor en weer kan verwijderen — en hem niet
   kan bewerken (AC-008 t/m AC-012, AC-027, AC-030),
3. de bibliotheek kan hergenereren in beide modi, met voortgang, stoppen, hervatten, doorlopen na een
   fout en weigering van een tweede run (AC-013, AC-016, AC-026, AC-029, AC-031),
4. ziet dat een nieuwe upload de uitgezette maten overslaat en dat bestaande bestanden blijven
   (AC-017, AC-019),
5. dit alles werkt zowel met IMGX aan als uit (AC-023).

Externe afhankelijkheden: geen. Alles draait tegen WordPress core en de bestaande uploadmap; lokaal
te verifiëren met een gewone WordPress-installatie. Geen enkel acceptatiecriterium vereist een
extern account of een live dienst.
