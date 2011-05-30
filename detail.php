<?php

require_once 'config/main.php';
require_once 'include/head.php';




// Set previous (referer) page if that actual item in detail view will be deleted
if ( empty($_SERVER["HTTP_REFERER"]) AND isset($_SESSION["after_delete_page"]) ) {
    message($debug, "referer not set, seems to be from a delete operation, go to after_delete_page");
    $from_url = $_SESSION["after_delete_page"];
}elseif ( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/detail\.php/', $_SERVER["HTTP_REFERER"]) ){
    message($debug, "detail.php matched");
    $from_url = $_SERVER["HTTP_REFERER"];
}elseif( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/modify_item\.php/', $_SERVER["HTTP_REFERER"]) ){
    # coming from editing, do still a forward to the after_delete_page
    $from_url = $_SESSION["after_delete_page"];
}elseif( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/'.preg_quote("add_item_step2.php").'/', $_SERVER["HTTP_REFERER"]) ){
    # coming from an add (entry exists), do still a forward to the after_delete_page
    $from_url = $_SESSION["after_delete_page"];
}elseif( !empty($_SERVER["HTTP_REFERER"]) ){
    message($debug, "not from detail.php or modify, setting referer");
    $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    $from_url = $_SERVER["HTTP_REFERER"];
}else{
    # direct opening of this file, or no referer, go back to overview.php
    $from_url = "index.php";
}

set_page();

$item_class = db_templates("class_name", $_GET["id"]);
$item_name = db_templates("naming_attr", $_GET["id"]);



# History Tab-View
require_once 'include/history_tab.php';


# Normal detail page
echo '<table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="500">';

echo '<tr><td width="300"><h2>Details of '.$item_class.': '.$item_name.'</h2></td><td width="200">';
echo '<div align="right">';
    if(!isset($_GET["xmode"])){
        echo '&nbsp;<a href="modify_item.php?item='.$item_class.'&id='.$_GET["id"]
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'Modify\')" onmouseout="HideFlyingObj()"'
            .'>'.ICON_EDIT.'</a>';
        echo '&nbsp;<a href="delete_item.php?item='.$item_class.'&ids='.$_GET["id"].'&from='.$from_url
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'Delete\')" onmouseout="HideFlyingObj()"'
            .'>'.ICON_DELETE.'</a>';
    }
    echo '&nbsp;<a href="history.php?item='.$item_class.'&id='.$_GET["id"].'&from='.$from_url
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'History\')" onmouseout="HideFlyingObj()"'
        .'>'.ICON_HISTORY.'</a>';
    // special links for hosts (clone, service and dependency)
    if ($item_class == "host"){
        echo '&nbsp;<a href="clone_host.php?class='.$item_class.'&id='.$_GET["id"]
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'Clone\')" onmouseout="HideFlyingObj()"'
            .'>'.ICON_CLONE.'</a>';
        echo '&nbsp;<a href="modify_item_service.php?id='.$_GET["id"]
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'Show Services\')" onmouseout="HideFlyingObj()"'
            .'>'.ICON_SERVICES.'</a>';
        echo '&nbsp;<a href="dependency.php?id='.$_GET["id"]
            .'" onmousemove="UpdateFlyingObj(event)" onmouseover="SetFlyingObj(\'Show Dependencies\')" onmouseout="HideFlyingObj()"'
            .'>'.ICON_DEPENDENCY.'</a>';
    }
echo '</div></td></tr>';
echo '</table><table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="500">';


# get basic entries
$query = 'SELECT ConfigAttrs.friendly_name,attr_value, ConfigAttrs.datatype
                        FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                        AND id_item=fk_id_item
                        AND ConfigAttrs.visible="yes" 
                        AND id_item='.$_GET["id"].'
                        ORDER BY ConfigAttrs.ordering';

