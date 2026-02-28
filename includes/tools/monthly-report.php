<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// CRON — 1er de chaque mois à 8h
// -------------------------------------------------------
function quantyss_schedule_monthly_report() {
    if (!wp_next_scheduled('quantyss_monthly_report')) {
        // Calcul du prochain 1er du mois à 8h
        $next = mktime(8, 0, 0, date('n') + 1, 1, date('Y'));
        wp_schedule_event($next, 'monthly', 'quantyss_monthly_report');
    }
}
add_action('wp', 'quantyss_schedule_monthly_report');

// Ajouter l'intervalle mensuel
function quantyss_add_monthly_interval($schedules) {
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => 'Une fois par mois',
    ];
    return $schedules;
}
add_filter('cron_schedules', 'quantyss_add_monthly_interval');

// Hook principal
add_action('quantyss_monthly_report', 'quantyss_generate_monthly_report');

// -------------------------------------------------------
// GÉNÉRATION DU RAPPORT
// -------------------------------------------------------
function quantyss_generate_monthly_report($manual = false) {
    global $wpdb;

    $month_label  = date_i18n('F Y', strtotime('last month'));
    $month_start  = date('Y-m-01', strtotime('last month'));
    $month_end    = date('Y-m-t', strtotime('last month'));
    $report_date  = date_i18n('d F Y');

    // ---- Collecte des données ----

    // Articles publiés ce mois
    $posts_month = new \WP_Query([
        'post_type'   => 'post',
        'post_status' => 'publish',
        'date_query'  => [['after' => $month_start, 'before' => $month_end, 'inclusive' => true]],
        'fields'      => 'ids',
    ]);

    // Articles par catégorie
    $categories = get_categories(['hide_empty' => true]);
    $cat_data   = [];
    foreach ($categories as $cat) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.term_id = %d AND p.post_status = 'publish'
             AND p.post_date >= %s AND p.post_date <= %s",
            $cat->term_id, $month_start, $month_end . ' 23:59:59'
        ));
        if ($count > 0) $cat_data[$cat->name] = $count;
    }

    // Leads ce mois
    $leads_month = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quantyss_leads
         WHERE created_at >= %s AND created_at <= %s",
        $month_start, $month_end . ' 23:59:59'
    ));

    // Leads par source
    $leads_by_source = $wpdb->get_results($wpdb->prepare(
        "SELECT source, COUNT(*) as count
         FROM {$wpdb->prefix}quantyss_leads
         WHERE created_at >= %s AND created_at <= %s
         GROUP BY source",
        $month_start, $month_end . ' 23:59:59'
    ));

    // Leads par statut (total)
    $leads_by_status = $wpdb->get_results(
        "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}quantyss_leads GROUP BY status"
    );

    // Téléchargements lead magnets
    $downloads_month = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quantyss_magnet_tokens
         WHERE downloaded = 1 AND created_at >= %s AND created_at <= %s",
        $month_start, $month_end . ' 23:59:59'
    ));

    // Uptime moyen du mois
    $uptime_history = get_option('quantyss_uptime_history', []);
    $month_checks   = array_filter($uptime_history, fn($h) =>
        $h['date'] >= $month_start && $h['date'] <= $month_end . ' 23:59:59'
    );
    $up_count    = count(array_filter($month_checks, fn($h) => $h['status'] === 'up'));
    $total_checks = count($month_checks);
    $uptime_pct  = $total_checks > 0 ? round(($up_count / $total_checks) * 100, 2) : 100;

    // Alertes sécurité
    $failed_logins = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quantyss_logs
         WHERE action = 'login_failed' AND created_at >= %s AND created_at <= %s",
        $month_start, $month_end . ' 23:59:59'
    ));

    // Total articles publiés
    $total_posts = wp_count_posts('post')->publish;

    // Derniers articles du mois
    $recent_posts = get_posts([
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'date_query'     => [['after' => $month_start, 'before' => $month_end, 'inclusive' => true]],
    ]);

    // ---- Préparer les données pour le script Python ----
    $data = [
        'site_name'      => get_bloginfo('name'),
        'site_url'       => home_url(),
        'month_label'    => $month_label,
        'report_date'    => $report_date,
        'posts_month'    => $posts_month->found_posts,
        'total_posts'    => $total_posts,
        'cat_data'       => $cat_data,
        'leads_month'    => (int) $leads_month,
        'leads_by_source'=> array_map(fn($r) => ['source' => $r->source, 'count' => $r->count], $leads_by_source),
        'leads_by_status'=> array_map(fn($r) => ['status' => $r->status, 'count' => $r->count], $leads_by_status),
        'downloads'      => (int) $downloads_month,
        'uptime'         => $uptime_pct,
        'failed_logins'  => (int) $failed_logins,
        'recent_posts'   => array_map(fn($p) => [
            'title' => $p->post_title,
            'date'  => get_the_date('d/m/Y', $p),
            'url'   => get_permalink($p),
        ], $recent_posts),
    ];

    // ---- Appel du script Python ----
    $json_data  = escapeshellarg(json_encode($data));
    $script     = QUANTYSS_PATH . 'scripts/generate-report.py';
    $output_dir = wp_upload_dir()['basedir'] . '/quantyss-reports/';

    if (!file_exists($output_dir)) {
        wp_mkdir_p($output_dir);
        file_put_contents($output_dir . '.htaccess', 'Options -Indexes');
    }

    $output_file = $output_dir . 'rapport-' . date('Y-m') . '.pdf';
    $command     = "python3 " . escapeshellarg($script) . " {$json_data} " . escapeshellarg($output_file) . " 2>&1";
    $result      = shell_exec($command);

    if (!file_exists($output_file)) {
        quantyss_log('report_error', 'Échec génération rapport : ' . $result);
        return false;
    }

    // ---- Envoi email ----
    quantyss_send_monthly_report_email($output_file, $data);
    quantyss_log('report_sent', 'Rapport mensuel ' . $month_label . ' envoyé');

    // Sauvegarder le dernier rapport
    update_option('quantyss_last_report', [
        'file'  => $output_file,
        'month' => $month_label,
        'date'  => current_time('mysql'),
    ]);

    return $output_file;
}

