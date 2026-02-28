<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// CRON — vérification toutes les 5 minutes
// -------------------------------------------------------
function quantyss_add_cron_interval($schedules) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display'  => 'Toutes les 5 minutes',
    ];
    return $schedules;
}
add_filter('cron_schedules', 'quantyss_add_cron_interval');

function quantyss_schedule_uptime_check() {
    if (!wp_next_scheduled('quantyss_uptime_check')) {
        wp_schedule_event(time(), 'every_5_minutes', 'quantyss_uptime_check');
    }
}
add_action('wp', 'quantyss_schedule_uptime_check');

function quantyss_run_uptime_check() {
    $url      = home_url('/');
    $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
    $code     = wp_remote_retrieve_response_code($response);
    $is_up    = ($code >= 200 && $code < 400);
    $now      = current_time('mysql');

    // Historique uptime
    $history  = get_option('quantyss_uptime_history', []);
    $history[] = [
        'status' => $is_up ? 'up' : 'down',
        'code'   => $code,
        'date'   => $now,
    ];

    // Garde 288 entrées = 24h à raison d'1 check/5min
    if (count($history) > 288) {
        $history = array_slice($history, -288);
    }

    update_option('quantyss_uptime_history', $history, false);

    // Alerte email si le site est down
    if (!$is_up) {
        $last_alert = get_option('quantyss_uptime_last_alert', 0);

        // Une alerte max toutes les 30 minutes
        if (time() - $last_alert > 1800) {
            $to      = get_option('admin_email');
            $subject = '🚨 Alerte — ' . get_bloginfo('name') . ' semble hors ligne';
            $body    = "
            <div style='font-family:sans-serif;max-width:480px;'>
                <div style='background:#ef4444;padding:20px;border-radius:12px 12px 0 0;'>
                    <h1 style='color:#fff;margin:0;font-size:18px;'>⚠️ Site potentiellement hors ligne</h1>
                </div>
                <div style='background:#fff;padding:20px;border:1px solid #e5e7eb;border-radius:0 0 12px 12px;'>
                    <p>Le monitoring a détecté une anomalie sur <strong>" . home_url() . "</strong></p>
                    <p>Code HTTP reçu : <strong>$code</strong></p>
                    <p>Heure : <strong>$now</strong></p>
                    <p style='color:#6b7280;font-size:13px;'>Vérifiez votre hébergeur si le problème persiste.</p>
                </div>
            </div>";

            wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
            update_option('quantyss_uptime_last_alert', time());
            quantyss_log('uptime_alert', "Site down — HTTP $code");
        }
    }
}
add_action('quantyss_uptime_check', 'quantyss_run_uptime_check');

// -------------------------------------------------------
// PAGE ADMIN — UPTIME
// -------------------------------------------------------
function quantyss_register_uptime_page() {
    add_submenu_page(
        'quantyss-dashboard',
        'Monitoring Uptime',
        'Uptime',
        'manage_options',
        'quantyss-uptime',
        'quantyss_render_uptime'
    );
}
add_action('admin_menu', 'quantyss_register_uptime_page');

function quantyss_render_uptime() {
    if (!current_user_can('manage_options')) return;

    $history  = get_option('quantyss_uptime_history', []);
    $total    = count($history);
    $up_count = count(array_filter($history, fn($h) => $h['status'] === 'up'));
    $uptime   = $total > 0 ? round(($up_count / $total) * 100, 2) : 100;
    $last     = !empty($history) ? end($history) : null;
    $is_up    = $last ? $last['status'] === 'up' : true;

    // Données pour le graphique (last 48 checks = 4h)
    $chart_data  = array_slice($history, -48);
    $chart_labels = array_map(fn($h) => date('H:i', strtotime($h['date'])), $chart_data);
    $chart_values = array_map(fn($h) => $h['status'] === 'up' ? 1 : 0, $chart_data);
    ?>

    <div class="wrap qd-wrap">
        <div class="qd-header">
            <div class="qd-header__logo" style="background:<?php echo $is_up ? '#10b981' : '#ef4444'; ?>">
                <?php echo $is_up ? '✓' : '✗'; ?>
            </div>
            <div>
                <h1>Monitoring Uptime</h1>
                <p class="qd-subtitle">
                    <?php echo $is_up
                        ? '✅ Site en ligne — dernier check : ' . ($last['date'] ?? '—')
                        : '🚨 Site potentiellement hors ligne'; ?>
                </p>
            </div>
        </div>

        <!-- KPIs -->
        <div class="qd-grid" style="margin-bottom:24px;">
            <div class="qd-card qd-card--primary">
                <div class="qd-card__icon"><?php echo $is_up ? '🟢' : '🔴'; ?></div>
                <div class="qd-card__value"><?php echo $is_up ? 'En ligne' : 'Hors ligne'; ?></div>
                <div class="qd-card__label">Statut actuel</div>
            </div>
            <div class="qd-card">
                <div class="qd-card__icon">📈</div>
                <div class="qd-card__value"><?php echo $uptime; ?>%</div>
                <div class="qd-card__label">Uptime 24h</div>
                <div class="qd-card__sub"><?php echo $total; ?> checks effectués</div>
            </div>
            <div class="qd-card">
                <div class="qd-card__icon">🔍</div>
                <div class="qd-card__value"><?php echo $last['code'] ?? '—'; ?></div>
                <div class="qd-card__label">Dernier code HTTP</div>
            </div>
            <div class="qd-card">
                <div class="qd-card__icon">⏱️</div>
                <div class="qd-card__value">5 min</div>
                <div class="qd-card__label">Intervalle de check</div>
            </div>
        </div>

        <!-- Graphique -->
        <div class="qd-chart-wrap" style="margin-bottom:24px;">
            <h2>Disponibilité — 4 dernières heures</h2>
            <canvas id="uptime-chart" height="60"></canvas>
        </div>

        <!-- Historique -->
        <div class="qd-recent">
            <h2>Derniers événements</h2>
            <table class="qd-table">
                <thead>
                    <tr><th>Statut</th><th>Code HTTP</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse(array_slice($history, -20)) as $entry) : ?>
                        <tr>
                            <td>
                                <?php if ($entry['status'] === 'up') : ?>
                                    <span style="color:#10b981;font-weight:600;">✅ En ligne</span>
                                <?php else : ?>
                                    <span style="color:#ef4444;font-weight:600;">🔴 Hors ligne</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:monospace;"><?php echo esc_html($entry['code']); ?></td>
                            <td style="color:#6b7280;font-size:13px;">
                                <?php echo date('d/m/Y H:i', strtotime($entry['date'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('uptime-chart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_values); ?>,
                    backgroundColor: <?php echo json_encode(
                        array_map(fn($h) => $h['status'] === 'up'
                            ? 'rgba(16,185,129,0.7)'
                            : 'rgba(239,68,68,0.7)',
                        $chart_data)
                    ); ?>,
                    borderWidth: 0,
                    borderRadius: 3,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { display: false, min: 0, max: 1 },
                    x: { ticks: { maxTicksLimit: 12 } }
                }
            }
        });
    });
    </script>
    <?php
}