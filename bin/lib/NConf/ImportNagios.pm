##############################################################################
# "NConf::ImportNagios" library
# A collection of shared functions for the NConf Perl scripts.
# Functions needed to parse existing Nagios configuration files.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-25 v0.1   A. Gargiulo   First release
#
##############################################################################

package NConf::ImportNagios;

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
    @EXPORT      = qw(@NConf::EXPORT parseNagiosConfigFile);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub parseNagiosConfigFile {
    &logger(5,"Entered parseNagiosConfigFile()");

    # SUB use: parse a Nagios config file, load data into memory

    # SUB specs: ###################

    # Expected arguments:
    # 0: NConf class name of items to parse for
    # 1: NConf naming-attr for specified class
    # 2: File to parse

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key, 
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed
    my $input_class = shift;
    my $naming_attr = shift;
    my $input_file  = shift;

    unless($input_class && $naming_attr && $input_file){&logger(1,"parseNagiosConfigFile(): Missing argument(s). Aborting.")}

    # clean up arguments passed
    $input_class =~ s/^\s*//;
    $input_class =~ s/\s*$//;
    $naming_attr =~ s/^\s*//;
    $naming_attr =~ s/\s*$//;

    tie my %main_hash, 'Tie::IxHash';
    my $file_class = undef;
    my $filepos = 1;

    # set block delimiter
    $/ = "define ";

    &logger(4,"Opening and parsing input file $input_file");
    open(LIST, $input_file) or &logger(1,"Could not read from $input_file. Aborting.");

    while(<LIST>){

        # count amount of lines in current block
        my $linecount = 0;
        while($_ =~ /\n/g){$linecount++}

        # skip empty blocks or comments
        chomp $_;
        unless($_){next}
        if($_ =~ /^\s*$/){next}
        if($_ =~ /^\s*#/){next}
        my $block = $_;

        # check if more than one class type is defined within the input file
        $block =~ /\s*([A-Za-z0-9_-]+)\s*{/;
        if($file_class && $file_class ne $1){
            &logger(1,"The input file contains more than one class of items (starting at line $filepos).\nMake sure only '$input_class' items are defined. Aborting."); 
        }
        $file_class = $1;

        # clean up current block (remove empty lines and trailing spaces/comments)
        $block =~ s/\n*.*\{//;
        $block =~ s/\s*\}.*\n*//;
        $block =~ s/^\s*\n//;
        $block =~ s/\n\s*\n/\n/g;
        $block =~ s/#.*\n/\n/;

        my @lines = split(/\n/, $block);
        tie my %block_hash, 'Tie::IxHash';
        my $block_naming_attr = undef;
        my $service_parent_attr = undef;

        # process each line of the current block
        foreach my $line (@lines){

            # clean up current line
            $line =~ s/^\s*//g;
            $line =~ s/\s*$//g;
            if($line =~ /^\s*#/ || $line eq ""){next}

            $line =~ /^([^\s]+)\s+(.+)$/;
            my $attr  = $1;
            my $value = $2;

            if($attr eq "" or $value eq ""){
                &logger(1,"Problem reading some attrs/values for $input_class (starting at line $filepos). Aborting.");
            }

            if($block_hash{$attr}){
                &logger(2,"'$attr' is defined more than once for $input_class (starting at line $filepos). Using last instance.");
            }

            # push all attributes and their values into a hash (make distinct)
            $block_hash{$attr} = $value;

            # determine the naming attr in the current block
            if($attr eq $naming_attr){$block_naming_attr=$value}
            if($input_class =~ /^service$/i){
                if($attr =~ /^host_name$/i){$service_parent_attr=$value}
            }
            # look for templates in host / service definitions
            if($attr =~ /^name$/i && ($input_class =~ /^service$/i || $input_class =~ /^host$/i)){
                &logger(1,"The input file seems to contain host- or service-templates (starting at line $filepos).\nMake sure you import templates separately! Do not combine them with other host / service items. Aborting.");
            }
        }

        unless($block_naming_attr){
            &logger(1,"Could not locate '$naming_attr' for $input_class (starting at line $filepos). Aborting.");
        }

        if(!$service_parent_attr && $input_class =~ /^service$/i){
            &logger(1,"Could not locate 'host_name' attr for service (starting at line $filepos). Aborting.");
        }


        # write block hash reference to global hash, using the naming attr as key
        if($input_class =~ /^service$/i){
            &logger(5,"Parsing attributes of $input_class '$service_parent_attr;;$block_naming_attr'");
            if($main_hash{"$service_parent_attr;;$block_naming_attr"}){
                &logger(2,"$input_class '$service_parent_attr: $block_naming_attr' (starting at line $filepos) is defined more than once.");
            }
            $main_hash{"$service_parent_attr;;$block_naming_attr"} = \%block_hash;
        }else{
            &logger(5,"Parsing attributes of $input_class '$block_naming_attr'");
            if($main_hash{$block_naming_attr}){
                &logger(2,"$input_class '$block_naming_attr' (starting at line $filepos) is defined more than once.");
            }
            $main_hash{$block_naming_attr} = \%block_hash;
        }

        $filepos += $linecount;
    }

    close(LIST);
    return %main_hash;
}

##############################################################################

1;

__END__

}
