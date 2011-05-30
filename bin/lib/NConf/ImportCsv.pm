##############################################################################
# "NConf::ImportCsv" library
# A collection of shared functions for the NConf Perl scripts.
# Functions needed to parse CSV files.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-06-17 v0.1   A. Gargiulo   First release
#
##############################################################################

package NConf::ImportCsv;

use strict;
use Exporter;
use NConf;
use NConf::Logger;
use Tie::IxHash;    # preserve hash order

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT parseCsv parseHostServiceCsv);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub parseCsv {
    &logger(5,"Entered parseCsv()");

    # SUB use: parse a CSV file, load data into memory

    # SUB specs: ###################

    # Expected arguments:
    # 0: CSV file to parse
    # 1: a reference to an array containing the names & order of the CSV attributes
    # 2: the NConf class name of items to parse for
    # 3: optional: the NConf naming-attr for specified class (defaults to the first element
    #    in the array containing the names & order of the CSV attributes)
    # 4: optional: the CSV delimiter (defaults to ";")

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key, 
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed

    my ($csv_file,$arr1,$input_class,$naming_attr,$csv_delim) = @_;
    my @csv_syntax = @$arr1;

    unless($csv_file && @csv_syntax && $input_class){&logger(1,"parseCsv(): Missing argument(s). Aborting.")}
    unless($naming_attr){$naming_attr=$csv_syntax[0]}
    unless($csv_delim){$csv_delim=";"}

    tie my %main_hash, 'Tie::IxHash';
    my $inputline = 1;

    &logger(4,"Opening and parsing input file $csv_file");
    open(LIST, $csv_file) or &logger(1,"Could not read from $csv_file. Aborting.");

    while(<LIST>){

        chomp $_;
        unless($_){$inputline++;next}
        if($_ =~ /^\s*#/){$inputline++;next}

        my @csv_line = split(/$csv_delim/, $_);
        map(s/^\s*//g, @csv_line);
        map(s/\s*$//g, @csv_line);

        # do CSV-data -> NConf-attrs mapping
        tie my %line_hash, 'Tie::IxHash';
        my $attr_count = 0;
        my $naming_attr_value = undef;
        foreach my $attr (@csv_syntax){

            # check if attribute is defined twice for the same item
            if($line_hash{$attr}){
                &logger(2,"Attribute '$attr' is defined more than once (on input line $inputline). Using last instance.");
            }

            # add each item to the line-hash according to the specified CSV syntax
            $line_hash{$attr} = $csv_line[$attr_count];

            # fetch and store naming attr value separately
            if($attr eq $naming_attr){$naming_attr_value = $csv_line[$attr_count]}
            
            # look for templates in host / service definitions
            if($attr =~ /^name$/i && ($input_class =~ /^service$/i || $input_class =~ /^host$/i)){
                &logger(1,"The input file seems to contain host- or service-templates.\nMake sure you import templates separately! Do not combine them with other host / service items. Aborting.");
            }
            $attr_count++;
        }

        # abort if the value for the naming attr could not be determined
        unless($naming_attr_value){
            &logger(1,"Could not locate '$naming_attr' for $input_class (on input line $inputline). Aborting.");
        }

        &logger(5,"Parsing attributes of $input_class '$naming_attr_value'");

        # check if item already exists in global hash
        if($main_hash{$naming_attr_value}){
            &logger(2,"$input_class '$naming_attr_value' is defined more than once (on input line $inputline).");
        }

        # write line-hash reference to global hash, using the naming attr value as key
        $main_hash{$naming_attr_value} = \%line_hash;

        $inputline++;
    }

    close(LIST);
    return %main_hash;
}

##############################################################################

sub parseHostServiceCsv {
    &logger(5,"Entered parseHostServiceCsv()");

    # SUB use: parse a CSV file containing host/service information, load data into memory

    # SUB specs: ###################

    # The CSV file must have the following format:
    #
    # host_attr1;...;host_attrN ;srvX_attr1;...;srvX_attrN [;srvY_attr1;...;...]
    # <-----------------------> <------------------------> <------------------->
    #      host attributes          service attributes      additional services

    # Expected arguments:
    # 0: CSV file to parse
    # 1: a reference to an array containing the names & order of the host attributes
    # 2: a reference to an array containing the names & order of the service attributes
    # 3: optional: the CSV delimiter (defaults to ";")

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key, 
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed

    my ($csv_file,$arr1,$arr2,$csv_delim) = @_;
    
    my @host_syntax = @$arr1;
    my @srv_syntax = @$arr2;

    unless($csv_file && @host_syntax && @srv_syntax){&logger(1,"parseHostServiceCsv(): Missing argument(s). Aborting.")}
    unless($csv_delim){$csv_delim=";"}

    tie my %main_hash, 'Tie::IxHash';
    my $inputline = 1;

    &logger(4,"Opening and parsing input file $csv_file");
    open(LIST, $csv_file) or &logger(1,"Could not read from $csv_file. Aborting.");

    while(<LIST>){

        chomp $_;
        unless($_){$inputline++;next}
        if($_ =~ /^\s*#/){$inputline++;next}

        my @csv_line = split(/$csv_delim/, $_);
        map(s/^\s*//g, @csv_line);
        map(s/\s*$//g, @csv_line);

        ##### process host data

        # do CSV-data -> NConf-attrs mapping for host related data
        tie my %host_hash, 'Tie::IxHash';
        my $attr_count = 0;
        my $host_name  = undef;
        my $host_cgroup  = undef;
        my $host_cperiod  = undef;
        my $host_nperiod  = undef;
        foreach my $attr (@host_syntax){

            # check if attribute is defined twice for the same host
            if($host_hash{$attr}){
                &logger(2,"Attribute '$attr' is defined more than once (on input line $inputline). Using last instance.");
            }

            # add each item to the host-hash according to the specified host syntax
            $host_hash{$attr} = $csv_line[$attr_count];

            # fetch and store hostname, contactgroups, check- & notification-period separately
            if($attr eq "host_name"){$host_name = $csv_line[$attr_count]}
            if($attr eq "contact_groups"){$host_cgroup = $csv_line[$attr_count]}
            if($attr eq "check_period"){$host_cperiod = $csv_line[$attr_count]}
            if($attr eq "notification_period"){$host_nperiod = $csv_line[$attr_count]}
            $attr_count++;
        }

        # abort if hostname could not be determined
        unless($host_name){
            &logger(1,"Could not locate 'host_name' for host (on input line $inputline). Aborting.");
        }

        &logger(5,"Parsing attributes of host '$host_name'");

        # check if host already exists in global hash
        if($main_hash{$host_name}){
            &logger(2,"Host '$host_name' is defined more than once (on input line $inputline).");
        }

        # write host-hash reference to global hash, using the hostname as key
        $main_hash{$host_name} = \%host_hash;

        ##### process service data

        # do CSV-data -> NConf-attrs mapping for service related data
        tie my %srv_hash, 'Tie::IxHash';

        my $srv_name  = undef;
        my $num_elements = @srv_syntax; # count the amount of service attributes
        while ($csv_line[$attr_count] || $csv_line[$attr_count+$num_elements]){

            tie my %srv_attr_hash, 'Tie::IxHash';

            foreach my $attr (@srv_syntax){

                # check if attribute is defined twice for the same service
                if($srv_attr_hash{$attr}){
                    &logger(2,"Attribute '$attr' is defined more than once within the same service (on input line $inputline). Using last instance.");
                }

                # add each item to the service-hash according to the specified service syntax
                $srv_attr_hash{$attr} = $csv_line[$attr_count];

                # fetch and store service name separately
                if($attr eq "service_description"){$srv_name = $csv_line[$attr_count]}
                $attr_count++;
            }

            # add "host_name" attr to all services
            $srv_attr_hash{"host_name"} = $host_name;

            # abort if service name could not be determined
            unless($srv_name){
                &logger(2,"Could not locate 'service_description' for a service (on input line $inputline). Skipping service.");
                next;
            }

            # make service inherit "contact_groups", "check_period" and "notification_period", if not defined explicitely
            unless($srv_attr_hash{"contact_groups"}){
                &logger(4,"Could not locate 'contact_groups' for a service (on input line $inputline). Inheriting value from host.");
                $srv_attr_hash{"contact_groups"} = $host_cgroup;
            }
            unless($srv_attr_hash{"check_period"}){
                &logger(4,"Could not locate 'check_period' for a service (on input line $inputline). Inheriting value from host.");
                $srv_attr_hash{"check_period"} = $host_cperiod;
            }
            unless($srv_attr_hash{"notification_period"}){
                &logger(4,"Could not locate 'notification_period' for a service (on input line $inputline). Inheriting value from host.");
                $srv_attr_hash{"notification_period"} = $host_nperiod;
            }

            $srv_hash{$srv_name} = \%srv_attr_hash;

            &logger(5,"Parsing attributes of service '$srv_name'");

            # check if service already exists in global hash
            if($main_hash{"$host_name;;$srv_name"}){
                &logger(2,"Service '$srv_name' is defined more than once for the same host (on input line $inputline).");
            }

            # write service-hash reference to global hash, using the service name as key
            $main_hash{"$host_name;;$srv_name"} = ${srv_hash{$srv_name}};
        }

        $inputline++;
    }

    close(LIST);
    return %main_hash;
}

##############################################################################

1;

__END__

}
