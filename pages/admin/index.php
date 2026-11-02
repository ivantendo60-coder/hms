<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');
header('Location: dashboard.php');
exit;
