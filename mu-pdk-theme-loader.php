<?php
/**
 * PDK Theme Options — Must-Use Plugin Loader
 *
 * Gebruik dit bestand om PDK Theme Options als must-use plugin te draaien.
 * MU-plugins zijn altijd actief en kunnen niet per ongeluk worden gedeactiveerd.
 *
 * Installatie:
 *  1. Kopieer de volledige plugin-map naar wp-content/mu-plugins/pdk-theme-options/
 *  2. Kopieer DIT bestand naar wp-content/mu-plugins/pdk-theme-options.php
 *  3. Klaar — de plugin laadt automatisch bij elke pagina-aanvraag.
 *
 * Kinsta-hosting:
 *  Op Kinsta staat de mu-plugins map op twee locaties. Gebruik de primaire:
 *  /app/mu-plugins/pdk-theme-options.php  (primair)
 *  /wp-content/mu-plugins/pdk-theme-options.php  (fallback)
 *
 * Let op:
 *  - De GitHub-updater werkt niet in MU-modus (MU-plugins staan buiten het
 *    WordPress update-mechanisme). Updates moeten handmatig worden geïnstalleerd.
 *  - Er is geen activatie-hook in MU-modus. Bij eerste gebruik initialiseert de
 *    plugin zichzelf automatisch op de eerste admin-paginabezoek.
 *  - Koppel code-editor-rechten via PDK Tools → Rechten na installatie.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/pdk-theme-options/pdk-theme-options-plugin.php';
