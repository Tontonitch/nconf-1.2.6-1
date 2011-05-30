<?php
##
##  main CONFIG FILE,
##
##  all config files will be loaded here
##  also the functions will be loaded
##

#
# nconf Version info
#
require_once('include/version.php');

#
# nconf Specific configuration
#
require_once('config/nconf.php');

#
# mysql-DB settings
#
require_once('config/mysql.php');
#
# mysql Initiate connection
#
$dbh = mysql_connect(DBHOST,DBUSER,DBPASS);
mysql_select_db(DBNAME);

#
# Authentication / login
#
require_once('config/authentication.php');

#
# some misc gui things
#
require_once('include/gui.php');

#
# part for messages
#
require_once('include/messages.php');


##
## LOAD Functions
##
require_once('include/functions.php');



#
# configure some modules here 
#
$modules_dir = getDirectoryTree('include/modules');
foreach($modules_dir as $module){
    $module_config = 'include/modules/'.$module.'/config.php';
    if (file_exists($module_config) ){
        require_once($module_config);
        message($debug, "Loaded module: $module_config");
    }else{
        message($error, "FAILED loading module: $module_config");
    }
}

?>
