<?php

require_once 'config/main.php';
require_once 'include/head.php';

// Form action and url handling
$request_url = set_page();

// Delete Cache of modify (if still exist)
if ( isset($_SESSION["cache"]["modify_attr"]) ) unset($_SESSION["cache"]["modify_attr"]);

// set info in the footer when naming attribute changes have done (from modify attribute)
if ( isset($_GET["naming_attr"]) ){
    if ($_GET["naming_attr"] == "changed"){
        message($info, TXT_NAMING_ATTR_CHANGED);
    }elseif ($_GET["naming_attr"] == "last"){
        message($info, TXT_NAMING_ATTR_LAST);
    }

    // Remove it from url, so that formular dont takes this get variable all around
    $request_url = ereg_replace("&naming_attr=last", "", $request_url);
    $request_url = ereg_replace("&naming_attr=changed", "", $request_url);
}




# Filters


if ( isset($_POST["os"]) ) {
    $filter_os = $_POST["os"];
}else{
    $filter_os = "";
}



# Show class selection
$show_class_select = "yes";


//if ( isset($_POST["filter1"]) ) {
//    $class = $_POST["filter1"];
//}elseif ( isset($_GET["class"]) ) {
if ( isset($_GET["class"]) ) {
    $class = $_GET["class"];
}else{
    $class = "host";
}



// Page output begin


echo '<h2 style="margin-right:4px">Show attributes: '.$class.'</h2>';
?>

<table width=550 border=0 frame="box" rules=none cellspacing=0 cellpadding=6>
  <tr class="box_content"><td class="bg_header"><b>WARNING</b></td></tr>
  <tr class="box_content"><td class="box_content" valign=top><b>This mask allows administrators to modify the data schema of the NConf application. There is no need to make any changes to the schema for ordinary opration.<br>
Users are strictly discouraged from changing any attribute names, datatypes, from modifying classes in any way, and from any other changes to the schema.<br>
Disregarding this may result in unexpected behavour of the tool, failure to generate the Nagios configuration properly and may result in COMPLETE DATA CORRUPTION OR LOSS!<br>Make sure you know what you're doing!</b></td></tr>
</table>

<form name="filter" action="<?php echo $request_url; ?>" method="get">
<table>
<?php

// Class Filter
if ( isset($show_class_select) ){
    echo '<tr>';
        echo '<td>Class</td>';

        $result = db_handler('SELECT config_class FROM ConfigClasses ORDER BY config_class', "result", "Get Config Classes");

    echo '</tr>';
    echo '<tr>';
        echo '<td><select name="class" style="width:192px" onchange="document.filter.submit()">';
        //echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        while($row = mysql_fetch_row($result)){
            echo "<option value=$row[0]";
            if ( (isset($class) ) AND ($row[0] == $class) ) echo " SELECTED";
            echo ">$row[0]</option>";
        }

        echo '</select>&nbsp;&nbsp;</td>';
    //echo '</tr>';
}


    // submit button
    echo '<td align=right id=buttons>&nbsp;&nbsp;<input type="submit" value="Show" name="submiter" align="middle">';
    # because we now have onchange event, Reset option is obsolet:
    //echo '&nbsp;&nbsp;<input type="Reset" value="Reset">';

    // Clear button
    if ( isset($_SESSION["cache"]["searchfilter"]) ){
        if ( strstr($_SERVER['REQUEST_URI'], ".php?") ){
            $clear_url = $_SERVER['REQUEST_URI'].'&clear=1';
        }else{
            $clear_url = $_SERVER['REQUEST_URI'].'?clear=1';
        }
        echo '&nbsp;&nbsp;<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
    }

    echo "</td>";
echo "</tr>";

?>

</table>
</form>




<h3>&nbsp;Overview</h3>

