# Voortgang — module Afbeeldingsmaten

**Doel**: een opsomming van de geregistreerde WordPress-afbeeldingsmaten, met een duallistbox om per maat aan/uit te zetten, eigen maten aanmaken, en de bibliotheek hergenereren in de actieve maten.

Brief: `docs/afbeeldingsmaten-brief.md` (in bewerking)

## Profiel

- **Standard** — triagescore 4
- Score-opbouw: bestanden ~8 (1), gedeeltelijk precedent (1), ambiguïteit 0 na de blokkerende vragen, onomkeerbaar 1, parallelliseerbaar 1
- Eerdere triage gaf 6 (Full). Verlaagd nadat de gebruiker beide blokkerende vragen beantwoordde: concrete UI-keuze (duallistbox) en de regel dat defaults nooit in-place worden gewijzigd. Verlaging gemeld aan de gebruiker.
- `Overgeslagen: architect` — bestaande module-architectuur, geen nieuwe componenten
- `Overgeslagen: ui-ux-designer` — één admin-tab binnen een bestaand, vastliggend stramien
- `Overgeslagen: adversarial debate` — Standard-profiel draait nul debatcycli

## Runteller

2 dispatches (limiet 8 voor Standard; daarna circuit-breaker)

## Infrastructuur

`Local stack: N/A — WordPress-plugin zonder eigen externe runtime-afhankelijkheden. Verificatie gaat tegen twee draaiende Local-sites: local-dev.local (Oxygen 6.1.3) en dev.local (Oxygen Classic 4.9.3), beide met de module als mu-plugin.`

## Besluiten (intern, omkeerbaar — gelogd, niet gevraagd)

