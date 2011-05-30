<?php

require_once 'config/main.php';
require_once 'include/head.php';

// Form action and url handling
if ( !isset($_GET["goto"]) ){
    $request_url = set_page();
}else{
    $request_url = $_SESSION["go_back_page"];
}

if ( !empty($_GET["order"]) ){
    $regex = '/&order=[^&]*/';
    $request_url4ordering = preg_replace($regex, '', $request_url);
}else{
    $request_url4ordering = $request_url;
}
if ( !empty($_GET["start"]) ){
    $regex = '/&start=[^&]*/';
    $request_url4limit = preg_replace($regex, '', $request_url);
    $request_url4ordering = preg_replace($regex, '', $request_url4ordering);
    $request_url4form = $request_url4ordering;
}else{
    $request_url4limit= $request_url;
    $request_url4form = $request_url4ordering;
}
# Quantity
if ( !empty($_GET["quantity"]) ){
    $regex = '/&quantity=[^&]*/';
    $request_url4quantity = preg_replace($regex, '', $request_url);
}else{
    $request_url4quantity = $request_url;
}



# Order
if ( isset($_GET["order"]) ) {
    $order = $_GET["order"];
}else{
    $order = "";
}

if ( isset($_GET["spec"]) ) {
    $spec = $_GET["spec"];
    $show_class_select = "yes";
}else{
    $spec = "";
}


// show select class field when no class is given in URL
// (admin check would be not relevant, because access rules are set very smart :)
if (  ( !isset($_GET["class"]) AND empty($_GET["class"]) ) AND ($_SESSION["group"] == GROUP_ADMIN) AND (!isset($_GET["xmode"])) ) {
    $show_class_select = "yes";
}



# set Filters

// Class Filter
if ( isset($_GET["filter1"]) AND !empty($_GET["filter1"])) {
    $class = $_GET["filter1"];
    $_SESSION["cache"]["searchfilter"]["filter1"] = $class;
}elseif ( isset($_GET["class"]) ) {
    $class = $_GET["class"];
}elseif ( isset($_SESSION["cache"]["searchfilter"]["filter1"]) ) {
    $class = $_SESSION["cache"]["searchfilter"]["filter1"];
}else{
    $class = "";
}

# special mode to allow ordinary users to change on-call settings
if (isset($_GET["xmode"]) && $_GET["xmode"] == "pikett"){
    $class = "contact";
}



# OS Filter
if ( isset($_GET["os"]) ) {
    $filter_os = $_GET["os"];
    $_SESSION["cache"][$class]["searchfilter"]["os"] = $filter_os;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["os"]) ) {
    $filter_os = $_SESSION["cache"][$class]["searchfilter"]["os"];
}else{
    $filter_os = "";
}


if ( ($class == "host") AND ($spec == "") ){
    $show_os_select = 1;
}

# Searchfilter
if ( isset($_GET["filter2"]) AND !empty($_GET["filter2"]) ) {
    $filter2 = $_GET["filter2"];
    $filter2 = str_replace("%", "*", $filter2);
    $_SESSION["cache"][$class]["searchfilter"]["filter2"] = $filter2;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["filter2"]) ) {
    $filter2 = $_SESSION["cache"][$class]["searchfilter"]["filter2"];
}else{
    $filter2 = "";
}

# quantity
# how many entries to show
if ( isset($_GET["quantity"]) ) {
    $show_quantity = $_GET["quantity"];
    $_SESSION["cache"][$class]["searchfilter"]["quantity"] = $show_quantity;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["quantity"]) ) {
    $show_quantity = $_SESSION["cache"][$class]["searchfilter"]["quantity"];
}else{
    if ( defined('OVERVIEW_QUANTITY_STANDARD') ){
        $show_quantity = OVERVIEW_QUANTITY_STANDARD;
    }else{
        $show_quantity = '';
    }
}
# handle "all" (empty variable will show all entries)
if ($show_quantity == "all") $show_quantity = '';




