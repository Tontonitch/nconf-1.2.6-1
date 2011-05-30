<?php
require_once 'config/main.php';
require_once 'include/head.php';

//delete cache if not resent from clone
if( !empty($_SESSION["go_back_page"]) AND !preg_match('/^clone/', $_SESSION["go_back_page"]) ){
    message ($debug, 'Cleared clone cache' );
    unset($_SESSION["cache"]["clone_service"]);
}
set_page();
// Check chache
if ( isset($_SESSION["cache"]["clone_service"]) ){
    $cache = $_SESSION["cache"]["clone_service"];
}elseif( !empty($_GET["service_id"]) ){
    $cache["service_id"] = $_GET["service_id"];
}

# Fetch all hosts
$query = 'SELECT fk_id_item,attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses 
                WHERE id_attr=fk_id_attr 
                    AND naming_attr="yes" 
                    AND id_class=fk_id_class 
                    AND config_class="host" 
                ORDER BY attr_value';
$hosts = db_handler($query, "array_2fieldsTOassoc", "get all hosts");



$host_id = $_GET["id"];
$item_name = db_templates("naming_attr", $host_id);


echo '<h2>&nbsp;Clone Service from host '.$item_name.'</h2>';

echo '
<form name="clone_item" action="clone_service_write2db.php?action=clone2hosts" method="post" onsubmit="multipleSelectOnSubmit()">
  <br>
    <table>
    ';
    echo define_colgroup();
    echo '
      <tr><td valign=top>service to clone</td>
          <td>';

            echo '<input type="hidden" name="source_host_id" value="'.$host_id.'">';

            echo '<select name="service_id">';


            $services = db_templates("get_services_from_host_id", $host_id);
            foreach ($services as $service_id => $service_name){
                echo '<option value="'.$service_id.'"';
                if (!empty($cache["service_id"]) AND ($service_id == $cache["service_id"]) ) echo ' selected';
                echo '>'.$service_name.'</option>';
            }
?>

            </select>
        </td>
        <td valign="top" class="attention">*</td>
        <td></td>
      </tr>
      <tr><td valign="top">name of cloned service</td>
          <td><input name="new_service_name" type=text maxlength=250
                value="<?php if (!empty($cache["new_service_name"])) echo $cache["new_service_name"];?>"></td>
          <td valign="top" class="attention"></td>
          <td>
          </td>
      </tr>
      <tr>
      </tr>
      <tr><td valign="top"><br>clone service to
          </td>
          <td colspan=3>
            <select multiple name="all_hosts[]" id="fromBox">
            <?php
            foreach ($hosts as $host_id => $host_name){
                    echo '<option value="'.$host_id.'">'.$host_name.'</option>';
            }
            ?>
            </select>

            <select multiple name="destination_host_ids[]" id="toBox">
            </select>

            <!--</form>-->
            <script type="text/javascript">
            createMovableOptions("fromBox","toBox",500,145,'Available hosts','Selected hosts',"livesearch");
            </script>

            <!-- needed for IE7, otherwise the select will be cut on the bottom -->
            <br>

          </td>
          <td valign="top" class="attention">*</td>
          <td valign="top">You move elements by clicking on the buttons or by double clicking on select box items</td>

        </tr>

    </table>

<?php
# Tell the Session, send db query is ok (we are coming from formular)
$_SESSION["submited"] = "yes";
?>
    <div id=buttons><br><br>
    <input type="Submit" value="Submit" name="submit" align="middle">
    <input type="Reset" value="Reset">
    <?php
        // Clear button
        if ( isset($_SESSION["cache"]["clone_service"]) ){
            if ( strstr($_SERVER['REQUEST_URI'], ".php?") ){
                $clear_url = $_SERVER['REQUEST_URI'].'&clear=1&class=clone';
            }else{
                $clear_url = $_SERVER['REQUEST_URI'].'?clear=1&class=clone';
            }
            echo '<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
        }
    ?>
    </div>
</form>

<?php
mysql_close($dbh);
require_once 'include/foot.php';
?>
