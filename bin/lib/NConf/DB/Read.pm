##############################################################################
# "NConf::DB::Read" library
# A collection of shared functions for the NConf Perl scripts.
# Functions which execute read-only queries in the database.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-25 v0.1   A. Gargiulo   First release
#
##############################################################################

package NConf::DB::Read;

use strict;
use Exporter;
use DBI;
use NConf;
use NConf::DB;
use NConf::Logger;
use NConf::Helpers;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf::DB);
    @EXPORT      = qw(@NConf::DB::EXPORT getItemId getItemName getServiceId getAttrId getItemClass getConfigAttrs getConfigClasses getItemData getItems getItemsLinked checkLinkAsChild checkItemsLinked queryExecRead);
    @EXPORT_OK   = qw(@NConf::DB::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub getItemId {
    &logger(5,"Entered getItemId()");

    # SUB use: fetch the ID of any item in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item name (the contents of the naming-attr)
    # 1: the class of the item

    # Return values:
    # 0: a scalar containing the ID of the item, undef on falure

    ################################

    # read arguments passed
    my $item_name = shift;
    my $item_class = shift;

    unless($item_name && $item_class){&logger(1,"getItemId(): Missing argument(s). Aborting.")}

    if($item_class eq "service"){&logger(1,"Illegal function call: getItemId() cannot be used for services. Use getServiceId() instead. Aborting.")}

    my $sql = "SELECT ConfigValues.fk_id_item FROM ConfigValues, ConfigAttrs, ConfigClasses 
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr 
                    AND ConfigAttrs.fk_id_class=ConfigClasses.id_class 
                    AND ConfigAttrs.naming_attr='yes' 
                    AND ConfigClasses.config_class='$item_class' 
                    AND ConfigValues.attr_value='$item_name'
                    LIMIT 1";

    my $id_item = &queryExecRead($sql, "Fetching id_item for $item_class '$item_name'", "one");

    if($id_item){return $id_item}
    else{return undef}
}

##############################################################################

sub getItemName {
    &logger(5,"Entered getItemName()");

    # SUB use: fetch the name (the value of the naming attr) of any item in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID

    # Return values:
    # 0: a scalar containing the name of the item, undef on falure

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getItemName(): Missing argument(s). Aborting.")}

    my $sql = "SELECT ConfigValues.attr_value FROM ConfigValues, ConfigAttrs
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr
                    AND ConfigAttrs.naming_attr='yes'
                    AND ConfigValues.fk_id_item=$id_item
                    LIMIT 1";

    my $item_name = &queryExecRead($sql, "Fetching item name for ID '$id_item'", "one");

    if($item_name){return $item_name}
    else{return undef}
}

##############################################################################

sub getServiceId {
    &logger(5,"Entered getServiceId()");

    # SUB use: fetch the ID of a service in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the service name (the contents of the naming-attr)
    # 1: the ID of the parent host (value in 'host_name' attr)

    # Return values:
    # 0: a scalar containing the ID of the service, undef on falure

    ################################

    # read arguments passed
    my $service_name   = shift;
    my $parent_host_id = shift;

    unless($service_name && $parent_host_id){&logger(1,"getServiceId(): Missing argument(s). Aborting.")}

    my $sql = "SELECT ConfigItems.id_item FROM ConfigItems, ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr
                    AND ConfigValues.fk_id_item=ConfigItems.id_item
                    AND ConfigAttrs.fk_id_class=ConfigClasses.id_class
                    AND ItemLinks.fk_id_item=ConfigItems.id_item
                    AND ConfigClasses.config_class='service'
                    AND ConfigAttrs.naming_attr='yes'
                    AND ConfigValues.attr_value='$service_name'
                    AND ItemLinks.fk_item_linked2='$parent_host_id'
                    LIMIT 1"; 

    my $id_srv = &queryExecRead($sql, "Fetching id_item for service '$service_name' linked to host ID '$parent_host_id'", "one");

    if($id_srv){return $id_srv}
    else{return undef}
}

##############################################################################

sub getAttrId {
    &logger(5,"Entered getAttrId()");

    # SUB use: fetch attr ID based on attr name and class name

    # SUB specs: ###################

    # Expected arguments:
    # 0: attribute name
    # 1: class name

    # Return values:
    # 0: attr ID, undef on failure

    ################################

    # read arguments passed
    my $attr_name  = shift;
    my $class_name = shift;

    $class_name = lc($class_name);

    unless($attr_name && $class_name){&logger(1,"getAttrId(): Missing argument(s). Aborting.")}

    my $id_attr = undef;
    if($NC_db_caching == 1 && exists($NC_dbcache_getAttrId{$class_name}->{$attr_name})){
        # if cached, read from cache
        &logger(4,"Fetching id_attr for '$attr_name' (cached)");
        $id_attr = $NC_dbcache_getAttrId{$class_name}->{$attr_name};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigAttrs.id_attr FROM ConfigAttrs, ConfigClasses 
                    WHERE ConfigAttrs.fk_id_class=ConfigClasses.id_class 
                        AND ConfigClasses.config_class='$class_name' 
                        AND ConfigAttrs.attr_name ='$attr_name' 
                        LIMIT 1";

        $id_attr = &queryExecRead($sql, "Fetching id_attr for '$attr_name'", "one");

        # save to cache
        $NC_dbcache_getAttrId{$class_name}->{$attr_name} = $id_attr;
    }

    if($id_attr){return $id_attr}
    else{return undef}
}

##############################################################################

sub getItemClass {
    &logger(5,"Entered getItemClass()");

    # SUB use: fetch the class of an item based on the item ID

    # SUB specs: ###################

    # Expected arguments:
    # 0: item ID

    # Return values:
    # 0: class name, undef on failure

    ################################

    # read arguments passed
    my $id_item   = shift;

    unless($id_item){&logger(1,"getItemClass(): Missing argument(s). Aborting.")}

    my $item_class = undef;
    if($NC_db_caching == 1 && exists($NC_dbcache_getItemClass{$id_item})){
        # if cached, read from cache
        &logger(4,"Fetching class name for item ID '$id_item' (cached)");
        $item_class = $NC_dbcache_getItemClass{$id_item};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigClasses.config_class FROM ConfigClasses, ConfigItems 
                    WHERE ConfigItems.fk_id_class=ConfigClasses.id_class 
                    AND ConfigItems.id_item=$id_item";

        $item_class = &queryExecRead($sql, "Fetching class name for item ID '$id_item'", "one");

        # save to cache
        $NC_dbcache_getItemClass{$id_item} = $item_class;
    }

    if($item_class){return $item_class}
    else{return undef}
}

##############################################################################

sub getConfigAttrs {
    &logger(5,"Entered getConfigAttrs()");

    # SUB use: get a list of all attrs plus their properties (datatype, maxlength, mandatory etc.)

    # SUB specs: ###################

    # Return values:
    # 0: A hash containing the following data structure:
    #    $class_attrs_hash{'class name'}->{'attr name'}->{'property'}

    ################################

    # if cached, read from cache
    if($NC_db_caching == 1 && keys(%NC_dbcache_getConfigAttrs)){
        &logger(4,"Fetching all attributes from ConfigAttrs table (cached)");
        return %NC_dbcache_getConfigAttrs;
    }

    # if not cached, run DB query
    my $q_attr = "SELECT fk_id_class AS class_id,
                    (SELECT config_class FROM ConfigClasses WHERE id_class=class_id) AS belongs_to_class, 
                    id_attr,
                    attr_name,
                    datatype, 
                    max_length, 
                    poss_values, 
                    predef_value, 
                    mandatory, 
                    naming_attr, 
                    link_as_child, 
                    write_to_conf,
                    fk_show_class_items,
                    (SELECT config_class FROM ConfigClasses WHERE id_class=fk_show_class_items) AS assign_to_class
                        FROM ConfigAttrs 
                        ORDER BY fk_id_class, id_attr";

    my %config_attrs = &queryExecRead($q_attr,"Fetching all attributes from ConfigAttrs table","all2","id_attr");

    # get all available classes
    my %classes;
    foreach my $attr_id (keys(%config_attrs)){
        $classes{$config_attrs{$attr_id}->{'belongs_to_class'}} = $config_attrs{$attr_id}->{'belongs_to_class'};
    }

    # feed all attrs and their properties into a hash structure:
    #    $class_attrs_hash{'class name'}->{'attr name'}->{'property'}

    my %class_attrs_hash;
    foreach my $class (keys(%classes)){
        my %attrs_hash;
        foreach my $attr_id (keys(%config_attrs)){
            if($config_attrs{$attr_id}->{'belongs_to_class'} eq $class){
                $attrs_hash{$config_attrs{$attr_id}->{'attr_name'}} = $config_attrs{$attr_id};
            }
        }
        $class_attrs_hash{$class} = \%attrs_hash;
    }
    
    # save to cache
    %NC_dbcache_getConfigAttrs = %class_attrs_hash;

    return %class_attrs_hash;
}

##############################################################################

sub getConfigClasses {
    &logger(5,"Entered getConfigClasses()");

    # SUB use: get a list of all classes plus their properties (out_file, nagios_object)

    # SUB specs: ###################

    # Return values:
    # 0: A hash containing the following data structure:
    #    $class_hash{'class name'}->{'property'}

    ################################

    # if cached, read from cache
    if($NC_db_caching == 1 && keys(%NC_dbcache_getConfigClasses)){
        &logger(4,"Fetching all classes from ConfigClasses table (cached)");
        return %NC_dbcache_getConfigClasses;
    }

    # if not cached, run DB query
    my $q_class = "SELECT config_class, nagios_object, out_file FROM ConfigClasses ORDER BY ordering";

    my %class_hash = &queryExecRead($q_class,"Fetching all classes from ConfigClasses table","all2","config_class");

    # save to cache
    %NC_dbcache_getConfigClasses = %class_hash;

    return %class_hash;
}

##############################################################################

sub getItemData {
    &logger(5,"Entered getItemData()");

    # SUB use: fetch all attributes and values assigned to an item over the ConfigValues table
    #          (i.e. the data of 'text', 'select' and 'password' attributes)

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID

    # Return values:
    # 0: a reference to an array containing all attributes of an item, their values and the write_to_conf flag

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getItemData(): Missing argument(s). Aborting.")}

    my $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigAttrs,ConfigValues
                  WHERE id_attr=fk_id_attr
                  AND fk_id_item=$id_item
                  ORDER BY naming_attr,ordering";

    my @attrs = &queryExecRead($sql, "Fetching all normal attributes and values for item '$id_item'", "all");

    # replace NConf macros with the respective value
    foreach my $attr (@attrs){
        $attr->[1] = &replaceMacros($attr->[1]);
    }

    return(@attrs);
}

##############################################################################

sub getItems {
    &logger(5,"Entered getItems()");

    # SUB use: fetch all items of a certain class (e.g. all contacts)

    # SUB specs: ###################

    # Expected arguments:
    # 0: class name
    # 1: optional: 1 = also return item names

    # Return values:
    # 0: an array containing references to arrays with two values: 
    #    [0] the item ID
    #    [1] optional: the name of the item (value of the naming attr)

    ################################

    # read arguments passed
    my $class = shift;
    my $item_names = shift;

    unless($class){&logger(1,"getItems(): Missing argument(s). Aborting.")}

    my $sql = undef;

    if($item_names == 1){
        $sql = "SELECT fk_id_item,attr_value
                    FROM ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_attr=fk_id_attr
                       AND naming_attr='yes'
                       AND id_class=fk_id_class
                       AND config_class = '$class'
                       ORDER BY attr_value";
    }else{
        $sql = "SELECT id_item FROM ConfigItems,ConfigClasses
                    WHERE id_class=fk_id_class
                       AND config_class = '$class'
                       ORDER BY id_item";
    }

    my @items = &queryExecRead($sql, "Fetching all items of type '$class'", "all");

    return @items;
}

##############################################################################

sub getItemsLinked {
    &logger(5,"Entered getItemsLinked()");

    # SUB use: fetch all attributes and values linked to an item over the ItemLinks table
    #          (i.e. the data of 'assign_one', 'assign_many' and 'assign_cust_order' attributes)

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID
    # 1: the class of the item

    # Return values:
    # 0: a reference to an array containing all linked attributes and values, the write_to_conf flag, 
    #    as well as additional linking information such as the linked item ID etc.

    ################################

    # read arguments passed
    my $id_item = shift;
    my $class   = shift;

    unless($id_item){&logger(1,"getItemsLinked(): Missing argument(s). Aborting.")}

    my $sql = "SELECT attr_name,attr_value,write_to_conf,fk_item_linked2 AS item_id,cust_order,ordering,
                   (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                    WHERE id_attr=fk_id_attr
                        AND attr_name='monitored_by'
                        AND fk_id_item=item_id) AS monitored_by
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND (link_as_child <> 'yes' OR link_as_child IS NULL)
                        AND ItemLinks.fk_id_item=$id_item
                UNION
                SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,cust_order,ordering,
                    (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                     WHERE id_attr=fk_id_attr
                         AND attr_name='monitored_by'
                         AND fk_id_item=item_id) AS monitored_by
                     FROM ConfigValues,ItemLinks,ConfigAttrs
                     WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND link_as_child = 'yes'
                        AND fk_item_linked2=$id_item
                        ORDER BY cust_order,ordering";

    my @attrs = &queryExecRead($sql, "Fetching all linked attributes and values for item '$id_item'", "all");

    # if the item being processed is a "host", check if it's parents are also monitored,
    # if not, remove them from the list of "parents"

    if($class eq "host"){
        my $rowcount = 0;
        my @parents2del = undef;
        foreach my $attr (@attrs){
            if($attr->[0] eq "parents" && $attr->[6] eq ""){push(@parents2del,$rowcount)}
            $rowcount++;
        }

        foreach my $row (@parents2del){undef($attrs[$row])}
    }

    @attrs = &makeValuesDistinct(@attrs);

    return(@attrs);
}

##############################################################################

sub checkLinkAsChild {
    &logger(5,"Entered checkLinkAsChild()");

    # SUB use: check if "link_as_child" flag is set for a specific attribute

    # SUB specs: ###################

    # Expected arguments:
    # 0: attr ID

    # Return values:
    # 0: 'true' if link_as_child = "yes", 
    #    'false' if link_as_child = "no", 
    #     undef on failure

    ################################

    # read arguments passed
    my $id_attr = shift;

    unless($id_attr){&logger(1,"checkLinkAsChild(): Missing argument(s). Aborting.")}

    my $qres;
    if($NC_db_caching == 1 && exists($NC_dbcache_checkLinkAsChild{$id_attr})){
        # if cached, read from cache
        &logger(4,"Fetching 'link_as_child' flag for attr ID '$id_attr' (cached)");
        $qres = $NC_dbcache_checkLinkAsChild{$id_attr};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigAttrs.link_as_child FROM ConfigAttrs WHERE id_attr = $id_attr";

        $qres = &queryExecRead($sql, "Fetching 'link_as_child' flag for attr ID '$id_attr'", "one");

        # save to cache
        $NC_dbcache_checkLinkAsChild{$id_attr} = $qres;
    }

    if($qres eq "yes"){return "true"}
    #elsif($qres eq "no" || $qres eq "" || $qres eq "NULL"){return "false"} # link_as_child ENUM has been changed to yes/no
    elsif($qres eq "no"){return "false"}
    else{
        &logger(2,"Failed to determine if 'link_as_child' flag is set for attr ID '$id_attr'. Aborting checkLinkAsChild().");
        return undef;
    }
}

##############################################################################

sub checkItemsLinked {
    &logger(5,"Entered checkItemsLinked()");

    # SUB use: check if two items are linked via the ItemLinks table

    # SUB specs: ###################

    # Expected arguments:
    # 0: ID of item that will be linked
    # 1: ID of item to link the first one to
    # 2: name of NConf attr (of type assign_one/many/cust_order)

    # Return values:
    # 0: 'true' if items already linked, 'false' if not, undef on failure

    # This function automatically checks and considers the "link_as_child" flag

    ################################

    # read arguments passed
    my $id_item = shift;
    my $id_item_linked2 = shift;
    my $attr_name = shift;

    unless($id_item && $id_item_linked2 && $attr_name){&logger(1,"checkItemsLinked(): Missing argument(s). Aborting.")}

    # fetch class name
    my $class_name = &getItemClass($id_item);
    unless($class_name){
        &logger(2,"Failed to resolve the class name for item ID '$id_item' using getItemClass(). Aborting checkItemsLinked().");
        return undef;
    }

    # fetch id_attr
    my $id_attr = &getAttrId($attr_name, $class_name);
    unless($id_attr){
        &logger(2,"Failed to resolve attr ID for '$attr_name' using getAttrId(). Aborting checkItemsLinked().");
        return undef;
    }

    # check link_as_child
    my $las = &checkLinkAsChild($id_attr);
    unless($las){
        &logger(2,"Failed to check if 'link_as_child' flag is set using checkLinkAsChild(). Aborting checkItemsLinked().");
        return undef;
    }

    # check if items are linked
    my $sql = undef;

    if($las eq "true"){
        $sql = "SELECT 'true' AS item_linked FROM ItemLinks 
                  WHERE fk_id_item=$id_item_linked2 
                    AND fk_item_linked2=$id_item 
                    AND fk_id_attr=$id_attr 
                    LIMIT 1";
    }else{
        $sql = "SELECT 'true' AS item_linked FROM ItemLinks 
                  WHERE fk_id_item=$id_item 
                    AND fk_item_linked2=$id_item_linked2 
                    AND fk_id_attr=$id_attr 
                    LIMIT 1";
    }

    my $qres = &queryExecRead($sql, "Checking if items '$id_item' and '$id_item_linked2' are linked", "one");

    if($qres eq "true"){return 'true'}
    else{return 'false'}
}

##############################################################################

sub queryExecRead {
    &logger(5,"Entered queryExecRead()");

    # SUB use: Execute a query which reads data from the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: The SQL query
    # 1: The message to log for the query
    # 2: The format of the return value:
    #    "one"  return a single scalar value (first value returned by the query)
    #    "row"  return an array containing all values of one row
    #    "row2" return a hash containing all values of one row (with attr names as keys)
    #    "all"  return an array that contains one array reference per row of data
    #    "all2" return a hash containing one hash reference per row of data (with a specified attr as key)
    #
    # 3: Only if "all2": The attr name to use as key for the hash that is returned

    # Return values:
    # 0: The output of the query in the specified format, 
    #    undef if query returns no rows / on failure

    ################################

    # read arguments passed
    my $sql = shift;
    my $msg = shift;
    my $ret = shift;
    my $key = shift;

    unless($sql && $msg && $ret){&logger(1,"queryExecRead(): Missing argument(s). Aborting.")}
    if ($ret eq "all2" && !$key){&logger(1,"queryExecRead(): Missing argument(s). Aborting.")}

    my $dbh  = &dbConnect;

    &logger(4,$msg);
    &logger(5,$sql,1);
    my $sth = $dbh->prepare($sql);
    $sth->execute();

    if($ret eq "one"){
        my @qres = $sth->fetchrow_array;
        if($qres[0]){
            &logger(5,"Query result: '$qres[0]'");
            return $qres[0];
        }else{return undef}

    }elsif($ret eq "row"){
        my $array_ref = $sth->fetchrow_arrayref;
        if($array_ref){return @$array_ref}
        else{return undef}

    }elsif($ret eq "row2"){
        my $hash_ref = $sth->fetchrow_hashref;
        if($hash_ref){return %{$hash_ref}}
        else{return undef}

    }elsif($ret eq "all"){
        my $array_ref = $sth->fetchall_arrayref;
        if($array_ref){return @$array_ref}
        else{return undef}

    }elsif($ret eq "all2"){
        my $hash_ref = $sth->fetchall_hashref($key);
        if($hash_ref){return %{$hash_ref}}
        else{return undef}
    }
}

##############################################################################

#sub get... {
#    &logger(5,"Entered get...()");

    # SUB use: fetch ...

    # SUB specs: ###################

    # Expected arguments:
    # 0: ...
    # 1: ...

    # Return values:
    # 0: ..., undef on failure

    ################################

    # read arguments passed
#    my $item = shift;

#    unless($item){&logger(1,"get...(): Missing argument(s). Aborting.")}

#    my $sql = "SELECT...";

#    my @qres = &queryExecRead($sql, "Fetching ... for '$item'", "all");

#    if(@qres){return @qres}
#    else{return undef}
#}

##############################################################################

1;

__END__

}
