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

    if (!$config) {
        return rest_ensure_response(faap_get_default_form_steps());
    }

    $decoded = json_decode($config, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return rest_ensure_response(faap_get_default_form_steps());
    }

    return rest_ensure_response($decoded);
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

function faap_get_letterhead_logo_url() {
    $logo = get_option('faap_letterhead_logo_url');
    if (empty($logo)) {
        // If not set, fall back to a logo shipped with the plugin.
        $logo = plugins_url('Prominence Bank.png', __FILE__);
    }
    return esc_url($logo);
}

function faap_get_banking_policy_html() {
    return '<div style="margin-top:26px;padding:14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;"><div style="font-size:13px;color:#111827;line-height:1.45;"><div style="margin-bottom:8px;font-weight:700;font-size:14px;">AGREED AND ATTESTED</div><div>By signing and submitting this Personal Bank Account Application, the Applicant(s) acknowledge(s), confirm(s), attest(s), represent(s), warrant(s), and irrevocably agree(s) to the following:</div><div style="margin-top:8px;font-weight:700;">A. Mandatory Submission Requirements (Strict Compliance)</div><div style="margin-top:2px;">The Applicant(s) understand(s), acknowledge(s), and accept(s) that the Bank shall automatically reject, without substantive review, processing, or response, any application submitted without all mandatory items required by the Bank, including, without limitation:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>Full Personal Bank Account opening fee</li><li>Valid proof of payment</li><li>All required documentation, disclosures, and supporting materials specified in the application form</li></ul>The Applicant(s) further acknowledge(s) that repeated submission of incomplete, deficient, inaccurate, or non-compliant applications may, at the Bank’s sole and absolute discretion, result in permanent disqualification from reapplying for any banking product or service.</div><div style="margin-top:6px;font-weight:700;">B. Payment Instructions (Opening Fee)</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s), understand(s), and accept(s) that payments made via KTT/TELEX are strictly prohibited and shall not be accepted under any circumstances for payment of the bank account opening fee.<br>Accepted methods of payment for the opening fee are strictly limited to the following:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>SWIFT international wire transfer</li><li>Cryptocurrency transfer to the designated wallet address listed in the application form</li></ul>The Applicant(s) further acknowledge(s) that the Application ID must be included in the payment reference field exactly as instructed by the Bank in order to ensure proper and timely allocation of funds. Incomplete, inaccurate, omitted, misdirected, or improperly referenced payments may delay processing and may result in rejection of the application, without liability to the Bank.</div><div style="margin-top:6px;font-weight:700;">C. Account Opening Requirements</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s), understand(s), and accept(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>A minimum balance of USD/EUR 5,000 must be maintained at all times.</li><li>Ongoing adherence to the Bank’s account policies, procedures, operational requirements, and compliance standards is required in order to maintain access to banking services.</li><li>If the account balance falls below the minimum required level, the Bank may restrict services, request corrective funding, apply internal controls, and/or place the account under compliance, risk, or administrative review until the deficiency is remedied.</li></ul></div><div style="margin-top:6px;font-weight:700;">D. Finality of Account Type Selection; No Conversion or Reclassification After Opening</div><div style="margin-top:2px;">The Applicant(s) hereby acknowledge(s), confirm(s), represent(s), warrant(s), and irrevocably agree(s) that the account category selected in this Application is made solely at the Applicant’s own election, responsibility, and risk, and shall be deemed final for purposes of the submitted Application.<br>The Applicant(s) further acknowledge(s) and accept(s) that, once the Application has been submitted, approved by the Bank, and the account has been opened, activated, or established under the selected account category, such account category shall be final and may not thereafter be amended, converted, substituted, re-designated, reclassified, exchanged, or otherwise modified into any other account type, whether in whole or in part.</div><div style="margin-top:6px;font-weight:700;">E. Transaction Profile and Ongoing Due Diligence</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) and accept(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>Account activity must at all times reasonably align with the information declared in this application.</li><li>Any material deviation, inconsistency, anomaly, or change in activity profile may require additional verification and may be delayed, restricted, reviewed, declined, or otherwise subject to enhanced due diligence.</li><li>The Applicant(s) agree(s) to provide such additional documentation, declarations, evidence, or clarifications as the Bank may request at any time to satisfy AML/KYC and risk requirements.</li></ul></div><div style="margin-top:6px;font-weight:700;">F. Accuracy and Authorization</div><div style="margin-top:2px;">The Applicant(s) hereby affirm(s), represent(s), warrant(s), and undertake(s) that:<br><ul style="margin:4px 0 4px 18px;color:#111827;"><li>All information provided in this application is true, accurate, complete, current, and not misleading in any respect.</li><li>The information is submitted for the purpose of establishing a service relationship with Prominence Bank under the Terms and Conditions disclosed prior to submission and accepted by the Applicant(s) upon signature and/or submission.</li></ul>The Applicant(s) hereby authorize(s) the Bank, without further notice except where required by applicable law or the Bank’s governing framework, to verify details, conduct credit/fraud/identity/sanctions/compliance checks, and request additional information at any time.</div><div style="margin-top:6px;font-weight:700;">G. Account Retention, Record-Keeping, and Banking Relationship (ETMO Framework)</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) that accounts may be retained or restricted for regulatory, audit, compliance, or operational reasons and that closure is subject to internal policies and applicable legal obligations.</div><div style="margin-top:6px;font-weight:700;">H. Compliance and Regulatory Framework</div><div style="margin-top:2px;">The Applicant(s) acknowledge(s) that the Bank operates under a diplomatic regulatory framework, applies AML/KYC and sanctions controls, and may perform ongoing monitoring and due diligence.</div><div style="margin-top:6px;font-weight:700;">I. Data Processing and Privacy</div><div style="margin-top:2px;">The Applicant(s) agree(s) that personal data is collected and processed for onboarding, compliance, execution, and record retention in accordance with the Bank’s policies. The Applicant(s) can request rights where available under applicable law.</div></div></div>';
}

