<?php
require_once __DIR__.'/../includes/feature_bootstrap.php';
require_once __DIR__.'/../includes/identity_verification.php';
caz_require_user();
header('Cache-Control: private, no-store');
caz_identity_schema($conn);
$userId=(int)$_SESSION['user_id'];
$verification=caz_identity_details($conn,$userId);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    caz_check_csrf(); $transaction=false;
    try {
        if (($_SESSION['user_role']??'')==='admin') throw new InvalidArgumentException('Use a customer account to submit an ID.');
        $contact=caz_identity_contact($_POST);
        $image=caz_identity_upload($_FILES['identity_document']??[],$_POST['id_type']??null);
        $conn->begin_transaction(); $transaction=true;
        $lock=$conn->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->bind_param('i',$userId); $lock->execute(); $lock->get_result()->fetch_assoc();
        $current=caz_identity_details($conn,$userId,true);
        if ($current && $current['status']==='approved') throw new InvalidArgumentException('Your account is already verified.');
        caz_identity_save($conn,$userId,$image,$contact);
        $conn->commit(); $transaction=false;
        header('Location: verification.php?submitted=1'); exit;
    } catch (InvalidArgumentException $e) { if ($transaction) caz_identity_rollback($conn); $error=$e->getMessage(); }
    catch (Throwable $e) { if ($transaction) caz_identity_rollback($conn); error_log('CAZ identity submission failed.'); $error='We could not save the photo. Please try again.'; }
}
$values=$verification ? ['id_type'=>$verification['id_type'],'contact_person_name'=>$verification['contact_name'],'contact_person_phone'=>$verification['contact_phone'],'contact_person_relationship'=>$verification['contact_relationship']] : [];
if ($_SERVER['REQUEST_METHOD']==='POST') foreach(array_keys($values+array_fill_keys(['id_type','contact_person_name','contact_person_phone','contact_person_relationship'],'')) as $key) if(is_string($_POST[$key]??null)) $values[$key]=$_POST[$key];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account verification · CAZ</title>
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping"><link rel="stylesheet" href="../css/experience.css?v=20260921-location"><link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal"><link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal"><link rel="stylesheet" href="../css/identity-verification.css?v=20260929"><script defer src="../js/identity-verification.js?v=20260930"></script><script defer src="../js/account-validation.js?v=<?= filemtime(__DIR__ . '/../js/account-validation.js') ?>"></script></head>
<body class="caz-themed"><?php require __DIR__.'/../includes/background.php'; require __DIR__.'/../includes/navigation.php'; ?>
<main class="verification-shell">
<section class="verification-panel"><h1>Account verification</h1>
<?php if ($error): ?><div class="verification-message" role="alert"><?= caz_h($error) ?> Please select the photo again before submitting.</div><?php endif; ?>
<?php if (isset($_GET['submitted'])): ?><p class="verification-message" role="status">Your ID was submitted. An administrator will review it.</p><?php endif; ?>
<?php if ($verification): ?><span class="verification-status"><?= caz_h($verification['status']) ?></span>
<p><?= $verification['status']==='approved'?'Your account has been approved. You can place orders. Your ID photo has been removed after review.':($verification['status']==='rejected'?'Please correct the issue below and submit a new, clear ID photo.':'You can browse and use your cart while the admin reviews your ID. Ordering opens after approval.') ?></p>
<?php if ($verification['review_note']): ?><p><strong>Admin feedback:</strong> <?= caz_h($verification['review_note']) ?></p><?php endif; ?>
<p><strong>Order verification contact:</strong> <?= caz_h($verification['contact_name']) ?> · <?= caz_h($verification['contact_phone']) ?></p>
<?php if ($verification['status']!=='approved'): ?><div class="verification-actions"><a href="identity_document.php" target="_blank" rel="noopener noreferrer">View submitted ID privately</a></div><?php endif; ?>
<?php else: ?><p>Your existing account can continue to place orders. You may submit an ID below for admin verification.</p><?php endif; ?>
<div class="verification-actions"><a href="cctv.php">Browse products</a><a href="messages.php">Contact Caz admin</a></div></section>
<?php if (!$verification || $verification['status']!=='approved'): ?><form class="verification-panel" method="post" enctype="multipart/form-data"><h2><?= $verification?'Replace your submission':'Submit a valid ID' ?></h2><input type="hidden" name="csrf" value="<?= caz_h(caz_csrf_token()) ?>"><?php caz_identity_form_fields($values); ?><div class="verification-actions"><button type="submit">Submit for review</button></div></form><?php endif; ?>
</main></body></html>
