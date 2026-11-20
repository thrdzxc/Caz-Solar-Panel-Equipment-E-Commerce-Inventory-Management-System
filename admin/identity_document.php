<?php
require_once __DIR__.'/../includes/feature_bootstrap.php';
require_once __DIR__.'/../includes/identity_verification.php';
caz_require_user(true,true);
if ($_SERVER['REQUEST_METHOD']!=='GET') caz_json(['success'=>false,'message'=>'Use GET.'],405);
$userId=filter_var($_GET['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$userId) caz_json(['success'=>false,'message'=>'Choose a customer.'],400);
caz_identity_schema($conn);
caz_identity_image_response($conn,$userId);