<?php

    // Attr manipulation
    if ( isset($_GET["do"]) ){
        if ($_GET["do"] == "up"){
            attr_order($_GET["id"], "up");
        }elseif($_GET["do"] == "down"){
            attr_order($_GET["id"], "down");
        }
            
    }




    echo '<table class="noneborder simpletable bordertop" style="min-width:480px">';


        $query = 'SELECT ConfigAttrs.friendly_name, ConfigAttrs.ordering, id_attr, attr_name, datatype, mandatory, naming_attr
                FROM ConfigAttrs,ConfigClasses
                    WHERE id_class=fk_id_class
                    AND config_class="'.$class.'"
                    ORDER BY ConfigAttrs.ordering
        ';

        $result = db_handler($query, "result", "get attributes from class");

        if ($result != "") {

            echo '<tr class="bg_header">';
                echo '<td width=30>&nbsp;</td>';
                echo '<td width=150>&nbsp;<b>Attribute Name</b></td>';
                echo '<td width=160>&nbsp;<b>Friendly Name</b></td>';
                echo '<td width=100>&nbsp;<b>Datatype</b></td>';
                echo '<td width=70>&nbsp;<b>Mandatory</b></td>';
                echo '<td width=60 colspan=2>&nbsp;<b>Ordering</b></td>';
                echo '<td width=60 style="text-align:center"><b>Naming<br>Attribute</b></td>';
                //echo '<td width=40 style="text-align:center"><b>Distribute<br>a Value</b></td>';
                echo '<td width=40 style="text-align:center"><b>Edit</b></td>';
                echo '<td width=40 style="text-align:center"><b>Delete</b></td>';

            echo "</tr>";




            $count = 1;
            $naming_attr_count = 0;
            while($entry = mysql_fetch_assoc($result)){
                $row_warn = 0;
                if ($entry["naming_attr"] == "yes"){
                    $naming_attr_count++;
                    $pre = "&nbsp;<b>";
                    $fin = "</b>";
                    $naming_attr_cell = SHOW_ATTR_NAMING_ATTR;
                    if ($naming_attr_count > 1){
                        $row_warn = 1;
                        message($info, TXT_NAMING_ATTR_CONFLICT);
                        $naming_attr_cell .= SHOW_ATTR_NAMING_ATTR_CONFLICT;
                    }
                }else{
                    $pre = "&nbsp;";
                    $fin = "";
                    $naming_attr_cell = "&nbsp;";
                }

                // Show datatype icons 
                switch ($entry["datatype"]){
                    case "text":
                        $ICON_datatype = SHOW_ATTR_TEXT;
                    break;
                    case "password":
                        $ICON_datatype = SHOW_ATTR_PASSWORD;
                    break;
                    case "select":
                        $ICON_datatype = SHOW_ATTR_SELECT;
                    break;
                    case "assign_one":
                        $ICON_datatype = SHOW_ATTR_ASSIGN_ONE;
                    break;
                    case "assign_many":
                        $ICON_datatype = SHOW_ATTR_ASSIGN_MANY;
                    break;
                }

                // Show mandatory icons 
                switch ($entry["mandatory"]){
                    case "yes":
                        $ICON_mandatory = SHOW_ATTR_YES;
                    break;
                    case "no":
                    default:
                        $ICON_mandatory = SHOW_ATTR_NO;
                    break;
                }


                // set list color
                if ($row_warn == 1){
                    echo '<tr class="color_warning highlight">';
                }elseif((1 & $count) == 1){
                    echo '<tr class="color_list1 highlight">';
                }else{
                    echo '<tr class="color_list2 highlight">';
                }


                
                echo '<td>'.$ICON_datatype.'</td>';

                echo '<td>'.$pre.'<a href="detail_attributes.php?class='.$class.'&id='.$entry["id_attr"].'">'.$entry["attr_name"].'</a>'.$fin.'</td>';
                echo '<td>'.$pre.$entry["friendly_name"].$fin.'</td>';
                echo '<td>'.$pre.$entry["datatype"].$fin.'</td>';
                echo '<td align="center"><div align=center>'.$ICON_mandatory.'</div></td>';
                //echo '<td>'.$pre.$entry["mandatory"].$fin.'</td>';
                // Ordering is good for debbuging
                //echo '<td>'.$pre.$entry["ordering"].$fin.'</td>';
                echo '<td>'.$pre.'<a href="show_attr.php?class='.$class.'&id='.$entry["id_attr"].'&do=up">'.SHOW_ATTR_UP.'</a>'.$fin.'</td>';
                echo '<td>'.$pre.'<a href="show_attr.php?class='.$class.'&id='.$entry["id_attr"].'&do=down">'.SHOW_ATTR_DOWN.'</a>'.$fin.'</td>';
                echo '<td align="center"><div align=center>'.$naming_attr_cell.'</div></td>';
                echo '<td style="text-align:center">&nbsp;<a href="modify_attr.php?id='.$entry["id_attr"].'">'.OVERVIEW_EDIT.'</a></td>';
                echo '<td style="text-align:center">&nbsp;<a href="delete_attr.php?id='.$entry["id_attr"].'">'.OVERVIEW_DELETE.'</a></td>';
                echo "</tr>\n";

                $count++;
            }
            
            // Warn if there is no naming attribute
            if ($naming_attr_count == 0){
                message($info, TXT_NAMING_ATTR_MISSED);
            }

        }


    echo '</table>';



mysql_close($dbh);
require_once 'include/foot.php';

?>
