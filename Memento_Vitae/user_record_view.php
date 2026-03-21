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

if (!isset($_GET["id"])) {
    header("Location: user_status.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$id = (int)$_GET["id"];
$message = "";
$message_type = "error";

$sql = "SELECT *
        FROM death_records
        WHERE record_id = ? AND applicant_user_id = ? AND deleted_at IS NULL
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$record = mysqli_fetch_assoc($res);

if (!$record) {
    header("Location: user_status.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload_document") {
    $document_type = trim($_POST["document_type"] ?? "");
    [$ok, $upload_error, $document_id] = saveUploadedRecordDocument($id, $user_id, $document_type, $_FILES["supporting_document"] ?? []);

    if ($ok) {
        $message = "Supporting document uploaded successfully. It is now waiting for staff review.";
        $message_type = "success";
        logRecordActivity(
            $id,
            $user_id,
            "document_uploaded",
            $document_type . " uploaded for verification review.",
            $record["status"],
            $record["status"],
            "",
            $user_id
        );
    } else {
        $message = $upload_error;
    }
}

$requests_stmt = mysqli_prepare(
    $conn,
    "SELECT
        request_id,
        purpose,
        copies_requested,
        contact_number,
        remarks,
        status,
        email_status,
        email_error,
        submitted_at,
        emailed_at
     FROM death_certificate_requests
     WHERE record_id = ? AND requester_user_id = ?
     ORDER BY submitted_at DESC, request_id DESC
     LIMIT 5"
);
mysqli_stmt_bind_param($requests_stmt, "ii", $id, $user_id);
mysqli_stmt_execute($requests_stmt);
$requests_res = mysqli_stmt_get_result($requests_stmt);
$certificate_requests = [];
while ($request = mysqli_fetch_assoc($requests_res)) {
    $certificate_requests[] = $request;
}
mysqli_stmt_close($requests_stmt);

$documents_stmt = mysqli_prepare(
    $conn,
    "SELECT
        rd.*,
        uploader.full_name AS uploader_name,
        reviewer.full_name AS reviewer_name
     FROM record_documents rd
     JOIN users uploader ON rd.uploaded_by = uploader.user_id
     LEFT JOIN users reviewer ON rd.reviewed_by = reviewer.user_id
     WHERE rd.record_id = ?
     ORDER BY rd.uploaded_at DESC, rd.document_id DESC"
);
mysqli_stmt_bind_param($documents_stmt, "i", $id);
mysqli_stmt_execute($documents_stmt);
$documents_res = mysqli_stmt_get_result($documents_stmt);
$documents = [];
while ($document = mysqli_fetch_assoc($documents_res)) {
    $documents[] = $document;
}
mysqli_stmt_close($documents_stmt);

$can_request_certificate = $record["status"] === "Approved";

$log_stmt = mysqli_prepare(
    $conn,
    "SELECT
        l.action_type,
        l.old_status,
        l.new_status,
        l.remarks,
        l.details,
        l.created_at,
        u.full_name AS actor_name,
        u.role_id AS actor_role
     FROM record_activity_logs l
     JOIN users u ON l.actor_user_id = u.user_id
     WHERE l.related_record_id = ?
     ORDER BY l.created_at DESC, l.log_id DESC
     LIMIT 8"
);
mysqli_stmt_bind_param($log_stmt, "i", $id);
mysqli_stmt_execute($log_stmt);
$log_res = mysqli_stmt_get_result($log_stmt);
$activity_logs = [];
while ($log = mysqli_fetch_assoc($log_res)) {
    $activity_logs[] = $log;
}
mysqli_stmt_close($log_stmt);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - View Record</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .row { margin: 10px 0; }
    .label { color:#bdbdbd; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
  </style>
</head>
<body class="app-content-page">

<div class="page-shell">
  <div class="card" style="max-width:none; text-align:left;">
    <h1 style="text-align:center;">Memento Vitae</h1>
    <h2 style="text-align:center;">Record Details</h2>

    <?php if ($message !== "") { ?>
      <div class="alert alert-<?php echo e($message_type); ?>">
        <?php echo e($message); ?>
      </div>
    <?php } ?>

    <div class="panel" style="margin-top:16px;">
      <h3>Application Overview</h3>
      <div class="row"><div class="label">Record ID</div><?php echo $record["record_id"]; ?></div>
      <div class="row"><div class="label">Reference Code</div><?php echo e($record["tracking_code"]); ?></div>
      <div class="row"><div class="label">Deceased Name</div><?php echo htmlspecialchars($record["deceased_name"]); ?></div>
      <div class="row"><div class="label">Date of Death</div><?php echo $record["date_of_death"]; ?></div>
      <div class="row"><div class="label">Place of Death</div><?php echo htmlspecialchars($record["place_of_death"]); ?></div>
      <div class="row"><div class="label">Cause of Death</div><?php echo htmlspecialchars($record["cause_of_death"]); ?></div>
      <div class="row"><div class="label">Informant Name</div><?php echo htmlspecialchars($record["informant_name"]); ?></div>
      <div class="row"><div class="label">Relationship</div><?php echo htmlspecialchars($record["relationship"]); ?></div>
      <div class="row"><div class="label">Status</div><span class="badge status-<?php echo strtolower(e($record["status"])); ?>"><?php echo htmlspecialchars($record["status"]); ?></span></div>
      <div class="row"><div class="label">Date Submitted</div><?php echo $record["date_submitted"]; ?></div>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Supporting Documents</h3>
      <p class="muted" style="margin:0 0 12px;">
        Upload the proof documents that the barangay staff needs to review before the record can be verified.
      </p>
      <ul class="helper-list">
        <li>At least one <b>Medical Certificate</b> or <b>Autopsy Report</b> must be uploaded and marked valid before verification.</li>
        <li>If staff marks a file as <b>Needs Replacement</b>, upload a corrected copy here.</li>
        <li>Accepted formats: PDF, JPG, JPEG, PNG. Max size: 5 MB.</li>
      </ul>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_document">

        <label class="note">Document Type</label>
        <select name="document_type" required>
          <option value="">Select document type...</option>
          <?php foreach (documentTypeOptions() as $type_option) { ?>
            <option value="<?php echo e($type_option); ?>"><?php echo e($type_option); ?></option>
          <?php } ?>
        </select>

        <label class="note">Upload File</label>
        <input type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png" required>

        <button type="submit">Upload Document</button>
      </form>

      <?php if (!empty($documents)) { ?>
        <div class="document-grid">
          <?php foreach ($documents as $document) { ?>
            <?php $status_class = strtolower(str_replace(" ", "-", (string)$document["review_status"])); ?>
            <div class="document-card">
              <div class="document-card-header">
                <div>
                  <strong><?php echo e($document["document_type"]); ?></strong>
                  <div class="muted"><?php echo e($document["original_file_name"]); ?></div>
                </div>
                <span class="badge document-status-<?php echo e($status_class); ?>"><?php echo e($document["review_status"]); ?></span>
              </div>
              <div class="document-meta">
                <div class="muted">Uploaded by: <?php echo e($document["uploader_name"]); ?> on <?php echo e($document["uploaded_at"]); ?></div>
                <?php if (!empty($document["reviewed_at"])) { ?>
                  <div class="muted">Reviewed by: <?php echo e($document["reviewer_name"]); ?> on <?php echo e($document["reviewed_at"]); ?></div>
                <?php } ?>
                <?php if (!empty($document["review_notes"])) { ?>
                  <div>Review Notes: <?php echo e($document["review_notes"]); ?></div>
                <?php } ?>
              </div>
              <div class="document-actions">
                <a class="smallbtn" href="document_view.php?id=<?php echo (int)$document["document_id"]; ?>">Open File</a>
              </div>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="table-empty">No supporting documents uploaded yet.</div>
      <?php } ?>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Death Certificate Request</h3>
      <p class="muted" style="margin:0 0 12px;">
        Once the application is approved, you can forward a request to the local civil registry so the bereaved family has less manual paperwork to handle.
      </p>

      <?php if ($can_request_certificate) { ?>
        <div class="inline-actions" style="margin-bottom:12px;">
          <a class="smallbtn" href="request_certificate.php?id=<?php echo (int)$record["record_id"]; ?>">Request Certificate</a>
          <span class="note">Destination: <?php echo e(certificateRequestRecipientEmail()); ?></span>
        </div>
      <?php } else { ?>
        <div class="table-empty">Certificate requests open once this record reaches Approved status.</div>
      <?php } ?>

      <?php if (!empty($certificate_requests)) { ?>
        <div class="request-list">
          <?php foreach ($certificate_requests as $request) { ?>
            <div class="request-card">
              <div class="request-card-header">
                <strong><?php echo e($request["purpose"]); ?></strong>
                <span class="badge"><?php echo e($request["status"]); ?></span>
              </div>
              <div class="muted">Submitted: <?php echo e($request["submitted_at"]); ?></div>
              <div class="muted">Copies Requested: <?php echo e($request["copies_requested"]); ?></div>
              <?php if (!empty($request["contact_number"])) { ?>
                <div class="muted">Contact Number: <?php echo e($request["contact_number"]); ?></div>
              <?php } ?>
              <div class="muted">Email Delivery: <?php echo e($request["email_status"]); ?><?php if (!empty($request["emailed_at"])) { ?> on <?php echo e($request["emailed_at"]); ?><?php } ?></div>
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
        <div class="table-empty">No certificate request has been sent yet for this record.</div>
      <?php } ?>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Status Timeline</h3>
      <?php if (!empty($activity_logs)) { ?>
        <div class="timeline">
          <?php foreach ($activity_logs as $log) { ?>
            <div class="timeline-item">
              <h4><?php echo e(ucwords(str_replace("_", " ", $log["action_type"]))); ?></h4>
              <div class="timeline-meta">
                <?php echo e($log["actor_name"]); ?> (<?php echo e(roleName((int)$log["actor_role"])); ?>)
                | <?php echo e($log["created_at"]); ?>
              </div>
              <?php if (!empty($log["details"])) { ?>
                <div><?php echo e($log["details"]); ?></div>
              <?php } ?>
              <?php if (!empty($log["remarks"])) { ?>
                <div class="muted" style="margin-top:8px;">Remarks: <?php echo e($log["remarks"]); ?></div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="table-empty">No workflow updates have been logged yet.</div>
      <?php } ?>
    </div>

    <div style="text-align:center; margin-top:14px;">
      <a class="smallbtn" href="user_status.php">Back to Status List</a>
      <a class="smallbtn" href="notifications.php" style="margin-left:8px;">Notifications</a>
    </div>
  </div>
</div>

</body>
</html>

<?php mysqli_stmt_close($stmt); ?>
