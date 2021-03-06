<?php
###
###  WELCOME TO NConf, configuration files are located here : config/..
###
#
# CONFIG
#

if ( !file_exists('config/main.php') ){
    require_once('config.orig/nconf.php');
    require_once('config.orig/authentication.php');
    require_once('include/functions.php');

    require_once 'include/head.php';
    // here it will fail if update is not possible (when nconf is not already installed)
}else{
    require_once('config/main.php');
}


#
# Step handling
#
if ( !isset($_POST["step"]) ){
    $step = 0;
}else{
    $step = $_POST["step"];
    if (isset($_POST["submit"]) AND $_POST["submit"] == "Back"){
        #Back button pressed
        $step = $step - 1;
    }elseif(isset($_POST["submit"]) AND $_POST["submit"] == "Next"){
        # increase step for next form
        $step++;
    }else{
        #refreshing, no step modification
    }
    
}


#
# DB creation / handling
#
if (isset($_POST["db_status"])){
    $db_check = $_POST["db_status"];
}elseif (!empty($_GET["from"]) AND $_GET["from"] == "install" ){
    # coming from install when user already has an nconf installation
    $db_check = "connect";
    $step = 1;
}else{
    $db_check = FALSE;
}
unset($_POST["db_status"]);
if ( ($step == 2 AND $_POST["submit"] != "Back") AND !($db_check == "ok") ){
    # run again the Database part
    $step = 1;
}


# define installation/configuration
$_SESSION["install"] = TRUE;
require_once 'include/head.php';

###
### Functions for install
###

#
# Function which generates the html output (table rows)
#
function check_session_value($name){
    if ( isset($_SESSION["update_data"]) ){
        if (is_array($_SESSION["update_data"]) ){
            foreach ($_SESSION["update_data"] as $step){
                if ( array_key_exists($name, $step) ){
                    return $step[$name];
                }
            }
        }
    }else{
        if ( defined($name) ){
            return constant($name);
        }
    }
    return FALSE;
}

function table_row_text($title, $value, $description, $type = "text", $attention = 0, $disabled = 0, $check_input = 0){
    $cache_value = check_session_value($title);
    if ($cache_value) $value = $cache_value;
    $row = '
        <tr>
            <td width=150>'.$title.'</td>
        <td width=270>';
        $row .= '<input type="'.$type.'" name="'.$title.'" maxlength=50 value="'.$value.'"';
            if ($disabled == 1) $row .= ' disabled="disabled" ';
            if ($check_input == 1) $row .= ' onchange="check_input()" onkeyup="check_input()"';
        $row .= '>';
        $row .= '</td><td width=20 class="attention">';
            if ($attention == 1) $row .= "*";
        $row .= '</td><td>';
            if (!empty($description)) $row .= $description;
        $row .= '</td></tr>';
    return $row;
}
function table_row_select($title, $values, $selected, $description, $attention = 0, $check_input = 0){
    $cache_value = check_session_value($title);
    if ($cache_value) $selected = $cache_value;
    $row = '
        <tr>
            <td width=150>'.$title.'</td>
        <td width=270>';
        $row .= '<select name="'.$title.'"';
        if ($check_input == 1) $row .= ' onchange="check_input()"';
        $row .= '>';
        foreach ($values as $name => $value){
            $row .= '<option value="'.$value.'"';
            if ($value == $selected) $row .= ' selected';
            $row .= '>'.$name.'</option>';
        }
        $row .= '</select>';
        $row .= '</td><td width=20 class="attention">';
            if ($attention == 1) $row .= "*";
        $row .= '</td><td>';
            if (!empty($description)) $row .= $description;
        $row .= '</td></tr>';
    return $row;
}

function table_row_description($title, $description, $display = ""){
    $row = "";
    if (!empty($title)){
        $row = '
        <tr id="'.$title.'_titel" name="'.$title.'" style="display:'.$display.'">
            <td colspan=3><h2>'.$title.'</h2></td>
        </tr>';
    }
    if (!empty($description)){
    $row .= '<tr id="'.$title.'_desc" name="'.$title.'" style="display:'.$display.'">
            <td colspan=3>'.$description.'<br><br></td>
        </tr>
    ';
    }

    return $row;
}


