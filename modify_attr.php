<?php
require_once 'config/main.php';
require_once 'include/head.php';
set_page();
# go to show attribute page, when modify was ok
$HTTP_referer = 'show_attr.php';
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "set go_back_page_ok : ".$_SESSION["go_back_page_ok"]);

# Choose title
if(isset($_GET['id'])){
  $id = $_GET['id'];
  $title = "Modify attribute";
}else{
  $id = "new";
  $title = "Add attribute";
}


if ($id != "new"){
    # get entries
    $query = 'SELECT * FROM ConfigAttrs WHERE id_attr="'.$id.'"';
    $attr_entry = db_handler($query, "assoc", "get entries");

}else{
    # new entry does not have predefined values like the modify part
    $attr_entry = array("attr_name" => "",
                        "friendly_name" => "",
                        "description" => "",
                        "datatype" => "text",
                        "max_length" => "1024",
                        "poss_values" => "",
                        "predef_value" => "",
                        "mandatory" => "",
                        "ordering" => "",
                        "visible" => "",
                        "write_to_conf" => "",
                        "naming_attr" => "",
                        "link_as_child" => "no",
                        "fk_show_class_items" => "",
                        "fk_id_class" => "");
}


# Check cache
if ( isset($_SESSION["cache"]["modify_attr"][$id]) ){
    # Cache
    foreach ($_SESSION["cache"]["modify_attr"][$id] as $key => $value) {
        $attr_entry[$key] = $value;
    }
}


?>
    <h2><?php echo $title;?></h2><br>
    <form name="form1" action="modify_attr_write2db.php" method="post">
    <!--<form name="form1" action="modify_attr_write2db.php" method="post" onclick="check_input()" onkeydown="check_input()" onreset="check_input()">-->
    <input type=hidden name=attr_id value="<?php echo $id; ?>">
        <table width=410>
            <tr><td width=200>Nagios-specific attribute name</td>
                <td width=20></td>
                <td><input type=text name=attr_name maxlength=60 value="<?php echo htmlspecialchars($attr_entry["attr_name"]); ?>"></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>friendly name (will be shown in GUI)</td>
                <td width=20></td>
                <td><input type=text name=friendly_name maxlength=80 value="<?php echo htmlspecialchars($attr_entry["friendly_name"]); ?>"></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>description, example or help-text</td>
                <td width=20></td>
                <td><input type=text name=description maxlength=250 value="<?php echo htmlspecialchars($attr_entry["description"]); ?>"></td>
                <td class="attention"></td>
            </tr>
            <tr><td>attribute belongs to class</td>
                <td width=20></td>
                <td><select name="fk_id_class"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
<?php
    $query = mysql_query("SELECT id_class,config_class FROM ConfigClasses ORDER BY config_class");

    while($entry = mysql_fetch_row($query)){
        echo '<option value='.$entry[0];
            if ( $entry[0] == $attr_entry["fk_id_class"] ) echo " selected";
        echo " >$entry[1]</option>";
    }
