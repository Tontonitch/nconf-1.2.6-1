<?php
require_once 'config/main.php';
require_once 'include/head.php';

set_page();

// Autocomplete
if ( defined('AUTO_COMPLETE_PIKETT') AND AUTO_COMPLETE_PIKETT != "0" ){
    //Get Pikett email and pager list
    include('include/modules/sunrise/autocomplete/pikett_users.php');
    // Create pikett / pager list for autocomplete
    $prepare_status = js_Autocomplete_prepare('emaillist', $emaillist);
    $prepare_status = js_Autocomplete_prepare('pagerlist', $pagerlist);
}


# Use Session cache only if back button was clicked
if ( !isset($_POST["back"]) ){
    if ( isset($_SESSION["cache"]["modify"]) ) unset($_SESSION["cache"]["modify"]);
    if ( isset($_SERVER["HTTP_REFERER"]) ) $_SESSION["go_back_page_ok"] = $_SERVER["HTTP_REFERER"];
}
//message($info, "url : ".$_SESSION["go_back_page_ok"]);

if(isset($_GET["xmode"])){

    # Special mode to allow ordinary users to change on-call settings
    # get id_item based on contact name
    $query = 'SELECT fk_id_item FROM ConfigValues,ConfigAttrs,ConfigClasses 
                WHERE id_attr = fk_id_attr 
                    AND naming_attr = "yes" 
                    AND id_class = fk_id_class 
                    AND config_class = "contact" 
                    AND attr_value = "'.$_GET["xmode"].'"';

    $qres = mysql_query($query);
    $entry = mysql_fetch_assoc($qres);

    $_GET["id"] = $entry["fk_id_item"];
    $item_class = "contact";

}else{
    # get $item_class
    $item_class = db_templates("class_name", $_GET["id"]);
}