if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND ( !isset($_SESSION["cmdb_serverlist"]) )  ){ 
    # load server list, if activated and shoudl be loaded
    # the new login system can directly go to overview without loading the server list after login, so do it here when not done yet
    $load_serverlist = 'include/modules/sunrise/load_serverlist.php';
    if (file_exists($load_serverlist) ){
        require_once ($load_serverlist);
    }
}


// selected a submit which goes to other page?
if ( !empty($_POST["advanced"]) && ($_POST["advanced"] == "clone") OR !empty($_POST["clone_x"] ) OR
    !empty($_GET["goto"]) && ($_GET["goto"] == "clone") ){
    $id_items = $_POST["advanced_items"][0];
    echo '<meta http-equiv="refresh" content="0; url=clone_host.php?id='.$id_items.'">';
    exit;
    // Do not go thru more code
}
if ( !empty($_POST["advanced"]) && ($_POST["advanced"] == "multimodify") OR !empty($_POST["multimodify_x"]) OR
    !empty($_GET["goto"]) && ($_GET["goto"] == "multimodify") ){
    $id_items = implode(",", $_POST["advanced_items"]);
    echo '<meta http-equiv="refresh" content="0; url=multimodify_attr.php?class='.$class.'&ids='.$id_items.'">';
    exit;
    // Do not go thru more code
}
if ( !empty($_POST["advanced"]) && ($_POST["advanced"] == "multidelete") OR !empty($_POST["multidelete_x"]) OR
    !empty($_GET["goto"]) && ($_GET["goto"] == "multidelete") ){
    $id_items = implode(",", $_POST["advanced_items"]);
    echo '<meta http-equiv="refresh" content="0; url=delete_item.php?item='.$class.'&ids='.$id_items.'">';
    exit;
    // Do not go thru more code
}


# save overview page for a delete operation
$_SESSION["after_delete_page"] = $request_url;


echo '<form name="search" action="'.$request_url.'" method="get">';
# set some var for search form
if (!empty($_GET["class"]) ){
    echo '<input type="hidden" name="class" value="'.$class.'">';
}
if (!empty($_GET["order"]) ){
    echo '<input type="hidden" name="order" value="'.$order.'">';
}
if (!empty($_GET["quantity"]) ){
    echo '<input type="hidden" name="quantity" value="'.$_GET["quantity"].'">';
}

// Page output begin


echo '<div style="width: 500px; float: left;">';

echo '<h2 style="margin-right:4px">Show: '.$class.'</h2>';
echo '<table border=0 frame=box rules=none width="550">';


// Class Filter
if ( isset($show_class_select) ){
    echo '<tr>';
        echo '<td colspan=2>Class</td>';

        $query = 'SELECT config_class FROM ConfigClasses ORDER BY config_class';
        $result = db_handler($query, 'result', "Get Config Classes");

    echo '</tr>';
    echo '<tr>';
        //echo '<td><select name="filter1" style="width:190px">';
        echo '<td colspan=2><select name="filter1">';
        echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        while($row = mysql_fetch_row($result)){
            echo "<option value=$row[0]";
            if ( (isset($class) ) AND ($row[0] == $class) ) echo " SELECTED";
            echo ">$row[0]</option>";
        }

        echo '</select>&nbsp;&nbsp;</td>';
    echo '</tr>';
}


// Searchfilter
echo '<tr>';
    echo '<td style="width:170px">Searchfilter</td>';

    if ( isset($show_os_select) ){
        echo '<td>&nbsp;&nbsp;OS</td>';
    }
    echo '<td></td>';
echo '</tr>';

