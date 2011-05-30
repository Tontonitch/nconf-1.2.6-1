<?php
    require_once 'config/main.php';
//    require_once 'include/head.php';
    $status = "OK";

    // check if "temp" dir is writable
    if(!is_writable(NCONFDIR."/temp/")){
        echo "<br><div id=attention>Could not write to 'temp' folder. Cannot generate config.</div>";
        $status = "error";
        exit;
    }

    // check if "output" dir is writable
    if(!is_writable(NCONFDIR."/output/")){
        echo "<br><div id=attention>Could not write to 'output' folder. Cannot store generated config.</div>";
        $status = "error";
        exit;
    }

    // check if generate_config script is executable
    if(!is_executable(NCONFDIR."/bin/generate_config.pl")){
        echo "<br><div id=attention>Could not execute generate_config script. <br>The file '".NCONFDIR."/bin/generate_config.pl' is not executable.</div>";
        $status = "error";
        exit;
    }

    // check if the Nagios / Icinga binary is executable
    exec(NAGIOS_BIN,$bin_out);
    if(!preg_match('/Nagios|Icinga/',implode(' ',$bin_out))){
        echo "<br><div id=attention>Error accessing or executing Nagios / Icinga binary '".NAGIOS_BIN."'. <br>Cannot run the mandatory syntax check.</div>";
        $status = "error";
        exit;
	}

    // check if existing "output/NagiosConfig.tgz" is writable
    if(file_exists(NCONFDIR."/output/NagiosConfig.tgz" and !is_writable(NCONFDIR."/output/NagiosConfig.tgz"))){
        echo "<br><div id=attention>Cannot rename ".NCONFDIR."/output/NagiosConfig.tgz. Access denied.</div>";
        $status = "error";
        exit;
    }

    // check if static config folder(s) are readable
    foreach ($STATIC_CONFIG as $static_folder){
        if(!is_readable($static_folder)){
            echo "<br><div id=attention>Could not access static config folder '".$static_folder."'.";
            echo "<br>Check your \$STATIC_CONFIG array in 'config/nconf.php'.</div>";
            $status = "error";
            exit;
        }
    }

    // fetch all monitor and collector servers from DB
    $servers = array();
    $query = "SELECT fk_id_item AS item_id,attr_value,config_class
                  FROM ConfigValues,ConfigAttrs,ConfigClasses
                  WHERE id_attr=fk_id_attr
                      AND naming_attr='yes'
                      AND id_class=fk_id_class
                      AND (config_class = 'nagios-collector' OR config_class = 'nagios-monitor') 
                  ORDER BY attr_value";

    $result = db_handler($query, "result", "fetch all monitor and collector servers from DB");

    while ($entry = mysql_fetch_assoc($result) ){
        $renamed = preg_replace('/-|\s/','_',$entry["attr_value"]);

        if($entry["config_class"] == 'nagios-collector'){
            $renamed = preg_replace('/Nagios|Icinga/i','collector',$renamed);
        }
        array_push($servers, $renamed);
    }

    // Log to history
    history_add("general", "config", "generated");
?>

<table border=0>
    <tr><td>
        <b>Generating config:</b><pre><?php system(NCONFDIR."/bin/generate_config.pl") ?></pre><br>

        <?php
           // create tar file
           system("cd ".NCONFDIR."/temp; tar -cf NagiosConfig.tar global ".implode(" ", $servers));

           // add folders with static config to tar file           
           foreach ($STATIC_CONFIG as $static_folder){
               if(!is_empty_folder($static_folder) and is_empty_folder($static_folder) != "error"){
                   $last_folder = basename($static_folder);
                   system("cd ".$static_folder."; cd ../; tar -rf ".NCONFDIR."/temp/NagiosConfig.tar ".$last_folder);
               }
           }
           
           // compress tar file
           system("cd ".NCONFDIR."/temp; gzip NagiosConfig.tar; mv NagiosConfig.tar.gz NagiosConfig.tgz");


	       // now run tests on all generated files
           foreach ($servers as $server){
	       
               exec(NAGIOS_BIN." -v ".NCONFDIR."/temp/test/".$server.".cfg",$srv_summary[$server]);

               $server_str = preg_replace("/\./", "_", $server);
               echo '<input type=hidden id="'.$server_str.'" value="';
               foreach($srv_summary[$server] as $line){echo "$line\n";}
               echo '">';
           }
        ?>

        <b>Running syntax check:</b><br><br>

        <?php
            $icon_count = 1;

            foreach ($servers as $server){
                $server_str = preg_replace("/\./", "_", $server);
                echo '<table cellspacing=0 cellpadding=0>
                      <tr onClick="set_'.$server_str.'()"><td><img src="img/icon_expand.gif" id="icon'.$icon_count.'"></td>
                        <td width=85><b>&nbsp;&nbsp;'.$server.':</b></td><td>&nbsp;';

                $count=0;
                foreach($srv_summary[$server] as $line){
                    if(ereg("Total",$line)){
                        echo "$line&nbsp;&nbsp;";
                        $count++;
                        if(ereg("Errors",$line) && !preg_match('/Total Errors:\s+0/',$line)){
                            $status = "error";
                        }
                    }
                }
                if($count==0){
                    echo "Error generating config";
                    $status = "error";
                }

                echo '</td></tr></table><pre style="width:500px"><hr><div id="show_'.$server_str.'">&nbsp;</div></pre>';
                $icon_count++;
            }

        ?>

    </td></tr>
</table>

<?php

    if($status == "OK"){

        // Move generated config to "output" dir
        if(file_exists(NCONFDIR."/output/NagiosConfig.tgz")){
            system("mv ".NCONFDIR."/output/NagiosConfig.tgz ".NCONFDIR."/output/NagiosConfig.tgz.".time());
        }
        system("mv ".NCONFDIR."/temp/NagiosConfig.tgz ".NCONFDIR."/output/");
        system("rm -rf ".NCONFDIR."/temp/*");

        if(ALLOW_DEPLOYMENT == 1){
            // Show deployment button
            echo "<form method=\"POST\" action=\"deploy_config.php\" id=buttons>";
            echo "<input type=hidden name=status value=\"".$status."\">";
            echo "<b>Deploy generated config</b><br><br>";
            echo "<input type=submit name=submit value=\"Deploy\">";
            echo "</form>";
        }else{
            // Simply show success message
            echo "<b>Changes updated successfully.</b><br><br>";
        }

    }else{
        // Remove generated config - syntax check has failed
        system("rm -rf ".NCONFDIR."/temp/*");
        echo "<div id=attention>Deployment not possible due to errors in configuration.</div>";
    }

    mysql_close($dbh);
//    require_once 'include/foot.php';
?>

<script type="text/javascript">

<?php

    foreach ($servers as $server){
        $server_str = preg_replace("/\./", "_", $server);
        echo 'document.getElementById("show_'.$server_str.'").firstChild.nodeValue = "";';
    }

    $icon_count = 1;

    foreach ($servers as $server){
        $server_str = preg_replace("/\./", "_", $server);
        echo 'function set_'.$server_str.' () {
                if(document.getElementById("show_'.$server_str.'").firstChild.nodeValue == ""){
                    document.getElementById("show_'.$server_str.'").firstChild.nodeValue = document.getElementById("'.$server_str.'").value;
                    document.getElementById("icon'.$icon_count.'").src = "img/icon_collapse.gif";
                }else{
                    document.getElementById("show_'.$server_str.'").firstChild.nodeValue = "";
                    document.getElementById("icon'.$icon_count.'").src = "img/icon_expand.gif";
                }
        }';
        $icon_count++;
    }
?>

</script>
