<?php
// Functions

# make info, error or debug text
global $debug;
global $info;
global $error;
global $critical;
global $count;
$count = 0;
function message(&$variable, $text, $mode = "standard"){
    global $count;
    $count++;
    switch($mode){
        case "standard":
            $variable  .= "$text<br>";
            break;
        case "grouptitle":
            $variable  .= "<hr><br><h2><b>".$text."</b></h2><br>";
            break;
        case "list":
            $variable  .= "-&nbsp;$text<br>";
            break;
        case "overwrite":
            $variable  = "$text<br>";
            break;
        /*case "query_swap":
            $variable  .= '<br><img src="img/icon_expand.gif" id="swap_icon_message_'.$count.'" >&nbsp;<a href="javascript:swap_visible(\'message_'.$count.'\')">'.$text[0].'</a><br><ul><li id="message_'.$count.'" style="position: relative; width: 300px; left: 10px; display:none">'.$text[1].'</li></ul>';
            break;*/
        case "query_swap":
            $text[1] = preg_replace('/(SELECT|FROM|WHERE|AND|ORDER BY)/', '<BR><font color="red">${1}</font>', $text[1]);
            $text[1] = "<b>SQL-Query:</b>".$text[1];
            $variable  .= '<div>
                            <a href="javascript:swap_visible(\'message_'.$count.'\')">
                                <img src="img/icon_expand.gif" id="swap_icon_message_'.$count.'" >
                                    &nbsp;'.$text[0].'
                            </a>
                           </div>
                           <div id="message_'.$count.'" class="debbug_query" style="display:none">'
                                .$text[1].
                          '</div>';
            break;
        case "query":
            $variable  .= '<img src="img/icon_expand.gif" id="swap_icon_message_'.$count.'" >&nbsp;<a href="javascript:swap_visible(\'message_'.$count.'\')">show query</a><br><ul><li id="message_'.$count.'" style="position: relative; width: 300px; left: 10px; display:none">'.$text.'</li></ul>';
            break;
        case "query2":
            $variable  .= "$text<br>";
            break;
        case "ok":
            $variable  .= "<font color='green'><b>[ OK ]</b></font>&nbsp;$text<br><br>";
            break;
        case "failed":
            $variable  .= "<font color='red'><b>[ FAILED ]</b></font>&nbsp;$text<br>";
            break;
        case "red":
            $variable  .= "<font color='red'>&nbsp;$text</font><br>";
            break;
        case "nomatch":
            $variable  .= "<font color='orange'><b>[ NO MATCH ]</b></font>&nbsp;$text<br>";
            break;
        default:
            $variable  .= "$text<br>"; 
    }
}


function escape_string($string){
    # Strip slashes if magic_quotes_gpc is ON (DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 6.0.0.)
    # Reverse magic_quotes_gpc/magic_quotes_sybase effects on those vars if ON.
    if (get_magic_quotes_gpc() ){
        message($debug, "magic_quotes_gpc is ON: using stripslashes to correct it");
        $string = stripslashes($string);
    }
    
    # Make a safe string
    $escaped_string = mysql_real_escape_string($string);
    return $escaped_string;
}



function set_page(){
    global $debug;
    // set source page for comeback
    $URL = basename($_SERVER['REQUEST_URI']);
    $URL = str_replace("?clear=1", "", $URL);
    $URL = str_replace("&clear=1", "", $URL);
    $_SESSION["go_back_page"] = $URL;
    message($debug, "set sourcepage for edit: $URL");
    return $URL;
}

function define_colgroup($type = ''){
    if ($type == "multi_modify"){
        return '
        <colgroup>
            <col width="260">
            <col width="10">
            <col width="260">
            <col width="10">
            <col width="230">
        </colgroup>
        ';
    }else{
        return '
        <colgroup>
            <col width="160">
            <col width="260">
            <col width="10">
            <col width="260">
            <col width="10">
            <col width="70">
        </colgroup>
        ';
    }
}

function attr_order($id, $mode){
    // Get old order and class
    $result_assoc = db_handler("SELECT ordering, fk_id_class FROM ConfigAttrs WHERE id_attr=$id", "assoc", "GET order and class of attr");
    $old_order  = $result_assoc["ordering"];
    $attr_class = $result_assoc["fk_id_class"];

    // Select next attr to change with
    // Make query right to the mode up/down
    if ($mode == "up") {
        $query = 'SELECT id_attr, ordering AS dest_order FROM ConfigAttrs WHERE ordering < '.$old_order.' AND fk_id_class='.$attr_class.' ORDER BY ordering DESC LIMIT 1';
    }elseif ($mode == "down"){
        $query = 'SELECT id_attr, ordering AS dest_order FROM ConfigAttrs WHERE ordering > '.$old_order.' AND fk_id_class='.$attr_class.' ORDER BY ordering ASC LIMIT 1';
    }
    $result_assoc = db_handler($query, "assoc", "GET new order (and the attr_id of destination)");
      $dest_attr  = $result_assoc["id_attr"];
      $dest_order = $result_assoc["dest_order"];

    // IF there is an attribute to change position
    if ($result_assoc){
        // change attributes order
        $query = 'UPDATE ConfigAttrs SET ordering='.$old_order.' WHERE ordering = '.$dest_order.' AND fk_id_class='.$attr_class;
        db_handler($query, "insert", "UPDATE: move all attributes with destination ordering");
        $query = 'UPDATE ConfigAttrs SET ordering='.$dest_order.' WHERE id_attr = '.$id.' AND fk_id_class='.$attr_class;
        db_handler($query, "insert", "UPDATE: move selected attribute");

    }

}