echo '<tr>';

    echo '<td><input style="width:150px" type="text" name="filter2" value="'.$filter2.'"></td>';

    if ( isset($show_os_select) ){
        // OS filter
        echo '<td>';
        echo '<select name="os" style="width:200px">';
        echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        $query = 'SELECT fk_id_item,attr_value
                    FROM ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_attr=fk_id_attr
                    AND id_class=fk_id_class
                    AND naming_attr="yes"
                    AND config_class="os"
                    ORDER BY attr_value
                 ';
        $result = db_handler($query, 'result', "select all os");
        while ($entry = mysql_fetch_assoc($result) ){
            echo '<option value='.$entry["fk_id_item"];
            if ( (isset($filter_os) ) AND ($entry["fk_id_item"] == $filter_os) ) echo " SELECTED";
            echo '>'.$entry["attr_value"].'</option>';
        }
        echo '</select></td>';
    }


    // submit button
    echo '<td align="left" id="buttons" width=300>&nbsp;&nbsp;<input type="submit" value="Search" name="search" align="middle">';

    // Clear button
    if ( isset($_SESSION["cache"][$class]["searchfilter"]) ){

        # get the script name
        $clear_url = $_SERVER['SCRIPT_NAME'].'?';

        # remember the class only if given ( should not be set on "general overview" )
        if (isset($_GET["class"]) )   $clear_url .= 'class='.$class.'&';
        # clear filter 1 if given
        if (isset($_GET["filter1"]) ) $clear_url .= 'filter1=&';

        # add the clear
        $clear_url .= 'clear=1';

        echo '&nbsp;&nbsp;<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
    }

    echo "</td>";
echo "</tr>";

echo '</table>';

echo '</div>';


echo '</form>';

# open new form
echo '<form name="advanced" action="'.$request_url4form.'" method="post">';


# Advanced Tab-View
if (!isset($_GET["xmode"])){
    require_once 'include/tabs/advanced.php';
}



