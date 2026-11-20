<?php
require_once __DIR__.'/../includes/feature_bootstrap.php';
require_once __DIR__.'/../includes/identity_verification.php';
caz_require_user(false,true);
if ($_SERVER['REQUEST_METHOD']!=='GET') caz_json(['success'=>false,'message'=>'Use GET.'],405);
if (isset($_GET['user_id']) && (string)$_GET['user_id']!==(string)$_SESSION['user_id']) caz_json(['success'=>false,'message'=>'Access denied.'],403);
caz_identity_schema($conn);
caz_identity_image_response($conn,(int)$_SESSION['user_id']);
