<?php
require_once 'config/main.php';
require_once 'include/head.php';

$service_name_changed = FALSE;

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

# Set info_summary, feedback of modifications
$info_summary["ok"] = array();
$info_summary["failed"] = array();
$info_summary["ignored"] = array();

# name of selected attribute
if ( !empty($_POST["HIDDEN_selected_attr"]) ){
    $HIDDEN_selected_attr = $_POST["HIDDEN_selected_attr"];
}
if( ( isset($_POST["HIDDEN_config_class"]) ) AND ($_POST["HIDDEN_config_class"] != "") ){
    $config_class = $_POST["HIDDEN_config_class"]; 
}

$array_ids = explode(",", $_POST["HIDDEN_modify_ids"]);

# predefine ask vererben variable:
$ask_vererben = 0;



# ONCALL CHECK
# check oncall groups when try modifying it and class is host or service
if ( isset($_POST["modify"]) AND ( $config_class == "host" OR $config_class == "service") ) {
    # get id of contact_group attr
    $contact_group_id = db_templates("get_attr_id", $config_class, "contact_groups");
    if ( isset($_POST[$contact_group_id]) ){
        # if failed do not allow write2db
        $oncall_check = oncall_check();
    }
}

# Check mandatory fields
while ( $attr = each($_POST) ){
    if ( is_int($attr["key"]) ){
        # Check mandatory fields
        $m_array = db_templates("mandatory", $config_class, $attr["key"]);
        if ( check_mandatory($m_array,$_POST) == "no"){
            $write2db = "no";
        }
    }
}

# Give error message if oncall or mandatory check fails
if ( ( isset($oncall_check) AND $oncall_check === FALSE )
  OR ( isset($write2db) AND $write2db == "no")
){
            echo "<b>Error:</b><br><br>";
            echo $error;
            echo "<br><br>";
            echo '<form name="modify" action="'.$_SESSION["go_back_page"].'" method="post">';
                echo '<div id=buttons>';
                echo '<input type="Submit" value="Back" name="back" align="middle">';
                echo '</div>';
            echo '</form>';

            mysql_close($dbh);
            require_once 'include/foot.php';

            exit;
}


/*
# ONCALL CHECK
# check oncall groups when try modifying it and class is host or service
if ( isset($_POST["modify"]) AND ( $config_class == "host" OR $config_class == "service") ) {
    # get id of contact_group attr
    $contact_group_id = db_templates("get_attr_id", $config_class, "contact_groups");
    if ( isset($_POST[$contact_group_id]) ){
        # if failed do not allow write2db
        if ( oncall_check() == FALSE ){
            echo "<b>Error:</b><br><br>";
            echo $error;
            echo "<br><br>";
            echo '<form name="modify" action="'.$_SESSION["go_back_page"].'" method="post">';
                echo '<div id=buttons>';
                echo '<input type="Submit" value="Back" name="back" align="middle">';
                echo '</div>';
            echo '</form>';

            mysql_close($dbh);
            require_once 'include/foot.php';

            exit;
        }
    }
}
# END of ONCALL CHECK
*/