// -------------------------------------------------------
// ENVOI EMAIL
// -------------------------------------------------------
function quantyss_send_monthly_report_email($pdf_path, $data) {
    $to      = get_option('admin_email');
    $subject = '📊 Rapport mensuel Quantyss — ' . $data['month_label'];

    $body = "
    <div style='font-family:sans-serif;max-width:560px;margin:0 auto;'>
        <div style='background:#6366f1;padding:28px;border-radius:12px 12px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:20px;'>📊 Rapport mensuel</h1>
            <p style='color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px;'>{$data['month_label']} · {$data['site_name']}</p>
        </div>
        <div style='background:#fff;padding:28px;border:1px solid #e5e7eb;border-radius:0 0 12px 12px;'>
            <p style='color:#374151;'>Bonjour,</p>
            <p style='color:#374151;'>Voici le résumé de l'activité de votre site pour le mois de <strong>{$data['month_label']}</strong>.</p>

            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='background:#f9fafb;'>
                    <td style='padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-size:14px;'>Articles publiés</td>
                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;font-size:18px;color:#6366f1;text-align:right;'>{$data['posts_month']}</td>
                </tr>
                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-size:14px;'>Nouveaux leads</td>
                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;font-size:18px;color:#6366f1;text-align:right;'>{$data['leads_month']}</td>
                </tr>
                <tr style='background:#f9fafb;'>
                    <td style='padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-size:14px;'>Téléchargements guides</td>
                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;font-size:18px;color:#6366f1;text-align:right;'>{$data['downloads']}</td>
                </tr>
                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-size:14px;'>Disponibilité du site</td>
                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;font-size:18px;color:#10b981;text-align:right;'>{$data['uptime']}%</td>
                </tr>
            </table>

            <p style='color:#374151;'>Le rapport complet est joint à cet email en PDF.</p>
            <p style='color:#9ca3af;font-size:13px;margin-top:24px;'>
                Ce rapport est généré automatiquement par Quantyss Core.
            </p>
        </div>
    </div>";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = [$pdf_path];

    wp_mail($to, $subject, $body, $headers, $attachments);
}

// -------------------------------------------------------
// DÉCLENCHEMENT MANUEL (bouton admin)
// -------------------------------------------------------
function quantyss_manual_report_trigger() {
    if (
        !isset($_POST['quantyss_generate_report']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'quantyss_manual_report') ||
        !current_user_can('manage_options')
    ) return;

    $result = quantyss_generate_monthly_report(true);
    $status = $result ? 'success' : 'error';

    wp_safe_redirect(admin_url('admin.php?page=quantyss-dashboard&report=' . $status));
    exit;
}
add_action('admin_init', 'quantyss_manual_report_trigger');