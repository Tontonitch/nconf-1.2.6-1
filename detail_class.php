<?php

require_once 'config/main.php';
require_once 'include/head.php';
//set_page();

########################################
## configure user friendly names here ##
########################################
$user_friendly_names = array(
    "id_attr" 	=> "Attribute ID",
    "id_class" 	=> "Class ID",
    "attr_name"	=> "Nagios-specific attribute name",
    "config_class"	=> "Nagios-specific class name",
    "friendly_name" 	=> "Friendly name (shown in GUI)",
    "description" 	=> "description, example or help-text",
    "datatype" 	=> "Data type",
    "max_length" 	=> "max. text-field length (chars)",
    "poss_values" 	=> "Possible values",
    "predef_value" 	=> "Predefined value",
    "mandatory" 	=> "Is attribute mandatory",
    "ordering" 	=> "Ordering position",
    "nav_visible" 	=> "Is Class visible in Navigation",
    "visible" 	=> "Is attribute visible",
    "write_to_conf" 	=> "write attribute to configuration",
    "naming_attr" 	=> "naming attribute",
    "link_as_child" 	=> "link selected item(s) as children",
    "fk_show_class_items" 	=> "items of class to be assigned",
    "fk_id_class" 	=> "attribute belongs to class",
    "grouping" 	=> "Navigatino Group",
    "nav_links" 	=> "Configure Links",
    "nav_privs" 	=> "Viewable by",
    "out_file" 	    => "generated filename",
    "nagios_object" => "Nagios object definition"
);
########################################
########################################

$HTTP_referer = 'show_class.php';
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "url : ".$_SESSION["go_back_page_ok"]);
// clear cache , if not cleared
if ( isset($_SESSION["cache"]["modify_class"]) ) unset($_SESSION["cache"]["modify_class"]);


echo '<table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width=100%>';
echo '<tr><td><h2>Details</h2></td><td width=20%>';
    if(!isset($_GET["xmode"])){
        echo '<a href="delete_class.php?id='.$_GET["id"].'">'.DETAIL_DELETE.'</a>';
        echo '<a href="modify_class.php?id='.$_GET["id"].'">'.DETAIL_EDIT.'</a>';
    }
echo '</td></tr>';

echo '</table><table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="100%">';


# get entries
$query = 'SELECT * FROM ConfigClasses WHERE id_class = '.$_GET["id"];
$entries = db_handler($query, "array", "Get Details of Class");
foreach($entries[0] as $title=>$value){
    // Change the titles for more user friendly titles
    $title = strtr($title, $user_friendly_names);

    // Display the row
    echo '<tr>';
        echo '<td class="color_list2" width="200">&nbsp;'.$title.':&nbsp;</td>';
        echo '<td class="color_list1 highlight">&nbsp;'.$value.'</td>';
    echo '</tr>';
}



echo '</table>';

mysql_close($dbh);
require_once 'include/foot.php';

?>
