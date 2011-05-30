<?php

if ( isset($_POST["add_service"]) ){
    unset($_POST["checkcommands"]);
    $_POST["checkcommands"][$_POST["add_checkcommand"]] = $_POST["HIDDEN_checkcommands"][$_POST["add_checkcommand"]];
}


if (  ( isset($_POST["checkcommands"]) ) AND ( is_array($_POST["checkcommands"]) )  ){

    # each checkcommand
    while ( $checkcommand = each($_POST["checkcommands"]) ){

        // Generate new item_id for service
        $query = 'INSERT INTO ConfigItems (fk_id_class) 
                    VALUES ((SELECT id_class
                                FROM ConfigClasses
                                WHERE config_class="service"))
                 ';

        $insert = db_handler($query, "insert", "Generate new item_id for service");
        if ( $insert ){

            // Get generated ID
            $new_service_ID = mysql_insert_id();

            // Link new service with host        
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                        VALUES ('.$new_service_ID.','.$host_ID.',
                            (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                            WHERE id_class=fk_id_class 
                            AND config_class="service" 
                            AND attr_name="host_name"))
                     ';
            db_handler($query, "insert", "Link new service with host");


            #
            # additional name handling for existing service names
            #

            # get all service names of destination server
            $existing_service_names = db_templates("get_services_from_host_id", $host_ID);

            if ( in_array($checkcommand["value"], $existing_service_names) ){
                $new_service_name = $checkcommand["value"].'_';
                $i = 1;
                do{
                    $i++;
                    $try_service_name = $new_service_name.$i;
                }while( in_array($try_service_name, $existing_service_names) );
                # found a services name, which does not exist
                $new_service_name = $try_service_name;
                // move value back
                $checkcommand["value"] = $new_service_name;
            }

            // Set name of service
            $query = 'INSERT INTO ConfigValues (attr_value,fk_id_item,fk_id_attr) 
                       VALUES ("'.$checkcommand["value"].'",'.$new_service_ID.',
                        (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                            WHERE id_class=fk_id_class 
                            AND config_class="service" 
                            AND naming_attr="yes"))
                     ';
            db_handler($query, "insert", "Set name of service");
            history_add("added", "service", $checkcommand["value"], $host_ID);

            // Link service with checkcommand
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                        VALUES ('.$new_service_ID.','.$checkcommand["key"].',
                            (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                            WHERE id_class=fk_id_class 
                            AND config_class="service" 
                            AND attr_name="check_command"))
                     ';
            db_handler($query, "insert", "Link service with checkcommand");

            // Read default checkcommand params
            $query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses
                                  WHERE id_attr=fk_id_attr
                                  AND attr_name="default_params"
                                  AND id_class=fk_id_class
                                  AND config_class="checkcommand"
                                  AND fk_id_item='.$checkcommand["key"];

            $default_params = db_handler($query, "getOne", "Read default checkcommand params");

            if($default_params == ""){
                $default_params="!";
            }else{
                # escape the string for mysql (field contains: " ' \ etc. )
                $default_params = escape_string($default_params);
            }

            // Set default checkcommand params
            $query = 'INSERT INTO ConfigValues (fk_id_item,attr_value,fk_id_attr)
                       VALUES('.$new_service_ID.',"'.$default_params.'",
                            (SELECT id_attr FROM ConfigAttrs,ConfigClasses                             
                                  WHERE id_class=fk_id_class                             
                                  AND config_class="service"                             
                                  AND attr_name="check_params"))
                     ';

            db_handler($query, "insert", "set default checkcommand params");


            $query = 'SELECT fk_item_linked2 AS item_id,attr_name
                        FROM ItemLinks,ConfigAttrs,ConfigClasses
                        WHERE id_attr=fk_id_attr
                            AND id_class=fk_id_class
                            AND fk_id_item="'.$host_ID.'"
                            HAVING (SELECT config_class FROM ConfigItems,ConfigClasses 
                                    WHERE id_class=fk_id_class 
                                        AND id_item=item_id) = "timeperiod"';

            $result = db_handler($query, "result", "select timeperiods");
            if ($result){
                message ($debug, '[ OK ] --> selected: '.mysql_num_rows($result) );
                if ( mysql_num_rows($result) > 0 ){
#                   $timeperiod_ID = mysql_result($result, 0);
                    while ($timeperiod = mysql_fetch_assoc($result)){

                        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                                    VALUES ('.$new_service_ID.','.$timeperiod["item_id"].',
                                        (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                                        WHERE id_class=fk_id_class 
                                        AND config_class="service"
                                        AND attr_name="'.$timeperiod["attr_name"].'"))';

                        db_handler($query, "insert", "insert timeperiod");
                    }
                }


            }else{
                message ($debug, '[ FAILED ]');
            }    


            // Link service with same contactgroups as host
            $query = 'SELECT fk_item_linked2
                        FROM ItemLinks,ConfigAttrs 
                        WHERE id_attr=fk_id_attr
                        AND attr_name="contact_groups"
                        AND fk_id_item="'.$host_ID.'"
                     ';

            $result = db_handler($query, "result", "Link service with same contactgroups as host (select)");
            if ($result){
                message ($debug, '[ OK ] --> selected: '.mysql_num_rows($result) );
                if ( mysql_num_rows($result) > 0 ){
                    while ($contactgroup_ID = mysql_fetch_row($result)){
                        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                                    VALUES ('.$new_service_ID.','.$contactgroup_ID[0].',
                                        (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                                        WHERE id_class=fk_id_class 
                                        AND config_class="service"
                                        AND attr_name="contact_groups"))
             
                                 ';

                        db_handler($query, "insert", "Link service with same contactgroups as host (insert)");
                    } // END while
                }


            }else{
                message ($debug, '[ FAILED ]');
            }    
        

        }// END if ( $insert ){

    }// END while

} // END is_array($_POST["checkcommands"])

// Display step3
$from_step2 = "no";

?>
