<?php
###
###  WELCOME TO NConf, configuration files are located here : config/..
###
## do not change anything other
##

# look if configuration files exist
if (file_exists('config/main.php') ){
    require_once 'config/main.php';
    require_once 'include/head.php';

    # for all pages show the login or if logged in only version info
    require_once("include/login_form.php");

    # Load serverlist from cmdb if activated
    if ( defined('LOAD_SERVERLIST') AND (LOAD_SERVERLIST == 1)){
        $load_serverlist = 'include/modules/sunrise/load_serverlist.php';
        if (file_exists($load_serverlist) ){
            require_once ($load_serverlist); 
        }
    }
    require_once 'include/foot.php';
}else{
    # config not yet done, load from config.orig the needed data for install (head.php will handle the rest)
    require_once('config.orig/nconf.php');
    require_once('config.orig/authentication.php');
    require_once('include/functions.php');
    require_once 'include/head.php';
    require_once 'include/foot.php';
}



###
### Finish
### anything is loaded until here
###
?>
