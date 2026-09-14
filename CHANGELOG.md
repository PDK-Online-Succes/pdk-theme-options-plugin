# Changelog

Alle noemenswaardige wijzigingen in PDK Theme Options worden hier bijgehouden.

## [2.10.1] — 2026-09-14

### Documentatie per module

- **Zestien referentiedocumenten in `docs/modules/`**, één per module, met per module: hoe je hem aan- en uitzet, de instellingen met standaardwaarden, hoe hij werkt inclusief hooks en prioriteiten, wat er op schijf en in de database belandt, wat er bij verwijderen gebeurt, wat themaontwikkelaars kunnen aanroepen, de valkuilen, en welke zelftest hem dekt. Elke bewering verwijst naar `bestand:regel`
- **De README is weer de index.** De moduletabel stond op elf modules terwijl er zestien zijn — Security, Libraries, AI-agent toegang, IMGX en Afbeeldingsmaten ontbraken. Elke rij verwijst nu naar zijn referentiedocument; de diepgang staat daar en wordt in de README niet herhaald
- Drie verouderde beweringen in de README gecorrigeerd: "elke module is in- of uitschakelbaar" klopte niet (drie zijn altijd actief), de WooCommerce-afhankelijkheid werd aan de verkeerde module toegeschreven, en er stond nog een testpad van vóór de verhuizing naar `tests/`
- **`[bloginfo key="..."]` in de README moest `[bloginfo name="..."]` zijn.** De shortcode accepteert alleen `name` (`shortcode_atts`); een onbekende sleutel wordt stilzwijgend genegeerd, dus `key="admin_email"` gaf gewoon de sitenaam terug zonder enige foutmelding

### Modulelijst

- **De drie altijd-actieve modules (Security, Critical Error Status, Site Instellingen) staan nu gewoon in de modulelijst**, met een vaste, uitgegrijsde toggle, in plaats van in een los tekstblok boven de tabel — dat blok noemde Security er trouwens ook niet bij. Eén lijst laat in één oogopslag zien wat er allemaal draait
- Een uitgeschakelde checkbox verstuurt niets, dus zonder uitzondering zou het opslaan `enabled = false` wegschrijven voor precies die drie modules. `save_modules()` slaat ze nu over, net als het al deed voor modules waarvan WooCommerce ontbreekt
- `is_module_enabled()` geeft voor die drie altijd `true`. Dat is de effectieve toestand: ze draaien, ongeacht wat er in de optie staat. Ook de modulestatus die de Abilities API aan een AI-agent rapporteert klopt daarmee weer

## [2.10.0] — 2026-09-13

### Nieuwe module: Afbeeldingsmaten

Opsomming van de geregistreerde WordPress-afbeeldingsmaten (`thumbnail`, `medium`, `medium_large`, `large`, `1536x1536`, `2048x2048`, plus eigen en thema-maten), met een handgebouwde duallistbox (geen JS-library) om maten aan/uit te zetten, en eigen maten aanmaken/verwijderen. Standaard uit, aan te zetten op de Modules-tab.

- **Aan/uit is een blocklist**, net als `PDK_Libraries::disabled()`: een maat die nergens in `image_sizes.disabled` voorkomt is actief, ook als een thema hem pas later registreert. `thumbnail` is vergrendeld en kan niet worden uitgeschakeld, ook niet via een geprepareerde POST — de server verwijdert hem server-side uit de blocklist
- **Eigen maten** krijgen het voorvoegsel `pdk_`, verschijnen in de opsomming met een "eigen"-badge en in de maatkiezer van de blok-editor (`image_size_names_choose`). Ze zijn niet te bewerken: verwijderen en opnieuw aanmaken. Verwijderen van een niet-`pdk_`-sleutel wordt geweigerd — core- en thema-maten zijn niet aan te raken via deze module
- **Uitzetten verwijdert niets van schijf.** Alleen de generatie voor NIEUWE uploads stopt, via het filter `intermediate_image_sizes_advanced`; bestaande bestanden en metadata blijven ongewijzigd
- **WordPress-defaults blijven ongewijzigd.** Een andere afmeting wil je? Zet de default uit en maak een eigen maat aan
- **Hergeneratie van de mediabibliotheek in batches**, zelfde patroon als IMGX' batchrunner: stappen van 3 bijlagen, maximaal 15 seconden per AJAX-stap, voortgangsbalk met percentage, een stopknop die de al gegenereerde bestanden laat staan, en hervatten vanaf de laatste offset na het sluiten en heropenen van de tab. Twee modi: *ontbrekende maten* (alleen bijlagen waar een actieve, toepasbare maat ontbreekt) en *alle maten* (forceert de volledige maatset opnieuw per bijlage). Faalt een bijlage — bronbestand ontbreekt of onleesbaar — dan wordt dat gelogd en getoond en loopt de run door met de volgende. De status staat in een eigen optie `pdk_image_sizes_batch`; een tweede gelijktijdige start wordt geweigerd zonder de lopende run te verstoren
- **Werkt samen met IMGX zonder er ook maar iets van te weten.** Hergeneratie slaat metadata op via de normale WordPress-weg (`wp_update_attachment_metadata()`), waar IMGX's eigen sidecar-generatie al aan hangt zodra die module actief is — nul aanroepen naar IMGX-code. Staat IMGX uit, dan toont de tab één regel uitleg dat nieuwe maten geen WebP/AVIF krijgen tot IMGX weer aan staat
- Eén defect uit de eerste slice hersteld: een eigen maat met een naam die botst met een core-maatnaam (bijvoorbeeld `large`) werd ten onrechte geaccepteerd — de botsingscontrole vergeleek alleen de voorvoegde sleutel `pdk_large`, en die botst nooit. Nu wordt ook de kale ingevoerde naam getoetst
- **De maatkiezer van de blok-editor krijgt er alleen eigen maten bij.** Het filter is bewust additief: wat andere plugins aan `image_size_names_choose` toevoegen blijft staan. Staat er `medium_large`, `1536x1536` of `2048x2048` in, dan komt dat van een andere plugin — Oxygen doet dit bijvoorbeeld — en niet van deze module

