<?php
require_once 'config/main.php';
require_once 'include/head.php';

$step2 = "no";

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

if( ( isset($_POST["config_class"]) ) AND ($_POST["config_class"] != "") ){
    $config_class = $_POST["config_class"]; 
}

    // DENY USER ADDING AN ADMIN ACCOUNT
    if( ($_SESSION["group"] != "admin")
        AND ($config_class == "contact")
        AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin") )
    ){
        // Cache other infos
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["add"][$key] = $value;
        }
        unset($_SESSION["cache"]["add"][$_POST["ID_nc_permission"]]);

        include('include/stop_user_modifying_admin_account.php');
    }



?>

<table>
    <tr>
        <td>

<?php
// Check if submit is allowed
if ( isset($_SESSION["submited"]) ){

    // Write2DB (feedback: $step2)
    require_once 'include/add_item_write2db.php';
    unset($_SESSION["submited"]);

}else{
    message($info, 'Sorry the submited infos are not allowed to resent, go to the form and submit it');
    if ( isset($_SESSION["created_id"]) ){
        $id = $_SESSION["created_id"];
        $step2 = "yes";
    }
}

# Content of Page

if ($step2 == "yes") {
    echo '<h2>Step 2 </h2><br>';

    if ( $config_class == "host" ){        
        # Show step 2 :
        $query = 'SELECT fk_item_linked2 
                    FROM ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE id_attr=fk_id_attr 
                    AND attr_name="host-preset"
                    AND id_class=fk_id_class 
                    AND config_class="host"
                    AND fk_id_item="'.$id.'"
                    ';
        $result = mysql_query($query);
        message ($debug, "host-preset : ".$query);
        if ( $result ){
            if ( mysql_num_rows($result) > 0 ){
                $hosttemplate = mysql_result($result, 0);
            }
            $step2 = "yes";
        }else{
            message($error, mysql_error() );
        }


    } // END $config_class == "host"


    ?>

    <form name="add_item_step2" action="modify_item_service.php?id=<?php echo $id; ?>" method="post">
    <input name="HIDDEN_config_class" type="hidden" value="<?php echo $config_class;?>">
    <input name="HIDDEN_host_ID" type="hidden" value="<?php echo $id;?>">
    <input name="HIDDEN_hosttemplate" type="hidden" value="<?php echo $hosttemplate;?>">
    <?php

    echo 'Select the services (checkcommands) for your host<br><br>';

    if ( isset($hosttemplate) ){
        // Get all checked commands
        $query = 'SELECT ItemLinks.fk_id_item,attr_value
            FROM ConfigValues,ConfigAttrs,ItemLinks,ConfigClasses 
            WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item 
            AND id_attr=ConfigValues.fk_id_attr 
            AND naming_attr="yes"
            AND id_class=fk_id_class 
            AND config_class="checkcommand"
            AND fk_item_linked2='.$hosttemplate;

        $result = mysql_query($query);
        while ($entry = mysql_fetch_assoc($result) ){
            $checkcommands_checked[] = $entry["fk_id_item"];
        }
   
    }else{
        $checkcommands_checked = array();
    }  // END isset(hosttemplate)

    // Get all commands
    $query = 'SELECT fk_id_item,attr_value 
        FROM ConfigValues,ConfigAttrs,ConfigClasses 
        WHERE id_attr=fk_id_attr 
        AND id_class=fk_id_class 
        AND config_class="checkcommand"
        AND naming_attr="yes" 
        ORDER BY attr_value';

    echo '<table cellpadding=3 border=0><tr>';

    $counter = 1;
    $result = mysql_query($query);
    while($checkcommands = mysql_fetch_assoc($result)){
        echo '<td valign=left>';
        echo '<input style="border:none !important; width:12px" type="checkbox" name="checkcommands['.$checkcommands["fk_id_item"].']" value="'.$checkcommands["attr_value"].'"';
        if ( in_array($checkcommands["fk_id_item"], $checkcommands_checked) ) echo ' CHECKED';
        echo '>&nbsp;&nbsp;'.$checkcommands["attr_value"];
        echo '</td><td width=10></td>';
        if($counter == 4){
            echo "</tr>";    
            $counter = 1;
        }else{
            $counter++;
        }
    }
    echo '</table><br>';

    # Tell the Session, send db query is ok (we are coming from formular)
    $_SESSION["submited_step2"] = "yes";
    ?>
    <table>
    <tr>
        <td>
            <div id=buttons>
                <input type="Submit" value="Submit" name="submit" align="middle">
                <input type="Reset" value="Reset">
            </div>
        </td>
    </tr>
    </table>
    </form>
    <?php
}else{

    if ( isset($_SESSION["cache"]["add"]) ) unset($_SESSION["cache"]["add"]);

    if ( !empty($error) ) {
        echo "<b>Error:</b><br><br>";
        echo $error;
        echo "<br><br>";
        echo '<div id=buttons>';
            echo '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';
        echo '</div>';

        // Cache
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["add"][$key] = $value;
        }

    }else{
        echo "<br>$info<br>";
        
        $url = 'overview.php?class='.$config_class;
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        message($info, '...redirecting to <a href="'.$url.'">overview</a>...');
    }


}



?>
            </td>
        </tr>
    </table>
<?php

mysql_close($dbh);

require_once 'include/foot.php';
?>
