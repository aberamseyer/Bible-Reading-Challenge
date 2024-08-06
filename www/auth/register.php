<?php

$insecure = true;

require __DIR__."/../inc/init.php";

if ($me) {
  redirect("/");
}

// confirm user from email link
if ($_REQUEST['confirm']) {
  $user_row = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND uuid = '".$db->esc($_REQUEST['confirm'])."'");
  $token = $redis->get_verify_email_key($user_row['id']);
  if (!$user_row || $token != $_REQUEST['key']) {
    $_SESSION['error'] = "Invalid confirmation link.";
  }
  else {
    $db->update("users", [
      "email_verified" => 1
    ], "id = ".$user_row['id']);
    $redis->delete_verify_email_key($user_row['id']);
    
    $_SESSION['success'] = "Email verified, welcome!";
    
    log_user_in($user_row['id']);
    redirect("/today");
  }
}
else if ($_POST['email']) {
  $_POST['email'] = strtolower($_POST['email']);
  // register user
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email.";
  }
  else if ($_POST['password'] != $_POST['password_confirm']) {
    $_SESSION['error'] = "Passwords did not match.";
  }
  else if (strlen($_POST['password']) < 8) {
    $_SESSION['error'] = "Password too short.";
  }
  else if ($db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND email = '".$db->esc($_POST['email'])."'")) {
    $_SESSION['error'] = "Email already registered.";
  }
  else {
    $ret = $site->create_user($_POST["email"], $_POST['name'], $_POST["password"]);
    $redis->set_verify_email_key($ret['insert_id'], $ret['verify_token']);
    $site->send_register_email($_POST['email'], SCHEME."://".$site->DOMAIN."/auth/register?confirm=".$ret['uuid']."&key=".$ret['verify_token']);
    $_SESSION['email'] = "Registration email sent. Check your inbox!";
    redirect("/auth/login");
  }
}

$page_title = "Register";
require DOCUMENT_ROOT."inc/head.php";
?>
  <div id='auth-wrap'>
    <div>
      <img src='<?= $site->resolve_img_src('login') ?>' style='width: 280px'>
    </div>
    <div>
      <h4>Register</h4>
      <p>Or <a href='login'>log in here</a></p>
      <form action='' method='post'>
        <p>
          <input name='name' type='text' placeholder="Name" value="<?= $_POST['name'] ?>" minlength='1' required>
        </p>
        <p>
        <input name='email' type='text' placeholder="Email" value="<?= $_POST['email'] ?>">
        </p>
        <p>
          <input name='password' type='password' placeholder="Password" required><br>
          <small>At least 8 characters</small>
        </p>
        <p>
          <input name='password_confirm' type='password' placeholder="Confirm password" required><br>
        </p>
        <button type="submit">Submit</button>
      </form>
      <hr>
      <div id="g_id_onload"
        data-client_id="<?= $site->env('GOOGLE_CLIENT_ID') ?>"
        data-context="signup"
        data-ux_mode="popup"
        data-login_uri="https://<?= $site->DOMAIN ?>/auth/oauth"
        data-auto_prompt="false">
      </div>
      <div class="g_id_signin center"
        data-type="standard"
        data-shape="pill"
        data-theme="outline"
        data-text="signup_with"
        data-size="large"
        data-logo_alignment="left">
      </div>
      <div class='text-center'><small><a href='/privacy'>Privacy Policy</a></small></div>
      <?php $add_to_foot .= '<script src="https://accounts.google.com/gsi/client" async></script>'; ?>
    </div>
  </div>
<?php
require DOCUMENT_ROOT."inc/foot.php";