function class_order($id, $mode){
    // Get old order and class
    $result_assoc  = db_handler("SELECT ordering, grouping, nav_privs FROM ConfigClasses WHERE id_class=$id", "assoc", "GET order and grouping of class");
      $old_order  = $result_assoc["ordering"];
      $group  = $result_assoc["grouping"];
      $nav_priv  = $result_assoc["nav_privs"];

    // Select next class to change with
    // Make query right to the mode up/down
    if ($mode == "up") {
        $query = 'SELECT id_class, ordering AS dest_order FROM ConfigClasses WHERE ordering < '.$old_order.' AND grouping="'.$group.'" AND nav_privs="'.$nav_priv.'" ORDER BY ordering DESC LIMIT 1';
    }elseif ($mode == "down"){
        $query = 'SELECT id_class, ordering AS dest_order FROM ConfigClasses WHERE ordering > '.$old_order.' AND grouping="'.$group.'"AND nav_privs="'.$nav_priv.'" ORDER BY ordering ASC LIMIT 1';
    }
    $result_assoc = db_handler($query, "assoc", "GET new order (and the id_class of destination)");
      $dest_id  = $result_assoc["id_class"];
      $dest_order = $result_assoc["dest_order"];

    // IF there is an class to change position
    if ($result_assoc){
        // change class order
        $query = 'UPDATE ConfigClasses SET ordering='.$old_order.' WHERE id_class='.$dest_id;
        db_handler($query, "insert", "UPDATE: move other class with destination ordering");
        $query = 'UPDATE ConfigClasses SET ordering='.$dest_order.' WHERE id_class = '.$id;
        db_handler($query, "insert", "UPDATE: move selected class");

    }

}


# Mandatory fields
# write to db only if mandatory fields are ok!
function check_mandatory($mandatory, &$values2check){
    global $debug;
    global $error;
    global $info;
    $write2db = "yes";

    foreach ($mandatory AS $var_name => $friendly_name){
        # Get the value which should be checked
        if ( !isset($values2check[$var_name]) ){
            # no value/array found
            $check_value = '';
            # do nothin here
        }elseif ( is_array($values2check[$var_name]) ){
            $check_value = $values2check[$var_name][0];
        }else{
            $check_value = $values2check[$var_name];
        }

        # Check 
        if ( ( isset($check_value) ) AND ( $check_value != "") ){
            message($debug, "$friendly_name: ok!");
        }else{
            message($error, "$friendly_name: mandatory field !");
            //message($info, TXT_GO_BACK_BUTTON, "overwrite");
            $write2db = 'no';
        }
    }
    return $write2db;
}


# History insert entry
#   action = created, added, assigned, unassigned, modified, removed
#   name = "name" of object
#   value = "value" of object
#   fk_id_item = OPTIONAL
#   user = "user", will be taken from SESSION, otherwise is unknown(but should not be)
function history_add($action, $name, $value, $fk_id_item = 'NULL', $feature = ''){
    # User handling
    if ( !empty($_SESSION["userinfos"]["username"]) ){
        $user = $_SESSION["userinfos"]["username"];
    }else{
        $user = "unknown";
    }
    # Feature's
    switch($feature){
        # Resolve assignment looks up the real value behind the id(foreign keys)
        # It doesn't matter if there is only one ore multiple id's
        case "resolve_assignment":
            #if comma seperated, make array
            if ( is_array($value) ){
                $ids = $value;
            }else{
                $ids = explode(",", $value);
            }

            # get entries
            $value_array = array();
            foreach ($ids as $id){
                $value_array[] = db_templates("naming_attr", $id);
            }

            # make string for history entry
            $value = implode(",", $value_array);
        break;

    }

    # Do not write password attributes plaintext in history
    if (is_numeric($name) ){
        $attr_datatype = db_templates("attr_datatype", $name);
        if ($attr_datatype == "password"){
            # Overwrite the password
            $value = PASSWD_HIDDEN_STRING;
        }
    }

    # if name is integer, look for attr name
    if (is_numeric($name) ){
        $name = db_templates("friendly_attr_name", $name);
    }

    # Insert into History
    $query = "INSERT INTO `History` (user_str, action, attr_name, attr_value, fk_id_item) VALUES ( '$user', '$action', '$name', '$value', $fk_id_item)";

    db_handler($query, 'affected', 'Add to History');
}



# reloads the db connection and selects the db
# (must be user after auth by sql)
function relaod_nconf_db_connection(){
    $dbh = mysql_connect(DBHOST,DBUSER,DBPASS);
    mysql_select_db(DBNAME);
}


