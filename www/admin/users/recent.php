<?php

require __DIR__."/../../inc/init.php";

if (!$staff) {
  redirect('/');
}

$since = false;
if (isset($_GET['since'])) {
  $since = (int)strtotime($_GET['since']);
}
if (!$since) {
  $since = strtotime('July 1 -'.$site->TZ_OFFSET.' hours');
}

$recent_users = BibleReadingChallenge\Database::get_instance()->select("
  SELECT id, name, email, email_verses, DATE(date_created, 'unixepoch', '".$site->TZ_OFFSET." hours') registered
  FROM users
  WHERE site_id = ".$site->ID." AND date_created >= $since
  ORDER BY date_created DESC;");

$page_title = "Recent Signups";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require DOCUMENT_ROOT."inc/head.php";
echo admin_navigation();
?>
<p><?= back_button("Back") ?></p>
<form action='/admin/users/recent' method='get'>
  <label>
    Show those registered since:
    <input 
      type='date'
      name='since'
      value='<?= date('Y-m-d', $since) ?>'
    >
    <button type='submit'>Refresh</button>
  </label>
</form>
<p><b><?= count($recent_users) ?></b> record<?= xs($recent_users) ?></p>
<div class='table-scroll'>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Receiving Notifications?</th>
        <th>Registered</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($recent_users as $user): ?>
        <tr>
          <td><a href='/admin/users?user_id=<?= $user['id']?>'><?= $user['name'] ?></a></td>
          <td><?= $user['email'] ?></td>
          <td><img alt='check' class='icon' src='/img/static/circle-<?= $user['email_verses'] == 1 || $user['push_notifications'] == 1 ? 'check' : 'x' ?>.svg'>
          <td><?= date('D, M j, Y', strtotime($user['registered'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?= !$recent_users ? "<tr><td>No one has registered since then.</td></tr>" : "" ?>
    </tbody>
  </table>
</div>

<?php
require DOCUMENT_ROOT."inc/foot.php";