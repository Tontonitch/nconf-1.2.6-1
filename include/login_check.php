<?php
// USER login default
// for other methods, expand this file and configure it in the config part
// --> AUTH_TYPE

// information what is needed after this script:
// - check username and pw
// - set $_SESSION['group'] to GROUP_USER or GROUP_ADMIN
// - optional parameters
//   - $_SESSION['username'] for "welcome message"



// Handle loginname and password (also made encryption)
$user_loginname = $_POST["username"];
$user_pwd = encrypt_password($_POST["password"]);
// remove pw in $_POST
unset($_POST["password"]);

// authentication type
message($debug, "Authentication type: ".AUTH_TYPE);
message($debug, "Encryption type: ".PASSWD_ENC);




function prepare_password ($password){
    # if encryption is also in password, it has to be in UPPERCASE ( {crypt} -> {CRYPT}, {MD5} etc...
    if ( preg_match('/(^\{.*\})(.*)/', $password, $matched) ){
        # will find [0]:whole string, [1]:crypt type, [2]:password
        $crypt = strtoupper($matched[1]);
        $pw = $matched[2];

        if ($crypt == "{CLEAR}"){
            // {Clear} info is not needed. cut away!
            $password = $pw;
        }else{
            $password = $crypt.$pw;
        }
    }

    return $password;

}

##
##
##
##############################################################################################
if (AUTH_TYPE == "file"){
    //Read file
    $filename = "config/.file_accounts.php";
    if ( (file_exists($filename)) AND ( $file = fopen($filename, "r") ) ){
        while ( $row = fgets($file) ) {
            # Do not use commented rows(#) or blank rows
            if ( $row != "" AND !ereg("^\s*(#|\/\*|\*\/|<\?|\?>)", $row) ){
                $user = explode("::", $row);
                # check uppercase crypt part, remove {CLEAR} if exists
                $password = prepare_password($user[1]);
    
                $user_array[$user[0]] = array("password" => $password,     "group" => $user[2],   "name" => $user[3]);
            }
        }
        fclose($file);
        // Authentification
        if ( isset($user_array["$user_loginname"]) ){
            if ( $user_array[$user_loginname]["password"] == $user_pwd ){
                //pw ok, set group
                $_SESSION['group']      = $user_array[$user_loginname]["group"];
 
                // get Welcome name
                if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($user_array[$user_loginname]["name"]) ){
                    $_SESSION["userinfos"]['username']   = $user_array[$user_loginname]["name"];
                }else{
                    $_SESSION["userinfos"]['username']   = $user_loginname;
                }
            }else{
                //PW not ok, login failed
                message($error, TXT_LOGIN_FAILED);
            }
        }else{
            //User not found
            message($error, TXT_LOGIN_FAILED);
        }
    
    }else{
        //FILE not found
        message($error, "Account-file not found : $filename");
    }


##############################################################################################

}elseif (AUTH_TYPE == "sql"){
    // login check function
    ##########
    function auth_by_sql($username, $passwd, $sqlquery){
        // Connect to the database
        $auth_db_link = mysql_connect(AUTH_DBHOST,AUTH_DBUSER, AUTH_DBPASS, TRUE);
        mysql_select_db(AUTH_DBNAME, $auth_db_link);
        $result = db_handler($sqlquery, 'getOne', "Authentication by sql");
        mysql_close($auth_db_link);

        if ($result) {
            // get Welcome name
            if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($result) ){
                $_SESSION["userinfos"]['username'] = $result;
            }else{
                $_SESSION["userinfos"]['username']   = $user_loginname;
            }
            return TRUE;
        }else{
            message($error, TXT_LOGIN_FAILED);
            return FALSE;
        }

    }
    ##########


    // Prepare querys
    $auth_sqlquery_USER = AUTH_SQLQUERY_USER;
    $auth_sqlquery_USER = str_replace("!!!USERNAME!!!", $user_loginname, $auth_sqlquery_USER);
    $auth_sqlquery_USER = str_replace("!!!PASSWORD!!!", $user_pwd, $auth_sqlquery_USER);
    if ( defined("AUTH_SQLQUERY_ADMIN") ){
        $auth_sqlquery_ADMIN = AUTH_SQLQUERY_ADMIN;
        $auth_sqlquery_ADMIN = str_replace("!!!USERNAME!!!", $user_loginname, $auth_sqlquery_ADMIN);
        $auth_sqlquery_ADMIN = str_replace("!!!PASSWORD!!!", $user_pwd, $auth_sqlquery_ADMIN);
    }

    // Authentification
    if ( ( defined("AUTH_SQLQUERY_ADMIN") ) AND auth_by_sql($user_loginname, $user_pwd, $auth_sqlquery_ADMIN) ){
        $_SESSION['group'] = GROUP_ADMIN;
    }elseif ( auth_by_sql($user_loginname, $user_pwd, $auth_sqlquery_USER) ){
        $_SESSION['group'] = GROUP_USER;
    }else{
        message($error, TXT_LOGIN_FAILED);
    }

    # needed database reload, otherwise the connection is lost
    relaod_nconf_db_connection();

