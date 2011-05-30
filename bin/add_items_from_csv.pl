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
use vars qw($opt_c $opt_n $opt_f $opt_x $opt_s);
getopts('c:n:f:x:s');
unless($opt_c && $opt_n && $opt_f){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

# global vars
my @csv_syntax;

#### DEFINE THE CONTENTS OF YOUR CSV FILE HERE ###

# set the CSV delimiter
my $csv_delimiter = ";";

# example definition for a CSV file containing "hosts"
push(@csv_syntax, 'host_name');
push(@csv_syntax, 'alias');
push(@csv_syntax, 'address');
push(@csv_syntax, 'check_period');
push(@csv_syntax, 'notification_period');
push(@csv_syntax, 'contact_groups');

# example definition for a CSV file containing "checkcommands"
#push(@csv_syntax, 'command_name');
#push(@csv_syntax, 'default_service_name');
#push(@csv_syntax, 'command_line');
#push(@csv_syntax, 'command_syntax');
#push(@csv_syntax, 'default_params');
#push(@csv_syntax, 'command_param_count');

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

tie my %main_hash, 'Tie::IxHash';
%main_hash = &parseCsv($opt_f, \@csv_syntax, $opt_c, $opt_n, $csv_delimiter);

# loop through all items
foreach my $item (keys(%main_hash)){

    my $item_class = $opt_c;

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
This script reads a CSV file and imports the content by creating new items in NConf.

Please edit this script and define the contents of your CSV file 
using the "csv_syntax" array at the beginning of the script.

Usage:
$0 -c class -n naming-attr -f /path/to/file [-x (1-5)] [-s]

Help:

  required

  -c  Specify the class of items that you wish to import. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  -n  Specify the naming attribute for the class to be imported. Must correspond with the
      naming attr in NConf (e.g. host: "host_name", service: "service_description"...)

  -f  The path to the file which is to be imported. CAUTION: Make sure you have
      only items of one class in the same file (e.g. "hosts", "services"...)
      Also make sure you import host- or service-templates separately ("host" or 
      "service" items containing a "name" attribute)

  optional

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
