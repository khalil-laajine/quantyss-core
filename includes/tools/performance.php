<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// OPTIMISATIONS SILENCIEUSES
// -------------------------------------------------------

// Désactiver l'emoji WordPress (économise 3 requêtes HTTP)
function quantyss_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'quantyss_disable_emojis');

// Supprimer le lien RSD inutile dans le <head>
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_generator'); // Cache la version WP

// Désactiver XML-RPC (vecteur d'attaque fréquent)
add_filter('xmlrpc_enabled', '__return_false');

// Lazy load natif sur toutes les images
function quantyss_add_lazy_load($attr) {
    $attr['loading'] = 'lazy';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'quantyss_add_lazy_load');

// Nettoyage BDD automatique — cron hebdomadaire
function quantyss_schedule_cleanup() {
    if (!wp_next_scheduled('quantyss_db_cleanup')) {
        wp_schedule_event(time(), 'weekly', 'quantyss_db_cleanup');
    }
}
add_action('wp', 'quantyss_schedule_cleanup');

function quantyss_run_db_cleanup() {
    global $wpdb;

    // Révisions d'articles (garde les 3 dernières par post)
    $wpdb->query("
        DELETE FROM {$wpdb->posts}
        WHERE post_type = 'revision'
        AND ID NOT IN (
            SELECT * FROM (
                SELECT ID FROM {$wpdb->posts} p2
                WHERE p2.post_parent = {$wpdb->posts}.post_parent
                ORDER BY p2.post_date DESC
                LIMIT 3
            ) tmp
        )
    ");

    // Transients expirés
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_timeout_%'
        AND option_value < UNIX_TIMESTAMP()
    ");
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_%'
        AND option_name NOT LIKE '_transient_timeout_%'
        AND option_name NOT IN (
            SELECT REPLACE(option_name, '_timeout', '') 
            FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_%'
        )
    ");

    // Commentaires spam
    $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");

    quantyss_log('db_cleanup', 'Nettoyage automatique hebdomadaire');
}
add_action('quantyss_db_cleanup', 'quantyss_run_db_cleanup');

// -------------------------------------------------------
// MESURE DU TEMPS DE CHARGEMENT
// -------------------------------------------------------
function quantyss_start_timer() {
    if (!is_admin()) {
        $GLOBALS['quantyss_start'] = microtime(true);
    }
}
add_action('init', 'quantyss_start_timer');

function quantyss_record_load_time() {
    if (is_admin() || !isset($GLOBALS['quantyss_start'])) return;

    $duration = round((microtime(true) - $GLOBALS['quantyss_start']) * 1000);
    $url      = $_SERVER['REQUEST_URI'] ?? '/';

    $history = get_option('quantyss_perf_history', []);
    $history[] = [
        'url'  => $url,
        'ms'   => $duration,
        'date' => date('Y-m-d H:i'),
    ];

    // Garde les 200 dernières mesures
    if (count($history) > 200) {
        $history = array_slice($history, -200);
    }

    update_option('quantyss_perf_history', $history, false);
}
add_action('wp_footer', 'quantyss_record_load_time');