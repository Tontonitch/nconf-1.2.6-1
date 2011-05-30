##############################################################################
# "NConf::ExportNagios" library
# A collection of shared functions for the NConf Perl scripts.
# Functions needed to generate and export Nagios configuration files from NConf.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-10-08 v0.1   A. Gargiulo   First release
#
# To-Do:
# This module was migrated from the former generate_config.pl script.
# Functions which access the database need to be consolidated with 
# NConf::DB::Read, if possible.
#
##############################################################################
 
package NConf::ExportNagios;

use strict;
use Exporter;
use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::Helpers;
use NConf::Logger;
use Tie::IxHash;    # preserve hash order

# debug
#&setLoglevel(4);

# trace
#&setLoglevel(5);

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT create_monitor_config create_collector_config create_global_config);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

# Global Vars
use vars qw($fattr $fval); # Superglobals
my ($root_path, $output_path, $global_path, $test_path, $monitor_path, $collector_path);
my (@superadmins, @oncall_groups, @global_cfg_files, @server_cfg_files);
my (%files_written);

# Fetch options from main NConf config
@superadmins   = &readNConfConfig(NC_CONFDIR."/nconf.php","SUPERADMIN_GROUPS","array");
@oncall_groups = &readNConfConfig(NC_CONFDIR."/nconf.php","ONCALL_GROUPS","array");
$root_path     = &readNConfConfig(NC_CONFDIR."/nconf.php","NCONFDIR","scalar");

# Define output structure
$output_path = "$root_path/temp";
$test_path   = "$root_path/temp/test";
$global_path = "$output_path/global";

my $timestamp = time();
my $mon_count = 0;

##############################################################################
### S U B S ##################################################################
##############################################################################

# SUB create_monitor_config
# Generate Nagios configuration files for monitor server(s)

sub create_monitor_config {
    &logger(5,"Entered create_monitor_config()");

    # fetch all monitor servers
    my @monitors = &getItems("nagios-monitor",1);

    foreach my $row (@monitors){

        &logger(3,"Generating config for Nagios-monitor '$row->[1]'");

        # store monitor name separately
        $NC_macro_values{'NAGIOS_SERVER_NAME'} = $row->[1];

        # create output directory
        $row->[1] =~ s/-|\s/_/g;
        $monitor_path = "$output_path/$row->[1]";
        if(-e $monitor_path){rename($monitor_path,$monitor_path."_".time) or &logger(1,"Could not rename $monitor_path")}
        &logger(4,"Creating output directory '$monitor_path'");
        mkdir($monitor_path,0755) or &logger(1,"Could not create $monitor_path");

        # create hosts.cfg and extended_host_info.cfg files for monitor server
        &create_monitor_hosts_extinfo_files($row->[0]);

        # create services.cfg and extended_service_info.cfg files for monitor server
        &create_monitor_services_extinfo_files($row->[0]);

        # create nagios.cfg file for monitor server to test generated config
        &create_test_cfg($row->[1]);
        @server_cfg_files = undef;

	    $mon_count++;
    }
}


########################################################################################
# SUB create_collector_config
# Generate Nagios configuration files for collector servers

sub create_collector_config {
    &logger(5,"Entered create_collector_config()");

    # fetch all collector servers
    my @collectors = &getItems("nagios-collector",1);
    my $col_count = 0;

    foreach my $row (@collectors){

        &logger(3,"Generating config for Nagios-collector '$row->[1]'");

        # store collector name separately
        $NC_macro_values{'NAGIOS_SERVER_NAME'} = $row->[1];

        # create output directory
        $row->[1] =~ s/-|\s/_/g;
        $row->[1] =~ s/Nagios|Icinga/collector/i;
        $collector_path = "$output_path/$row->[1]";
        if(-e $collector_path){rename($collector_path,$collector_path."_".time) or &logger(1,"[ERROR] Could not rename $collector_path")}
        &logger(4,"Creating output directory '$collector_path'");
        mkdir($collector_path,0755) or &logger(1,"Could not create $collector_path");

        # create hosts.cfg file for each collector server
        &create_collector_hosts_file($row->[0]);

        # create services.cfg file for each collector server
        &create_collector_services_file($row->[0]);

        # create nagios.cfg file for each collector server to test generated config
        &create_test_cfg($row->[1]);
        @server_cfg_files = undef;

        $col_count++;
    }

    unless($col_count > 0){&logger(2,"No collector servers defined. Specify at least one.")}
}


########################################################################################
# SUB create_global_config
# Process global config files. These only need to be created once.

sub create_global_config {
    &logger(5,"Entered create_global_config()");
    &logger(3,"Generating global config files");

    # create "global" folder
    if(-e $global_path){rename($global_path,$global_path."_".$timestamp)}
    &logger(4,"Creating output directory '$global_path'");
    mkdir($global_path,0755) or &logger(1,"Could not create $global_path");

    # fetch all classes in ConfigClasses table
    my %class_info = &getConfigClasses();
    foreach my $class (keys(%class_info)){
        
        # skip server-specific classes or classes with no 'out_file'
        if($class eq "host" || $class eq "hostgroup" || $class eq "service" || $class eq "servicegroup"){next}
        unless($class_info{$class}->{'out_file'}){
            if($class eq "contact" || $class eq "contactgroup" || $class eq "checkcommand" || $class eq "misccommand" || $class eq "timeperiod"){
                &logger(2,"No ".$class."s were exported. Make sure 'out_file' is set properly for the correspondig class.");
            }
            next;
        }

        # fetch all object ID's for current class
        my @class_items = &getItems("$class");

        # write an output file for each class in ConfigClasses table
        push(@global_cfg_files, "$global_path/$class_info{$class}->{'out_file'}");
        &write_file("$global_path/$class_info{$class}->{'out_file'}","$class","$class_info{$class}->{'nagios_object'}",\@class_items);

        # also generate a .htpasswd file, if there are contacts with a password attr
        if($class eq "contact"){
            &write_htpasswd_file("$global_path/nagios.htpasswd",\@class_items);
        }
    }
}

