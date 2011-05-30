<?php
require_once 'config/main.php';
require_once 'include/head.php';

$URL = set_page();

// Unset created id --> is only for step2 refreshs
unset($_SESSION["created_id"]);

if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

if( ( isset($_POST["config_class"]) ) AND ($_POST["config_class"] != "") ){
    $config_class = $_POST["config_class"]; 
}
if( ( isset($_POST["HIDDEN_host_ID"]) ) AND ($_POST["HIDDEN_host_ID"] != "") ){
    $host_ID = $_POST["HIDDEN_host_ID"]; 
}
if( ( isset($_POST["HIDDEN_count"]) ) AND ($_POST["HIDDEN_count"] != "") ){
    $check_count = $_POST["HIDDEN_count"]; 
}elseif ( !empty($_GET["count"]) ){
    $check_count = $_GET["count"];
}else{
    $check_count = "";
}
if( ( isset($_GET["id"]) ) AND ($_GET["id"] != "") ){
    $host_ID = $_GET["id"]; 
}

// show services from server or add data from step2 form
// Check if submit is allowed


$item_name = db_templates("naming_attr", $host_ID);
$title = '&nbsp;Services of '.$item_name;

if( isset($_SESSION["submited_step2"]) ){
    // From step 2 of add host

    // Write2DB (feedback: $step2)
    require_once 'include/add_item_step2_write2db.php';
    unset($_SESSION["submited_step2"]);

    // add step3 to title
    $title = "&nbsp;Step 3: $title";

}elseif( isset($_POST["add_service"]) ){
    if( $_SESSION["count"] == $check_count){
        // Add Service (button clicked)
        require_once 'include/add_item_step2_write2db.php';
    }else{       
        // refresh of site not allowed
        message($info, TXT_NO_RESENT);
        message($error, 'The Session counter is : '.$_SESSION["count"].'<br> The submited form counter is '.$check_count);
    }
}elseif( isset($_GET["action"]) AND $_GET["action"] == "cloneONhost" ){
    if( $_SESSION["count"] == $check_count){
        // Add Service (button clicked)
        require_once 'clone_service_write2db.php';
    }else{       
        // refresh of site not allowed
        message($info, TXT_NO_RESENT);
        message($error, 'The Session counter is : '.$_SESSION["count"].'<br> The submited get counter is '.$check_count);
    }
}else{
    // Only show Services
}

# load tab
require_once 'include/tabs/service.php';

echo '<h2>'.$title.'</h2><br>';

echo ' <table border=0>
        <tr>
            <td>';



////
// Content of Page
////

$query = 'SELECT ConfigValues.fk_id_item AS id, attr_value AS entryname
            FROM ConfigValues, ConfigAttrs, ItemLinks
            WHERE id_attr = ConfigValues.fk_id_attr
            AND naming_attr = "yes"
            AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
            AND fk_item_linked2 = '.$host_ID
         ;
$result = db_handler($query, 'result', "Get services from host");

$checkcommands_checked = array();
while ($entry = mysql_fetch_assoc($result) ){
    $checkcommands_checked[] = $entry["entryname"];
}

# Get all commands
# (take this if Variant 2 does not work)
# ! sorting will not do correctly !
/*
$query = 'SELECT fk_id_item item_ID, attr_value, (
            SELECT attr_value
            FROM ConfigValues, ConfigAttrs
            WHERE ConfigValues.fk_id_item = item_ID
                AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                AND ConfigAttrs.attr_name = "default_service_name"
            ) AS default_service_name
    FROM ConfigValues,ConfigAttrs,ConfigClasses 
    WHERE id_attr=fk_id_attr 
    AND id_class=fk_id_class 
    AND config_class="checkcommand"
    AND naming_attr="yes" 
    ORDER BY attr_value';
*/

# (with IFNULL function of mysql)
# this will take "default_service_name" if not empty, else attr_value
$query = 'SELECT fk_id_item AS item_ID,
            attr_value AS check_name,
            (SELECT attr_value
            FROM ConfigValues, ConfigAttrs
            WHERE ConfigValues.fk_id_item = item_ID
                AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                AND ConfigAttrs.attr_name = "default_service_name"
            ) AS default_service_name,
            IFNULL(
                (SELECT attr_value
                FROM ConfigValues, ConfigAttrs
                WHERE ConfigValues.fk_id_item = item_ID
                    AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                    AND ConfigAttrs.attr_name = "default_service_name"
                )
            , attr_value) AS sorting
    FROM ConfigValues,ConfigAttrs,ConfigClasses 
    WHERE id_attr=fk_id_attr 
    AND id_class=fk_id_class 
    AND config_class="checkcommand"
    AND naming_attr="yes" 
    ORDER BY sorting';