function faap_build_application_html($submission) {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $submitted_at = sanitize_text_field($submission['submittedAt'] ?? date('Y-m-d H:i:s'));

    $data = $submission;
    if (isset($submission['applicationData']) && is_string($submission['applicationData'])) {
        $decoded = json_decode($submission['applicationData'], true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    $rows = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'submittedAt', 'status', 'type', 'accountTypeId', 'applicationId'];
    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map('esc_html', $value));
        }
        $rows .= '<tr><td style="padding:8px 10px;border:1px solid #e5e7eb;font-weight:600;background:#f9fafb;color:#111827;">' . esc_html(faap_format_label($key)) . '</td><td style="padding:8px 10px;border:1px solid #e5e7eb;color:#111827;">' . esc_html((string)$value) . '</td></tr>';
    }

    $attachments = [];
    if (!empty($submission['mainDocumentFile'])) $attachments[] = $submission['mainDocumentFile'];
    if (!empty($submission['paymentProofFile'])) $attachments[] = $submission['paymentProofFile'];
    if (!empty($submission['companyRegFile'])) $attachments[] = $submission['companyRegFile'];

    $attachmentItems = '';
    foreach ($attachments as $fileUrl) {
        $attachmentItems .= '<li><a href="' . esc_url($fileUrl) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:none;">' . esc_html(basename($fileUrl)) . '</a></li>';
    }
    if (empty($attachmentItems)) {
        $attachmentItems = '<li style="color:#6b7280;">No documents uploaded.</li>';
    }

    $user_name = esc_html($data['fullName'] ?? $data['name'] ?? 'Applicant');
    $user_email = esc_html($data['email'] ?? '');
    $logoUrl = faap_get_letterhead_logo_url();

    $signatureHtml = '';
    if (!empty($data['signatureImage'])) {
        $signatureHtml = '<div style="border:1px solid #e2e8f0;background:#f9fafb;border-radius:6px;padding:10px;margin-bottom:16px;"><div style="font-weight:700;margin-bottom:4px;">Signature</div><img src="' . esc_url($data['signatureImage']) . '" alt="Signature" style="max-width:240px;height:auto;border:1px solid #cbd5e1;border-radius:5px;" /></div>';
    }

    return '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f3f4f6;padding:18px;">
      <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:18px 20px;border-bottom:1px solid #e5e7eb;gap:16px;flex-wrap:wrap;">
          <div style="font-size:12px;color:#374151;line-height:1.5;">
            <div><strong>From:</strong> Prominence Bank Corp. &lt;account@prominencebank.com&gt;</div>
            <div><strong>Subject:</strong> Application Submission Receipt</div>
            <div><strong>Date:</strong> ' . esc_html(date('F j, Y \@ H:i', strtotime($submitted_at))) . '</div>
            <div><strong>To:</strong> ' . esc_html($user_email ?: 'N/A') . '</div>
          </div>
          <div style="width:92px;display:flex;justify-content:flex-end;align-items:center;">
            <img src="' . esc_url($logoUrl) . '" alt="Prominence Bank" style="max-height:72px;object-fit:contain;" />
          </div>
        </div>

        <div style="padding:20px;">
          <div style="margin-bottom:16px;">
            <div style="font-size:18px;font-weight:700;color:#111827;">Hello ' . $user_name . ',</div>
            <p style="margin:6px 0 0;color:#4b5563;line-height:1.4;">Your application has been received and is now under compliance review. Below is the submitted form summary.</p>
          </div>

          <div style="background:#f9fafb;border:1px solid #e5e7eb;padding:12px 14px;border-radius:8px;margin-bottom:16px;display:flex;gap:14px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><div style="font-size:12px;color:#6b7280;">Application ID</div><div style="font-weight:700;color:#111827;">' . esc_html($app_id) . '</div></div>
            <div style="flex:1;min-width:200px;"><div style="font-size:12px;color:#6b7280;">Application Type</div><div style="font-weight:700;color:#111827;">' . esc_html($type_label) . '</div></div>
            <div style="flex:1;min-width:200px;"><div style="font-size:12px;color:#6b7280;">Submitted At</div><div style="font-weight:700;color:#111827;">' . esc_html($submitted_at) . '</div></div>
          </div>

          <div style="margin-bottom:16px;"><div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">Application Details</div>
            <table style="width:100%;border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;">
              ' . $rows . '
            </table>
          </div>

          <div style="margin-bottom:16px;">
            <div style="font-weight:700;color:#111827;font-size:14px;margin-bottom:8px;">Uploaded Documents</div>
            <ul style="margin:0 0 0 18px;padding:0;color:#111827;">' . $attachmentItems . '</ul>
          </div>

          ' . $signatureHtml . '

          ' . faap_get_banking_policy_html() . '

          <div style="font-size:12px;color:#6b7280;">If you need support, contact <a href="mailto:support@prominencebank.com" style="color:#2563eb;text-decoration:none;">support@prominencebank.com</a>.</div>
        </div>
      </div>
    </div>';
}

