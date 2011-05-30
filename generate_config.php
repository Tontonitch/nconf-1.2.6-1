<?php
    require_once 'config/main.php';
    require_once 'include/head.php';
?>

<table><tr><td height=20 colspan=4></tr>
       <tr><td width=15></td>
           <td><img src="img/working.gif"></td>
           <td width=15></td>
           <td><br><h2>Generating updated Nagios configuration. <br>Please stand by...</h2></td>
</tr></table>


<?php

# Load exec script with AJAX, so content will change when script is finished
echo js_prepare("ajax_loadContent('maincontent','call_ajax.php?ajax_file=exec_generate_config.php&username=".$_SESSION['userinfos']['username']."');");


mysql_close($dbh);
require_once 'include/foot.php';

?>