### Nieuwe module: IMGX — WebP- en AVIF-afbeeldingen

De losse IMGX-plugin is als module opgenomen. Hij zet WebP- en AVIF-versies náást je bestaande JPEG- en PNG-bestanden en serveert die uit via `<picture>`; de originelen worden nooit gewijzigd, hercomprimeerd of verwijderd. Standaard uit, aan te zetten op de Modules-tab.

- **Eigen tab onder PDK Tools** zodra de module aan staat: formaatkeuze (AVIF+WebP, alleen AVIF, alleen WebP), kwaliteit per formaat, `<picture>`-vervanging aan/uit, gedrag bij nieuwe uploads (achtergrondtaak of direct) en debug-logging
- **Servercontrole is een echte encodeertest, geen gok.** De tabel *Server support* laat per formaat zien of de server het kan, met de reden erbij als het niet kan. Kan de server geen AVIF, dan blijft die `<source>` gewoon weg en werkt de rest
- **Bestaande mediabibliotheek bijwerken** met een voortgangsbalk die per batch bijwerkt en te stoppen is: *ontbrekende afbeeldingen genereren*, *alles opnieuw genereren* of *alle gegenereerde bestanden verwijderen*. Grote bibliotheken gaan sneller via WP-CLI: `wp imgx generate`, `--force`, `--ids=…`, en `wp imgx status`
- **Kolom NextGen in de mediabibliotheek** toont per afbeelding of er een AVIF- en WebP-variant is
- **Site Health** krijgt een test *Modern image format support* en een IMGX-sectie met het encoderrapport, met een link naar de IMGX-tab
- **Bij verwijderen van een bijlage** gaan de bijbehorende `.webp`/`.avif`-bestanden mee; andere bestanden in dezelfde map worden niet aangeraakt
- **De IMGX-instellingen staan in hun eigen optie** `imgx_settings`, niet in `pdk_theme_options`. Dat scheelde het herschrijven van de complete Settings API-registratie, en de rest van de module is ongewijzigd overgenomen — makkelijker terug te halen wat er sinds de import in de losse plugin is veranderd. Bij het verwijderen van de plugin worden `imgx_settings`, `imgx_capabilities`, `imgx_batch_state`, `imgx_recent_errors`, de generatie-locks en de `_imgx_variants`-postmeta opgeruimd. De gegenereerde bestanden blijven bewust staan: honderdduizenden bestanden verwijderen in een uninstall-hook loopt in een timeout — gebruik daarvoor eerst de knop *Delete all generated files* of `wp imgx delete`
- **De teksten zijn vertaald naar het Nederlands** en staan onder het textdomain `pdk-theme-options`, zoals de rest van de plugin. Ook de WP-CLI-uitvoer. Geen `.po`/`.mo`-bestanden nodig: de Nederlandse tekst staat in de code. In de tekst bij *Mediabibliotheek* stond `wp imgx generate --missing`; die vlag bestaat niet, dat is nu `wp imgx generate`
- **Draait de losse IMGX-plugin nog?** Zet die uit voordat je de module aanzet — anders worden de IMGX-constanten dubbel gedefinieerd en vuurt elke hook twee keer
- **Oxygen, Oxygen Classic en Breakdance worden herkend, zonder instelling.** Geen van drieën roept `the_content` aan — met een probe nagemeten: de callback stond geregistreerd en werd nul keer aangeroepen. Oxygen 6 en Breakdance delen één render-engine en vuren `breakdance_render_rendered_html` voor elk document dat ze opbouwen (pagina, header, footer); Oxygen Classic 4.x is een andere codebase die zijn pagina in de globale `$template_content` zet en die vlak na `ct_before_builder` uitprint. Op beide punten wordt nu ingehaakt. De builder-canvas zelf blijft ongemoeid, anders zie je in de editor iets anders dan je bewerkt
- **De `<picture>` neemt de classes van de afbeelding over.** Door het inpakken zakt de `<img>` een niveau in de DOM, en Oxygen positioneert via die class — `.ct-image` in Classic, `.oxy-image-2-100` per element in 6. Zonder overname stond de CSS op een element dat niet meer op die plek zit. Het `id` gaat bewust níet mee: twee elementen met hetzelfde id is ongeldige HTML en breekt `getElementById`. Een class die marges of padding zet geldt nu voor wrapper én afbeelding en telt op; met het filter `imgx_picture_class` haal je zo'n class van de wrapper af
- **Bijlage-ID's worden in één query opgezocht** als de `wp-image-<id>`-class ontbreekt, wat bij builder-markup altijd zo is. Een Oxygen-pagina met dertig afbeeldingen kost zo één query in plaats van dertig. URL's op een ander domein worden meteen afgewezen — anders kan een afbeelding van een vreemde host op een lokale bijlage uitkomen en krijg je `<source>`-URL's die daar 404 geven. Uitkomsten worden per verzoek gecachet, ook de missers
- **De optie *Markup → Paginabuilders* blijft als terugval** voor builders zonder eigen koppeling, standaard uit. Voor Oxygen, Oxygen Classic en Breakdance is hij niet meer nodig
- **Alle zelftests staan nu in `tests/` in de repo-root**, buiten `pdk-theme-options/`. De installer pakt alleen die map uit de zipball, dus er komt geen testcode meer op een klantsite terecht. `php tests/run.php` draait ze allemaal (elk in een eigen proces, want ze definiëren allemaal hun eigen `ABSPATH` en stubs); losse bestanden blijven werken met `php tests/test-<naam>.php`
- **Let op de opmaak van je thema.** Een afbeelding in een `<picture>` staat een niveau dieper in de DOM: een selector als `.card > img` matcht niet meer, `.card picture > img` of `.card img` wel. Breekt er iets, dan kan `<picture>`-vervanging uit — de gegenereerde bestanden blijven staan, er hoeft niets opnieuw gegenereerd te worden
- **Vier punten uit de code-review meteen meegenomen.** Het zelftestbestand had als enige in het project geen `PHP_SAPI !== 'cli'`-guard en draaide dus op een gewoon HTTP-verzoek naar zijn eigen pad — inclusief schrijven in de tijdelijke map en serverpaden in de uitvoer. `prune_stale_sizes()` las een lege doellijst als "elke maat is verouderd" en wiste dan de complete variantenregistratie, waardoor de `<picture>`-uitvoer stilviel terwijl de bestanden gewoon op schijf stonden; een lege lijst betekent nu "bron onbereikbaar" en er wordt niets opgeruimd. De statische caches van `Files`, `Variants`, `Settings` en de renderer worden op `switch_blog` geleegd — ze staan op bijlage-ID en bestandsnaam, en die botsen tussen sites in een multisite. En `Generator::clear_scheduled_events()` bestond wel maar werd nergens aangeroepen; dat gebeurt nu bij deactiveren en bij verwijderen
- **Hergeneratie verwijderde tijdelijk bestaande sidecars.** WordPress bouwt submaten sinds 5.3 incrementeel op en slaat de metadata na élke submaat tussentijds op (om een time-out te overleven); bij die tussenopslagen is `$metadata['sizes']` nog leeg of onvolledig. `on_update_metadata()` behandelde zo'n tussenopslag als gezaghebbend en `prune_stale_sizes()` ruimde dan alles op wat niet in die onvolledige lijst stond — registratie én bestanden op schijf — terwijl de bronbestanden gewoon bestonden. Een tussenopslag wordt nu herkend aan de call stack: staat `wp_create_image_subsizes()` er nog op, dan wordt er niets opgeruimd. Een maat die écht verdwijnt (uitgezet, verwijderd, bronbestand weg) wordt nog steeds opgeruimd bij de definitieve opslag
- **Kwaliteit verlagen laat overgeslagen varianten opnieuw proberen.** Een variant die groter uitviel dan het origineel werd als "klaar" geboekt, en *Ontbrekende afbeeldingen genereren* deed daarna niets meer — alleen een volledige hergeneratie pikte de nieuwe kwaliteit op. De registratie bewaart nu de vingerafdruk van modus en kwaliteiten waaronder ze is opgebouwd; wijkt die af, dan gaat een `larger-than-source` opnieuw door de encoder. Een door een filter geweigerde variant blijft overgeslagen, want die keuze staat los van de instellingen
- Zelftest: `php tests/test-imgx.php` (77 controles op bestandsnaamgeving, verwijdergaranties, formaatherkenning, srcset-mapping en het opschonen van instellingen)
- **Tweede, onafhankelijke grens tegen het verwijderen van sidecars bij hergeneratie.** De call-stack-detectie uit D-3 (`is_intermediate_metadata_save()`) faalt open: weet hij het niet zeker, dan mag `prune_stale_sizes()` opruimen. Die functie verwijdert nu nooit meer een `.webp`/`.avif`-bestand zolang het bronbestand waar het uit gegenereerd is nog op schijf staat, ongeacht wat de metadata zegt — controleert de bestandsnaam terug naar de bron in plaats van op de call stack te vertrouwen. Alleen de registratie-entry vervalt als de bron nog bestaat; het bestand blijft staan, wat ook geldt zodra een beheerder een maat uitzet en daarna hergenereert
- **De verplaatsknoppen van de duallistbox (Afbeeldingsmaten) hebben nu een `aria-label`** ("Naar Uitgeschakeld", "Naar Actief", "Alles naar Uitgeschakeld", "Alles naar Actief"); een schermlezer las eerder alleen "dubbel rechts aanhalingsteken" voor alle vier de knoppen. De pijltekens zelf zijn `aria-hidden`
- **De handlers van Afbeeldingsmaten gaven een fatale fout** als de module uitstond terwijl het tabblad nog open was gebleven (vereist `manage_options` en een geldige nonce, dus lage ernst). Ze controleren nu op `class_exists( 'PDK_Image_Sizes' )` en sturen bij een uitgeschakelde module terug naar de Modules-tab met een melding, net als elders in de plugin

