<?php
require_once 'config/main.php';
require_once 'include/head.php';

?>
<h2>Delete Items</h2><br>

<?php


if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

if ( !empty($_GET["class"]) ){
    $class = $_GET["class"];
}elseif( !empty($_GET["item"]) ){
    $class = $_GET["item"];
}

# delete item count
$deleted_items = 0;


if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["ids"]) ) AND ($_POST["ids"] != "") )
  ){

    # make ids as array
    if ( !empty($_POST["ids"]) ){
        $ids = explode(",", $_POST["ids"]);
    }

    foreach ($ids as $id){

        $item_name  = db_templates("naming_attr", $id);
        $item_class = db_templates("class_name", $id);

        # Delete Services if item = host
        if ($item_class == "host"){

            // Select all linked services
            $query = 'SELECT id_item
                        FROM ConfigItems, ConfigClasses, ItemLinks
                        WHERE fk_id_class = id_class
                        AND config_class = "service"
                        AND fk_id_item = id_item
                        AND fk_item_linked2='.$id
                     ;
            $result = db_handler($query, "result", "Select all linked services");
            if ($result){
                message ($debug, 'selected: '.mysql_num_rows($result), "ok" );
                if ( mysql_num_rows($result) > 0 ){
                    while ($item_ID = mysql_fetch_row($result)){
                        $query = 'DELETE FROM ConfigItems
                                    WHERE id_item='.$item_ID[0]
                                 ;
                        message ($debug, $query, "query");
                        if (DB_NO_WRITES != 1) {
                            if ( mysql_query($query) ){
                                message ($debug, '', "ok");
                            }else{
                                message ($debug, '', "failed");
                            }
                        }
                    } // END while
                }


            }else{
                message ($debug, '', "failed");
            }    

        } //END $item_class == "host"


        # Services: Bevore deleting the entry, check the host ID, for later history entry
        if ($class == "service"){
            $Host_ID = db_templates("hostID_of_service", $id);
        }

        // Delete entry
        $query = 'DELETE FROM ConfigItems
                    WHERE id_item='.$id;

        $result = db_handler($query, "result", "Delete entry");
        if ( $result ){
            # increase deleted items
            if (mysql_affected_rows() > 0){
                $deleted_items++;
            }

            message ($debug, '', "ok");

            # Special service handling
            if ( ($class == "service") AND !empty($Host_ID) ){
                # Enter also the Host_ID of the deleted service into the History table
                history_add("removed", $class, $item_name, $Host_ID);
            }else{
                # Enter normal deletion, which object is deleted, without a "parent / linked" id
                history_add("removed", $class, $item_name );
            }

            // Go to next page without pressing the button (also have a look if delete comes from detailview
            if ( isset($_POST["from"]) AND ($_POST["from"] != "") ){
                $url = $_POST["from"];
            }else{
                $url = $_SESSION["after_delete_page"];
            }
                
        }elseif (DB_NO_WRITES != 1){
            message($error, 'Error when deleting '.$id.':'.$query);
        }
    

    } // foreach

    # Feedback of delete action
    if ($deleted_items > 0){
        echo TXT_DELETED.' '.$deleted_items.' item(s)<br>';
    }else{
        echo 'No item(s) deleted';
    }

    if ( empty($error) ){
        echo '<meta http-equiv="refresh" content="'.(REDIRECTING_DELAY+1).'; url='.$url.'">';
        message($info, '...redirecting to <a href="'.$url.'">page</a> in '.(REDIRECTING_DELAY+1).' seconds...');
    }



}elseif( ( isset($_GET["ids"]) ) AND ($_GET["ids"] != "") ){

    // Go to next page without pressing the button (also have a look if delete comes from detailview
    if ( isset($_GET["from"]) AND ($_GET["from"] != "") ){
        $url = $_GET["from"];
    }elseif ( !empty($_SESSION["after_delete_page"]) ){
        $url = $_SESSION["after_delete_page"];
    }
    # this could be buggy:
    #elseif ( !empty($_SESSION["go_back_page_ok"]) ){
    #    $url = $_SESSION["go_back_page_ok"];
    #}

    # make ids as array
    if ( !empty($_GET["ids"]) ){
        $ids = explode(",", $_GET["ids"]);
    }

    foreach ($ids as $id ){
        // CHECK IF A USER TRIES TO DELETE AN ADMIN
        if ( !empty($id) ) $nc_id = $id;
        if ($_SESSION["group"] != "admin" AND $class == "contact"){
            $nc_permission_query = 'SELECT attr_value FROM ConfigValues, ConfigAttrs WHERE fk_id_attr=id_attr AND fk_id_item="'.$nc_id.'" AND attr_name = "nc_permission"';
            $nc_permission = db_handler($nc_permission_query, "getOne", "Look for nc_permissions of user");
            if ($nc_permission == "admin"){
                // Disable the submit button and add message
                include('include/stop_user_modifying_admin_account.php');
            }
            
        }

    }


    # Modify question when deleting services
    if ($class == "service"){
        $question = "Do you really want to delete these services? The hosts will not be deleted.";
    }else{
        $question = "Do you really want to delete these item(s) ?";
    }



    # Fetch item name
echo '    
  <table width=410 cellspacing=0 cellpadding=0>
    <tr>
        <td width=250>'.$question.'</td>
    </tr>
    ';

    ?>

    <tr>
        <td>
          <form name="delete_item" action="delete_item.php<?php echo "?item=".$class;?>" method="post">
            <input type="hidden" name="ids" value="<?php echo $_GET["ids"];?>">
            <input type="hidden" name="from" value="<?php echo $url;?>">
            <input type="hidden" name="delete" value="yes">
            <div id=buttons><br><br>
                <input type="Submit" value="Delete" name="submit" align="middle" <?php if(DB_NO_WRITES == 1) echo "disabled";?> >&nbsp;
                <!-- <input type="Button" value="Cancel" name="cancel" align="middle" onClick="javascript:history.back()"> -->
                <input type=button onClick="window.location.href='<?php echo $_SESSION["go_back_page"]; ?>'" value="Back">
                <br>
                <br>
            </div>
            <?php
            if(DB_NO_WRITES == 1) echo "<br>$info";
            ?>
          </form>
        </td>
    </tr>
 </table>
 <?php

    if ( !empty($_GET["ids"]) ){
        $ids = explode(",", $_GET["ids"]);
    }

    foreach ($ids as $id) {

        # Delete Services if item = host
        $item_class = db_templates("class_name", $id);

        if ($item_class == "host"){

            # WARN services linked to host
            $get_srv_query = 'SELECT attr_value, ConfigValues.fk_id_item AS item_id,"service" AS config_class,
                            "service name" AS friendly_name
                            FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                            WHERE id_attr = ConfigValues.fk_id_attr
                            AND naming_attr = "yes"
                            AND id_class = fk_id_class
                            AND config_class = "service"
                            AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                            AND fk_item_linked2 = '.$id.'
                            ORDER BY attr_value';

            $result = db_handler($get_srv_query, "result", "get services linked to host");
            # prepare services
            $services = array();
            while($entry = mysql_fetch_assoc($result)){
                $services[] = array(
                            "id" => $entry["item_id"],
                            "name" => $entry["attr_value"],
                            "type" => "service" ) ;

            }


        }

        # Lookup class and name of item
        $item_class = db_templates("class_name", $id);
        $item_name  = db_templates("naming_attr", $id);

        # on service items we want to group it by their associated hostname
        if ($item_class == "service"){
            # service deletions

            # get host name of service
            $hostID   = db_templates("hostID_of_service", $id);
            $hostname = db_templates("naming_attr", $hostID);
            # create hostname entrie
            if ( !isset($entries[$hostname]) ){
                $entries[$hostname] = array(
                            "id" => $hostID,
                            "name" => $hostname,
                            "title" => "",
                            "status" => "open" );
            }

            # add the service to the host branch
            $services = array(
                        "id" => $id,
                        "name" => $item_name,
                        "type" => "service" ) ;
            $entries[$hostname]["childs"][] = $services;
        }else{
            # any other deletion:
            if (!empty($services) ){
                # for host trees with services (class = host)
                $entries[] = array(
                                "id" => $id,
                                "name" => $item_name,
                                "title" => $item_class.": ",
                                "status" => "open",
                                "childs" => $services );
            }else{
                # for single (any other) classes
                $entries[] = array(
                                "id" => $id,
                                "name" => $item_name,
                                "title" => $item_class.": ",
                                "type" => $item_class);
            }
        }

    }//foreach

    # service deletion: sort the array on hostnames
    ksort($entries);

    # Modify text on top tree element (root) when deleting services
    if ($item_class == "service"){
        $items = "services";
    }else{
        $items = "items";
    }
    $tree_view = array( "root" => array(
                        "id" => "root",
                        "status" => 'open',
                        "name" => "The following $items will be deleted",
                        "type" => "parent",
                        "childs" => $entries) );

    # display tree
    echo '<br><div>';
        displayTree_list($tree_view);
    echo '</div>';



}else{
    message($error, "No item to delete");
    echo $error;

}

?>


<?php

mysql_close($dbh);

require_once 'include/foot.php';
?>
