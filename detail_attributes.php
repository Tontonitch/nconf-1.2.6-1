<?php

require_once 'config/main.php';
require_once 'include/head.php';
//set_page();

########################################
## configure user friendly names here ##
########################################
$user_friendly_names = array(
    "id_attr" 	=> "Attribute ID",
    "attr_name"	=> "Nagios-specific attribute name",
    "friendly_name" 	=> "Friendly name (shown in GUI)",
    "description" 	=> "description, example or help-text",
    "datatype" 	=> "Data type",
    "max_length" 	=> "max. text-field length (chars)",
    "poss_values" 	=> "Possible values",
    "predef_value" 	=> "Predefined value",
    "mandatory" 	=> "Is attribute mandatory",
    "ordering" 	=> "Ordering position",
    "visible" 	=> "Is attribute visible",
    "write_to_conf" 	=> "write attribute to configuration",
    "naming_attr" 	=> "naming attribute",
    "link_as_child" 	=> "link selected item(s) as children",
    "fk_show_class_items" 	=> "items of class to be assigned",
    "fk_id_class" 	=> "attribute belongs to class",
);
########################################
########################################

$HTTP_referer = 'show_attr.php?class='.$_GET["class"];
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "url : ".$_SESSION["go_back_page_ok"]);


echo '<table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="100%">';

echo '<tr><td><h2>Details</h2></td><td width="20%">';
    if(!isset($_GET["xmode"])){
        echo '<a href="delete_attr.php?id='.$_GET["id"].'">'.DETAIL_DELETE.'</a>';
        echo '<a href="modify_attr.php?id='.$_GET["id"].'">'.DETAIL_EDIT.'</a>';
    }
    //echo '<a href="history.php?item='.$item_class.'&id='.$_GET["id"].'&from='.$from_url.'">'.DETAIL_HISTORY.'</a>';
echo '</td></tr>';
echo '</table><table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="100%">';





# get basic entries
$query = 'SELECT
        id_attr, attr_name, ConfigAttrs.friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ConfigAttrs.ordering, visible, write_to_conf, naming_attr, link_as_child, 
        (SELECT ConfigClasses.config_class FROM ConfigClasses WHERE id_class= fk_show_class_items) AS fk_show_class_items,
        ConfigClasses.config_class AS fk_id_class
            FROM ConfigAttrs, ConfigClasses
            WHERE id_attr ='.$_GET["id"].'
            AND fk_id_class = ConfigClasses.id_class';
$entries = db_handler($query, "array", "Get Details of Attribute");

foreach($entries[0] as $title=>$value){
    // Change the titles for more user friendly titles
    $title = strtr($title, $user_friendly_names);

    // Display the row
    echo '<tr>';
        echo '<td class="color_list2" style="vertical-align:text-top" width="200">&nbsp;'.$title.':&nbsp;</td>';
        echo '<td class="color_list1 highlight" style="word-break:break-all;word-wrap:break-word">&nbsp;'.$value.'</td>';
    echo '</tr>';
}



echo '</table>';

mysql_close($dbh);
require_once 'include/foot.php';

?>