## [2.9.0] — 2026-09-04

### Security: XML-RPC uit en `/wp/v2/` achter de login

Beide staan **standaard aan** op elke site, ook op sites die al draaien — instelbaar in de Security-tab als een koppeling ze nodig heeft.

- **XML-RPC volledig uit.** `xmlrpc_enabled=false` alleen is niet genoeg: `pingback.ping` blijft dan werkbaar. De methodelijst wordt nu leeggemaakt, en de `X-Pingback`-header en de RSD-link verdwijnen. Uitzetten als Jetpack of de WordPress-app nog via XML-RPC koppelt
- **Gevoelige REST-routes vragen om inloggen.** Standaard `/wp/v2/` — daar zit `/wp/v2/users`, dat zonder afscherming je gebruikersnamen weggeeft voor een brute force. Niet ingelogd levert een 401 op
- **Bewust alleen dichtzetten waar iets te halen valt, niet heel `/wp-json`.** Een allowlist zou élke betaalgateway, formulier-plugin en checkout-block moeten kennen, en betaalwebhooks zijn server-naar-server POSTs zonder cookie: één gemiste route betekent dat een geslaagde betaling nooit binnenkomt en de order op *in afwachting* blijft staan. Mollie verhuisde zijn webhook van `?wc-api=` naar `/wp-json/mollie/v1/webhook` — bij een allowlist zijn dat stilgevallen betalingen tot een klant belt. Nu raakt Mollie, PayPal, Stripe, Buckaroo, MultiSafepay, Adyen, de Store API en Contact Form 7 er niets van, ook als ze morgen van route wisselen
- **Beschermde routes** (textarea, één prefix per regel) zijn aan te passen. Alleen toevoegen wat de site zeker niet nodig heeft — een te ruim prefix legt stilletjes een webhook plat
- **IP-whitelist** (textarea, één IP per regel): deze adressen mogen de beschermde routes zonder inloggen gebruiken. Het veld toont je huidige IP. Alleen geldige IP-adressen worden opgeslagen. Werkt niet achter Cloudflare of een reverse proxy — dan ziet WordPress alleen het IP van de proxy
- **De prefixcheck is hoofdletterongevoelig**, net als de routematching van WordPress zelf: `/wp-json/WP/V2/users` komt ook bij de users-controller uit en glipt er dus niet langs
- **Een prefix zonder beginslash krijgt er één.** `wp/v2/` opslaan leverde een regel op die er actief uitzag maar nooit matchte — hij beschermde niets
- **Geweigerde REST-routes staan onderaan de Security-tab**, met hoe lang geleden en een vinkje om de lijst leeg te maken. Hoogstens één regel per route per uur, maximaal 20 routes, ook in de foutlog. Veel `/wp/v2/users` betekent dat iemand gebruikersnamen aan het verzamelen is; iets anders betekent dat een zelf toegevoegd prefix te ruim was
- **Ook hoogstens één onbekende route per uur erbij.** De route komt van de bezoeker, dus een scan op `/wp/v2/<willekeurig>` zou anders per verzoek een optie wegschrijven en de echte meldingen binnen één burst uit de lijst van 20 duwen. Prijs: loopt er een scan, dan komt een écht nieuwe weigering pas in de lijst nadat je hem leegmaakt
- **Regeleindes gaan uit de route voordat hij de foutlog in gaat**, zodat niemand met `?rest_route=/wp/v2/%0A…` zijn eigen regels in je log kan schrijven
- Bij verwijderen van de plugin worden nu ook `pdk_mu_hashes`, `pdk_missing_required_plugins` en `pdk_rest_blocked_routes` opgeruimd
- De afscherming zit in PHP, niet in `.htaccess`: dat werkt ook op nginx, overleeft een serverwissel, en kan de beheerder niet buitensluiten. Achter Cloudflare of een reverse proxy is de IP-whitelist onbruikbaar — WordPress ziet dan alleen het IP van de proxy
- Zelftest uitgebreid: `php modules/security/test-security.php`

