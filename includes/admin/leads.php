<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// SOUS-MENU
// -------------------------------------------------------
function quantyss_register_leads_page() {
    add_submenu_page(
        'quantyss-dashboard',
        'Leads',
        'Leads',
        'manage_options',
        'quantyss-leads',
        'quantyss_render_leads'
    );
}
add_action('admin_menu', 'quantyss_register_leads_page');

// -------------------------------------------------------
// ASSETS
// -------------------------------------------------------
function quantyss_enqueue_leads_assets($hook) {
    if ($hook !== 'quantyss_page_quantyss-leads') return;
    wp_enqueue_style(
        'quantyss-leads',
        QUANTYSS_URL . 'includes/admin/leads-style.css',
        [],
        QUANTYSS_VERSION
    );
}
add_action('admin_enqueue_scripts', 'quantyss_enqueue_leads_assets');

// -------------------------------------------------------
// MISE À JOUR DU STATUT
// -------------------------------------------------------
function quantyss_update_lead_status() {
    if (
        !isset($_POST['quantyss_lead_nonce']) ||
        !wp_verify_nonce($_POST['quantyss_lead_nonce'], 'quantyss_update_lead') ||
        !current_user_can('manage_options')
    ) return;

    global $wpdb;
    $table     = $wpdb->prefix . 'quantyss_leads';
    $lead_id   = (int) $_POST['lead_id'];
    $status    = sanitize_text_field($_POST['lead_status']);
    $allowed   = ['new', 'in_progress', 'qualified', 'archived'];

    if (in_array($status, $allowed)) {
        $wpdb->update($table, ['status' => $status], ['id' => $lead_id]);
    }
}
add_action('admin_init', 'quantyss_update_lead_status');

