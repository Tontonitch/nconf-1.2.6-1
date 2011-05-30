<?php


$step2 = "";


# Check for existing entry
$query = 'SELECT id_attr
            FROM ConfigAttrs,ConfigClasses
            WHERE naming_attr="yes"
                AND id_class=fk_id_class
                AND config_class="'.$config_class.'"
         ';
$id_naming_attr = db_handler($query, "getOne", "get naming_attr ID");

$query = 'SELECT attr_value, fk_id_item
            FROM ConfigValues
            WHERE fk_id_attr='.$id_naming_attr.'
            AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
        ';
$result = db_handler($query, "result", "Check if entry already exists");        

# Entry exists ?
if ( (mysql_num_rows($result)) AND ($config_class != "service") ){
    message($error, 'Entry with name &quot;'.$_POST[$id_naming_attr].'&quot; already exists! Click for details or go back:');
    while($entry = mysql_fetch_assoc($result)){
        message($error, '<a href="detail.php?id='.$entry["fk_id_item"].'">'.$entry["attr_value"].'</a>', "list");
    }

    # When user clicks on a listed item, and goes to delete it, the redirect must know where to go after delete, this would be the add page:
    $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    message($debug, 'Setting after delete page to : '.$_SERVER["HTTP_REFERER"]);

    $write2db = "no";
}else{
    #entry not existing

    # Check mandatory fields
    $m_array = db_templates("mandatory", $config_class);
    $write2db = check_mandatory($m_array,$_POST);


    # check oncall groups when class is host or service
    if ($config_class == "host" OR $config_class == "service"){
        #if failed do not allow write2db
        if ( oncall_check() == FALSE ){
            $write2db = 'no';
        }
    }





    if ($write2db == "yes"){
        ################
        #### write to db
        ################

        $query = 'INSERT INTO ConfigItems
                    (id_item, fk_id_class)
                    VALUES
                    (NULL, (SELECT id_class FROM ConfigClasses WHERE config_class = "'.$config_class.'") )
                    ';

        message ($debug, $query);

        if (DB_NO_WRITES != 1) {
            $insert = mysql_query($query);
        }else{
            $insert = TRUE;
        }

        if ( $insert ){
            # Get ID of insert:
            $id = mysql_insert_id();

            # add object CREATED to history
            history_add("created", $config_class, $_POST[$id_naming_attr], $id);            
            
            while ( $attr = each($_POST) ){
                if ( is_int($attr["key"]) ){
                    if ( is_array($attr["value"]) ){
                        # add assigns to history
                        foreach ($attr["value"] as $attr_added){
                            history_add("assigned", $attr["key"], $attr_added, $id, "resolve_assignment");
                        }
                        # Reset array pointer !!!
                        reset($attr["value"]);

                        # counter for assign_cust_order
                        $cust_order = 0;
                        $attr_datatype = db_templates("attr_datatype", $attr["key"]);
                        # save assign_one/assign_many/assign_cust_order in ItemLinks
                        while ( $many_attr = each($attr["value"]) ){
                            # if value is empty go to next one
                            if (!$many_attr["value"]){
                                continue;
                            }else{
                                # check link_as_child option
                                $lac_query = mysql_query('SELECT link_as_child
                                                FROM ConfigAttrs
                                                WHERE id_attr = "'.$attr["key"].'"
                                ');

                                $result = mysql_query($lac_query);
                                if ( mysql_result($lac_query, 0) == "yes"){
                                    $query = 'INSERT INTO ItemLinks
                                        (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                                        VALUES
                                        ('.$many_attr["value"].', '.$id.', '.$attr["key"].', '.$cust_order.')
                                        ';
                                }else{
                                    $query = 'INSERT INTO ItemLinks
                                        (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                                        VALUES
                                        ('.$id.', '.$many_attr["value"].', '.$attr["key"].', '.$cust_order.')
                                        ';
                                }    
                                message ($debug, $query, "query");

                                if (DB_NO_WRITES != 1) {
                                    if ( mysql_query($query) ){
                                        message ($debug, '', "ok");
                                        //message ($debug, 'Successfully linked "'.$many_attr["value"].'" with '.$attr["key"]);
                                    }else{
                                        message ($error, 'Error when linking '.$many_attr["value"].' with '.$attr["key"].':'.$query);
                                    }
                                }

                                # increase assign_cust_order if needed
                                if ($attr_datatype == "assign_cust_order") $cust_order++;

                            }
                        }
                    }else{

                        # Lookup datatype
                        # Password field is a encrypted, do not save
                        $query =    'SELECT datatype FROM ConfigAttrs WHERE id_attr = '.$attr["key"];
                        $datatype = db_handler($query, "getOne", "Lookup datatype");
                        if ($datatype == "password"){
                            $insert_attr_value = encrypt_password($attr["value"]);
                        }else{
                            # normal text/select
                            $insert_attr_value = escape_string($attr["value"]);
                        }

                        $query = 'INSERT INTO ConfigValues
                            (attr_value, fk_id_attr, fk_id_item)
                            VALUES
                            ("'.$insert_attr_value.'", "'.$attr["key"].'", '.$id.' )
                            ';
                        message ($debug, $query, "query");

                        if (DB_NO_WRITES != 1) {
                            if ( mysql_query($query) ){
                                message ($debug, 'Added '.$insert_attr_value, "ok");
                                # add value ADDED to history
                                history_add("added", $attr["key"], $insert_attr_value, $id, "get_attr_name");
                            }else{
                                message ($error, 'Error when adding '.$attr["value"], "failed");
                            }
                        }
                    }
                }else{
                    continue;
                }
            }
            if (DB_NO_WRITES != 1) {
                message ($info, '<b>Adding '.$config_class.' was successful</b>');
            }else{
                message ($info, '<b>Adding '.$config_class.' should work fine...</b>');
            }
            if (isset($_SESSION["cache"]["add"])) unset($_SESSION["cache"]["add"]);

            if ($config_class == "host") {
                $_SESSION["created_id"] = $id;
                $step2 = "yes";
            }
            if ($id) { $_SESSION["created_id"] = $id; }

        }else{
            # insert not ok
            message ($error, 'Error in adding entry to ConfigItems:'.$query);
        }
    }
    # end of write2db

}# END Entry exists ?

?>
