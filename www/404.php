<?php

$insecure = true;
require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

$page_title = "Privacy Policy";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

?>

<p><img style='width: 150px' src='<?= $site->resolve_img_src('logo') ?>' onclick='window.location = `/`'></p>
<p><a href='/'><< Home</a></p>
<p>The page you requested
  <?php if($_GET['uri']) {
    echo "<code>$_GET[uri]</code>";
  } ?>
  could not be found.</p>

<?php
  
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";

?>