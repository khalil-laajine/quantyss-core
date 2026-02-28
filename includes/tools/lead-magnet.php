<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// SHORTCODE — BOUTON DANS LES ARTICLES
// [lead_magnet id="1" label="Télécharger le guide GTM"]
// -------------------------------------------------------
function quantyss_magnet_button_shortcode($atts) {
    $atts = shortcode_atts([
        'id'    => 0,
        'label' => 'Télécharger le guide gratuit',
    ], $atts, 'lead_magnet');

    $magnet_id = (int) $atts['id'];
    $magnet    = quantyss_get_magnet($magnet_id);
    if (!$magnet) return '';

    $page_url = add_query_arg('magnet', $magnet_id, get_permalink(
        get_option('quantyss_magnet_page_id')
    ));

    wp_enqueue_style('quantyss-lead-magnet');

    ob_start(); ?>
    <div class="qlm-cta-block">
        <div class="qlm-cta-block__icon">📘</div>
        <div class="qlm-cta-block__content">
            <strong><?php echo esc_html($magnet->title); ?></strong>
            <span><?php echo esc_html($magnet->subtitle); ?></span>
        </div>
        <a href="<?php echo esc_url($page_url); ?>" class="qlm-cta-block__btn">
            <?php echo esc_html($atts['label']); ?> →
        </a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lead_magnet', 'quantyss_magnet_button_shortcode');

// -------------------------------------------------------
// PAGE DE TÉLÉCHARGEMENT DÉDIÉE
// Shortcode : [lead_magnet_form id="1"]
// -------------------------------------------------------
function quantyss_magnet_form_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts, 'lead_magnet_form');

    $magnet_id = (int) ($_GET['magnet'] ?? $atts['id']);
    $magnet    = quantyss_get_magnet($magnet_id);
    if (!$magnet) return '<p>Ressource introuvable.</p>';

    wp_enqueue_style('quantyss-lead-magnet');

    // Étape 2 — confirmation après soumission
    if (isset($_GET['qlm_sent'])) {
        ob_start(); ?>
        <div class="qlm-form qlm-form--success">
            <div class="qlm-success-icon">✉️</div>
            <h2>Vérifiez votre boîte email !</h2>
            <p>Nous venons de vous envoyer un lien de téléchargement sécurisé.<br>
               Il est valable <strong>24 heures</strong>.</p>
            <p class="qlm-hint">Pensez à vérifier vos spams si vous ne le recevez pas.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start(); ?>
    <div class="qlm-form">
        <div class="qlm-form__header">
            <div class="qlm-form__badge">Guide gratuit</div>
            <h2><?php echo esc_html($magnet->title); ?></h2>
            <p><?php echo esc_html($magnet->subtitle); ?></p>
        </div>

        <form method="post" class="qlm-form__body" id="qlm-form">
            <?php wp_nonce_field('quantyss_magnet_submit', 'qlm_nonce'); ?>
            <input type="hidden" name="qlm_magnet_id" value="<?php echo $magnet_id; ?>">

            <div class="qlm-field">
                <label for="qlm_email">Votre adresse email professionnelle</label>
                <input type="email"
                       name="qlm_email"
                       id="qlm_email"
                       placeholder="vous@entreprise.com"
                       required />
            </div>

            <button type="submit" name="qlm_submit" class="qlm-btn">
                Recevoir le guide gratuit →
            </button>

            <p class="qlm-privacy">
                🔒 Pas de spam. Votre email ne sera jamais partagé.
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lead_magnet_form', 'quantyss_magnet_form_shortcode');

// -------------------------------------------------------
// TRAITEMENT DU FORMULAIRE
// -------------------------------------------------------
function quantyss_process_magnet_form() {
    if (!isset($_POST['qlm_submit'])) return;

    if (
        !isset($_POST['qlm_nonce']) ||
        !wp_verify_nonce($_POST['qlm_nonce'], 'quantyss_magnet_submit')
    ) return;

    $email     = sanitize_email($_POST['qlm_email'] ?? '');
    $magnet_id = (int) ($_POST['qlm_magnet_id'] ?? 0);
    $magnet    = quantyss_get_magnet($magnet_id);

    if (!is_email($email) || !$magnet) return;

    global $wpdb;

    // Génération du token sécurisé
    $token      = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $wpdb->insert($wpdb->prefix . 'quantyss_magnet_tokens', [
        'email'      => $email,
        'token'      => $token,
        'magnet_id'  => $magnet_id,
        'expires_at' => $expires_at,
        'created_at' => current_time('mysql'),
    ]);

    // Ajout dans les leads
    $wpdb->insert($wpdb->prefix . 'quantyss_leads', [
        'first_name' => '',
        'last_name'  => '',
        'email'      => $email,
        'phone'      => '',
        'company'    => '',
        'message'    => 'Téléchargement : ' . $magnet->title,
        'status'     => 'new',
        'source'     => 'lead_magnet',
        'created_at' => current_time('mysql'),
    ]);

    // Envoi de l'email avec le lien
    quantyss_send_magnet_email($email, $token, $magnet);

    // Redirection confirmation
    $redirect = add_query_arg([
        'magnet'   => $magnet_id,
        'qlm_sent' => '1',
    ], get_permalink());

    wp_safe_redirect($redirect);
    exit;
}
add_action('template_redirect', 'quantyss_process_magnet_form');

// -------------------------------------------------------
// TÉLÉCHARGEMENT SÉCURISÉ
// -------------------------------------------------------
function quantyss_handle_magnet_download() {
    if (!isset($_GET['qlm_download'])) return;

    $token = sanitize_text_field($_GET['qlm_download']);

    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quantyss_magnet_tokens
         WHERE token = %s AND expires_at > %s",
        $token, current_time('mysql')
    ));

    if (!$row) {
        wp_die('Ce lien a expiré ou est invalide. Veuillez soumettre à nouveau votre email.', 'Lien invalide', ['response' => 403]);
    }

    $magnet = quantyss_get_magnet($row->magnet_id);
    if (!$magnet) wp_die('Ressource introuvable.', 'Erreur', ['response' => 404]);

    $file_path = QUANTYSS_PATH . 'assets/pdfs/' . $magnet->filename;
    if (!file_exists($file_path)) {
        wp_die('Le fichier est temporairement indisponible.', 'Erreur', ['response' => 404]);
    }

    // Marquer comme téléchargé
    $wpdb->update(
        $wpdb->prefix . 'quantyss_magnet_tokens',
        ['downloaded' => 1],
        ['token' => $token]
    );

    quantyss_log('magnet_downloaded', $magnet->title . ' — ' . $row->email);

    // Servir le fichier
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $magnet->filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($file_path);
    exit;
}
add_action('template_redirect', 'quantyss_handle_magnet_download');

