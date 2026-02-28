<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// SOUS-MENU
// -------------------------------------------------------
function quantyss_register_magnet_admin() {
    add_submenu_page(
        'quantyss-dashboard',
        'Lead Magnets',
        'Lead Magnets',
        'manage_options',
        'quantyss-magnets',
        'quantyss_render_magnet_admin'
    );
}
add_action('admin_menu', 'quantyss_register_magnet_admin');

// -------------------------------------------------------
// SAUVEGARDE
// -------------------------------------------------------
function quantyss_save_magnet() {
    if (
        !isset($_POST['qlm_admin_nonce']) ||
        !wp_verify_nonce($_POST['qlm_admin_nonce'], 'quantyss_save_magnet') ||
        !current_user_can('manage_options')
    ) return;

    $magnets   = get_option('quantyss_magnets', []);
    $is_edit   = isset($_POST['magnet_id']) && $_POST['magnet_id'] !== '';
    $magnet_id = $is_edit ? (int)$_POST['magnet_id'] : time();

    $entry = [
        'id'       => $magnet_id,
        'title'    => sanitize_text_field($_POST['magnet_title']),
        'subtitle' => sanitize_text_field($_POST['magnet_subtitle']),
        'filename' => '',
    ];

    // Upload du PDF
    if (!empty($_FILES['magnet_pdf']['name'])) {
        $file     = $_FILES['magnet_pdf'];
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (strtolower($ext) !== 'pdf') {
            wp_die('Seuls les fichiers PDF sont acceptés.');
        }

        $filename    = 'magnet-' . $magnet_id . '.pdf';
        $upload_path = QUANTYSS_PATH . 'assets/pdfs/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $entry['filename'] = $filename;
        }
    } elseif ($is_edit) {
        // Garder l'ancien fichier si pas de nouvel upload
        foreach ($magnets as $m) {
            if ((int)$m['id'] === $magnet_id) {
                $entry['filename'] = $m['filename'];
                break;
            }
        }
    }

    // Mise à jour ou ajout
    if ($is_edit) {
        foreach ($magnets as &$m) {
            if ((int)$m['id'] === $magnet_id) { $m = $entry; break; }
        }
    } else {
        $magnets[] = $entry;
    }

    update_option('quantyss_magnets', $magnets);

    wp_safe_redirect(admin_url('admin.php?page=quantyss-magnets&saved=1'));
    exit;
}
add_action('admin_init', 'quantyss_save_magnet');

// -------------------------------------------------------
// SUPPRESSION
// -------------------------------------------------------
function quantyss_delete_magnet() {
    if (
        !isset($_GET['qlm_delete']) ||
        !isset($_GET['_wpnonce']) ||
        !wp_verify_nonce($_GET['_wpnonce'], 'qlm_delete_' . $_GET['qlm_delete']) ||
        !current_user_can('manage_options')
    ) return;

    $id      = (int)$_GET['qlm_delete'];
    $magnets = get_option('quantyss_magnets', []);
    $magnets = array_filter($magnets, fn($m) => (int)$m['id'] !== $id);
    update_option('quantyss_magnets', array_values($magnets));

    wp_safe_redirect(admin_url('admin.php?page=quantyss-magnets&deleted=1'));
    exit;
}
add_action('admin_init', 'quantyss_delete_magnet');