########################################################################################
# SUB create_monitor_hosts_extinfo_files
# Fetch host and hostgroup data for monitor server and write hosts.cfg and extended_host_info.cfg files

sub create_monitor_hosts_extinfo_files {
    &logger(5,"Entered create_monitor_hosts_extinfo_files()");

    my $sql = undef;
    my @monitor_params;

    # fetch all host ID's that have a collector assigned
    $sql = "SELECT id_item AS item_id
                FROM ConfigItems,ConfigClasses
                WHERE id_class=fk_id_class
                    AND config_class = 'host'
                    HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                                WHERE fk_item_linked2=id_item
                                    AND id_class=fk_id_class
                                    AND config_class = 'nagios-collector'
                                    AND fk_id_item=item_id) <> ''";

    my @hosts = &queryExecRead($sql, "Fetching all host ID's that have a collector assigned", "all");

    # fetch monitor-specific options, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND naming_attr='no'
                   AND (attr_name='active_checks_enabled'
                           OR attr_name='passive_checks_enabled'
                           OR attr_name='notifications_enabled')
                   AND fk_id_item=$_[0]
                   ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching monitor-specific options", "all");
    foreach (@attrs1){push(@monitor_params,$_)}

    # fetch monitor-specific host-templates, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @host_tpl = &queryExecRead($sql, "Fetching monitor-specific host-templates", "all");
    foreach (@host_tpl){push(@monitor_params,$_)}

    # fetch all hostgroup ID's
    my @hostgroups = &getItems("hostgroup");

    # fetch all classes in ConfigClasses table
    my %class_info = &getConfigClasses();

    # use output file name from ConfigClasses table
    if($class_info{'host'}->{'out_file'}){
        push(@server_cfg_files, "$monitor_path/$class_info{'host'}->{'out_file'}");
        &write_file("$monitor_path/$class_info{'host'}->{'out_file'}","host","$class_info{'host'}->{'nagios_object'}",\@hosts,\@monitor_params);
    }else{
        &logger(2,"No hosts were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    if($class_info{'hostgroup'}->{'out_file'}){
        push(@server_cfg_files, "$monitor_path/$class_info{'hostgroup'}->{'out_file'}");
        &write_file("$monitor_path/$class_info{'hostgroup'}->{'out_file'}","hostgroup","$class_info{'hostgroup'}->{'nagios_object'}",\@hostgroups);
    }else{
        &logger(2,"No hostgroups were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    push(@server_cfg_files, "$monitor_path/extended_host_info.cfg");
    &write_file("$monitor_path/extended_host_info.cfg","hostextinfo","hostextinfo",\@hosts);

}


########################################################################################
# SUB create_monitor_services_extinfo_files
# Fetch service and servicegroup data for monitor server and write services.cfg and extended_service_info.cfg files

sub create_monitor_services_extinfo_files {
    &logger(5,"Entered create_monitor_services_extinfo_files()");

    my $sql = undef;
    my @monitor_params;

    # fetch all host ID's that have a collector assigned and if a host is a collector itself
    $sql = "SELECT id_item AS item_id,
               (SELECT attr_value FROM ConfigValues,ConfigAttrs
                    WHERE id_attr=fk_id_attr
                    AND attr_name='host_is_collector'
                    AND fk_id_item=item_id) AS host_is_collector
               FROM ConfigItems,ConfigClasses
               WHERE id_class=fk_id_class
                   AND config_class = 'host'
                   HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                      WHERE fk_item_linked2=id_item
                      AND id_class=fk_id_class
                      AND config_class = 'nagios-collector'
                      AND fk_id_item=item_id) <> ''";

    my @hosts = &queryExecRead($sql, "Fetching all host ID's that have a collector assigned", "all");

    # fetch monitor-specific options, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
               AND naming_attr='no'
               AND fk_id_item=$_[0]
               ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching monitor-specific options", "all");
    foreach (@attrs1){push(@monitor_params,$_)}

    # fetch monitor-specific service-templates, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @srv_tpl = &queryExecRead($sql, "Fetching monitor-specific service-templates", "all");
    foreach (@srv_tpl){push(@monitor_params,$_)}

    # fetch 'stale_service_command' attr for nagios-monitor
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'stale_service_command' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        LIMIT 1";

    # expect exactly one row to be returned
    push(@monitor_params,&queryExecRead($sql, "Fetching 'stale_service_command' attr", "all"));

    # fetch all service ID's of hosts that have a collector assigned, also pass
    # the collector's name and if a host is a collector itself
    my @services = undef;
    foreach my $host (@hosts){
        $sql = "SELECT ItemLinks.fk_id_item AS item_id,'$host->[1]','$host->[2]' FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND fk_id_class=id_class
                        AND config_class = 'service'
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND ItemLinks.fk_item_linked2='$host->[0]'
                        ORDER BY attr_value";

        my @queryref = &queryExecRead($sql, "Fetching service ID's for host '$host->[0]', assigning collector to service", "all");
        foreach my $service (@queryref){push(@services, $service)}
    }

    # remove first array element if it's empty
    unless($services[0]){shift(@services)}

    # fetch all servicegroup ID's
    my @servicegroups = &getItems("servicegroup");

    # fetch all classes in ConfigClasses table
    my %class_info = &getConfigClasses();

    # use output file name from ConfigClasses table
    if($class_info{'service'}->{'out_file'}){
        push(@server_cfg_files, "$monitor_path/$class_info{'service'}->{'out_file'}");
        &write_file("$monitor_path/$class_info{'service'}->{'out_file'}","service","$class_info{'service'}->{'nagios_object'}",\@services,\@monitor_params);
    }else{
        &logger(2,"[WARN] No services were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    if($class_info{'servicegroup'}->{'out_file'}){
        push(@server_cfg_files, "$monitor_path/$class_info{'servicegroup'}->{'out_file'}");
        &write_file("$monitor_path/$class_info{'servicegroup'}->{'out_file'}","servicegroup","$class_info{'servicegroup'}->{'nagios_object'}",\@servicegroups);
    }else{
        &logger(2,"[WARN] No servicegroups were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    push(@server_cfg_files, "$monitor_path/extended_service_info.cfg");
    &write_file("$monitor_path/extended_service_info.cfg","serviceextinfo","serviceextinfo",\@services);

}


########################################################################################
# SUB create_collector_hosts_file
# Fetch host and hostgroup data for collector servers

sub create_collector_hosts_file {
    &logger(5,"Entered create_collector_hosts_file()");

    my $sql = undef;
    my @collector_params;

    # fetch all host ID's that are assigned to the collector
    $sql = "SELECT id_item AS item_id FROM ConfigItems,ConfigClasses
                WHERE id_class=fk_id_class
                AND config_class = 'host'
                HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                    WHERE fk_item_linked2=id_item
                    AND id_class=fk_id_class
                    AND config_class = 'nagios-collector'
                    AND fk_id_item=item_id) = $_[0]";

    my @hosts = &queryExecRead($sql, "Fetching all host ID's that are assigned to the current collector", "all");

    # fetch collector-specific options, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
                WHERE id_attr=fk_id_attr
                    AND naming_attr='no'
                    AND (attr_name='active_checks_enabled'
                         OR attr_name='passive_checks_enabled'
                         OR attr_name='notifications_enabled')
                    AND fk_id_item=$_[0]
                    ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching collector-specific options", "all");
    foreach (@attrs1){push(@collector_params,$_)}

    # fetch collector-specific host-templates, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @host_tpl = &queryExecRead($sql, "Fetching collector-specific host-templates", "all");
    foreach (@host_tpl){push(@collector_params,$_)}

    # fetch all hostgroup ID's
    my @hostgroups = &getItems("hostgroup");

    # add the collector's ID to the list of hostgroup ID's
    foreach my $entry (@hostgroups){
        $entry->[1] = $_[0];
    }

    # fetch all classes in ConfigClasses table
    my %class_info = &getConfigClasses();

    # use output file name from ConfigClasses table
    if($class_info{'host'}->{'out_file'}){
        push(@server_cfg_files, "$collector_path/$class_info{'host'}->{'out_file'}");
        &write_file("$collector_path/$class_info{'host'}->{'out_file'}","host","$class_info{'host'}->{'nagios_object'}",\@hosts,\@collector_params);
    }else{
        &logger(2,"No hosts were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    if($class_info{'hostgroup'}->{'out_file'}){
        push(@server_cfg_files, "$collector_path/$class_info{'hostgroup'}->{'out_file'}");
        &write_file("$collector_path/$class_info{'hostgroup'}->{'out_file'}","hostgroup","$class_info{'hostgroup'}->{'nagios_object'}",\@hostgroups);
    }else{
        &logger(2,"No hostgroups were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    # if there are no Monitor servers present, also generare "extinfo" files for every Collector
    # (it is assumed that Collector config is always generated after Monitor config, so $mon_count==0)
    if($mon_count==0){
        push(@server_cfg_files, "$collector_path/extended_host_info.cfg");
        &write_file("$collector_path/extended_host_info.cfg","hostextinfo","hostextinfo",\@hosts);
    }
}


########################################################################################
# SUB create_collector_services_file
# Fetch service and servicegroup data for collector servers

sub create_collector_services_file {
    &logger(5,"Entered create_collector_services_file()");

    my $sql = undef;
    my @collector_params;

    # fetch all host ID's that are assigned to the collector
    $sql = "SELECT id_item AS item_id FROM ConfigItems,ConfigClasses
                WHERE id_class=fk_id_class
                AND config_class = 'host'
                HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                    WHERE fk_item_linked2=id_item
                    AND id_class=fk_id_class
                    AND config_class = 'nagios-collector'
                    AND fk_id_item=item_id) = $_[0]";

    my @hosts = &queryExecRead($sql, "Fetching all host ID's that are assigned to the current collector", "all");

    # fetch collector-specific options, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
                WHERE id_attr=fk_id_attr
                AND naming_attr='no'
                AND fk_id_item=$_[0]
                ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching collector-specific options", "all");
    foreach (@attrs1){push(@collector_params,$_)}

    # fetch collector-specific service-templates, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @srv_tpl = &queryExecRead($sql, "Fetching collector-specific service-templates", "all");
    foreach (@srv_tpl){push(@collector_params,$_)}

    # fetch all service ID's of hosts assigned to the collector
    my @services = undef;
    foreach my $host (@hosts){
        $sql = "SELECT ItemLinks.fk_id_item AS item_id FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND fk_id_class=id_class
                        AND config_class = 'service'
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND ItemLinks.fk_item_linked2='$host->[0]'
                        ORDER BY attr_value";
        
        my @queryref = &queryExecRead($sql, "Fetching service ID's for host '$host->[0]'", "all");
        foreach my $service (@queryref){push(@services, $service)}
    }

    # remove first array element if it's empty
    unless($services[0]){shift(@services)}

    # fetch all servicegroup ID's
    my @servicegroups = &getItems("servicegroup");

    # add the collector's ID to the list of servicegroup ID's
    foreach my $entry (@servicegroups){
        $entry->[1] = $_[0];
    }

    # fetch all classes in ConfigClasses table
    my %class_info = &getConfigClasses();

    # use output file name from ConfigClasses table
    if($class_info{'service'}->{'out_file'}){
        push(@server_cfg_files, "$collector_path/$class_info{'service'}->{'out_file'}");
        &write_file("$collector_path/$class_info{'service'}->{'out_file'}","service","$class_info{'service'}->{'nagios_object'}",\@services,\@collector_params);
    }else{
        &logger(2,"[WARN] No services were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    if($class_info{'servicegroup'}->{'out_file'}){
        push(@server_cfg_files, "$collector_path/$class_info{'servicegroup'}->{'out_file'}");
        &write_file("$collector_path/$class_info{'servicegroup'}->{'out_file'}","servicegroup","$class_info{'servicegroup'}->{'nagios_object'}",\@servicegroups);
    }else{
        &logger(2,"[WARN] No servicegroups were exported. Make sure 'out_file' is set properly for the correspondig class.");
    }

    # if there are no Monitor servers present, also generare "extinfo" files for every Collector
    # (it is assumed that Collector config is always generated after Monitor config, so $mon_count==0)
    if($mon_count==0){
        push(@server_cfg_files, "$collector_path/extended_service_info.cfg");
        &write_file("$collector_path/extended_service_info.cfg","serviceextinfo","serviceextinfo",\@services);
    }
}


########################################################################################
# SUB write_file
# Write actual config files.

sub write_file {
    &logger(5,"Entered write_file()");

# define output format
format FILE =
                @<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<  @*
$fattr,$fval
.
    # read params passed
    my $path   = $_[0];
    my $class  = $_[1];
    my $item   = $_[2];
    my $list   = $_[3];
    my $params = $_[4];
    my @items  = @$list;

    # use class name if no Nagios object definition was specified
    unless($item){$item=$class};

    # read and parse additional monitor/collector params
    my @mon_col_params;
    my $stale_service_command = undef;
    if($params){
        @mon_col_params = @$params;

        # determine if a 'stale_service_command' attr is set for the services of the current nagios-monitor
        if($class eq "service"){
            foreach my $extattr (@mon_col_params){
                if($extattr->[0] eq "stale_service_command"){
                    $stale_service_command = $extattr->[1];
                }
            }
        }
    }

    # vars needed for several checks
    my $has_proc_sshd = 0;
    my @curr_host = undef;
    my @prev_host = undef;

    if($files_written{$path} && $files_written{$path} ne ""){open(FILE,">>$path") or &logger(1,"Could not open $path for writing (appending)")}
    else{open(FILE,">$path") or &logger(1,"Could not open $path for writing")}
    &logger(4,"Writing file '$path'");

    foreach my $id_item (@items){

        # CASE1: process special host files
        if($class eq "hostextinfo"){
            print FILE "define $item {\n";
            # fetch extended host info
            my @item_attrs = fetch_hostextinfo($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }
            print FILE "}\n\n";

        # CASE2: process special service files
        }elsif($class eq "serviceextinfo"){
            print FILE "define $item {\n";
            # fetch extended service info
            my @item_attrs = fetch_serviceextinfo($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }
            print FILE "}\n\n";

        # CASE3: process hostgroups
        }elsif($class eq "hostgroup"){

            # check for members
            my $has_members = 0;
            my @item_links = fetch_hostgroups($id_item->[0],$id_item->[1]);
            foreach my $attr (@item_links){
                if($attr->[0] eq "members"){$has_members = 1}
            }
            # skip this hostgroup if it has no menbers
            if($has_members != 1){next}
            print FILE "define $item {\n";

            # fetch ordinary hostgroup attributes
            my @item_attrs = &getItemData($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # fetch items linked to hostgroup
            my @item_links = fetch_hostgroups($id_item->[0],$id_item->[1]);
            foreach my $attr (@item_links){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }
            print FILE "}\n\n";

        # CASE4: process servicegroups
        }elsif($class eq "servicegroup"){

            # check for members
            my $has_members = 0;
            my @item_links = fetch_servicegroups($id_item->[0],$id_item->[1]);
            foreach my $attr (@item_links){
                if($attr->[0] eq "members"){$has_members = 1}
            }
            # skip this servicegroup if it has no menbers
            if($has_members != 1){next}
            print FILE "define $item {\n";

            # fetch ordinary servicegroup attributes
            my @item_attrs = &getItemData($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # fetch items linked to servicegroup
            my @item_attrs = fetch_servicegroups($id_item->[0],$id_item->[1]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }
            print FILE "}\n\n";

        # CASE5: process host files
        }elsif($class eq "host"){

            my (@host_templates1, @host_templates2, @host_templates3);
            print FILE "define $item {\n";

            # fetch all ordinary attributes
            my @item_attrs = &getItemData($id_item->[0]);
            my $hostname = undef;
            foreach my $attr (@item_attrs){
                # don't write notes to default config (will be handled hostextinfo)
                if($attr->[0] eq "notes" || $attr->[0] eq "notes_url"){next}
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
                if($attr->[0] eq "host_name"){$hostname=$attr->[1]}
            }

            # fetch host-alive check and timeperiod specific options
            my @aux_data = fetch_host_timeperiod_data($id_item->[0]);
            foreach my $attr (@aux_data){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "host_template"){push(@host_templates2, $attr->[1]);next}

                if($attr->[0] eq "host_notification_options"){$attr->[0] = "notification_options"}
                if($attr->[0] ne "" && $attr->[1] ne ""){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # print monitor-/collector-specific options
            foreach my $attr (@mon_col_params){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "host_template"){push(@host_templates3, $attr->[1]);next}

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # fetch all items linked
            my @item_links = &getItemsLinked($id_item->[0],$class);
            my $has_contactgroup = 0;
            my $has_oncall_group = 0;
            foreach my $attr (@item_links){

                # don't write "parents" attribute to collector config, if a monitor server is present
                if($attr->[0] eq "parents" && $path =~ /$collector_path/ && $mon_count > 0){next}

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "use"){push(@host_templates1, $attr->[1]);next}

                # check for contactgroups
                if($attr->[0] eq "contact_groups"){
                    $has_contactgroup = 1;

                    # check for oncall groups
                    my @cg_parts = split(/,/, $attr->[1]);
                    foreach my $cgroup (@cg_parts){
                        foreach (@oncall_groups){
                            if($cgroup eq $_){$has_oncall_group=1}
                        }
                    }

                    # add superadmin groups to all hosts by default
                    foreach(@superadmins){
                        if($attr->[1] !~ /\b$_\b/){$attr->[1] = $attr->[1].",".$_}
                    }
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){$fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # process host-templates
            # templates will be applied to hosts in the following order: 

            # 1. host specific template(s)
            # 2. notification_period template(s)
            # 3. check_period template(s)
            # 4. collector/monitor template(s)

            my @host_templates;
            push(@host_templates, @host_templates1);
            push(@host_templates, @host_templates2);
            push(@host_templates, @host_templates3);

            # make sure templates are only applied once, keeping original order (first occurence wins)
            tie my %tpl_hash, 'Tie::IxHash';
            foreach my $tpl (@host_templates){

                if($tpl_hash{$tpl}){next}

                if($tpl =~ /,/){
                    my @temp = split(/,/,$tpl);
                    foreach(@temp){$tpl_hash{$_}=$_}
                }else{
                    $tpl_hash{$tpl} = $tpl;
                }
            }

            $fattr = "use";
            $fval = join(",",keys(%tpl_hash));
            if($fval){write FILE}

            # warn if no oncall groups were assigned to host
            if($has_oncall_group == 0 && $oncall_groups[0] ne ""){
                &logger(2,"No oncall group is assigned to host $hostname");
            }

            # add superadmin groups, even if no contactgroups were specified at all
            if($has_contactgroup == 0){
                $fattr = "contact_groups";
                $fval = join(",",@superadmins);
                write FILE;
            }
            print FILE "}\n\n";

        # CASE6: process service files
        }elsif($class eq "service"){

            my $is_proc_sshd = 0;
            my $is_trap_service = 0;
            my (@service_templates1, @service_templates2, @service_templates3);

            # fetch service_description
            my @item_attrs = &getItemData($id_item->[0]);
            my $hostname = undef;
            my $srvname = undef;
            foreach my $attr (@item_attrs){
                if($attr->[0] eq "service_description"){$srvname=$attr->[1]}
                if($attr->[0] eq "service_description" && $attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){
                    if($attr->[1] =~ /trap/i && $path =~ /$collector_path/ && $mon_count > 0){
                        # don't write "TRAP" service to collector config
                        $is_trap_service = 1;
                    }else{
                        print FILE "define $item {\n";
                        $fattr=$attr->[0];
                        $fval=$attr->[1];
                        write FILE;
                    }
                }
            }

            if($is_trap_service == 1){next}

            # fetch all items linked
            my @item_links = &getItemsLinked($id_item->[0],$class);
            my $has_contactgroup = 0;
            my $has_oncall_group = 0;
            foreach my $attr (@item_links){

                # add a dummy check_command to all services of a monitor server, if stale_service_command attr is set 
                if($attr->[0] eq "check_command"){
                    if($monitor_path && $path =~ /$monitor_path/ && $stale_service_command){
                        $attr->[1] = $stale_service_command;
                    }
                    else{
                        my $cmd_params = fetch_cmd_params($id_item->[0]);
                        $attr->[1] = $attr->[1].$cmd_params;
                    }
                }

                # don't write "parents" attribute to collector config, if a monitor server is present
                if($attr->[0] eq "parents" && $path =~ /$collector_path/ && $mon_count > 0){next}

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "use"){push(@service_templates1, $attr->[1]);next}

                # check for contactgroups
                if($attr->[0] eq "contact_groups"){
                    $has_contactgroup = 1;

                    # check for oncall groups
                    my @cg_parts = split(/,/, $attr->[1]);
                    foreach my $cgroup (@cg_parts){
                        foreach (@oncall_groups){
                            if($cgroup eq $_){$has_oncall_group=1}
                        }
                    }

                    # add superadmin groups to all services by default
                    foreach(@superadmins){
                        if($attr->[1] !~ /\b$_\b/){$attr->[1] = $attr->[1].",".$_}
                    }
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}

                # read the host we're currently working on
                if($attr->[0] eq "host_name"){
                    $hostname = $attr->[1];
                    $curr_host[0] = $attr->[1];
                    $curr_host[1] = $id_item->[1];
                    if(@prev_host eq undef){$prev_host[0] = $attr->[1];$prev_host[1] = $id_item->[1]}}
            }

            # check if the host has changed
            if($curr_host[0] ne $prev_host[0]){
                # if previous host was a collector, warn if no sshd service was found
                if($prev_host[1] eq "yes" && $monitor_path && $path =~ /$monitor_path/ && $has_proc_sshd != 1){
                    &logger(2,"No SSH check was found for $prev_host[0]");
                }
                $has_proc_sshd = 0;
            }

            # fetch all ordinary attributes
            #### 2009-10-12 uncommented. unnecessary to run again.
            #### my @item_attrs = &getItemData($id_item->[0]);

            foreach my $attr (@item_attrs){

                # don't write notes to default config (will be handled serviceextinfo)
                if($attr->[0] eq "notes" || $attr->[0] eq "notes_url"){next}
                if($attr->[0] eq "service_description" && $attr->[1] =~ /ssh/i){$is_proc_sshd=1;$has_proc_sshd=1}

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no" && $attr->[0] ne "service_description"){
                    $fattr=$attr->[0];
                    $fval=$attr->[1];
                    write FILE;
                }
            }

            # fetch timeperiod specific options
            my @aux_data = fetch_service_timeperiod_data($id_item->[0]);
            foreach my $attr (@aux_data){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "service_template"){push(@service_templates2, $attr->[1]);next}
    
                if($attr->[0] eq "service_notification_options"){$attr->[0] = "notification_options"}
                if($attr->[0] ne "" && $attr->[1] ne ""){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # determine if this service belongs to a collector host
            my $new_checkfreshness = undef;
            my $new_freshthresh = undef;
	    my $checkfreshness_isset = 0;
	    my $freshthresh_isset = 0;
            if($id_item->[1] eq "yes"){
                # look for collector-specific check_freshness and freshness_threshhold
                foreach my $extattr (@mon_col_params){
                    if($extattr->[0] eq "collector_check_freshness"){$new_checkfreshness = $extattr->[1]}
                    if($extattr->[0] eq "collector_freshness_threshold"){$new_freshthresh = $extattr->[1]}
		    if($extattr->[0] eq "check_freshness" && $extattr->[1] ne ""){$checkfreshness_isset=1}
		    if($extattr->[0] eq "freshness_threshold" && $extattr->[1] ne ""){$freshthresh_isset=1}
                }
            }

            # print monitor-/collector-specific options
            foreach my $attr (@mon_col_params){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "service_template"){push(@service_templates3, $attr->[1]);next}

                # overwrite the check_freshness and freshness_threshhold values if
                # this service belongs to a collector host (only in monitor config)
                if($id_item->[1] eq "yes" && $attr->[0] eq "check_freshness"  && $monitor_path && $path =~ /$monitor_path/ && $new_checkfreshness){
                    $fattr=$attr->[0];
                    $fval=$new_checkfreshness;
                # also overwrite the threshhold value, but only for one sevice of the same host (PROC_sshd)
                }elsif($id_item->[1] eq "yes" && $attr->[0] eq "freshness_threshold" && $is_proc_sshd == 1 && $monitor_path && $path =~ /$monitor_path/ && $new_freshthresh){
                    $fattr=$attr->[0];
                    $fval=$new_freshthresh;
                }else{
                    $fattr=$attr->[0];
                    $fval=$attr->[1];
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){write FILE}
            }

	    # overwrite the check_freshness and freshness_threshhold values also if the attributes 
	    # are not set on a service level (e.g. using service-templates)
	    if($id_item->[1] eq "yes" && $monitor_path && $path =~ /$monitor_path/ && $new_checkfreshness && $checkfreshness_isset eq "0"){
		$fattr = "check_freshness";
		$fval  = $new_checkfreshness;
		write FILE;
	    }
	    if($id_item->[1] eq "yes" && $is_proc_sshd == 1 && $monitor_path && $path =~ /$monitor_path/ && $new_freshthresh && $freshthresh_isset eq "0"){
		$fattr = "freshness_threshold";
		$fval  = $new_freshthresh;
		write FILE;
	    }

            # process service-templates
            # templates will be applied to services in the following order: 

            # 1. service specific template(s)
            # 2. notification_period template(s)
            # 3. check_period template(s)
            # 4. collector/monitor template(s)

            my @service_templates;
            push(@service_templates, @service_templates1);
            push(@service_templates, @service_templates2);
            push(@service_templates, @service_templates3);

            # make sure templates are only applied once, keeping original order (first occurence wins)
            tie my %tpl_hash, 'Tie::IxHash';
            foreach my $tpl (@service_templates){

                if($tpl_hash{$tpl}){next}

                if($tpl =~ /,/){
                    my @temp = split(/,/,$tpl);
                    foreach(@temp){$tpl_hash{$_}=$_}
                }else{
                    $tpl_hash{$tpl} = $tpl;
                }
            }

            $fattr = "use";
            $fval = join(",",keys(%tpl_hash));
            if($fval){write FILE}

            # warn if no oncall groups were assigned to service
            if($has_oncall_group == 0 && $oncall_groups[0] ne ""){
                &logger(2,"No oncall group is assigned to service \"$srvname\" on host $hostname");
            }

            # add superadmin groups, even if no contactgroups were specified at all
            if($has_contactgroup == 0){
                $fattr = "contact_groups";
                $fval = join(",",@superadmins);
                write FILE;
            }

            @prev_host = @curr_host;
            print FILE "}\n\n";

        # CASE7: process all other files
        }else{
            print FILE "define $item {\n";

            # fetch all ordinary attributes
            my @item_attrs = &getItemData($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # fetch all items linked
            my @item_links = &getItemsLinked($id_item->[0],$class);
            foreach my $attr (@item_links){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }
            print FILE "}\n\n";
        }

    }
    close(FILE);
    $files_written{$path} = $path; # remember which files were already written (needed for appending logic)
}

########################################################################################
# SUB write_htpasswd_file
# Create a .htpasswd file for Apache webservers based on contact entries.
# Apache requires password encryption in NConf to be set to CRYPT by default.
# This allows users to manage access to a website based on contacts in NConf.

sub write_htpasswd_file {
    &logger(5,"Entered write_htpasswd_file()");

    # read params passed
    my $path = $_[0];
    my $list = $_[1];
    my @items = @$list;
    my $usercount = 0;

    open(FILE,">$path") or &logger(1,"Could not open $path for writing");
    &logger(4,"Writing file '$path'");

    foreach my $id_item (@items){

        # fetch all ordinary attributes
        my @item_attrs = &getItemData($id_item->[0]);

        my $username = undef;
        my $userpass = undef;
        my $userperm = undef;

        foreach my $attr (@item_attrs){
            if($attr->[0] eq "contact_name" && $attr->[1] ne ""){ $username=$attr->[1] }
            if($attr->[0] eq "user_password" && $attr->[1] ne ""){ $userpass=$attr->[1] }
            if($attr->[0] eq "nagios_access"){ $userperm=$attr->[1] }
        }

        $userpass =~s/\{.+\}//;
        if($username && $userpass && $userperm !~ /disabled/i){
	    print FILE "$username:$userpass\n";
	    $usercount++;
	}

    }
    close(FILE);

    # make sure .htpasswd file is only created if users with a password attr exist
    if($usercount==0){unlink $path}
}

########################################################################################
# SUB create_test_cfg
# Create a nagios.cfg file for each collector/monitor to run tests on the generated config

sub create_test_cfg {
    &logger(5,"Entered create_test_cfg()");

    # read params passed
    my $server = $_[0];
    my $testfile = "$test_path/$server.cfg";

    unless(-e $test_path){
        &logger(4,"Creating output directory '$test_path'");
        mkdir($test_path,0775) or &logger(1,"Could not create $test_path");
    }

	open(FILE,">$testfile") or &logger(1,"Could not open $testfile for writing");
    &logger(4,"Writing file '$testfile'");

    # write header
    print FILE "### nagios.cfg file - FOR TESTING ONLY ###\n\n";
    print FILE "# OBJECT CONFIGURATION FILE(S)\n";

    # write global cfg files
    my %unique_global_cfg;
    foreach my $global_cfg (@global_cfg_files){
        unless($global_cfg){next}
        if($unique_global_cfg{$global_cfg} && $unique_global_cfg{$global_cfg} ne ""){next}
        print FILE "cfg_file=$global_cfg\n";
        $unique_global_cfg{$global_cfg} = $global_cfg;
    }

    # write server-specific cfg files
    my %unique_server_cfg;
    foreach my $server_cfg (@server_cfg_files){
        unless($server_cfg){next}
        if($unique_server_cfg{$server_cfg} && $unique_server_cfg{$server_cfg} ne ""){next}
        print FILE "cfg_file=$server_cfg\n";
        $unique_server_cfg{$server_cfg} = $server_cfg;
    }

    # write footer + extra options
    print FILE "\n# ILLEGAL MACRO OUTPUT CHARS\n";
    print FILE "illegal_macro_output_chars=`~\$&|'\"<>\n";
    print FILE "check_result_path=$root_path/temp/";
    #print FILE "\n# RUN NAGIOS UNDER THIS USER\n";
    #print FILE "nagios_user=apache\n";

    close(FILE);
}


########################################################################################
# SUB fetch_hostextinfo
# Fetch all extended host info attributes

sub fetch_hostextinfo {
    &logger(5,"Entered fetch_hostextinfo()");

    my $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr AND naming_attr='yes' AND fk_id_item=$_[0]
               UNION
               SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr AND attr_name LIKE('notes%') AND fk_id_item=$_[0]
               UNION
               SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND fk_id_item = (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                     WHERE id_attr=fk_id_attr
                                     AND attr_name='os'
                                     AND fk_id_item=$_[0])";

    my @attrs = &queryExecRead($sql, "Fetching all extended host info attributes for host '$_[0]'", "all");

    # replace NConf macros with the respective value
    foreach my $attr (@attrs){
        $attr->[1] = &replaceMacros($attr->[1]);
    }

    return(@attrs);
}


########################################################################################
# SUB fetch_serviceextinfo
# Fetch all extended service info attributes

sub fetch_serviceextinfo {
    &logger(5,"Entered fetch_serviceextinfo()");

    my $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND naming_attr='yes'
                   AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                   WHERE id_attr=fk_id_attr
                                       AND attr_name='host_name'
                                       AND fk_id_item=$_[0])
               UNION
               SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND naming_attr='yes'
                   AND fk_id_item=$_[0]
               UNION
               SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND attr_name LIKE('notes%')
                   AND fk_id_item=$_[0]";

    my @attrs = &queryExecRead($sql, "Fetching all extended service info attributes for service '$_[0]'", "all");

    # replace NConf macros with the respective value
    foreach my $attr (@attrs){
        $attr->[1] = &replaceMacros($attr->[1]);
    }

    return(@attrs);
}


########################################################################################
# SUB fetch_hostgroups
# Fetch hostgroup info

sub fetch_hostgroups {
    &logger(5,"Entered fetch_hostgroups()");

    my $sql = undef;

    if($_[1]){

        # select all hosts linked to a hostgroup that are monitored by a specific collector
        $sql = "SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,ordering
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND link_as_child = 'yes'
		                AND fk_item_linked2=$_[0]
                        HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                            WHERE fk_item_linked2=id_item
                            AND id_class=fk_id_class
                            AND config_class = 'nagios-collector'
                            AND fk_id_item=item_id) = '$_[1]'";
    }else{

        # select all hosts linked to a hostgroup that are monitored by a collector
        $sql = "SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,ordering
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND link_as_child = 'yes'
		                AND fk_item_linked2=$_[0]
                        HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                            WHERE fk_item_linked2=id_item
                            AND id_class=fk_id_class
                            AND config_class = 'nagios-collector'
                            AND fk_id_item=item_id) <> ''";
    }

    my @attrs = &queryExecRead($sql, "Fetching all hosts linked to hostgroup '$_[0]'", "all");

    @attrs = &makeValuesDistinct(@attrs);

    return(@attrs);
}


########################################################################################
# SUB fetch_servicegroups
# Fetch servicegroup info

sub fetch_servicegroups {
    &logger(5,"Entered fetch_servicegroups()");

    my $sql = undef;

    if($_[1]){

    # select all services linked to a service group and that belong to a host, which is being monitored by a specific collector
    $sql = "SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,ordering,
                (SELECT attr_value FROM ConfigValues,ConfigAttrs,ItemLinks
                 WHERE id_attr=ConfigValues.fk_id_attr
                     AND naming_attr='yes'
                     AND attr_name='host_name'
                     AND ConfigValues.fk_id_item=fk_item_linked2
                     AND ItemLinks.fk_id_item=item_id) AS hostname,
                (SELECT ConfigValues.fk_id_item FROM ConfigValues,ConfigAttrs,ItemLinks
                 WHERE id_attr=ConfigValues.fk_id_attr
                     AND naming_attr='yes'
                     AND attr_name='host_name'
                     AND ConfigValues.fk_id_item=fk_item_linked2
                     AND ItemLinks.fk_id_item=item_id) AS host_id
            FROM ConfigValues,ItemLinks,ConfigAttrs
            WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                AND link_as_child = 'yes'
				AND fk_item_linked2=$_[0]
                HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                    WHERE fk_item_linked2=id_item
                    AND id_class=fk_id_class
                    AND config_class = 'nagios-collector'
                    AND fk_id_item=host_id) = '$_[1]'";
    }else{

    # select all services linked to a service group and that belong to a host, which is being monitored by a collector
    $sql = "SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,ordering,
                (SELECT attr_value FROM ConfigValues,ConfigAttrs,ItemLinks
                 WHERE id_attr=ConfigValues.fk_id_attr
                     AND naming_attr='yes'
                     AND attr_name='host_name'
                     AND ConfigValues.fk_id_item=fk_item_linked2
                     AND ItemLinks.fk_id_item=item_id) AS hostname,
                (SELECT ConfigValues.fk_id_item FROM ConfigValues,ConfigAttrs,ItemLinks
                 WHERE id_attr=ConfigValues.fk_id_attr
                     AND naming_attr='yes'
                     AND attr_name='host_name'
                     AND ConfigValues.fk_id_item=fk_item_linked2
                     AND ItemLinks.fk_id_item=item_id) AS host_id
            FROM ConfigValues,ItemLinks,ConfigAttrs
            WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                AND link_as_child = 'yes'
				AND fk_item_linked2=$_[0]
                HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                    WHERE fk_item_linked2=id_item
                    AND id_class=fk_id_class
                    AND config_class = 'nagios-collector'
                    AND fk_id_item=host_id) <> ''";
    }

    my @attrs = &queryExecRead($sql, "Fetching all services linked to servicegroup '$_[0]'", "all");

    foreach my $service (@attrs){
        $service->[1] = $service->[5].",".$service->[1];
    }

    @attrs = &makeValuesDistinct(@attrs);

    return(@attrs);
}


########################################################################################
# SUB fetch_host_timeperiod_data
# Fetch host-alive check and host data that is stored in timeperiods

sub fetch_host_timeperiod_data {
    &logger(5,"Entered fetch_host_timeperiod_data()");

    my $sql = undef;

    # fetch it from the misccommand, that is linked to the host-preset of the host
    $sql = "SELECT 'check_command', attr_value
              FROM ConfigValues, ConfigAttrs
              WHERE fk_id_attr=id_attr
                  AND attr_name='command_name'
                  AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                  WHERE id_attr=fk_id_attr
                                    AND attr_name='hostalive_check'
                                    AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                                  WHERE id_attr=fk_id_attr
                                                    AND attr_name='host-preset'
                                                    AND fk_id_item=$_[0]))";

    my @hostalive = &queryExecRead($sql, "Fetching host-alive check for host '$_[0]'", "row");

    unless($hostalive[1]){
        &logger(1,"Failed to get host-alive check for host '$_[0]'. Make sure the host is linked with a host-preset. Aborting.")
    }

    my @attrs;
    # fetch timeperiod host values (notification_period)
    $sql = "SELECT attr_name,attr_value FROM ConfigValues,ConfigAttrs
            WHERE id_attr=fk_id_attr
                AND (attr_name='notification_interval' OR attr_name='host_notification_options')
                AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                WHERE id_attr=fk_id_attr
                                    AND attr_name='notification_period'
                                    AND fk_id_item = $_[0])
                                    ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching timeperiod host values (notification_period) for host '$_[0]'", "all");
    foreach (@attrs1){push(@attrs,$_)}

    # fetch timeperiod host values (check_period)
    $sql = "SELECT attr_name,attr_value FROM ConfigValues,ConfigAttrs
            WHERE id_attr=fk_id_attr
                AND attr_name='max_check_attempts'
                AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                WHERE id_attr=fk_id_attr
                                    AND attr_name='check_period'
                                    AND fk_id_item = $_[0])
                                    ORDER BY ordering";

    my @attrs2 = &queryExecRead($sql, "Fetching timeperiod host values (check_period) for host '$_[0]'", "all");
    foreach (@attrs2){push(@attrs,$_)}

    # fetch host-templates linked to timeperiod (notification_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='notification_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @host_tpl1 = &queryExecRead($sql, "Fetching host-templates linked to timeperiod (notification_period) for host '$_[0]'", "all");
    foreach (@host_tpl1){push(@attrs,$_)}

    # fetch host-templates linked to timeperiod (check_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='check_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @host_tpl2 = &queryExecRead($sql, "Fetching host-templates linked to timeperiod (check_period) for host '$_[0]'", "all");
    foreach (@host_tpl2){push(@attrs,$_)}

    unshift(@attrs,\@hostalive);
    return(@attrs);
}


