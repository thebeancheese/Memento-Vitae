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

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$created_by_filter = (int)($_GET["created_by"] ?? 0);
$applicant_filter = (int)($_GET["applicant_user_id"] ?? 0);
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");
$view_mode = trim($_GET["view"] ?? "active");
$archived_mode = isAdminRole($role_id) && $view_mode === "archived";
$added_msg = isset($_GET["added"]) ? "Record added successfully!" : "";
$deleted_msg = isset($_GET["deleted"]) ? ($archived_mode ? "Record restored successfully!" : "Record archived successfully!") : "";

$status_options = statusOptions();
$sort_map = [
    "newest" => "dr.date_submitted DESC",
    "oldest" => "dr.date_submitted ASC",
    "name_asc" => "dr.deceased_name ASC",
    "name_desc" => "dr.deceased_name DESC",
    "status_asc" => "dr.status ASC, dr.date_submitted DESC"
];
$order_by = $sort_map[$sort] ?? $sort_map["newest"];

$creator_options = [];
$creator_res = mysqli_query(
    $conn,
    "SELECT user_id, full_name, role_id
     FROM users
     WHERE role_id <> " . ROLE_USER . "
     ORDER BY full_name ASC"
);
if ($creator_res) {
    while ($row = mysqli_fetch_assoc($creator_res)) {
        $creator_options[] = $row;
    }
}

$applicant_options = [];
$applicant_res = mysqli_query(
    $conn,
    "SELECT user_id, full_name
     FROM users
     WHERE role_id = " . ROLE_USER . "
     ORDER BY full_name ASC"
);
if ($applicant_res) {
    while ($row = mysqli_fetch_assoc($applicant_res)) {
        $applicant_options[] = $row;
    }
}

$conditions = [];
$param_types = "";
$params = [];
$conditions[] = $archived_mode ? "dr.deleted_at IS NOT NULL" : "dr.deleted_at IS NULL";

if ($search !== "") {
    $conditions[] = "(dr.deceased_name LIKE ? OR dr.tracking_code LIKE ?)";
    $param_types .= "ss";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
}

if (in_array($status, $status_options, true)) {
    $conditions[] = "dr.status = ?";
    $param_types .= "s";
    $params[] = $status;
}

if ($created_by_filter > 0) {
    $conditions[] = "dr.created_by = ?";
    $param_types .= "i";
    $params[] = $created_by_filter;
}

if ($applicant_filter > 0) {
    $conditions[] = "dr.applicant_user_id = ?";
    $param_types .= "i";
    $params[] = $applicant_filter;
}

if ($date_from !== "") {
    $conditions[] = "DATE(dr.date_submitted) >= ?";
    $param_types .= "s";
    $params[] = $date_from;
}

if ($date_to !== "") {
    $conditions[] = "DATE(dr.date_submitted) <= ?";
    $param_types .= "s";
    $params[] = $date_to;
}

$where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$summary_sql = "SELECT
                    COUNT(*) AS total_records,
                    SUM(CASE WHEN dr.status = 'Pending' THEN 1 ELSE 0 END) AS pending_total,
                    SUM(CASE WHEN dr.status = 'Verified' THEN 1 ELSE 0 END) AS verified_total,
                    SUM(CASE WHEN dr.status = 'Approved' THEN 1 ELSE 0 END) AS approved_total,
                    SUM(CASE WHEN dr.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_total
                FROM death_records dr
                $where_sql";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
stmtBindParams($summary_stmt, $param_types, $params);
mysqli_stmt_execute($summary_stmt);
$summary_res = mysqli_stmt_get_result($summary_stmt);
$summary = mysqli_fetch_assoc($summary_res) ?: [
    "total_records" => 0,
    "pending_total" => 0,
    "verified_total" => 0,
    "approved_total" => 0,
    "rejected_total" => 0
];
mysqli_stmt_close($summary_stmt);

$list_sql = "SELECT
                dr.record_id,
                dr.tracking_code,
                dr.deceased_name,
                dr.status,
                dr.date_submitted,
                dr.created_by,
                dr.deleted_at,
                creator.full_name AS creator_name,
                creator.role_id AS creator_role,
                applicant.full_name AS applicant_name
             FROM death_records dr
             JOIN users creator ON dr.created_by = creator.user_id
             JOIN users applicant ON dr.applicant_user_id = applicant.user_id
             $where_sql
             ORDER BY $order_by";
$stmt = mysqli_prepare($conn, $list_sql);
stmtBindParams($stmt, $param_types, $params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=" . ($archived_mode ? "archived-death-records-report.csv" : "death-records-report.csv"));

    $out = fopen("php://output", "w");
    fputcsv($out, ["Record ID", "Reference", "Deceased Name", "Applicant", "Status", "Date Submitted", "Created By", "Creator Role"]);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($out, [
            $row["record_id"],
            $row["tracking_code"],
            $row["deceased_name"],
            $row["applicant_name"],
            $row["status"],
            $row["date_submitted"],
            $row["creator_name"],
            roleName((int)$row["creator_role"])
        ]);
    }
    fclose($out);
    mysqli_stmt_close($stmt);
    exit();
}

