<?php
/**
 * Plugin Name: Financial Account Application Portal
 * Description: Secure Account Application portal with Admin Management and AI Email Notifications.
 * Version: 5.5
 * Author: AccountSelectr
 */

// 1. Database Setup
register_activation_hook(__FILE__, 'faap_setup_database');
function faap_setup_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_apps = $wpdb->prefix . 'faap_submissions';
    $sql_apps = "CREATE TABLE IF NOT EXISTS $table_apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('personal', 'business') NOT NULL,
        account_type_id VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Pending',
        ip_address VARCHAR(45),
        form_data LONGTEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    $table_forms = $wpdb->prefix . 'faap_forms';
    $sql_forms = "CREATE TABLE IF NOT EXISTS $table_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_type VARCHAR(50) UNIQUE,
        config LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_apps);
    dbDelta($sql_forms);

    // Ensure IP column exists on previous installs
    $exists = $wpdb->get_results("SHOW COLUMNS FROM $table_apps LIKE 'ip_address'");
    if (empty($exists)) {
        $wpdb->query("ALTER TABLE $table_apps ADD ip_address VARCHAR(45) NULL");
    }

    // Set default frontend URL if not set yet.
    if (!get_option('faap_frontend_url')) {
        add_option('faap_frontend_url', 'https://prominencebank.com:9002/');
    }
}

// 2. REST API Endpoints
add_action('rest_api_init', function () {
    register_rest_route('faap/v1', '/form-config/(?P<type>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'faap_get_form_config',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/save-form', array(
        'methods' => 'POST',
        'callback' => 'faap_save_form_config',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/submit', array(
        'methods' => 'POST',
        'callback' => 'faap_handle_submission',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications', array(
        'methods' => 'GET',
        'callback' => 'faap_get_applications',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications/(?P<id>\d+)/export-pdf', array(
        'methods' => 'GET',
        'callback' => 'faap_export_application_pdf',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('faap/v1', '/applications/(?P<id>\d+)/payment-verified', array(
        'methods' => 'POST',
        'callback' => 'faap_verify_payment',
        'permission_callback' => '__return_true',
    ));
});

add_action('admin_menu', 'faap_admin_menu');
function faap_admin_menu() {
    add_menu_page('FAAP Applications', 'FAAP', 'manage_options', 'faap-applications', 'faap_admin_submissions');
    add_submenu_page('faap-applications', 'Manage Forms', 'Manage Forms', 'manage_options', 'faap-forms', 'faap_admin_manage_forms');
}

add_filter('rest_pre_serve_request', function($value) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    return $value;
});

function faap_get_form_config($data) {
    global $wpdb;
    $type = sanitize_text_field($data['type'] ?? 'personal');
    $table_forms = $wpdb->prefix . 'faap_forms';
    $config = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", $type));

    if (empty($config)) {
        return rest_ensure_response(faap_get_default_form_steps());
    }

    $decoded = json_decode($config, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        // Try a safe fallback for common JSON issues.
        $cleanJson = wp_unslash(htmlspecialchars_decode($config));
        $decoded = json_decode($cleanJson, true);
    }

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return rest_ensure_response(faap_get_default_form_steps());
    }

    return rest_ensure_response($decoded);
}

function faap_validate_form_config($decoded) {
    if (!is_array($decoded)) {
        return false;
    }
    foreach ($decoded as $step) {
        if (!is_array($step)) {
            return false;
        }
        if (!isset($step['id'], $step['title'], $step['order'], $step['fields']) || !is_array($step['fields'])) {
            return false;
        }
        foreach ($step['fields'] as $field) {
            if (!is_array($field)) {
                return false;
            }
            if (!isset($field['id'], $field['label'], $field['name'], $field['type'], $field['width'])) {
                return false;
            }
            if (!in_array($field['type'], ['text','number','date','select','radio','textarea','email','file'], true)) {
                return false;
            }
            if (!in_array($field['width'], ['full','half'], true)) {
                return false;
            }
        }
    }
    return true;
}

function faap_save_form_config($request) {
    $type = sanitize_text_field($request->get_param('type'));
    $config = $request->get_param('config');

    if (is_array($config)) {
        $decoded = $config;
    } elseif (is_string($config)) {
        $decoded = json_decode(wp_unslash($config), true);
    } else {
        return new WP_Error('invalid', 'Invalid config');
    }

    if (!is_array($decoded) || !faap_validate_form_config($decoded)) {
        return new WP_Error('invalid', 'Invalid JSON');
    }

    $encoded = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid', 'Invalid JSON');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'faap_forms';
    $wpdb->replace($table, [
        'form_type' => $type,
        'config' => $encoded,
    ]);
    return ['success' => true];
}

function faap_save_uploaded_file($file, $prefix = 'faap') {
    if (empty($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $upload_dir = wp_upload_dir();
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = sanitize_file_name($prefix . '-' . uniqid() . '.' . $ext);
    $target_path = trailingslashit($upload_dir['path']) . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return trailingslashit($upload_dir['url']) . $filename;
    }

    return null;
}

function faap_format_label($key) {
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
    $label = str_replace(['_', '-'], ' ', $label);
    return ucwords($label);
}

function faap_is_base64_blob($value) {
    if (!is_string($value)) {
        return false;
    }
    if (strlen($value) > 500 && preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', trim($value))) {
        return true;
    }
    if (strpos($value, 'data:') !== false && strpos($value, 'base64') !== false) {
        return true;
    }
    return false;
}

function faap_normalize_value($value) {
    if (is_array($value)) {
        $flat = [];
        foreach ($value as $subKey => $subValue) {
            if (is_array($subValue)) {
                $flat[] = faap_normalize_value($subValue);
            } else {
                $flat[] = (string)$subValue;
            }
        }
        return implode(', ', $flat);
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    $str = trim((string)$value);
    if ($str === '') {
        return '-';
    }
    if (faap_is_base64_blob($str)) {
        return '[Content hidden]';
    }
    return nl2br(esc_html($str));
}

function faap_build_data_rows($data) {
    $rows = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'submittedAt', 'status', 'type', 'accountTypeId', 'applicationId', 'formData', 'passportPhoto'];
    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }
        if ($key === 'id' || $key === 'submitted_at') {
            continue;
        }

        if (is_array($value)) {
            $value = wp_json_encode($value);
        }
        $displayValue = faap_normalize_value($value);
        if ($displayValue === '-'){ continue; }

        $rows .= '<tr><td style="padding:9px 10px;border:1px solid #e5e7eb;background:#f9fafb;font-weight:700;width:35%;font-size:13px;">' . esc_html(faap_format_label($key)) . '</td><td style="padding:9px 10px;border:1px solid #e5e7eb;font-size:13px;">' . $displayValue . '</td></tr>';
    }
    return $rows;
}

function faap_build_ordered_detail_list($data) {
    $lines = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'submittedAt', 'status', 'type', 'accountTypeId', 'applicationId', 'formData', 'passportPhoto', 'id', 'submitted_at'];
    $counter = 1;
    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map('esc_html', $value));
        }
        $normalized = faap_normalize_value($value);
        if ($normalized === '-' || $normalized === '[Content hidden]') {
            continue;
        }
        $lines .= '<div style="margin-bottom:6px;"><strong>' . $counter . '. ' . esc_html(faap_format_label($key)) . '</strong><br><span style="margin-left:4px;color:#111827;">' . $normalized . '</span></div>';
        $counter++;
    }
    if ($lines === '') {
        return '<div style="color:#6b7280;font-size:13px;">No form data available.</div>';
    }
    return '<div style="font-size:13px;color:#111827;">' . $lines . '</div>';
}

