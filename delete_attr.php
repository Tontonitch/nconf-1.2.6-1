<?php
require_once 'config/main.php';
require_once 'include/head.php';



?>
<h2>Delete Attribute</h2><br>

<?php


if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["id"]) ) AND ($_POST["id"] != "") )
  ){
    
    // Delete entry
    $query = 'DELETE FROM ConfigAttrs
                WHERE id_attr='.$_POST["id"];

    //message ($debug, $query, "query");
    $result = db_handler($query, "result", "Delete entry");
    if ( $result ){
        message ($debug, '', "ok");
        history_add("removed", "Attribute", $_POST["name"]);

        echo TXT_DELETED;

        $url = $_SESSION["go_back_page"];
            
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        message($info, '...redirecting to <a href="'.$url.'">page</a> in '.REDIRECTING_DELAY.' seconds...');
    }else{
        message ($error, 'Error when deleting id_attr '.$_POST["id"].':'.$query);
    }
    

}else{

    // Fetch attr name
    $query = 'SELECT attr_name, config_class FROM ConfigAttrs, ConfigClasses WHERE id_attr='.$_GET["id"].' AND fk_id_class=ConfigClasses.id_class';
    $attr = db_handler($query, "assoc", "Fetch attr name");
    ?>
    <table width=410 cellspacing=0 cellpadding=0>
    <tr>
        <td width=250><?php
            echo '<b>WARNING</b><br>All &quot;<b>'.$attr["config_class"].'</b>&quot; items will lose their &quot;<b>'.$attr["attr_name"].'</b>&quot; attribute. All data associated with this attribute will also be lost. This action cannot be undone.
            <br><br>Are you REALLY SURE you want to proceed?';
            ?>
        </td>
    </tr>
    <tr>
        <td>
          <form name="delete_attr" action="delete_attr.php" method="post">
            <?php
            echo '<input type="hidden" name="id" value="'.$_GET["id"].'">';
            echo '<input type="hidden" name="name" value="'.$attr["attr_name"].'">';
            if ( !empty($_GET["from"]) ) echo '<input type="hidden" name="from" value="'.$_GET["from"].'">';
            echo '<input type="hidden" name="delete" value="yes">
                  <div id=buttons><br><br>';
            echo '<input type="Submit" value="Delete" name="submit" align="middle">&nbsp;';
            // echo '<input type="Button" value="Cancel" name="cancel" align="middle" onClick="javascript:history.back()"> ';
            echo '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';
            ?>
            </div>
          </form>
        </td>
    </tr>
 </table>


<?php

}


mysql_close($dbh);

require_once 'include/foot.php';
?>