function faap_build_application_pdf_html($submission) {
    $app_id = sanitize_text_field($submission['applicationId'] ?? 'N/A');
    $type_label = ucwords(sanitize_text_field($submission['type'] ?? 'personal'));
    $submitted_at = sanitize_text_field($submission['submittedAt'] ?? date('Y-m-d H:i:s'));

    $data = $submission;
    if (isset($submission['applicationData']) && is_string($submission['applicationData'])) {
        $decoded = json_decode($submission['applicationData'], true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    $user_email = esc_html($data['email'] ?? '');
    $logoUrl = faap_get_letterhead_logo_url();

    $rows = '';
    $excluded = ['emailSubject', 'emailBody', 'applicationData', 'mainDocumentFile', 'paymentProofFile', 'companyRegFile', 'signatureImage', 'submittedAt', 'status', 'type', 'accountTypeId', 'applicationId'];
    foreach ($data as $key => $value) {
        if (in_array($key, $excluded, true)) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map('esc_html', $value));
        }
        $rows .= '<tr><td style="padding:6px 8px;border:1px solid #ccd0d5;background:#f7f7f7;font-weight:700;width:28%;">' . esc_html(faap_format_label($key)) . '</td><td style="padding:6px 8px;border:1px solid #ccd0d5;">' . esc_html((string)$value) . '</td></tr>';
    }

    $doc_images = [];
    foreach (['mainDocumentFile', 'paymentProofFile', 'companyRegFile'] as $field) {
        if (!empty($submission[$field])) {
            $doc_images[] = esc_url($submission[$field]);
        }
    }

    $images_html = '';
    foreach ($doc_images as $img) {
        $images_html .= '<div style="margin-top:10px;"> <div style="font-weight:600;margin-bottom:4px;">Document</div><img src="' . $img . '" style="width:260px;border:1px solid #e2e8f0;border-radius:6px;" /> </div>';
    }
    if (empty($images_html)) {
        $images_html = '<p style="color:#6b7280;">No image attachments available.</p>';
    }

    $signatureHtml = '';
    if (!empty($data['signatureImage'])) {
        $signatureHtml = '<div style="margin-top:16px;border:1px solid #e2e8f0;border-radius:6px;background:#f9fafb;padding:10px;"><div style="font-weight:700;margin-bottom:6px;">Applicant Signature</div><img src="' . esc_url($data['signatureImage']) . '" style="max-width:280px;border:1px solid #cbd5e1;border-radius:6px;" /></div>';
    }

    return '<html><head><meta charset="utf-8"><style>body{font-family:Arial,Helvetica,sans-serif;color:#111;}.card{border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:14px;}.details-table{width:100%;border-collapse:collapse;} .details-table td{vertical-align:top;}</style></head><body style="background:#f3f4f6;margin:0;padding:14px;">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:16px 18px;border-bottom:1px solid #e5e7eb;gap:14px;flex-wrap:wrap;">
          <div style="font-size:12px;color:#374151;line-height:1.5;">
            <div><strong>From:</strong> Prominence Bank Corp. &lt;account@prominencebank.com&gt;</div>
            <div><strong>Subject:</strong> Application Submission Receipt</div>
            <div><strong>Date:</strong> ' . esc_html(date('F j, Y \@ H:i', strtotime($submitted_at))) . '</div>
            <div><strong>To:</strong> ' . esc_html($user_email ?: 'N/A') . '</div>
          </div>
          <div style="width:92px;display:flex;justify-content:flex-end;align-items:center;">
            <img src="' . esc_url($logoUrl) . '" alt="Prominence Bank" style="max-height:72px;object-fit:contain;" />
          </div>
        </div>
        <div style="padding:14px 0;">
          <strong>Application ID:</strong> ' . esc_html($app_id) . '<br>
          <strong>Type:</strong> ' . esc_html($type_label) . '<br>
        </div>
        <div style="margin-top:10px;"><h3 style="margin-bottom:8px;">Client Details</h3>
          <table class="details-table">' . $rows . '</table>
        </div>
        <div style="margin-top:16px;"><h3 style="margin-bottom:8px;">Uploaded Documents</h3>' . $images_html . '</div>
        ' . $signatureHtml . '
        <div style="margin-top:18px;font-size:12px;color:#374151;">' . faap_get_banking_policy_html() . '</div>
      </div>
    </body></html>';
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

        $full_body = faap_build_application_html($params);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $user_body = '<div style="font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:700px;margin:0 auto;padding:18px;background:#ffffff;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:1px solid #e5e7eb;gap:12px;">
            <div style="font-size:12px;color:#374151;line-height:1.4;">
              <div><strong>From:</strong> Prominence Bank Corp. &lt;account@prominencebank.com&gt;</div>
              <div><strong>Subject:</strong> Application Submission Receipt</div>
              <div><strong>Date:</strong> ' . date('F j, Y \@ H:i') . '</div>
              <div><strong>To:</strong> ' . esc_html($user_email ?: '') . '</div>
            </div>
            <div style="width:92px;height:92px;display:flex;align-items:center;justify-content:center;">
              <img src="' . $logoUrl . '" alt="Prominence Bank" style="max-width:100%;max-height:100%;object-fit:contain;" />
            </div>
          </div>

          <div style="padding-top:18px;color:#111827;">
            <p style="margin:0 0 10px;">Dear Customer,</p>
            <p style="margin:0 0 10px;color:#374151;">Your application has been received and is under review. A PDF summary of your submission is attached, along with any uploaded documents.</p>
            <p style="margin:0;color:#374151;">Thank you,<br>Prominence Bank Team</p>
          </div>
        </div>';
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

        $applicant_name = $params['fullName'] ?? $params['companyName'] ?? $params['signatoryName'] ?? $params['name'] ?? '';
        return rest_ensure_response([
            'success' => true,
            'id' => $wpdb->insert_id,
            'applicationId' => $application_id,
            'applicantName' => $applicant_name,
            'status' => $params['status'],
        ]);
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
            'applicationId' => $form_data['applicationId'] ?? 'N/A',
            'applicantName' => $form_data['fullName'] ?? $form_data['companyName'] ?? $form_data['signatoryName'] ?? 'N/A',
            'formData' => $form_data
        ];
    }, $applications);
    
    return $formatted_apps;
}