// -------------------------------------------------------
// RENDU
// -------------------------------------------------------
function quantyss_render_magnet_admin() {
    if (!current_user_can('manage_options')) return;

    $magnets    = get_option('quantyss_magnets', []);
    $edit_magnet = null;

    if (isset($_GET['edit'])) {
        foreach ($magnets as $m) {
            if ((int)$m['id'] === (int)$_GET['edit']) {
                $edit_magnet = $m;
                break;
            }
        }
    }

    global $wpdb;
    ?>
    <div class="wrap qd-wrap">

        <div class="qd-header">
            <div class="qd-header__logo">Q</div>
            <div>
                <h1>Lead Magnets</h1>
                <p class="qd-subtitle">Gérez vos guides et livres blancs</p>
            </div>
        </div>

        <?php if (isset($_GET['saved']))   echo '<div class="notice notice-success"><p>✅ Sauvegardé.</p></div>'; ?>
        <?php if (isset($_GET['deleted'])) echo '<div class="notice notice-success"><p>🗑️ Supprimé.</p></div>'; ?>

        <div style="display:grid;grid-template-columns:1fr 400px;gap:24px;">

            <!-- Liste des magnets -->
            <div>
                <?php if (empty($magnets)) : ?>
                    <div style="background:#fff;padding:40px;border-radius:12px;text-align:center;color:#9ca3af;box-shadow:0 1px 4px rgba(0,0,0,.08);">
                        Aucun lead magnet. Créez le premier ci-contre.
                    </div>
                <?php else : ?>
                    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden;">
                        <table class="qd-table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Fichier</th>
                                    <th>Téléchargements</th>
                                    <th>Shortcodes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($magnets as $m) :
                                    $dl_count = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$wpdb->prefix}quantyss_magnet_tokens
                                         WHERE magnet_id = %d AND downloaded = 1",
                                        $m['id']
                                    ));
                                    $delete_url = wp_nonce_url(
                                        admin_url('admin.php?page=quantyss-magnets&qlm_delete=' . $m['id']),
                                        'qlm_delete_' . $m['id']
                                    );
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($m['title']); ?></strong><br>
                                            <span style="color:#9ca3af;font-size:12px;"><?php echo esc_html($m['subtitle']); ?></span>
                                        </td>
                                        <td style="font-size:12px;color:#6b7280;">
                                            <?php echo $m['filename'] ? '✅ ' . esc_html($m['filename']) : '⚠️ Aucun PDF'; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <strong style="color:#6366f1;"><?php echo $dl_count; ?></strong>
                                        </td>
                                        <td style="font-size:11px;">
                                            <code>[lead_magnet id="<?php echo $m['id']; ?>"]</code><br>
                                            <code>[lead_magnet_form id="<?php echo $m['id']; ?>"]</code>
                                        </td>
                                        <td>
                                            <a href="?page=quantyss-magnets&edit=<?php echo $m['id']; ?>"
                                               class="qd-action">Éditer</a>
                                            <a href="<?php echo esc_url($delete_url); ?>"
                                               class="qd-action"
                                               style="color:#ef4444;background:#fee2e2;"
                                               onclick="return confirm('Supprimer ce lead magnet ?')">
                                               Supprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formulaire création / édition -->
            <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);height:fit-content;">
                <h2 style="margin-top:0;">
                    <?php echo $edit_magnet ? '✏️ Modifier' : '➕ Nouveau lead magnet'; ?>
                </h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('quantyss_save_magnet', 'qlm_admin_nonce'); ?>
                    <?php if ($edit_magnet) : ?>
                        <input type="hidden" name="magnet_id" value="<?php echo $edit_magnet['id']; ?>">
                    <?php endif; ?>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:6px;">
                            Titre du guide
                        </label>
                        <input type="text" name="magnet_title"
                               value="<?php echo esc_attr($edit_magnet['title'] ?? ''); ?>"
                               placeholder="Go-to-market : le guide pour les startups tech"
                               style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;"
                               required />
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:6px;">
                            Sous-titre / accroche
                        </label>
                        <input type="text" name="magnet_subtitle"
                               value="<?php echo esc_attr($edit_magnet['subtitle'] ?? ''); ?>"
                               placeholder="Le guide complet pour lancer votre produit tech sur le marché"
                               style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;" />
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:6px;">
                            Fichier PDF
                        </label>
                        <?php if (!empty($edit_magnet['filename'])) : ?>
                            <p style="font-size:12px;color:#10b981;margin:0 0 6px;">
                                ✅ <?php echo esc_html($edit_magnet['filename']); ?> (laisser vide pour conserver)
                            </p>
                        <?php endif; ?>
                        <input type="file" name="magnet_pdf" accept=".pdf" />
                    </div>

                    <button type="submit"
                            style="width:100%;padding:12px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;">
                        <?php echo $edit_magnet ? 'Mettre à jour' : 'Créer le lead magnet'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}