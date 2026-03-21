<?php
// includes/db.php

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/oauth_config.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

$db_host = getenv("DB_HOST") ?: "localhost";
$db_name = getenv("DB_NAME") ?: "mementovitae";
$db_user = getenv("DB_USER") ?: "root";
$db_pass = getenv("DB_PASSWORD");
if ($db_pass === false) {
    $db_pass = "";
}
$db_port = getenv("DB_PORT");
$db_port = $db_port !== false && $db_port !== "" ? (int)$db_port : 3306;

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if (!$conn) {
    die("DB connection failed: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined("ROLE_ADMIN")) define("ROLE_ADMIN", 1);
if (!defined("ROLE_BARANGAY_STAFF")) define("ROLE_BARANGAY_STAFF", 2);
if (!defined("ROLE_USER")) define("ROLE_USER", 3);

if (!function_exists("roleName")) {
    function roleMap() {
        global $conn;

        static $role_map = null;
        if ($role_map !== null) {
            return $role_map;
        }

        $role_map = [
            ROLE_ADMIN => "Admin",
            ROLE_BARANGAY_STAFF => "Barangay Staff",
            ROLE_USER => "User"
        ];

        $res = @mysqli_query($conn, "SELECT role_id, role_name FROM roles ORDER BY role_id ASC");
        if ($res) {
            $db_map = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $db_map[(int)$row["role_id"]] = $row["role_name"];
            }
            if (!empty($db_map)) {
                $role_map = $db_map;
            }
        }

        return $role_map;
    }

    function roleName($rid) {
        $rid = (int)$rid;
        $map = roleMap();
        return $map[$rid] ?? "Role " . $rid;
    }
}

if (!function_exists("isAdminRole")) {
    function isAdminRole($rid) {
        return (int)$rid === ROLE_ADMIN;
    }
}

if (!function_exists("isUserRole")) {
    function isUserRole($rid) {
        return (int)$rid === ROLE_USER;
    }
}

if (!function_exists("isStaffRole")) {
    function isStaffRole($rid) {
        $rid = (int)$rid;
        return !$rid ? false : !isAdminRole($rid) && !isUserRole($rid);
    }
}

if (!function_exists("statusOptions")) {
    function statusOptions() {
        return ["Pending", "Verified", "Approved", "Rejected"];
    }
}

if (!function_exists("documentTypeOptions")) {
    function documentTypeOptions() {
        return [
            "Medical Certificate",
            "Autopsy Report",
            "Valid ID",
            "Supporting Affidavit"
        ];
    }
}

if (!function_exists("primaryProofDocumentTypes")) {
    function primaryProofDocumentTypes() {
        return ["Medical Certificate", "Autopsy Report"];
    }
}

if (!function_exists("documentReviewOptions")) {
    function documentReviewOptions() {
        return ["Pending Review", "Valid", "Needs Replacement", "Rejected"];
    }
}

if (!function_exists("e")) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("setFlash")) {
    function setFlash($key, $value) {
        $_SESSION["_flash"][$key] = $value;
    }
}

if (!function_exists("getFlash")) {
    function getFlash($key, $default = null) {
        if (!isset($_SESSION["_flash"][$key])) {
            return $default;
        }

        $value = $_SESSION["_flash"][$key];
        unset($_SESSION["_flash"][$key]);
        return $value;
    }
}

if (!function_exists("stmtBindParams")) {
    function stmtBindParams($stmt, $types, array $params) {
        if ($types === "" || empty($params)) {
            return true;
        }

        $args = [$stmt, $types];
        foreach ($params as $key => $value) {
            $args[] = &$params[$key];
        }

        return call_user_func_array("mysqli_stmt_bind_param", $args);
    }
}

