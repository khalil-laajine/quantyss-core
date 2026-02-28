<?php
/**
 * Plugin Name: Quantyss Core
 * Plugin URI:  https://quantyss.com
 * Description: Outils sur mesure pour le site Quantyss — sliders, shortcodes et extensions métier.
 * Version:     1.0.0
 * Author:      Khalil LAAJINE
 * Author URI:  https://novasiteweb.fr
 * Text Domain: quantyss-core
 * License:     GPL-2.0+
 */

defined('ABSPATH') || exit; // Sécurité : empêche l'accès direct

// Constantes utiles partout dans le plugin
define('QUANTYSS_VERSION', '1.0.0');
define('QUANTYSS_PATH', plugin_dir_path(__FILE__));
define('QUANTYSS_URL',  plugin_dir_url(__FILE__));

// Chargement des modules
require_once QUANTYSS_PATH . 'includes/enqueue.php';
require_once QUANTYSS_PATH . 'includes/slider.php';