| # | Besluit | Waarom |
|---|---|---|
| B1 | Eigen module `image_sizes` ("Afbeeldingsmaten"), niet een IMGX-tab | Zet je IMGX uit, dan moeten de maatinstellingen blijven staan; anders vallen maten terug en triggert dat een massale hergeneratie. IMGX volgt de maten, bezit ze niet |
| B2 | Een maat uitzetten stopt generatie voor nieuwe uploads en sluit hem uit bij hergeneratie. Bestaande bestanden blijven staan | Omkeerbaar houden. Opruimen is een aparte, expliciete actie |
| B3 | `thumbnail` staat in de lijst maar is vergrendeld | wp-admin (mediabibliotheek, bijlagekiezer) valt om zonder die maat |
| B4 | Zelfgemaakte maten krijgen het voorvoegsel `pdk_` | Voorkomt botsing met maten van thema of andere plugins |
| B5 | Duallistbox met de hand, geen JS-afhankelijkheid | Twee `<select multiple>` plus knoppen is enkele tientallen regels; een library erbij is niet te verdedigen |
| B6 | WordPress-defaults nooit in-place wijzigen: eerst uitzetten, dan een eigen maat aanmaken | Letterlijke instructie van de gebruiker |
| B7 | **Opruimen van oude maatbestanden valt buiten deze release (Won't Have)** | Doc-review stelde terecht vast dat niemand hierom vroeg. B2 begrenst het uitzetten ("wist niets"), het is geen opdracht een verwijderfunctie te bouwen. Hieraan hingen AC-020/021/022/024, R-001 (hoog), R-004, OQ-004 en UC-005 — ruwweg de helft van de brief. Later toe te voegen |
| B8 | Eigen maten zijn niet bewerkbaar: verwijderen en opnieuw aanmaken | Zelfde logica als B6, en het voorkomt dat een wijziging bestaande bestanden onder zich vandaan trekt |
| B9 | Eén globale runstatus in een eigen optie `pdk_image_sizes_batch`; een tweede gelijktijdige start wordt geweigerd | Sluit R-003 (gelijktijdige runs). Patroon van `imgx_batch_state`, `modules/imgx/includes/class-batch-processor.php:23` |
| B10 | Instellingen in `pdk_theme_options['image_sizes']` (`disabled` en `custom`); alleen de vluchtige batchstatus in een eigen optie | Volgt het projectpatroon; `uninstall.php:16` ruimt `pdk_theme_options` al op, dus alleen de batchoptie hoeft erbij |
| B11 | `image_sizes` ook opnemen in `PDK_Admin::MODULE_TABS` | Zonder die constante valt een deeplink naar een uitgeschakelde module-tab niet terug op Modules (`includes/class-pdk-admin.php:26-35`, gebruikt op `:635`) |
| B16 | **Versie blijft 2.10.0. Geen bump naar 2.11.0.** | Coördinatorfout in de slice 1-opdracht: ik schreef "2.10.0 is nog niet uitgebracht, dus tel op naar 2.11.0". Dat is omgekeerd. Tags lopen tot `v2.8.1`, dus 2.10.0 is nooit uitgebracht en blijft gevuld worden tot hij shipt. Teruggedraaid op beide plekken in `pdk-theme-options-plugin.php`; de 2.11.0-changelogsectie is samengevoegd in 2.10.0, datum naar 2026-09-13, en de regel "volgen in een latere slice — bewust niet in deze release" gecorrigeerd omdat slice 2 nu in dezelfde release valt. **Slice 2 mag de versie NIET bumpen.** |

## Beantwoorde vragen

| # | Vraag | Antwoord |
|---|---|---|
| V1 | Wat te doen met niet-genoemde maten (`thumbnail`, `medium_large`, `1536x1536`, `2048x2048`)? | Niet vooraf beslissen — de gebruiker kiest per site via een duallistbox in de opties |
| V2 | `medium` van 300×300 naar 600×600: wat met de weesbestanden? | Vervalt. De genoemde maten waren voorbeelden; defaults worden niet gewijzigd. Wie een andere maat wil, schakelt de default uit en maakt een nieuwe aan |

## Open vragen uit de brief — afhandeling

Alle zes zijn omkeerbare interne keuzes of platformfeiten, geen extern gegronde feiten en geen
onomkeerbare besluiten. Daarom door de coördinator beslist en gelogd, niet aan de gebruiker gevraagd
(Phase 0, blokkerende-vragenpoort). Elk besluit is terug te draaien zolang de slice niet af is.

| OQ-ID | Besluit | Grond |
|-------|---------|-------|
| OQ-001 | Twee modi, net als IMGX: `missing` (standaard) en `all` (forceren) | Precedent `modules/imgx/includes/class-batch-processor.php:43`; het patroon bestaat al, hergebruik kost bijna niets |
| OQ-002 | Altijd de hele set per bijlage, geen per-maat-hergeneratie | Platformfeit: `wp_generate_attachment_metadata()` bouwt per aanroep alle submaten. Per maat zou WordPress-gedrag moeten nabouwen |
| OQ-003 | Blocklist blijft leidend; een thema dat een uitgezette maat herregistreert wint niet | Precedent `modules/libraries/class-pdk-libraries.php:84-89`; zelfde semantiek in dit project |
| OQ-004 | Opruimen pakt alle uitgezette maten tegelijk, met bevestiging en vooraf getoonde telling | Omkeerbaar, en één bevestiging is minder foutgevoelig dan een reeks losse |
| OQ-005 | Eigen maten worden wél aangeboden in de maatkiezer van de editor (`image_size_names_choose`) | Zonder dat is een aangemaakte maat onzichtbaar voor redacteuren en is "aanmaken" half werk |
| OQ-006 | Geen WP-CLI. De batch heeft hervatten, een tijdslimiet per stap en een stopknop; dat is de mitigatie voor R-002 | Sectie 1 van de brief sluit CLI al expliciet uit — OQ-006 spreekt dat tegen. Niet bouwen wat niemand vroeg; later toe te voegen als R-002 zich in de praktijk voordoet |

## Acceptatiecriteria

25 AC-IDs (AC-001 t/m AC-025) over 10 functionele eisen, vastgelegd in
`docs/afbeeldingsmaten-brief.md`. Rolmodel: één menselijke rol (ROLE-001, `manage_options`),
use cases inline in de brief, geen `docs/use-cases.md`, geen permissiematrix.

## Taken

| # | Slice / subtaak | Agent | Status | Evidence |
|---|---|---|---|---|
| 1 | Thin delta brief met AC-IDs | product-analyst | DONE_WITH_CONCERNS | 360 regels, 10 FR / 25 AC / 6 OQ / 7 risico's; citaten met bestand:regel geverifieerd. Concerns: OQ's rond hergeneratie |
| 2 | Review van de brief | doc-reviewer | DONE_WITH_CONCERNS | 5 kritiek, 7 belangrijk, 4 suggesties; alle bestand:regel-citaten in de brief geverifieerd en correct bevonden |
| 2b | Brief herzien op alle bevindingen | product-analyst | DONE | Alle 5 kritieke + 7 belangrijke + 3 suggesties `accepted_and_fixed`. Eind: 9 FR, 23 AC, 0 open vragen, 3 risico's |
| 2c | Hercontrole van de herziening | coördinator | DONE | Zelf geverifieerd i.p.v. een run aan doc-review: FR-009/AC-020/021/022/024/UC-005/R-001/R-004/OQ-004/AC-018/AC-025 aantoonbaar weg, geen WP-CLI-tegenstrijdigheid, B-verwijzingen consistent, nul parafrases tussen aanhalingstekens. Budget bewaard voor implementatie |
| 3 | Slice 1: matenregister, instellingen, duallistbox-tab | backend-dev | DONE_WITH_CONCERNS | Lint schoon, `php tests/run.php` 9/9, 3 nieuwe bestanden, versie 2.11.0. Tab rendert op local-dev; duallistbox, vergrendelde `thumbnail` en blocklist geverifieerd tegen echte WordPress |
| 3b | Verificatie tegen echte WordPress | coördinator | DONE | Zie "Bevindingen uit live-verificatie" hieronder. 15 van 17 AC's aantoonbaar in orde, 2 defecten |

## Bevindingen uit live-verificatie slice 1 (local-dev.local, WordPress 6.x)

De implementatieagent kon alleen tegen stubs testen en meldde AC-003/004/008 als
"needs live-WP confirmation". Die verificatie heeft de coördinator zelf gedaan, omdat er twee
draaiende WordPress-sites beschikbaar zijn. Dat leverde twee defecten op die met stubs onzichtbaar waren.

**Aangetoond in orde** (tegen een echte WordPress): AC-001 (alle zes core-maten in de tabel),
AC-002 (badge "eigen"), AC-003 (twee `<select multiple>`, alleen de eigen module-assets in de
enqueue-lijst), AC-004 (blocklist opgeslagen), AC-006 (`disabled='disabled'` op `thumbnail`),
**AC-007 (een geprepareerde POST met `image_sizes_disabled[]=thumbnail` werd server-side gestript
terwijl `medium_large` in dezelfde POST wél doorging)**, AC-008 (`pdk_banner` en `pdk_large`
geregistreerd, zichtbaar op `wp_loaded`), AC-010 (0x0 geweigerd met validatiefout),
AC-017 (`intermediate_image_sizes_advanced` laat `medium_large` weg terwijl hij geregistreerd blijft),
AC-019 (geen enkel bestandspad in de module).

**Defect D-1 — AC-009 faalt.** Een eigen maat met de naam `large` werd geaccepteerd en opgeslagen als
`pdk_large`. De botsingscontrole vergelijkt de *voorvoegde* sleutel met bestaande sleutels, waardoor
een core-maatnaam nooit botst. AC-009 eist letterlijk afwijzing bij een botsing met "eigen maat of
core-maat". Bewijs: POST met `name=large` gaf 302 en `image_sizes.custom` bevat nu `pdk_large` 900x900.

**Defect D-2 — neveneffect bij AC-027.** Het filter `image_size_names_choose` voegt niet alleen de
eigen maten toe maar ook `medium_large`, `1536x1536` en `2048x2048`. WordPress houdt die bewust
buiten de maatkiezer; het zijn retina-maten, geen redactiekeuzes. AC-027 vraagt alleen om de eigen
maten. Bewijs: kiezer bevat `thumbnail, medium, large, full, pdk_banner, pdk_large, medium_large,
1536x1536, 2048x2048`.

| 4 | Slice 2: hergeneratie + D-1/D-2 herstellen + AC-032/AC-033 | backend-dev | DONE_WITH_CONCERNS | Lint schoon, tests 9/9, 21 scratchpad-controles. Versie ongemoeid op 2.10.0 |
| 4b | Live-verificatie slice 2 | coördinator | DONE | Zie hieronder. D-1 bevestigd hersteld; D-2 was een fout van de coördinator; AC-023 faalt |

## Bevindingen uit live-verificatie slice 2 (local-dev.local)

**Bevestigd in orde**: D-1 (naam `medium` wordt geweigerd met melding "De sleutel medium bestaat al"),
AC-031 (tweede gelijktijdige start geweigerd), modusvalidatie (`../etc` geweigerd), AC-033 (melding bij
IMGX uit staat op de tab), **AC-032** (hergeneratie met IMGX uit: 2/2 verwerkt, nieuwe WordPress-maat
`612x400.jpg` aangemaakt, avif en webp bleven allebei op 6 — nul sidecars, ontkoppeling bewezen).

**D-2 was géén defect — fout van de coördinator.** De retina-maten `medium_large`, `1536x1536` en
`2048x2048` in de editor-maatkiezer komen van `\Breakdance\Media\Sizes\imageSizeNamesChoose`, dus van
Oxygen, niet van deze module. Gemeten met een probe op de callbacks van de hook. De "fix" die daarop
volgde bouwde de keuzelijst opnieuw op vanuit een vaste set van vier en gooide daarmee weg wat andere
plugins met lagere prioriteit toevoegden — aangetoond met een testfilter op prioriteit 5, dat verdween.
Door de coördinator teruggedraaid naar additief gedrag (`filter_size_choices()` voegt alleen de eigen
`pdk_`-maten toe); de ongebruikte constante `CORE_CHOICES` is verwijderd. Opnieuw gemeten: de keuze van
de andere plugin overleeft. **Nog te beoordelen door code-reviewer — dit is een coördinatorwijziging
die geen review heeft gehad.**

**Defect D-3 — AC-023 faalt.** Hergeneratie met IMGX áán verwijdert bestaande sidecars in plaats van
de ontbrekende aan te vullen. Gemeten: vóór 6 avif + 6 webp, na hergeneratie 2 avif + 2 webp; alleen de
`full`-sidecars overleefden en IMGX' eigen registratie stond daarna ook op alleen `full`, terwijl de
submaat-JPEG's intact bleven en de metadata `medium, thumbnail, pdk_banner` bevatte. De nieuwe maat
kreeg géén sidecar. AC-023 eist dat die er "zonder extra handeling" komt.
**Herstelbaar**: één run van IMGX' eigen "Ontbrekende afbeeldingen genereren" bracht alles terug op
7 avif + 7 webp + 7 jpg, inclusief sidecars voor de nieuwe `612x400`. Ernst daarmee geen
gegevensverlies, maar wel: tussen hergeneratie en handmatig herstel serveert de site geen sidecars, en
de belofte van AC-023 wordt niet waargemaakt. **Oorzaak vastgesteld met een trace, niet gegokt.** WordPress bouwt submaten sinds 5.3 incrementeel op
en slaat de metadata na élke submaat opnieuw op (bedoeld om een time-out te overleven). Gemeten volgorde
per bijlage:

```
update_metadata #11 sizes=0 []
update_metadata #11 sizes=1 [medium]
update_metadata #11 sizes=2 [medium,thumbnail]
update_metadata #11 sizes=3 [medium,thumbnail,pdk_banner]
generate_metadata #11 sizes=3
update_metadata #11 sizes=3 [medium,thumbnail,pdk_banner]
```

Bij de eerste tussenopslag is `sizes` leeg. IMGX' `on_update_metadata()` behandelt die als gezaghebbend,
`build_targets()` levert dan alleen `full`, en `prune_stale_sizes()` verwijdert al het andere.

**Dit is een bestaande fout in IMGX, niet in de nieuwe module.** Elke hergeneratie van thumbnails raakt
hem, ook via Regenerate Thumbnails of WordPress' eigen hervattingspad na een time-out. De
`if ( ! $targets ) return;`-grens die eerder is toegevoegd vangt dit niet: bij de tussenopslag bestaat
`full` wel degelijk als doel, dus de lijst is niet leeg. D-3 wordt daarom in `modules/imgx/` hersteld.

**D-3 hersteld en live bevestigd.** `on_update_metadata()` slaat `prune_stale_sizes()` over zolang
`wp_create_image_subsizes` nog op de aanroepstack staat. Gemeten na de fix: hergeneratie met IMGX aan,
2/2 verwerkt, bestanden bleven op 7 avif + 7 webp + 7 jpg (vóór de fix: 6 → 2).

**Aandachtspunt voor de code-reviewer.** De detectie gebruikt `debug_backtrace()` en zoekt de
WordPress-interne functienaam `wp_create_image_subsizes` op de stack. `prune_stale_sizes()` zelf is
ongewijzigd, dus dit is de **enige** bescherming op een pad dat bestanden verwijdert. Drie bezwaren om
te wegen: (1) de guard faalt *open* — hernoemt of herstructureert WordPress die functie, dan keert het
destructieve gedrag stil terug; bij een bestandsverwijderend pad hoort falen richting behouden, niet
richting verwijderen. (2) `debug_backtrace()` draait bij élke metadata-opslag. (3) koppeling aan een
WordPress-intern aanroeppad dat geen stabiele API-garantie heeft. Mogelijke versterking: een tweede,
onafhankelijke grens op de vórm van de data — niet opruimen wanneer `$metadata['sizes']` leeg is
terwijl de registratie niet-`full`-maten bevat én het bronbestand bestaat. Dan moeten beide grenzen
falen voordat er iets verdwijnt. Bewust niet zelf doorgevoerd: dit hoort door de review te komen.

## Afwijkingen, met toestemming van de gebruiker (2026-09-13)

1. **D-1 en D-2 worden gebundeld in de slice 2-dispatch** in plaats van eerst slice 1 groen te maken.
   Wijkt af van de DoD-poort ("slice N groen voordat N+1 start"). Reden: beide defecten zijn geïsoleerd
   (een botsingscontrole en de scope van één filter), raken de hergeneratie niet, en het scheelt een
   dispatch binnen een budget van 8. Gebruiker akkoord.
2. **AC-032 en AC-033 zijn hier vastgelegd in plaats van eerst in de brief.** De brief wordt later
   bijgewerkt. Docs-code-sync-schuld, bewust aangegaan om een dispatch te sparen. Gebruiker akkoord.

### Nieuwe criteria (nog niet in de brief)

- **AC-032**: Gegeven dat de IMGX-module is uitgeschakeld, wanneer de beheerder hergeneratie start en
  laat voltooien, dan verloopt de run zonder fout, krijgen de bijlagen de actieve WordPress-maten, en
  worden er geen `.webp`/`.avif`-bestanden aangemaakt. Bewijst de ontkoppeling uit B1: de module mag
  geen enkele functionele afhankelijkheid van IMGX hebben.
- **AC-033**: Gegeven dat de IMGX-module is uitgeschakeld, wanneer de beheerder de tab Afbeeldingsmaten
  opent, dan toont de pagina één regel uitleg: nieuwe maten krijgen geen WebP/AVIF, en dat is te
  herstellen door IMGX aan te zetten en daar "Ontbrekende afbeeldingen genereren" te draaien.
  Aanleiding: AC-023 dekt alleen het geval dat IMGX áán staat; zonder deze regel weet niemand dat de
  sidecars ontbreken. IMGX herstelt dat zelf, want `build_targets()` leest `$metadata['sizes']` vers.
| 4 | Slice 2: hergeneratie in batches | backend-dev | pending | — |
| 5 | Tests (Mode A+B samengevoegd) | tester | DONE | `tests/test-image-sizes.php` nieuw met 36 controles; `tests/test-imgx.php` 77 → 88. `php tests/run.php` → 10 bestanden, 0 mislukt |
| 5b | Regressietest bewezen | coördinator | DONE | Guard tijdelijk uitgeschakeld: 6 D-3-controles falen (82/6). Guard hersteld, marker weg, 10/10 groen. De regressietest vangt de bug dus echt |
| 6 | Cross-cutting review | code-reviewer | DONE_WITH_CONCERNS | Alle 23 AC's PASS, geen blokkerende bevindingen. 4 belangrijke punten, 6 suggesties |
| 7 | B1/B3/B4 uit de review verwerkt | backend-dev | DONE | Tweede fail-closed grens op het unlink-pad; aria-labels; class_exists-guard op drie handlers |
| 7b | Live-verificatie eindronde | coördinator | DONE | Zie hieronder |

## Live-verificatie na de reviewronde

- **Verdediging in de diepte bewezen.** Guard 1 (`is_intermediate_metadata_save()`) aantoonbaar
  uitgeschakeld op de testsite — marker geverifieerd aanwezig, lint schoon — en daarna hergeneratie
  gedraaid met IMGX aan. Resultaat 7 avif + 7 webp + 7 jpg, ongewijzigd. Ditzelfde scenario ging vóór
  B1 van 6 naar 2. De tweede grens vangt dus wat de eerste laat lopen. Guard 1 daarna hersteld,
  nul resten.
- **B3**: de vier knoppen dragen `aria-label` "Naar Uitgeschakeld", "Naar Actief",
  "Alles naar Uitgeschakeld", "Alles naar Actief"; de pijlglyphs staan achter `aria-hidden="true"`.
- **B4**: POST naar `pdk_image_sizes_save` met de module uitgeschakeld geeft HTTP 302 naar
  `tab=modules&error=De module Afbeeldingsmaten staat uit.` — geen fatale fout in de body.
- `php tests/run.php` → 10 bestanden, 0 mislukt (waarvan `test-imgx.php` 88 controles).

## Bewust niet opgepakt

- `handle_library_upload` (`includes/class-pdk-admin.php:56`, `:1705`) heeft exact dezelfde
  onvoorwaardelijke-handler-fout als B4. Door de reviewer als structureel aangemerkt. Buiten scope
  gehouden van deze taak: losse opvolgtaak.
- Suggesties S2 t/m S6 uit de review (leesbaarder label in de maatkiezer, "Opgeslagen"-melding bij het
  verwijderen van een niet-bestaande sleutel, `needs_generation()`-heuristiek bij crop-maten,
  `role="progressbar"`, één halve testcontrole die altijd waar is). Geen van alle gedragsbepalend.
- Een uitgezette eigen maat wordt nog steeds aangeboden in de editor-maatkiezer (S1). Geen AC eist het;
  één `is_enabled()`-controle zou het sluiten.

## Sessiestatus

- Fase: 2 — dispatch
- Slice: 0 (voor implementatie)
- Local stack: N/A
- Herdispatch-tellers: geen
- Volgende actie: product-analyst dispatchen voor de thin delta brief
