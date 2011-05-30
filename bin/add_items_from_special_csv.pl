#!/usr/bin/perl

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::DB::Modify;
use NConf::Logger;
use NConf::ImportCsv;
use Getopt::Std;
use Tie::IxHash;    # preserve hash order

# read commandline arguments
use vars qw($opt_f $opt_x $opt_s);
getopts('f:x:s');
unless($opt_f){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

# global vars
my @host_syntax;
my @srv_syntax;

# Define the syntax for the host / service part of the CSV file here

# set the CSV delimiter
my $csv_delimiter = ";";

push(@host_syntax, 'host_name');
push(@host_syntax, 'alias');
push(@host_syntax, 'address');
push(@host_syntax, 'os');
push(@host_syntax, 'check_period');
push(@host_syntax, 'notification_period');
push(@host_syntax, 'contact_groups');
push(@host_syntax, 'parents');
push(@host_syntax, 'host-preset');
push(@host_syntax, 'monitored_by');

push(@srv_syntax,  'service_description');
push(@srv_syntax,  'check_command');
push(@srv_syntax,  'check_params');
push(@srv_syntax,  'check_period');
push(@srv_syntax,  'notification_period');

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

tie my %main_hash, 'Tie::IxHash';
%main_hash = &parseHostServiceCsv($opt_f, \@host_syntax, \@srv_syntax, $csv_delimiter);

# loop through all items
foreach my $item (keys(%main_hash)){

    my $item_class = undef;
    if($main_hash{$item}->{'host_name'} && !$main_hash{$item}->{'service_description'}){$item_class = "host"}
    elsif($main_hash{$item}->{'service_description'}){$item_class = "service"}
    else{logger(1, "Failed to determine if current item is a host or a service. Aborting")}

    &logger(3,"Adding $item_class '$item'");

    tie my %item_hash, 'Tie::IxHash';
    %item_hash = %{$main_hash{$item}};

    if( &addItem($item_class, %item_hash) ){
        logger(3, "Successfully added $item_class '$item'");
    }else{
        logger(1, "Failed to add $item_class '$item'. Aborting");
    }
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG
This script reads a CSV file and imports hosts / services by creating new items in NConf.

The CSV file must have the following format:

host_attr1;...;host_attrN ;srv_attr1;...;srv_attrN [;...;...;...]
<-----------------------> <----------------------> <------------------->
     host attributes         service attributes     additional services

Host attributes:
host_name;alias;address;os;check_period;notification_period;contact_group(s);parent(s);host-preset;monitored_by

Service attributes:
service_description;check_command;check_params;check_period;notification_period

Usage:
$0 -f /path/to/file [-x (1-5)] [-s]

Help:

  required

  -f  The path to the CSV file which is to be imported.

  optional

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
