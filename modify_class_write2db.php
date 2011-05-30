<?php
   require_once 'config/main.php';
   require_once 'include/head.php';

   // delay normaly short (given from config)
   // but if there is a special message, the message should be read, so put the delay few seconds higher
   $redirecting_delay = REDIRECTING_DELAY;

   // read mandatory values  
   $class_id = $_POST['class_id'];
   $config_class = $_POST['config_class'];
   $friendly_name = $_POST['friendly_name'];
   $nav_visible = $_POST['nav_visible'];
   $ordering = $_POST['ordering'];
   $out_file = $_POST['out_file'];
   $nagios_object = $_POST['nagios_object'];

   // process non-mandatory values
   if(isset($_POST['new_group'])){
      $new_group = $_POST['new_group'];
   }else{
      $new_group = "";
   }


// New group or selected user/admin Group
   if(isset($_POST['nav_privs'])){
      $nav_privs = $_POST['nav_privs'];
        if ( $new_group != "" ){
            $grouping = $new_group;
        }else{
            if ($nav_privs == "user"){
               if(isset($_POST['selectusergroup'])){
                  $grouping = $_POST['selectusergroup'];
               }else{
                  $grouping = "";
               }
            }elseif ($nav_privs == "admin"){
               if(isset($_POST['selectadmingroup'])){
                  $grouping = $_POST['selectadmingroup'];
               }else{
                  $grouping = "";
               }
            }
        }
   }else{
      // Should never come in this part...
      $nav_privs = "";
      $grouping  = "";
   }



   if(isset($_POST['nav_links'])){
      $nav_links = $_POST['nav_links'];
   }else{
      $nav_links = "";
   }

   if( ( $_POST['old_nav_privs'] != $nav_privs  OR $_POST['old_group'] != $grouping ) OR ($class_id == "new") ){
        $query = "SELECT MAX(ordering)+1 FROM ConfigClasses WHERE grouping = '$grouping' AND nav_privs = '$nav_privs'";
        $ordering = db_handler($query, "getOne", "Get MAX ordering of coresponding group");
        if ($ordering == NULL){
            $ordering = 0;
        }
   }


    

    # Check mandatory fields
    $mandatory = array("config_class" => "Class name", "friendly_name" => "Friendly Name");
    $write2db = check_mandatory($mandatory,$_POST);




    if ($write2db == "yes"){

        if ($class_id == "new"){
            // Generate navigation link string
            $nav_links = 'Show::overview.php?class='.$config_class.';;Add::add_item.php?item='.$config_class;
            // Make insert (adding class)
            $query = "INSERT INTO ConfigClasses (config_class, friendly_name, nav_visible, grouping, nav_links, nav_privs, ordering, out_file, nagios_object)
                        VALUES ('$config_class', '$friendly_name', '$nav_visible', '$grouping', '$nav_links', '$nav_privs', '$ordering', '$out_file', '$nagios_object')";
            $action = "created";
        }else{
           // UPDATE ConfigAttrs
            $action = "modified";
           $query = "UPDATE ConfigClasses
            SET
                config_class = '$config_class',
                friendly_name = '$friendly_name',
                nav_visible = '$nav_visible',
                grouping = '$grouping',
                nav_links = '$nav_links',
                nav_privs = '$nav_privs',
                ordering = '$ordering',
                out_file = '$out_file',
                nagios_object = '$nagios_object'
            WHERE
                id_class = $class_id
            ";
       }
        
       $result = db_handler($query, "result", "$action Entry");
       if($result){
            echo "<h2>$action class successfully &quot;$config_class&quot;</h2>";
            history_add($action, "Class", $config_class);

            // Go to next page without pressing the button
            echo '<meta http-equiv="refresh" content="'.$redirecting_delay.'; url='.$_SESSION["go_back_page_ok"].'">';
            message($info, '...redirecting to <a href="'.$_SESSION["go_back_page_ok"].'">page</a> in '.REDIRECTING_DELAY.' seconds...');

       }else{
          echo "<h2>Failed to $action attribute &quot;$config_class&quot;</h2>";
       }


    }else{

        if ( isset($_SESSION["cache"]["modify_class"]) ) unset($_SESSION["cache"]["modify_class"]);

        if ( !isset($error) ) {
            echo "<br>$info<br>";
        }else{
            echo "<b>Error:</b><br><br>";
            echo $error;
            echo "<br><br>";
            echo '<div id=buttons>';
                echo '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';
            echo '</div>';

            // Cache
            foreach ($_POST as $key => $value) {
                if ($key == "selectusergroup"){
                    $_SESSION["cache"]["modify_class"][$class_id]["grouping"] = $value;
                }elseif ($key == "selectadmingroup"){
                    $_SESSION["cache"]["modify_class"][$class_id]["grouping"] = $value;
                }else{
                    $_SESSION["cache"]["modify_class"][$class_id][$key] = $value;
                }
            }

        }
    }

   mysql_close($dbh);
   require_once 'include/foot.php';
?>
