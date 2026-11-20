<?php
require_once __DIR__.'/../includes/feature_bootstrap.php';
require_once __DIR__.'/../includes/identity_verification.php';
caz_require_user(true);
header('Cache-Control: private, no-store');
caz_identity_schema($conn); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    caz_check_csrf(); $transaction=false;
    try {
        $userId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $decision=$_POST['decision']??''; $version=$_POST['version']??'';
        $note=trim(is_string($_POST['review_note']??null)?$_POST['review_note']:'');
        if (!$userId || !in_array($decision,['approved','rejected'],true) || !is_string($version)) throw new InvalidArgumentException('Choose a valid submission and decision.');
        if ($decision==='approved' && ($_POST['review_confirmed']??'')!=='1') throw new InvalidArgumentException('Review the ID and contact details, then confirm the review before approval.');
        if (mb_strlen($note)>1000 || ($decision==='rejected' && mb_strlen($note)<5)) throw new InvalidArgumentException('Provide a clear reason for rejection (5–1,000 characters).');
        $conn->begin_transaction(); $transaction=true;
        $lock=$conn->prepare('SELECT id FROM users WHERE id=? AND role<>\'admin\' FOR UPDATE'); $lock->bind_param('i',$userId); $lock->execute();
        if (!$lock->get_result()->fetch_assoc()) throw new InvalidArgumentException('Customer not found.');
        $current=caz_identity_details($conn,$userId,true);
        if (!$current || $current['status']!=='pending' || !hash_equals($current['version'],$version)) throw new InvalidArgumentException('This submission changed or was already reviewed. Refresh before reviewing it.');
        $adminId=(int)$_SESSION['user_id'];
        $stmt=$conn->prepare("UPDATE caz_identity_verifications SET status=?,review_note=?,reviewed_by=?,reviewed_at=NOW(),image_data=IF(?='approved',NULL,image_data) WHERE user_id=?");
        $stmt->bind_param('ssisi',$decision,$note,$adminId,$decision,$userId); $stmt->execute();
        $conn->commit(); $transaction=false; header('Location: verifications.php?saved=1'); exit;
    } catch (InvalidArgumentException $e) { if ($transaction) caz_identity_rollback($conn); $error=$e->getMessage(); }
    catch(Throwable $e) { if ($transaction) caz_identity_rollback($conn); error_log('CAZ identity review failed.'); $error='The review could not be saved. Please try again.'; }
}
$status=is_string($_GET['status']??null)?$_GET['status']:'pending';
if (!in_array($status,['pending','approved','rejected','all'],true)) $status='pending';
$page=max(1,min(100000,(int)($_GET['page']??1))); $offset=($page-1)*20;
$where=$status==='all'?'':" WHERE v.status='".$status."'";
$rows=$conn->query('SELECT v.user_id,v.status,v.id_type,v.version,v.contact_name,v.contact_phone,v.contact_relationship,v.review_note,v.submitted_at,v.reviewed_at,u.name,u.email,u.contact_number FROM caz_identity_verifications v JOIN users u ON u.id=v.user_id'.$where.' ORDER BY v.submitted_at DESC LIMIT 21 OFFSET '.$offset)->fetch_all(MYSQLI_ASSOC);
$more=count($rows)>20; $rows=array_slice($rows,0,20);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customer verification · CAZ Admin</title><link rel="stylesheet" href="css/admin-style.css?v=20260910-release"><link rel="stylesheet" href="../css/refined.css?v=20260920-shopping"><link rel="stylesheet" href="../css/experience.css?v=20260921-location"><link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal"><link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal"><link rel="stylesheet" href="../css/identity-verification.css?v=<?= (int)filemtime(__DIR__ . '/../css/identity-verification.css') ?>"></head>
<body class="caz-themed caz-admin"><?php require __DIR__.'/../includes/background.php'; require __DIR__.'/../includes/navigation.php'; ?>
<main class="verification-shell"><section class="verification-panel"><h1>Customer verification</h1><p>Review the customer’s name, photo, ID validity and contact details. Contact the person when necessary. Approve only when the submission is clear and consistent; request a new photo if it is blurred, cropped or unreadable.</p><p>ID upload alone does not establish authenticity. Approval records your manual review and opens ordering for the customer. The ID photo is removed after approval.</p></section>
<?php if($error): ?><div class="verification-message" role="alert"><?= caz_h($error) ?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="verification-message" role="status">Review saved.</div><?php endif; ?>
<nav class="verification-filters" aria-label="Filter verifications"><?php foreach(['pending','approved','rejected','all'] as $filter): ?><a href="?status=<?= $filter ?>" <?= $status===$filter?'aria-current="page"':'' ?>><?= ucfirst($filter) ?></a><?php endforeach; ?></nav>
<p class="verification-live" data-verification-live role="status" aria-live="polite">Checking for new submissions…</p>
<div data-verification-list>
<?php if(!$rows): ?><section class="verification-panel"><p>No <?= $status==='all'?'':caz_h($status) ?> submissions to show.</p></section><?php endif; ?>
<?php foreach($rows as $row): ?><article class="verification-panel" data-verification-key="<?= (int)$row['user_id'] ?>-<?= caz_h($row['version']) ?>-<?= caz_h($row['status']) ?>"><h2><?= caz_h($row['name']) ?></h2><span class="verification-status"><?= caz_h($row['status']) ?></span><dl class="verification-grid"><div><dt>Customer #<?= (int)$row['user_id'] ?> · <?= caz_h($row['id_type']) ?></dt><dd><?= caz_h($row['email']) ?></dd></div><div><dt>Customer mobile</dt><dd><?= caz_h($row['contact_number']) ?></dd></div><div><dt>Contact person</dt><dd><?= caz_h($row['contact_name']) ?><?= $row['contact_relationship']?' · '.caz_h($row['contact_relationship']):'' ?></dd></div><div><dt>Contact person’s mobile</dt><dd><?= caz_h($row['contact_phone']) ?></dd></div><div><dt>Submitted</dt><dd><?= caz_h($row['submitted_at']) ?></dd></div></dl>
<?php if($row['status']!=='approved'): ?><div class="verification-actions"><a href="identity_document.php?user_id=<?= (int)$row['user_id'] ?>" target="_blank" rel="noopener noreferrer">View ID privately</a></div><?php endif; ?>
<?php if($row['review_note']): ?><p><strong>Review note:</strong> <?= caz_h($row['review_note']) ?></p><?php endif; ?>
<?php if($row['status']==='pending'): ?><form method="post"><input type="hidden" name="csrf" value="<?= caz_h(caz_csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>"><input type="hidden" name="version" value="<?= caz_h($row['version']) ?>"><label for="note-<?= (int)$row['user_id'] ?>">Review note · required when requesting a new photo</label><textarea id="note-<?= (int)$row['user_id'] ?>" name="review_note" maxlength="1000" placeholder="Explain what needs correcting, if any."></textarea><label class="identity-consent"><input type="checkbox" name="review_confirmed" value="1"><span>I reviewed the ID and contact details and found them clear and consistent.</span></label><div class="verification-actions"><button class="approve" type="submit" name="decision" value="approved">Approve customer</button><button class="reject" type="submit" name="decision" value="rejected">Request a new photo</button></div></form><?php endif; ?>
</article><?php endforeach; ?>
<div class="verification-actions"><?php if($page>1): ?><a href="?status=<?= $status ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?><?php if($more): ?><a href="?status=<?= $status ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?></div></div></main></body></html>