if( ( isset($class) ) AND ($class != "") ){

    # handle start
    if (empty($_GET["start"]) OR $_GET["start"] < 0 ){
        $start = 0;
    }else{
        $start = $_GET["start"];
    }

    # Querys
    if ($class == "host") {

        $query = '
    SELECT fk_id_item AS host_id,
       attr_value AS hostname,
       (SELECT attr_value 
             FROM ConfigValues,ConfigAttrs 
             WHERE id_attr=fk_id_attr 
                 AND attr_name="address" 
                 AND fk_id_item=host_id) AS IP,
       (SELECT INET_ATON(IP)
             ) AS BIN_IP,
       (SELECT attr_value 
             FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks 
             WHERE id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND ConfigValues.fk_id_item=fk_item_linked2 
                 AND id_class=fk_id_class 
                 AND config_class="nagios-collector" 
                 AND ItemLinks.fk_id_item=host_id) AS collector,
       (SELECT attr_value 
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os,
       (SELECT ConfigValues.fk_id_item 
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os_id,
       (SELECT attr_value
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND attr_name="icon_image"
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os_icon
       FROM ConfigValues,ConfigAttrs,ConfigClasses 
       WHERE id_attr=fk_id_attr AND naming_attr="yes" 
           AND id_class=fk_id_class 
           AND config_class="'.$class.'"';

        # use filters
        if ($filter2 != ""){
            $filter2 = str_replace("*", "%", $filter2);
            $filter2 = escape_string($filter2);
            $query .= ' AND attr_value LIKE "'.$filter2.'"';
        }

        # use os filter
        if ( !empty($filter_os) ) $query .= ' HAVING os_id = "'.$filter_os.'"';

        # Handle order
        if (!empty($order) ){
            $query .= ' ORDER BY '.$order;
        }else{
            $order = 'hostname ASC';
            $query .= ' ORDER BY '.$order;
        }

        # LIMIT
        if ( !empty($show_quantity) ){
            # lookup how many entries are totaly
            $show_num_rows = db_handler($query, 'num_rows', "How many rows totaly");
            if ($start == $show_num_rows) $start = $show_num_rows - $show_quantity;
            if ($start < 0) $start = 0;
            
            
            # make limited query
            $query .= ' LIMIT '.$start.' , '.$show_quantity;
        }

        $result = db_handler($query, 'result', "Overview list");
        # result for overview list

    }else{
        # class != "host"
        if ($class == "checkcommand"){
            # special query for checkcommand and its default service name
            $query = 'SELECT id_item,attr_value AS entryname,
                (SELECT attr_value
                    FROM ConfigValues, ConfigAttrs
                    WHERE ConfigValues.fk_id_item = id_item
                    AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                    AND ConfigAttrs.attr_name = "default_service_name") AS default_service_name,
                IFNULL(
                    (SELECT attr_value
                    FROM ConfigValues, ConfigAttrs
                    WHERE ConfigValues.fk_id_item = id_item
                        AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                        AND ConfigAttrs.attr_name = "default_service_name"
                    )
                , attr_value) AS sorting
                FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                WHERE id_item=fk_id_item
                    AND id_attr=fk_id_attr
                    AND naming_attr="yes"
                    AND ConfigItems.fk_id_class=id_class
                    AND config_class="'.$class.'"';
            # define special ordering
            if( empty($order) ){
                $order = "sorting ASC";
            }
        }elseif($class == "service"){
            # special query for service and its hostnames
            $query = 'SELECT id_item,attr_value AS entryname,
                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                            AND id_attr=ConfigValues.fk_id_attr
                            AND naming_attr="yes"
                            AND fk_id_class = id_class
                            AND config_class="host"
                            AND ItemLinks.fk_id_item=id_item) AS hostname
                    FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND naming_attr="yes"
                        AND ConfigItems.fk_id_class=id_class
                        AND config_class="'.$class.'"';
        }else{
            $query = 'SELECT id_item,attr_value AS entryname
                    FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND naming_attr="yes"
                        AND ConfigItems.fk_id_class=id_class
                        AND config_class="'.$class.'"';
        }
        if($filter2 != ""){
            # replace * with % for sql search
            $filter2 = str_replace("*", "%", $filter2);
            $filter2 = escape_string($filter2);
            if($class == "service"){
                # search for servername AND servicename on "service"
                $query .= ' HAVING CONCAT(hostname,entryname) LIKE "'.$filter2.'"';
            }elseif($class == "checkcommand"){
                # search for default service name and checkcommand name
                $query .= 'HAVING default_service_name LIKE "'.$filter2.'"
                            OR entryname LIKE "'.$filter2.'"';
            }else{
                $query .= ' AND attr_value LIKE "'.$filter2.'"';
            }
        }
        
        # XMODE
        if(isset($_GET["xmode"]) && $_GET["xmode"] == "pikett"){
            if ( !empty($ONCALL_GROUPS) ){
                # first entry must be AND, all other are part of it with OR
                $oncall_i = 1;
                foreach ($ONCALL_GROUPS as $oncall_group){
                    if ($oncall_i == 1){
                        $query .= ' AND ( attr_value = "'.$oncall_group.'"';
                    }else{
                        $query .= ' OR attr_value = "'.$oncall_group.'"';
                    }
                    $oncall_i++;
                }
                #close query correct
                $query .= ' ) ';
            }
        }

        # Handle order
        if ($class == "service"){
            # define special ordering
            if( empty($order) ){
                $order = "hostname ASC, entryname ASC";
            }
        }

        if (!empty($order) ){
            $query .= ' ORDER BY '.$order;
        }else{
            $order = 'entryname ASC';
            $query .= ' ORDER BY '.$order;
        }


        # LIMIT
        if ( !empty($show_quantity) ){
            # lookup how many entries are totaly
            $show_num_rows = db_handler($query, 'num_rows', "How many rows totaly");
            if ($start == $show_num_rows) $start = $show_num_rows - $show_quantity;
            if ($start < 0) $start = 0;

            # make limited query
            $query .= ' LIMIT '.$start.' , '.$show_quantity;
        }

        # result for overview list
        $result = db_handler($query, 'result', "Overview list");
    }


    # overview table in IE 8 will only do correct margin-top when previouse element hast clear:both
    echo '<div class="clearer"></div>';

    ##
    # show quantity
    ##
    if ($class == "host"){
        echo '<table class="overview_head" style="width: 100%;">';
    }else{
        $table_width = 400;
        if ($class == "service") $table_width = 500;

        echo '<table class="overview_head" style="width: '.$table_width.'px;">';
    }

        echo '<tr>';

        echo '<td width="20%">
                <h3>&nbsp;Overview</h3>
              </td>';

        if ( !empty($show_quantity) ){

            ###
            # show limited entries, make it swap'able
            ###
            $show_next = $start + $show_quantity;
            $show_prev = $start - $show_quantity; 
            if ($show_prev < 0) $show_prev = 0;
            if ($show_next > $show_num_rows) $show_next = $show_num_rows;
            $show_start = $start + 1;
            # no results mean no start at 0 not 1
            if ($show_num_rows == 0) $show_start = 0;
                echo '<td style="text-align: center; vertical-align: middle">';
                    echo '<a href="'.$request_url4limit.'&start=0">'.ICON_LEFT_FIRST_ANIMATED.'</a>';
                    echo '<a href="'.$request_url4limit.'&start='.$show_prev.'">'.ICON_LEFT_ANIMATED.'</a>';
                        echo 'Entries '.$show_start.' - '.$show_next.' of '.$show_num_rows;
                    echo '<a href="'.$request_url4limit.'&start='.$show_next.'">'.ICON_RIGHT_ANIMATED.'</a>';
                    echo '<a href="'.$request_url4limit.'&start='.$show_num_rows.'">'.ICON_RIGHT_LAST_ANIMATED.'</a>';
                echo '</td>';
        }else{
            echo '<td>&nbsp;</td>';
        }




        # selectable quantity
        echo '<td class="overview_quantity"  width="20%">';
            echo ($show_quantity != QUANTITY_SMALL) ?  '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_SMALL.'">'.QUANTITY_SMALL.'</a>' : QUANTITY_SMALL;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != QUANTITY_MEDIUM) ? '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_MEDIUM.'">'.QUANTITY_MEDIUM.'</a>' : QUANTITY_MEDIUM;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != QUANTITY_LARGE) ?  '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_LARGE.'">'.QUANTITY_LARGE.'</a>' : QUANTITY_LARGE;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != "") ?  '<a href="'.$request_url4quantity.'&quantity=all">all</a>' : 'all';
        echo '</td>';


    # close table header div
    echo '</tr>';
    echo '</table>';



    if ($class == "host"){
        echo '<table class="noneborder simpletable bordertop" style="min-width:480px" width="100%">';
    }else{
        $table_width = 400;
        if ($class == "service") $table_width = 500;
        echo '<table id="advanced_box_table" class="noneborder simpletable bordertop" style="min-width:350px" width="'.$table_width.'">';
    }

    

    # Fetch column titles
    $query = 'SELECT ConfigAttrs.friendly_name
                            FROM ConfigAttrs,ConfigClasses
                            WHERE id_class=fk_id_class
                            AND naming_attr="yes"
                            AND config_class="'.$class.'"
                            ';
    $title_result = db_handler($query, 'result', "Friendly name");

    echo '<tr class="bg_header">';
        if ($class == "host") {
            echo '<td width="30">&nbsp;<b>'.FRIENDLY_NAME_OS_LOGO.'</b></td>';
        }
        while($entry = mysql_fetch_assoc($title_result)){
            if ($class == "host"){
                $order_value = (!empty($order) AND $order ==  "hostname ASC") ? 'hostname DESC' : 'hostname ASC';
            }elseif ($class == "checkcommand"){
                $order_value = (!empty($order) AND $order ==  "sorting ASC") ? 'sorting DESC' : 'sorting ASC';
            }elseif ($class == "service"){
                $order_value = (!empty($order) AND $order ==  "hostname ASC, entryname ASC") ? 'hostname DESC, entryname DESC' : 'hostname ASC, entryname ASC';
            }else{
                $order_value = (!empty($order) AND $order ==  "entryname ASC") ? 'entryname DESC' : 'entryname ASC';
            }
            echo '<td><b>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">&nbsp;'
                .$entry["friendly_name"].
                '</a></b></td>';
        }
        if ($class == "host") {
            $order_value = ($order ==  "BIN_IP ASC") ? 'BIN_IP DESC' : 'BIN_IP ASC';
            echo '<td width=100><b>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">&nbsp;'
                .FRIENDLY_NAME_IPADDRESS.
                '</a></b></td>';
            $order_value = ($order ==  "collector ASC") ? 'collector DESC' : 'collector ASC';
            echo '<td width=100><b>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">&nbsp;'
                .FRIENDLY_NAME_NAGIOSSERVER.
                '</a></b></td>';
            $order_value = ($order ==  "os ASC") ? 'os DESC' : 'os ASC';
            echo '<td width=100><b>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">&nbsp;'
                .FRIENDLY_NAME_OS.
                '</a></b></td>';
        }
        echo '<td width="30" style="text-align: center;"><b>'.FRIENDLY_NAME_EDIT.'</b></td>';
        if(!isset($_GET["xmode"])){
            echo '<td width="30" style="text-align: center;"><b>'.FRIENDLY_NAME_DELETE.'</b></td>';
        }
        if ($class == "host") {
            echo '<td width="30" style="text-align: center;"><b>'.FRIENDLY_NAME_SERVICES.'</b></td>';
        }

        # Check cookie, if advanced tab was open, display the checkboxes too (when not, set display: none:)
        echo '<td width="30" id="advanced_box" name="advanced_box" style="text-align: center;';
            if ( !empty($_COOKIE["advanced_box"]) AND $_COOKIE["advanced_box"] == "open" ){
            }else{
                echo ' display: none;';
            }
        echo '"><b>advanced</b></td>';

    echo "</tr>";





    # Show host overview    
    if ($class == "host") {
        # result was generated near row 384 / 453....
        if ($result != "") {
            $count = 1;
            while($entry = mysql_fetch_assoc($result)){

                # set list color
                if ((1 & $count) == 1){
                    $bgcolor = "color_list1";
                }else{
                    $bgcolor = "color_list2";
                }

                $nocol_style = "";

                // Compare hostname feature
                $compare_status = 0;  # default set to 0 (deactivated)
                if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND ( isset($_SESSION["cmdb_serverlist"]) AND is_array($_SESSION["cmdb_serverlist"]) )  ){ 
                    $compare_status = compare_hostname($entry["hostname"], $_SESSION["cmdb_serverlist"]);
                }
                if ($compare_status == 2){
                    # status 2 = not in array
                    echo '<tr class="color_warning overview.php">';
                }else{
                    if($entry["collector"]){
                        echo '<tr class="'.$bgcolor.' highlight">';
                    }else{
                        echo '<tr class="color_nomon highlight">';
                        $entry["collector"] = "not monitored";
                        $nocol_style = 'class="color_nomon_text"';
                    }
                }
                echo '<td style="text-align:center">';
                $os_icon_path = OS_LOGO_PATH.'/'.$entry["os_icon"];
                if ( file_exists($os_icon_path) ){
                    echo '<img src="'.$os_icon_path.'" alt="'.$entry["os"].'" '.OS_LOGO_SIZE.'>';
                }
                echo '</td>';
                echo '<td>&nbsp;<a href="detail.php?id='.$entry["host_id"].'">'.$entry["hostname"].'</a></td>';
                echo '<td>&nbsp;'.$entry["IP"].'</td>';
                echo '<td '.$nocol_style.'>&nbsp;'.$entry["collector"].'</td>';
                echo '<td>&nbsp;'.$entry["os"].'</td>';
                echo '<td style="text-align:center"><a href="modify_item.php?item='.$class.'&id='.$entry["host_id"].'">'.OVERVIEW_EDIT.'</a></td>';
                echo '<td style="text-align:center"><a href="delete_item.php?item='.$class.'&ids='.$entry["host_id"].'">'.OVERVIEW_DELETE.'</a></td>';
                echo '<td style="text-align:center"><a href="modify_item_service.php?id='.$entry["host_id"].'">'.OVERVIEW_SERVICES.'</a></td>';

                # Check cookie, if advanced tab was open, display the checkboxes too (when not, set display: none:)
                echo '<td id="advanced_box" name="advanced_box" style="text-align:center;';
                    if ( !empty($_COOKIE["advanced_box"]) AND $_COOKIE["advanced_box"] == "open" ){
                    }else{
                        echo ' display: none;';
                    }
                echo '">';
                    echo '<input type="checkbox" name="advanced_items[]" value="'.$entry["host_id"].'" style="width: 12px; height: 12px; border-style:none"></td>';
                echo "</tr>\n";
                $count++;
            }
        }

    }else{
    # all other classes

        if ($result != "") {
            $count = 1;
            while($entry = mysql_fetch_assoc($result)){

                # set list color
                if((1 & $count) == 1){
                    echo '<tr class="color_list1 highlight">';
                }else{
                    echo '<tr class="color_list2 highlight">';
                }

                # checkcommand name and default service name special handling
                if( ( isset($class) ) AND ($class == "checkcommand") ){
                    if ( !empty($entry["default_service_name"]) ){
                        $entry["entryname"] = $entry["default_service_name"].' ('.$entry["entryname"].')';
                    }
                }

                if( ( isset($class) ) AND ($class == "service") ){

                    echo '<td>&nbsp;<a href="detail.php?id='.$entry["id_item"].'">'.$entry["hostname"].': '.$entry["entryname"].'</a></td>';

                }else{
                    if(isset($_GET["xmode"])){
                        echo '<td>&nbsp;<a href="detail.php?id='.$entry["id_item"].'&xmode='.$entry["entryname"].'">'.$entry["entryname"].'</a></td>';
                    }else{
                        echo '<td>&nbsp;<a href="detail.php?id='.$entry["id_item"].'">'.$entry["entryname"].'</a></td>';
                    }
                }

                if(isset($_GET["xmode"])){
                    echo '<td style="text-align:center">&nbsp;<a href="modify_item.php?xmode='.$entry["entryname"].'">'.OVERVIEW_EDIT.'</a></td>';
                }else{
                    echo '<td style="text-align:center"><a href="modify_item.php?item='.$class.'&id='.$entry["id_item"].'">'.OVERVIEW_EDIT.'</a></td>';
                    echo '<td style="text-align:center"><a href="delete_item.php?item='.$class.'&ids='.$entry["id_item"].'">'.OVERVIEW_DELETE.'</a></td>';
                }

                // Advanced checkbox
                # Check cookie, if advanced tab was open, display the checkboxes too (when not, set display: none:)
                echo '<td id="advanced_box" name="advanced_box" style="text-align:center;';
                    if ( !empty($_COOKIE["advanced_box"]) AND $_COOKIE["advanced_box"] == "open" ){
                    }else{
                        echo ' display: none;';
                    }
                echo '">';
                    echo '<input type="checkbox" name="advanced_items[]" value="'.$entry["id_item"].'" style="width: 12px; height: 12px; border-style:none">';
                echo '</td>';

                echo "</tr>\n";

                $count++;
            }
        }
    }

    echo '</table>';

}

echo '</form>';



mysql_close($dbh);
require_once 'include/foot.php';

?>
