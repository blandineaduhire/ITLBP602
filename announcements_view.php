<?php
include "config/db.php";
include "includes/auth.php";
require_role(['admin', 'teacher', 'student']);

// Only show announcements whose publication date has arrived
$today = date('Y-m-d');

$sql = "SELECT a.*, u.username AS author_name
        FROM announcements a
        LEFT JOIN users u ON a.posted_by = u.user_id
        WHERE a.publication_date <= ?
        ORDER BY a.publication_date DESC, a.announcement_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $today);
mysqli_stmt_execute($stmt);
$announcements = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

include "includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary">Announcements</h2>
    <span class="badge bg-info text-dark"><?php echo count($announcements); ?> post(s)</span>
</div>

<?php if (count($announcements) > 0) { ?>
    <?php foreach ($announcements as $a) { ?>
        <div class="card shadow border-0 mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary">
                    <?php echo htmlspecialchars($a['title']); ?>
                </h5>
                <small class="text-muted">
                    <?php echo htmlspecialchars($a['publication_date']); ?>
                </small>
            </div>
            <div class="card-body">
                <p class="mb-3" style="white-space: pre-line;">
                    <?php echo htmlspecialchars($a['message']); ?>
                </p>
                <small class="text-muted">
                    Posted by: <strong><?php echo htmlspecialchars($a['author_name'] ?? 'Unknown'); ?></strong>
                </small>
            </div>
        </div>
    <?php } ?>
<?php } else { ?>
    <div class="alert alert-info">
        No announcements have been published yet.
    </div>
<?php } ?>

<?php include "includes/footer.php"; ?>