##############################################################################################

}elseif (AUTH_TYPE == "ldap") {
    $ldapconnection = ldap_connect(LDAP_SERVER, LDAP_PORT);
    ldap_set_option($ldapconnection, LDAP_OPT_PROTOCOL_VERSION, 3);

    # Check ldap connection
    if($ldapconnection) {

        # Try to logon user to ldap
        $ldap_user_dn = str_replace(USER_REPLACEMENT,$user_loginname,BASE_DN);
        $ldap_response = @ldap_bind($ldapconnection, $ldap_user_dn, $user_pwd);

        if($ldap_response and $user_loginname and $user_pwd) {
            # If user login was successfull, look for group
            # admins are in group : ADMIN_GROUP
            # normal nconf user are in group : USER_GROUP
            # all other do not have access

            // AdminUsers
            $sr = ldap_search($ldapconnection, GROUP_DN, ADMIN_GROUP);
            $results = ldap_get_entries($ldapconnection,$sr);
            $Admin_user_array = $results[0]["memberuid"];
            // remove field count
            unset($Admin_user_array["count"]);


            // BasicUsers
            $sr = ldap_search($ldapconnection, GROUP_DN, USER_GROUP);
            $results = ldap_get_entries($ldapconnection,$sr);
            $Basic_user_array = $results[0]["memberuid"];
            // remove field count
            unset($Basic_user_array["count"]);


            // Users Infos
            $justthese = array("cn");
            //$justthese = array("cn", "description", "uid");
            $sr = ldap_read($ldapconnection, $ldap_user_dn, "(objectclass=*)", $justthese);
            $results = ldap_get_entries($ldapconnection,$sr);

            // get Welcome name
            if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($results[0]["cn"][0]) ){
                $_SESSION["userinfos"]["username"]  = $results[0]["cn"][0];
            }else{
                $_SESSION["userinfos"]['username']  = $user_loginname;
            }

            //$_SESSION["userinfos"]["useremail"] = $results[0]["description"][0];
            //$_SESSION["userinfos"]["uid"]       = $results[0]["uid"][0];
     
            #Check if user is in Basic userlist
            #or in Admin userlist
            if (in_array($user_loginname, $Admin_user_array) ){
                $_SESSION['group'] = GROUP_ADMIN;
                message($info, $_SESSION["group"].' access granted', "yes");
            }elseif (in_array($user_loginname, $Basic_user_array) ){
                $_SESSION['group'] = GROUP_USER;
                message($info, $_SESSION["group"].' access granted', "yes");
            }else{
                message($error, TXT_LOGIN_NOT_AUTHORIZED);
            }
            

        } else {

            message($error, TXT_LOGIN_FAILED);

        }


    } else {

        message($error, "Can not connect to ldap server");

    }


}else{
    // no AUTH TYPE matched.. cant login :
    message($error, "No authentication type set in config, login restricted");

}




?>
