<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');
$page_title='Staff Directory';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  csrf_check();
  $stmt = $pdo->prepare('DELETE FROM users WHERE id=? AND role<>?');
  $stmt->execute([(int)$_POST['delete_id'], 'admin']);
  header('Location: staff.php'); exit;
}

$staff = $pdo->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-people text-primary"></i> Staff Directory</h3>
<div class="card border-0 shadow-sm p-3 mb-3">
  <p class="text-muted">View all hospital staff, upload profile photos, remove former team members, and open edit forms.</p>
</div>
<table class="table bg-white shadow-sm align-middle">
<thead><tr><th></th><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Joined</th><th></th></tr></thead>
<tbody>
<?php foreach($staff as $u): ?>
<tr>
  <td><img src="<?= base_url('pages/admin/staff_photo.php?id='.$u['id']) ?>" class="rounded-circle" style="width:40px;height:40px;object-fit:cover"></td>
  <td><?= e($u['name']) ?></td>
  <td><?= e($u['email']) ?></td>
  <td><?= e($u['role']) ?></td>
  <td><?= e($u['phone']) ?></td>
  <td><?= e($u['created_at']) ?></td>
  <td class="text-end">
    <a class="btn btn-sm btn-outline-primary" href="staff_edit.php?id=<?= $u['id'] ?>">Edit</a>
    <?php if ($u['role'] !== 'admin'): ?>
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
        <button class="btn btn-sm btn-danger" onclick="return confirm('Remove this staff member?')">Remove</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php include __DIR__ . '/../../includes/footer.php'; ?>