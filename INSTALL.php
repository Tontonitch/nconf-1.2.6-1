<?php
###
###  WELCOME TO NConf, configuration files are located here : config/..
###
#
# CONFIG
#
require_once('config.orig/nconf.php');
require_once('config.orig/authentication.php');
require_once('include/functions.php');


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
    if ( isset($_SESSION["install_data"]) ){
    if (is_array($_SESSION["install_data"]) ){
        foreach ($_SESSION["install_data"] as $step){
            if ( array_key_exists($name, $step) ){
                return $step[$name];
            }
        }
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
    if (file_exists($url)){
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
        return $result;
    }else{
        return FALSE;
    }
}


#
# Session handling
#
foreach ($_POST as $key => $value){
    #do not save the submit button
    if ( $key == "submit" OR $key == "step" ) continue;
    $_SESSION["install_data"][$_POST["step"]][$key] = $value;
}



echo '<form name="install" action="INSTALL.php" method="post">';
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
        preg_match('/(Distrib|Ver) ([0-9]+[\.0-9]*)/i', $output, $version);
        if ( !empty($version[2]) ){
            return $version[2];
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
        preg_match('/v([0-9]+[\.0-9]*)/i', $output, $version);
        if ( !empty($version[1]) ){
            return $version[1];
        }else{
            return FALSE;
        }
    }

    # html content
    echo table_row_description("Welcome to NConf setup", 'The pre-installation check will test your system for NConf.');

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

    # perl version
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
        
    //if ($db_check !== FALSE AND isset($_SESSION["install_data"][2]) ){
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

                # InnoDB support
                function InnoDB_support(){
                    $engines_query = "SHOW ENGINES;";
                    $engines_array = db_handler($engines_query, "array", "get mysql engines");
                    $InnoDB_support = "UNKNOWN";
                    foreach ($engines_array as $engine){
                        $engine_result = array_search('InnoDB', $engine);
                        if ($engine_result){
                            if ($engine["Support"] == TRUE){
                                $InnoDB_support = TRUE;
                                return TRUE;
                            }else{
                                $InnoDB_support = FALSE;
                                return FALSE;
                            }
                        }
                    }
                    return $InnoDB_support;
                }
                $InnoDB_support = InnoDB_support();
                echo table_row_check('InnoDB support', $InnoDB_support);


                # DB selected, try to create tables
                echo table_row_check('access database', TRUE ); 
                
                # check if tables are set ....
                //$query = 'SHOW TABLES FROM '.check_session_value("DBNAME");
                $query = 'SELECT id_attr FROM ConfigAttrs LIMIT 1;';
                $result = mysql_query($query, $dbh);
                if ($result){
                    # say that db is done, go to next step
                    echo table_row_check('tables were already created', TRUE );
                    # check if nconf already is installed
                    if ( file_exists('config/nconf.php') ){
                        echo table_row_check('NConf config files check', FALSE );
                        echo '</table><table width="450">';
                        echo table_row_description('', '<br>If you previously installed an older version of NConf, please use the <b><a href="UPDATE.php">UPDATE</a></b> function!');
                    }else{
                        echo '</table><table width="450">';
                        echo table_row_description('', '<input type=hidden name="db_status" value="ok">');
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
                }else{
                    # read sql-structure file for import
                    $install_file = 'INSTALL/create_database.sql';
                    if ( file_exists($install_file) ){
                        # try create tables
                        $result = parse_mysql_dump($install_file);
                        if (!$result){
                            echo table_row_check('create tables', FALSE );
                            echo table_row_description('', 'Error: '.mysql_error() );
                        }else{
                            echo table_row_check('create tables', TRUE );
                            # say that next step is the connect db part
                            echo table_row_description('', '<input type=hidden name="db_status" value="ok">');
                        }
                    }else{
                        echo table_row_check('open '.$install_file, FALSE );
                    }
                    
                }

            }else{
                # failed to select db
                echo table_row_check('access database', FALSE ); 
                # try to create DB
                $query = 'CREATE DATABASE '.check_session_value("DBNAME").';';
                $result = mysql_query($query, $dbh);
                if ($result){
                    echo table_row_check('created database', TRUE );
                }else{
                    echo table_row_check('created database', FALSE );
                    echo table_row_description("<br><br>Manual task", 'Please create the database manually.');
                }
            }
        }
    }

}elseif ($step == 2){
    # some checks
    $nconfdir = $_SERVER["SCRIPT_FILENAME"];
    $nconfdir = str_replace("/INSTALL.php", "", $nconfdir);

    # nagios bin
    $nagios_bin = $nconfdir."/bin/nagios";


    $templates = getDirectoryTree("design_templates");


    # html content
    echo table_row_description("General configuration", 'Please define basic settings here.');
    echo table_row_text("NCONFDIR", $nconfdir, "Path to the NConf directory");
    echo table_row_text("NAGIOS_BIN", $nagios_bin, "Path to the Nagios / Icinga binary");
    echo table_row_select("TEMPLATE_DIR", $templates, "nconf_fresh", "choose a template (color schema)");





}elseif ($step == 3){
    echo table_row_description("Authentication configuration", 'Choose if you want NConf to prompt for a login. <br>If yes, choose how you want to authenticate the users.');
    echo table_row_select("AUTH_ENABLED", array("TRUE" => "1", "FALSE" => "0"), "0", "Do you want NConf to authenticate users?", '', 1);
    echo table_row_select("AUTH_TYPE", array("file" => "file"), "file", "How do you want to authenticate?", '', 1);
    echo table_row_text("file_admin_password", "", "Password for admin when AUTH_TYPE = file<br>Do <b>not</b> use '::' (2 colons) in password", "password", '', '', 1);
    echo table_row_description("WARNING", "Please do not use '::' (2 colons) in passwords! You will not be able to login otherwise!<br>'::' is used as delimiter.", "none");

    ?>
    <script type="text/javascript">
    <!--

    function check_input(){
        if (document.install.AUTH_ENABLED.value == "0"){
            document.install.AUTH_TYPE.disabled = true;
            document.install.file_admin_password.disabled = true;
        }else{
            document.install.AUTH_TYPE.disabled = false;
            if (document.install.AUTH_TYPE.value == "file"){
                document.install.file_admin_password.disabled = false;
                if ( /.*\:\:.*/.test(document.install.file_admin_password.value) ){
                    document.getElementById('WARNING_titel').style.display = "";
                    document.getElementById('WARNING_desc').style.display = "";
                }else{
                    document.getElementById('WARNING_titel').style.display = "none";
                    document.getElementById('WARNING_desc').style.display = "none";
                }


            }else{
                document.install.file_admin_password.disabled = true;
            }
        }
    }
    check_input();
    //-->
    </script>
    <?php

}elseif ($step == 4){
    # shorter table for this step
    echo '</table><table width="500">';

    # copy config.orig to config
    echo table_row_description("Check if config files are present", '');
    if ( !file_exists('config/nconf.php') ){
        echo table_row_description("Create basic settings", 'Creating basic settings for NConf');
        $config_files = getFiles("config.orig");
        $failed = FALSE;
        foreach($config_files as $filename){
            $copy = @copy('config.orig/'.$filename, 'config/'.$filename);
            if (!$copy) $failed = TRUE;
            echo table_row_check("copy config file ($filename)", ($copy) );
        }
        if ($failed){
            echo table_row_description("<br><br>Check if your webserver can handle these:", 'Check if config/ is writable for your webserver<br>Check if config.orig is readable');
        }
    }else{
        echo table_row_check('Config files already exist', TRUE );
    }

    if ( !file_exists('config/nconf.php') ){
        $save_error = TRUE;
    }else{
        
        if ($_POST["submit"] != "Retry"){
            # delete some vars if user have changed auth type, but he was already in session
            if (!isset($_POST["AUTH_TYPE"]) AND check_session_value("AUTH_TYPE") ){
                unset($_SESSION["install_data"][3]["AUTH_TYPE"]);
            }
            if (!isset($_POST["file_admin_password"]) AND check_session_value("file_admin_password") ){
                unset($_SESSION["install_data"][3]["file_admin_password"]);
            }
        }
        ##
        ## Write config
        ##
        # save_error is for mark an error, so later will be refresh button when it goes TRUE
        $save_error = FALSE;

        echo table_row_description("<br>Save configuration", 'Saving your settings to config');


        # mysql config
        $mysql_config = write_config('config/mysql.php', array('DBHOST', 'DBNAME', 'DBUSER','DBPASS' ) );
        echo table_row_check('mysql conf', $mysql_config );
        if ($mysql_config !== TRUE) $save_error = TRUE;

        # NConf basic
        $nconf_config = write_config('config/nconf.php', array('TEMPLATE_DIR', 'NCONFDIR', 'NAGIOS_BIN' ) );
        echo table_row_check('NConf basic conf', $nconf_config );
        if ($nconf_config !== TRUE) $save_error = TRUE;

        # authentication 
        if (check_session_value("AUTH_ENABLED") ){
            $auth_type_config = write_config('config/authentication.php', array('AUTH_ENABLED', 'AUTH_TYPE') );
            echo table_row_check('authentication conf', $auth_type_config );
            if ($auth_type_config !== TRUE) $save_error = TRUE;
            if (check_session_value("AUTH_TYPE") == "file"){
                $pw = check_session_value("file_admin_password");
                if (!empty($pw)){
                    $file_accounts_config = write_config('config/.file_accounts.php', array('file_admin_password'), "password_file" );
                    echo table_row_check('admin password', $file_accounts_config );
                    if ($file_accounts_config !== TRUE){
                        $save_error = TRUE;
                    }else{
                        # password saved, tell the user the admin account
                        echo table_row_description("", 'The username for logging in is "admin".');
                    }
                }else{
                    # password was empty, must be given
                    echo table_row_check('admin password', "admin password was empty; password is required if authentication is enabled with auth type 'file'" );
                    $save_error = TRUE;
                }
            }
        }else{
            $authentication_config = write_config('config/authentication.php', array('AUTH_ENABLED') );
            echo table_row_check('authentication conf', $authentication_config );
            if ($authentication_config === FALSE) $save_error = TRUE;
        }

        if($step == 4 AND $save_error === FALSE ){
            echo table_row_description("<br><br>Installation complete", 'Please delete the following files and directories to continue:<br>
                <br>- INSTALL
                <br>- INSTALL.php
                <br>- UPDATE
                <br>- UPDATE.php');
            session_unset();
        }


    } # end of if config/nconf.php file exist


}





# End table
echo '</table>';


echo '<table>
            <tr><td>
                <div id=buttons>';
		if ( $step != 1 ){ echo '<br>'; }
                if ( $step == 4 AND $save_error === TRUE ){
                    echo '<input type="Submit" value="Retry" name="submit" align="middle">&nbsp;&nbsp;';
                }elseif($step == 4 AND $save_error === FALSE ){
                    # saved, go to index page
                    echo '<input type="button" value="Finish" name="submit" align="middle" onclick="location.href=\'index.php\'">&nbsp;&nbsp;';
                }
                if ($step != 4){
                    echo '<input type="Submit" value="Next" name="submit" align="middle">&nbsp;&nbsp;';
                }
                if ($step != 0 AND !($step == 4 AND $save_error === FALSE)){
                    echo '<input type="Submit" value="Back" name="submit" align="middle">&nbsp;&nbsp;';
                }
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
