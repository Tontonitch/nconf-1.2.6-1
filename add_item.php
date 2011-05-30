<?php
require_once 'config/main.php';
require_once 'include/head.php';
set_page();


//if( ( isset($_GET["item"]) ) AND ($_GET["item"] != '') ){
if( !empty($_GET["item"]) ){
    $config_class = $_GET["item"]; 

    if ($config_class == "host"){
        if ( (defined('LOAD_SERVERLIST') AND LOAD_SERVERLIST == 1)
             AND (isset($_SESSION["cmdb_serverlist"]) AND  is_array($_SESSION["cmdb_serverlist"]) AND !empty($_SESSION["cmdb_serverlist"]) )
        ){
            // Create cmdb-serverlist for autocomplete
            $prepare_status = js_Autocomplete_prepare('cmdbserverlist', $_SESSION["cmdb_serverlist"]);
        }
    }



    $query = mysql_query("SELECT ConfigAttrs.id_attr,
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
                                 ConfigAttrs.description
                          FROM ConfigAttrs,ConfigClasses 
                          WHERE id_class=fk_id_class 
                              AND ConfigClasses.config_class='$config_class'
                              AND ConfigAttrs.visible='yes'
                          ORDER BY ConfigAttrs.ordering");
    
    # Load Info Box (ajax content)
    require_once('include/tabs/info.php');
    echo '<h2>Add '.$config_class.'</h2>';
    ?>



    <form name="add_item" action="add_item_step2.php" method="post" onsubmit="multipleSelectOnSubmit()">

    <input name="config_class" type="hidden" value="<?php echo $config_class;?>">
        <table border=0 width="100%" style="table-layout:fixed">
        <?php
        # predefine col width 
        echo define_colgroup();
        
        $notification_period_attribute_id = db_templates("get_attr_id", $config_class, "notification_period");
        $check_period_attribute_id        = db_templates("get_attr_id", $config_class, "check_period");
        $contact_groups_attribute_id      = db_templates("get_attr_id", $config_class, "contact_groups");

        while($entry = mysql_fetch_assoc($query)){
            // Check cache
            if ( isset($_SESSION["cache"]["add"][$entry["id_attr"]]) ){
                $entry["predef_value"] = $_SESSION["cache"]["add"][$entry["id_attr"]];
            }

            # assign_many needs special tr class for setting margin
            if($entry["datatype"] == "assign_many"
                OR $entry["datatype"] == "assign_cust_order" ){
                echo '<tr class="assign_many">';
            }else{
                echo '<tr>';
            }


            echo '<td>'.$entry["friendly_name"].'</td>';

            # check if items being displayed are "services"
            if(isset($entry["fk_show_class_items"])){
                $srvquery = mysql_query('SELECT config_class FROM ConfigClasses WHERE id_class='.$entry["fk_show_class_items"]);
                $srv = mysql_fetch_assoc($srvquery);
            }

            if($entry["datatype"] == "text"){
                // Special hostname selection (from cmdb)
                if ($entry["attr_name"] == "host_name"){
                    echo '<td><input id="serverlist" name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.$entry["predef_value"].'"';
                    if ( isset($prepare_status) AND ($prepare_status == 1) ){
                        # special: get ip address from hostname
                        echo ' onBlur="_loadIps(\'iplist\', \'https://nconf.ispdev-du.tescht.ch/include/modules/sunrise/soap/load_ips.php\', \'serverlist\', 1);"';
                    }
                    echo '>
                            <div id="ipDiv"></div>
                          </td>';
                }elseif ($entry["attr_name"] == "address"){
                    echo '<td><input id="iplist" name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value='.$entry["predef_value"].'></td>';
                }else{
                    echo '<td><input name="'.$entry["id_attr"].'" type="text" maxlength="'.$entry["max_length"].'" value="'.$entry["predef_value"].'"></td>';
                }

            }elseif($entry["datatype"] == "password"){
                echo '<td><input name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.$entry["predef_value"].'">';

            }elseif($entry["datatype"] == "select"){
                // ADMIN users only
                if (  ($_SESSION["group"] != "admin") AND ( in_array($entry["attr_name"], $ADMIN_ONLY) )  ){
                    echo '<input name="'.$entry["id_attr"].'" type=HIDDEN value='.$entry["predef_value"].'>';
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
                    if ($menu == $entry["predef_value"]) echo " SELECTED";
                    echo ">$menu</option>";
                }
                echo "</select></td>";
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
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value');
                }else{
                   $query2 = mysql_query('SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value');
                }

                echo '<td><select id="'.$entry["id_attr"].'" name="'.$entry["id_attr"].'[]" ';

                # Load ajax info for periods (check and notification)
                # mainly for Internet Explorer
                if ($entry["id_attr"] == $notification_period_attribute_id OR $entry["id_attr"] == $check_period_attribute_id){
                    echo ' onmouseover="attachInfo(this, \'basic\')"';
                    echo ' onblur="showHideContent(\'\', \'dhtmlgoodies_q1\', \'hide\');"';
                }
                echo ' >';
                
                if ($entry["mandatory"] == "no"){
                    echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                }
                while($menu2 = mysql_fetch_assoc($query2)){
                    echo '<option value='.$menu2["id_item"];
                    
                    # Load ajax info for periods (check and notification)
                    # mainly for FireFox
                    if ($entry["id_attr"] == $notification_period_attribute_id OR $entry["id_attr"] == $check_period_attribute_id){
                        echo ' onmouseover="getText(this, \'basic\')"';
                    }
                    // SELECTED
                    if ( isset($_SESSION["cache"]["add"][$entry["id_attr"]]) ) {
                        if ( $_SESSION["cache"]["add"][$entry["id_attr"]][0] == $menu2["id_item"] ){
                            echo " SELECTED";
                        }
                    }else{
                        if ( is_array($entry["predef_value"]) ){
                            if ($menu2["id_item"] == $entry["predef_value"][0]) echo ' SELECTED';
                        }else{
                            if ($menu2["attr_value"] == $entry["predef_value"]) echo ' SELECTED';
                        }

                    }
                    // END of SELECTED

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }
                    else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select></td>';

            }elseif($entry["datatype"] == "assign_many"){

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
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value');
                 }else{
                    $query2 = mysql_query('SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value');
                 }
                
                echo '<td colspan=3><select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }
                echo '>';
                $predef_value = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
                $selected_items = array();
                while($menu2 = mysql_fetch_assoc($query2)){
                    # SELECTED
                    if ( isset($_SESSION["cache"]["add"][$entry["id_attr"]]) ) {
                        if ( in_array($menu2["id_item"], $_SESSION["cache"]["add"][$entry["id_attr"]]) ){
                            $selected_items[] = $menu2;
                            continue;
                        }
                    }else{
                        if ( is_array($predef_value) ){
                            if ( in_array($menu2["attr_value"], $predef_value) ){
                                $selected_items[] = $menu2;
                                continue;
                            }
                            
                        }
                    }


                    echo '<option value='.$menu2["id_item"];
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }
                    else{   
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';



                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }
                    
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
                                                    AND ConfigItems.fk_id_class="'.$entry["fk_show_class_items"].'"
                                                ORDER BY hostname,attr_value';
                 }else{
                    $query2 = 'SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class="'.$entry["fk_show_class_items"].'"
                                                ORDER BY attr_value';
                 }


                $result2 = db_handler($query2, "result", "assign_cust_order");

                $selected_items = array();

                # generate base array
                $base_array = array();
                $search_array = array();
                while($entry_row = mysql_fetch_assoc($result2)){
                    $base_array[$entry_row["id_item"]] = $entry_row;
                    # we need a simpler array for searching when using predef_value:
                    $search_array[$entry_row["id_item"]] = $entry_row["attr_value"];
                }
                if ( isset($_SESSION["cache"]["add"][$entry["id_attr"]]) ) {
                    if ( isset($_SESSION["cache"]["add"][$entry["id_attr"]]) ) {
                        foreach ($_SESSION["cache"]["add"][$entry["id_attr"]] as $key => $value){
                            if ( array_key_exists($value, $base_array) ){
                                $selected_items[] = $base_array[$value];
                                unset($base_array[$value]);
                            }
                        }
                    }
                }else{
                    # load predefined items, prepare arrays (this needs the special search_array
                    $predef_values = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
                    if ( isset($predef_values) AND is_array($predef_values) ){
                        foreach ($predef_values as $value){
                            $key = array_search($value, $search_array);
                            if ( $key !== FALSE){
                                $selected_items[] = $base_array[$key];
                                unset($base_array[$key]);
                            }
                        }
                    }
                }

  

                # generate base options
                echo '<td colspan=3><select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }
                echo '>';
                foreach($base_array as $menu2){
                    echo '<option value='.$menu2["id_item"];

                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }
                    echo '>';
                    if ($srv["config_class"] == "service"){
                        echo $menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo $menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';




                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    # Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }

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






            # make "*" for  mandatory fields
            echo '<td class="mark_as_mandatory">';
                if ($entry["mandatory"] == "yes"){
                    if ( ($entry["datatype"] == "assign_many") OR ($entry["datatype"] == "assign_cust_order") ) echo '<br>';
                echo '*';
                }else{
                    echo '&nbsp;';
                }
            echo '</td>';

            # display descripton
            echo '<td valign="top" class="desc" style="word-break:break-all;word-wrap:break-word" ';
                if ( ($entry["datatype"] != "assign_many") AND ($entry["datatype"] != "assign_cust_order") ) echo 'colspan=3';
                echo '>';
                if ($entry["description"] != ""){
                  echo $entry["description"];
                }else{
                  echo '&nbsp;';
                }
            echo '</td>';


            echo "</tr>\n";


        // Take ID from nc_permission for check in write2db script (if user tries to hack)
        if ($entry["attr_name"] == "nc_permission"){
            echo '<input type="hidden" name="ID_nc_permission" value="'.$entry["id_attr"].'">';
        }



        } // End while



        ?>

            <tr>
                <td colspan=2>
                <br>
                <br>

        <?php
        # location of the code for not visible fields
        require_once 'include/add_item_notvisibles.php';

        # Tell the Session, send db query is ok (we are coming from formular)
        $_SESSION["submited"] = "yes";
        ?>
                <div id=buttons>
                <input type="Submit" value="Submit" name="submit" align="middle">
                <input type="Reset" value="Reset">
        <?php
            // Clear button
            if ( isset($_SESSION["cache"]["add"]) ){
                if ( strstr($_SERVER['REQUEST_URI'], ".php?") ){
                    $clear_url = $_SERVER['REQUEST_URI'].'&clear=1';
                }else{
                    $clear_url = $_SERVER['REQUEST_URI'].'?clear=1';
                }
                echo '<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
            }
        ?>
                
                </div>
            </td></tr>
        </table>
    </form>



    <?php
    // Run the Autocomplete function
    if ( isset($prepare_status) AND ($prepare_status == 1) ){
        # only run if prepare was ok
        js_Autocomplete_run('serverlist', 'cmdbserverlist');
    }

}else{
    message($error, "Error: No config_class set");
}


?>



<?php
mysql_close($dbh);
require_once 'include/foot.php';
?>


