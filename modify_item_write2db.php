<?php
require_once 'config/main.php';
require_once 'include/head.php';

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

if( ( isset($_POST["HIDDEN_config_class"]) ) AND ($_POST["HIDDEN_config_class"] != "") ){
    $config_class = $_POST["HIDDEN_config_class"]; 
}

if( ( isset($_POST["HIDDEN_modify_id"]) ) AND ($_POST["HIDDEN_modify_id"] != "") ){
    $id = $_POST["HIDDEN_modify_id"]; 
}



// DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
if ( (isset($_POST["deny_modification"]) AND ($_POST["deny_modification"] == TRUE))
OR (
    ($_SESSION["group"] != "admin")
    AND ($config_class == "contact")
    AND (
          ( isset($_POST["ID_nc_permission"]) )
          AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin")
        )
    )
   )
){
    // Disable the submit button and add message
    include('include/stop_user_modifying_admin_account.php');
}




# Modify = from modify, write to db
# vererben = assign changes to linked services
if ( isset($_POST["modify"]) ){

    # Implode the splitet fields (exploded in modify_item.php)
    if(  ( isset($_POST["exploded"]) ) AND ( is_array($_POST["exploded"]) )  ){
        foreach ($_POST["exploded"] as $field_key => $value_array) {
            # string starts with a "!"
            $imploded = "!";
            # implode the other arguments
            $imploded .= implode("!", $value_array);
            # Save it to the POST-var, so the var will be added later in this script
            $_POST[$field_key] = $imploded;
        }
    }

    ########
    # Get variables for check which one has changed (for history entries)

    # GET linked data for checking if they has changed (array entries)
    # get linked entries (ItemLinks) for passed id
    $query_old_linked_data = 'SELECT id_attr,attr_value,fk_item_linked2
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                    AND id_attr=ItemLinks.fk_id_attr
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                    AND ItemLinks.fk_id_item='.$id.'
                    ORDER BY
                    ConfigAttrs.friendly_name DESC,
                    ItemLinks.cust_order
                    ';

    $result_old_linked_data = db_handler($query_old_linked_data, "result", "get linked entries");

    $old_linked_data = array();
    while($entry2 = mysql_fetch_assoc($result_old_linked_data)){
        $old_linked_data[$entry2["id_attr"]][] = $entry2["fk_item_linked2"];
    }

    # get entries linked as child (ItemLinks) for passed id   (without the childs saved in the parents!)
    $query_old_linked_child_data = 'SELECT id_attr,attr_value,ItemLinks.fk_id_item
                FROM ConfigValues,ItemLinks,ConfigAttrs
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND ConfigAttrs.visible="yes"
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                AND ItemLinks.fk_item_linked2='.$id.'
                AND ConfigAttrs.attr_name <> "parents"
                ORDER BY ConfigAttrs.friendly_name DESC';

    $result_old_linked_child_data = db_handler($query_old_linked_child_data, "result", "get linked as child entries");
    while($entry3 = mysql_fetch_assoc($result_old_linked_child_data)){
        $old_linked_data[$entry3["id_attr"]][] = $entry3["fk_id_item"];
    }

    # Get old variables finished
    ########




    # Check for existing entry
    $query = 'SELECT id_attr
                FROM ConfigAttrs,ConfigClasses
                WHERE naming_attr="yes"
                    AND id_class=fk_id_class
                    AND config_class="'.$config_class.'"
             ';

    $id_naming_attr = db_handler($query, "getOne", "naming_attr ID:");

    $query = 'SELECT attr_value, fk_id_item
                FROM ConfigValues
                WHERE fk_id_attr='.$id_naming_attr.'
                AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
                AND fk_id_item <>'.$id.'
            ';
    $result = db_handler($query, "result", "does entry already exist");
            
    # Entry exists ?
    if ( (mysql_num_rows($result)) AND ($config_class != "service") ){
        echo 'Entry with name &quot;'.$_POST[$id_naming_attr].'&quot; already exists!';
        echo '<br><br>Click for details: ';
        while($entry = mysql_fetch_assoc($result)){
            echo '<a href="detail.php?id='.$entry["fk_id_item"].'">'.$entry["attr_value"].'</a>';
        }
        echo '<br><br>or go <a href="javascript:history.go(-1)">back</a>';
    }else{
        #entry not existing, lets try to modify:

        if ($config_class == "host") {
            # Vererben ?
            $vererben1_result = db_templates("vererben", $id);
            while($row = mysql_fetch_assoc($vererben1_result)){
                $vererben1[$row["item_id"]] = $row["attr_name"];
            }
        }

        echo "<h2>Modify $config_class</h2>";
        ?>
        <table>
            <tr>
                <td>
        <?php

        # Check mandatory fields
        $m_array = db_templates("mandatory", $config_class);
        $write2db = check_mandatory($m_array,$_POST);


        # check oncall groups when class is host or service
        if ($config_class == "host" OR $config_class == "service") {
            # if failed do not allow write2db
            if ( oncall_check() == FALSE ){
                $write2db = 'no';
            }
        }



        if ($write2db == "yes"){
            ################
            #### write to db
            ################

            while ( $attr = each($_POST) ){
                if ( is_int($attr["key"]) ){
                    // Get name of attribute:
                    $attr_name = db_templates("friendly_attr_name", $attr["key"]);
                    if ( $attr_name ){
                        message ($debug, $attr_name, 'grouptitle' );
                    }

                    if ( is_array($attr["value"]) ){
                        # modify assign_one/assign_many/assign_cust_order in ItemLinks
                        # get datatype for handling assign_cust_order
                        $attr_datatype = db_templates("attr_datatype", $attr["key"]);

                        # Check if the values are modifyied, only save changed values
                        if ( !isset($old_linked_data[$attr["key"]]) ){
                            $old_linked_data[$attr["key"]] = array("0" => "");
                        }

                        /*
                        echo "<br><br>saved array:";
                        var_dump($old_linked_data[$attr["key"]]);
                        echo '<br><b>new array '.$attr["key"].':</b>';
                        var_dump($attr["value"]);
                        */

                        # Assigned items
                        if ($attr_datatype == "assign_cust_order"){
                            # compare arrays with additional index check
                            $diff_array = array_diff_assoc($attr["value"] ,$old_linked_data[$attr["key"]]);
                        }else{
                            # normal compare of arrays
                            $diff_array = array_diff($attr["value"] ,$old_linked_data[$attr["key"]]);
                        }
                        if ( !empty($diff_array) ){
                            while ( $attr_added = each($diff_array) ){
                                history_add("assigned", $attr_name, $attr_added["value"], $id, "resolve_assignment");
                            }
                        }

                        # Unassigned items
                        if ($attr_datatype == "assign_cust_order"){
                            # compare arrays with additional index check
                            $diff_array2 = array_diff_assoc($old_linked_data[$attr["key"]], $attr["value"]);
                        }else{
                            # normal compare of arrays
                            $diff_array2 = array_diff($old_linked_data[$attr["key"]], $attr["value"]);
                        }
                        if ( !empty($diff_array2) ){
                            while ( $attr_removed = each($diff_array2) ){
                                history_add("unassigned", $attr_name, $attr_removed["value"], $id, "resolve_assignment");
                            }
                        }

                        /*
                        echo "<pre>";
                        var_dump($diff_array);
                        var_dump($diff_array2);
                        echo "</pre>";
                        */

                        if ( (count($diff_array) OR count($diff_array2) ) != 0 ){
                            message ($info, "Attribute '$attr_name' has changed.");
                        }else{
                            message ($debug, 'no changes in this attribute');
                            ########## CONTINUE IF ATTRIBUTE WAS NOT CHANGED   ############
                            continue;
                        }



                        ###########################
                        ### Delete old links

                        $lac_query = 'SELECT link_as_child
                                        FROM ConfigAttrs
                                        WHERE id_attr = "'.$attr["key"].'"
                                        ';
                        $lac_result = db_handler($lac_query, "getOne", "delete: link as child?");



                        // is actual id the "servicegroup"?
                        // Attention, very special hack!
                        $servicegroup_select = 0;
                        $servicegroup_id = db_templates("servicegroup_id");
                        if ( $servicegroup_id == $attr["key"] ){
                            $servicegroup_select = 1;
                            message($debug, "delete: servicegroup matched");
                        }
                        
                        //Querys
                        $delete_query_lac = 'DELETE FROM ItemLinks
                                    WHERE fk_id_attr="'.$attr["key"].'"
                                    AND fk_item_linked2="'.$id.'"
                                    ';

                        $delete_query = 'DELETE FROM ItemLinks
                                    WHERE fk_id_attr="'.$attr["key"].'"
                                    AND fk_id_item="'.$id.'"
                                    ';

                        if ( (($lac_result == "yes") AND ($servicegroup_select != 1)) OR (($lac_result == "yes") AND ($servicegroup_select == 1) AND ($config_class == "servicegroup")) ){
                            db_handler($delete_query_lac, "delete", "Delete link as child");
                        }else{
                            db_handler($delete_query, "delete", "Delete (not link as child)");
                        }

                        #########
                        ### Insert new links

                        # counter for assign_cust_order
                        $cust_order = 0;
                        # save assign_one/assign_many/assign_cust_order in ItemLinks
                        while ( $many_attr = each($attr["value"]) ){
                            # if value is empty go to next one
                            if (!$many_attr["value"]){
                                continue;
                            }else{
                                # check link_as_child option
                                $lac_query = 'SELECT link_as_child
                                                FROM ConfigAttrs
                                                WHERE id_attr = "'.$attr["key"].'"
                                                ';
                                $lac_result = db_handler($lac_query, "getOne", "get link as child");

                                $servicegroup_select = 0;
                                $servicegroup_id = db_templates("servicegroup_id");
                                if ( $servicegroup_id == $attr["key"] ){
                                    $servicegroup_select = 1;
                                    message($debug, "servicegroup matched");
                                }

                                # if the circumstances are correct, link as child
                                if ( (($lac_result == "yes") AND ($servicegroup_select != 1)) OR (($lac_result == "yes") AND ($servicegroup_select == 1) AND ($config_class == "servicegroup")) ){
                                    $query = 'INSERT INTO ItemLinks
                                    (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                                    VALUES
                                    ('.$many_attr["value"].', '.$id.', '.$attr["key"].', '.$cust_order.')';
                                # otherwise link items normally
                                }else{
                                    $query = 'INSERT INTO ItemLinks
                                        (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                                        VALUES
                                        ('.$id.', '.$many_attr["value"].', '.$attr["key"].', '.$cust_order.')';
                                }    
                                message ($debug, $query);

                                if (DB_NO_WRITES != 1) {
                                    if ( mysql_query($query) ){
                                        message ($debug, 'Successfully linked "'.$many_attr["value"].'" with '.$attr["key"]);
                                    }else{
                                        message ($error, 'Error while linking '.$many_attr["value"].' with '.$attr["key"].':'.$query);
                                    }
                                }

                            }

                            # increase assign_cust_order if needed
                            if ($attr_datatype == "assign_cust_order") $cust_order++;

                        }

                    }else{
                        # Lookup datatype
                        $query = 'SELECT ConfigValues.attr_value, ConfigAttrs.datatype FROM `ConfigAttrs`, ConfigValues
                                    WHERE ConfigAttrs.id_attr = "'.$attr["key"].'"
                                    AND ConfigValues.fk_id_attr = ConfigAttrs.id_attr
                                    AND ConfigValues.fk_id_item = "'.$id.'"';

                        $check = db_handler($query, "assoc", "Lookup value and datatype");
                        if ($check == FALSE){
                            $check["datatype"] = db_templates("attr_datatype", $attr["key"]);
                        }
                        
                        // Check if the value has changed
                        if ( !isset($check["attr_value"]) OR ($check["attr_value"] != $attr["value"]) ){
                            if ($check["datatype"] == "password"){
                                // IF Password field is a encrypted, do not save
                                if ( preg_match( '/^{.*}/', $attr["value"]) ){
                                    message ($info, "encrypted field will not be saved");
                                    continue;
                                }elseif ( (PASSWD_DISPLAY == 0) AND  ( strpos($attr["value"], PASSWD_HIDDEN_STRING) !== false) ){
                                    // Passwort was displayed as "hidden" like "********", do not save
                                    message ($info, "passwd was hidden and not modified");
                                    continue;
                                }else{
                                    $insert_attr_value = encrypt_password($attr["value"]);
                                }
                            }else{
                                // modify text/select
                                $insert_attr_value = escape_string($attr["value"]);
                            }

                            $query =   'INSERT INTO ConfigValues
                                            (attr_value, fk_id_attr, fk_id_item)
                                        VALUES
                                            ("'.$insert_attr_value.'", "'.$attr["key"].'", '.$id.' )
                                        ON DUPLICATE KEY UPDATE
                                            attr_value="'.$insert_attr_value.'"
                                        ';

                            $insert = db_handler($query, "insert", 'Insert entry');
                            if ($insert){
                                message ($debug, 'Successfully added ('.stripslashes($insert_attr_value).')');
                                history_add("modified", $attr["key"], $insert_attr_value, $id);
                            }else{
                                message ($error, 'Error while adding '.stripslashes($insert_attr_value).':'.$query);
                            }
                        }{
                            // The data value has not changed, so no saving is needed
                        }
                    }
                }else{
                    continue;
                }
            }
            if (DB_NO_WRITES != 1) {
                message ($info, '<br><b>Successfully modified '.$config_class.'.</b>');

                if ($config_class == "host") {
                    # Vererben ?
                    $vererben2_result = db_templates("vererben", $id);
                    while($row = mysql_fetch_assoc($vererben2_result)){
                        $vererben2[$row["item_id"]] = $row["attr_name"];
                    }
         
                    # Ask for make the changes also to the linked services
                    if ($vererben1 !== $vererben2) {
                        $update_button  = '<form name="vererben" action="'.$_SERVER["PHP_SELF"].'" method="post">';
                        $update_button .= '<input name="HIDDEN_modify_id" type="hidden" value="'.$_POST["HIDDEN_modify_id"].'">';
                        $update_button .= '<br><div id=buttons>';
                        $update_button .= '<input type="Submit" value="yes" name="vererben" align="middle">';
                        $update_button .= '&nbsp;<input type=button name="no" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="no">';
                        $update_button .= '</div>';
                        $update_button .= '</form>';
                        message ($info, TXT_UPDATE_SERVICES.'<br>'.$update_button);
                    }
                }

                echo "<br>$info<br>";

            }else{
                message ($info, '<b>Modify '.$config_class.' should work fine...</b>');
            }
            
            # Delete session
            if (isset($_SESSION["cache"]["modify"])) unset($_SESSION["cache"]["modify"]);

            if ( isset($_SESSION["go_back_page_ok"]) AND !isset($update_button) ){
                // Go to next page without pressing the button
                echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
                message($info, '...redirecting to <a href="'.$_SESSION["go_back_page_ok"].'">page</a> in '.REDIRECTING_DELAY.' seconds...');
            }
        }
        # end of write2db
        if ($error) {
            
            echo "<b>Error:</b><br><br>";
            echo $error;
            echo "<br><br>";
            echo '<form name="modify" action="'.$_SESSION["go_back_page"].'" method="post">';
                echo '<div id=buttons>';
                echo '<input type="Submit" value="Back" name="back" align="middle">';
                echo '</div>';
            echo '</form>';
            foreach ($_POST as $key => $value) {
                $_SESSION["cache"]["modify"][$key] = $value;
            }
        }else{
            if (isset($_SESSION["cache"]["modify"])) unset($_SESSION["cache"]["modify"]);
        }

        ?>
                    </td>
                </tr>
            </table>
        <?php

    } # END Entry exists ?


}elseif( isset($_POST["vererben"]) ){

    $query_services = 'SELECT fk_id_item AS service_id
            FROM ItemLinks, ConfigItems, ConfigClasses 
            WHERE fk_id_item=id_item 
                AND fk_id_class=id_class 
                AND config_class="service" 
                AND fk_item_linked2='.$id;
 
    // These services will be modified
    $services = db_handler($query_services, "array", "get all Services");

    // this array is needed to delete all these services
    $change_attrs = array("check_period", "notification_period", "contact_groups");

    # 1. delete existing timeperiod & contactgroup links of service
    foreach( $services as $service ){
        foreach( $change_attrs as $change_attr ){
                $query = 'DELETE FROM ItemLinks 
                    WHERE fk_id_item='.$service["service_id"].' 
                    AND fk_id_attr = (SELECT id_attr FROM ConfigAttrs,ConfigClasses
                       WHERE id_class=fk_id_class
                       AND config_class="service"
                       AND attr_name="'.$change_attr.'")';
                db_handler($query, "delete", "delete linked");
         }
    }

    # 2. create new links between service and timeperiod/contactgroup (same as host)
    # This result will contain the items information to link to (e.g.: notification timeperiod, check period, contactgroup)
    $vererben_res = db_templates("vererben", $id);
    foreach( $services as $service ){
        while ($new_link = mysql_fetch_assoc($vererben_res)){
              $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr)
                    VALUES ('.$service["service_id"].','.$new_link["item_id"].',
                           (SELECT id_attr FROM ConfigAttrs,ConfigClasses
                               WHERE id_class=fk_id_class
                               AND config_class="service"
                               AND attr_name="'.$new_link["attr_name"].'"))';   
                db_handler($query, "insert", "insert linked");
        }
        mysql_data_seek($vererben_res, 0);
    }



    message ($info, '<b>Successfully updated all linked services.</b>');
    echo "<br>$info<br>";

    if ( isset($_SESSION["go_back_page"]) ){
/*
        echo '<div id=buttons>';
        echo '<input name=finish type=button onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="Finish">';
        echo '</div>';
*/
        // Go to next page without pressing the button
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
        message($info, '...redirecting to <a href="'.$_SESSION["go_back_page_ok"].'">page</a> in '.REDIRECTING_DELAY.' seconds...');
    }


}



mysql_close($dbh);
require_once 'include/foot.php';
?>