function table_row_check($title, $status){
    if ($status === TRUE){
        $row = '
        <tr>
            <td><b>'.$title.'</b></td>
            <td class="status_ok">OK</td>
            <td></td>
        </tr>';
    }elseif ($status == "UNKNOWN"){
        $row = '
        <tr>
            <td><b>'.$title.'</b></td>
            <td class="status_unknown">UNKNOWN</td>
            <td></td>
        </tr>';
    }elseif ($status === FALSE){
        $row = '
        <tr>
            <td><b>'.$title.'</b></td>
            <td class="status_failed">FAILED</td>
            <td></td>
        </tr>';
    }else{
        $row = '
        <tr class="header">
            <td><b>'.$title.'</b></td>
            <td class="status_failed">FAILED</td>
            <td>
                <a href="javascript:swap_visible(\''.$title.'\')">
                <img src="img/icon_expand.gif" id="swap_icon_'.$title.'" > 
                show errorcode
                </a>
            </td>
        </tr>
        <tr id="'.$title.'" style="display:none"  class="box_content">
            <td colspan=3>'.$status.'</td>
        </tr>';

    }

    return $row;
}


function write_config($file, $replacers, $special = ''){
    global $error;
    global $info;
    foreach($replacers as $replacer){
        if ( check_session_value($replacer) === FALSE){
            message($error, "Not all necessary config variables are present. ($replacer)");
            return FALSE;
        }
    }
    # read the config file
    $lines = file($file);
    if ($lines === FALSE){
        message($error, "The config file ($file) could not be read.");
        return FALSE;
    }

    # try to open config file writable, else readable
    if (is_writable($file)) {
        $fh = fopen($file, "w");
    }else{
        $fh = fopen($file, "r");
        message($errer, "read only");
    }
    if ($fh === FALSE){
        message($error, "The config file ($file) could not be opened.");
        return FALSE;
    }else{
        $new_config = '';
        $log = '';
        # go thru each line
        foreach ($lines as $line){
            $mark_line = 0;

            # ignore comments
            if ( !preg_match( '/^#/', $line) ){
                # find the replacer (the constant)
                foreach ($replacers as $replacer){
                    if ($special == "password_file"){
                        if ( preg_match( '/^admin/', $line) ){
                            $line = "admin::".check_session_value($replacer)."::admin::Administrator::\n";
                            $mark_line = 1;
                        }
                    }else{
                        if ( preg_match( '/^define\(["\']'.$replacer.'["\']/', $line) ){
                            $line = "define('$replacer', '".check_session_value($replacer)."');\n";
                            $mark_line = 1;
                        }
                    }
                }
            }

            # mark the new lines
            if ($mark_line){
                $log .= "<b>".htmlspecialchars($line)."</b><br>";
            }else{
                $log .= htmlspecialchars($line)."<br>";
            }
            
            # add line to config var
            $new_config .= $line;
        }


        #write to file
        if ( fwrite($fh, $new_config) == FALSE){
            # could not write to file, put out config code on page
            message($info, "The config directory and all files should be writeable for your webserver (mostly apache)", "overwrite");
            message($error, "could not write config file($file), please change the <b>bold lines</b> manually or upload the file into the config directory by FTP");
            return $log;
        }else{
            # write file success
            return TRUE;
        }
        fclose($fh);
    
    }

}

# For creating/importing the database
function parse_mysql_dump($url){
    $file_content = file($url);
    $query = "";
    foreach($file_content as $sql_line){
      if(trim($sql_line) != "" && strpos($sql_line, "--") === false){
        $query .= $sql_line;
        if(preg_match("/;[\040]*\$/", $sql_line)){
          $result = mysql_query($query);   //or die(mysql_error());
          if (!$result) return $result;

          $query = "";
        }
      }
    }

    return TRUE;
}


function upgrade_mysql($file){
    $update = parse_mysql_dump($file);
    echo table_row_check('Executing '.basename($file), $update );
    return $update;
}

function print_file($file){
    $file_content = @file_get_contents($file);
    echo '<pre class="editor_field">'.$file_content.'</pre>';
}

# upgrade file handling
function upgrade_files($installed_version, $dir, $file_regex, $action, $special = ''){
    # read the directory
    $dirs = getDirectoryTree($dir);
    $success = TRUE;

    # update each seperate version
    do {
        # reset next version
        if ( isset($next_update_version) ){
            $installed_version = $next_update_version;
            unset($next_update_version);
        }

        # read the destination version
        foreach($dirs as $update_folder){
            if( preg_match('/^(.*)_to_(.*)$/', $update_folder, $matches) ){
                $from_version = $matches[1];
                $to_version = $matches[2];
                # has actual version a upgrade directory ?
                if($installed_version == $from_version
                    OR ($special == 'last_readme' AND $installed_version == $to_version )
                ){
                    # or if no update just display the last README file
                    if ($special != 'last_readme'){
                        $next_update_version = $to_version;
                    }
                    $files = getFiles($dir.'/'.$update_folder);
                    foreach($files as $update_file){
                        if( preg_match($file_regex, $update_file) ){
                            if (is_callable($action)){
                                $update = $action($dir.'/'.$update_folder.'/'.$update_file);
                                //echo table_row_check('Executing '.$update_file, $update );
                                if (!$update){
                                    $success = FALSE;
                                }
                            }
                        }
                    }
                }
            }
        }
    } while ( !empty($next_update_version) );

    return $success;
}