# get basic entries (ConfigValues) for passed id
$query = mysql_query('SELECT id_attr,attr_value
                        FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                        AND id_item=fk_id_item
                        AND visible="yes" 
                        AND id_item='.$_GET["id"].'
                        ORDER BY ordering');

$item_data = array();
while($entry = mysql_fetch_assoc($query)){
    $item_data[$entry["id_attr"]] = $entry["attr_value"];
}

# get linked entries (ItemLinks) for passed id
$query2 = 'SELECT id_attr,attr_value,fk_item_linked2 
                FROM ConfigValues,ItemLinks,ConfigAttrs 
                WHERE fk_item_linked2=ConfigValues.fk_id_item 
                AND id_attr=ItemLinks.fk_id_attr 
                AND ConfigAttrs.visible="yes" 
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes" 
                AND ItemLinks.fk_id_item='.$_GET["id"].'
                ORDER BY
                ConfigAttrs.friendly_name DESC,
                ItemLinks.cust_order
                ';

//$result2 = mysql_query($query2);
//message($debug, "<b>linked entries</b><br>".$query2);
$result2 = db_handler($query2, "result", "linked entries");

$item_data2 = array();
while($entry2 = mysql_fetch_assoc($result2)){
    $item_data2[$entry2["id_attr"]][$entry2["fk_item_linked2"]] = $entry2["attr_value"];
}


# get entries linked as child (ItemLinks) for passed id
$query3 = 'SELECT id_attr,attr_value,ItemLinks.fk_id_item
                FROM ConfigValues,ItemLinks,ConfigAttrs
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND ConfigAttrs.visible="yes"
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                AND ItemLinks.fk_item_linked2='.$_GET["id"].'
                ORDER BY ConfigAttrs.friendly_name DESC';

$result3 = db_handler($query3, "result", "Linked as child");
//$result3 = mysql_query($query3);
//message($debug, "<b>Linked as child: </b><br>".$query3);

while($entry3 = mysql_fetch_assoc($result3)){
    $item_data2[$entry3["id_attr"]][$entry3["fk_id_item"]] = $entry3["attr_value"];

}

#########################################################################
#########################################################################

if( ( isset($item_class) ) AND ($item_class != '') ){

    # Fetch all attributes belonging to the class that is being edited

    $query = "SELECT ConfigAttrs.id_attr,
                    ConfigAttrs.attr_name,
                    ConfigAttrs.friendly_name,
                    ConfigAttrs.datatype,
                    ConfigAttrs.max_length,
                    ConfigAttrs.poss_values,
                    ConfigAttrs.predef_value,
                    ConfigAttrs.mandatory,
                    ConfigAttrs.ordering,
                    ConfigAttrs.visible,
                    ConfigAttrs.fk_show_class_items,
                    ConfigAttrs.description,
                    ConfigAttrs.link_as_child
                    FROM ConfigAttrs,ConfigClasses 
                    WHERE id_class=fk_id_class 
                    AND ConfigClasses.config_class='$item_class'
                    AND ConfigAttrs.visible='yes'
                    ORDER BY ConfigAttrs.ordering";

    $select_result = db_handler($query, "result", "Get all attributes for config_class='$item_class'");
    
    echo '<h2>Modify '.$item_class.'</h2>';
    ?>

    <form name="add_item" action="modify_item_write2db.php" method="post" onsubmit="multipleSelectOnSubmit()">
    <input name="HIDDEN_config_class" type="hidden" value="<?php echo $item_class;?>">
    <input name="HIDDEN_modify_id" type="hidden" value="<?php echo $_GET["id"];?>">
        <table border="0" style="table-layout:fixed; width:770px">

        <?php
        # predefine col width
        echo define_colgroup();

        //$notification_period_attribute_id = db_templates("get_attr_id", "host", "notification_period");
        //$check_period_attribute_id        = db_templates("get_attr_id", "host", "check_period");
        $contact_groups_attribute_id      = db_templates("get_attr_id", "host", "contact_groups");
        
        while($entry = mysql_fetch_assoc($select_result)){

            # Display servicegroup assignment (only when editing a service)
            if ( ($item_class == "service") AND ($entry["friendly_name"] == "notes") ) {

                # Get id of service
                $servicegroup_id = db_templates("servicegroup_id");

                $query_servicegroups = 'SELECT fk_id_item AS id_item,attr_value AS entryname
                            FROM ConfigValues,ConfigAttrs,ConfigClasses
                            WHERE id_attr=fk_id_attr AND naming_attr="yes"
                                AND id_class=fk_id_class
                                AND config_class="servicegroup"
                                ORDER BY entryname';
                                
                $result = db_handler($query_servicegroups, "result", "Get Servicegroups");
                echo '<tr>';
                echo '<td valign="top">servicegroups</td>';
                echo '<td>';
                echo '<select name="'.$servicegroup_id.'[]" style="'.CSS_SELECT_MULTI.'" multiple>';
                echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

                while($menu3 = mysql_fetch_assoc($result)){
                    echo '<option value='.$menu3["id_item"];

                    if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ){
                        if ( !empty($_SESSION["cache"]["modify"][$servicegroup_id]) AND in_array($menu3["id_item"], $_SESSION["cache"]["modify"][$servicegroup_id]) ) {
                            echo ' SELECTED';
                        }
                    }elseif ( isset($item_data2[$servicegroup_id][$menu3["id_item"]]) ) {
                        echo ' SELECTED';
                    }

                    echo '>'.$menu3["entryname"].'</option>';
                }

                echo "</select></td></tr>";
            }


            # Display checkcommand syntax, if necessary
            if ($item_class == "service" && $entry["attr_name"] == "check_params"){

                # Get command_param_count
                $command_query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs 
                                       WHERE id_attr=fk_id_attr 
                                       AND attr_name="command_param_count" 
                                       AND fk_id_item=
                                            (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                                               WHERE fk_id_attr=id_attr 
                                               AND attr_name="check_command" 
                                               AND fk_id_item='.$_GET["id"].')';

                $command_param_count = db_handler($command_query, "getOne", "Get command_param_count");

                # Get command syntax
                $command_query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs 
                                       WHERE id_attr=fk_id_attr 
                                       AND attr_name="command_syntax" 
                                       AND fk_id_item=
                                            (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                                               WHERE fk_id_attr=id_attr 
                                               AND attr_name="check_command" 
                                               AND fk_id_item='.$_GET["id"].')';

                $cmd_syntax = db_handler($command_query, "getOne", "Get command syntax");

                # Print Command syntax
                $replace = array(",", ";", "!");
                $cmd_syntax = str_replace($replace, "<br>", $cmd_syntax);
                $command_args = '<td colspan=2>&nbsp;</td></tr><tr><td colspan=2 bgcolor=#DDDDDD>
                                 <b>Command syntax:</b><br><i>'.$cmd_syntax.'</i></td><td colspan=2></td></tr>';
            }else{
                $command_args = "";
            }

            # assign_many needs special tr class for setting margin
            if($entry["datatype"] == "assign_many"
                OR $entry["datatype"] == "assign_cust_order" ){
                echo '<tr class="assign_many">'.$command_args;
            }else{
                echo '<tr>'.$command_args;
            }

            echo '<td valign="top">'.$entry["friendly_name"].'</td>';

            # check if items being displayed are "services"
            if(isset($entry["fk_show_class_items"])){
                $srvquery = mysql_query('SELECT config_class FROM ConfigClasses WHERE id_class='.$entry["fk_show_class_items"]);
                $srv = mysql_fetch_assoc($srvquery);
            }

            # process "text" fields
            if(  ($entry["datatype"] == "text") AND (  (( isset($command_param_count) ) AND ($command_param_count  <= 1)) OR ( !isset($command_param_count) )     ) ){

                if (  ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) )  ){
                    $value = $_SESSION["cache"]["modify"][$entry["id_attr"]];
                }elseif ( isset($item_data[$entry["id_attr"]]) ){
                    $value = $item_data[$entry["id_attr"]];
                }else{
                    $value = "";
                }
                //$value = htmlspecialchars($value);
                
                //special auto complete
                //if ($entry["attr_name"] == "email" OR $entry["attr_name"] == "pager"){
                if ( preg_match("/[email|pager]/", $entry["attr_name"]) ){
                    echo '<td><input id="'.$entry["attr_name"].'" name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">';
                }else{
                    echo '<td><input name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">';
                }



            # process "password" fields
            }elseif($entry["datatype"] == "password"){
                if (  ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) )  ){
                    $value = $_SESSION["cache"]["modify"][$entry["id_attr"]];
                }elseif ( isset($item_data[$entry["id_attr"]]) ){
                    $value = $item_data[$entry["id_attr"]];
                    $value = show_password($value);
                }else{
                    $value = "";
                }
                echo '<td><input name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">';


            # process "select" fields
            }elseif($entry["datatype"] == "select"){
                // ADMIN users only
                if (  ($_SESSION["group"] != "admin") AND ( in_array($entry["attr_name"], $ADMIN_ONLY) )  ){
                    echo '<input name="'.$entry["id_attr"].'" type="HIDDEN" value="'.$entry["predef_value"].'">';
                }

                $dropdown = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["poss_values"]);
                echo '<td><select name="'.$entry["id_attr"].'" size="0"';

                // ADMIN users only
                if (  ($_SESSION["group"] != "admin") AND ( in_array($entry["attr_name"], $ADMIN_ONLY) )  ){
                    echo " DISABLED";
                }

                echo '>';
                
                if ($entry["mandatory"] == "no"){
                    echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                }

                foreach ($dropdown as $menu){
                    echo "<option";
                    if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ){
                        if ( $menu == $_SESSION["cache"]["modify"][$entry["id_attr"]] ){
                            echo " SELECTED";
                        }
                    }elseif (  ( isset($item_data[$entry["id_attr"]]) ) AND ($menu == $item_data[$entry["id_attr"]])  ){
                        echo " SELECTED";
                    }
                    echo ">$menu</option>";
                }
                echo "</select></td>";

            # process "assign_one" fields
            }elseif($entry["datatype"] == "assign_one"){
                if ($srv["config_class"] == "service"){
                    $query2 = mysql_query('SELECT id_item,attr_value,
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
                                                        AND naming_attr="yes"
                                                        AND id_item <> '.$_GET["id"].'
                                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value');
                }else{
                    $query2 = mysql_query('SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                    WHERE id_item=fk_id_item
                                                        AND id_attr=fk_id_attr
                                                        AND naming_attr="yes"
                                                        AND id_item <> '.$_GET["id"].'
                                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value');
                }

                echo '<td><select name="'.$entry["id_attr"].'[]">';
                
                if ($entry["mandatory"] == "no"){
                    echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                }
                while($menu2 = mysql_fetch_assoc($query2)){

                    echo '<option value='.$menu2["id_item"];
                    if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ) {
                        if ( $_SESSION["cache"]["modify"][$entry["id_attr"]][0] == $menu2["id_item"] ){
                            echo " SELECTED";
                        }
                    }elseif( isset($item_data2[$entry["id_attr"]][$menu2["id_item"]]) ) {
                        echo ' SELECTED';
                    }

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select></td>';
            # process "assign_many" fields
            }elseif($entry["datatype"] == "assign_many"){
                if ($srv["config_class"] == "service"){
                    $query2 = 'SELECT id_item,attr_value,
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
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY hostname,attr_value';
                }else{
                    $query2 = 'SELECT id_item,attr_value
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY attr_value';
                }

                $result2 = db_handler($query2, "result", "assign_many");
                echo '<td colspan=3><select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                
                $selected_items = array();
                while($menu2 = mysql_fetch_assoc($result2)){
                    // SELECTED
                    if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ) {
                        if ( in_array($menu2["id_item"], $_SESSION["cache"]["modify"][$entry["id_attr"]]) ){
                            $selected_items[] = $menu2;
                            continue;
                        }
                    }else{
                        if ( is_array($item_data2[$entry["id_attr"]]) ){
                            if ( array_key_exists($menu2["id_item"], $item_data2[$entry["id_attr"]] ) ){
                                $selected_items[] = $menu2;
                                continue;
                            }
                        }
                    }

                    echo '<option value='.$menu2["id_item"];

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';

                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }*/

                    // END of SELECTED

                    if ($srv["config_class"] == "service"){
                        echo '>'.$selected_menu["hostname"].': '.$selected_menu["attr_value"].'</option>';
                    }
                    else{   
                        echo '>'.$selected_menu["attr_value"].'</option>';
                    }
                }
                echo '</select>';
                
                # assign_cust_order handling
                $assign_cust_order = ($entry["datatype"] == "assign_cust_order") ? 1 : 0;
                echo '
                <script type="text/javascript">
                    createMovableOptions("fromBox_'.$entry["id_attr"].'","toBox_'.$entry["id_attr"].'",500,145,"available items","selected items","livesearch",'.$assign_cust_order.');
                </script>
                ';
                

                echo '</td>';


            # process "assign_cust_order" fields
            }elseif($entry["datatype"] == "assign_cust_order"){

                if ($srv["config_class"] == "service"){
                    $query2 = 'SELECT id_item,attr_value,
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
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY hostname,attr_value';
                }else{
                    $query2 = 'SELECT id_item,attr_value
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY attr_value';
                }

                $result2 = db_handler($query2, "result", "assign_many");
                
                $selected_items = array();

                # generate base array
                $base_array = array();
                while($entry_row = mysql_fetch_assoc($result2)){
                    $base_array[$entry_row["id_item"]] = $entry_row;
                }

                if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ) {
                    if ( isset($_SESSION["cache"]["modify"][$entry["id_attr"]]) ) {
                        foreach ($_SESSION["cache"]["modify"][$entry["id_attr"]] as $key => $value){
                            if ( array_key_exists($value, $base_array) ){
                                $selected_items[] = $base_array[$value];
                                unset($base_array[$value]);
                            }
                        }
                    }
                }else{
                    # load selected items, prepare arrays
                    if ( isset($item_data2[$entry["id_attr"]]) AND is_array($item_data2[$entry["id_attr"]]) ){
                        foreach ($item_data2[$entry["id_attr"]] as $key => $value){
                            if ( array_key_exists($key, $base_array) ){
                                $selected_items[] = $base_array[$key];
                                unset($base_array[$key]);
                            }
                        }
                    }
                }

                # generate base options
                echo '<td colspan=3><select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach($base_array as $menu2){
                    echo '<option value='.$menu2["id_item"];

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';

                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }*/

                    // END of SELECTED

                    if ($srv["config_class"] == "service"){
                        echo '>'.$selected_menu["hostname"].': '.$selected_menu["attr_value"].'</option>';
                    }
                    else{   
                        echo '>'.$selected_menu["attr_value"].'</option>';
                    }
                }
                echo '</select>';
                
                # assign_cust_order handling
                $assign_cust_order = ($entry["datatype"] == "assign_cust_order") ? 1 : 0;
                echo '
                <script type="text/javascript">
                    createMovableOptions("fromBox_'.$entry["id_attr"].'","toBox_'.$entry["id_attr"].'",500,145,"available items","selected items","livesearch",'.$assign_cust_order.');
                </script>
                ';
                

                echo '</td>';


            }




            # display "*" for mandatory fields
            echo '<td class="mark_as_mandatory">';
                if ($entry["mandatory"] == "yes"){
                    if ( ($entry["datatype"] == "assign_many") OR ($entry["datatype"] == "assign_cust_order") ) echo '<br>';
                    echo '*';
                }else{
                    echo '&nbsp;';
                }
            echo '</td>';

            # display attr descripton
            echo '<td valign="top" class="desc"';
                if ( ($entry["datatype"] != "assign_many") AND ($entry["datatype"] != "assign_cust_order") ) echo 'colspan=3';
                echo '>';
                if ($entry["description"] != ""){
                    echo $entry["description"];
                }else{
                    echo '&nbsp;';
                }
                echo '</td>';

            echo "</tr>\n";


            # process multivalue fields
            if(  ($entry["datatype"] == "text") AND (( isset($command_param_count) ) AND ($command_param_count  > 1)) ){

                if (  isset($_SESSION["cache"]["modify"][$entry["id_attr"]])  ){
                    $value = $_SESSION["cache"]["modify"][$entry["id_attr"]];
                }elseif ( isset($item_data[$entry["id_attr"]]) ){
                    $value = $item_data[$entry["id_attr"]];
                }else{
                    $value = "";
                }

                $commands_split = explode("!", $value);


                #
                # Handle  \!  in commands
                # Nagios allows to put \! in commands, so do not split that
                # Put commands back together if a \! was split bevore
                #
                $commands_array = array();
                $command = '';
                foreach($commands_split as $command_part){
                    $command .= $command_part;
                    # if command ends with a backslash (\) the next command must be attached with a !
                    if ( preg_match("/\\\\$/", $command) ){
                        # if there is a backslash at the end, add the !
                        $command .= "!";
                    }else{
                        # command doesn't end with a backslash (\) so put it in array
                        $commands_array[] = $command;
                        $command = '';
                    }
                }

                # generate html output
                for ($i = 1; $i <= $command_param_count; $i++){
                    # If not set make empty because of php offset failure
                    if ( isset($commands_array[$i]) ){

                    }else{
                        $commands_array[$i] = '';
                    }

                    echo '<tr><td align=right>ARG'.$i.': </td>
                              <td colspan=3> <input name="exploded['.$entry["id_attr"].'][]" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($commands_array[$i]).'"></td>
                          </tr>';
                }

                # unset $command_param_count for next loop
                unset($command_param_count);

            }

            // DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
            if ( ($item_class == "contact") AND ($_SESSION["group"] != "admin")
            AND ( $entry["attr_name"] == "nc_permission" AND
                    (!empty($item_data[$entry["id_attr"]]) AND  $item_data[$entry["id_attr"]] == "admin") )  ){
                // Disable the submit button and add message
                $deny_modification = TRUE;
                echo '<input type="hidden" name="deny_modification" value="TRUE">';
                message($info, TXT_SUBMIT_DISABLED4USER, "red");
            }

            // Take ID from nc_permission for check in write2db script (if user tries to hack)
            if ($entry["attr_name"] == "nc_permission"){
                echo '<input type="hidden" name="ID_nc_permission" value="'.$entry["id_attr"].'">';
            }


        } // END of while

#########

        echo '</table>
            <br>
            <br>';
        // location of the code for not visible fields
        require_once 'include/add_item_notvisibles.php';

        if ( isset($deny_modification) AND ($deny_modification == TRUE) ){
            // DENIED
            echo '<div id=buttons>';
            echo '<input type="Submit" value="Submit" name="modify" align="middle" DISABLED>';
            echo ' <input type="Reset" value="Reset">';
            echo '</div>';
            echo '<br>'.$info;
        }else{
            // ALLOWED
            echo '<div id=buttons>';
            echo '<input type="Submit" value="Submit" name="modify" align="middle">';
            echo ' <input type="Reset" value="Reset">';
            echo '</div>';
        }
        
        echo '</form>';

}else{
    message($error, "Error: No config_class set");
}


// Run the Autocomplete function
if ( isset($prepare_status) AND ($prepare_status == 1) ){
    # only run if prepare was ok
    js_Autocomplete_run('email', 'emaillist');
    js_Autocomplete_run('pager', 'pagerlist');
}



mysql_close($dbh);
require_once 'include/foot.php';
?>
