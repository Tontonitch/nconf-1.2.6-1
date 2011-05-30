<?php
# Imports an AJAX file
# We want to have all ajax files in a folder, but scripts need access to includes etc. so this is because this file must be in root directory of nconf
if ($_GET["ajax_file"] AND file_exists('include/ajax/'.$_GET["ajax_file"])){
    # if username is set, save it for history_add etc.
    # bacause we are called with ajax here, we do not know the $_SESSION vars from the application...
    if ( !empty($_GET["username"]) ) $_SESSION["userinfos"]["username"] = $_GET["username"];

    require_once('include/ajax/'.$_GET["ajax_file"]);
}else{
    echo 'AJAX-File "include/ajax/'.$_GET["ajax_file"].'" not found';
}
?>
