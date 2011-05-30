<?php

// Define page (URL) access based on types of users (admins, ordinary users)

// Load group
if ( isset($_SESSION["group"]) ){
    $group = $_SESSION["group"];
}else{
    $group = GROUP_NOBODY;
}


// Config starts here:

// $page_access:        access only the specified page, no subpages
// $page_access_sub:    access page and all subpages (everything allowed behind the URL)


// access rights for all, also non-authentificated users (loginpage)
array_push($page_access_sub, "index.php");
array_push($page_access_sub, "INSTALL.php");


// access rights for 'users' (and 'admins')
if ( ($group == GROUP_USER) OR ($group == GROUP_ADMIN) ){
    array_push($page_access_sub, "index.php");
    array_push($page_access_sub, "add_item.php?item=host");
    array_push($page_access_sub, "add_item.php?item=service");
    array_push($page_access_sub, "add_item.php?item=contact");
    array_push($page_access_sub, "add_item_step");
    array_push($page_access_sub, "add_item_write2db.php");
    array_push($page_access_sub, "clone_host");
    array_push($page_access_sub, "clone_service");
    array_push($page_access_sub, "multimodify_attr");
    array_push($page_access_sub, "modify_item.php?item=host");
    array_push($page_access_sub, "modify_item.php?item=hostgroup");
    array_push($page_access_sub, "modify_item.php?item=service");
    array_push($page_access_sub, "modify_item.php?item=servicegroup");
    array_push($page_access_sub, "modify_item.php?item=contact");
    array_push($page_access_sub, "modify_item.php?xmode=pikett");
    array_push($page_access_sub, "modify_item.php?xmode=oncall");
    array_push($page_access_sub, "modify_item.php?xmode=on-call");
    array_push($page_access_sub, "modify_item.php?xmode=on_call");
    array_push($page_access_sub, "modify_item_write2db.php");
    array_push($page_access_sub, "modify_item_service.php");
    array_push($page_access_sub, "delete_item.php?item=host");
    array_push($page_access_sub, "delete_item.php?item=service");
    array_push($page_access_sub, "detail.php");
    array_push($page_access_sub, "history.php");
    array_push($page_access_sub, "generate_config.php");
    array_push($page_access_sub, "exec_generate_config.php");
    array_push($page_access_sub, "deploy_config.php");
    array_push($page_access_sub, "overview.php?class=host");
    array_push($page_access_sub, "overview.php?class=hostgroup");
    array_push($page_access_sub, "overview.php?class=servicegroup");
    array_push($page_access,     "overview.php?class=contact&xmode=pikett");
    array_push($page_access,     "overview.php?class=contact&xmode=oncall");
    array_push($page_access,     "overview.php?class=contact&xmode=on-call");
    array_push($page_access,     "overview.php?class=contact&xmode=on_call");
    array_push($page_access_sub, "dependency.php");
    array_push($page_access_sub, "id_wrapper.php?item=host&id_str=");
    array_push($page_access_sub, "id_wrapper.php?item=hostgroup&id_str=");
    array_push($page_access_sub, "id_wrapper.php?item=service&id_str=");
    array_push($page_access_sub, "id_wrapper.php?item=servicegroup&id_str=");
}


// additional access rights for 'admins' only
if ( $group == GROUP_ADMIN ) {
    array_push($page_access_sub, "add_item");
    array_push($page_access_sub, "add_attr");
    array_push($page_access_sub, "add_class");
    array_push($page_access_sub, "modify_item");
    array_push($page_access_sub, "modify_attr");
    array_push($page_access_sub, "modify_class");
    array_push($page_access_sub, "detail_attributes.php");
    array_push($page_access_sub, "detail_class.php");
    array_push($page_access_sub, "show_attr.php");
    array_push($page_access_sub, "show_class");
    array_push($page_access_sub, "delete_item.php");
    array_push($page_access_sub, "delete_attr.php");
    array_push($page_access_sub, "delete_class.php");
    array_push($page_access_sub, "overview.php");
    array_push($page_access, "static_file_editor.php");
    array_push($page_access_sub, "id_wrapper.php");
}

?>
