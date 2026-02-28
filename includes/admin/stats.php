<?php
defined('ABSPATH') || exit;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;

// -------------------------------------------------------
// SOUS-MENU DANS QUANTYSS
// -------------------------------------------------------
function quantyss_register_stats_page() {
    add_submenu_page(
        'quantyss-dashboard',
        'Statistiques GA4',
        'Statistiques',
        'manage_options',
        'quantyss-stats',
        'quantyss_render_stats'
    );
}
add_action('admin_menu', 'quantyss_register_stats_page');

// -------------------------------------------------------
// ASSETS
// -------------------------------------------------------
function quantyss_enqueue_stats_assets($hook) {
    if ($hook !== 'quantyss_page_quantyss-stats') return;

    wp_enqueue_style(
        'quantyss-stats',
        QUANTYSS_URL . 'includes/admin/stats-style.css',
        [],
        QUANTYSS_VERSION
    );

    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        [],
        '4',
        true
    );
}
add_action('admin_enqueue_scripts', 'quantyss_enqueue_stats_assets');

// -------------------------------------------------------
// SAUVEGARDE DES PARAMÈTRES GA4
// -------------------------------------------------------
function quantyss_save_ga4_settings() {
    if (
        !isset($_POST['quantyss_ga4_nonce']) ||
        !wp_verify_nonce($_POST['quantyss_ga4_nonce'], 'quantyss_save_ga4')
    ) return;

    if (!current_user_can('manage_options')) return;

    if (isset($_POST['ga4_property_id'])) {
        update_option('quantyss_ga4_property_id', sanitize_text_field($_POST['ga4_property_id']));
    }

    if (isset($_FILES['ga4_credentials']) && $_FILES['ga4_credentials']['size'] > 0) {
        $upload = wp_upload_bits(
            'quantyss-ga4-credentials.json',
            null,
            file_get_contents($_FILES['ga4_credentials']['tmp_name'])
        );
        if (!$upload['error']) {
            update_option('quantyss_ga4_credentials_path', $upload['file']);
        }
    }
}
add_action('admin_init', 'quantyss_save_ga4_settings');

// -------------------------------------------------------
// RÉCUPÉRATION DES DONNÉES GA4
// -------------------------------------------------------
function quantyss_get_ga4_data($period = '30daysAgo') {
    $property_id   = get_option('quantyss_ga4_property_id');
    $credentials   = get_option('quantyss_ga4_credentials_path');

    if (!$property_id || !$credentials || !file_exists($credentials)) {
        return ['error' => 'Configuration GA4 incomplète.'];
    }

    try {
        $client = new BetaAnalyticsDataClient([
            'credentials' => $credentials,
        ]);

        // --- Sessions & utilisateurs sur la période ---
        $overview = $client->runReport([
            'property'   => "properties/$property_id",
            'dateRanges' => [new DateRange(['start_date' => $period, 'end_date' => 'today'])],
            'metrics'    => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'totalUsers']),
                new Metric(['name' => 'bounceRate']),
                new Metric(['name' => 'averageSessionDuration']),
            ],
        ]);

        // --- Sessions par jour (graphique) ---
        $daily = $client->runReport([
            'property'   => "properties/$property_id",
            'dateRanges' => [new DateRange(['start_date' => '30daysAgo', 'end_date' => 'today'])],
            'dimensions' => [new Dimension(['name' => 'date'])],
            'metrics'    => [new Metric(['name' => 'sessions'])],
            'orderBys'   => [new OrderBy([
                'dimension' => new OrderBy\DimensionOrderBy([
                    'dimension_name' => 'date',
                    'order_type'     => OrderBy\DimensionOrderBy\OrderType::ALPHANUMERIC,
                ]),
            ])],
        ]);

        // --- Sources de trafic ---
        $sources = $client->runReport([
            'property'   => "properties/$property_id",
            'dateRanges' => [new DateRange(['start_date' => $period, 'end_date' => 'today'])],
            'dimensions' => [new Dimension(['name' => 'sessionDefaultChannelGroup'])],
            'metrics'    => [new Metric(['name' => 'sessions'])],
            'orderBys'   => [new OrderBy([
                'metric'  => new MetricOrderBy(['metric_name' => 'sessions']),
                'desc'    => true,
            ])],
            'limit' => 6,
        ]);

        // Formatage
        $overview_row = $overview->getRows()[0]->getMetricValues();
        $data = [
            'sessions'         => $overview_row[0]->getValue(),
            'users'            => $overview_row[1]->getValue(),
            'bounce_rate'      => round((float)$overview_row[2]->getValue() * 100, 1) . '%',
            'avg_duration'     => gmdate('i\m s\s', (int)$overview_row[3]->getValue()),
            'daily_labels'     => [],
            'daily_sessions'   => [],
            'source_labels'    => [],
            'source_sessions'  => [],
        ];

        foreach ($daily->getRows() as $row) {
            $date = $row->getDimensionValues()[0]->getValue();
            $data['daily_labels'][]   = date('d/m', strtotime($date));
            $data['daily_sessions'][] = (int)$row->getMetricValues()[0]->getValue();
        }

        foreach ($sources->getRows() as $row) {
            $data['source_labels'][]   = $row->getDimensionValues()[0]->getValue();
            $data['source_sessions'][] = (int)$row->getMetricValues()[0]->getValue();
        }

        return $data;

    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// -------------------------------------------------------