function faap_save_form_config($request) {
    $type = sanitize_text_field($request->get_param('type'));
    $config = $request->get_param('config');
    if (!is_string($config)) {
        return new WP_Error('invalid', 'Invalid config');
    }
    $decoded = json_decode($config, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid', 'Invalid JSON');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'faap_forms';
    $wpdb->replace($table, [
        'form_type' => $type,
        'config' => $config,
    ]);
    return ['success' => true];
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
                ['id' => 'f43', 'label' => 'Passport Photo', 'name' => 'passportPhoto', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f44', 'label' => 'Payment Proof / Transfer Receipt', 'name' => 'paymentProof', 'type' => 'file', 'width' => 'full', 'required' => true],
                ['id' => 'f45', 'label' => 'Full Name / Signature', 'name' => 'fullNameSignature', 'type' => 'text', 'width' => 'full', 'required' => true],
                ['id' => 'f46', 'label' => 'Passport or ID Number', 'name' => 'signatureIdNumber', 'type' => 'text', 'width' => 'half', 'required' => true],
                ['id' => 'f47', 'label' => 'Signature Date', 'name' => 'signatureDate', 'type' => 'date', 'width' => 'half', 'required' => true],
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
    $rows = $wpdb->get_results("SELECT * FROM $table_apps ORDER BY submitted_at DESC");
    ?>
    <div class="wrap faap-admin">
        <div class="faap-header">
            <h1 style="font-family: 'Alegreya', serif; color: #0a192f; margin: 0;">Application Submissions</h1>
            <p style="color: #666; margin: 5px 0 0;">Manage and review submitted applications</p>
        </div>
        <div class="faap-content">
            <table class="wp-list-table widefat fixed striped faap-table">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Applicant Name</th>
                        <th>Type</th>
                        <th>Account Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Details</th>
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
                        <td><span class="faap-badge faap-type"><?php echo strtoupper($row->type); ?></span></td>
                        <td><?php echo esc_html($row->account_type_id); ?></td>
                        <td><span class="faap-status"><?php echo esc_html($row->status); ?></span></td>
                        <td><?php echo $row->submitted_at; ?></td>
                        <td>
                            <button class="button button-small faap-btn" onclick='const data = <?php echo $row->form_data; ?>; console.log(data); alert("Application data logged to console.");'>View Details</button>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="faap-empty">No applications received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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
        $config = trim($_POST['form_config']);
        $decoded = json_decode($config, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $wpdb->replace($table_forms, ['form_type' => $_POST['form_type'], 'config' => $config]);
            $message = 'Form configuration updated successfully.';
            $message_class = 'updated';
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
    document.addEventListener('DOMContentLoaded', () => {
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
    });
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