# save attribute to each item
foreach ($array_ids as $id){

    # Unset and reset some vars for multiple runs
    unset($info);
    # Reset pointer of $_POST
    reset($_POST);

    # Title of id
    $name = db_templates("naming_attr", $id);
    if ($config_class == "service"){
        # get host name of service
        $hostID   = db_templates("hostID_of_service", $id);
        $hostname = db_templates("naming_attr", $hostID);
        $name = $hostname.":".$name;
    }


    # DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
    if ( (isset($_POST["deny_modification"]) AND ($_POST["deny_modification"] == TRUE))
    OR (
        ($_SESSION["group"] != "admin")
        AND ($config_class == "contact")
        AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin") )
       )
    ){
        # Disable the submit button and add message
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

        if ( isset($_POST[$id_naming_attr]) AND $config_class != "service"){
            # naming attr not allowed
            message($error, "Naming attribute cannot be modified with multiple items");

        }else{
            # entry is not a naming attr, lets try to modify:
            
            if ($config_class == "host") {
                # Vererben ?
                if ( isset($vererben1) ) unset($vererben1);
                $vererben1_result = db_templates("vererben", $id);
                while($row = mysql_fetch_assoc($vererben1_result)){
                    $vererben1[$row["item_id"]] = $row["attr_name"];
                }
            }

            $write2db = "yes";
            if ($write2db == "yes"){
                ################
                #### write to db
                ################
                while ( $attr = each($_POST) ){
                    if ( is_int($attr["key"]) ){
                        # Get name of attribute:
                        $attr_name = db_templates("friendly_attr_name", $attr["key"]);
                        if ( $attr_name ){
                            message ($debug, $attr_name, 'grouptitle' );
                            $HIDDEN_selected_attr = $attr_name;
                        }

                        if ( is_array($attr["value"]) ){
                            # modify assign_one/assign_many in ItemLinks
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
                                $info_summary["ok"][] = $name;
                            }else{
                                $info_summary["ignored"][] = $name;
                                message ($debug, 'no changes in this attribute');
                                ########## CONTINUE IF ATTRIBUTE WAS NOT CHANGED   ############
                                continue;
                            }


                            ###########################
                            ### Delete old links
                            ###########################

                            $lac_query = 'SELECT link_as_child
                                            FROM ConfigAttrs
                                            WHERE id_attr = "'.$attr["key"].'"
                                            ';
                            $lac_result = db_handler($lac_query, "getOne", "delete: link as child?");



                            # is actual id the "servicegroup"?
                            # Attention, very special hack!
                            $servicegroup_select = 0;
                            $servicegroup_id = db_templates("servicegroup_id");
                            if ( $servicegroup_id == $attr["key"] ){
                                $servicegroup_select = 1;
                                message($debug, "delete: servicegroup matched");
                            }
                            
                            # Querys
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
                            #########

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
                            
                            # Check if the value has changed
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

                                # check for service name (dublicates are not allowed, so generate an name which is not already used in this host)
                                if ( isset($_POST[$id_naming_attr]) AND $config_class == "service" ){
                                    # check the service name, it should not be the same as the source service

                                    # get all service names of destination server
                                    $host_ID = db_templates("hostID_of_service", $id);
                                    $existing_service_names = db_templates("get_services_from_host_id", $host_ID);


                                    # when service name does not exist, we can add service with its name
                                    # otherwise we have to create an other name:
                                    $new_service_name = $insert_attr_value;
                                    if ( in_array($new_service_name, $existing_service_names) ){
                                        $service_name_changed = TRUE;
                                        //$new_service_name = $new_service_name.'_copy';
                                        //if ( in_array($new_service_name, $existing_service_names) ){
                                            //# service name with "_copy" also already exists
                                            # create a service name with "_" and a number, until we found a service name which is not used
                                            $new_service_name = $insert_attr_value.'_';
                                            $i = 1;
                                            do{
                                                $i++;
                                                $try_service_name = $new_service_name.$i;
                                            }while( in_array($try_service_name, $existing_service_names) );
                                            # found a services name, which does not exist
                                            $new_service_name = $try_service_name;
                                        //}
                                    }
                                    # give the service name back for writing to db
                                    $insert_attr_value = $new_service_name;
                                }



                                # save value to DB
                                $query =   'INSERT INTO ConfigValues
                                                (attr_value, fk_id_attr, fk_id_item)
                                            VALUES
                                                ("'.$insert_attr_value.'", "'.$attr["key"].'", '.$id.' )
                                            ON DUPLICATE KEY UPDATE
                                                attr_value="'.$insert_attr_value.'"
                                            ';

                                $insert = db_handler($query, "insert", 'Insert entry');
                                if ($insert){
                                    //message ($debug, 'Successfully added ('.stripslashes($insert_attr_value).')');
                                    $info_summary["ok"][] = $name;
                                    history_add("modified", $attr["key"], $insert_attr_value, $id);
                                }else{
                                    message ($error, 'Error while adding '.stripslashes($insert_attr_value).':'.$query);
                                    $info_summary["failed"][] = $name;
                                }
                            }else{
                                // The data value has not changed, so no saving is needed
                                //echo 'The value is not different, so no change is needed.<br><br>';
                                $info_summary["ignored"][] = $name;
                            }
                        }
                    }else{
                        continue;
                    }
                }

                if ($config_class == "host") {

                    # Vererben ?
                    if ( isset($vererben2) ) unset($vererben2);
                    $vererben2_result = db_templates("vererben", $id);
                    while($row = mysql_fetch_assoc($vererben2_result)){
                        $vererben2[$row["item_id"]] = $row["attr_name"];
                    }
                    if ($vererben1 !== $vererben2) {
                        $ask_vererben = 1;
                    }
                }

/*
                # Delete session
                if (isset($_SESSION["cache"]["modify"])) unset($_SESSION["cache"]["modify"]);
                if ( isset($_SESSION["go_back_page_ok"]) AND !isset($update_button) ){
                    // Go to next page without pressing the button
                    echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
                    message($info, '...redirecting to <a href="'.$_SESSION["go_back_page_ok"].'">page</a> in '.REDIRECTING_DELAY.' seconds... !');
                }
*/
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
                //foreach ($_POST as $key => $value) {
                //    $_SESSION["cache"]["modify"][$key] = $value;
                //}
            }else{
                if (isset($_SESSION["cache"]["modify"])) unset($_SESSION["cache"]["modify"]);
            }


        } // END Entry exists ?


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



        # Successfully updated all linked services
        $info_summary["ok"][] = $name;

    }




} //for each ID