### Actieve tabs als submenu onder PDK Tools

- **Elke zichtbare tab staat nu ook in het admin-menu** onder *PDK Tools*, dus naar Custom CSS of Security is het één klik in plaats van eerst de plugin openen en dan de tab kiezen. Het submenu volgt de tabs: een module die uit staat, verschijnt er niet in
- Het juiste submenu-item wordt gemarkeerd zolang je op die tab zit — WordPress kijkt normaal alleen naar `?page=`, en dat is voor alle tabs hetzelfde
- De kop boven de pagina blijft *PDK Theme Options*; zonder ingreep neemt WordPress de naam van het eerste submenu-item over en stond op elke tab "Modules"

## [2.8.1] — 2026-08-27

### Libraries: meerdere bestanden tegelijk en sourcemaps

- **Meerdere bestanden tegelijk uploaden** kan nu: het uploadveld accepteert een selectie. Lukt er één niet, dan gaan de rest gewoon door en meldt de foutmelding welk bestand is overgeslagen en waarom (*2 bestanden geplaatst. nope.php: ongeldig bestandstype…*)
- **`.map`-bestanden mogen mee.** Een minified bestand eindigt op `sourceMappingURL=….map`; ontbreekt die, dan geeft de browserconsole een 404. Een sourcemap wordt zelf nooit ingeladen: hij staat in de lijst zonder vinkje, alleen met een verwijderknop
- **Bugfix:** een bestandsnaam die ooit is uitgezet bleef in de uit-lijst staan, ook nadat het bestand verwijderd was. Een later opnieuw geüpload bestand met dezelfde naam laadde daardoor niet. Uploaden en verwijderen halen de naam nu uit die lijst

## [2.8.0] — 2026-08-27

### Nieuwe module: Libraries — losse JS- en CSS-bestanden

Voor kant-en-klare bibliotheken als Glide.js, Swiper of Splide, zonder ze in het thema te zetten.

- Nieuwe tab **Libraries** (zichtbaar zodra de module aan staat in de Modules-tab) met een upload-veld voor `.js` en `.css`, een lijst van wat er staat, een vinkje per bestand om het wel/niet te laden, en een verwijderknop
- Elk ingeschakeld bestand laadt op **alle** frontend-pagina's: CSS in de head, JS in de footer. Versie = `filemtime`, dus na een nieuwe upload is de cache meteen vers
- **Laadvolgorde is alfabetisch.** Zet er een cijfer voor als het uitmaakt: `10-swiper.min.js` vóór `20-slider-init.js`
- Bestanden staan in `uploads/pdk-theme-options/libraries/`, dus buiten de pluginmap — een plugin-update raakt ze niet. De bestaande `.htaccess` in de storage-map blokkeert daar ook `.php`
- Uitzetten laat het bestand staan (alleen niet laden); alleen wat expliciet uit staat wordt overgeslagen, zodat een nieuwe upload meteen werkt
- **Uploaden en verwijderen vraagt code-editor rechten** (`PDK_CAP_EDIT_CODE`), niet alleen `manage_options` — een JS-bestand uploaden is code op de site zetten. Beheerders zonder die rechten zien de lijst read-only
- Alleen `.js` en `.css` worden geaccepteerd, bestandsnamen worden geschoond en namen die met een punt beginnen geweigerd
- Zelftest: `php tests/test-libraries.php`

### Library-bestanden bewerken, met dezelfde beveiliging als de custom code

Handig voor bestanden als `glide.theme.css` — de optionele opmaak die je per site aanpast.

- Knop **Bewerken** per bestand opent dezelfde CodeMirror-editor als de PHP-, CSS- en JS-tabs, inclusief de vergelijking met de laatst opgeslagen versie
- Opslaan legt een SHA-256-vingerafdruk vast en bewaart de vorige versie als `.bak`, precies zoals bij `custom-style.css`
- Wijkt een bestand daarna af, dan **wordt het niet meer ingeladen** en verschijnt de bestaande integriteitsmelding met *Herstel back-up* / *Wijziging vertrouwen*. In de lijst staat er dan bij: *gewijzigd buiten de editor — wordt niet geladen*
- Bij het uploaden wordt de vingerafdruk meteen vastgelegd, dus vanaf dat moment telt elke wijziging buiten de editor als manipulatie
- Bewerken vraagt code-editor rechten, net als uploaden
- De integriteitscontrole kijkt nu naar `pdk_watched_files()`: de drie code-bestanden plus alle libraries