$result = db_handler($query, 'result', "Get all Services");

echo '<form name="add_checkcommand_form" action="'.$URL.'" method="post">';
echo '<input name="HIDDEN_host_ID" type="hidden" value="'.$host_ID.'">';
echo '<table><tr><td colspan=2>Add additional services to host:</td></tr>';
echo '<tr><td><select name="add_checkcommand">';
while($checkcommands = mysql_fetch_assoc($result)){
    # create select field
    if ( !empty($checkcommands["default_service_name"]) ){
        echo '<option value='.$checkcommands["item_ID"].'>'.$checkcommands["default_service_name"].' ('.$checkcommands["check_name"].')</option>';
        $HIDDEN_checkcommands[$checkcommands["item_ID"]] = $checkcommands["default_service_name"];
    }elseif( !empty($checkcommands["check_name"]) ){
        echo '<option value='.$checkcommands["item_ID"].'>'.$checkcommands["check_name"].'</option>';
        $HIDDEN_checkcommands[$checkcommands["item_ID"]] = $checkcommands["check_name"];
    }
}

echo "</select>";

    if ( isset($HIDDEN_checkcommands) ){
        while ($checkcommand = each($HIDDEN_checkcommands) ){
            echo '<input name="HIDDEN_checkcommands['.$checkcommand["key"].']" type="hidden" value="'.$checkcommand["value"].'">';
        }
    }


echo "</td>";

?>
    <td id=buttons>
        <input name="HIDDEN_count" type="hidden" value="<?php echo ($_SESSION["count"] + 1);?>">
        &nbsp;&nbsp;<input type="submit" value="Add" name="add_service" align="middle">
    </td></tr></table>
</form>
<?php



############

echo '<br>&nbsp;Edit a host\'s existing services:<br>';

echo '<table class="noneborder simpletable bordertop" border=0>';
    echo '<tr class="bg_header">';
        echo '<td width=160>&nbsp;<b>'.FRIENDLY_NAME_SERVICES.'</b></td>';
        echo '<td width=30>&nbsp;<b>'.FRIENDLY_NAME_EDIT.'</b></td>';
        echo '<td width=50>&nbsp;<b>'.FRIENDLY_NAME_DELETE.'</b></td>';
        echo '<td width=50>&nbsp;<b>'.FRIENDLY_NAME_CLONE.'</b></td>';
    echo '</tr>'; 

    $query = 'SELECT ConfigValues.fk_id_item AS id, attr_value AS entryname
                FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND id_class = fk_id_class
                AND config_class = "service"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = '.$host_ID.'
                ORDER BY entryname
             ';
    message ($debug, $query);
    $result = mysql_query($query);
    $count = 1;
    while($entry = mysql_fetch_assoc($result)){
        if((1 & $count) == 1){
            echo '<tr class="color_list1 highlight">';
        }else{
            echo '<tr class="color_list2 highlight">';
        }
        echo '<td>&nbsp;<a href="detail.php?id='.$entry["id"].'">'.$entry["entryname"].'</a></td>';
        echo '<td align="center">&nbsp;<a href="modify_item.php?item=service&id='.$entry["id"].'">'.OVERVIEW_EDIT.'</a></td>';
        echo '<td><div align=center><a href="delete_item.php?item=service&ids='.$entry["id"].'&from=modify_item_service.php?id='.$host_ID.'">'.OVERVIEW_DELETE.'</a></div></td>';
        echo '<td><div align=center><a href="modify_item_service.php?item=service&action=cloneONhost&count='.($_SESSION["count"] + 1).'&id='.$host_ID.'&service_id='.$entry["id"].'">'.ICON_CLONE.'</a></div></td>';
        echo "</tr>\n";

        $count++;
    }

echo '</table>';

echo '<br><br><div id=buttons><input type="button" name="finish" value="Finish" onClick="window.location.href=\'overview.php?class=host\'"></div>';



?>
            </td>
        </tr>
    </table>
<?php

mysql_close($dbh);

require_once 'include/foot.php';
?>