$_SESSION["go_back_page"] = str_replace("&goto=multimodify", "", $_SESSION["go_back_page"]);
if ($config_class == "host") {

    # Ask for make the changes also to the linked services
    if ($ask_vererben) {
        $update_button  = '<br><form name="vererben" action="'.$_SERVER["PHP_SELF"].'" method="post">';
        $update_button .= '<input name="HIDDEN_modify_ids" type="hidden" value="'.$_POST["HIDDEN_modify_ids"].'">';
        $update_button .= '<input name="HIDDEN_config_class" type="hidden" value="'.$_POST["HIDDEN_config_class"].'">';
        $update_button .= '<input name="HIDDEN_selected_attr" type="hidden" value="'.$HIDDEN_selected_attr.'">';
        $update_button .= '<br><div id=buttons>';
        $update_button .= '<input type="Submit" value="yes" name="vererben" align="middle">';
        $update_button .= '&nbsp;<input type=button name="no" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="no">';
        $update_button .= '</div>';
        $update_button .= '</form>';
        message ($info, TXT_UPDATE_SERVICES.'<br>'.$update_button, "overwrite");
        echo "<br><br>$info";
    }
}


// not needed anymore, i think...
/*
if ( isset($_SESSION["go_back_page"]) AND (!$ask_vererben) ){
    // Go to next page without pressing the button
    if ($service_name_changed){
        message($info, '<a href="'.$_SESSION["go_back_page"].'">click here to continue</a>', "overwrite");
        echo "<br><br>$info";
    }
}
*/



#
# Feedback of modifications
#

# if failed or ignored, do not redirect automatic
if ( !empty($info_summary["failed"]) ){
    echo '<div id=buttons>
        <input type=button name="next" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="Finish">
      </div>
    ';
}

# failed
if ( !empty($info_summary["failed"]) ){
    echo "<h2>Failed to modify $HIDDEN_selected_attr on $config_class:</h2><ul>";
    foreach ($info_summary["failed"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";
}
# ignored
if ( !empty($info_summary["ignored"]) ){
    echo "<h2>Item(s) skipped:</h2>";
    echo 'No changes necessary for the following item(s)';
    echo "<ul>";
    foreach ($info_summary["ignored"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";
}

# ok
if ( !empty($info_summary["ok"]) ){
    echo "Successfully modified &quot;<b>$HIDDEN_selected_attr</b>&quot; of $config_class(s):<ul>";
    foreach ($info_summary["ok"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";
}

# Auto redirect if no action was failed and ask_vererben is not true
if ( !$ask_vererben AND empty($info_summary["failed"]) AND ( !empty($info_summary["ignored"])  OR  !empty($info_summary["ok"]) )  ){
    message($info, '...redirecting to <a href="'.$_SESSION["go_back_page_ok"].'">page</a> in '.REDIRECTING_DELAY.' seconds... !', "overwrite");
    echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
}

mysql_close($dbh);
require_once 'include/foot.php';
?>