### Bugfix: de integriteits-zelftest controleerde niets

- `includes/test-file-integrity.php` gebruikte `assert()`. Met `zend.assertions=-1` — de standaard in een productie-PHP, ook in Local — worden die regels wegcompileerd, dus de test printte altijd `OK` zonder iets te doen. Vervangen door een echte controle die faalt met exitcode 1. Alle 16 controles slagen; de gecontroleerde logica was dus in orde, de test alleen niet

## [2.7.0] — 2026-08-26

### Security-tab: plugins die actief moeten blijven

- Nieuwe tab **Security** in het PDK-menu met een vinklijst van alle geïnstalleerde plugins. Vink aan welke op deze site altijd actief moeten blijven; per site instelbaar, opgeslagen als slug onder `security.required_plugins`
- Gaat er één uit, dan gaat er een mail naar `admin_email` en zien beheerders een melding in de admin. De tab zelf toont ook direct wat er mist
- Er wordt pas opnieuw gemaild als de lijst met uitgevallen plugins *verandert* — dus geen mail bij elke pageload. Komt alles weer goed, dan wordt de stand (`pdk_missing_required_plugins`) gewist zodat een volgende uitval opnieuw gemeld wordt
- De plugin wordt **niet** automatisch geheractiveerd; dat blijft een bewuste handeling
- Dit is het enige onderdeel van de security-module met een instelling. De header-firewall, de blacklists en de MU-integriteitscontrole blijven vastliggen in de code
- Zelftest uitgebreid: `php modules/security/test-security.php`

## [2.6.0] — 2026-08-26

### Nieuwe module: Security — vier maatregelen, geen toggle

`modules/security/class-pdk-security.php` wordt als eerste geladen, nog vóór Critical Error Status. Er is bewust **geen instelling** om hem uit te zetten; de lijsten staan als constanten in het bestand.

**1. Header-firewall.** Requests met een verdachte custom header krijgen een 403 en worden gelogd. Geblokkeerd wordt een headernaam die alleen uit hex bestaat (`HTTP_F5C4F24` — het patroon van eval-via-header backdoors) en elke headerwaarde die op PHP-code lijkt (`eval(`, `base64_decode(`, `system(`, `exec(`, `assert(`). Draait direct bij het laden van de module, dus vóór `init` en vóór de rest van de plugin.

**2. MU-plugin blacklist.** Verwijdert `installatron_hide_status_test.php`, `automation-by-installatron.php` en `test-mu-plugin.php` uit `wp-content/mu-plugins/`, plus alles wat op `*.suspected` matcht (het patroon dat scanners voor quarantaine gebruiken). Draait op `muplugins_loaded` (prioriteit 1) wanneer de plugin als must-use draait; in reguliere plugin-modus is die hook al gepasseerd op het moment dat de plugin laadt, dus dan gebeurt het meteen bij het inladen van de module.

**3. Plugin-blacklist.** Staat een geblokkeerde plugin actief, dan wordt hij op `admin_init` gedeactiveerd met een foutmelding in de admin. Nu in de lijst: `wp-file-manager` en `wtec-webp`. **Alleen de slug (mapnaam) invoeren** — `wp-file-manager`, niet `wp-file-manager/file_folder_manager.php`; de naam van het hoofdbestand doet er niet toe, ook niet als die na een update verandert. Overgezet uit de losse `pdk-custom-functions`-snippet.

**4. Integriteitscontrole van `mu-plugins/`.** Maximaal één keer per uur worden de SHA-256-vingerafdrukken van alle `.php` in `mu-plugins/` vergeleken; bij een nieuw, gewijzigd of verwijderd bestand gaat er een mail naar `admin_email` en een regel naar `debug.log`. De baseline staat in de optie `pdk_mu_hashes`, niet in `mu-plugins/` zelf — een backdoor kan hem daar niet bijwerken. Eerste run legt de baseline vast zonder te mailen (trust-on-first-use), en na een melding wordt de baseline direct bijgewerkt zodat dezelfde afwijking niet elk uur opnieuw mailt.

- Alle meldingen loggen naar `debug.log` met de prefix `[PDK Security]`
- Zelftest: `php tests/test-security.php`
- Let op: netwerk-geactiveerde plugins op multisite worden bij punt 3 nog niet gecontroleerd

### MU Installer 1.1.0 — loader draait nu als eerste MU-plugin

- WordPress laadt must-use plugins puur op alfabet, er is geen prioriteit. De loader heet daarom niet meer `pdk-theme-options.php` maar **`00-pdk-theme-options.php`**, zodat de header-firewall en de blacklist-opruiming vóór elke andere MU-plugin draaien
- Bij (her)installeren wordt de oude loader zonder prefix automatisch verwijderd, dus er blijven er nooit twee staan. Verwijderen ruimt beide namen op
- **Installer-wijziging: klanten krijgen dit niet via GitHub.** De zip van `pdk-mu-installer/` moet opnieuw geüpload en geactiveerd worden, daarna één keer op *Installeren/Bijwerken* klikken om de loader te hernoemen

## [2.5.0] — 2026-08-20

### Echte code-editor: CodeMirror 6

- De textarea voor PHP, CSS en JS is vervangen door CodeMirror 6: syntax-kleuring, regelnummers, code vouwen, haakjes-matching, meerdere cursors, zoeken/vervangen (Ctrl+F), autocompletion en undo/redo
- **Geen linters** — de editor kleurt, hij keurt niet af. CSS Nesting, PHP 8.5 en ES2026 leveren dus nooit een valse foutmelding. De echte PHP-syntaxcontrole gebeurt bij het opslaan server-side met `token_get_all()`, dus met de PHP-versie van de site zelf
- De textarea blijft achter de schermen bestaan en loopt mee: opslaan, Ctrl+S en terugvallen zonder JavaScript werken ongewijzigd
- Bijwerken naar de nieuwste CodeMirror: `npm run update` in de repo-root (bouwt de bundel opnieuw en draait de rooktest). Klanten hebben geen build-stap: `assets/js/editor.bundle.js` staat gebouwd in de repo
- De bundel (672 kB) laadt alleen op de drie code-tabs, niet op de rest van de admin

