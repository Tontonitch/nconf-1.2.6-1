<?php
session_start();

// no Form-resending when browser refresh with button or F5
if ( isset($_SESSION["count"]) ){
    $_SESSION["count"]++;
}else{
    $_SESSION["count"] = 1;
}

// Clean cache (session)
if ( isset($_GET["clear"]) ){
    if ( !empty($_GET["class"]) ){
        unset($_SESSION["cache"][$_GET["class"]]);
    }else{
        unset($_SESSION["cache"]);
    }
}

// Logout
if ( isset($_GET["logout"]) ){
    session_unset();
    session_destroy();
}

// Authenticate
if (AUTH_ENABLED == 1){
    if ( isset($_POST["authenticate"]) ){
        require_once 'include/login_check.php';

        // Log to history
        if (!empty($_SESSION["group"]) ){
            history_add("general", "login", "access granted (".$_SESSION['group'].")");
        }else{
            history_add("general", "login", "access denied (user: ".$user_loginname.")");
        }
    }

}else{
    // NO authentication
    $_SESSION['group'] = GROUP_ADMIN;
    $_SESSION["userinfos"]['username'] = GROUP_ADMIN;
    message($debug, 'authentication is disabled');
    message($debug, $_SESSION["group"].' access granted');
}


// main file (nothing other will be accepted)
$page_access = array();
// main file with all sub strings
$page_access_sub = array();


?>





<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <?php
    // Choose template file from config
    if ( defined('TEMPLATE_DIR') ){
        # ownstyle and new.css will be removed in a future release
        echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/new.css">';
        echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/ownstyle.css">';

        # This is the main css and will be the only on in later releases
        echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/main.css">';
        echo '<link rel="shortcut icon" href="design_templates/'.TEMPLATE_DIR.'/favicon.ico">';

        # IE 8 special CSS
        echo '
            <!--[if IE 8]>
            <link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/style_ie8.css">
            <![endif]-->
        ';
    }
    ?>

    <!-- Load nconf js functions -->
    <script src="include/js/nconf.js" type="text/javascript"></script>
    <script src="include/js/ajax.js" type="text/javascript"></script>
    <script src="include/js/ajax-dynamic-content.js" type="text/javascript"></script>

    <?php
    if ( defined('AUTO_COMPLETE') ){
    echo '
        <!-- Load autocomplete -->
        <script src="include/modules/sunrise/autocomplete/autocomplete.js" type="text/javascript"></script>
        <script src="include/modules/sunrise/autocomplete/ajax_ip.js" type="text/javascript"></script>
        ';
    }
    ?>

    <title>NConf</title>
</head>




<body>
<div id="title">
    <center>
        <div id="logo"></div>
    </center>
</div>
<div id="titlesub">
<center>
    <table>
        <tr>
            <td>Welcome&nbsp;<?php if( isset($_SESSION["userinfos"]['username']) ) echo $_SESSION["userinfos"]['username']; ?></td>
            <td><div align="right"><a href="http://www.nconf.org/dokuwiki/doku.php?id=nconf:help:main" target="_blank">Help</a></div></td>
        </tr>
    </table>