?>
                </select></td>
                <td class="attention">*</td>
            </tr>
        </table>
        <br>
        <table width=410>
            <tr><td colspan=4>
                    <table border=0 frame=box rules=none cellspacing=2 cellpadding=3>
                        <tr class="box_content"><td colspan=3 class="bg_header"><strong>Info: datatype</strong></td></tr>
                        <tr class="box_content"><td width=10% valign=top class="box_content"><i>text</i></td>
                            <td width=3%></td><td width=87%>This datatype is used for a simple text attribute. A maximum length may be specified.</td></tr>
                        <tr class="box_content"><td width=10% valign=top><i>password</i></td>
                            <td width=3%></td><td width=87%>This datatype is used for a password attribute. An encryption may be specified. Passwords will not be displayed</td></tr>
                        <tr class="box_content"><td valign=top><i>select</i></td>
                            <td></td><td>This datatype creates a drop-down menu. A comma-separated list of possible values must be specified.</td></tr>
                        <tr class="box_content"><td valign=top><i>assign_one</i></td>
                            <td></td><td>This datatype creates a drop-down menu that allows an item of any class to be assigned to another one (selected item will be linked as &quot;parent&quot; by default).</td></tr>
                        <tr class="box_content"><td valign=top><i>assign_many</i></td>
                            <td></td><td>This datatype creates a menu that allows an item of any class to be assigned to one or more other items (selected items will be linked as &quot;parents&quot; by default).</td></tr>
                        <tr class="box_content"><td valign=top><i>assign_cust_order</i></td>
                            <td></td><td>This datatype is identic to &quot;assign_many&quot; but can additionally handle the order of items</td></tr>
                    </table><br>
                </td></tr>
            <tr><td width=200>choose attribute datatype</td>
                <td width=20></td>
                <td><select name="datatype" onChange="check_input(document.form1.datatype.value)"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
                    <option value="text" <?php if($attr_entry["datatype"] == "text") echo " selected"; ?> >text</option>
                    <option value="password" <?php if($attr_entry["datatype"] == "password") echo " selected"; ?> >password</option>
                    <option value="select" <?php if($attr_entry["datatype"] == "select") echo " selected"; ?> >select</option>
                    <option value="assign_one" <?php if($attr_entry["datatype"] == "assign_one") echo " selected"; ?> >assign_one</option>
                    <option value="assign_many" <?php if($attr_entry["datatype"] == "assign_many") echo " selected"; ?> >assign_many</option>
                    <option value="assign_cust_order" <?php if($attr_entry["datatype"] == "assign_cust_order") echo " selected"; ?> >assign_cust_order</option>
                    </select></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>items of class to be assigned</td>
                <td width=20></td>
                <td><select name="fk_show_class_items" disabled>
<?php
    $query = mysql_query("SELECT id_class,config_class FROM ConfigClasses ORDER BY config_class");

    while($entry = mysql_fetch_row($query)){
        //echo "<option value=$entry[0]>$entry[1]</option>";
        echo '<option value='.$entry[0];
            if ( $entry[0] == $attr_entry["fk_show_class_items"] ) echo " selected";
        echo " >$entry[1]</option>";
    }
