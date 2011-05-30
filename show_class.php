<?php

require_once 'config/main.php';
require_once 'include/head.php';

// Form action and url handling
$request_url = set_page();



// Page output begin

echo '<h2 style="margin-right:4px">Show classes:</h2>';

?>

<table width=550 border=0 frame="box" rules=none cellspacing=0 cellpadding=6>
  <tr class="box_content"><td class="bg_header"><b>WARNING</b></td></tr>
  <tr class="box_content"><td class="box_content" valign=top><b>This mask allows administrators to modify the data schema of the NConf application. There is no need to make any changes to the schema for ordinary opration.<br>
Users are strictly discouraged from changing any attribute names, datatypes, from modifying classes in any way, and from any other changes to the schema.<br>
Disregarding this may result in unexpected behavour of the tool, failure to generate the Nagios configuration properly and may result in COMPLETE DATA CORRUPTION OR LOSS!<br>Make sure you know what you're doing!</b></td></tr>
</table>

<?php
    // Attr manipulation
    if ( isset($_GET["do"]) ){
        if ($_GET["do"] == "up"){
            class_order($_GET["id"], "up");
        }elseif($_GET["do"] == "down"){
            class_order($_GET["id"], "down");
        }
            
    }



// for user and admin navigation
$nav_tree = array("user", "admin");
foreach ($nav_tree as $nav_priv) {
    echo "<b>&nbsp;&nbsp;".ucfirst($nav_priv)." classes:</b>";
    echo '<table class="noneborder simpletable bordertop" style="min-width:480px">';

        $query = 'SELECT * FROM ConfigClasses WHERE nav_privs = "'.$nav_priv.'" ORDER BY grouping, ordering ASC, config_class';
        $result = db_handler($query, "result", "ConfigClasses");

        if ($result) {

            echo '<tr class="bg_header">';
                echo '<td width=150>&nbsp;<b>Class Name</b></td>';
                echo '<td width=160>&nbsp;<b>Friendly Name</b></td>';
                echo '<td width=70 style="text-align:center">&nbsp;<b>Visible</b></td>';
                echo '<td width=60 colspan=2>&nbsp;<b>Ordering</b></td>';
                echo '<td width=40 style="text-align:center"><b>Edit</b></td>';
                echo '<td width=40 style="text-align:center"><b>Delete</b></td>';
            echo "</tr>";

            // Define here , how much td's there are (for colspans needed later)
            // Attention, also check tds with colspans !
            $colspan = 7;


            $count = 1;
            $naming_attr_count = 0;
            $group_bevore = '';
            while($entry = mysql_fetch_assoc($result)){
                $row_warn = 0;
                $pre = "&nbsp;";
                $fin = "";
                $naming_attr_cell = "&nbsp;";

                // Show visible icons 
                switch ($entry["nav_visible"]){
                    case "yes":
                        $ICON_mandatory = SHOW_ATTR_YES;
                    break;
                    case "no":
                        $ICON_mandatory = SHOW_ATTR_NO;
                    break;
                }

                // Make a space between groups
                if ($count == 1){
                    # User or Admin group begins
                    if (empty($entry["grouping"]) AND $nav_priv == "user" ){
                        $Group = TXT_MENU_BASIC;
                    }elseif (empty($entry["grouping"]) AND $nav_priv == "admin" ){
                        $Group = TXT_MENU_ADDITIONAL;
                    }
                    
                }elseif ( $entry["grouping"] != $group_bevore){
                    # New sub-group
                    $Group = $entry["grouping"];
                    $count = 1;

                    # Close old group
                    echo '<tr>
                            <td colspan='.$colspan.' class="color_group_border_close">';
                                echo '&nbsp;
                            </td>
                          </tr>
                    ';
                }
                if ($count == 1){
                    # Make titlebox
                    echo '<tr>
                            <td colspan='.$colspan.' style="border-bottom:0px">&nbsp;</td>
                          </tr>
                    ';
                    echo '
                      <tr class="color_group_border highlight">';
                    echo '
                        <td colspan='.$colspan.'>
                            <b>'.$Group.'</b>
                        </td>
                      </tr>';
                }

                $group_bevore = $entry["grouping"];

                // set list color
                if ($row_warn == 1){
                    echo '<tr class="color_warning highlight">';
                }elseif((1 & $count) == 1){
                    echo '<tr class="color_list1 highlight">';
                }else{
                    echo '<tr class="color_list2 highlight">';
                }


                echo '<td class="color_group_border_left">';
                    echo $pre.'<a href="detail_class.php?id='.$entry["id_class"].'">'.$entry["config_class"].'</a>'.$fin;
                echo '</td>';
                echo '<td>'.$pre.'<a href="detail_class.php?id='.$entry["id_class"].'"><b>'.$entry["friendly_name"].'</b></a>'.$fin.'</td>';
                echo '<td align="center"><div align=center>'.$ICON_mandatory.'</div></td>';
                echo '<td>'.$pre.'<a href="show_class.php?id='.$entry["id_class"].'&do=up">'.SHOW_ATTR_UP.'</a>'.$fin.'</td>';
                echo '<td>'
                        .$pre.'<a href="show_class.php?id='.$entry["id_class"].'&do=down">'.SHOW_ATTR_DOWN.'</a>'.$fin.
                     '</td>';
                echo '<td style="text-align:center">&nbsp;<a href="modify_class.php?id='.$entry["id_class"].'">'.OVERVIEW_EDIT.'</a></td>';
                echo '<td style="text-align:center" class="color_group_border_right">&nbsp;<a href="delete_class.php?id='.$entry["id_class"].'">'.OVERVIEW_DELETE.'</a></td>';


                echo "</tr>\n";
                
                $count++;
            }

            echo '<tr>
                            <td colspan='.$colspan.' class="color_group_border_top_bottom">&nbsp;</td>
                          </tr>
                ';

        }


    echo '</table>';

} // End of nav_tree


mysql_close($dbh);
require_once 'include/foot.php';

?>
