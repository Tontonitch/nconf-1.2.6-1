<?php
echo '
    <h2 class="header"><span>'.TXT_MENU_BASIC.'</span></h2>
    <div class="box_content">';
        # FIX menu user begin
        include('include/menu/static_content/menu_user_begin.html');

        # Select ConfigClasses
        $query = 'SELECT grouping, nav_links, friendly_name  FROM ConfigClasses WHERE nav_privs = "user" AND nav_visible = "yes" ORDER BY UPPER(grouping), ordering ASC, config_class';
        $result = db_handler($query, "array", "Select user Navigation classes");

        # Creates user menu dynamic
        create_menu($result);


        # Links at the end of the users menu
        $user_menu_end = array();

        # Create oncall link, if $ONCALL_GROUPS is defined
        if (!empty($ONCALL_GROUPS)){
            array_push($user_menu_end, array("nav_links" => "Change on-call settings::overview.php?class=contact&amp;xmode=pikett", "friendly_name" => "", "grouping" => ""));
        }

        # Generate Nagios config link
        array_push($user_menu_end, array("nav_links" => "Generate Nagios config::generate_config.php", "friendly_name" => "", "grouping" => ""));

        # Creates the menu
        create_menu($user_menu_end);


echo '</div>';

// FIX menu user end
//include('include/menu/static_content/menu_user_end.php');
include('include/menu/static_content/menu_user_end.html');

?>