function faap_get_letterhead_logo_url() {
    $logo = get_option('faap_letterhead_logo_url');
    if (empty($logo)) {
        $local_logo = plugin_dir_url(__FILE__) . rawurlencode('Prominence Bank.png');
        if (file_exists(plugin_dir_path(__FILE__) . 'Prominence Bank.png')) {
            $logo = $local_logo;
        } else {
            $frontend = rtrim(get_option('faap_frontend_url', ''), '/');
            if (!empty($frontend)) {
                $logo = $frontend . '/' . rawurlencode('Prominence Bank.png');
            } else {
                $logo = 'https://prominencebank.com/' . rawurlencode('Prominence Bank.png');
            }
        }
    }
    return esc_url($logo);
}

function faap_build_application_html($submission, $recipient = 'admin') {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $submitted_at_raw = sanitize_text_field($submission['submittedAt'] ?? $submission['submitted_at'] ?? current_time('mysql'));
    $submitted_at_ts = strtotime($submitted_at_raw);
    if ($submitted_at_ts === false) {
        $submitted_at_ts = current_time('timestamp');
    }
    $submitted_at = date('F j, Y \a\t g:iA', $submitted_at_ts);
    $logoUrl = faap_get_letterhead_logo_url();

    $data = $submission;
    if (isset($submission['applicationData']) && is_string($submission['applicationData'])) {
        $decoded = json_decode($submission['applicationData'], true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    $rows = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'submittedAt', 'submitted_at', 'status', 'type', 'accountTypeId', 'applicationId'];
    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map('esc_html', $value));
        }
        $rows .= '<tr><td style="padding:8px 10px;border:1px solid #e5e7eb;font-weight:600;background:#f9fafb;color:#111827;width:30%;">' . esc_html(faap_format_label($key)) . '</td><td style="padding:8px 10px;border:1px solid #e5e7eb;color:#111827;">' . esc_html((string)$value) . '</td></tr>';
    }

    $attachments = [];
    if (!empty($submission['mainDocumentFile'])) $attachments[] = $submission['mainDocumentFile'];
    if (!empty($submission['paymentProofFile'])) $attachments[] = $submission['paymentProofFile'];
    if (!empty($submission['companyRegFile'])) $attachments[] = $submission['companyRegFile'];

    $attachmentItems = '';
    foreach ($attachments as $fileUrl) {
        $attachmentItems .= '<li style="margin-bottom:4px;"><a href="' . esc_url($fileUrl) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:none;">' . esc_html(basename($fileUrl)) . '</a></li>';
    }
    if (empty($attachmentItems)) {
        $attachmentItems = '<li style="color:#6b7280;">No documents uploaded.</li>';
    }

    $user_name = esc_html($data['fullName'] ?? $data['name'] ?? 'Applicant');
    $user_email = esc_html($data['email'] ?? 'N/A');
    $signatureHtml = '';
    if (!empty($data['signatureImage'])) {
        $signatureHtml = '<div style="border:1px solid #e2e8f0;background:#f9fafb;border-radius:6px;padding:10px;margin-top:14px;"><div style="font-weight:700;margin-bottom:4px;">Signature</div><img src="' . esc_url($data['signatureImage']) . '" alt="Signature" style="max-width:240px;height:auto;border:1px solid #cbd5e1;border-radius:5px;" /></div>';
    }

    $recipientType = strtolower(trim($recipient));
    $fromEmail = 'account@prominencebank.com';
    $emailSubjectLine = 'New Form Entry #' . esc_html($app_id) . ' for ' . esc_html($type_label) . ' Bank Account';
    if ($recipientType === 'applicant') {
        $headerBlock = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;margin-bottom:10px;"><div style="font-weight:700;font-size:13px;color:#0f172a;">Applicant Email Header</div><div style="font-size:12px;color:#334155;margin-top:4px;">From: Prominence Bank Corp. &lt;' . esc_html($fromEmail) . '&gt;</div><div style="font-size:12px;color:#334155;margin-top:2px;">To: ' . esc_html($user_email) . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Subject: ' . esc_html($emailSubjectLine) . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Date: ' . esc_html($submitted_at) . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Application ID: ' . esc_html($app_id) . '</div></div>';
    } else {
        $adminEmail = esc_html(get_option('admin_email'));
        $headerBlock = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;margin-bottom:10px;"><div style="font-weight:700;font-size:13px;color:#0f172a;">Admin Email Header</div><div style="font-size:12px;color:#334155;margin-top:4px;">From: Prominence Bank Corp. &lt;' . esc_html($fromEmail) . '&gt;</div><div style="font-size:12px;color:#334155;margin-top:2px;">To: ' . $adminEmail . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Subject: ' . esc_html($emailSubjectLine) . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Date: ' . esc_html($submitted_at) . '</div><div style="font-size:12px;color:#334155;margin-top:2px;">Application ID: ' . esc_html($app_id) . '</div></div>';
    }

    return '<div style="font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;padding:16px;">
      <div style="max-width:800px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:14px 16px;background:#0a192f;color:#fff;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-weight:700;font-size:14px;letter-spacing:.03em;">PROMINENCE BANK CORP.</div>
            <div style="font-size:11px;opacity:.9;">account@prominencebank.com</div>
          </div>
          <div style="text-align:right;"><img src="' . esc_url($logoUrl) . '" alt="Prominence Bank" style="max-height:72px;object-fit:contain;" /></div>
        </div>
        <div style="padding:16px;">
          ' . $headerBlock . '
          <div style="font-weight:700;font-size:18px;color:#111827;margin-bottom:8px;">You have a new website form submission</div>
          <div style="margin-bottom:10px;color:#374151;">Application ID: <strong>' . esc_html($app_id) . '</strong></div>
          <div style="margin-bottom:18px;font-size:14px;">'. esc_html($type_label) .' Account application form</div>
          <div style="margin-bottom:6px;font-size:13px;color:#1f2937;">Application Type: <strong>' . esc_html($type_label) . '</strong></div>
          <div style="margin-bottom:14px;font-weight:700;">Applicant</div>
          <div style="margin-bottom:16px;">Name: ' . $user_name . ' | Email: ' . $user_email . '</div>
          <div style="font-weight:700;margin-bottom:8px;">Submitted Information</div>
          <table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">' . $rows . '</table>
          <div style="margin-top:16px;font-weight:700;">Uploaded Documents</div>
          <ul style="margin:4px 0 0 18px;padding:0;color:#111827;">' . $attachmentItems . '</ul>

          <div style="margin-top:20px;padding:12px;background:#f7f9fc;border:1px solid #e2e8f0;border-radius:8px;">
            <div style="font-weight:700;font-size:13px;color:#0f172a;margin-bottom:6px;">KYC/AML Documentation Note (Step 8)</div>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">Please ensure all documents are clear and valid. PCM may assist with intake and document coordination and transmit the compiled package to Prominence Bank. Prominence Bank may request additional documentation or enhanced due diligence at any time. Incomplete or inconsistent information may delay processing or result in application decline.</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">Insert Full Color Photo of your Passport Here *</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">ACCOUNT OPENING FEE — PAYMENT INSTRUCTIONS</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">Applicable to all new account types listed below.</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">Account Opening Fee (Onboarding & Compliance Processing Fee) does not guarantee approval or account opening.</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">€25,000 – Euro Account<br>$25,000 – USD Account<br>€25,000 – Custody Account<br>€25,000 – Cryptocurrency Account<br>€50,000 – Numbered Account</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;font-weight:700;">REFUND POLICY (NO EXCEPTIONS)</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">If declined and no account opened, fee refunded in full by PCM (no PCM deductions). Intermediary or network fees outside PCM control may affect net received amount. Refunds issued to original sender within 10 business days after formal decline.</p>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">If approved and account opened, fee is fully earned and non-refundable.</p>
            <div style="margin-top:8px;font-weight:700;font-size:12px;color:#111827;">PAYMENT OPTION 1: INTERNATIONAL WIRE (SWIFT)</div>
            <p style="margin:2px 0;color:#334155;font-size:12px;">EURO (€) CURRENCY</p>
            <p style="margin:0;color:#334155;font-size:12px;">Bank Name: Wise Europe<br>Bank Address: Rue du Trône 100, 3rd floor. Brussels. 1050. Belgium<br>SWIFT Code: TRWIBEB1XXX<br>Account Name: PROMINENCE CLIENT MANAGEMENT<br>Account Number/IBAN: BE31905717979455<br>Payment Reference: Application ID: ' . esc_html($app_id) . ' | Onboarding and Compliance Processing Fee</p>
            <p style="margin:4px 0;color:#334155;font-size:12px;">USD ($) CURRENCY</p>
            <p style="margin:0;color:#334155;font-size:12px;">Bank Name: Wise US Inc.<br>Bank Address: 108 W 13th St, Wilmington, DE, 19801, United States<br>SWIFT Code: TRWIUS35XXX<br>Account Name: PROMINENCE CLIENT MANAGEMENT<br>Account Number: 205414015428310<br>Payment Reference: Application ID: ' . esc_html($app_id) . ' | Onboarding and Compliance Processing Fee</p>
            <div style="margin-top:8px;font-weight:700;font-size:12px;color:#111827;">PAYMENT OPTION 2: CRYPTOCURRENCY (USDT TRC20)</div>
            <p style="margin:0 0 4px;color:#334155;font-size:12px;">USDT Wallet Address (TRC20): TPYjSzK3BbZRZAVhBoRZcdyzKpQ9NN6S6Y</p>
            <p style="margin:0 0 7px;color:#b91c1c;font-size:12px;">IMPORTANT: The Account Opening Fee must be paid via SWIFT or USDT. KTT / Telex not accepted.</p>
            <p style="margin:0;color:#334155;font-size:12px;">THIRD-PARTY ONBOARDING AND PAYMENT NOTICE: PCM is an independent introducer providing intake coordination only. PCM is not authorized to bind Prominence Bank. PCM does not provide banking, deposit-taking, custody, or legal services. Payment to PCM is a service fee and not a bank deposit.</p>
          </div>

          <div style="margin-top:20px;padding:12px;background:#fff7ed;border:1px solid #f5d1a1;border-radius:8px;">
            <div style="font-weight:700;font-size:13px;color:#713f12;margin-bottom:6px;">Agreed and Attested (Step 9)</div>
            <p style="margin:0 0 4px;color:#3b3f46;font-size:12px;"><strong>A. Mandatory Submission Requirements</strong> – Application may be automatically rejected if mandatory items are missing, including full opening fee, valid proof of payment, required documents, disclosures, and supporting materials.</p>
            <p style="margin:0 0 4px;color:#3b3f46;font-size:12px;"><strong>B. Payment Instructions</strong> – Only SWIFT or USDT accepted. KTT/Telex strictly prohibited. Application ID must be included in payment reference.</p>
            <p style="margin:0 0 4px;color:#3b3f46;font-size:12px;"><strong>C. Account Opening Requirements</strong> – Minimum balance and policy adherence required; account may be restricted if below minimum.</p>
            <p style="margin:0 0 4px;color:#3b3f46;font-size:12px;"><strong>D. Finality of Account Type</strong> – Selected account type is final; conversion requires a new application.</p>
            <p style="margin:0 0 4px;color:#3b3f46;font-size:12px;"><strong>E. Transaction Profile</strong> – Activity must align with declared profile; deviations may trigger due diligence.</p>
            <p style="margin:0;color:#3b3f46;font-size:12px;">By signing and submitting, applicant attests that information is true, accurate, complete, and authorizes verification checks, AML/KYC screening, and bank review in accordance with internal policies.</p>
          </div>

          <div style="margin-top:16px;">' . faap_get_banking_policy_html() . '</div>
          ' . ($signatureHtml ?: '<div style="margin-top:12px;font-size:12px;color:#6b7280;">[No drawn signature image available]</div>') . '
        </div>
      </div>
    </div>';
}

