#!/usr/bin/perl

########################################################################################
# Description:
# This script gives you quick access to items in the NConf database. 
# It is intended for debugging purposes, to get a quick overview of an item's attributes 
# and links to other items.
# It can be also used to export all or parts of the items of a specified class. Supported
# export formats are CSV and nagios config file format.
#
########################################################################################
# Version 1.0.1
# Angelo Gargiulo
#
########################################################################################
# Revision history:
# ????-??-??  v1.0    A. Gargiulo   Initial release
# 2010-11-12  v1.0.1  Y. Charton    Added attribute filter keywords: NAGIOS and VISIBLE
#                                   Added argument -s for csv separator
#                                   Little fix to skip null attribute names related to
#                                   getItemsLinked() function problems in nconf 1.2.6
#
########################################################################################

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::Helpers;
use Getopt::Std;
use Tie::IxHash;    # preserve hash order

# read commandline arguments
use vars qw($opt_c $opt_i $opt_r $opt_a $opt_f $opt_e $opt_s);
getopts('c:i:r:a:fes:');
unless($opt_c){&usage}

#set csv separator
my $csv_separator = ";";
if ($opt_s){$csv_separator = "$opt_s";}

# build the list of items to display
my %restrict;
if($opt_i){
    my @restrict = split(/,/, $opt_i);
    foreach(@restrict){
        $_ =~ s/^\s+//;
        $_ =~ s/\s+$//;
        $restrict{$_} = $_;
    }
}

# build the list of attributes to display
my %attr_restrict;
if($opt_a){
    my @attr_restrict = ();
    if($opt_a eq "VISIBLE") {
        my %conf_attrs = getConfigAttrs();
        foreach my $attr (keys(%{%conf_attrs->{$opt_c}})){
            unless($attr){next}
            if($conf_attrs{$opt_c}->{$attr}->{'visible'} eq "yes"){
                push(@attr_restrict, $attr);
            }
        }
    }elsif($opt_a eq "NAGIOS") {
        my %conf_attrs = getConfigAttrs();
        foreach my $attr (keys(%{%conf_attrs->{$opt_c}})){
            unless($attr){next}
            if($conf_attrs{$opt_c}->{$attr}->{'write_to_conf'} eq "yes"){
                push(@attr_restrict, $attr);
            }
        }
    }else{
        @attr_restrict = split(/,/, $opt_a);
    }
    foreach(@attr_restrict){
        $_ =~ s/^\s+//;
        $_ =~ s/\s+$//;
        $attr_restrict{$_} = $_;
    }
}

#########################
# MAIN

my @items = getItems($opt_c,1);
my @csv_items;
tie my %csv_attrs, 'Tie::IxHash';

# define output format
my($fattr,$fval);
format STDOUT =
                @<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<  @*
$fattr,$fval
.

# process all items
foreach my $item (@items) {

    if($opt_i && !$opt_r){
        unless($restrict{$item->[1]} eq $item->[1]){next}
    }
    elsif($opt_r && !$opt_i){
        unless($item->[1] =~ /$opt_r/){next}
    }
    elsif($opt_i && $opt_r){
        print "ERROR: Either specify option -i or -r, but not both!\n";
        &usage;
    }

    if($opt_f && $opt_e){
        print "ERROR: Either specify option -f or -e, but not both!\n";
        &usage;
    }

    # fetch item information
    my @item_data  = getItemData($item->[0]);
    my @item_links = getItemsLinked($item->[0]);
    @item_links    = makeValuesDistinct(@item_links);
    my @all_data   = (@item_data, @item_links);

    my $csv_line   = undef;
    tie my %csv_item_hash, 'Tie::IxHash';

    # for Nagios-like output:
    # mapping for NConf specific classes to Nagios definitions
    my $nagios_item;
    if($opt_c =~ /^.+command$/){$nagios_item="command"}
    elsif($opt_c eq "host-template"){$nagios_item="host"}
    elsif($opt_c eq "service-template"){$nagios_item="service"}
    else{$nagios_item=$opt_c;$nagios_item=~s/-//g}

    if($opt_f){print "define $nagios_item {\n"}

    # process all attributes of an item
    foreach my $data (@all_data) {

        # apply certain filters
        unless($data->[0] && $data->[1]){next}
        if($opt_a){unless($attr_restrict{$data->[0]} eq $data->[0]){next}}

        # Nagios style formated output
        if($opt_f && $data->[2] eq "yes"){
            $fattr = $data->[0];
            $fval  = $data->[1];
            write STDOUT;
            next;
        }

        # CSV formated output
        if($opt_e){
            unless($csv_attrs{$data->[0]}){$csv_attrs{$data->[0]}=$data->[0]}

            # replace special characters
            $data->[1] =~ s/"/""/g;
            if($data->[1] =~ /;|"/){$data->[1]='"'.$data->[1].'"'}

            # store attr/value pairs to hash structure
            $csv_item_hash{$data->[0]} = $data->[1];
            next;
        }

        # unformated output
        if(!$opt_f && !$opt_e){
            print "$data->[0]: $data->[1]\n";
            next;
        }
    }
    push(@csv_items, \%csv_item_hash);

    if($opt_f){print "}\n"}
    unless($opt_e){print "\n"}
}

if($opt_e){
    # print CVS header
    my $csv_header = undef;
    foreach my $attr (keys(%csv_attrs)){$csv_header=$csv_header.$csv_separator.$attr}
    $csv_header =~ s/^$csv_separator//;
    $csv_header =~ s/$csv_separator$//;
    print "$csv_header\n";

    # print CVS output
    foreach my $item (@csv_items){
        my $csv_record =  undef;
        foreach my $attr (keys(%csv_attrs)){
            $csv_record = $csv_record.%{$item}->{$attr}.$csv_separator;
        }
        $csv_record =~ s/$csv_separator$//;
        print "$csv_record\n";
    }
}

#########################
# SUB: display usage information

sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG

This script gives you quick access to items in the NConf database. It is intended for debugging purposes,
to get a quick overview of an item's attributes and links to other items.

Specify the type of items you wish to see, as well as several filtering options.
The output will be printed to the command-line in text format.

Usage:
$0 -c class [-i item name,item name,...] [-r "Perl regex"] [-a attribute1,attribute2,...] [-f] [-e] [-s "csv separator"]

Help:

  required

  -c  Specify the class of items that you wish to output. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  optional

  -i  Limit output to a single item, or a list of items (e.g. one specific host)

  -r  Limit output to items matching a certain regex (this is a Perl regex, e.g. "^local.*")

  -a  Only print a single attribute and its value, or several specific attributes and their values (does not affect attribute order)
      You can also use the following keyword (respect the case)
        * 'NAGIOS' :    returns all attributes for which values are written in nagios config files
        * 'VISIBLE' :   returns all attributes that are visible in the nconf interface

  -f  Nagios formated output ("Nagios-config-like", not a replacement for generating the real config!)

  -e  CSV formated output.

  -s  Define the CSV separator when CSV formated output is used (default is ;)

EOT
exit;

}