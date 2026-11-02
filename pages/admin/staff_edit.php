<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/upload.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$isNew = $id === 0;
$staff = ['name'=>'','email'=>'','role'=>'doctor','phone'=>'','photo'=>null];

if (!$isNew) {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
  $stmt->execute([$id]);
  $staff = $stmt->fetch();
  if (!$staff) die('Staff not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $nameInput = $_POST['name'] ?? '';
  $emailInput = $_POST['email'] ?? '';
  $roleInput = $_POST['role'] ?? 'doctor';
  $phoneInput = $_POST['phone'] ?? '';
  $name = is_string($nameInput) ? trim($nameInput) : '';
  $email = is_string($emailInput) ? trim($emailInput) : '';
  $role = is_string($roleInput) ? $roleInput : '';
  $phone = is_string($phoneInput) ? trim($phoneInput) : '';
  $passwordInput = $_POST['password'] ?? '';
  $password = is_string($passwordInput) ? $passwordInput : '';
  $allowedRoles = ['admin', 'doctor', 'receptionist', 'lab', 'pharmacy'];
  $passwordError = $password === '' ? null : password_policy_error($password);

  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $allowedRoles, true)) {
    http_response_code(400);
    exit('Enter a valid name, email, and role.');
  }
  if ($passwordError !== null) {
    http_response_code(400);
    exit(e($passwordError));
  }
  if (!is_string($passwordInput)) {
    http_response_code(400);
    exit('Invalid password value.');
  }

  if ($isNew) {
    $hash = password_hash($password !== '' ? $password : 'password', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role,phone,photo,must_change_password) VALUES (?,?,?,?,?,?,1)');
    $stmt->execute([$name,$email,$hash,$role,$phone,null]);
    $id = $pdo->lastInsertId();
  } else {
    if ($password) {
      $pdo->prepare('UPDATE users SET name=?,email=?,role=?,phone=?,password=? WHERE id=?')
          ->execute([$name,$email,$role,$phone,password_hash($password, PASSWORD_DEFAULT),$id]);
    } else {
      $pdo->prepare('UPDATE users SET name=?,email=?,role=?,phone=? WHERE id=?')
          ->execute([$name,$email,$role,$phone,$id]);
    }
  }

  if (!$isNew && $password !== '') {
    $pdo->prepare('UPDATE users SET must_change_password=1 WHERE id=?')->execute([$id]);
    if ($id === (int)current_user()['id']) {
      $_SESSION['user']['must_change_password'] = 1;
    }
  }

  if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    $uploadDir = realpath(__DIR__ . '/../../uploads/staff');
    $mime = $file['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : false;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 4 * 1024 * 1024
        || !isset($allowed[$mime]) || $uploadDir === false) {
      http_response_code(400);
      exit('Upload a JPG, PNG, or WebP photo no larger than 4 MB.');
    }
    $filename = 'staff_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
      throw new RuntimeException('Could not save staff photo.');
    }
    chmod($uploadDir . DIRECTORY_SEPARATOR . $filename, 0640);
    $pdo->prepare('UPDATE users SET photo=? WHERE id=?')->execute([$filename,$id]);
  }

  header('Location: staff.php'); exit;
}

$page_title = $isNew ? 'Add Staff' : 'Edit Staff';
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-person-plus text-primary"></i> <?= $isNew ? 'Add New Staff' : 'Edit Staff' ?></h3>
<div class="card border-0 shadow-sm p-4">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Full Name</label><input name="name" class="form-control" value="<?= e($staff['name']) ?>" required></div>
      <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= e($staff['email']) ?>" required></div>
      <div class="col-md-4"><label class="form-label">Role</label><select name="role" class="form-select">
          <?php foreach(['admin','doctor','receptionist','lab','pharmacy'] as $r): ?>
            <option value="<?= $r ?>"<?= $staff['role']===$r?' selected':'' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($staff['phone']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Password</label><input name="password" type="password" class="form-control" placeholder="<?= $isNew ? 'Blank uses temporary password' : 'Leave blank to keep current' ?>" minlength="12" maxlength="72" autocomplete="new-password"></div>
      <div class="col-md-6"><label class="form-label">Profile Photo</label><input name="photo" type="file" accept="image/*" class="form-control"></div>
      <?php if (!empty($staff['photo'])): ?>
        <div class="col-md-6">
          <label class="form-label">Current Photo</label><br>
          <img src="<?= base_url('pages/admin/staff_photo.php?id='.$id) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #ddd">
        </div>
      <?php endif; ?>
      <div class="col-12"><button class="btn btn-primary"><?= $isNew ? 'Create Staff' : 'Save Changes' ?></button>
        <a class="btn btn-light" href="staff.php">Cancel</a></div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>