### Diff-weergave

- Knop *Vergelijk met laatst opgeslagen versie* op elke code-tab: zij-aan-zij vergelijking tussen de `.bak` (laatst via de editor opgeslagen) en de huidige inhoud, met ongewijzigde blokken ingeklapt
- De integriteitsmelding heeft een knop *Bekijk de wijziging* die direct in die vergelijking opent — zo zie je precies wat er buiten de editor om is bijgeschreven
- Op een gemanipuleerd bestand opent de vergelijking automatisch

### Overig

- Nieuw in de repo-root: `package.json` en `src/` (buildbronnen). De MU-installer pakt alleen `pdk-theme-options/` uit, dus klanten krijgen ze niet mee
- Rooktest voor de editor: `npm test`

## [2.4.0] — 2026-08-20

### Code-editor-rechten vastzetten in wp-config.php

- Nieuwe constante `PDK_CODE_EDITORS` (gebruikers-ID's, logins of e-mailadressen, komma-gescheiden). Is die gedefinieerd, dan is wp-config leidend: capabilities uit de database worden genegeerd en de Rechten-tab is read-only
- Werkt via `map_meta_cap`, dus ook multisite-superbeheerders passeren de controle niet meer
- **Bugfix:** een beheerder uitvinken in de Rechten-tab had geen effect als de capability op de administrator-*rol* stond (oudere installaties) — `remove_cap()` op een gebruiker haalt een rol-capability niet weg. Opslaan verwijdert de capability nu ook van alle rollen

### Integriteitscontrole van custom PHP, CSS en JS

- Bij elke opslag wordt een SHA-256-vingerafdruk vastgelegd en de vorige versie bewaard als `.bak`
- Wijkt een bestand daarna af, dan wordt `custom-functions.php` niet meer ingeladen en worden CSS/JS niet meer uitgeserveerd — een backdoor die zichzelf bijschrijft draait dus nooit
- Beheerders krijgen een melding met *Herstel back-up* of *Wijziging vertrouwen* (voor bewuste wijzigingen via SFTP of WP-CLI)
- Bestaande installaties: de huidige inhoud wordt éénmalig als vertrouwd vastgelegd — controleer de bestanden één keer na deze update
- `.htaccess` in de storage-map blokkeert nu ook `.bak`-bestanden (voorheen alleen `.php`) en gebruikt `Require all denied` voor Apache 2.4. Wordt bij deze update automatisch herschreven
- Zelftest: `php tests/test-file-integrity.php`

### Documentatie

- README: `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` als aanvullende hardening, plus uitleg over `PDK_CODE_EDITORS` en de integriteitscontrole

## [2.3.1] — 2026-08-17

### De agent weet nu wat de plugin op de frontend rendert

- Nieuwe ability `pdk-theme-options/get-site-info`: bedrijfsgegevens, social media, openingstijden, afwijkende periodes, of het vandaag gesloten is, welke modules aan staan, en de bestaande helpers (`pdk_site_setting()`, `pdk_company_address()`, `pdk_opening_hours_html()`, `PDK_Site_Settings::*`) en shortcodes
- Per frontend-uitvoer (`[openingstijden]`, `[levertijd]`, `[vakantiemelding]`) de shortcode, de template-hook, de HTML-opbouw met CSS-klassen én `html_now`: de HTML zoals die er op dat moment daadwerkelijk uitkomt. Ook de uitvoer buiten shortcodes staat erin — favicon, de handles `pdk-custom-style` en `pdk-custom-script`, het `@font-face`-blok, de loginpagina en de term-meta `short_description`
- De markup-beschrijving staat als derde argument bij `pdk_register_frontend_output()`, dus náást de render-code; nieuwe modules die die helper gebruiken verschijnen automatisch in de ability. Nieuw: `pdk_frontend_outputs()` geeft dat register terug
- De schrijf-ability verwijst naar `get-site-info`, zodat een agent gegevens ophaalt en bestaande shortcodes hergebruikt in plaats van hard codeert, en CSS op de juiste klassen zet
- De agent kan de openingstijden en klantgegevens alleen lézen — wijzigen blijft aan de klant via *Site Instellingen*

### Site Instellingen

- Favicon en logo kiezen uit de mediabibliotheek, met voorbeeldweergave en een "Verwijderen"-knop. De waarde blijft een URL, dus `pdk_client_logo_url()` en de favicon-output werken ongewijzigd en bestaande instellingen blijven staan
- De regel "Gebruik pdk_site_setting(...)" stond onder élk sub-tabblad; hij staat nu alleen onder Klantgegevens, en noemt ook `pdk_company_address()`

## [2.3.0] — 2026-08-17

### AI-agent toegang tot eigen PHP, CSS en JS (MCP)

- Nieuwe module *AI-agent toegang (MCP)* (standaard uit): registreert `pdk-theme-options/read-custom-code` en `pdk-theme-options/write-custom-code` via de WordPress Abilities API (WP 6.9+), met `file` = `php` | `css` | `js`
- Een MCP-server publiceert die automatisch als tools, via `meta.mcp.public = true` zoals Agent Connector en de WordPress MCP Adapter verwachten. De plugin spreekt zelf geen MCP-protocol
- Ook bereikbaar over REST onder `wp-abilities/v1` (`meta.show_in_rest = true`)
- Toegang loopt via de bestaande capability `pdk_edit_custom_code`: de gebruiker waarmee de agent inlogt moet code-editor rechten hebben (Rechten-tab). Beheerder zijn is niet genoeg
- Zelftest: `php tests/test-agent-abilities.php`

### Gewijzigd

- `pdk_write_storage_file()` weigert PHP met een syntaxfout (`token_get_all` met `TOKEN_PARSE`) en meldt regelnummer + fout. Geldt voor élke schrijver — dus ook de admin-code-editor kan de site niet meer platleggen met een typefout

## [2.2.0] — 2026-08-17

### Afwijkende dagen: één lijst voor openingstijden, levertijden en vakantiemodus

- Nieuwe sub-tab *Site Instellingen → Afwijkende dagen*: periodes met van/tot, omschrijving, afwijkende openingstijden en een vinkje "Webshop sluiten"
- Kerst, oud en nieuw, zomerperiodes en bedrijfsvakanties worden nog maar op één plek ingevuld; voorheen kostte een sluiting twee invoerplekken en waren afwijkende openingstijden helemaal niet mogelijk
- Openingstijden per periode: tijden leeg = gesloten, tijden ingevuld = afwijkende openstelling
- De openingstijden-tabel kijkt zeven dagen vooruit, zodat een periode op de juiste weekdag landt, met een melding erboven: "Let op: afwijkende openingstijden i.v.m. Kerst"
- Verzenden wordt afgeleid en heeft geen eigen instelling meer: een gesloten periode telt als niet-verzenddag, een periode met afwijkende tijden verzendt gewoon door
- Nieuwe API voor thema's: `PDK_Site_Settings::active_period()`, `::matching_periods()`, `::is_closed_on()`

### Gewijzigd

- **Levertijden:** de tabel "Uitzonderingsdata" is vervallen; die data staat nu bij Afwijkende dagen, met een verwijzing op de tab
- **Vakantiemodus:** start- en einddatum zijn vervallen; de tab toont de geplande sluitingen uit de periodelijst. Staat de module aan zonder enige sluitingsperiode, dan is de webshop direct dicht — gelijk aan het oude gedrag zonder datums
- Bestaande uitzonderingsdata en vakantiedatums worden automatisch omgezet naar periodes bij de eerste keer laden; vielen ze samen, dan worden het één rij

## [2.1.0] — 2026-08-17

### PDK MU Installer (nieuwe micro-plugin)

- Losse plugin `pdk-mu-installer/` — te uploaden via *Plugins → Nieuwe plugin*
- Installeert PDK Theme Options als must-use plugin vanuit de laatste GitHub-release, inclusief de loader `mu-plugins/pdk-theme-options.php`
- Meldt nieuwe releases in de admin; bijwerken en opnieuw installeren met één knop, plus "MU-plugin verwijderen"
- Versievergelijking op de `Version:`-header van de geïnstalleerde MU-plugin tegen de laatste release-tag; release-info 12 uur gecached
- Vereist directe schrijftoegang (`FS_METHOD` direct); hosts die FTP-gegevens eisen krijgen een duidelijke foutmelding

### Admin: Ctrl+S opslaan

- `Ctrl+S` (of `Cmd+S`) slaat op waar je op dat moment in zit: bericht, pagina, WooCommerce-product of instellingenpagina
- Werkt op elke admin-pagina, niet alleen op de PDK-tabs
- Op een concept wordt "Concept opslaan" gebruikt, nooit "Publiceren"
- In de blok-editor doet het script niets — die heeft z'n eigen Ctrl+S

### Site Instellingen: sub-tabs

- Basis / Klantgegevens / Openingstijden / Social Media staan nu op aparte sub-tabs
- Alle secties blijven in één formulier: één keer opslaan bewaart alles
- Gekozen sub-tab wordt onthouden per sessie; een `#anker` in de URL wint daarvan

### Site Instellingen: openingstijden

- Openingstijden per weekdag (van/tot of "Gesloten"), instelbaar onder *PDK Tools → Site Instellingen*
- Uitvoer via `[openingstijden]`, `do_action( 'pdk_openingstijden' )` of `pdk_opening_hours_html()`
- Een half ingevulde dag (alleen "van" of alleen "tot") telt als gesloten — nooit een halve tijdsaanduiding op de site

### Frontend-uitvoer via shortcode én template-hook

- Nieuwe helper `pdk_register_frontend_output( $naam, $callback )`: één registratie levert zowel `[naam]` als `do_action( 'pdk_naam' )`
- Toegepast op Levertijden (`[levertijd]`), Vakantiemodus (`[vakantiemelding]`, nieuw) en Openingstijden
- Dagnamen staan nu centraal in `pdk_day_labels()` in plaats van per module

### Module: SKU Beperken & Valideren (nieuw)

- Overgenomen uit de losse plugin `wc-sku-beperking.php` — dat bestand is uit de repo verwijderd
- **Punt toegevoegd aan de toegestane tekens**: `a-z A-Z 0-9 . -` (was zonder punt)
- Opschonen bij opslaan én live tijdens typen; botst de opgeschoonde SKU met een bestaande, dan blijft de oude SKU staan met een foutmelding
- WP-CLI-conversie hernoemd naar `wp pdk sku-convert` (`--live` om te schrijven)
- Vereist WooCommerce — valt onder dezelfde afhankelijkheidsbewaking

### Module: Levertijden (nieuw)

- Verzenddagen met eigen cutoff-tijd per weekdag; tekst instelbaar met de tags `{cutoff}`, `{dag}` en `{volgende_dag}`
- Uitzonderingsdata (bijv. kerst, bedrijfsuitje) — enkele dag of periode, worden overgeslagen bij het bepalen van de eerstvolgende verzenddag
- Product-uitzondering via het bestaande veld `pdk_edt` heeft voorrang op de algemene instellingen
- Shortcode `[levertijd]` voor de productpagina
- Instellingen onder *PDK Tools → Levertijden* (niet meer onder WooCommerce → Levertijden)

### WooCommerce-afhankelijkheid

- Modules die WooCommerce nodig hebben (`vacation_mode`, `delivery_time`) worden niet geladen en tonen geen tab zolang WooCommerce inactief is — voorkomt fatale fouten
- De toggle van zo'n module is uitgeschakeld in de Modules-tab; de opgeslagen voorkeur blijft behouden en werkt weer zodra WooCommerce actief is
- `pdk_woocommerce_active()` valt terug op de lijst met actieve plugins, omdat modules al vóór `plugins_loaded` geladen worden

## [2.0.0] — 2026-07-30

Volledige herstructurering: acht afzonderlijke plugins samengebracht in één modulaire plugin.

### Samengevoegde plugins

| Oud | Functionaliteit |
|---|---|
| `pdk-critical-error-status` | HTTP 500 bij fatale PHP-fouten |
| `pdk-theme-options-plugin` (v1) | Carbon Fields klantgegevens, social media |
| `custom-functions` | PHP-hulpfuncties, SVG-uploads, WooCommerce-uitbreidingen |
| `custom-css` | Eigen CSS via editor |
| `custom-js` | Eigen JavaScript via editor |
| `ma-custom-fonts` | @font-face CSS generatie |
| `custom-login-page` | Aangepaste loginpagina |
| `language-checker` | Taalbestandsbeheer |

### Nieuw

- **Modulaire architectuur** — elke module is afzonderlijk in- of uitschakelbaar
- **Must-Use plugin ondersteuning** — `mu-loader.php` voor MU-installatie; automatische eerste-keer-initialisatie via `admin_init`
- **GitHub Releases updater** (`PDK_Updater`) — automatische updates via WordPress update-mechanisme (niet actief in MU-modus)
- **Custom capability** `pdk_edit_custom_code` — code-editor-rechten los van `administrator`, per gebruiker instelbaar via *PDK Tools → Rechten*

### Module: Custom Fonts

- Fontbestanden scannen vanuit `wp-content/uploads/fonts/`
- Automatische weight/style-detectie uit bestandsnaam (`FamilyName-Bold.woff2`)
- `@font-face` CSS gegenereerd op `wp_head` en `admin_head`
- **Nieuw:** Admin-tab toont fontnamen gegroepeerd per familie met live preview
- **Nieuw:** Upload-formulier in admin (alleen `woff2`, `woff`, `ttf`, `otf`; vereist `manage_options`)
- **Nieuw:** Verwijderknop per fontbestand met bestandsgrootte-weergave
- Configureerbare `font-display` strategie (auto / block / swap / fallback / optional)
- **Nieuw:** CSS-uitvoer kiezen: inline in `<head>` of gecached extern bestand (`pdk-custom-fonts.css`)
- **Nieuw:** Gutenberg-integratie — custom fonts beschikbaar in de blok-editor font picker (via `wp_theme_json_data_theme`)
- **Nieuw:** Variable font-ondersteuning (`VariableFont` of `[wght]` in bestandsnaam → `font-weight: 1 1000`)
- **Nieuw:** Multi-source `@font-face` — woff2 + ttf van dezelfde variant gecombineerd in één `src:`-regel
- **Fix:** Woordgrens-regex voor gewichtdetectie — `SemiCondensed` werd eerder fout als gewicht 600 herkend

### Module: Custom Functions

- Eigen `custom-functions.php` laden vanuit `wp-content/uploads/pdk-theme-options/`
- Block Library CSS en Dashicons uitschakelen voor niet-ingelogde gebruikers
- Automatisch deactiveren van WP File Manager plugin
- SVG-uploads toestaan met correcte MIME-type-validatie
- Automatische plugin/thema-update e-mails uitschakelen
- WP Mail SMTP-samenvattingsmail uitschakelen
- Shortcodes: `[pdk_year]`, `[bloginfo]`
- WooCommerce: korte beschrijving op productcategorieën, checkout-adresvalidatie, archief-titelopmaak

### Module: Site Instellingen

- Favicon-URL instelling
- Gutenberg uitschakelen voor paginatype *Pagina*
- Klantgegevens: naam, straat + huisnummer, postcode + stad, telefoon, e-mail
- Klantlogo-URL
- Social media: Facebook, Instagram, LinkedIn, X/Twitter, YouTube, TikTok
- Hulpfuncties: `pdk_site_setting()`, `pdk_client_logo_url()`, `pdk_company_address()`

### Module: Login Page

- PDK-logo (SVG) en achtergrond (PNG) ingebundeld in plugin — geen externe afhankelijkheden
- Inline CSS (geen apart bestand) voor robuustere caching
- Donkerpaarse navigatielinks met witte gloed voor leesbaarheid op lichte achtergrond

### Module: Language Cleaner

- Geïnstalleerde WordPress-kerntalen weergeven via `wp_get_installed_translations('core')`
- Talen verwijderen inclusief alle bijbehorende bestanden:
  - `.mo`, `.po`, `.l10n.php` (patroon `*{locale}.*`)
  - Gehashte JSON-bestanden (patroon `*{locale}-*`)
  - Bestanden in `WP_LANG_DIR`, `WP_LANG_DIR/plugins/` en `WP_LANG_DIR/themes/`
- Verweesde vertaalbestanden opsporen (plugins/thema's die niet meer geïnstalleerd zijn)
  - Regex voor locale-achtervoegsel toegepast vóór `strtolower()` (correcte `_[A-Z]{2}` matching)
- Actieve taal kan niet worden verwijderd

### Module: Vakantiemodus

- Vrije melding (HTML toegestaan)
- Optioneel: winkelwagen uitschakelen
- Optioneel: checkout uitschakelen (redirect naar winkel)
- Start- en einddatum (automatisch activeren/deactiveren)

### Module: Critical Error Status

- HTTP 500 bij fatale PHP-fouten (i.p.v. standaard 200 met foutpagina)
- Altijd actief, niet uitschakelbaar

### Technisch

- Geen externe afhankelijkheden (geen Carbon Fields, geen Composer)
- Native WordPress Settings API
- `PDK_Loader` — alle hooks verzameld, één keer geregistreerd via `run()`
- Clientbestanden in `wp-content/uploads/pdk-theme-options/` (blijven bij plugin-updates)
- WooCommerce HPOS-compatibiliteit gedeclareerd

---

## [1.x] — voor 2026

Zie afzonderlijke repositories van de acht component-plugins.
