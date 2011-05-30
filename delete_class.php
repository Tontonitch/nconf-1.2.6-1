<?php
require_once 'config/main.php';
require_once 'include/head.php';
set_page();

?>
<h2>Delete Class</h2><br>
<?php


if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["id"]) ) AND ($_POST["id"] != "") )
  ){

    # Delete entry
    $query = 'DELETE FROM ConfigClasses
                WHERE id_class='.$_POST["id"];

    $result = db_handler($query, "result", "Delete Class");
    if ($result) {
        echo TXT_DELETED;
        history_add("removed", "Class", $_POST["class_name"]);

        # set url for redirect
        //$url = $_SESSION["after_delete_page"];
        if ( !empty($_SESSION["after_delete_page"]) ){
            $url = $_SESSION["after_delete_page"];
        }elseif ( !empty($_SESSION["go_back_page_ok"]) ){
            $url = $_SESSION["go_back_page_ok"];
        }else{
            // should never go into this.
            $url = "show_class.php";
        }
            
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        message($info, '...redirecting to <a href="'.$url.'">page</a> in '.REDIRECTING_DELAY.' seconds...');
    }else{
        message ($error, 'Error when deleting class '.$_POST["id"].':'.$query);
    }

}else{
    if ( !empty($_SERVER["HTTP_REFERER"]) ){
        $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    }
    # class name
    $query = 'SELECT config_class FROM ConfigClasses where id_class='.$_GET["id"];
    $class_name = db_handler($query, 'getOne', "get class name");

    // Fetch attr name
    $query = 'SELECT attr_name  FROM ConfigAttrs, ConfigClasses WHERE id_class='.$_GET["id"].' AND fk_id_class=ConfigClasses.id_class';
    $attr = db_handler($query, "array", "Get Attrs of this Class");
    ?>

    <table width=410 cellspacing=0 cellpadding=0>
    <tr>
        <td width=250>
            <?php
            if ( isset($attr[0]["attr_name"]) ){
                echo '<b>WARNING</b>
                <br>
                The class you chose to delete contains one or more attributes. 
                <br>If you proceed, all items belonging to this class, all attributes and 
                <br>any asscociated data will be lost!
                <br><br>Are you ABSOLUTELY SURE you want to proceed?
                <br><br>List of attributes defined for this class:<br>(items using these attributes are not listed here explicitly)
                <br><ul>';
                foreach($attr as $item){
                    echo '<li>'.$item["attr_name"].'</li>';
                }
                echo '</ul>';
            }else{
                echo 'No attributes defined for this class.<br>You may safely delete the &quot;<b>'.$class_name.'</b>&quot; class.<br><br>';
            }
            ?>
        </td>
    </tr>
    <tr>
        <td>
          <form name="delete_class" action="delete_class.php" method="post">
            <input type="hidden" name="id" value="<?php echo $_GET["id"];?>">
            <input type="hidden" name="from" value="<?php echo $_GET["from"];?>">
            <input type="hidden" name="class_name" value="<?php echo $class_name;?>">
            <input type="hidden" name="delete" value="yes">
            <div id=buttons><br>
                <input type="Submit" value="Delete" name="submit" align="middle">&nbsp;
                <!-- <input type="Button" value="Cancel" name="cancel" align="middle" onClick="javascript:history.back()"> -->
                <input type=button onClick="window.location.href='<?php echo $_SESSION["go_back_page"]; ?>'" value="Back">
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