# NEW DB HANDLER templates
function db_templates($template, $value = '', $search = ''){
    $value  = escape_string($value);
    $search = escape_string($search);
    /*
    if ( empty($value) ){
        message ($debug, "no value for lookup in db_template");
        return;    
    }
    */

    switch($template){
        case "naming_attr":     # : is old get_value(attr_name)
            $query = 'SELECT attr_value
                        FROM ConfigValues, ConfigAttrs
                        WHERE fk_id_attr=id_attr
                        AND naming_attr="yes"
                        AND fk_id_item="'.$value.'"';
            $output = db_handler($query, 'getOne', "select naming_attr name");
            break;
        case "get_naming_attr_from_class":
            $query = 'SELECT id_attr
                        FROM ConfigAttrs,ConfigClasses
                        WHERE naming_attr="yes"
                            AND id_class=fk_id_class
                            AND config_class="'.$value.'"';
            $output = db_handler($query, "getOne", "naming_attr ID of $value:");
            break;
        case "get_id_of_item":
            $query = 'SELECT fk_id_item
                        FROM ConfigValues, ConfigAttrs
                        WHERE fk_id_attr = id_attr
                            AND id_attr = "'.$value.'"
                            AND attr_value = "'.$search.'"';
            $output = db_handler($query, "getOne", "item ID of $search:");
            break;
        case "get_id_of_hostname_service":
            $query = 'SELECT id_item,attr_value AS servicename,
                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                            AND id_attr=ConfigValues.fk_id_attr
                            AND naming_attr="yes"
                            AND fk_id_class = id_class
                            AND config_class="host"
                            AND ItemLinks.fk_id_item=id_item) AS hostname
                    FROM ConfigItems,ConfigValues,ConfigAttrs
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND id_attr="'.$value.'"
                    HAVING CONCAT(hostname,":",servicename) LIKE "'.$search.'"';
            $output = db_handler($query, "getOne", "item ID of $search:");
            break;
        case "attr_name":
            $query = 'SELECT attr_name
                        FROM ConfigAttrs
                        WHERE id_attr="'.$value.'"';
            $output = db_handler($query, 'getOne', "select attr name");
            break;
        case "attr_datatype":
            $query = 'SELECT datatype FROM `ConfigAttrs`
                        WHERE id_attr = "'.$value.'"';
            $output = db_handler($query, "getOne", "Lookup only datatype (no old value found)");
            break;
        case "friendly_attr_name":
            $query = 'SELECT friendly_name
                        FROM ConfigAttrs
                        WHERE id_attr="'.$value.'"';
            $output = db_handler($query, 'getOne', "select attr name");
            break;
        case "class_name":
            $query = 'SELECT config_class
                        FROM ConfigClasses,ConfigItems
                        WHERE id_class = fk_id_class
                        AND id_item = "'.$value.'"';
            $output = db_handler($query, 'getOne', "select class name");
            break;
        case "get_value":
            $query = 'SELECT attr_value
                        FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                        AND id_item=fk_id_item
                        AND ConfigAttrs.visible="yes" 
                        AND id_item="'.$value.'"
                        AND attr_name = "'.$search.'"
                        ORDER BY ConfigAttrs.ordering';

            $output = db_handler($query, "getOne", "get $search of item with id $value");
            break;

        case "get_linked_item":
            $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_item_linked2 = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND ItemLinks.fk_id_item ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            $output = db_handler($query, "array", "get linked $search of item with id $value");
            break;
        # with link as child attrs: (contacts of contactgroup)
        case "get_linked_item_2":
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE ItemLinks.fk_id_item = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND fk_item_linked2 ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            $output = db_handler($query, "array", "get linked $search of item with id $value");
            break;

/*
something for later use... other query for link_as_child attrs....
        case "get_linked_item":
            if ($link_as_child == "yes"){
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE ItemLinks.fk_id_item = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND fk_item_linked2 ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            }else{
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_item_linked2 = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND ItemLinks.fk_id_item ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            }
            $output = db_handler($query, "array", "get linked $search of item with id $value");
            break;
*/
        case "get_id_from_hostname":
            $query = 'SELECT fk_id_item'
                . ' FROM ConfigValues, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_id_attr=id_attr'
                . ' AND attr_value="'.$value.'"'
                . ' AND id_class=fk_id_class'
                . ' AND config_class="host"';
            $output = db_handler($query, 'getOne', "get_id_from_hostname");
            break;
        case "get_attr_id":
            $query = 'SELECT id_attr FROM ConfigAttrs,ConfigClasses
                        WHERE attr_name="'.$search.'"
                        AND id_class=fk_id_class
                        AND config_class="'.$value.'"';
            $output = db_handler($query, 'getOne', "Get attr_id where attr_name = $search and class = $value");
            break;
        case "host_attr_id":
            $query = 'SELECT id_attr FROM ConfigAttrs,ConfigClasses
                        WHERE attr_name="'.$value.'"
                        AND id_class=fk_id_class
                        AND config_class="host"';
            $output = db_handler($query, 'getOne', "select attr id");
            break;
        case "get_attributes_from_class":
            $query = 'SELECT ConfigAttrs.friendly_name, ConfigAttrs.ordering, id_attr, attr_name, datatype, mandatory, naming_attr
                    FROM ConfigAttrs,ConfigClasses
                        WHERE id_class=fk_id_class
                        AND config_class="'.$value.'"
                        AND ConfigAttrs.visible = "yes"
                        ORDER BY ConfigAttrs.ordering';
            $output = db_handler($query, 'array', "select all attributes of a class");
            break;
        case "hostID_of_service":
            $query = 'SELECT fk_item_linked2
                        FROM ItemLinks, ConfigItems, ConfigClasses
                        WHERE id_item = fk_item_linked2
                        AND fk_id_class = id_class
                        AND config_class = "host"
                        AND fk_id_item = "'.$value.'"';
            $output = db_handler($query, 'getOne', "select host_ID of service");
            break;

        case "get_services_from_host_id":
            $query = 'SELECT ConfigValues.fk_id_item AS id, attr_value AS name
                FROM ConfigValues, ConfigAttrs, ItemLinks, ConfigClasses
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = "'.$value.'"
                AND fk_id_class = id_class
                AND config_class = "service"
                ORDER BY attr_value';
            $output = db_handler($query, 'array_2fieldsTOassoc', "Get services from host");
            break;


        case "servicegroup_id":
            $query = 'SELECT id_attr
                    FROM ConfigAttrs,ConfigClasses
                    WHERE attr_name = "members"
                    AND id_class=fk_id_class
                    AND config_class = "servicegroup"';
            $output = db_handler($query, 'getOne', "Get servicegroup id");
            break;


        case "mandatory":
            $query = 'SELECT  ConfigAttrs.id_attr, ConfigAttrs.friendly_name
                    FROM ConfigAttrs,ConfigClasses
                    WHERE id_class=fk_id_class
                    AND ConfigClasses.config_class="'.$value.'"
                    AND ConfigAttrs.visible="yes"
                    AND ConfigAttrs.mandatory="yes" ';
            # multi modify only needs feedback about one(the selected attribute)
            if ( !empty($search) ) $query .= 'AND ConfigAttrs.id_attr = "'.$search.'"';
            $query .= 'ORDER BY ConfigAttrs.id_attr';

            $output = db_handler($query, 'array_2fieldsTOassoc', "get mandatory fields");
            break;


        case "vererben":
            $query = 'SELECT fk_item_linked2 AS item_id,attr_name
                FROM ItemLinks,ConfigAttrs,ConfigClasses
                WHERE id_attr=fk_id_attr
                AND id_class=fk_id_class
                AND fk_id_item="'.$value.'"
                HAVING ((SELECT config_class FROM ConfigItems,ConfigClasses
                        WHERE id_class=fk_id_class
                            AND id_item=item_id) = "timeperiod"
                    OR (SELECT config_class FROM ConfigItems,ConfigClasses
                        WHERE id_class=fk_id_class
                            AND id_item=item_id) = "contactgroup")
                    ORDER BY item_id
                    ';
            $output = db_handler($query, 'result', "vererben");
            break;


        case "linked_as_child":
            # get entries linked as child
            $query = 'SELECT DISTINCT attr_value,ItemLinks.fk_id_item AS item_id,
                          (SELECT config_class FROM ConfigItems,ConfigClasses 
                              WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
                          (SELECT ConfigAttrs.friendly_name
                              FROM ConfigValues,ConfigAttrs
                              WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                                  AND id_attr=fk_id_attr
                                  AND naming_attr="yes") AS friendly_name
                        FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                        WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                            AND id_attr=ItemLinks.fk_id_attr
                            AND ConfigAttrs.visible="yes"
                            AND fk_id_class=id_class
                            AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                            AND ItemLinks.fk_item_linked2="'.$value.'"
                        ORDER BY friendly_name DESC,attr_value';
            $output = db_handler($query, 'result', "get entries linked as child");
            break;






    }

    return $output;


}



