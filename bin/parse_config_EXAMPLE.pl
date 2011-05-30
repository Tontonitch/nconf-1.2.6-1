#!/usr/bin/perl

use strict;
use lib "./lib";

use NConf;
use NConf::ImportNagios;
use Getopt::Std;

# read commandline arguments
use vars qw($opt_c $opt_n $opt_f);
getopts('c:n:f:');
unless($opt_c && $opt_n && $opt_f){&usage}

#########################
# MAIN

my %main_hash = &parseNagiosConfigFile($opt_c, $opt_n, $opt_f);
&process_data(%main_hash);

#########################
# SUB: process data read from input file
sub process_data {

    my %main_hash = @_;

    # loop through all items
    foreach my $item (keys(%main_hash)){

        # access all attr -> value pairs
        #foreach my $attr (keys(%{$main_hash{$item}})){
        #    print "$item -> $attr -> $main_hash{$item}->{$attr}\n";
        #}

        # access only a specific attr value
        #print "contact_groups $item: $main_hash{$item}->{'contact_groups'}\n";
    }
}

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG
This script parses a Nagios configuration file and processes it for import into NConf.

Usage:
$0 -c class -n naming-attr -f /path/to/file

Help:

  required

  -c  Specify the class of items that you wish to import. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  -n  Specify the naming attribute for the class to be imported. Must correspond with the
      naming attr in NConf (e.g. host: "host_name", service: "service_description"...)

  -f  The path to the file which is to be imported. CAUTION: Make sure you have
      only items of one class in the same file (e.g. "hosts.cfg", "services.cfg"...)

EOT
exit;

}