function faap_build_application_pdf_html($submission) {
    $body = faap_build_application_html($submission);
    return '<html><head><meta charset="utf-8"><style>body{margin:0;padding:0;background:#f3f4f6;} .pdf-container{max-width:850px;margin:0 auto;background:#fff;padding:10px;}</style></head><body><div class="pdf-container">' . $body . '</div></body></html>';
}
function faap_generate_application_pdf($submission) {
    $upload_dir = wp_upload_dir();
    $pdf_path = trailingslashit($upload_dir['path']) . 'faap-app-' . uniqid() . '.pdf';
    $html_file = trailingslashit($upload_dir['path']) . 'faap-app-' . uniqid() . '.html';

    $html_content = faap_build_application_pdf_html($submission);
    file_put_contents($html_file, $html_content);

    $wkhtml = trim(shell_exec('which wkhtmltopdf 2>/dev/null'));
    if ($wkhtml) {
        $escaped = escapeshellarg($wkhtml) . ' --enable-local-file-access ' . escapeshellarg($html_file) . ' ' . escapeshellarg($pdf_path) . ' 2>&1';
        $out = shell_exec($escaped);
        if (file_exists($pdf_path) && filesize($pdf_path) > 0) {
            @unlink($html_file);
            return $pdf_path;
        }
    }

    if (function_exists('proc_open')) {
        $cmd = 'wkhtmltopdf --enable-local-file-access ' . escapeshellarg($html_file) . ' ' . escapeshellarg($pdf_path);
        @exec($cmd, $output, $return);
        if ($return === 0 && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            @unlink($html_file);
            return $pdf_path;
        }
    }

    @unlink($html_file);
    return null;
}