// -------------------------------------------------------
// RENDU
// -------------------------------------------------------
function quantyss_render_leads() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $table = $wpdb->prefix . 'quantyss_leads';

    // Filtre statut
    $filter     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    $where      = $filter !== 'all' ? $wpdb->prepare("WHERE status = %s", $filter) : '';
    $leads      = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");
    $counts     = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table GROUP BY status", OBJECT_K);
    $total      = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    // Lead detail
    $lead_detail = null;
    if (isset($_GET['lead'])) {
        $lead_detail = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int)$_GET['lead']));
    }

    $statuses = [
        'new'         => ['label' => 'Nouveau',    'color' => '#6366f1'],
        'in_progress' => ['label' => 'En cours',   'color' => '#f59e0b'],
        'qualified'   => ['label' => 'Qualifié',   'color' => '#10b981'],
        'archived'    => ['label' => 'Archivé',    'color' => '#9ca3af'],
    ];

    $export_url = wp_nonce_url(
        admin_url('admin.php?quantyss_export_leads=1'),
        'quantyss_export_leads'
    );
    ?>

    <div class="wrap ql-wrap">

        <!-- En-tête -->
        <div class="ql-header">
            <div class="ql-header__logo">Q</div>
            <div>
                <h1>Leads <span class="ql-total"><?php echo $total; ?></span></h1>
                <p class="ql-subtitle">Contacts reçus via le formulaire Quantyss</p>
            </div>
            <a href="<?php echo esc_url($export_url); ?>" class="ql-btn-export">
                ⬇ Exporter CSV
            </a>
        </div>

        <!-- Filtres statut -->
        <div class="ql-filters">
            <a href="?page=quantyss-leads" class="ql-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">
                Tous <span><?php echo $total; ?></span>
            </a>
            <?php foreach ($statuses as $key => $s) :
                $count = isset($counts[$key]) ? $counts[$key]->count : 0;
            ?>
                <a href="?page=quantyss-leads&status=<?php echo $key; ?>"
                   class="ql-filter <?php echo $filter === $key ? 'active' : ''; ?>"
                   style="--dot-color:<?php echo $s['color']; ?>">
                    <span class="ql-filter__dot"></span>
                    <?php echo $s['label']; ?> <span><?php echo $count; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="ql-layout">

            <!-- Liste des leads -->
            <div class="ql-list">
                <?php if (empty($leads)) : ?>
                    <div class="ql-empty">Aucun lead pour le moment.</div>
                <?php else : ?>
                    <?php foreach ($leads as $lead) :
                        $s = $statuses[$lead->status] ?? $statuses['new'];
                        $is_active = isset($_GET['lead']) && (int)$_GET['lead'] === (int)$lead->id;
                    ?>
                        <a href="?page=quantyss-leads&lead=<?php echo $lead->id; ?>&status=<?php echo $filter; ?>"
                           class="ql-lead-row <?php echo $is_active ? 'active' : ''; ?>">
                            <div class="ql-lead-row__avatar">
                                <?php echo strtoupper(substr($lead->first_name, 0, 1) . substr($lead->last_name, 0, 1)); ?>
                            </div>
                            <div class="ql-lead-row__info">
                                <strong><?php echo esc_html($lead->first_name . ' ' . $lead->last_name); ?></strong>
                                <span><?php echo esc_html($lead->company ?: $lead->email); ?></span>
                            </div>
                            <div class="ql-lead-row__meta">
                                <span class="ql-status-dot" style="background:<?php echo $s['color']; ?>"></span>
                                <time><?php echo date('d/m/Y', strtotime($lead->created_at)); ?></time>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Détail du lead -->
            <?php if ($lead_detail) :
                $s = $statuses[$lead_detail->status] ?? $statuses['new'];
            ?>
                <div class="ql-detail">
                    <div class="ql-detail__header">
                        <div class="ql-detail__avatar">
                            <?php echo strtoupper(substr($lead_detail->first_name, 0, 1) . substr($lead_detail->last_name, 0, 1)); ?>
                        </div>
                        <div>
                            <h2><?php echo esc_html($lead_detail->first_name . ' ' . $lead_detail->last_name); ?></h2>
                            <span><?php echo esc_html($lead_detail->company ?: '—'); ?></span>
                        </div>
                    </div>

                    <div class="ql-detail__fields">
                        <div class="ql-field">
                            <label>Email</label>
                            <a href="mailto:<?php echo esc_attr($lead_detail->email); ?>">
                                <?php echo esc_html($lead_detail->email); ?>
                            </a>
                        </div>
                        <div class="ql-field">
                            <label>Téléphone</label>
                            <a href="tel:<?php echo esc_attr($lead_detail->phone); ?>">
                                <?php echo esc_html($lead_detail->phone ?: '—'); ?>
                            </a>
                        </div>
                        <div class="ql-field">
                            <label>Reçu le</label>
                            <span><?php echo date('d/m/Y à H:i', strtotime($lead_detail->created_at)); ?></span>
                        </div>
                        <div class="ql-field ql-field--full">
                            <label>Message</label>
                            <p><?php echo nl2br(esc_html($lead_detail->message)); ?></p>
                        </div>
                    </div>

                    <!-- Changer le statut -->
                    <form method="post" class="ql-status-form">
                        <?php wp_nonce_field('quantyss_update_lead', 'quantyss_lead_nonce'); ?>
                        <input type="hidden" name="lead_id" value="<?php echo $lead_detail->id; ?>">
                        <label>Statut du lead</label>
                        <div class="ql-status-btns">
                            <?php foreach ($statuses as $key => $st) : ?>
                                <button type="submit"
                                        name="lead_status"
                                        value="<?php echo $key; ?>"
                                        class="ql-status-btn <?php echo $lead_detail->status === $key ? 'active' : ''; ?>"
                                        style="--btn-color:<?php echo $st['color']; ?>">
                                    <?php echo $st['label']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <a href="mailto:<?php echo esc_attr($lead_detail->email); ?>"
                       class="ql-btn-reply">
                        ✉️ Répondre par email
                    </a>
                </div>

            <?php else : ?>
                <div class="ql-detail ql-detail--empty">
                    <p>Sélectionne un lead pour voir le détail</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
}