$export_query = $_GET;
$export_query["export"] = "csv";
$export_url = "death_records_list.php?" . http_build_query($export_query);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Memento Vitae - Records List</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-dashboard-page">

<header class="public-header">
  <a class="public-brand" href="index.php" aria-label="Memento Vitae home">
    <img src="assets/logo.png" alt="Memento Vitae">
  </a>

  <nav class="public-nav" aria-label="Primary">
    <a href="index.php">Home</a>
    <a href="articles.php">Articles</a>
    <a href="requirements.php">Requirements</a>
    <a href="contact.php">Contact Us</a>
  </nav>

  <a class="public-login" href="logout.php">Logout</a>
</header>

<div class="page-shell">
  <div class="card dashboard-card records-list-card" style="max-width:none; text-align:left;">
    <img class="dashboard-logo" src="assets/logo.png" alt="Memento Vitae">
    <h2 style="text-align:center;">Death Records List</h2>

    <?php if ($added_msg !== "") { ?>
      <div class="alert alert-success"><?php echo e($added_msg); ?></div>
    <?php } ?>
    <?php if ($deleted_msg !== "") { ?>
      <div class="alert alert-success"><?php echo e($deleted_msg); ?></div>
    <?php } ?>

    <div class="inline-actions" style="justify-content:space-between; margin-top:16px;">
      <div class="inline-actions">
        <a class="smallbtn" href="dashboard.php">Back</a>
        <?php if (!$archived_mode) { ?>
          <a class="smallbtn" href="death_records_add.php">Add Record</a>
        <?php } ?>
      </div>
      <div class="inline-actions">
        <?php if (isAdminRole($role_id)) { ?>
          <a class="smallbtn" href="death_records_list.php?view=<?php echo $archived_mode ? "active" : "archived"; ?>">
            <?php echo $archived_mode ? "View Active Records" : "View Archived Records"; ?>
          </a>
        <?php } ?>
        <a class="smallbtn" href="<?php echo e($export_url); ?>">Export CSV</a>
        <a class="smallbtn" href="death_records_list.php<?php echo $archived_mode ? "?view=archived" : ""; ?>">Reset Filters</a>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="label">Filtered Results</div>
        <div class="value"><?php echo (int)$summary["total_records"]; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Pending</div>
        <div class="value status-pending"><?php echo (int)$summary["pending_total"]; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Verified</div>
        <div class="value status-verified"><?php echo (int)$summary["verified_total"]; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Approved</div>
        <div class="value status-approved"><?php echo (int)$summary["approved_total"]; ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Rejected</div>
        <div class="value status-rejected"><?php echo (int)$summary["rejected_total"]; ?></div>
      </div>
    </div>

    <div class="panel records-filter-panel" style="margin-top:18px;">
      <h3><?php echo $archived_mode ? "Archived Records Filters" : "Advanced Filters & Report Controls"; ?></h3>
      <form method="GET">
        <?php if ($archived_mode) { ?>
          <input type="hidden" name="view" value="archived">
        <?php } ?>
        <div class="filters-grid">
          <div>
            <label class="note">Search Name</label>
            <input type="text" name="search" placeholder="Deceased name..." value="<?php echo e($search); ?>">
          </div>

          <div>
            <label class="note">Status</label>
            <select name="status">
              <option value="">All Statuses</option>
              <?php foreach ($status_options as $status_option) { ?>
                <option value="<?php echo e($status_option); ?>" <?php echo $status === $status_option ? "selected" : ""; ?>>
                  <?php echo e($status_option); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div>
            <label class="note">Created By</label>
            <select name="created_by">
              <option value="">All Staff</option>
              <?php foreach ($creator_options as $creator) { ?>
                <option value="<?php echo (int)$creator["user_id"]; ?>" <?php echo $created_by_filter === (int)$creator["user_id"] ? "selected" : ""; ?>>
                  <?php echo e($creator["full_name"]); ?> (<?php echo e(roleName((int)$creator["role_id"])); ?>)
                </option>
              <?php } ?>
            </select>
          </div>

          <div>
            <label class="note">Applicant</label>
            <select name="applicant_user_id">
              <option value="">All Applicants</option>
              <?php foreach ($applicant_options as $applicant) { ?>
                <option value="<?php echo (int)$applicant["user_id"]; ?>" <?php echo $applicant_filter === (int)$applicant["user_id"] ? "selected" : ""; ?>>
                  <?php echo e($applicant["full_name"]); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div>
            <label class="note">Submitted From</label>
            <input type="date" name="date_from" value="<?php echo e($date_from); ?>">
          </div>

          <div>
            <label class="note">Submitted To</label>
            <input type="date" name="date_to" value="<?php echo e($date_to); ?>">
          </div>

          <div>
            <label class="note">Sort</label>
            <select name="sort">
              <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest First</option>
              <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Oldest First</option>
              <option value="name_asc" <?php echo $sort === "name_asc" ? "selected" : ""; ?>>Name A-Z</option>
              <option value="name_desc" <?php echo $sort === "name_desc" ? "selected" : ""; ?>>Name Z-A</option>
              <option value="status_asc" <?php echo $sort === "status_asc" ? "selected" : ""; ?>>Status Then Date</option>
            </select>
          </div>

          <div>
            <button type="submit">Apply Filters</button>
          </div>
        </div>
      </form>
    </div>

    <div style="margin-top:18px;">
      <table class="data-table">
        <tr>
          <th>ID</th>
          <th>Reference</th>
          <th>Deceased Name</th>
          <th>Applicant</th>
          <th>Status</th>
          <th>Date Submitted</th>
          <th>Created By</th>
          <?php if ($archived_mode) { ?><th>Archived</th><?php } ?>
          <th>Actions</th>
        </tr>

        <?php if (mysqli_num_rows($result) > 0) { ?>
          <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <?php $can_modify = isAdminRole($role_id) || (int)$row["created_by"] === $user_id; ?>
            <?php $status_class = "status-" . strtolower($row["status"]); ?>
            <tr>
              <td><?php echo (int)$row["record_id"]; ?></td>
              <td><?php echo e($row["tracking_code"]); ?></td>
              <td><?php echo e($row["deceased_name"]); ?></td>
              <td><?php echo e($row["applicant_name"]); ?></td>
              <td><span class="badge <?php echo e($status_class); ?>"><?php echo e($row["status"]); ?></span></td>
              <td><?php echo e($row["date_submitted"]); ?></td>
              <td>
                <?php echo e($row["creator_name"]); ?>
                <span class="badge"><?php echo e(roleName((int)$row["creator_role"])); ?></span>
              </td>
              <?php if ($archived_mode) { ?>
                <td><?php echo e($row["deleted_at"] ?? "Archived"); ?></td>
              <?php } ?>
              <td>
                <?php if ($archived_mode) { ?>
                  <a class="action" href="death_records_restore.php?id=<?php echo (int)$row["record_id"]; ?>">Restore</a>
                <?php } else { ?>
                  <a class="action" href="record_view.php?id=<?php echo (int)$row["record_id"]; ?>">View</a>
                <?php if ($can_modify) { ?>
                  &nbsp;|&nbsp;
                  <a class="action" href="death_records_edit.php?id=<?php echo (int)$row["record_id"]; ?>">Edit</a>
                  &nbsp;|&nbsp;
                  <a class="action" href="death_records_delete.php?id=<?php echo (int)$row["record_id"]; ?>">Archive</a>
                <?php } else { ?>
                  <div class="muted" style="margin-top:6px;">View + status update only</div>
                <?php } ?>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
        <?php } else { ?>
          <tr>
            <td colspan="<?php echo $archived_mode ? 9 : 8; ?>" class="table-empty">No death records matched the selected filters.</td>
          </tr>
        <?php } ?>
      </table>
    </div>

  </div>
</div>

<footer class="public-footer">@MementoVitae - All rights reserved 2026</footer>

</body>
</html>

<?php mysqli_stmt_close($stmt); ?>
