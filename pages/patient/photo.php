<?php
// Patient profile photos are disabled.
require_once __DIR__ . '/../../includes/auth.php';
require_role(['doctor','receptionist','lab','pharmacy','admin']);
http_response_code(404);
exit;