$result = db_handler($query, "result", "get basic entries");
while($entry = mysql_fetch_assoc($result)){
    echo '<tr>';
        echo '<td style="vertical-align:text-top" width="150" class="color_list2">&nbsp;'.$entry["friendly_name"].':&nbsp;</td>';
        if ( $entry["datatype"] == "password" ){
            $password = show_password($entry["attr_value"]);
            // show password
            echo '<td class="color_list1 highlight">&nbsp;'.$password.'</td>';

        }else{
            // Link handling
            if ( preg_match( '/^http*/', $entry["attr_value"]) ){
                # Link
                echo '<td class="color_list1 highlight">&nbsp;<a target="_blank"href="'.$entry["attr_value"].'">'.$entry["attr_value"].'</a></td>';
            }else{
                # normal text
                echo '<td class="color_list1 highlight" style="word-break:break-all;word-wrap:break-word">&nbsp;'.$entry["attr_value"].'</td>';
            }
        }
    echo '</tr>';
}

# get linked entries
$query2 = 'SELECT ConfigAttrs.friendly_name,attr_value,fk_item_linked2 AS item_id,
                        (SELECT config_class FROM ConfigItems,ConfigClasses
                            WHERE id_class=fk_id_class AND id_item=item_id) AS config_class
                        FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                            AND id_attr=ItemLinks.fk_id_attr
                            AND ConfigAttrs.visible="yes"
                            AND fk_id_class=id_class
                            AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                            AND ItemLinks.fk_id_item='.$_GET["id"].'
                        ORDER BY
                            ConfigAttrs.friendly_name DESC,
                            ItemLinks.cust_order,
                            attr_value';

$result = db_handler($query2, "result", "get linked entries");
if ( $result AND $result != "") {
    if(mysql_num_rows($result)){
        echo '<tr><td colspan=2><br><b>This item is linked to:</b></td></tr>';
    }

    $last_fname = '';
    while($entry = mysql_fetch_assoc($result)){

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

        // group same attributes
        if($last_fname != $entry["friendly_name"]){
           $show_fname = $entry["friendly_name"].':&nbsp;';
           $bgcolor = 'class="color_list2"';
        }

        echo '<tr>';
            echo '<td '.$bgcolor.'>&nbsp;'.$show_fname.'</td>';
            if($entry["config_class"] == "service" && $item_class != "host"){
                echo '<td class="color_list1 highlight">&nbsp;<a href="detail.php?id='.$entry["item_id"].'">'.$hostname.': '.$entry["attr_value"].'</td>';
            }else{
                echo '<td class="color_list1 highlight">&nbsp;<a href="detail.php?id='.$entry["item_id"].'">'.$entry["attr_value"].'</td>';
            }
        echo '</tr>';

        $last_fname = $entry["friendly_name"];
        $show_fname = '';
        $bgcolor = '';
    }

}

# get entries linked as child
$result = db_templates("linked_as_child", $_GET["id"]);

if ( $result AND $result != "") {

    if(mysql_num_rows($result)){
        echo '<tr><td colspan=2><br><b>Child items linked:</b></td></tr>';
    }

    $last_fname = '';
    while($entry = mysql_fetch_assoc($result)){

        if($entry["config_class"] == "service"){
            $host_query = 'SELECT attr_value AS hostname
                                  FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                  WHERE fk_item_linked2=ConfigValues.fk_id_item
                                      AND id_attr=ConfigValues.fk_id_attr
                                      AND naming_attr="yes"
                                      AND fk_id_class = id_class
                                      AND config_class="host"
                                      AND ItemLinks.fk_id_item='.$entry["item_id"];

            $hostname = db_handler($host_query, "getOne", "Get linked hostnames (if service)");
        }

        // group same attributes
        if($last_fname != $entry["friendly_name"]){
           $show_fname = $entry["friendly_name"].':&nbsp;';
           $bgcolor = 'class="color_list2"';
        }

        echo '<tr>';
            echo '<td '.$bgcolor.'>&nbsp;'.$show_fname.'</td>';
            if($entry["config_class"] == "service" && $item_class != "host"){
                echo '<td class="color_list1 highlight">&nbsp;<a href="detail.php?id='.$entry["item_id"].'">'.$hostname.': '.$entry["attr_value"].'</td>';
            }else{
                echo '<td class="color_list1 highlight">&nbsp;<a href="detail.php?id='.$entry["item_id"].'">'.$entry["attr_value"].'</td>';
            }
        echo '</tr>';

        $last_fname = $entry["friendly_name"];
        $show_fname = '';
        $bgcolor = '';
    }

}


echo '</table>';





mysql_close($dbh);
require_once 'include/foot.php';

?>