if (!function_exists("createNotification")) {
    function createNotification($user_id, $title, $message, $record_id = null) {
        global $conn;

        $user_id = (int)$user_id;
        $record_id = $record_id !== null ? (int)$record_id : 0;
        if ($user_id <= 0) {
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, related_record_id, title, message)
             VALUES (?, NULLIF(?, 0), ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $record_id, $title, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists("logRecordActivity")) {
    function logRecordActivity($record_id, $actor_user_id, $action_type, $details = "", $old_status = null, $new_status = null, $remarks = "", $affected_user_id = null) {
        global $conn;

        $record_id = $record_id !== null ? (int)$record_id : 0;
        $actor_user_id = (int)$actor_user_id;
        $affected_user_id = $affected_user_id !== null ? (int)$affected_user_id : 0;

        if ($actor_user_id <= 0 || $action_type === "") {
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO record_activity_logs
             (related_record_id, actor_user_id, affected_user_id, action_type, old_status, new_status, remarks, details)
             VALUES (NULLIF(?, 0), ?, NULLIF(?, 0), ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "iiisssss",
            $record_id,
            $actor_user_id,
            $affected_user_id,
            $action_type,
            $old_status,
            $new_status,
            $remarks,
            $details
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists("unreadNotificationCount")) {
    function unreadNotificationCount($user_id) {
        global $conn;

        $user_id = (int)$user_id;
        if ($user_id <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $row ? (int)$row["total"] : 0;
    }
}

if (!function_exists("googleAuthConfigured")) {
    function googleAuthConfigured() {
        return defined("GOOGLE_CLIENT_ID")
            && GOOGLE_CLIENT_ID !== ""
            && strpos(GOOGLE_CLIENT_ID, "YOUR_GOOGLE_CLIENT_ID") !== 0;
    }
}

if (!function_exists("appUrl")) {
    function appUrl($path = "") {
        $base = rtrim(APP_URL, "/");
        $path = ltrim((string)$path, "/");
        return $path === "" ? $base : $base . "/" . $path;
    }
}

if (!function_exists("issueTokenLink")) {
    function issueTokenLink($table, $user_id, $target_path, $ttl_hours = 24) {
        global $conn;

        $user_id = (int)$user_id;
        if ($user_id <= 0) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $token_hash = hash("sha256", $token);
        $expires_at = date("Y-m-d H:i:s", time() + ($ttl_hours * 3600));

        $cleanup = mysqli_prepare($conn, "UPDATE {$table} SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
        mysqli_stmt_bind_param($cleanup, "i", $user_id);
        mysqli_stmt_execute($cleanup);
        mysqli_stmt_close($cleanup);

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO {$table} (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $token_hash, $expires_at);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return appUrl($target_path . "?token=" . urlencode($token));
    }
}

if (!function_exists("createEmailVerificationLink")) {
    function createEmailVerificationLink($user_id) {
        return issueTokenLink("email_verification_tokens", $user_id, "verify_email.php", 24);
    }
}

if (!function_exists("createPasswordResetLink")) {
    function createPasswordResetLink($user_id) {
        return issueTokenLink("password_reset_tokens", $user_id, "reset_password.php", 2);
    }
}

if (!function_exists("findValidTokenRecord")) {
    function findValidTokenRecord($table, $raw_token) {
        global $conn;

        $raw_token = trim((string)$raw_token);
        if ($raw_token === "") {
            return null;
        }

        $token_hash = hash("sha256", $raw_token);
        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM {$table}
             WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW()
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $token_hash);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $row;
    }
}

if (!function_exists("markTokenUsed")) {
    function markTokenUsed($table, $token_id_column, $token_id) {
        global $conn;

        $token_id = (int)$token_id;
        $stmt = mysqli_prepare($conn, "UPDATE {$table} SET used_at = NOW() WHERE {$token_id_column} = ?");
        mysqli_stmt_bind_param($stmt, "i", $token_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists("queueDeliveryLink")) {
    function queueDeliveryLink($title, $link, $message = "") {
        // Backward-compatible no-op now that live SMTP delivery is used.
        return false;
    }
}

if (!function_exists("mailConfigured")) {
    function mailConfigured() {
        return MAIL_DELIVERY_MODE === "smtp" && MAIL_CONFIGURED;
    }
}

if (!function_exists("sendMailMessage")) {
    function sendMailMessage($to_email, $to_name, $subject, $html_body, $plain_body = "") {
        if (!mailConfigured()) {
            return [false, "SMTP mail is not configured yet."];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->Port = (int)MAIL_PORT;

            if (MAIL_ENCRYPTION === "ssl") {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            if (MAIL_ALLOW_INSECURE) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to_email, $to_name ?: $to_email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_body;
            $mail->AltBody = $plain_body !== "" ? $plain_body : strip_tags($html_body);
            $mail->send();

            return [true, ""];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}

if (!function_exists("deliverActionLink")) {
    function deliverActionLink($to_email, $to_name, $subject, $intro_text, $link) {
        $safe_intro = e($intro_text);
        $safe_link = e($link);
        $html = "
            <div style=\"font-family:Arial,sans-serif;color:#111;line-height:1.6;\">
                <h2 style=\"margin-bottom:12px;\">Memento Vitae</h2>
                <p>{$safe_intro}</p>
                <p><a href=\"{$safe_link}\" style=\"display:inline-block;padding:12px 18px;background:#b30000;color:#fff;text-decoration:none;border-radius:8px;\">Open Secure Link</a></p>
                <p style=\"font-size:13px;color:#666;\">If the button does not work, use this link:<br>{$safe_link}</p>
            </div>
        ";
        $plain = $intro_text . "\n\n" . $link;
        return sendMailMessage($to_email, $to_name, $subject, $html, $plain);
    }
}

if (!function_exists("certificateRequestRecipientEmail")) {
    function certificateRequestRecipientEmail() {
        return trim((string)LCR_REQUEST_EMAIL);
    }
}

if (!function_exists("sendCertificateRequestEmail")) {
    function sendCertificateRequestEmail(array $request, array $record, array $requester) {
        $recipient_email = certificateRequestRecipientEmail();
        if ($recipient_email === "") {
            return [false, "The local civil registry email address is not configured."];
        }

        $requester_name = $requester["full_name"] ?? "Applicant";
        $requester_email = $requester["email"] ?? "";
        $tracking_code = $record["tracking_code"] ?? "";
        $deceased_name = $record["deceased_name"] ?? "";
        $purpose = $request["purpose"] ?? "";
        $copies = (int)($request["copies_requested"] ?? 1);
        $contact_number = trim((string)($request["contact_number"] ?? ""));
        $remarks = trim((string)($request["remarks"] ?? ""));

        $subject = "Death Certificate Request - " . $deceased_name . " (" . $tracking_code . ")";
        $html = "
            <div style=\"font-family:Arial,sans-serif;color:#111;line-height:1.6;\">
                <h2 style=\"margin-bottom:12px;\">Memento Vitae - Certificate Request</h2>
                <p>A bereaved family member submitted a request for a death certificate through the Memento Vitae portal.</p>
                <table style=\"border-collapse:collapse;width:100%;margin:18px 0;\">
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Reference Code</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($tracking_code) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Deceased Name</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($deceased_name) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Date of Death</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($record["date_of_death"] ?? "") . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Place of Death</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($record["place_of_death"] ?? "") . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Applicant Name</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($requester_name) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Applicant Email</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($requester_email) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Contact Number</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($contact_number !== "" ? $contact_number : "Not provided") . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Copies Requested</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e((string)$copies) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Purpose</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . e($purpose) . "</td></tr>
                    <tr><td style=\"padding:8px;border:1px solid #ddd;\"><b>Remarks</b></td><td style=\"padding:8px;border:1px solid #ddd;\">" . nl2br(e($remarks !== "" ? $remarks : "None")) . "</td></tr>
                </table>
                <p>Please coordinate directly with the applicant using the contact details above.</p>
            </div>
        ";
        $plain = "Death certificate request\n"
            . "Reference Code: " . $tracking_code . "\n"
            . "Deceased Name: " . $deceased_name . "\n"
            . "Date of Death: " . ($record["date_of_death"] ?? "") . "\n"
            . "Place of Death: " . ($record["place_of_death"] ?? "") . "\n"
            . "Applicant Name: " . $requester_name . "\n"
            . "Applicant Email: " . $requester_email . "\n"
            . "Contact Number: " . ($contact_number !== "" ? $contact_number : "Not provided") . "\n"
            . "Copies Requested: " . $copies . "\n"
            . "Purpose: " . $purpose . "\n"
            . "Remarks: " . ($remarks !== "" ? $remarks : "None");

        return sendMailMessage($recipient_email, LCR_REQUEST_NAME, $subject, $html, $plain);
    }
}

if (!function_exists("httpGetJson")) {
    function httpGetJson($url) {
        $response = false;

        if (function_exists("curl_init")) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
        }

        if ($response === false) {
            $context = stream_context_create([
                "http" => [
                    "timeout" => 10
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
        }

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists("verifyGoogleCredential")) {
    function verifyGoogleCredential($credential) {
        $credential = trim((string)$credential);
        if ($credential === "") {
            return [false, "Missing Google credential.", null];
        }

        if (!googleAuthConfigured()) {
            return [false, "Google login is not configured yet.", null];
        }

        // For a lightweight localhost setup, validate the ID token with Google's tokeninfo endpoint.
        $tokeninfo = httpGetJson("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential));
        if (!$tokeninfo || isset($tokeninfo["error_description"]) || isset($tokeninfo["error"])) {
            return [false, "Google token validation failed.", null];
        }

        $aud = $tokeninfo["aud"] ?? "";
        $iss = $tokeninfo["iss"] ?? "";
        $email = trim((string)($tokeninfo["email"] ?? ""));
        $email_verified = (string)($tokeninfo["email_verified"] ?? "");

        if ($aud !== GOOGLE_CLIENT_ID) {
            return [false, "Google client ID mismatch.", null];
        }

        if ($iss !== "accounts.google.com" && $iss !== "https://accounts.google.com") {
            return [false, "Invalid Google issuer.", null];
        }

        if ($email === "" || !in_array($email_verified, ["true", "1"], true)) {
            return [false, "Google account email is not verified.", null];
        }

        return [true, "", $tokeninfo];
    }
}

if (!function_exists("findUserByEmail")) {
    function findUserByEmail($email) {
        global $conn;

        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, password, role_id, status, email_verified_at FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $user;
    }
}

if (!function_exists("findUserBySocialAccount")) {
    function findUserBySocialAccount($provider, $provider_user_id) {
        global $conn;

        $stmt = mysqli_prepare(
            $conn,
            "SELECT u.user_id, u.full_name, u.email, u.password, u.role_id, u.status, u.email_verified_at
             FROM social_accounts sa
             JOIN users u ON sa.user_id = u.user_id
             WHERE sa.provider = ? AND sa.provider_user_id = ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $provider, $provider_user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $user;
    }
}

if (!function_exists("upsertSocialAccount")) {
    function upsertSocialAccount($user_id, $provider, $provider_user_id, $provider_email = null) {
        global $conn;

        $user_id = (int)$user_id;
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email, last_login_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                provider_email = VALUES(provider_email),
                last_login_at = NOW()"
        );
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $provider, $provider_user_id, $provider_email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists("createSocialUser")) {
    function createSocialUser($full_name, $email, $role_id = ROLE_USER) {
        global $conn;

        $generated_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users (full_name, email, password, role_id, status, email_verified_at)
             VALUES (?, ?, ?, ?, 'active', NOW())"
        );
        mysqli_stmt_bind_param($stmt, "sssi", $full_name, $email, $generated_password, $role_id);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        return $user_id;
    }
}

if (!function_exists("generateTrackingCode")) {
    function generateTrackingCode() {
        return "DN-" . date("Ymd") . "-" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists("recordDocumentStoragePath")) {
    function recordDocumentStoragePath() {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "record_documents";
    }
}

if (!function_exists("ensureRecordDocumentStorage")) {
    function ensureRecordDocumentStorage() {
        $path = recordDocumentStoragePath();
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return is_dir($path);
    }
}

if (!function_exists("saveUploadedRecordDocument")) {
    function saveUploadedRecordDocument($record_id, $uploaded_by, $document_type, array $file) {
        global $conn;

        $record_id = (int)$record_id;
        $uploaded_by = (int)$uploaded_by;
        $document_type = trim((string)$document_type);

        if ($record_id <= 0 || $uploaded_by <= 0) {
            return [false, "Invalid upload request.", null];
        }

        if (!in_array($document_type, documentTypeOptions(), true)) {
            return [false, "Please choose a valid document type.", null];
        }

        if (!isset($file["error"]) || (int)$file["error"] !== UPLOAD_ERR_OK) {
            return [false, "Please upload a file successfully before submitting.", null];
        }

        if (($file["size"] ?? 0) > 5 * 1024 * 1024) {
            return [false, "Files must be 5 MB or smaller.", null];
        }

        $original_name = basename((string)($file["name"] ?? "document"));
        $extension = strtolower((string)pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ["pdf", "jpg", "jpeg", "png"];
        if (!in_array($extension, $allowed_extensions, true)) {
            return [false, "Only PDF, JPG, JPEG, and PNG files are allowed.", null];
        }

        $mime_type = "";
        if (function_exists("finfo_open")) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime_type = (string)finfo_file($finfo, $file["tmp_name"]);
                finfo_close($finfo);
            }
        }

        $allowed_mimes = [
            "pdf" => ["application/pdf"],
            "jpg" => ["image/jpeg"],
            "jpeg" => ["image/jpeg"],
            "png" => ["image/png"]
        ];
        if ($mime_type !== "" && !in_array($mime_type, $allowed_mimes[$extension], true)) {
            return [false, "The uploaded file type does not match the file extension.", null];
        }

        if (!ensureRecordDocumentStorage()) {
            return [false, "The document storage folder is not available.", null];
        }

        $stored_name = "record_" . $record_id . "_" . date("YmdHis") . "_" . bin2hex(random_bytes(6)) . "." . $extension;
        $relative_path = "storage/record_documents/" . $stored_name;
        $absolute_path = recordDocumentStoragePath() . DIRECTORY_SEPARATOR . $stored_name;

        if (!move_uploaded_file($file["tmp_name"], $absolute_path)) {
            return [false, "The document could not be saved on the server.", null];
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO record_documents
             (record_id, document_type, original_file_name, stored_file_name, file_path, mime_type, file_size, uploaded_by, review_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending Review')"
        );
        $file_size = (int)($file["size"] ?? 0);
        mysqli_stmt_bind_param(
            $stmt,
            "isssssii",
            $record_id,
            $document_type,
            $original_name,
            $stored_name,
            $relative_path,
            $mime_type,
            $file_size,
            $uploaded_by
        );
        mysqli_stmt_execute($stmt);
        $document_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        return [true, "", $document_id];
    }
}

if (!function_exists("canVerifyRecord")) {
    function canVerifyRecord($record_id, &$reason = "") {
        global $conn;

        $record_id = (int)$record_id;
        $primary_types = primaryProofDocumentTypes();
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                SUM(CASE WHEN document_type IN (?, ?) THEN 1 ELSE 0 END) AS total_primary,
                SUM(CASE WHEN document_type IN (?, ?) AND review_status = 'Valid' THEN 1 ELSE 0 END) AS valid_primary,
                SUM(CASE WHEN document_type IN (?, ?) AND review_status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_primary,
                SUM(CASE WHEN document_type IN (?, ?) AND review_status = 'Needs Replacement' THEN 1 ELSE 0 END) AS replacement_primary
             FROM record_documents
             WHERE record_id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssi",
            $primary_types[0],
            $primary_types[1],
            $primary_types[0],
            $primary_types[1],
            $primary_types[0],
            $primary_types[1],
            $primary_types[0],
            $primary_types[1],
            $record_id
        );
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : [];
        mysqli_stmt_close($stmt);

        $total_primary = (int)($row["total_primary"] ?? 0);
        $valid_primary = (int)($row["valid_primary"] ?? 0);
        $pending_primary = (int)($row["pending_primary"] ?? 0);
        $replacement_primary = (int)($row["replacement_primary"] ?? 0);

        if ($total_primary <= 0) {
            $reason = "Upload at least one Medical Certificate or Autopsy Report before verification.";
            return false;
        }

        if ($pending_primary > 0) {
            $reason = "Supporting documents are still pending review.";
            return false;
        }

        if ($replacement_primary > 0) {
            $reason = "Some supporting documents need replacement before the record can be verified.";
            return false;
        }

        if ($valid_primary <= 0) {
            $reason = "No Medical Certificate or Autopsy Report has been marked valid yet.";
            return false;
        }

        $reason = "";
        return true;
    }
}

if (!function_exists("loginUserSession")) {
    function loginUserSession(array $user) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int)$user["user_id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["role_id"] = (int)$user["role_id"];
    }
}
?>
