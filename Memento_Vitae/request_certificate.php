<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isUserRole((int)$_SESSION["role_id"])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$record_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$message = "";
$message_type = "error";
$form = [
    "purpose" => "",
    "copies_requested" => 1,
    "contact_number" => "",
    "remarks" => ""
];

if ($record_id <= 0) {
    header("Location: user_status.php");
    exit();
}

$record_stmt = mysqli_prepare(
    $conn,
    "SELECT dr.*, u.full_name AS applicant_name, u.email AS applicant_email
     FROM death_records dr
     JOIN users u ON dr.applicant_user_id = u.user_id
     WHERE dr.record_id = ? AND dr.applicant_user_id = ? AND dr.deleted_at IS NULL
     LIMIT 1"
);
mysqli_stmt_bind_param($record_stmt, "ii", $record_id, $user_id);
mysqli_stmt_execute($record_stmt);
$record_res = mysqli_stmt_get_result($record_stmt);
$record = $record_res ? mysqli_fetch_assoc($record_res) : null;
mysqli_stmt_close($record_stmt);

if (!$record) {
    header("Location: user_status.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form["purpose"] = trim($_POST["purpose"] ?? "");
    $form["copies_requested"] = (int)($_POST["copies_requested"] ?? 1);
    $form["contact_number"] = trim($_POST["contact_number"] ?? "");
    $form["remarks"] = trim($_POST["remarks"] ?? "");

    if ($record["status"] !== "Approved") {
        $message = "This record must be approved before a death certificate request can be sent.";
    } else if ($form["purpose"] === "") {
        $message = "Please provide the request purpose.";
    } else if ($form["copies_requested"] < 1 || $form["copies_requested"] > 10) {
        $message = "Copies requested must be between 1 and 10.";
    } else {
        $recipient_email = certificateRequestRecipientEmail();
        $insert = mysqli_prepare(
            $conn,
            "INSERT INTO death_certificate_requests
             (record_id, requester_user_id, recipient_email, purpose, copies_requested, contact_number, remarks, status, email_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted', 'Pending')"
        );
        mysqli_stmt_bind_param(
            $insert,
            "iississ",
            $record_id,
            $user_id,
            $recipient_email,
            $form["purpose"],
            $form["copies_requested"],
            $form["contact_number"],
            $form["remarks"]
        );

        if (!mysqli_stmt_execute($insert)) {
            $message = "Unable to save the certificate request: " . mysqli_error($conn);
        } else {
            $request_id = mysqli_insert_id($conn);
            $request_payload = [
                "purpose" => $form["purpose"],
                "copies_requested" => $form["copies_requested"],
                "contact_number" => $form["contact_number"],
                "remarks" => $form["remarks"]
            ];
            [$sent, $mail_error] = sendCertificateRequestEmail(
                $request_payload,
                $record,
                [
                    "full_name" => $record["applicant_name"],
                    "email" => $record["applicant_email"]
                ]
            );

            $email_status = $sent ? "Sent" : "Failed";
            $status_update = mysqli_prepare(
                $conn,
                "UPDATE death_certificate_requests
                 SET email_status = ?, email_error = ?, emailed_at = CASE WHEN ? = 'Sent' THEN NOW() ELSE NULL END
                 WHERE request_id = ?"
            );
            mysqli_stmt_bind_param($status_update, "sssi", $email_status, $mail_error, $email_status, $request_id);
            mysqli_stmt_execute($status_update);
            mysqli_stmt_close($status_update);

            $details = "A death certificate request was forwarded to the local civil registry for " . $record["deceased_name"] . ".";
            $remarks_for_log = "Purpose: " . $form["purpose"] . " | Copies: " . $form["copies_requested"];
            if ($form["contact_number"] !== "") {
                $remarks_for_log .= " | Contact: " . $form["contact_number"];
            }

            logRecordActivity($record_id, $user_id, "certificate_requested", $details, $record["status"], $record["status"], $remarks_for_log, $user_id);

            if ($sent) {
                createNotification(
                    $user_id,
                    "Certificate request sent",
                    "Your death certificate request for " . $record["deceased_name"] . " was sent to the local civil registry.",
                    $record_id
                );
                $message = "Certificate request sent successfully to the local civil registry.";
                $message_type = "success";
                $form = [
                    "purpose" => "",
                    "copies_requested" => 1,
                    "contact_number" => "",
                    "remarks" => ""
                ];
            } else {
                createNotification(
                    $user_id,
                    "Certificate request saved",
                    "Your request for " . $record["deceased_name"] . " was saved, but the email relay failed. Please check SMTP settings.",
                    $record_id
                );
                $message = "The request was saved, but the email could not be delivered yet. " . ($mail_error !== "" ? $mail_error : "Check the SMTP settings.");
            }
        }

        mysqli_stmt_close($insert);
    }
}

$requests_stmt = mysqli_prepare(
    $conn,
    "SELECT request_id, purpose, copies_requested, contact_number, remarks, status, email_status, email_error, submitted_at, emailed_at
     FROM death_certificate_requests
     WHERE record_id = ? AND requester_user_id = ?
     ORDER BY submitted_at DESC, request_id DESC"
);
mysqli_stmt_bind_param($requests_stmt, "ii", $record_id, $user_id);
mysqli_stmt_execute($requests_stmt);
$requests_res = mysqli_stmt_get_result($requests_stmt);
$request_history = [];
while ($request = mysqli_fetch_assoc($requests_res)) {
    $request_history[] = $request;
}
mysqli_stmt_close($requests_stmt);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Request Certificate</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">
  <div class="page-shell">
    <div class="card" style="max-width:920px; text-align:left;">
      <h1 style="text-align:center;">Memento Vitae</h1>
      <h2 style="text-align:center;">Request Death Certificate</h2>

      <?php if ($message !== "") { ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
      <?php } ?>

      <div class="panel" style="margin-top:16px;">
        <h3>Record Reference</h3>
        <div class="request-meta">
          <div><span class="label">Reference Code</span><br><?php echo e($record["tracking_code"]); ?></div>
          <div><span class="label">Deceased Name</span><br><?php echo e($record["deceased_name"]); ?></div>
          <div><span class="label">Current Status</span><br><span class="badge status-<?php echo strtolower(e($record["status"])); ?>"><?php echo e($record["status"]); ?></span></div>
          <div><span class="label">Registry Email</span><br><?php echo e(certificateRequestRecipientEmail()); ?></div>
        </div>
      </div>

      <div class="panel" style="margin-top:18px;">
        <h3>Forward Request to Local Civil Registry</h3>
        <p class="muted" style="margin:0 0 12px;">
          This sends the bereaved family's request details to the local civil registry so the certificate request is already carried forward from the portal.
        </p>

        <?php if ($record["status"] !== "Approved") { ?>
          <div class="table-empty">This action unlocks once the record is approved.</div>
        <?php } else { ?>
          <form method="POST">
            <label class="note">Purpose of Request</label>
            <input type="text" name="purpose" placeholder="Example: Burial claim, insurance, family copy" value="<?php echo e($form["purpose"]); ?>" required>

            <label class="note">Copies Requested</label>
            <input type="number" name="copies_requested" min="1" max="10" value="<?php echo e((string)$form["copies_requested"]); ?>" required>

            <label class="note">Contact Number</label>
            <input type="text" name="contact_number" placeholder="Optional mobile or landline" value="<?php echo e($form["contact_number"]); ?>">

            <label class="note">Additional Remarks</label>
            <textarea name="remarks" rows="4" placeholder="Optional instructions for the civil registry."><?php echo e($form["remarks"]); ?></textarea>

            <button type="submit">Send Certificate Request</button>
          </form>
        <?php } ?>
      </div>

      <div class="panel" style="margin-top:18px;">
        <h3>Request History</h3>
        <?php if (!empty($request_history)) { ?>
          <div class="request-list">
            <?php foreach ($request_history as $request) { ?>
              <div class="request-card">
                <div class="request-card-header">
                  <strong><?php echo e($request["purpose"]); ?></strong>
                  <span class="badge"><?php echo e($request["status"]); ?></span>
                </div>
                <div class="muted">Submitted: <?php echo e($request["submitted_at"]); ?></div>
                <div class="muted">Copies Requested: <?php echo e($request["copies_requested"]); ?></div>
                <div class="muted">Email Delivery: <?php echo e($request["email_status"]); ?><?php if (!empty($request["emailed_at"])) { ?> on <?php echo e($request["emailed_at"]); ?><?php } ?></div>
                <?php if (!empty($request["contact_number"])) { ?>
                  <div class="muted">Contact Number: <?php echo e($request["contact_number"]); ?></div>
                <?php } ?>
                <?php if (!empty($request["remarks"])) { ?>
                  <div style="margin-top:8px;"><?php echo e($request["remarks"]); ?></div>
                <?php } ?>
                <?php if (!empty($request["email_error"])) { ?>
                  <div class="alert alert-error" style="margin-top:10px;">Email relay note: <?php echo e($request["email_error"]); ?></div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
        <?php } else { ?>
          <div class="table-empty">No death certificate requests have been sent yet for this record.</div>
        <?php } ?>
      </div>

      <div style="text-align:center; margin-top:18px;" class="inline-actions">
        <a class="smallbtn" href="user_record_view.php?id=<?php echo $record_id; ?>">Back to Record</a>
        <a class="smallbtn" href="user_status.php">Back to Status List</a>
      </div>
    </div>
  </div>
</body>
</html>