function faap_handle_submission($request) {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';

    $params = $request->get_json_params();
    if (empty($params) && !empty($_POST)) {
        $params = $_POST;
    }
    if (!is_array($params)) {
        $params = [];
    }

    if (isset($params['applicationData']) && is_string($params['applicationData'])) {
        $decoded = json_decode(stripslashes($params['applicationData']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $params = array_merge($params, $decoded);
        }
    }

    $params['type'] = in_array($params['type'] ?? 'personal', ['personal', 'business'], true) ? $params['type'] : 'personal';
    $params['accountTypeId'] = sanitize_text_field($params['accountTypeId'] ?? '');
    $params['applicationId'] = sanitize_text_field($params['applicationId'] ?? 'APP-' . strtoupper(uniqid()));
    $params['status'] = 'Pending';

    try {
        if (!empty($_FILES['mainDocumentFile'])) {
            $saved = faap_save_uploaded_file($_FILES['mainDocumentFile'], 'main_document');
            if ($saved) {
                $params['mainDocumentFile'] = $saved;
            }
        }
        if (!empty($_FILES['paymentProofFile'])) {
            $saved = faap_save_uploaded_file($_FILES['paymentProofFile'], 'payment_proof');
            if ($saved) {
                $params['paymentProofFile'] = $saved;
            }
        }
        if (!empty($_FILES['companyRegFile'])) {
            $saved = faap_save_uploaded_file($_FILES['companyRegFile'], 'company_reg');
            if ($saved) {
                $params['companyRegFile'] = $saved;
            }
        }

        $form_data_json = wp_json_encode($params);
        $inserted = $wpdb->insert($table_apps, [
            'type' => $params['type'],
            'account_type_id' => $params['accountTypeId'],
            'status' => 'Pending',
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'Unknown'),
            'form_data' => $form_data_json,
        ]);

        if (!$inserted) {
            return new WP_Error('db_err', 'Failed to save application.');
        }

        $email_subject = sanitize_text_field($params['emailSubject'] ?? 'Application Received - Prominence Bank');
        $application_id = sanitize_text_field($params['applicationId']);
        $user_email = sanitize_email($params['email'] ?? $params['signatoryEmail'] ?? '');
        $admin_email = sanitize_email(get_option('admin_email'));
        $type_label = ucwords(sanitize_text_field($params['type'] ?? 'personal'));
        $logoUrl = faap_get_letterhead_logo_url();

        $full_body = faap_build_application_html($params, 'admin');
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $user_body = faap_build_application_html($params, 'applicant');
        $attachments = [];
        if (!empty($params['mainDocumentFile'])) $attachments[] = $params['mainDocumentFile'];
        if (!empty($params['paymentProofFile'])) $attachments[] = $params['paymentProofFile'];
        if (!empty($params['companyRegFile'])) $attachments[] = $params['companyRegFile'];

        $pdf_attachment = faap_generate_application_pdf($params);
        if ($pdf_attachment && file_exists($pdf_attachment)) {
            $attachments[] = $pdf_attachment;
        }

        if (!empty($user_email)) {
            $user_subject = $email_subject;
            wp_mail($user_email, $user_subject, $user_body, $headers, $attachments);
        }
        $admin_subject = "NEW APPLICATION | " . $application_id . " | " . strtoupper($type_label);
        wp_mail($admin_email, $admin_subject, $full_body, $headers, $attachments);

        return rest_ensure_response(['success' => true, 'id' => $wpdb->insert_id, 'applicationId' => $application_id]);
    } catch (Exception $e) {
        return new WP_Error('submission_error', 'Application submission error: ' . $e->getMessage(), ['status' => 500]);
    }
}

function faap_get_applications() {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';
    
    $applications = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC", ARRAY_A);
    
    // Format the data for the admin dashboard
    $formatted_apps = array_map(function($app) {
        $form_data = json_decode($app['form_data'], true);
        return [
            'id' => $app['id'],
            'type' => $app['type'],
            'accountTypeId' => $app['account_type_id'],
            'status' => $app['status'],
            'submittedAt' => $app['submitted_at'],
            'ipAddress' => $app['ip_address'] ?? 'N/A',
            'applicationId' => $form_data['applicationId'] ?? 'N/A',
            'applicantName' => $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A',
            'formData' => $form_data
        ];
    }, $applications);
    
    return $formatted_apps;
}

function faap_export_application_pdf($request) {
    global $wpdb;
    $id = intval($request->get_param('id'));
    $table_apps = $wpdb->prefix . 'faap_submissions';
    $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", $id), ARRAY_A);
    if (!$app) {
        return new WP_Error('not_found', 'Application not found.', ['status' => 404]);
    }
    $form_data = json_decode($app['form_data'], true);
    if (!is_array($form_data)) {
        return new WP_Error('invalid_data', 'Stored application data is invalid.', ['status' => 400]);
    }
    $pdf_path = faap_generate_application_pdf($form_data);
    if (!$pdf_path || !file_exists($pdf_path)) {
        return new WP_Error('pdf_error', 'Unable to generate PDF from application data.', ['status' => 500]);
    }
    $upload_dir = wp_upload_dir();
    $basedir = trailingslashit($upload_dir['basedir']);
    $baseurl = trailingslashit($upload_dir['baseurl']);
    if (strpos($pdf_path, $basedir) === 0) {
        $relative = ltrim(str_replace($basedir, '', $pdf_path), '/\\');
        $pdf_url = $baseurl . str_replace('\\', '/', $relative);
    } else {
        $pdf_url = $pdf_path;
    }
    return rest_ensure_response(['success' => true, 'pdfUrl' => $pdf_url]);
}

