<?php
##
## Authentication
##

#
# enables/disables the use of users and passwords
# if disabled (0) NConf runs without user login / without authentication
define('AUTH_ENABLED', "0");

#
# select auth type
# possible values: [file|sql|ldap]
#
define('AUTH_TYPE', "file");

# Groups
define('GROUP_USER',       "user");
define('GROUP_ADMIN',      "admin");
define('GROUP_NOBODY',     "0");


###
###  Auth by "ldap"
###

# LDAP (the tree design must be pam_ldap and nss_ldap compliant)
define('LDAP_SERVER',      "ldaps://ldaphost.mydomain.com");

# The port to connect to. Not used when using URLs. Defaults to 389. (by PHP)
define('LDAP_PORT',        "389");

define('BASE_DN',          "uid=<username>,ou=People,dc=mydomain,dc=com");
define('USER_REPLACEMENT', "<username>");
define('GROUP_DN',         "ou=Group,dc=mydomain,dc=com");
define('ADMIN_GROUP',      "cn=nagiosadmin");
define('USER_GROUP',       "cn=sysadmin");

###
###  Auth by "sql"
###

# User database (can be any mysql DB)
define('AUTH_DBHOST',       "localhost");
define('AUTH_DBNAME',       "NConf");
define('AUTH_DBUSER',       "nconf");
define('AUTH_DBPASS',       "link2db");

#
# Defines if the user's full name should be displayed in the history and welcome message, 
# otherwise the username will be displayed.
#
define('AUTH_FEEDBACK_AS_WELCOME_NAME', "1");

# Custom SQL query to run in the user database.
# The query should return exactly one (1) record if:
# - the username exists
# - the password is correct
# - any additional attrs are set (optional for permission check etc.)

# INFO:
# The following queries are examples. They allow user authentication to be managed
# within the NConf DB itself. To enable this, you must configure additional attributes in
# the "contact" class (refer to the documentation for more details).
# Feel free to define your own queries, if you want to access any other existing user database.

# 
# if query matches, user will get limited access, for "normal users"
# !!!USERNAME!!! and !!!PASSWORD!!! will be replaced with the username and password from login page
# 
define('AUTH_SQLQUERY_USER',     '
SELECT attr_value AS username, id_item AS user_id
  FROM ConfigAttrs,ConfigValues,ConfigItems
 WHERE id_attr=fk_id_attr
 AND id_item=fk_id_item
 AND attr_name="alias"
  HAVING id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="contact_name"
   AND attr_value="!!!USERNAME!!!")
  AND id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="user_password"
   AND attr_value="!!!PASSWORD!!!")
  AND id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="nc_permission"
   AND attr_value="'.GROUP_USER.'");
');

#
#  ::OPTIONAL:: Define ADMIN access here :
# if query matches, user will get FULL ADMIN access, for Administrators
#
define('AUTH_SQLQUERY_ADMIN',     '
SELECT attr_value AS username, id_item AS user_id
  FROM ConfigAttrs,ConfigValues,ConfigItems
 WHERE id_attr=fk_id_attr
 AND id_item=fk_id_item
 AND attr_name="alias"
  HAVING id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="contact_name"
   AND attr_value="!!!USERNAME!!!")
  AND id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="user_password"
   AND attr_value="!!!PASSWORD!!!")
  AND id_item =(SELECT id_item FROM ConfigAttrs,ConfigValues,ConfigItems
   WHERE id_attr=fk_id_attr
   AND id_item=fk_id_item
   AND id_item=user_id
   AND attr_name="nc_permission"
   AND attr_value="'.GROUP_ADMIN.'");
');

?>