########################################################################################
# SUB fetch_service_timeperiod_data
# Fetch service data that is stored in timeperiods

sub fetch_service_timeperiod_data {
    &logger(5,"Entered fetch_service_timeperiod_data()");

    my $sql = undef;
    my @attrs;

    # fetch timeperiod service values (notification_period)
    $sql = "SELECT attr_name,attr_value FROM ConfigValues,ConfigAttrs
            WHERE id_attr=fk_id_attr
                AND (attr_name='notification_interval' OR attr_name='service_notification_options')
                AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                WHERE id_attr=fk_id_attr
                                   AND attr_name='notification_period'
                                   AND fk_id_item = $_[0])
                                   ORDER BY ordering";

    my @attrs1 = &queryExecRead($sql, "Fetching timeperiod service values (notification_period) for service '$_[0]'", "all");
    foreach (@attrs1){push(@attrs,$_)}

    # fetch timeperiod service values (check_period)
    $sql = "SELECT attr_name,attr_value FROM ConfigValues,ConfigAttrs
            WHERE id_attr=fk_id_attr
                AND (attr_name='max_check_attempts' OR attr_name='check_interval' OR attr_name='retry_interval')
                AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                WHERE id_attr=fk_id_attr
                                    AND attr_name='check_period'
                                    AND fk_id_item = $_[0])
                                    ORDER BY ordering";

    my @attrs2 = &queryExecRead($sql, "Fetching timeperiod service values (check_period) for service '$_[0]'", "all");
    foreach (@attrs2){push(@attrs,$_)}

    # fetch service-templates linked to timeperiod (notification_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='notification_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @srv_tpl1 = &queryExecRead($sql, "Fetching service-templates linked to timeperiod (notification_period) for service '$_[0]'", "all");
    foreach (@srv_tpl1){push(@attrs,$_)}

    # fetch service-templates linked to timeperiod (check_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='check_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @srv_tpl2 = &queryExecRead($sql, "Fetching service-templates linked to timeperiod (check_period) for service '$_[0]'", "all");
    foreach (@srv_tpl2){push(@attrs,$_)}

    return(@attrs);
}


########################################################################################
# SUB fetch_cmd_params
# Fetch checkcommand params

sub fetch_cmd_params {
    &logger(5,"Entered fetch_cmd_params()");

    my $sql = "SELECT attr_value FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                 AND attr_name='check_params'
                 AND fk_id_item=$_[0]";

    my @params = &queryExecRead($sql, "Fetching checkcommand params for service '$_[0]'", "all");

    return($params[0]->[0]);
}

########################################################################################

1;

__END__

}