</center>
</div>
<div id="mainwindow">
    <?php
    if ( isset($_SERVER["REQUEST_URI"]) AND preg_match( '/'.preg_quote('INSTALL.php').'/', $_SERVER['REQUEST_URI']) ){
        # Installation
        require_once("include/menu/menu_start.html");
        require_once("include/menu/menu_install.php");
        require_once("include/menu/menu_end.php");

        echo '<div id="maincontent">';
    }elseif ( ( isset($_SERVER["REQUEST_URI"]) AND preg_match( '/'.preg_quote('UPDATE.php').'/', $_SERVER['REQUEST_URI']) )
            AND (file_exists('config/nconf.php')) ){
        # UPDATE
            require_once("include/menu/menu_start.html");
            require_once("include/menu/menu_update.php");
            require_once("include/menu/menu_end.php");

        echo '<div id="maincontent">';
    }elseif ( ( isset($_SERVER["REQUEST_URI"]) AND preg_match( '/'.preg_quote('UPDATE.php').'/', $_SERVER['REQUEST_URI']) )
            AND (!file_exists('config/nconf.php')) ){
        # UPDATE not possible when nconf not installed yet
        echo '<div id="maincontent">';
                message($critical, 'Setup required. To install NConf <b><a href="INSTALL.php">click here</a></b><br>');
    }else{
        # not a install or update call
        if ( file_exists('config/nconf.php') AND (!file_exists('INSTALL.php') AND !file_exists('INSTALL') )
            AND ( !file_exists('UPDATE.php') AND !file_exists('UPDATE') )  ){
            # check must have vars / constanst
            # when something fails, will set $error
            require_once('include/check_vars.php');

            if (!empty($error) OR !empty($critical) ){
                # do not show a menu if there is a error/critical
                    echo '<div id="centercontent">';
            }else{
                if ( !isset($_SESSION["group"]) ) {

                    # User seems not logged in
                    array_push ($page_access, "index.php");
                    echo '<div id="centercontent">';
                } elseif (  ( isset($_SESSION["group"]) ) AND ($_SESSION["group"] == "user") ) { 

                    require_once("include/menu/menu_start.html");
                    require_once("include/menu/menu_user.php");
                    require_once("include/menu/menu_end.php");
                    echo '<div id="maincontent">';

                } elseif (  ( isset($_SESSION["group"]) ) AND ($_SESSION["group"] == "admin") ) {

                    require_once("include/menu/menu_start.html");
                    require_once("include/menu/menu_user.php");
                    require_once("include/menu/menu_admin.php");
                    require_once("include/menu/menu_end.php");
                    echo '<div id="maincontent">';

                }
            }

        }elseif ( file_exists('config/nconf.php') AND
                ( file_exists('INSTALL.php') OR file_exists('INSTALL') OR file_exists('UPDATE') OR file_exists('UPDATE.php') )
            ){
            # One of the INSTALL Files are still existing, remove theme first!
            echo '<div id="centercontent">';
                message($critical, 'NConf has detected update or installation files in the main folder.<br><br>
                    To update NConf, go to the <b><a href="UPDATE.php">update page</a></b>
                    <br><br>
                    If you have just finished installing or updating NConf, make sure you delete the following<br> 
                    files and directories to continue:<br>
                    <br>- INSTALL
                    <br>- INSTALL.php
                    <br>- UPDATE
                    <br>- UPDATE.php
                    <br>
                ');

        }else{
            # config not available, first run INSTALL.php
            require_once("include/menu/menu_start.html");
            require_once("include/menu/menu_install.php");
            require_once("include/menu/menu_end.php");

            echo '<div id="maincontent">';
                message($critical, 'Setup required. To install NConf <b><a href="INSTALL.php">click here</a></b><br>');
        }

    }

    # Check for critical error, continue or abort
    if (!empty($critical)){
        echo '<div class="accordion error">
                <h2 class="header">
                    <span>ERROR</span>
                </h2>';
        echo '<div class="box_content">'.$critical.'</div></div>';
        require_once 'include/foot.php';
        exit;
    }


    //////
    // URL Authorisation check :
    //////

    require_once 'include/access_rules.php';

    # script file name
    $load_page = basename($_SERVER['REQUEST_URI']);


    # if load page is empty , handle as index.php
    //if ($load_page == "") $load_page = "index.php";
    if ($load_page == "" OR !preg_match( '/'.preg_quote('.php').'/', $load_page)  ) $load_page = "index.php";

    // check direct pages
    $regex_matched = "no";
    foreach ($page_access as $regex){
        //DEBUG: echo '<br>/^'.preg_quote($regex).'$/\' --- '.$load_page;
        if ( preg_match( '/^'.preg_quote($regex).'$/', $load_page) ) {
            $regex_matched = "yes";
            message($debug, "regex matched: $regex");
        }

    }

    // check sub pages
    foreach ($page_access_sub as $regex){
        if ( preg_match( '/^'.preg_quote($regex).'\w*/', $load_page) ) {
            $regex_matched = "yes";
            message($debug, "regex matched: $regex");
        }

    }

    // allow the Installation
    if (preg_match( '/.*INSTALL.php|.*UPDATE.php/', $load_page) ) {
        $regex_matched = "yes";
    }


    // Show page or EXIT the script ? (based on above auth-checks)
    if ( $regex_matched == "yes" ){
        message($debug, "url-authorization regex matched :$regex_matched");
        # go ahead in file
    }elseif ( !isset($_SESSION["group"]) AND ( empty($_GET["goto"]) ) ){
        # not logged in

        # Go to login page, and redirect it to called page
        $url = 'index.php?goto='.urlencode($_SERVER['REQUEST_URI']);
        # Redirect to login page with url as goto
        echo '<meta http-equiv="refresh" content="0; url='.$url.'">';
        message($info, '...redirecting to <a href="'.$url.'">page</a>');
        require_once 'include/foot.php';
        exit;
        
    }elseif ( !isset($_SESSION["group"]) AND ( !empty($_GET["goto"]) ) ){
        # do nothing, login page will be displayed
         message($debug, "display login page");
    }else{
        message($info, "You don't have permission to access this page!");
        message($debug, "url-authorization regex matched :$regex_matched");

        echo "&nbsp;&nbsp;You don't have permission to access this page!";

        require_once 'include/foot.php';
        # EXIT because of no access
        exit;

    }



?>