# NEW DB HANDLER
function db_handler($query, $output = "result", $debug_title = "query"){
    global $debug;

    # Remove beginning spaces
    $query = trim($query);

    if ( (DB_NO_WRITES == 1) AND ( !preg_match("/^SELECT/i", $query) ) ){
        message ($info, "DB_NO_WRITES activated, no deletions or modifications will be performed!");
    }else{
        $result = mysql_query($query);
        # Debug message:
        $text_array = array($debug_title, $query);
        message($debug, $text_array, "query_swap");

        if ( $result ){
            # Output related stuff

            # not already implemented, or replaced functions:
            //if ($output == "getOne") $output = "1st_field_data";

            switch($output){

                case "affected":
                case "insert":
                case "update":
                case "delete":
                    $affected = mysql_affected_rows();
                    if ( $affected > 0 ){
                        message($debug, "# affected rows: $affected", "ok");
                        return $affected;
                    }else{
                        // needed for inserts ??:
                        message($debug, "# affected rows: $affected", "nomatch");
                        return "yes";
                    }

                    break;
                case "getOne":
                    $first_row = mysql_fetch_row($result);
                    message($debug, '# getOne: '.$first_row[0], "ok");
                    return $first_row[0];
                    break;
                
                case "result":
                    message($debug, "# result: ".$result);
                    return $result;
                    break;
                
                case "insert_id":
                    $new_id = mysql_insert_id();
                    message($debug, "# inserted entries ID: ".$new_id);
                    return $new_id;
                    break;

                case "num_rows":
                    $result = mysql_num_rows($result);
                    message($debug, "# num rows: ".$result);
                    return $result;
                    break;
            
                case "assoc":
                    $result = mysql_fetch_assoc($result);
                    message($debug, "# array: ".$result);
                    return $result;
                    break;
            
                case "array":
                    $i = 0;
                    $rows = array();
                    while ($row  = mysql_fetch_assoc($result) ){
                        $rows[$i] = $row;
                        $i++;
                    }
                    $count = count($rows);
                    message($debug, "# Array, rows:".$count, "ok");
                    return $rows;
                    break;

                case "array_direct":
                    $i = 0;
                    $rows = array();
                    while ($row  = mysql_fetch_row($result) ){
                        $rows[$i] = $row[0];
                        $i++;
                    }
                    $count = count($rows);
                    message($debug, "# Array, rows:".$count, "ok");
                    return $rows;
                    break;

                case "array_2fieldsTOassoc":
                    $rows = array();
                    while ($row  = mysql_fetch_row($result) ){
                        $rows[$row[0]] = $row[1];
                    }
                    $count = count($rows);
                    message($debug, "# Array, rows:".$count, "ok");
                    return $rows;
                    break;
                # Failed on output case
                default:
                    message($error, "db_handler failed on output case");
                    return FALSE;
                
            }

        }else{
            message($debug, mysql_error(), "failed");
        }

    }
}

# checks if a constant is set or a variable matches a specific type (array etc)
# the type can be "constant" or any php function, which checks the var type (is_array, is_numeric, etc...)
# $allow_empty_value will accept empty vars, if you do not want to allow empty vars, call the function with FALSE
function check_var($type, $vars, $allow_empty_value = TRUE){
    global $critical;
    global $info;
    $failed = FALSE;

    if ( !is_array($vars) ){
        $vars = array($vars);
    }
    foreach ($vars as $var){
    global $$var;
        if ($type == "constant"){
            if ( !defined($var) ){
                message($critical, 'The "'.$var.'" constant is not defined. <br>Check your configuration files.<br>');
                $failed = TRUE;
            }else{
                if (!$allow_empty_value){
                    # check if constant is empty
                    if ( constant($var) === ''){
                         message($critical, 'The "'.$var.'" constant is empty. <br>Check your configuration files.<br>');
                        $failed = TRUE;
                    }
                }
            }
        }else{
            if ( function_exists($type) ){
                # calls the function for checking the var, like is_array, is_string, is_int etc.
                if ( !call_user_func($type, $$var) ){
                    message($critical, '"'.$type.'" check failed on variable "$'.$var.'". <br>
                            Check your configuration files and make sure it is defined.<br>');
                    $failed = TRUE;
                }else{
                    # check if var is empty
                    if (!$allow_empty_value){
                        if ( empty($$var) ){
                            message($critical, 'The "$'.$var.'" variable is empty. <br>Check your configuration files.<br>');
                            $failed = TRUE;
                        }
                    }
                }
            }
        }
        
    }

    # return
    if ($failed == TRUE){
        return FALSE;
    }else{
        return TRUE;
    }

}


##########################################################################################
##################   Special Tools and features   ########################################
##########################################################################################

//js_Autocomplete_run('serverlist', 'cmdbserverlist'){
function js_Autocomplete_run($id, $js_array_name){
    //Only run if feature is activated
    if ( defined('AUTO_COMPLETE') AND AUTO_COMPLETE == 1){
        $content = 'AutoComplete_Create(\''.$id.'\', '.$js_array_name.');';

        $js_code = js_prepare($content);
        echo $js_code;
    }
}


// js_Autocomplete_prepare('cmdbserverlist', $_SESSION["cmdb_serverlist"])
function js_Autocomplete_prepare($js_array_name, $php_array){
    //Only run if feature is activated
    if ( defined('AUTO_COMPLETE') AND AUTO_COMPLETE == 1){

        global $debug;

        $comma_i    = 0;
        $temp       = "";
        foreach ($php_array as $key => $wert){
            // Dont put a comma in the FIRST run...
            if ($comma_i > 0){
                $temp .= ",";
            }
            $temp .= "'$wert'";
            $comma_i++;
        }

        if ($temp){
            $content  = "$js_array_name = [";
            $content .= $temp;
            $content .= "].sort();";

            $js_code = js_prepare($content);
            
            echo $js_code;
            message($debug, "js_Autocomplete_prepare", "ok");
            return 1;
        }else{
            message($debug, "js_Autocomplete_prepare", "failed");
            return 0;
        }
    }
}


function js_prepare($content){
    // create js code
    $beginn =   "<script type=\"text/javascript\">\n";
    $beginn .=  "<!--\n";

    $end    =   "//-->\n";
    $end    .=  "</script>\n";

    $js_code =  $beginn.$content.$end;
    return $js_code;

}

# compare_hostname($hostname, $_SESSION["cmdb_serverlist"]);
function compare_hostname($hostname, $array){
    if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND (is_array($array)) ){
        if (COMPARE_IGNORE){
            // Change the hostname befor compare with cmdblist
            $hostname = preg_replace('/\.phs$/', '', $hostname);
            $hostname = preg_replace('/\.2nd$/', '', $hostname);

            // a second search has to be done without the numbers of sites
            $hostname_2 = preg_replace('/-be8-/', '-be-', $hostname);
            $hostname_2 = preg_replace('/-la1-/', '-la-', $hostname_2);
            $hostname_2 = preg_replace('/-zu1-/', '-zu-', $hostname_2);
        }

        // compare status
        if ( in_array($hostname,$array) OR ( isset($hostname_2) AND in_array($hostname_2,$array) )  ){
            // in array
            $compare_status = 1;
        }else{
            // not in array
            $compare_status = 2;
        }
            
        
    }else{
        // Compare feature not activated
        $compare_status = 0;
    }

    return $compare_status;

}


