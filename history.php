<?php
require_once 'config/main.php';
require_once 'include/head.php';

set_page();


if ( !empty($_GET["id"]) ){
    # Get History of a Item
    if ( !empty($_GET["filter"]) ){
        # Query with filter attr_name
        $query = 'SELECT * FROM History WHERE
                fk_id_item='.$_GET["id"].'
                AND attr_name = "'.$_GET["filter"].'"
                ORDER BY timestamp DESC, id_hist DESC';
    }else{
        # Normal query
        $query = 'SELECT * FROM History WHERE
                fk_id_item='.$_GET["id"].'
                ORDER BY timestamp DESC, id_hist DESC';
    }

    $item_class = db_templates("class_name", $_GET["id"]);
    $item_name = db_templates("naming_attr", $_GET["id"]);

    # Set time seperation (empty row after time-change)
    $time_seperation = TRUE;

    # Set title
    $title = '<b>History of '.$item_class.': '.$item_name.'</b>';
    # Expand the titel with filter
    if ( !empty($_GET["filter"]) ) $title .= '<br>--> filtered for <i>'.$_GET["filter"].'</i>';
}else{
    # Get general History entries with status: created and removed and general
    $query = 'SELECT * FROM History WHERE'
        . ' (action="created"'
        . ' OR action="removed"'
        . ' OR action="general" )'
        . ' OR (action ="modified"'
            . ' AND (attr_name = "Class" OR attr_name = "Attribute"))'
        . ' ORDER BY timestamp DESC';

    # Set title
    $title      = 'Basic History';
    $subtitle   = TXT_HISTORY_SUBTITLE;
    $time_seperation = FALSE;
}


# Content
echo '<table class="simpletable">';
echo '<tr><td colspan=5><h2>'.$title.'</h2>';
if ( !empty($subtitle) ){
    echo $subtitle;
}
echo '</td></tr>';
echo '<tr><td height="20" colspan=5></td></tr>';
echo '<tr class="bg_header_2">';
    echo '<td width="110">When</td>
            <td width="90">Who</td>
            <td width="70">Action</td>
            <td width="100">Object</td>
            <td width="200">Value</td>';
echo '</tr>';

$result = db_handler($query, 'result', "get history entries");
if (mysql_num_rows($result) == 0){
    echo "<tr><td colspan=5>no history data found</td></tr>";
}else{
    while($entry = mysql_fetch_assoc($result)){
        if ( !empty($timestamp_previouse_entry) AND $timestamp_previouse_entry > $entry["timestamp"]) {
            # Place a empty row for visual seperation of actions
            if ($time_seperation) echo '<tr height="3"><td class="color_list3" colspan=5></td></tr>';
            $timestamp = $entry["timestamp"];
        }elseif( !empty($timestamp_previouse_entry) ){
            $timestamp = " ";
        }else{
            $timestamp = $entry["timestamp"];
        }
        echo '<tr>';
            echo '<td style="vertical-align:text-top" class="color_list2">'.$timestamp.'</td>';
            echo '<td style="vertical-align:text-top" class="color_list2">'.$entry["user_str"].'</td>';
            echo '<td style="vertical-align:text-top" class="color_list2">'.$entry["action"].'</td>';
            echo '<td style="vertical-align:text-top" class="color_list2">';
                if ( !empty($_GET["id"]) ){
                    echo '<a href="history.php?id='.$_GET["id"].'&filter='.$entry["attr_name"].'">'.$entry["attr_name"].'</a>';
                }else{
                    echo $entry["attr_name"];
                }
                echo '</td>';
            # if object name contains password, and PASSWD_DISPLAY is 0 (made in called function), do not show password
            if ( stristr($entry["attr_name"], "password") ){
                $entry["attr_value"] = show_password($entry["attr_value"]);
            }
            echo '<td class="highlight color_list1" style="vertical-align:text-top;word-break:break-all;word-wrap:break-word">'.$entry["attr_value"].'</td>';
        echo '</tr>';

        # save timestampt for compare with next entry
        $timestamp_previouse_entry = $entry["timestamp"];
    }

}




echo '</table>';

mysql_close($dbh);
require_once 'include/foot.php';

?>
