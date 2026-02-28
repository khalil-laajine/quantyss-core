<?php
defined('ABSPATH') || exit;

// -------------------------------------------------------
// INTERCEPTION CF7 → STOCKAGE BDD + EMAIL
// -------------------------------------------------------
function quantyss_catch_cf7_submission($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();

    // Mapping des champs CF7 — adapte les noms si besoin
    $first_name = sanitize_text_field($data['first-name'] ?? $data['your-name'] ?? '');
    $last_name  = sanitize_text_field($data['last-name']  ?? '');
    $email      = sanitize_email($data['your-email']      ?? $data['email'] ?? '');
    $phone      = sanitize_text_field($data['your-phone'] ?? $data['phone'] ?? '');
    $company    = sanitize_text_field($data['company']    ?? '');
    $message    = sanitize_textarea_field($data['your-message'] ?? $data['message'] ?? '');

    if (empty($email)) return; // Sécurité minimale

    global $wpdb;
    $table = $wpdb->prefix . 'quantyss_leads';

    // Insertion en BDD
    $wpdb->insert($table, [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'message'    => $message,
        'status'     => 'new',
        'source'     => 'cf7',
        'created_at' => current_time('mysql'),
    ]);

    $lead_id = $wpdb->insert_id;

    // Notification email à la CEO
    quantyss_notify_new_lead($lead_id, [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'message'    => $message,
    ]);
}
add_action('wpcf7_mail_sent', 'quantyss_catch_cf7_submission');


// -------------------------------------------------------
// EMAIL DE NOTIFICATION
// -------------------------------------------------------
function quantyss_notify_new_lead($lead_id, $data) {
    $to      = get_option('admin_email');
    $subject = '🔔 Nouveau lead Quantyss — ' . ($data['company'] ?: $data['first_name'] . ' ' . $data['last_name']);

    $admin_url = admin_url('admin.php?page=quantyss-leads&lead=' . $lead_id);

    $body = "
    <div style='font-family:sans-serif;max-width:560px;margin:0 auto;'>
        <div style='background:#6366f1;padding:24px;border-radius:12px 12px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:20px;'>Nouveau lead reçu</h1>
        </div>
        <div style='background:#fff;padding:24px;border:1px solid #e5e7eb;border-radius:0 0 12px 12px;'>
            <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                <tr><td style='padding:8px 0;color:#6b7280;width:140px;'>Nom</td>
                    <td style='padding:8px 0;font-weight:600;'>{$data['first_name']} {$data['last_name']}</td></tr>
                <tr><td style='padding:8px 0;color:#6b7280;'>Entreprise</td>
                    <td style='padding:8px 0;font-weight:600;'>" . ($data['company'] ?: '—') . "</td></tr>
                <tr><td style='padding:8px 0;color:#6b7280;'>Email</td>
                    <td style='padding:8px 0;'><a href='mailto:{$data['email']}'>{$data['email']}</a></td></tr>
                <tr><td style='padding:8px 0;color:#6b7280;'>Téléphone</td>
                    <td style='padding:8px 0;'>" . ($data['phone'] ?: '—') . "</td></tr>
                <tr><td style='padding:8px 0;color:#6b7280;vertical-align:top;'>Message</td>
                    <td style='padding:8px 0;'>{$data['message']}</td></tr>
            </table>
            <a href='{$admin_url}' style='display:inline-block;margin-top:20px;padding:12px 24px;
               background:#6366f1;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>
               Voir le lead dans WordPress →
            </a>
        </div>
    </div>";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $body, $headers);
}


// -------------------------------------------------------
// EXPORT CSV
// -------------------------------------------------------
function quantyss_export_leads_csv() {
    if (
        !isset($_GET['quantyss_export_leads']) ||
        !current_user_can('manage_options') ||
        !wp_verify_nonce($_GET['_wpnonce'], 'quantyss_export_leads')
    ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'quantyss_leads';
    $leads = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quantyss-leads-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    fputcsv($output, ['ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Entreprise', 'Message', 'Statut', 'Date'], ';');

    foreach ($leads as $lead) {
        fputcsv($output, [
            $lead['id'],
            $lead['first_name'],
            $lead['last_name'],
            $lead['email'],
            $lead['phone'],
            $lead['company'],
            $lead['message'],
            $lead['status'],
            $lead['created_at'],
        ], ';');
    }

    fclose($output);
    exit;
}
add_action('init', 'quantyss_export_leads_csv');