function show_password($password){

    if ( PASSWD_DISPLAY == 1 ){
        //do nothing
    }else{
        // convert password to *******
        $password = PASSWD_HIDDEN_STRING;
    }

    return $password;

}


function encrypt_password($password, $EncryptInfoInOutput = TRUE){

    switch (PASSWD_ENC){
    case "clear":
        # do nothing
        $encryption_Info = '';
        break;

    case "crypt":
        $password        = crypt($password, CRYPT_SALT);
        $encryption_Info = "{CRYPT}";
        break;

    case "md5":
        $password        = md5($password);
        $encryption_Info = "{MD5}";
        break;

    case "sha":
        $password        = sha1($password);
        $encryption_Info = "{SHA1}";
        break;

    }

    if ($EncryptInfoInOutput){
        $password = $encryption_Info.$password;
    }

return $password;

}



function create_menu($result){
  if ($result){
    echo '<table border=0 width=188>';
    echo '<colgroup>
            <col width="65">
            <col width="55">
            <col width="68">
          </colgroup>';

    // Generate Menu
    $group_bevore = "";
    $block_i = 0;
    foreach ($result as $nav_class){
        if ($nav_class["grouping"] != $group_bevore){

            echo '</table>
                </div>';

            // New Block for Group
            echo '<h2 class="header"><span>'.$nav_class["grouping"].'</span></h2>';
            echo '<div class="box_content">';
            echo '<table border=0 width=188>';
            echo '<colgroup>
                    <col width="55">
                    <col width="65">
                    <col width="68">
                  </colgroup>';
            echo "<tr><td></td><td></td><td></td></tr>";
        }
        $group_bevore = $nav_class["grouping"];

        // prepare links
        $nav_links = explode(";;", $nav_class["nav_links"]);
        $link_i = 0;
        $link_output = "";
        foreach ($nav_links as $entry){
            $link_i++;
            if ($link_i != "1"){
                $link_output .= ' / ';
            }

            $nav_link_details = explode("::", $entry);
            if ( isset($nav_link_details[1]) ){
                $link_output .= '<a href="'.$nav_link_details[1].'" >'.$nav_link_details[0].'</a>';
            }
        }


        // filled or empty "friendly_name" will choose the print style of the link
        if($nav_class["friendly_name"] == ""){
            // empty/without friendly_name style
            echo '<tr><td colspan=3><div class="link_with_tag">'.$link_output.'</div></td></tr>';
        }else{
            // filled friendly name makes other style  (< 10 characters should work fine)
            if ( strlen($nav_class["friendly_name"]) < 10){
                $td1_colspan = 1;
                $td2_colspan = 2;
            }else{
                $td1_colspan = 2;
                $td2_colspan = 1;
            }

            echo '<tr>
                <td colspan="'.$td1_colspan.'" style="vertical-align:top">
                    <div class="link_with_tag2"><b>'.$nav_class["friendly_name"].'</b>
                    </div>
                </td>
                <td colspan="'.$td2_colspan.'" align="right">
                    <div align="right"><b>'.$link_output.'</b></div>
                </td>
              </tr>';
        
        }
    }
    //END foreach

    // Last Block has to be closed :
    echo '</table>';

  }

}




function oncall_check() {
    # make message vars available
    global $debug;
    global $error;
    global $info;
    global $ONCALL_GROUPS;
    global $_POST;
    global $config_class;

    # get id of contact_group attr
    $contact_group_id = db_templates("get_attr_id", $config_class, "contact_groups");

    # also check if a must have contact group is selected (at least one entry : [0])
    if ( !empty($ONCALL_GROUPS[0]) ){
        $oncall_group_ids = array();
        foreach ($ONCALL_GROUPS as $oncall_group){
            $oncall_group_query = 'SELECT fk_id_item FROM ConfigValues, ConfigAttrs, ConfigClasses WHERE ConfigValues.fk_id_attr = ConfigAttrs.id_attr AND ConfigAttrs.fk_id_class = ConfigClasses.id_class AND ConfigClasses.config_class = "contactgroup" AND ConfigAttrs.attr_name = "contactgroup_name" AND ConfigValues.attr_value LIKE "'.$oncall_group.'"';
            # add id to array
            $oncall_id = db_handler($oncall_group_query, "getOne", "Select id of must have contact_group (oncall)");
            if ( !empty($oncall_id) ){
                $oncall_group_ids[] = $oncall_id;
            }else{
                message($info, "Could not find ONCALL GROUP: $oncall_group");
            }
        }

        if ( !empty($oncall_group_ids[0]) ){
            # a defined oncall group was found, check it
            $oncall_check = FALSE;
            foreach ($oncall_group_ids as $oncall_group_id){
                if(!empty($_POST[$contact_group_id]) AND in_array($oncall_group_id, $_POST[$contact_group_id]) ){
                    # a must have contact group was selected, mark check als OK / TRUE
                    $oncall_check = TRUE;
                }else{
                    # a must have contact group was NOT selected
                }
            }

        }else{
            # defined oncall groups (in config) not found in database, make no restrictions for group_contacts
            message($error, "Defined ONCALL GROUPs (in config) not found in database");
            $oncall_check = FALSE;
        }


        # give feedback
        if($oncall_check == TRUE){
            # a must have contact group was selected, go ahead
            message($debug, "ONCALL group selected");
            return TRUE;
        }else{
            # a must have contact group was NOT selected, stop and give info
            message($error, "Must have at least one ONCALL GROUP!");
            foreach ($ONCALL_GROUPS as $oncall_group_id){
                message($error, $oncall_group_id, "list");
            }
            message($info, TXT_GO_BACK_BUTTON, "overwrite");
            return FALSE;
        }


    }else{
        # no must have selected contact group, go ahead
        message($debug, "No must have ONCALL GROUPS defined");
        return TRUE;
    }



}