?>
                </select></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>list of possible values (separated by &quot;<?php echo SELECT_VALUE_SEPARATOR; ?>&quot;)</td>
                <td width=20></td>
                <td><input type=text name=poss_values value="<?php echo htmlspecialchars($attr_entry["poss_values"]); ?>"></td>
                <td class="attention">*</td>
            </tr>
            <tr><td>pre-defined value</td>
                <td width=20></td>
                <td><input type=text name=predef_value maxlength=1024 value="<?php echo htmlspecialchars($attr_entry["predef_value"]); ?>"></td>
                <td class="attention"></td>
            </tr>
            <tr><td>max. text-field length (chars)</td>
                <td width=20></td>
                <td><input type=text name=max_length maxlength=4 style="width:60px" value="<?php echo $attr_entry["max_length"]; ?>"></td>
                <td class="attention"></td>
            </tr>
            <tr><td>link selected item(s) as children?</td>
                <td width=20></td>
                <td>
                    <select name="link_as_child" style="width:60px" disabled> 
                        <option <?php if($attr_entry["link_as_child"] == "no") echo " selected"; ?> >no</option>
                        <option <?php if($attr_entry["link_as_child"] == "yes") echo " selected"; ?> >yes</option>
                    </select>
                </td>
                <td class="attention"></td>
            </tr>
        </table>
        <br>
        <table width=240>
            <tr><td>attribute is mandatory?</td>
                <td width=20></td>
                <td>
                    <select name=mandatory style="width:60px">
                        <option <?php if($attr_entry["mandatory"] == "no") echo " selected"; ?> >no</option>
                        <option <?php if($attr_entry["mandatory"] == "yes") echo " selected"; ?> >yes</option>
                    </select>
                <input type="hidden" name="HIDDEN_mandatory" value="<?php echo $attr_entry["mandatory"]; ?>">
                </td>
                <td class="attention">*</td>
            </tr>
            <tr><td>attribute is visible?</td>
                <td width=20></td>
                <td>
                    <select name=visible style="width:60px">
                        <option <?php if($attr_entry["visible"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($attr_entry["visible"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td class="attention">*</td>
            </tr>
            <tr><td>write attribute to configuration?</td>
                <td width=20></td>
                <td>
                    <select name=conf style="width:60px">
                        <option <?php if($attr_entry["write_to_conf"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($attr_entry["write_to_conf"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td class="attention">*</td>
            </tr>
            <tr><td>ordering</td>
                <td width=20></td>
                <td><input type=text name=ordering maxlength=2 style="width:60px" value="<?php echo $attr_entry["ordering"]; ?>"></td>
                <td class="attention"></td>
            </tr>
            <tr><td>naming attribute?</td>
                <td width=20></td>
                <td>
                    <select name=naming_attr style="width:60px" onChange="check_naming_attr()"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
                        <option <?php if($attr_entry["naming_attr"] == "no") echo " selected"; ?> >no</option>
                        <option <?php if($attr_entry["naming_attr"] == "yes") echo " selected"; ?> >yes</option>
                    </select>
                </td>
                <td class="attention">*</td>
            </tr>
            <tr><td colspan=4><br>
                 <table border=0 frame=void rules=none style="border-collapse:collapse" cellspacing=2 cellpadding=3>
                    <tr class="bg_header"><td><strong>Info: ordering / naming_attr</strong></td></tr>
                    <tr class="box_content"><td><i>ordering</i>: enter specific ranking number or leave blank for the next free one.
                            <br><br><i>naming_attr</i>: defines the naming attribute of a class and is displayed in assignments. There is only one naming_attr per class!</td></tr>
                 </table>
            </td></tr>
        </table>
        <table>
            <tr><td>
                <div id=buttons>
                <input type="Submit" value="Submit" name="submit" align="middle">&nbsp;&nbsp;
                <?php
                    echo '<input type="Reset" value="Reset" onclick=\'check_input("'.$attr_entry["datatype"].'")\'>';
                ?>
                </div>
            </td></tr>
        </table>
    </form>


<?php
# Script part for modify attribute
if ($id != "new"){
?>

    <script type="text/javascript">
    <!--
        // execute once on load
        //check_input();
        <?php
            echo 'check_input("'.$attr_entry["datatype"].'");';
        ?>
        // also check the naming_attr
        check_naming_attr();

        function check_input(datatype){
            if (!datatype){
                datatype = "text";
            }
            //var datatype = "<?php echo $attr_entry["datatype"] ?>";
            switch (datatype) {
            //switch (document.form1.datatype.value) {
                case "text":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = false;
                break;
                case "password":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = true;
                    document.form1.max_length.disabled = false;
                    document.form1.conf.value = "no";
                break;
                case "select":
                    document.form1.poss_values.disabled = false;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
                case "assign_one":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
                case "assign_many":
                case "assign_cust_order":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
            }
        }
        function check_naming_attr(){
            if (document.form1.naming_attr.value == "yes"){
                document.form1.mandatory.value = "yes";
                document.form1.mandatory.disabled = true;
            }else{
                document.form1.mandatory.disabled = false;
            }
        }
    //-->
    </script>

<?php
# Script part for add attribute
}else{
?>

    <script type="text/javascript">
    <!--
        // execute once on load
        //check_input();
        <?php
            echo 'check_input("'.$attr_entry["datatype"].'");';
        ?>
        // also check the naming_attr
        check_naming_attr();

        function check_input(datatype){
            if (!datatype){
                datatype = "text";
            }
            //var datatype = "<?php echo $attr_entry["datatype"] ?>";
            switch (datatype) {
            //switch (document.form1.datatype.value) {
                case "text":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = false;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                break;
                case "password":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = true;
                    document.form1.max_length.disabled = false;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                    document.form1.conf.value = "no";
                break;
                case "select":
                    document.form1.poss_values.disabled = false;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                break;
                case "assign_one":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = false;
                    document.form1.link_as_child.disabled = false;
                break;
                case "assign_many":
                case "assign_cust_order":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = false;
                    document.form1.link_as_child.disabled = false;
                break;
            }
        }
        function check_naming_attr(){
            if (document.form1.naming_attr.value == "yes"){
                document.form1.mandatory.value = "yes";
                document.form1.mandatory.disabled = true;
            }else{
                document.form1.mandatory.disabled = false;
            }
        }
    //-->
    </script>

<?php
} // end of add or modify script path



mysql_close($dbh);
require_once 'include/foot.php';
?>
