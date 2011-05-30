<?php
# History tab view (in detail.php)
?>

<div class="history">
    <div class="accordion">
    <a href="javascript:swap_visible('history')">
        <h2 class="header"><span>
        <img src="img/icon_expand.gif" id="swap_icon_history" >
        History</span></h2>
    </a>
        <?php


if ( !empty($_GET["id"]) ){
    # Normal query
    $query = 'SELECT timestamp, action, attr_name FROM History WHERE
            fk_id_item='.$_GET["id"].'
            ORDER BY timestamp DESC, id_hist DESC
            LIMIT '.HISTORY_TAB_LIMIT.';';
    if ( !empty($_GET["filter"]) ) $title .= '<br>--> filtered for <i>'.$_GET["filter"].'</i>';
}


# Content
echo '<table id="history" style="position: relative; width: 100%; left: 0px; display:none">';

$result = db_handler($query, 'result', "get history entries");
if (mysql_num_rows($result) == 0){
    echo '<tr class="box_content"><td colspan=3>no history data found</td></tr>';
}else{
    echo '<tr class="box_content">
            <td colspan=2>Last '.HISTORY_TAB_LIMIT.' changes:</td>
            <td>
                <div align="right">
                    <a href="history.php?id='.$_GET["id"].'">show all changes</a>
                </div>
            </td>
          </tr>';
    echo '<tr class="bg_header_2">';
        echo '<td>When</td>
                <td>Action</td>
                <td>Object</td>';
    echo '</tr>';
    while($entry = mysql_fetch_assoc($result)){
        if ( !empty($timestamp_previouse_entry) AND $timestamp_previouse_entry > $entry["timestamp"]) {
            echo '<tr><td class="color_list3" colspan=3></td></tr>';
            $timestamp = $entry["timestamp"];
        }elseif( !empty($timestamp_previouse_entry) ){
            $timestamp = " ";
        }else{
            $timestamp = $entry["timestamp"];
        }

        # Remove time from date
        $timestamp_arr = explode(' ', $timestamp);
        $timestamp = $timestamp_arr[0];

        echo '<tr>';
            echo '<td style="vertical-align:text-middle" class="color_list2">&nbsp;'.$timestamp.'</td>';
            //echo '<td style="vertical-align:text-top" class="color_list2">&nbsp;'.$entry["user_str"].'</td>';
            echo '<td style="vertical-align:text-top" class="color_list2">&nbsp;'.$entry["action"].'</td>';
            echo '<td style="vertical-align:text-top" class="color_list2">';
                if ( !empty($_GET["id"]) ){
                    echo '&nbsp<a href="history.php?id='.$_GET["id"].'&filter='.$entry["attr_name"].'">'.$entry["attr_name"].'</a>';
                }else{
                    echo $entry["attr_name"];
                }
                echo '</td>';
            //echo '<td style="vertical-align:text-top" class="color_list1">&nbsp;'.$entry["attr_value"].'</td>';
        echo '</tr>';

        # save timestampt for compare with next entry
        $timestamp_previouse_entry = $entry["timestamp"];
    }

}

echo '</table>';


# Close tab:
?>

    </div>
</div>
<?php