function faap_get_default_form_steps() {
    return [
        [
            'id' => 'step-1',
            'order' => 1,
            'title' => 'Account Type (Personal Account)',
            'description' => 'Select the account type.',
            'fields' => [
                ['id' => 'f1', 'label' => 'Account Type', 'name' => 'accountType', 'type' => 'select', 'width' => 'full', 'required' => true, 'options' => ['Savings Account', 'Custody Account', 'Numbered Account', 'Cryptocurrency Account']],
            ],
        ],
        [
            'id' => 'step-2',
            'order' => 2,
            'title' => 'Identity (Personal Details)',
            'description' => 'Personal identification information.',
            'fields' => [
                ['id' => 'f2', 'label' => 'First Name', 'name' => 'firstName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f3', 'label' => 'Last Name', 'name' => 'lastName', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f4', 'label' => 'Middle Name', 'name' => 'middleName', 'type' => 'text', 'width' => 'full', 'required' => false],
                ['id' => 'f5', 'label' => 'Date of Birth (dd-mm-yyyy)', 'name' => 'dateOfBirth', 'type' => 'date', 'width' => 'half', 'required' => true],
                ['id' => 'f6', 'label' => 'Place of Birth', 'name' => 'placeOfBirth', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f7', 'label' => 'Nationality', 'name' => 'nationality', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f8', 'label' => 'Passport / ID Number', 'name' => 'passportIdNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f9', 'label' => 'Passport Issue Date', 'name' => 'passportIssueDate', 'type' => 'date', 'width' => 'half', 'required' => true],
                ['id' => 'f10', 'label' => 'Passport Expiry Date', 'name' => 'passportExpiryDate', 'type' => 'date', 'width' => 'half', 'required' => true],
                ['id' => 'f11', 'label' => 'Country of Issue', 'name' => 'countryOfIssue', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f12', 'label' => 'Telephone / Fax Number', 'name' => 'telephoneFax', 'type' => 'text', 'width' => 'half', 'required' => true],
            ],
        ],
        [
            'id' => 'step-3',
            'order' => 3,
            'title' => 'Contact Information',
            'description' => 'Contact details.',
            'fields' => [
                ['id' => 'f13', 'label' => 'Home Address', 'name' => 'homeAddress', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f14', 'label' => 'Address Line 2', 'name' => 'addressLine2', 'type' => 'text', 'width' => 'full', 'required' => false],
                ['id' => 'f15', 'label' => 'City / State / Zip Code', 'name' => 'cityStateZip', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f16', 'label' => 'Country', 'name' => 'country', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f17', 'label' => 'Email Address', 'name' => 'email', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'f18', 'label' => 'Confirm Email', 'name' => 'emailConfirm', 'type' => 'email', 'width' => 'half', 'required' => true],
                ['id' => 'f19', 'label' => 'Mobile Number', 'name' => 'mobileNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
            ],
        ],
        [
            'id' => 'step-4',
            'order' => 4,
            'title' => 'Activity (Expected Transfer Activity)',
            'description' => 'Expected transfer activities.',
            'fields' => [
                ['id' => 'f20', 'label' => 'Main countries to send transfers', 'name' => 'sendCountries', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f21', 'label' => 'Main countries to receive transfers', 'name' => 'receiveCountries', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f22', 'label' => 'Estimated outgoing transfers per month', 'name' => 'outgoingTransfers', 'type' => 'number', 'width' => 'half', 'required' => true],
                ['id' => 'f23', 'label' => 'Estimated incoming transfers per month', 'name' => 'incomingTransfers', 'type' => 'number', 'width' => 'half', 'required' => true],
                ['id' => 'f24', 'label' => 'Average transfer value', 'name' => 'averageTransfer', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f25', 'label' => 'Maximum transfer value', 'name' => 'maxTransfer', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f26', 'label' => 'Initial funding currency', 'name' => 'fundingCurrency', 'type' => 'select', 'width' => 'half', 'required' => true, 'options' => ['EUR', 'USD']],
            ],
        ],
        [
            'id' => 'step-5',
            'order' => 5,
            'title' => 'Wealth (Source of Funds)',
            'description' => 'Source of funds information.',
            'fields' => [
                ['id' => 'f27', 'label' => 'Value of Initial Funding', 'name' => 'initialFundingValue', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f28', 'label' => 'Originating Bank Name', 'name' => 'originatingBankName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f29', 'label' => 'Bank Address', 'name' => 'originatingBankAddress', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f30', 'label' => 'Account Name & Number', 'name' => 'originatingAccount', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f31', 'label' => 'Signatory Name', 'name' => 'originatingSignatory', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f32', 'label' => 'Description of how funds were generated', 'name' => 'fundsDescription', 'type' => 'textarea', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'step-6',
            'order' => 6,
            'title' => 'Banking Details',
            'description' => 'Account banking details.',
            'fields' => [
                ['id' => 'f33', 'label' => 'Account Currency', 'name' => 'accountCurrency', 'type' => 'select', 'width' => 'half', 'required' => true, 'options' => ['EUR', 'USD']],
                ['id' => 'f34', 'label' => 'Optional account name (for your reference)', 'name' => 'optionalAccountName', 'type' => 'text', 'width' => 'half', 'required' => false],
            ],
        ],
        [
            'id' => 'step-7',
            'order' => 7,
            'title' => 'Fee Bank / Recommending Bank',
            'description' => 'Recommending bank details.',
            'fields' => [
                ['id' => 'f35', 'label' => 'Bank Name', 'name' => 'feeBankName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f36', 'label' => 'Bank Address', 'name' => 'feeBankAddress', 'type' => 'textarea', 'width' => 'full', 'required' => true],
                ['id' => 'f37', 'label' => 'SWIFT Code', 'name' => 'feeSwiftCode', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f38', 'label' => 'Account Holder Name', 'name' => 'feeAccountHolder', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f39', 'label' => 'Account Number', 'name' => 'feeAccountNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f40', 'label' => 'Account Signatory', 'name' => 'feeAccountSignatory', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f41', 'label' => 'Origin of Deposit Funds', 'name' => 'depositOrigin', 'type' => 'textarea', 'width' => 'full', 'required' => true],
            ],
        ],
        [
            'id' => 'step-8',
            'order' => 8,
            'title' => 'Account Opening Fee & Payment Instructions',
            'description' => 'Fee and payment details.',
            'fields' => [
                ['id' => 'f42', 'label' => 'Payment Method', 'name' => 'paymentMethod', 'type' => 'select', 'width' => 'full', 'required' => true, 'options' => ['SWIFT International Wire', 'Cryptocurrency (USDT TRC20)']],
                ['id' => 'f44', 'label' => 'Payment Proof / Transfer Receipt', 'name' => 'paymentProof', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f45', 'label' => 'Full Name / Signature', 'name' => 'fullNameSignature', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f46', 'label' => 'Passport or ID Number', 'name' => 'signatureIdNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f47', 'label' => 'Signature Date', 'name' => 'signatureDate', 'type' => 'date', 'width' => 'half', 'required' => true],
            ],
        ],
        [
            'id' => 'step-9',
            'order' => 9,
            'title' => 'Declaration & Signature',
            'description' => 'Agree to terms and sign.',
            'fields' => [
                ['id' => 'f48', 'label' => 'Agreement', 'name' => 'agreedToTerms', 'type' => 'radio', 'width' => 'full', 'required' => true, 'options' => ['Yes', 'No']],
                ['id' => 'f49', 'label' => 'Signature Pad', 'name' => 'signatureImage', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f50', 'label' => 'Signature Name', 'name' => 'signatureName', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f51', 'label' => 'Signature Date', 'name' => 'signatureDateConfirmation', 'type' => 'date', 'width' => 'half', 'required' => true],
            ],
        ],
    ];
}

function faap_verify_payment($request) {
    global $wpdb;
    $app_id = $request->get_param('id');
    $table_apps = $wpdb->prefix . 'faap_submissions';
    
    // Update status to verified
    $result = $wpdb->update($table_apps, ['status' => 'Payment Verified'], ['id' => $app_id]);
    
    if ($result) {
        // Get application data for email
        $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", $app_id), ARRAY_A);
        $form_data = json_decode($app['form_data'], true);
        $application_id = $form_data['applicationId'] ?? 'N/A';
        $user_email = $form_data['email'] ?? $form_data['signatoryEmail'] ?? '';
        
        // Send notification emails
        if (!empty($user_email)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $admin_email = get_option('admin_email');
            
            // Email to user
            $user_subject = "Payment Verified - Application ID: " . $application_id;
            $user_body = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:700px;margin:0 auto;padding:18px;background:#f9fafb;">
                <div style="background:#0a192f;color:#fff;padding:16px;border-radius:10px 10px 0 0;">
                  <div style="font-weight:800;font-size:18px;">Payment Verified</div>
                  <div style="margin-top:4px;font-size:12px;color:#d1d5db;">Prominence Bank Application</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:0 0 10px 10px;">
                  <p style="margin:0;color:#111827;">Dear Customer,</p>
                  <p style="margin:10px 0 0;color:#374151;">Your payment has been verified for Application ID: <strong>' . esc_html($application_id) . '</strong>.</p>
                  <p style="margin:10px 0 0;color:#374151;">Your account application is now being processed by our team. We will notify you when the next step is complete.</p>
                  <p style="margin:12px 0 0;color:#6b7280;">Thank you,<br>Prominence Bank Team</p>
                </div>
              </div>';
            wp_mail($user_email, $user_subject, $user_body, $headers);

            // Email to admin
            $admin_subject = "PAYMENT VERIFIED | " . $application_id;
            $admin_body = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:700px;margin:0 auto;padding:18px;background:#f9fafb;">
                <div style="background:#0a192f;color:#fff;padding:16px;border-radius:10px 10px 0 0;">
                  <div style="font-weight:800;font-size:18px;">Payment Verified</div>
                  <div style="margin-top:4px;font-size:12px;color:#d1d5db;">Prominence Bank Admin Alert</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:0 0 10px 10px;">
                  <p style="margin:0;color:#111827;">Payment has been verified for Application ID: <strong>' . esc_html($application_id) . '</strong>.</p>
                  <p style="margin:10px 0 0;color:#374151;">Please continue to process the application in the admin portal.</p>
                </div>
              </div>';
            wp_mail($admin_email, $admin_subject, $admin_body, $headers);
        }
        
        return ['success' => true, 'message' => 'Payment verified successfully'];
    }
    
    return new WP_Error('update_err', 'Failed to verify payment');
}

// 3. Admin Menu
add_action('admin_menu', function() {
    add_menu_page('Financial Portal', 'Financial Portal', 'manage_options', 'faap-admin', 'faap_admin_submissions', 'dashicons-bank', 30);
    add_submenu_page('faap-admin', 'Submissions', 'Submissions', 'manage_options', 'faap-admin', 'faap_admin_submissions');
    add_submenu_page('faap-admin', 'Manage Forms', 'Manage Forms', 'manage_options', 'faap-manage-forms', 'faap_admin_manage_forms');
});

function faap_admin_submissions() {
    global $wpdb;
    $table_apps = $wpdb->prefix . 'faap_submissions';

    if (!empty($_GET['preview_email']) && !empty($_GET['id'])) {
        $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", intval($_GET['id'])), ARRAY_A);
        if ($app) {
            $form_data = json_decode($app['form_data'], true);
            echo faap_build_application_html($form_data);
            exit;
        }
    }
    if (!empty($_GET['preview_pdf']) && !empty($_GET['id'])) {
        $app = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_apps WHERE id = %d", intval($_GET['id'])), ARRAY_A);
        if ($app) {
            $form_data = json_decode($app['form_data'], true);
            $pdf_path = faap_generate_application_pdf($form_data);
            if ($pdf_path && file_exists($pdf_path)) {
                $upload_dir = wp_upload_dir();
                $pdf_url = str_replace($upload_dir['path'], $upload_dir['url'], $pdf_path);
                wp_redirect($pdf_url);
                exit;
            }
        }
    }

    if (!empty($_GET['export_csv'])) {
        $rows = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC", ARRAY_A);
        $filename = 'faap-submissions-' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID','Application ID','Type','Account Type','Status','Submitted At','Applicant Name','Form Data']);
        foreach ($rows as $row) {
            $form_data = json_decode($row['form_data'], true);
            $app_id = $form_data['applicationId'] ?? 'N/A';
            $name = $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A';
            fputcsv($output, [$row['id'], $app_id, $row['type'], $row['account_type_id'], $row['status'], $row['submitted_at'], $name, json_encode($form_data)]);
        }
        fclose($output);
        exit;
    }

    if (!empty($_GET['export_all_pdf'])) {
        $rows = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC", ARRAY_A);
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;padding:20px;} .entry{border:1px solid #ccc;padding:12px;margin-bottom:12px;border-radius:8px;} .entry h3{margin:0 0 6px;color:#111;} .entry p{margin:3px 0;color:#333;}</style></head><body><h1>All Submissions</h1>';
        foreach ($rows as $row) {
            $form_data = json_decode($row['form_data'], true);
            $app_id = $form_data['applicationId'] ?? 'N/A';
            $name = $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A';
            $html .= '<div class="entry"><h3>Application ID: ' . esc_html($app_id) . '</h3><p><strong>Name:</strong> ' . esc_html($name) . '</p><p><strong>Type:</strong> ' . esc_html($row['type']) . '</p><p><strong>Account Type:</strong> ' . esc_html($row['account_type_id']) . '</p><p><strong>Status:</strong> ' . esc_html($row['status']) . '</p></div>';
        }
        $html .= '</body></html>';

        $tmp_html = wp_tempnam('faap-all-submissions') . '.html';
        $tmp_pdf = wp_tempnam('faap-all-submissions') . '.pdf';
        file_put_contents($tmp_html, $html);
        $cmd = 'wkhtmltopdf ' . escapeshellarg($tmp_html) . ' ' . escapeshellarg($tmp_pdf);
        exec($cmd, $out, $code);
        if ($code === 0 && file_exists($tmp_pdf)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="faap-all-submissions-' . date('YmdHis') . '.pdf"');
            readfile($tmp_pdf);
            unlink($tmp_html);
            unlink($tmp_pdf);
            exit;
        }
        // fallback if PDF conversion fails
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        unlink($tmp_html);
        exit;
    }

    $rows = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC");
    ?>
    <div class="wrap faap-admin">
        <div class="faap-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
              <h1 style="font-family: 'Alegreya', serif; margin: 0; background: linear-gradient(90deg, #f6e05e, #fbbf24, #f59e0b); -webkit-background-clip: text; color: transparent;">Application Submissions</h1>
              <p style="color: #f3f4f6; margin: 5px 0 0;">Manage and review submitted applications</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
              <a class="button button-primary" href="?page=faap-admin&export_csv=1">Export CSV</a>
              <a class="button button-primary" href="?page=faap-admin&export_all_pdf=1">Download all submissions PDF</a>
              <span style="font-size:12px;color:#fff;">Download all submissions as CSV</span>
            </div>
        </div>
        <div class="faap-content">
            <table class="wp-list-table widefat fixed striped faap-table">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Applicant Name</th>
                        <th>Client IP</th>
                        <th>Type</th>
                        <th>Account Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): foreach($rows as $row): 
                        $form_data = json_decode($row->form_data, true);
                        $app_id = $form_data['applicationId'] ?? 'N/A';
                        $app_name = $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($app_id); ?></strong></td>
                        <td><?php echo esc_html($app_name); ?></td>
                        <td><?php echo esc_html($row->ip_address ?? 'N/A'); ?></td>
                        <td><span class="faap-badge faap-type"><?php echo strtoupper($row->type); ?></span></td>
                        <td><?php echo esc_html($row->account_type_id); ?></td>
                        <td><span class="faap-status"><?php echo esc_html($row->status); ?></span></td>
                        <td><?php echo esc_html($row->submitted_at); ?></td>
                        <td>
                            <button class="button button-small faap-btn" onclick='document.getElementById("faap-details-summary").textContent = "Selected Application: " + <?php echo json_encode($app_id); ?> + " - " + <?php echo json_encode($app_name); ?> + ". Use the download button to get PDF."; document.getElementById("faap-details-id").textContent = "Application " + <?php echo json_encode($app_id); ?>; document.getElementById("faap-preview-pdf").href = "?page=faap-admin&preview_pdf=1&id=" + <?php echo json_encode($row->id); ?>;'>View Details</button>
                            <a class="button button-small" href="?page=faap-admin&preview_pdf=1&id=<?php echo intval($row->id); ?>" target="_blank">Export PDF</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="faap-empty">No applications received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="faap-details-panel">
                <h2 id="faap-details-id">Application Details</h2>
                <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                  <a id="faap-preview-pdf" class="button button-primary" target="_blank" href="?page=faap-admin">Download application PDF</a>
                </div>
                <div id="faap-details-summary" style="width:100%;min-height:140px;border:1px solid #ccc;padding:10px;border-radius:6px;background:#f9fbff;color:#111;">Click "View Details" on any submission to preview and download the application PDF.</div>
            </div>
        </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function(){
        const summary = document.getElementById('faap-details-summary');
        if (summary) {
          summary.textContent = 'Click "View Details" on any submission to preview and download the application PDF.';
        }
      });
    </script>
    <style>
    .faap-admin { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .faap-header { background: linear-gradient(135deg, #0a192f 0%, #1e3a5f 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .faap-content { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 20px; }
    .faap-table th { background: #f8f9fa; font-weight: 600; color: #0a192f; }
    .faap-badge { background: #0a192f; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .faap-status { color: #c29d45; font-weight: bold; }
    .faap-btn { background: #0a192f; color: white; border: none; border-radius: 4px; }
    .faap-btn:hover { background: #1e3a5f; }
    .faap-empty { text-align: center; color: #666; font-style: italic; }
    </style>
    <?php
}

function faap_admin_manage_forms() {
    global $wpdb;
    $table_forms = $wpdb->prefix . 'faap_forms';
    $message = '';
    $message_class = '';

    if (isset($_POST['save_form'])) {
        $form_type = sanitize_text_field($_POST['form_type'] ?? 'personal');
        $config_raw = wp_unslash($_POST['form_config'] ?? '');
        $decoded = json_decode($config_raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to clean common bad characters and decode again
            $config_raw = htmlspecialchars_decode($config_raw);
            $decoded = json_decode($config_raw, true);
        }

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && faap_validate_form_config($decoded)) {
            $encoded = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() === JSON_ERROR_NONE) {
                $wpdb->replace($table_forms, ['form_type' => $form_type, 'config' => $encoded]);
                $message = 'Form configuration updated successfully.';
                $message_class = 'updated';
            } else {
                $message = 'Could not encode JSON. Please try again.';
                $message_class = 'error';
            }
        } else {
            $message = 'Invalid JSON. Please fix and save again.';
            $message_class = 'error';
        }
    }

    $personal = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", 'personal'));
    $business = $wpdb->get_var($wpdb->prepare("SELECT config FROM $table_forms WHERE form_type = %s", 'business'));

    // Ensure valid JSON for the editor defaults.
    $personalData = json_decode($personal, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($personalData) || count($personalData) === 0) {
        $personalData = faap_get_default_form_steps();
    }
    $businessData = json_decode($business, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($businessData) || count($businessData) === 0) {
        $businessData = faap_get_default_form_steps();
    }

    $personalJson = json_encode($personalData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $businessJson = json_encode($businessData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    ?>
    <div class="wrap">
        <h1>Manage Form Steps (Visual Editor)</h1>
        <?php if ($message): ?>
            <div class="<?php echo esc_attr($message_class); ?>"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        <p>Use this visual editor to add/remove steps and fields. Click Save to persist changes.</p>

        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:320px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
                <h2>Personal Steps</h2>
                <div id="personal-steps" style="margin-bottom:12px;"></div>
                <button id="add-personal-step" class="button button-secondary">+ Add Step</button>
                <form method="post" id="personal-save-form" style="margin-top:12px;">
                    <input type="hidden" name="form_type" value="personal">
                    <input type="hidden" name="form_config" id="personal_form_config">
                    <button type="submit" name="save_form" class="button button-primary">Save Personal</button>
                </form>
            </div>

            <div style="flex:1;min-width:320px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
                <h2>Business Steps</h2>
                <div id="business-steps" style="margin-bottom:12px;"></div>
                <button id="add-business-step" class="button button-secondary">+ Add Step</button>
                <form method="post" id="business-save-form" style="margin-top:12px;">
                    <input type="hidden" name="form_type" value="business">
                    <input type="hidden" name="form_config" id="business_form_config">
                    <button type="submit" name="save_form" class="button button-primary">Save Business</button>
                </form>
            </div>
        </div>

        <div style="margin-top:22px;">
            <h3>Raw JSON (for backup)</h3>
            <p style="font-size:12px;color:#555;">The editor stores valid JSON. You can copy this for backup or manual edit.</p>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <textarea id="personal-raw" style="width:100%;min-height:160px;" readonly></textarea>
                <textarea id="business-raw" style="width:100%;min-height:160px;" readonly></textarea>
            </div>
        </div>

        <div style="margin-top:22px;border:1px solid #ccc;padding:12px;border-radius:8px;background:#fff;">
            <h3>How to Use the Form on Your Site</h3>
            <p>Use the shortcode <code>[financial_form]</code> to embed the application form on any page or post.</p>
            <p><strong>Basic Usage:</strong> Add <code>[financial_form]</code> to your page content.</p>
            <p><strong>Custom URL:</strong> If you need to point to a different frontend URL, use <code>[financial_form url="https://your-custom-url.com"]</code>.</p>
            <p>The form will load in an iframe with a height of 1200px. Adjust the height in the shortcode function if needed.</p>
            <p><strong>Note:</strong> Ensure your frontend URL is set correctly in the plugin settings (default: https://prominencebank.com:9002/).</p>
        </div>
    </div>

    <script>
    const personalData = <?php echo json_encode(json_decode($personalJson, true) ?: [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
    const businessData = <?php echo json_encode(json_decode($businessJson, true) ?: [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;

    function createFieldHtml(stepIndex, fieldIndex, field, baseId) {
      return `
        <div class="faap-field" style="border:1px dashed #d5d5d5; padding:8px; margin-bottom:6px; border-radius:6px; background:#f8f8f8;">
          <div style="display:flex;gap:8px; align-items:center; margin-bottom:4px;">
            <small style="font-weight:bold;">Field ${fieldIndex + 1}</small>
            <button type="button" data-remove-field="${stepIndex}:${fieldIndex}" class="button button-link" style="font-size:11px;">Remove</button>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px; margin-bottom:4px;">
            <input type="text" placeholder="label" data-field-label="${stepIndex}:${fieldIndex}" value="${field.label || ''}" style="width:100%;" />
            <input type="text" placeholder="name" data-field-name="${stepIndex}:${fieldIndex}" value="${field.name || ''}" style="width:100%;" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px; margin-bottom:4px;">
            <select data-field-type="${stepIndex}:${fieldIndex}" style="width:100%;">
              <option value="text" ${field.type === 'text' ? 'selected' : ''}>text</option>
              <option value="number" ${field.type === 'number' ? 'selected' : ''}>number</option>
              <option value="date" ${field.type === 'date' ? 'selected' : ''}>date</option>
              <option value="select" ${field.type === 'select' ? 'selected' : ''}>select</option>
              <option value="radio" ${field.type === 'radio' ? 'selected' : ''}>radio</option>
              <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>textarea</option>
              <option value="email" ${field.type === 'email' ? 'selected' : ''}>email</option>
              <option value="file" ${field.type === 'file' ? 'selected' : ''}>file</option>
            </select>
            <select data-field-width="${stepIndex}:${fieldIndex}" style="width:100%;">
              <option value="full" ${field.width === 'full' ? 'selected' : ''}>full</option>
              <option value="half" ${field.width === 'half' ? 'selected' : ''}>half</option>
            </select>
          </div>
          <div style="display:flex;gap:8px;">
            <label style="font-size:11px;">required <input type="checkbox" data-field-required="${stepIndex}:${fieldIndex}" ${field.required ? 'checked' : ''} /></label>
          </div>
        </div>
      `;
    }

    function renderEditor(data, containerId) {
      const container = document.getElementById(containerId);
      container.innerHTML = '';

      data.forEach((step, stepIndex) => {
        const stepDiv = document.createElement('div');
        stepDiv.style.border = '1px solid #d2d2d2';
        stepDiv.style.padding = '10px';
        stepDiv.style.marginBottom = '10px';
        stepDiv.style.borderRadius = '8px';
        stepDiv.style.background = '#fefefe';

        const stepHeader = document.createElement('div');
        stepHeader.style.display = 'flex';
        stepHeader.style.justifyContent = 'space-between';
        stepHeader.style.alignItems = 'center';
        stepHeader.style.marginBottom = '8px';

        const stepTitle = document.createElement('strong');
        stepTitle.textContent = `Step ${stepIndex + 1}`;

        const stepControls = document.createElement('div');
        stepControls.style.display = 'flex';
        stepControls.style.gap = '6px';
        stepControls.style.alignItems = 'center';

        const moveUp = document.createElement('button');
        moveUp.type = 'button';
        moveUp.textContent = '↑';
        moveUp.className = 'button button-link';
        moveUp.title = 'Move step up';
        moveUp.disabled = stepIndex === 0;
        moveUp.onclick = () => {
          if (stepIndex === 0) return;
          const prev = data[stepIndex - 1];
          const current = data[stepIndex];
          [data[stepIndex - 1], data[stepIndex]] = [current, prev];
          const tempOrder = prev.order;
          prev.order = current.order;
          current.order = tempOrder;
          renderAll();
        };

        const moveDown = document.createElement('button');
        moveDown.type = 'button';
        moveDown.textContent = '↓';
        moveDown.className = 'button button-link';
        moveDown.title = 'Move step down';
        moveDown.disabled = stepIndex === data.length - 1;
        moveDown.onclick = () => {
          if (stepIndex === data.length - 1) return;
          const next = data[stepIndex + 1];
          const current = data[stepIndex];
          [data[stepIndex + 1], data[stepIndex]] = [current, next];
          const tempOrder = next.order;
          next.order = current.order;
          current.order = tempOrder;
          renderAll();
        };

        const removeStep = document.createElement('button');
        removeStep.type = 'button';
        removeStep.textContent = 'Remove Step';
        removeStep.className = 'button button-link';
        removeStep.onclick = () => {
          data.splice(stepIndex, 1);
          renderAll();
        };

        stepControls.appendChild(moveUp);
        stepControls.appendChild(moveDown);
        stepControls.appendChild(removeStep);

        stepHeader.appendChild(stepTitle);
        stepHeader.appendChild(stepControls);

        const stepFields = document.createElement('div');
        stepFields.style.display = 'grid';
        stepFields.style.gridTemplateColumns = '1fr 1fr';
        stepFields.style.gap = '8px';
        stepFields.style.marginBottom = '8px';

        const idInput = document.createElement('input');
        idInput.type = 'text';
        idInput.value = step.id || `step-${stepIndex + 1}`;
        idInput.placeholder = 'id';
        idInput.onchange = (e) => {
          step.id = e.target.value;
          updateRaw();
        };

        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.value = step.title || '';
        titleInput.placeholder = 'title';
        titleInput.onchange = (e) => {
          step.title = e.target.value;
          updateRaw();
        };

        const orderInput = document.createElement('input');
        orderInput.type = 'number';
        orderInput.value = step.order || stepIndex + 1;
        orderInput.placeholder = 'order';
        orderInput.onchange = (e) => {
          step.order = Number(e.target.value);
          updateRaw();
        };

        const descInput = document.createElement('input');
        descInput.type = 'text';
        descInput.value = step.description || '';
        descInput.placeholder = 'description';
        descInput.onchange = (e) => {
          step.description = e.target.value;
          updateRaw();
        };

        stepFields.appendChild(idInput);
        stepFields.appendChild(titleInput);
        stepFields.appendChild(orderInput);
        stepFields.appendChild(descInput);

        const fieldsDiv = document.createElement('div');
        fieldsDiv.style.marginBottom = '8px';
        fieldsDiv.innerHTML = '<strong>Fields</strong>';

        (step.fields || []).forEach((field, fieldIndex) => {
          const fieldHtml = document.createElement('div');
          fieldHtml.innerHTML = createFieldHtml(stepIndex, fieldIndex, field, containerId);
          fieldsDiv.appendChild(fieldHtml);
        });

        const addFieldBtn = document.createElement('button');
        addFieldBtn.type = 'button';
        addFieldBtn.className = 'button button-secondary';
        addFieldBtn.textContent = '+ Add Field';
        addFieldBtn.onclick = () => {
          step.fields = step.fields || [];
          step.fields.push({ id: `f-${Date.now()}`, label: 'New field', name: 'newField', type: 'text', width: 'full', required: false });
          renderAll();
        };

        stepDiv.appendChild(stepHeader);
        stepDiv.appendChild(stepFields);
        stepDiv.appendChild(fieldsDiv);
        stepDiv.appendChild(addFieldBtn);

        container.appendChild(stepDiv);
      });

      Array.from(container.querySelectorAll('input[data-field-label],input[data-field-name],select[data-field-type],select[data-field-width],input[data-field-required]')).forEach((input) => {
        input.onchange = () => {
          const [stepIndex, fieldIndex] = input.dataset.fieldLabel?.split(':') || input.dataset.fieldName?.split(':') || input.dataset.fieldType?.split(':') || input.dataset.fieldWidth?.split(':') || input.dataset.fieldRequired?.split(':');
          const step = data[Number(stepIndex)];
          const field = step?.fields?.[Number(fieldIndex)];
          if (!field) return;

          if (input.dataset.fieldLabel) field.label = input.value;
          if (input.dataset.fieldName) field.name = input.value;
          if (input.dataset.fieldType) field.type = input.value;
          if (input.dataset.fieldWidth) field.width = input.value;
          if (input.dataset.fieldRequired) field.required = input.checked;
          updateRaw();
        };
      });

      Array.from(container.querySelectorAll('[data-remove-field]')).forEach((button) => {
        button.addEventListener('click', () => {
          const [stepIndex, fieldIndex] = button.dataset.removeField.split(':').map(Number);
          data[stepIndex].fields.splice(fieldIndex, 1);
          renderAll();
        });
      });

      updateRaw();
    }

    function sortSteps(steps) {
      return steps.slice().sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
    }

    function renderAll() {
      personalData.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
      businessData.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0));
      renderEditor(personalData, 'personal-steps');
      renderEditor(businessData, 'business-steps');
      updateRaw();
    }

    function updateRaw() {
      const personalRaw = document.getElementById('personal-raw');
      const businessRaw = document.getElementById('business-raw');
      const personalConfig = document.getElementById('personal_form_config');
      const businessConfig = document.getElementById('business_form_config');
      if (personalRaw) personalRaw.value = JSON.stringify(personalData, null, 2);
      if (businessRaw) businessRaw.value = JSON.stringify(businessData, null, 2);
      if (personalConfig) personalConfig.value = JSON.stringify(personalData, null, 2);
      if (businessConfig) businessConfig.value = JSON.stringify(businessData, null, 2);
    }

    document.getElementById('add-personal-step').addEventListener('click', () => {
      personalData.push({ id: `step-${personalData.length + 1}`, order: personalData.length + 1, title: 'New Step', description: '', fields: [] });
      renderAll();
    });

    document.getElementById('add-business-step').addEventListener('click', () => {
      businessData.push({ id: `step-${businessData.length + 1}`, order: businessData.length + 1, title: 'New Step', description: '', fields: [] });
      renderAll();
    });

    document.getElementById('personal-save-form').addEventListener('submit', () => {
      document.getElementById('personal_form_config').value = JSON.stringify(personalData, null, 2);
    });
    document.getElementById('business-save-form').addEventListener('submit', () => {
      document.getElementById('business_form_config').value = JSON.stringify(businessData, null, 2);
    });

    renderAll();
    </script>
    <?php
}

add_shortcode('financial_form', function($atts) {
    $defaultUrl = 'https://prominencebank.com:9002/';
    // Accept custom URL via shortcode [financial_form url="..."] for testing.
    $url = isset($atts['url']) ? esc_url_raw($atts['url']) : get_option('faap_frontend_url', $defaultUrl);
    if (empty($url)) {
        $url = $defaultUrl;
    }
    return "<div class='faap-container' style='background:#f4f7f9; padding:10px;'>
        <iframe src='" . esc_url($url) . "' style='width:100%; height:1200px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);' allow='payment'></iframe>
    </div>";
});