#
# Session handling
#
foreach ($_POST as $key => $value){
    #do not save the submit button
    if ( $key == "submit" OR $key == "step" ) continue;
    $_SESSION["update_data"][$_POST["step"]][$key] = $value;
}



echo '<form name="install" action="UPDATE.php" method="post">';
echo '<input type=hidden name="step" value="'.$step.'">';

# Begin table
echo '<table width="100%" border=0>';


#
# Install Steps
#


if ($step == 0){
    # some checks
    function find_SQL_Version() {
        $output = shell_exec('mysql -V');
        if ( !$output ){
            # could not execute
            return "UNKNOWN";
        }
        preg_match('/Distrib ([0-9]+[\.0-9]*)/i', $output, $version);
        if ( !empty($version[1]) ){
            return $version[1];
        }else{
            return FALSE;
        }
    }

    function find_PERL_Version() {
        $output = shell_exec('perl -v');
        if ( !$output ){
            # could not execute
            return "UNKNOWN";
        }
        preg_match('/is perl, v([0-9]+[\.0-9]*)/i', $output, $version);
        if ( !empty($version[1]) ){
            return $version[1];
        }else{
            return FALSE;
        }
    }

    # html content
    echo table_row_description("Welcome to NConf update", 'The pre-update check will test your system for NConf.');

    # shorter table for this step
    echo '</table><table width="240">';
    echo table_row_description("Requirements", '');
    echo table_row_check('PHP 5.0 (or higher) -> '.phpversion(), version_compare(phpversion(), '5.0', '>=') );

    # mysql version check
    $mysql_status = find_SQL_Version();
    if ($mysql_status == "UNKNOWN"){
        echo table_row_check('MySQL 5.0.2 (or higher)', "UNKNOWN");
    }else{
        echo table_row_check('MySQL 5.0.2 (or higher) -> '.$mysql_status, version_compare($mysql_status, '5.0.2', '>=') );
    }

    # php-mysql support
    $mysql_status = function_exists('mysql_connect');
    if (!$mysql_status) message ($error, 'Could not find function "mysql_connect()"<br>You must configure PHP with mysql support.');
    echo table_row_check('PHP-MySQL support', $mysql_status);


    $perl_status = find_PERL_Version();
    if ($perl_status == "UNKNOWN"){
        echo table_row_check('Perl 5.6 (or higher)', "UNKNOWN");
    }else{
        echo table_row_check('Perl 5.6 (or higher) -> '.$perl_status, version_compare($perl_status, '5.6', '>=') );
    }

}elseif ($step == 1){
        echo table_row_description("MySQL database configuration", 'Please enter the DB information for NConf installation.');
        echo table_row_text("DBHOST", "localhost", "DB server");
        echo table_row_text("DBNAME", "database_name", "DB name");
        echo table_row_text("DBUSER", "user_name", "DB user name");
        echo table_row_text("DBPASS", "password", "DB user password", "password");
	echo '<tr><td><br></td></tr>';

        # shorter table for this step
        echo '</table>';
        # say that next step is the connect db part
        echo '<input type=hidden name="db_status" value="connect">';

        echo '<table width="200">';
        
    if ($db_check !== FALSE ){
        echo table_row_description('Checks', '');
        if (function_exists('mysql_connect')){
            $dbh = @mysql_connect(check_session_value("DBHOST"),check_session_value("DBUSER"),check_session_value("DBPASS"));
            if (!$dbh){
                message($error, 'Could not connect: ' . mysql_error());
            }
        }else{
            $dbh = FALSE;
            message($error, '<b>mysql_connect</b> not found, you must install PHP with mysql support!');
        }

        if (!$dbh){
            echo table_row_check('Connect to DB', FALSE );
            echo table_row_description('', "<br>$error");
        }else{
            echo table_row_check('connect to mysql', TRUE ); 
            $db_selected = @mysql_select_db(check_session_value("DBNAME"));
            if ($db_selected ){
                # DB selected, try to create tables
                echo table_row_check('access database', TRUE ); 

                $installed_version = FALSE;

                # newest version is :
                $newest_version = VERSION_NUMBER;



                ###
                # check installed version
                ###
                # get version directories
                $dirs = getDirectoryTree('UPDATE');
                if ( empty($dirs) ){
                    echo table_row_check('check versions in UPDATE directory', FALSE );
                }else{
                    foreach($dirs as $update_folder){
                        include('UPDATE/'.$update_folder.'/version_check.php');
                    }
                }
                

                if ($installed_version){
                    echo table_row_check('DB version detected: '.$installed_version, TRUE );
                    if ( version_compare($installed_version, $newest_version, '>=') ){
                        echo table_row_description('<br>No update necessary', '<input type=hidden name="db_status" value="ok">');
                        echo table_row_description('', '<input type=hidden name="old_version" value="'.$installed_version.'">');
                    }elseif($db_check == "upgrade"){
                        echo '</table>
                                <input type=hidden name="old_version" value="'.$installed_version.'">
                              <table width="300">';
                        echo table_row_description('Update in progress...', '');
                    }else{
                        echo table_row_description('<br>Please proceed to update', '<input type=hidden name="db_status" value="upgrade">');
                    }

                    ?>
                    <script type="text/javascript">
                    <!--
                        disable('DBHOST');
                        disable('DBNAME');
                        disable('DBUSER');
                        disable('DBPASS');
                    //-->
                    </script>
                    <?php

                    # upgrade process
                    if ($db_check == "upgrade"){

                        # update DB with sql files
                        $success = upgrade_files($installed_version, 'UPDATE', '/\.sql$/', 'upgrade_mysql');

                        if ($success){
                            # say that db is done, go to next step
                            echo table_row_description('', '<input type=hidden name="db_status" value="ok">');
                        }

                    }  // end of upgrade

                }else{
                    echo table_row_check('lookup NConf DB version', FALSE );
                    echo '</table><table width="500">';
                    echo table_row_description("<br><br>NConf version could not be determined.", 'Please enter the correct data to connect to the existing NConf DB.<br>For first-time installation of NConf use the <b><a href="INSTALL.php">INSTALL</a></b> function.');

                }
                

            }else{
                # failed to select db
                echo table_row_check('access database', FALSE ); 
                echo '</table><table width="450">';
                echo table_row_description("Wrong information", 'Please enter the correct data to connect to the existing NConf DB.
                        <br>For first-time installation of NConf use the <b><a href="INSTALL.php">INSTALL</a></b> function.');
            }
        }
    }

}elseif ($step == 2){
    if ($_POST["old_version"] == VERSION_NUMBER){
        echo table_row_description("No database update found / no update required", "Please carefully examine the following release notes for additional update instructions.");
        echo '</table>';
        # display the last README
        $update = upgrade_files($_POST["old_version"], 'UPDATE', '/^README$/', 'print_file', 'last_readme');
    }else{
        echo table_row_description("Please carefully examine the following release notes for additional update instructions.", '');
        echo '</table>';
        $update = upgrade_files($_POST["old_version"], 'UPDATE', '/^README$/', 'print_file');
    }



}elseif ($step == 3){
            echo table_row_description("Update complete", 'Please delete the following files and directories to continue:<br>
                <br>- INSTALL
                <br>- INSTALL.php
                <br>- UPDATE
                <br>- UPDATE.php');
            session_unset();



}





# End table
echo '</table>';

$save_error = FALSE;
echo '<table>
            <tr><td>
                <div id=buttons>';
                if ( $step != 1 ){ echo '<br>'; }
                if ( $step == 4 AND $save_error === TRUE ){
                    echo '<input type="Submit" value="Retry" name="submit" align="middle">&nbsp;&nbsp;';
                }elseif($step == 3 AND $save_error === FALSE ){
                    # saved, go to index page
                    echo '<input type="button" value="Finish" name="submit" align="middle" onclick="location.href=\'index.php\'">&nbsp;&nbsp;';
                }
                if ($step != 3){
                    echo '<input type="Submit" value="Next" name="submit" align="middle">&nbsp;&nbsp;';
                }
                /*
                if ($step != 0 AND !($step == 4 AND $save_error === FALSE)){
                    echo '<input type="Submit" value="Back" name="submit" align="middle">&nbsp;&nbsp;';
                }*/

                echo'
                <!--<input type="Reset" value="Reset">-->
                </div>
            </td></tr>
        </table>
';
echo '</form>';


#
# Load footer
#
require_once 'include/foot.php';

/* DEBUG HELP
echo '<div align="left"><pre>';
var_dump($_SESSION["install_data"]);
echo '</pre></div>';
*/
###
### Finish
### anything is loaded until here
###
?>
