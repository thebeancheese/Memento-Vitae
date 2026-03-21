<?php
require_once 'includes/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$document_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($document_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        rd.document_id,
        rd.document_type,
        rd.original_file_name,
        rd.mime_type,
        rd.record_id,
        dr.applicant_user_id
     FROM record_documents rd
     JOIN death_records dr ON rd.record_id = dr.record_id
     WHERE rd.document_id = ? AND dr.deleted_at IS NULL
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $document_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$document = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$document) {
    header("Location: dashboard.php");
    exit();
}

$role_id = (int)$_SESSION["role_id"];
$user_id = (int)$_SESSION["user_id"];
if (isUserRole($role_id) && (int)$document["applicant_user_id"] !== $user_id) {
    header("Location: dashboard.php");
    exit();
}

$back_url = isUserRole($role_id)
    ? "user_record_view.php?id=" . (int)$document["record_id"]
    : "record_view.php?id=" . (int)$document["record_id"];
$file_url = "document_download.php?id=" . (int)$document["document_id"];
$mime_type = trim((string)$document["mime_type"]);
$is_image = str_starts_with($mime_type, "image/");
$is_pdf = $mime_type === "application/pdf";
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - View Document</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-content-page">
  <div class="page-shell">
    <div class="card" style="max-width:none; text-align:left;">
      <h1 style="text-align:center;">Memento Vitae</h1>
      <h2 style="text-align:center;">Document Viewer</h2>

      <div class="panel" style="margin-top:16px;">
        <div class="inline-actions" style="justify-content:space-between;">
          <div>
            <div><strong><?php echo e($document["document_type"]); ?></strong></div>
            <div class="muted"><?php echo e($document["original_file_name"]); ?></div>
          </div>
          <div class="inline-actions">
            <a class="smallbtn" href="<?php echo e($back_url); ?>">Back</a>
            <a class="smallbtn" href="<?php echo e($file_url); ?>" target="_blank" rel="noopener">Open Raw File</a>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top:18px;">
        <?php if ($is_image) { ?>
          <img src="<?php echo e($file_url); ?>" alt="<?php echo e($document["original_file_name"]); ?>" style="display:block; max-width:100%; height:auto; margin:0 auto; border-radius:16px;">
        <?php } else if ($is_pdf) { ?>
          <iframe src="<?php echo e($file_url); ?>" title="<?php echo e($document["original_file_name"]); ?>" style="width:100%; min-height:80vh; border:none; border-radius:16px; background:#111;"></iframe>
        <?php } else { ?>
          <div class="table-empty">
            This file type is best opened in a separate browser tab.
          </div>
          <div style="margin-top:12px; text-align:center;">
            <a class="smallbtn" href="<?php echo e($file_url); ?>" target="_blank" rel="noopener">Open File</a>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</body>
</html>