// -------------------------------------------------------
// ENVOI DE L'EMAIL
// -------------------------------------------------------
function quantyss_send_magnet_email($email, $token, $magnet) {
    $download_url = add_query_arg('qlm_download', $token, home_url('/'));
    $subject      = '📘 Votre guide : ' . $magnet->title;

    $body = "
    <div style='font-family:sans-serif;max-width:560px;margin:0 auto;'>
        <div style='background:#6366f1;padding:28px;border-radius:12px 12px 0 0;text-align:center;'>
            <h1 style='color:#fff;margin:0;font-size:22px;'>📘 Votre guide est prêt</h1>
        </div>
        <div style='background:#fff;padding:28px;border:1px solid #e5e7eb;border-radius:0 0 12px 12px;'>
            <p style='font-size:16px;color:#374151;'>Merci pour votre intérêt !</p>
            <p style='color:#374151;'>Votre guide <strong>{$magnet->title}</strong>
               vous attend. Cliquez sur le bouton ci-dessous pour le télécharger :</p>

            <div style='text-align:center;margin:28px 0;'>
                <a href='{$download_url}'
                   style='display:inline-block;padding:14px 32px;background:#6366f1;
                          color:#fff;border-radius:8px;text-decoration:none;
                          font-weight:700;font-size:16px;'>
                    Télécharger le guide →
                </a>
            </div>

            <p style='color:#9ca3af;font-size:13px;'>
                Ce lien est valable 24 heures. Après expiration, rendez-vous sur
                <a href='" . home_url() . "' style='color:#6366f1;'>" . get_bloginfo('name') . "</a>
                pour en obtenir un nouveau.
            </p>
            <hr style='border:none;border-top:1px solid #f3f4f6;margin:20px 0;'>
            <p style='color:#9ca3af;font-size:12px;text-align:center;'>
                " . get_bloginfo('name') . " · " . home_url() . "
            </p>
        </div>
    </div>";

    wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
}

// -------------------------------------------------------
// HELPER — RÉCUPÉRER UN MAGNET
// -------------------------------------------------------
function quantyss_get_magnet($id) {
    $magnets = get_option('quantyss_magnets', []);
    foreach ($magnets as $m) {
        if ((int)$m['id'] === (int)$id) return (object)$m;
    }
    return null;
}

// -------------------------------------------------------
// ENQUEUE CSS
// -------------------------------------------------------
function quantyss_enqueue_magnet_style() {
    wp_register_style(
        'quantyss-lead-magnet',
        QUANTYSS_URL . 'assets/css/lead-magnet.css',
        [],
        QUANTYSS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'quantyss_enqueue_magnet_style');