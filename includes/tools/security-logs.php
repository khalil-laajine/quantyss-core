<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// TABLE DES LOGS
// -------------------------------------------------------
function quantyss_create_logs_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'quantyss_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id    BIGINT(20) UNSIGNED DEFAULT 0,
        username   VARCHAR(100) DEFAULT '',
        action     VARCHAR(100) NOT NULL,
        object     VARCHAR(200) DEFAULT '',
        ip         VARCHAR(45)  DEFAULT '',
        user_agent VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('quantyss_plugin_activated', 'quantyss_create_logs_table');

// -------------------------------------------------------
// LOGGER CENTRAL
// -------------------------------------------------------
function quantyss_log($action, $object = '') {
    global $wpdb;
    $user = wp_get_current_user();

    $wpdb->insert($wpdb->prefix . 'quantyss_logs', [
        'user_id'    => $user->ID ?? 0,
        'username'   => $user->user_login ?? 'system',
        'action'     => sanitize_text_field($action),
        'object'     => sanitize_text_field($object),
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'created_at' => current_time('mysql'),
    ]);

    // Nettoyage automatique — garde 500 entrées max
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}quantyss_logs");
    if ($count > 500) {
        $wpdb->query("DELETE FROM {$wpdb->prefix}quantyss_logs ORDER BY id ASC LIMIT " . ($count - 500));
    }
}

// -------------------------------------------------------
// ÉVÉNEMENTS À LOGGER
// -------------------------------------------------------

// Connexions
function quantyss_log_login($login) {
    quantyss_log('login_success', $login);
}
add_action('wp_login', 'quantyss_log_login');

// Tentatives échouées
function quantyss_log_failed_login($login) {
    quantyss_log('login_failed', $login);
}
add_action('wp_login_failed', 'quantyss_log_failed_login');

// Déconnexions
add_action('wp_logout', fn() => quantyss_log('logout'));

// Modifications d'articles
function quantyss_log_post_save($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    $action = $update ? 'post_updated' : 'post_created';
    quantyss_log($action, $post->post_title);
}
add_action('save_post', 'quantyss_log_post_save', 10, 3);

// Suppressions d'articles
function quantyss_log_post_delete($post_id) {
    $post = get_post($post_id);
    if ($post) quantyss_log('post_deleted', $post->post_title);
}
add_action('before_delete_post', 'quantyss_log_post_delete');

// Installation / activation de plugins
function quantyss_log_plugin_activated($plugin) {
    quantyss_log('plugin_activated', $plugin);
}
add_action('activated_plugin', 'quantyss_log_plugin_activated');

function quantyss_log_plugin_deactivated($plugin) {
    quantyss_log('plugin_deactivated', $plugin);
}
add_action('deactivated_plugin', 'quantyss_log_plugin_deactivated');

// Modifications de profil utilisateur
function quantyss_log_profile_update($user_id) {
    $user = get_userdata($user_id);
    quantyss_log('profile_updated', $user->user_login);
}
add_action('profile_update', 'quantyss_log_profile_update');

// Blocage brute force — 5 tentatives en 5 minutes
function quantyss_brute_force_protection() {
    global $wpdb;
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    $table = $wpdb->prefix . 'quantyss_logs';
    $since = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    $attempts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE action = 'login_failed' AND ip = %s AND created_at > %s",
        $ip, $since
    ));

    if ($attempts >= 5) {
        wp_die(
            '🔒 Trop de tentatives de connexion. Réessayez dans 5 minutes.',
            'Accès bloqué',
            ['response' => 429]
        );
    }
}
add_action('wp_login_failed', 'quantyss_brute_force_protection');

// -------------------------------------------------------
// PAGE ADMIN — LOGS
// -------------------------------------------------------
function quantyss_register_logs_page() {
    add_submenu_page(
        'quantyss-dashboard',
        'Logs de sécurité',
        'Logs',
        'manage_options',
        'quantyss-logs',
        'quantyss_render_logs'
    );
}
add_action('admin_menu', 'quantyss_register_logs_page');

function quantyss_render_logs() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $logs = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}quantyss_logs ORDER BY created_at DESC LIMIT 100"
    );

    $icons = [
        'login_success'       => ['✅', '#10b981'],
        'login_failed'        => ['❌', '#ef4444'],
        'logout'              => ['👋', '#9ca3af'],
        'post_created'        => ['📝', '#6366f1'],
        'post_updated'        => ['✏️', '#f59e0b'],
        'post_deleted'        => ['🗑️', '#ef4444'],
        'plugin_activated'    => ['🔌', '#10b981'],
        'plugin_deactivated'  => ['🔌', '#9ca3af'],
        'profile_updated'     => ['👤', '#f59e0b'],
    ];
    ?>
    <div class="wrap qd-wrap">
        <div class="qd-header">
            <div class="qd-header__logo">Q</div>
            <div>
                <h1>Logs de sécurité</h1>
                <p class="qd-subtitle">100 dernières actions sur le site</p>
            </div>
        </div>

        <div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden;">
            <table class="qd-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Utilisateur</th>
                        <th>Objet</th>
                        <th>IP</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) :
                        $icon  = $icons[$log->action][0] ?? '•';
                        $color = $icons[$log->action][1] ?? '#6b7280';
                    ?>
                        <tr>
                            <td>
                                <span style="color:<?php echo $color; ?>">
                                    <?php echo $icon; ?>
                                    <?php echo esc_html(str_replace('_', ' ', $log->action)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->username); ?></td>
                            <td style="color:#6b7280;font-size:13px;"><?php echo esc_html($log->object); ?></td>
                            <td style="font-family:monospace;font-size:12px;"><?php echo esc_html($log->ip); ?></td>
                            <td style="color:#9ca3af;font-size:12px;">
                                <?php echo date('d/m/Y H:i', strtotime($log->created_at)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}