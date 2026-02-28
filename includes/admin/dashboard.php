<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// ENREGISTREMENT DE LA PAGE ADMIN
// -------------------------------------------------------
function quantyss_register_dashboard() {
    add_menu_page(
        'Quantyss Dashboard',           // Titre de la page
        'Quantyss',                      // Titre dans le menu
        'manage_options',                // Capacité requise
        'quantyss-dashboard',            // Slug
        'quantyss_render_dashboard',     // Fonction de rendu
        'dashicons-chart-bar',           // Icône
        3                                // Position dans le menu
    );
}
add_action('admin_menu', 'quantyss_register_dashboard');

// -------------------------------------------------------
// CSS DU DASHBOARD
// -------------------------------------------------------
function quantyss_enqueue_dashboard_assets($hook) {
    if ($hook !== 'toplevel_page_quantyss-dashboard') return;

    wp_enqueue_style(
        'quantyss-dashboard',
        QUANTYSS_URL . 'includes/admin/dashboard-style.css',
        [],
        QUANTYSS_VERSION
    );

    // Chart.js pour les graphiques
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        [],
        '4',
        true
    );
}
add_action('admin_enqueue_scripts', 'quantyss_enqueue_dashboard_assets');

// -------------------------------------------------------
// RÉCUPÉRATION DES KPIs
// -------------------------------------------------------
function quantyss_get_kpis() {
    // Articles publiés
    $posts_count = wp_count_posts('post');

    // Commentaires
    $comments = wp_count_comments();

    // Utilisateurs
    $users = count_users();

    // Articles ce mois-ci
    $this_month = new \WP_Query([
        'post_type'   => 'post',
        'post_status' => 'publish',
        'date_query'  => [[
            'after' => date('Y-m-01'),
        ]],
        'fields'      => 'ids',
    ]);

    // Articles des 6 derniers mois (pour le graphique)
    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end   = date('Y-m-t', strtotime("-$i months"));
        $label       = date_i18n('M Y', strtotime($month_start));

        $q = new \WP_Query([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'date_query'  => [[
                'after'  => $month_start,
                'before' => $month_end,
                'inclusive' => true,
            ]],
            'fields' => 'ids',
        ]);

        $monthly_data[] = [
            'label' => $label,
            'count' => $q->found_posts,
        ];
    }

    // Pages publiées
    $pages_count = wp_count_posts('page');

    return [
        'posts_published'  => $posts_count->publish,
        'posts_draft'      => $posts_count->draft,
        'posts_this_month' => $this_month->found_posts,
        'comments_total'   => $comments->total_comments,
        'comments_pending' => $comments->moderated,
        'users_total'      => $users['total_users'],
        'pages_published'  => $pages_count->publish,
        'monthly_data'     => $monthly_data,
    ];
}

// -------------------------------------------------------
// RENDU DU DASHBOARD
// -------------------------------------------------------
function quantyss_render_dashboard() {
    if (!current_user_can('manage_options')) return;

    $kpis = quantyss_get_kpis();
    $monthly_labels = array_column($kpis['monthly_data'], 'label');
    $monthly_counts = array_column($kpis['monthly_data'], 'count');
    ?>

    <div class="wrap qd-wrap">

        <!-- En-tête -->
        <div class="qd-header">
            <div class="qd-header__logo">Q</div>
            <div>
                <h1>Quantyss Dashboard</h1>
                <p class="qd-subtitle">Bonjour <?php echo esc_html(wp_get_current_user()->display_name); ?> — <?php echo date_i18n('l j F Y'); ?></p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="qd-grid">

            <div class="qd-card qd-card--primary">
                <div class="qd-card__icon">📝</div>
                <div class="qd-card__value"><?php echo $kpis['posts_published']; ?></div>
                <div class="qd-card__label">Articles publiés</div>
                <div class="qd-card__sub"><?php echo $kpis['posts_this_month']; ?> ce mois-ci</div>
            </div>

            <div class="qd-card">
                <div class="qd-card__icon">📄</div>
                <div class="qd-card__value"><?php echo $kpis['pages_published']; ?></div>
                <div class="qd-card__label">Pages publiées</div>
                <div class="qd-card__sub"><?php echo $kpis['posts_draft']; ?> brouillons</div>
            </div>

            <div class="qd-card">
                <div class="qd-card__icon">💬</div>
                <div class="qd-card__value"><?php echo $kpis['comments_total']; ?></div>
                <div class="qd-card__label">Commentaires</div>
                <div class="qd-card__sub"><?php echo $kpis['comments_pending']; ?> en attente</div>
            </div>

            <div class="qd-card">
                <div class="qd-card__icon">👥</div>
                <div class="qd-card__value"><?php echo $kpis['users_total']; ?></div>
                <div class="qd-card__label">Utilisateurs</div>
                <div class="qd-card__sub">inscrits</div>
            </div>

        </div>

        <!-- Graphique -->
        <div class="qd-chart-wrap">
            <h2>Publications — 6 derniers mois</h2>
            <canvas id="qd-chart" height="80"></canvas>
        </div>

        <!-- Derniers articles -->
        <div class="qd-recent">
            <h2>Derniers articles publiés</h2>
            <table class="qd-table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recent = new \WP_Query([
                        'post_type'      => 'post',
                        'post_status'    => 'publish',
                        'posts_per_page' => 8,
                    ]);
                    while ($recent->have_posts()) : $recent->the_post();
                        $cats = get_the_category();
                        $cat_name = $cats ? $cats[0]->name : '—';
                    ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td><span class="qd-badge"><?php echo esc_html($cat_name); ?></span></td>
                            <td><?php echo get_the_date(); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>" class="qd-action">Éditer</a>
                                <a href="<?php the_permalink(); ?>" class="qd-action qd-action--view" target="_blank">Voir</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('qd-chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Articles publiés',
                    data: <?php echo json_encode($monthly_counts); ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    });
    </script>

    <?php
}