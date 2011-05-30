<?php
require_once 'config/main.php';
require_once 'include/head.php';
set_page();


# go to show attribute page, when modify was ok
$HTTP_referer = 'show_class.php';
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "set go_back_page_ok : ".$_SESSION["go_back_page_ok"]);


if(isset($_GET['id'])){
  $id = $_GET['id'];
  $title = "Modify class";
}else{
  $id = "new";
  $title = "Add class";
}

if ($id != "new"){
    # get entries
    $query = 'SELECT * FROM ConfigClasses WHERE id_class="'.$id.'"';
    $class_entry = db_handler($query, "assoc", "get entries");

    $old_grouping = $class_entry["grouping"];
    $old_nav_privs = $class_entry["nav_privs"];

}else{
    # new entry does not have predefined values like the modify part
    $class_entry = array("nav_visible" => "",
                        "config_class" => "",
                        "friendly_name" => "",
                        "grouping" => "",
                        "nav_privs" => "",
                        "nav_links" => "",
                        "out_file" => "",
                        "nagios_object" => "",
                        "ordering" => "");
    $old_grouping = '';
    $old_nav_privs = '';
}

# new group must be defined in both scenarios
$class_entry["new_group"] = "";


# Check cache
if ( isset($_SESSION["cache"]["modify_class"][$id]) ){
    # Cache
    foreach ($_SESSION["cache"]["modify_class"][$id] as $key => $value) {
        $class_entry[$key] = $value;
    }
}


?>
    <h2><?php echo $title;?></h2><br>
    <form name="form1" action="modify_class_write2db.php" method="post" onreset="check_input()">
    <input type=hidden name="class_id" value="<?php echo $id; ?>">
    <input type=hidden name="ordering" value="<?php echo $class_entry["ordering"]; ?>">
    <input type=hidden name="old_group" value="<?php echo $old_grouping; ?>">
    <input type=hidden name="old_nav_privs" value="<?php echo $old_nav_privs; ?>">
        <table width="100%" border=0>
            <tr><td width="150">class name</td>
                <td width="270"><input type=text name=config_class maxlength=60 value="<?php echo $class_entry["config_class"]; ?>"></td>
                <td width="20" class="attention">*</td>
                <td> </td>
            </tr>
            <tr><td>friendly name (displayed)</td>
                <td><input type=text name=friendly_name maxlength=80 value="<?php echo $class_entry["friendly_name"]; ?>"></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>visible in navigation?</td>
                <td>
                    <select name="nav_visible" id="nav_visible" style="width:60px" onchange="check_input()">
                        <option <?php if($class_entry["nav_visible"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($class_entry["nav_visible"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td class="attention"></td>
            </tr>
            <tr><td>show in user or admin section</td>
                <td>
                    <select name=nav_privs style="width:60px" onchange="check_input()">
                        <option <?php if($class_entry["nav_privs"] == "admin") echo " selected"; ?> >admin</option>
                        <option <?php if($class_entry["nav_privs"] == "user") echo " selected"; ?> >user</option>
                    </select>
                </td>
                <td class="attention"></td>
            </tr>
            <tr><td>select user group</td>
                <td><select name="selectusergroup" onchange="check_input()">
<?php
    $query = mysql_query("SELECT grouping FROM ConfigClasses WHERE nav_privs = 'user' AND grouping != '' GROUP BY grouping ORDER BY grouping");
    echo '<option value=""></option>';
    while($entry = mysql_fetch_row($query)){
        echo '<option value="'.$entry[0].'"';
            if ( $entry[0] == $class_entry["grouping"] ) echo " selected";
        echo " >$entry[0]</option>";
    }
?>
                </select></td>
                <td class="attention"></td>
            </tr>
            <tr>
                <td>select admin group</td>
                <td><select name="selectadmingroup" onchange="check_input()">
<?php
    $query = mysql_query("SELECT grouping FROM ConfigClasses WHERE nav_privs = 'admin' AND grouping != '' GROUP BY grouping ORDER BY grouping");
    echo '<option value=""></option>';
    while($entry = mysql_fetch_row($query)){
        echo '<option value="'.$entry[0].'"';
            if ( $entry[0] == $class_entry["grouping"] ) echo " selected";
        echo " >$entry[0]</option>";
    }
?>
                </select></td>
                <td class="attention"></td>
            </tr>
            <tr>
                <td>or define a new group</td>
                <td><input type=text name=new_group maxlength=30 value="<?php echo $class_entry["new_group"]; ?>" onkeyup="check_input()"></td>
                <td class="attention"></td>
            </tr>
            <?php
            // Only display the navigation link string when modifying
            if ($id != "new"){
                echo '<tr><td>navigation link string</td>
                        <td colspan=3><input type=text name=nav_links maxlength=512 style="width:40em" value="'.$class_entry["nav_links"].'"></td>
                      </tr>';
            }else{
                echo '<tr style="display: none"><td>navigation link string</td>
                        <td colspan=3><input type=text name=nav_links maxlength=512 style="width:40em" value="'.$class_entry["nav_links"].'"></td>
                      </tr>';
            }
            ?>
            <tr>
                <td><br><b>Nagios specific</b></td>
            </tr>
            <tr>
                <td>generated filename</td>
                <td><input type="text" name="out_file" maxlength="50" value="<?php echo $class_entry["out_file"]; ?>" onkeyup="check_input()"></td>
                <td class="attention"></td>
            </tr>
            <tr>
                <td>Nagios object definition</td>
                <td><input type="text" name="nagios_object" maxlength="50" value="<?php echo $class_entry["nagios_object"]; ?>" onkeyup="check_input()"></td>
                <td class="attention"></td>
            </tr>
        </table>



        <table>
            <tr><td>
                <div id=buttons>
                <input type="Submit" value="Submit" name="submit" align="middle">&nbsp;&nbsp;
                <input type="Reset" value="Reset">
                </div>
            </td></tr>
        </table>
    </form>

<script type="text/javascript">
<!--

    function check_input(){

    //alert(document.form1.nav_visible.options[0].value);
    // disable fields if no is selected (value 1)
        if (document.form1.nav_visible.options[1].selected == true){
            document.form1.selectusergroup.disabled = true;
            document.form1.selectadmingroup.disabled = true;
            document.form1.new_group.disabled = true;
            document.form1.nav_links.disabled = true;
        }else{
            document.form1.new_group.disabled = false;
            document.form1.nav_links.disabled = false;
        //admin selected
            if (document.form1.nav_privs.options[0].selected == true){
                document.form1.selectusergroup.disabled = true;
                document.form1.selectadmingroup.disabled = false;
            }else{
                document.form1.selectusergroup.disabled = false;
                document.form1.selectadmingroup.disabled = true;
            }

            if (document.form1.new_group.value == "") {

            }else{
                document.form1.selectusergroup.disabled = true;
                document.form1.selectadmingroup.disabled = true;
            }
        }

    }

    check_input();



//-->
</script>

<?php
mysql_close($dbh);
require_once 'include/foot.php';
?>