// RENDU DE LA PAGE
// -------------------------------------------------------
function quantyss_render_stats() {
    if (!current_user_can('manage_options')) return;

    $property_id = get_option('quantyss_ga4_property_id');
    $credentials = get_option('quantyss_ga4_credentials_path');
    $configured  = $property_id && $credentials && file_exists($credentials);

    $period  = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30daysAgo';
    $data    = $configured ? quantyss_get_ga4_data($period) : null;
    $periods = [
        '7daysAgo'  => '7 derniers jours',
        '30daysAgo' => '30 derniers jours',
        '90daysAgo' => '90 derniers jours',
    ];
    ?>

    <div class="wrap qs-wrap">

        <!-- En-tête -->
        <div class="qs-header">
            <div class="qs-header__logo">Q</div>
            <div>
                <h1>Statistiques GA4</h1>
                <p class="qs-subtitle">Données en direct depuis Google Analytics</p>
            </div>
            <?php if ($configured) : ?>
                <div class="qs-period-selector">
                    <?php foreach ($periods as $val => $label) : ?>
                        <a href="?page=quantyss-stats&period=<?php echo $val; ?>"
                           class="qs-period-btn <?php echo $period === $val ? 'active' : ''; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$configured) : ?>
            <!-- Formulaire de configuration -->
            <div class="qs-config-card">
                <h2>⚙️ Configuration GA4</h2>
                <p>Renseigne ton Property ID et uploade le fichier JSON de credentials de ton compte de service Google.</p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('quantyss_save_ga4', 'quantyss_ga4_nonce'); ?>
                    <div class="qs-field">
                        <label for="ga4_property_id">Property ID GA4</label>
                        <input type="text"
                               name="ga4_property_id"
                               id="ga4_property_id"
                               value="<?php echo esc_attr($property_id); ?>"
                               placeholder="ex: 123456789" />
                    </div>
                    <div class="qs-field">
                        <label for="ga4_credentials">Fichier credentials JSON</label>
                        <input type="file" name="ga4_credentials" id="ga4_credentials" accept=".json" />
                        <?php if ($credentials) : ?>
                            <p class="qs-hint">✅ Fichier déjà uploadé</p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="qs-btn-save">Sauvegarder</button>
                </form>
            </div>

        <?php elseif (isset($data['error'])) : ?>
            <div class="qs-error">❌ <?php echo esc_html($data['error']); ?></div>

        <?php else : ?>

            <!-- KPI Cards -->
            <div class="qs-grid">
                <div class="qs-card qs-card--primary">
                    <div class="qs-card__icon">📊</div>
                    <div class="qs-card__value"><?php echo number_format($data['sessions'], 0, ',', ' '); ?></div>
                    <div class="qs-card__label">Sessions</div>
                </div>
                <div class="qs-card">
                    <div class="qs-card__icon">👤</div>
                    <div class="qs-card__value"><?php echo number_format($data['users'], 0, ',', ' '); ?></div>
                    <div class="qs-card__label">Utilisateurs</div>
                </div>
                <div class="qs-card">
                    <div class="qs-card__icon">↩️</div>
                    <div class="qs-card__value"><?php echo $data['bounce_rate']; ?></div>
                    <div class="qs-card__label">Taux de rebond</div>
                </div>
                <div class="qs-card">
                    <div class="qs-card__icon">⏱️</div>
                    <div class="qs-card__value"><?php echo $data['avg_duration']; ?></div>
                    <div class="qs-card__label">Durée moy. session</div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="qs-charts">

                <!-- Sessions par jour -->
                <div class="qs-chart-wrap qs-chart-wrap--large">
                    <h2>Sessions — 30 derniers jours</h2>
                    <canvas id="qs-daily-chart" height="80"></canvas>
                </div>

                <!-- Sources de trafic -->
                <div class="qs-chart-wrap">
                    <h2>Sources de trafic</h2>
                    <canvas id="qs-sources-chart" height="200"></canvas>
                    <ul class="qs-legend">
                        <?php
                        $colors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444'];
                        foreach ($data['source_labels'] as $i => $label) :
                        ?>
                            <li>
                                <span class="qs-legend__dot" style="background:<?php echo $colors[$i % count($colors)]; ?>"></span>
                                <?php echo esc_html($label); ?>
                                <strong><?php echo $data['source_sessions'][$i]; ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Sessions par jour
                new Chart(document.getElementById('qs-daily-chart'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($data['daily_labels']); ?>,
                        datasets: [{
                            label: 'Sessions',
                            data: <?php echo json_encode($data['daily_sessions']); ?>,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });

                // Sources de trafic
                new Chart(document.getElementById('qs-sources-chart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($data['source_labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($data['source_sessions']); ?>,
                            backgroundColor: <?php echo json_encode($colors); ?>,
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        cutout: '65%',
                    }
                });
            });
            </script>

        <?php endif; ?>

    </div>
    <?php
}