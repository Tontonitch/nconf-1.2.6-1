<?php

require_once 'config/main.php';
require_once 'include/head.php';

// Form action and url handling
$request_url = set_page();

# array should be set in config/nconf.php
if (!isset($STATIC_CONFIG)){
    $STATIC_CONFIG = array();
    message($error, "The STATIC_CONFIG array must be set in your configuration.");
}else{
    # Directory
    if ( !empty($_POST["directory"]) ) {
        $directory = $_POST["directory"];
    }else{
        $directory = $STATIC_CONFIG[0];
    }
}

# Filename
if ( isset($_POST["filename"]) ) {
    $filename = $_POST["filename"];
    $full_path = $directory.'/'.$filename;
}
# new fileContent
if ( isset($_POST["content"]) ) {
    $content = $_POST["content"];
}

# set basic action
if ( isset($_POST["action"]) ) {
    $action = $_POST["action"];
}else{
    $action = "Open";
}





###
# Save file
###
if ( ($action == "Save") AND (isset($content) AND isset($full_path) ) ){
    # try to open config file writable
    $fh = @fopen($full_path, "w");
    if ($fh === FALSE){
        message($error, "The config file ($full_path) could not be saved.");
        $saved = FALSE;
    }else{
        #write to file
        if ( fwrite($fh, $content) == FALSE){
            # could not write to file
            message($info, "The config directory and all contents must be writeable to your webserver user.", "overwrite");
            message($error, "Could not write config file($full_path). Make sure the directory and all contents are writable to your webserver user.");
            $saved = FALSE;
        }else{
            # write file success
            message($info, "Changes saved successfully.", "overwrite");
            $saved = TRUE;
        }
        fclose($fh);

    }
}

###
# Open file
###



if( isset($full_path) AND !empty($filename) ){
    # read the config file
    if (isset($saved) AND $saved == FALSE){
        $file_content = $content;
        #$file_content = $POST["content"];
    }else{
        $file_content = @file_get_contents($full_path);
        if ($file_content === FALSE){
            message($error, "The config file ($full_path) could not be read.");
        }
    }
}




###
# Info/Warning in the top right corner
###
echo '<h2 style="margin-right:4px">Edit static config files</h2>';

echo '
<div class="editor_info">
    <table width=100% border=0 frame="box" rules=none cellspacing=0 cellpadding=6>';
        if (!empty($error)){
            echo '
          <tr class="box_content"><td class="bg_header"><b>WARNING</b></td></tr>
          <tr class="box_content"><td class="box_content" valign=top>
            '.ICON_WARNING.'
            <span style="vertical-align: middle; height: 24px;">
                <b>
                    '.$error.'
                    <br>The webserver user must have write permissions to your config directory, <br>otherwise NConf cannot save your changes.
                </b>
            </span>
            </td>
          </tr>';
        }elseif( !empty($info) AND !empty($saved) ){
            echo '
              <tr class="box_content">
                <td class="bg_header">
                    <b>Successfully saved file</b>
                </td>
              </tr>
              <tr class="box_content">
                <td class="box_content" valign="top">'.SHOW_ATTR_YES.'
                    <span style="vertical-align: middle; height: 24px;">
                        <b>'.$info.'</b>
                    </span>
                </td>
              </tr>';
        }else{
            echo '
              <tr class="box_content">
                <td class="bg_header">
                    <b>Info</b>
                </td>
              </tr>
              <tr class="box_content">
                <td class="box_content" valign="top">
                    <b>
                        This mask allows administrators to modify static Nagios configuration files.
                    </b>
                </td>
              </tr>
            ';
        }
echo '
    </table>
</div>';


echo '<form name="editor" action="'.$request_url.'" method="post">
<table>';



###
# List directories and files for editing
###
    echo '<tr>';
        echo '<td>Directory</td>';
    echo '</tr>';
    echo '<tr>
            <td>
                <select name="directory" style="width:192px" onchange="document.editor.filename.value=\'\'; document.editor.submit()">';
        foreach($STATIC_CONFIG as $config_dir){
            echo "<option value=$config_dir";
            if ( (!empty($directory) ) AND ($directory == $config_dir) ) echo " SELECTED";
            echo ">$config_dir</option>";
        }

        echo '  </select>&nbsp;&nbsp;
            </td>';
    echo '</tr>';
    if ( !empty($directory) ){
        echo '<tr>';
            echo '<td>Files</td>';
        echo '</tr>';
        $config_files = getFiles($directory);

        echo '<tr>';
            echo '<td><select name="filename" style="width:192px" onchange="document.editor.submit()">';
            echo '<option value="">choose a file...</option>';

            foreach($config_files as $config_filename){
                echo "<option value=$config_filename";
                if ( (isset($filename) ) AND ($filename == $config_filename) ) echo " SELECTED";
                echo ">$config_filename</option>";
            }

            echo '</select>&nbsp;&nbsp;</td>';
        echo '</tr>';
    }

echo '</table>';




###
# Display editor
###
if ( (!empty($directory) AND !empty($filename) ) AND ($file_content !== FALSE) ){

    echo '<h3>&nbsp;Editor</h3>';
    echo '<table style="width:100%">';

    echo '<tr class="bg_header">';
        echo '<td>';
            echo '<input type="submit" value="Open" name="action" align="middle" style="width:40px">';
            echo "&nbsp;";
            echo '<input type="submit" value="Save" name="action" align="middle" style="width:40px">';
        echo '</td>';
    echo "</tr>";

    # Edit field
    echo '<tr>
            <td width="100%">';

        echo '<textarea class="editor_field" name="content" rows="30" wrap="soft">';
            echo $file_content;
        echo '</textarea>';

    echo '  </td>
         </tr>'; 


    echo '</table>';

}



echo '</form>';


mysql_close($dbh);
require_once 'include/foot.php';

?>