# get directories
function getDirectoryTree( $outerDir ){
    $dir_array = Array();
    if(file_exists($outerDir) ){
        $dirs = array_diff( scandir( $outerDir ), Array( ".", "..", ".svn" ) );
        foreach( $dirs as $d ){
            if( is_dir($outerDir."/".$d) ) $dir_array[$d] = $d;
        }
    }
    return $dir_array;
}

# get files
function getFiles( $outerDir ){
    if ( is_dir($outerDir) ){
        $files = array_diff( scandir( $outerDir ), Array( ".", ".." ) );
        $file_array = Array();
        foreach( $files as $f ){
            if( is_file($outerDir."/".$f) ) $file_array[$f] = $f;
        }
        return $file_array;
    }else{
        return FALSE;
    }
}

# check if folder is empty
function is_empty_folder($folder){
    $c=0;
    if(is_dir($folder) ){
        $files = opendir($folder);

        if($files == false){
            return "error";
        }

        while ($file=readdir($files)){$c++;}
        if ($c>2){
            return false;
        }else{
            return true;
        }
    }else{
        return "error";
    } 
}

##########################################################################################
##################   TREE VIEW features   ################################################
##########################################################################################

###
# FUNCTIONS for TREE VIEW
# html code starts @ Row #330#
###
$all_childs = array();
function get_childs($id, $mode, $levels = 0){
    global $all_childs;
    $all_childs[ $id ] = TRUE;

    $childs = array();
    $services = array();
    $child_id = 0;

    # get entries linked as child
    $query = 'SELECT DISTINCT attr_value,ItemLinks.fk_id_item AS item_id,
                  (SELECT config_class FROM ConfigItems,ConfigClasses
                      WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
                  (SELECT attr_value
                    FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses
                    WHERE ConfigValues.fk_id_item = ItemLinks.fk_item_linked2
                    AND id_attr = ConfigValues.fk_id_attr
                    AND attr_name = "icon_image"
                    AND id_class = fk_id_class
                    AND config_class = "os"
                    AND ItemLinks.fk_id_item = item_id
                  ) AS os_icon
                FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                    AND id_attr=ItemLinks.fk_id_attr
                    AND ConfigAttrs.visible="yes"
                    AND fk_id_class=id_class
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                    AND ItemLinks.fk_item_linked2="'.$id.'"
                ORDER BY config_class DESC,attr_value';
    $result = db_handler($query, 'result', "get childs from $id");


    while($entry = mysql_fetch_assoc($result)){
        /*
        #special for services
        if($entry["config_class"] == "service"){
            $host_query = 'SELECT attr_value AS hostname FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                           WHERE fk_item_linked2=ConfigValues.fk_id_item
                                               AND id_attr=ConfigValues.fk_id_attr
                                               AND naming_attr="yes"
                                               AND fk_id_class = id_class
                                               AND config_class="host"
                                               AND ItemLinks.fk_id_item='.$entry["item_id"];

            $hostname = db_handler($host_query, "getOne", "Get linked hostnames");
        }
        */

        # set child
        //var_dump($entry);
        if ($entry["config_class"] == "service"){
            if (!isset($childs["services"]["id"]) ){
                $service_tree["services"] = array(
                            "id" => "service_$id",
                            "status" => 'closed',
                            "name" => "Services",
                            "type" => "service");
                $childs = $service_tree;
            }
            $childs["services"]["childs"][$child_id]["parent"]   = $id;
            $childs["services"]["childs"][$child_id]["id"]       = $entry["item_id"];
            $childs["services"]["childs"][$child_id]["name"]     = $entry["attr_value"];
            $childs["services"]["childs"][$child_id]["type"]     = $entry["config_class"];
            # Nagiosview link
            if ($mode == "nagiosview"){
                $link = generate_nagios_service_link($id, $entry["attr_value"]);
                $childs["services"]["childs"][$child_id]["link"] = $link;
            }

        }elseif ($entry["config_class"] == "host"){
            $childs[$child_id]["parent"]   = $id;
            $childs[$child_id]["id"]       = $entry["item_id"];
            $childs[$child_id]["name"]     = $entry["attr_value"];
            $childs[$child_id]["type"]     = $entry["config_class"];
            $childs[$child_id]["os_icon"]  = $entry["os_icon"];
            # Nagiosview link
            if ($mode == "nagiosview"){
                $link = generate_nagios_pnp_link($id);
//                $link = generate_nagios_pnp_link($id, $entry["attr_value"]);
                $childs[$child_id]["link"] = $link;
            }


            # check if that child is called a second time (prevent a endless loop)
            if ( !isset($all_childs[ $entry["item_id"] ]) ){
                # save the child and parent combinatino, so that a loop can be prevented
                //$all_childs[ $childs[$child_id]["parent"].":".$childs[$child_id]["id"] ] = TRUE;
                //$all_childs[ $entry["item_id"] ] = TRUE;

                # get childs
                $childs[$child_id]["childs"]   = get_childs($entry["item_id"], $mode, ($levels+1) );

                # get informations about host
                # prepend the Host information to the beginning of the array
                array_unshift($childs[$child_id]["childs"], get_informations($entry["item_id"]) );

                # remove from loop detection
                unset($all_childs[ $entry["item_id"] ]);
            }else{
                # re-iteration
                $childs[$child_id]["childs"] = error_loop($entry["item_id"]);
                $childs[$child_id]["status"] = "open";

            }

        }
        # increase child id
        $child_id++;

    }

    # return child information
    return $childs;

}

function generate_nagios_service_link ($host_id, $servicename){
    # get hostname
    $hostname = db_templates("get_value", $host_id, "host_name");
    if (!empty($_GET["service_link"]) ){
        $link = $_GET["service_link"].'?type=2&host='.$hostname.'&service='.$servicename;
        return $link;
    }else{
        return FALSE;
    }
}

function generate_nagios_pnp_link ($host_id){
    # get hostname
    $hostname = db_templates("get_value", $host_id, "host_name");
    if (!empty($_GET["pnp_link"]) ){
        $link = $_GET["pnp_link"].'?host='.$hostname;
        return $link;
    }else{
        return FALSE;
    }
}

function get_informations ($id){
    # get ip address
    $ipaddress = db_templates("get_value", $id, "address");
    $informations[] = array(
                "name" => $ipaddress,
                "title" => "IP: ",
                "type" => "ipaddress") ;

    # hostgroups
    $hostgroups = db_templates("get_linked_item", $id, "members");
    foreach ($hostgroups as $hostgroup){
        $informations[] = array(
                "name" => $hostgroup["attr_value"],
                "title" => "Hostgroup: ",
                "type" => "hostgroup") ;
    }

    # PNP link
    if (!empty($_GET["xmode"]) ){
        $link = generate_nagios_pnp_link($id);
        if ($link){
            $informations[] = array(
                "name" => 'PNP link',
                "link" => $link,
                "type" => "link") ;
        }
    }

    # add it to info array
    $info = array(
        "id" => "info_$id",
        "status" => 'open',
        "name" => "Host info",
        "type" => "info",
        "childs" => $informations);


    return $info;

}


function error_loop ($id){
    # show_reiteration (endless loop)
    $loop[] = array(
        "id" => "loop_$id",
        "name" => TXT_DEPVIEW_ERROR_LOOP,
        "type" => "warn");

    return $loop;

}




###
# This function generates an array of parents, saved for each level of tree 0,1,2,3...
###
$all_parents = array();
function get_parents($id, &$flat = array(), $levels = 0){
    # $all_parents holds the taken parents, so the function will stop getting more parents if one already was fetched
    # (otherwise it will be an endless loop)
    global $all_parents;
    $all_parents[$id] = TRUE;


    $parent_id = 0;

    # get parents
    $sql = 'SELECT ConfigAttrs.friendly_name,attr_value,fk_item_linked2 AS item_id,
                (SELECT config_class FROM ConfigItems,ConfigClasses WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
(SELECT attr_value
                    FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses
                    WHERE ConfigValues.fk_id_item = ItemLinks.fk_item_linked2
                    AND id_attr = ConfigValues.fk_id_attr
                    AND attr_name = "icon_image"
                    AND id_class = fk_id_class
                    AND config_class = "os"
                    AND ItemLinks.fk_id_item = item_id
                  ) AS os_icon
            FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
            WHERE fk_item_linked2=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND fk_id_class=id_class
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr) ="yes"
                AND ItemLinks.fk_id_item='.$id.'
                AND ConfigAttrs.attr_name = "parents"
            ORDER BY ConfigAttrs.friendly_name DESC,attr_value';

    $result = db_handler($sql, "result", "Recursive get parents");
    while($entry = mysql_fetch_assoc($result)){

        #special for services
        /*
        if($entry["config_class"] == "service"){
            $host_query = 'SELECT attr_value AS hostname FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                           WHERE fk_item_linked2=ConfigValues.fk_id_item
                                               AND id_attr=ConfigValues.fk_id_attr
                                               AND naming_attr="yes"
                                               AND fk_id_class = id_class
                                               AND config_class="host"
                                               AND ItemLinks.fk_id_item='.$entry["item_id"];

            $hostname = db_handler($host_query, "getOne", "Get linked hostnames");
        }*/

        # set parent
        $flat[$levels][$parent_id]["name"]        = $entry["attr_value"];
        $flat[$levels][$parent_id]["id"]          = $entry["item_id"];
        $flat[$levels][$parent_id]["child"]       = $id;
        $flat[$levels][$parent_id]["os_icon"]     = $entry["os_icon"];
        $flat[$levels][$parent_id]["type"]        = $entry["config_class"];

        # check if that parent is called a second time (prevent a endless loop)
        if ( !isset($all_parents[$entry["item_id"]]) ){
            # go get all all parents recursive
            get_parents($entry["item_id"], $flat, ($levels+1) );
        }else{
            # parent loop
            $parent_loop = TRUE;
            $flat[$levels][$parent_id]["status"] = "loop_error";
        }

        # increase parent
        $parent_id++;

    }

    # return parent information
    return ($flat);

}






###
# prepare_dependency converts the levels from the flat array into parent groups
###
function prepare_dependency($source, &$root_item, $level = 0){
    # root_item has the selected items information and its childs

    # this runs level array
    $p_array = $source[$level];

    # next level
    $next_level = $level + 1;

    if (!empty($source[$next_level]) AND is_array($source[$next_level]) ){
        # if there is a next level, so go to it
        $result = prepare_dependency($source, $root_item, $next_level);
        # make a subgroup and pack the returning infos into it
        $p_array["group"]["id"]      = $level;
        $p_array["group"]["name"]    = "Parent level";
        $p_array["group"]["status"]  = 'open';
        $p_array["group"]["type"]    = 'parent';
        $p_array["group"]["childs"]  = $result;
    }elseif( !empty($p_array[0]["child"]) AND !empty($root_item[$p_array[0]["child"]]) ){
        # reached last level, the child id exists also in root_item (with its informations)

        # When there are more than one parents, put the child in a subgroup
        if ( count($p_array) > 1 ){
            $p_array["group"]["id"]      = $level;
            $p_array["group"]["name"]    = "Parent level";
            $p_array["group"]["status"]  = 'open';
            $p_array["group"]["type"]    = 'parent';
            $p_array["group"]["childs"][]= $root_item[$p_array[0]["child"]];
        }elseif (count($p_array) == 1 ){
            # there is only one parent, so give the child directly to the parent
            $p_array[0]["childs"][] = $root_item[$p_array[0]["child"]];
        }
    }
    return $p_array;
}



# unique counter is needed, because in parents tree could be more of the same object-id
function displayTree_list($arr, $status = "open", $level = 0, $space = array(), &$unique_counter = 0 ){
    $unique_counter++;
    # $array_size and $array_counter needed for locate the last item
    $array_size = count($arr);
    $array_counter = 0;
    $last_item = FALSE;


    echo '<div style="padding:0px; margin:0px;">';

    foreach($arr as $k=>$v){
        $array_counter++;
        if ($array_size == $array_counter){
            $last_item = TRUE;
            array_push($space, $level);
        }

        # tree open / close? (else it would get status from function call)
        if(!empty($v["status"])) $status = $v["status"];

        echo '<div style="margin-left: 0px; height:18px;">';

        # make spaces or lines, $spaces gives the col numbers which are space
        $tree = '';
        for($i = 0; $i < $level; $i++){
            if (in_array($i, $space)) {
                $tree .= '<img src="'.TREE_SPACE.'">';
            }else{
                $tree .= '<img src="'.TREE_LINE.'">';
            }
        }


        # go through childs
        if(!empty($v["childs"])){
            # +/-
            if ($last_item){
                $tree_plus  = TREE_PLUS_LAST;
                $tree_minus = TREE_MINUS_LAST;
            }else{
                $tree_plus  = TREE_PLUS;
                $tree_minus = TREE_MINUS;
            }

            if ($status == "open"){
                $tree_switch =  '<a href="javascript:swap_tree(\''.$v["id"].$unique_counter.'\', \''.$tree_plus.'\', \''.$tree_minus.'\')">';
                $tree_switch .= '<img src="'.$tree_minus.'" id="swap_icon_'.$v["id"].$unique_counter.'" >';
                $tree_switch .= '</a>';
            }else{
                $tree_switch =  '<a href="javascript:swap_tree(\''.$v["id"].$unique_counter.'\', \''.$tree_plus.'\', \''.$tree_minus.'\')">';
                $tree_switch .= '<img src="'.$tree_plus.'" id="swap_icon_'.$v["id"].$unique_counter.'" >';
                $tree_switch .= '</a>';
            }
        }else{
            if ($last_item){
                $tree_item = TREE_ITEM_LAST;
            }else{
                $tree_item = TREE_ITEM;
            }
            $tree_switch = '<img src="'.$tree_item.'">';
        }

        # standard size of logos in tree
        $icon_size = 'width="18" height="18"';

        # check icon
        if(!empty($v["type"]) ){
            # icon for different types
            if ($v["type"] == "service"){
                $icon_path = TREE_SERVICE;
                # service icons are only 16
            }elseif ($v["type"] == "parent"){
                $icon_path = TREE_PARENT;
            }elseif ($v["type"] == "info"){
                $icon_path = TREE_INFO;
            }elseif ($v["type"] == "warn"){
                $icon_path = TREE_WARNING;
            }elseif ($v["type"] == "host" AND !empty($v["os_icon"]) ){
                $icon_path = OS_LOGO_PATH.'/'.$v["os_icon"];
            }
        }else{
            # no type set, but perhaps still a icon path
            if (!empty($v["os_icon"])){
                $icon_path = $v["os_icon"];
            }else{

            }
        }

        # this variable holds the text (hostname, informations etc.)
        $tree_content_name = '';

        # check if icon exists
        if ( !empty($icon_path) AND file_exists($icon_path) ){
            $tree_item_logo = '<img src="'.$icon_path.'" alt="'.$icon_path.'"'.$icon_size.' />';
        }else{
            $tree_item_logo = '';

            if ( !empty($v["title"]) ){
                $tree_content_name .= $v["title"];
            }
        }

        # Text content of item
        $tree_content = '<span style="margin-left: 5px; height:18px; position: absolute">';

            # mark selected host
            if (!empty($v["selected"]) AND $v["selected"] == TRUE){
                $tree_content_name .= '<b>'.$v["name"].'</b>';
            }else{
                $tree_content_name .= $v["name"];
            }

            if (!empty($v["type"]) AND $v["type"] == "host"){
                # link for hosts
                if (!empty($_GET["xmode"]) ){
                    $tree_content .= '<a href="dependency.php?xmode='.$_GET["xmode"].'&id='.$v["id"];
                    if ( !empty($_GET["pnp_link"]) )    $tree_content .= '&pnp_link='.$_GET["pnp_link"];
                    if ( !empty($_GET["service_link"]) ) $tree_content .= '&service_link='.$_GET["service_link"];
                    $tree_content .= '" style="height:18px;">';
                }else{
                    $tree_content .= '<a href="dependency.php?id='.$v["id"].'" style="height:18px;">';
                }
                $tree_content .= $tree_content_name;
                $tree_content .= '</a>';
            }elseif ( !empty($v["link"]) ){
                # link for services
                if (!empty($_GET["xmode"]) AND !empty($v["link"]) ){
                    $tree_content .= '<a href="'.$v["link"].'" style="height:18px;">';
                    $tree_content .= $tree_content_name;
                    $tree_content .= '</a>';
                }else{
                    $tree_content .= $tree_content_name;
                }
            }else{
                $tree_content .= $tree_content_name;
            }

        $tree_content .= '</span>';


        # print content in choosen order
        echo $tree.$tree_switch.$tree_item_logo.$tree_content;


        echo '</div>';

        if(!empty($v["childs"])){
            echo '<div ';
            if ($status == "open"){
                echo 'style=""';
            }else{
                echo 'style="display:none"';
            }
            echo ' id="'.$v["id"].$unique_counter.'">';
                displayTree_list($v["childs"], $status, ($level+1), $space, $unique_counter);
            echo '</div>';
        }

    }
    echo '</div>';

}




?>
