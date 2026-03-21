<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$role_id = (int)$_SESSION["role_id"];
$user_id = (int)$_SESSION["user_id"];

if (isUserRole($role_id)) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: death_records_list.php");
    exit();
}

$id = (int)$_GET["id"];
$message = "";
$message_type = "error";

$sql = "SELECT
            dr.*,
            creator.full_name AS creator_name,
            applicant.full_name AS applicant_name
        FROM death_records dr
        JOIN users creator ON dr.created_by = creator.user_id
        JOIN users applicant ON dr.applicant_user_id = applicant.user_id
        WHERE dr.record_id = ? AND dr.deleted_at IS NULL
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$record = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$record) {
    header("Location: death_records_list.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "update_workflow";

    if ($action === "review_document") {
        $document_id = (int)($_POST["document_id"] ?? 0);
        $review_status = trim($_POST["review_status"] ?? "");
        $review_notes = trim($_POST["review_notes"] ?? "");

        if (!in_array($review_status, documentReviewOptions(), true)) {
            $message = "Invalid document review status.";
        } else {
            $doc_stmt = mysqli_prepare(
                $conn,
                "SELECT document_id, document_type, review_status
                 FROM record_documents
                 WHERE document_id = ? AND record_id = ?
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($doc_stmt, "ii", $document_id, $id);
            mysqli_stmt_execute($doc_stmt);
            $doc_res = mysqli_stmt_get_result($doc_stmt);
            $document = $doc_res ? mysqli_fetch_assoc($doc_res) : null;
            mysqli_stmt_close($doc_stmt);

            if (!$document) {
                $message = "The selected document could not be found.";
            } else {
                $review_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE record_documents
                     SET review_status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                     WHERE document_id = ?"
                );
                mysqli_stmt_bind_param($review_stmt, "ssii", $review_status, $review_notes, $user_id, $document_id);

                if (mysqli_stmt_execute($review_stmt)) {
                    $message = "Document review saved successfully.";
                    $message_type = "success";
                    $details = $document["document_type"] . " reviewed as " . $review_status . ".";
                    logRecordActivity(
                        $id,
                        $user_id,
                        "document_reviewed",
                        $details,
                        $record["status"],
                        $record["status"],
                        $review_notes,
                        (int)$record["applicant_user_id"]
                    );
                    createNotification(
                        (int)$record["applicant_user_id"],
                        "Document review updated",
                        $document["document_type"] . " was marked as " . $review_status . " for the record of " . $record["deceased_name"] . ".",
                        $id
                    );
                } else {
                    $message = "Unable to save the document review: " . mysqli_error($conn);
                }
                mysqli_stmt_close($review_stmt);
            }
        }
    } else {
        $new_status = trim($_POST["status"] ?? "");
        $remarks = trim($_POST["remarks"] ?? "");
        $allowed = statusOptions();

        if (!in_array($new_status, $allowed, true)) {
            $message = "Invalid status.";
        } else if ($new_status === "Verified" && !canVerifyRecord($id, $message)) {
            $message_type = "error";
        } else if ($new_status === "Approved" && $record["status"] !== "Verified") {
            $message = "A record must be verified before it can be approved.";
        } else if ($new_status === "Approved" && !canVerifyRecord($id, $message)) {
            $message_type = "error";
        } else if ($new_status === $record["status"] && $remarks === "") {
            $message = "No changes detected. Update the status or add a workflow note.";
        } else {
            $old_status = $record["status"];
            $status_changed = $new_status !== $old_status;

            if ($status_changed) {
                $upd = mysqli_prepare($conn, "UPDATE death_records SET status = ? WHERE record_id = ?");
                mysqli_stmt_bind_param($upd, "si", $new_status, $id);

                if (!mysqli_stmt_execute($upd)) {
                    $message = "Error updating status: " . mysqli_error($conn);
                } else {
                    $record["status"] = $new_status;
                    $message = "Workflow updated successfully!";
                    $message_type = "success";
                }
                mysqli_stmt_close($upd);
            } else {
                $message = "Workflow note saved successfully!";
                $message_type = "success";
            }

            if ($message_type === "success") {
                if ($status_changed) {
                    $details = "Status changed from " . $old_status . " to " . $new_status . ".";
                    logRecordActivity($id, $user_id, "status_updated", $details, $old_status, $new_status, $remarks, (int)$record["applicant_user_id"]);

                    createNotification(
                        (int)$record["applicant_user_id"],
                        "Application status updated",
                        "The record for " . $record["deceased_name"] . " changed from " . $old_status . " to " . $new_status . ".",
                        $id
                    );
                } else {
                    logRecordActivity(
                        $id,
                        $user_id,
                        "status_note",
                        "Workflow note added while the status remained " . $record["status"] . ".",
                        $record["status"],
                        $record["status"],
                        $remarks,
                        (int)$record["applicant_user_id"]
                    );

                    createNotification(
                        (int)$record["applicant_user_id"],
                        "Workflow note added",
                        "A new note was added to the record for " . $record["deceased_name"] . ".",
                        $id
                    );
                }
            }
        }
    }
}

$logs_stmt = mysqli_prepare(
    $conn,
    "SELECT
        l.*,
        u.full_name AS actor_name,
        u.role_id AS actor_role
     FROM record_activity_logs l
     JOIN users u ON l.actor_user_id = u.user_id
     WHERE l.related_record_id = ?
     ORDER BY l.created_at DESC, l.log_id DESC
     LIMIT 12"
);
mysqli_stmt_bind_param($logs_stmt, "i", $id);
mysqli_stmt_execute($logs_stmt);
$logs_res = mysqli_stmt_get_result($logs_stmt);
$activity_logs = [];
while ($log = mysqli_fetch_assoc($logs_res)) {
    $activity_logs[] = $log;
}
mysqli_stmt_close($logs_stmt);

$requests_stmt = mysqli_prepare(
    $conn,
    "SELECT
        dcr.request_id,
        dcr.purpose,
        dcr.copies_requested,
        dcr.contact_number,
        dcr.remarks,
        dcr.status,
        dcr.email_status,
        dcr.email_error,
        dcr.submitted_at,
        dcr.emailed_at,
        u.full_name AS requester_name,
        u.email AS requester_email
     FROM death_certificate_requests dcr
     JOIN users u ON dcr.requester_user_id = u.user_id
     WHERE dcr.record_id = ?
     ORDER BY dcr.submitted_at DESC, dcr.request_id DESC
     LIMIT 8"
);
mysqli_stmt_bind_param($requests_stmt, "i", $id);
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
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Record View</title>
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
    <h2 style="text-align:center;">Record Details & Workflow</h2>

    <?php if ($message !== "") { ?>
      <div class="alert alert-<?php echo e($message_type); ?>">
        <?php echo e($message); ?>
      </div>
    <?php } ?>

    <div class="panel" style="margin-top:16px;">
      <h3>Record Overview</h3>
      <div class="row"><div class="label">Record ID</div><?php echo (int)$record["record_id"]; ?></div>
      <div class="row"><div class="label">Reference Code</div><?php echo e($record["tracking_code"]); ?></div>
      <div class="row"><div class="label">Deceased Name</div><?php echo e($record["deceased_name"]); ?></div>
      <div class="row"><div class="label">Applicant</div><?php echo e($record["applicant_name"]); ?></div>
      <div class="row"><div class="label">Date of Death</div><?php echo e($record["date_of_death"]); ?></div>
      <div class="row"><div class="label">Place of Death</div><?php echo e($record["place_of_death"]); ?></div>
      <div class="row"><div class="label">Cause of Death</div><?php echo e($record["cause_of_death"]); ?></div>
      <div class="row"><div class="label">Informant Name</div><?php echo e($record["informant_name"]); ?></div>
      <div class="row"><div class="label">Relationship</div><?php echo e($record["relationship"]); ?></div>
      <div class="row"><div class="label">Created By</div><?php echo e($record["creator_name"]); ?></div>
      <div class="row"><div class="label">Date Submitted</div><?php echo e($record["date_submitted"]); ?></div>
      <div class="row">
        <div class="label">Current Status</div>
        <span class="badge status-<?php echo strtolower(e($record["status"])); ?>"><?php echo e($record["status"]); ?></span>
      </div>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Supporting Documents Review</h3>
      <p class="muted" style="margin:0 0 12px;">
        Verification requires at least one Medical Certificate or Autopsy Report marked valid. If a file is blurry, incomplete, or incorrect, mark it as Needs Replacement so the user can upload a better copy.
      </p>

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

              <form method="POST">
                <input type="hidden" name="action" value="review_document">
                <input type="hidden" name="document_id" value="<?php echo (int)$document["document_id"]; ?>">

                <label class="note">Review Status</label>
                <select name="review_status" required>
                  <?php foreach (documentReviewOptions() as $review_option) { ?>
                    <option value="<?php echo e($review_option); ?>" <?php echo $document["review_status"] === $review_option ? "selected" : ""; ?>>
                      <?php echo e($review_option); ?>
                    </option>
                  <?php } ?>
                </select>

                <label class="note">Review Notes</label>
                <textarea name="review_notes" rows="3" placeholder="Explain what makes the file valid or what needs to be replaced."><?php echo e($document["review_notes"]); ?></textarea>

                <button type="submit">Save Document Review</button>
              </form>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="table-empty">No supporting documents have been uploaded for this record yet.</div>
      <?php } ?>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Workflow Update</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_workflow">
        <label class="note">Status</label>
        <select name="status" required>
          <?php foreach (statusOptions() as $status_option) { ?>
            <option value="<?php echo e($status_option); ?>" <?php echo $record["status"] === $status_option ? "selected" : ""; ?>>
              <?php echo e($status_option); ?>
            </option>
          <?php } ?>
        </select>

        <label class="note">Status Note / Remarks</label>
        <textarea name="remarks" rows="4" placeholder="Explain what changed or add a review note for the timeline."></textarea>

        <ul class="helper-list">
          <li><b>Verified</b> requires at least one valid Medical Certificate or Autopsy Report and no pending replacements.</li>
          <li><b>Approved</b> can only happen after the record is already Verified.</li>
        </ul>

        <button type="submit">Save Workflow Update</button>
      </form>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Recent Activity Timeline</h3>

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
              <?php if (!empty($log["old_status"]) || !empty($log["new_status"])) { ?>
                <div class="muted" style="margin-top:8px;">
                  Status Flow:
                  <?php echo e($log["old_status"] ?: "-"); ?>
                  ->
                  <?php echo e($log["new_status"] ?: "-"); ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="table-empty">No activity logs yet for this record.</div>
      <?php } ?>
    </div>

    <div class="panel" style="margin-top:18px;">
      <h3>Death Certificate Requests</h3>

      <?php if (!empty($certificate_requests)) { ?>
        <div class="request-list">
          <?php foreach ($certificate_requests as $request) { ?>
            <div class="request-card">
              <div class="request-card-header">
                <strong><?php echo e($request["requester_name"]); ?></strong>
                <span class="badge"><?php echo e($request["status"]); ?></span>
              </div>
              <div class="muted"><?php echo e($request["requester_email"]); ?></div>
              <div class="muted">Submitted: <?php echo e($request["submitted_at"]); ?></div>
              <div class="muted">Copies Requested: <?php echo e($request["copies_requested"]); ?></div>
              <?php if (!empty($request["contact_number"])) { ?>
                <div class="muted">Contact Number: <?php echo e($request["contact_number"]); ?></div>
              <?php } ?>
              <div class="muted">Purpose: <?php echo e($request["purpose"]); ?></div>
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
        <div class="table-empty">No death certificate requests have been forwarded for this record yet.</div>
      <?php } ?>
    </div>

    <div style="text-align:center; margin-top:18px;">
      <a class="smallbtn" href="death_records_list.php">Back to List</a>
    </div>
  </div>
</div>

</body>
</html>
