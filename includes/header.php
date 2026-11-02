<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
$u = current_user();
$isLoginPage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php';
$showAuthenticatedHeader = $u && !$isLoginPage;
$bodyClass = $isLoginPage ? 'auth-page' : '';
csrf_token();
close_session();
?>
<!doctype html>
<html lang="en" data-theme="<?= e($u['color_theme'] ?? 'blue') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($page_title)?e($page_title).' • ':'' ?>MediCare HMS</title>
<link rel="icon" href="<?= base_url('assets/img/logo.png') ?>">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('index.php') ?>">
      <img src="<?= base_url('assets/img/logo.png') ?>" width="32" height="32" alt="logo">
      <strong>MediCare HMS</strong>
    </a>
    <?php if ($showAuthenticatedHeader): ?>
    <div class="d-flex align-items-center gap-3 ms-auto text-white">
      <span class="badge bg-light text-primary text-uppercase"><?= e($u['role']) ?></span>
      <div class="d-flex align-items-center gap-2">
        <img src="<?= base_url('pages/admin/staff_photo.php?id=' . intval($u['id'])) ?>" class="avatar" alt="Profile">
        <span><?= e($u['name']) ?></span>
      </div>
      <form method="post" action="<?= base_url('pages/auth/logout.php') ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</nav>
<div class="<?= $showAuthenticatedHeader ? 'app-shell' : 'public-shell' ?>">
<?php if ($showAuthenticatedHeader): include __DIR__ . '/sidebar.php'; endif; ?>
<main class="<?= $showAuthenticatedHeader ? 'app-main' : ($isLoginPage ? 'auth-main' : 'public-main') ?>">
