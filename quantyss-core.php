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

// Elementor — chargé uniquement si Elementor est actif
if (did_action('elementor/loaded')) {
    require_once QUANTYSS_PATH . 'includes/elementor/elementor-init.php';
}

// Dashboard Admin
require_once QUANTYSS_PATH . 'includes/admin/dashboard.php';

// Composer autoload
if (file_exists(QUANTYSS_PATH . 'vendor/autoload.php')) {
    require_once QUANTYSS_PATH . 'vendor/autoload.php';
}

require_once QUANTYSS_PATH . 'includes/admin/stats.php';

register_activation_hook(__FILE__, 'quantyss_create_leads_table');

function quantyss_create_leads_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'quantyss_leads';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name    VARCHAR(100) NOT NULL,
        last_name     VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL,
        phone         VARCHAR(30)  DEFAULT '',
        company       VARCHAR(150) DEFAULT '',
        message       TEXT         DEFAULT '',
        status        VARCHAR(30)  DEFAULT 'new',
        source        VARCHAR(100) DEFAULT 'cf7',
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('quantyss_db_version', '1.0.0');
}

require_once QUANTYSS_PATH . 'includes/leads-handler.php';
require_once QUANTYSS_PATH . 'includes/admin/leads.php';