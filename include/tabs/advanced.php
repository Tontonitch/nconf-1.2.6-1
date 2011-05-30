<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>

<!--
ajax_loadContent('maincontent','call_ajax.php?ajax_file=exec_generate_config.php&username=".$_SESSION['userinfos']['username']."');
onclick="createCookie('advanced_status','1',0)"
-->

<div class="tab_advanced accordion">
    <div class="accordion" style="position: absolute; width:inherit;">
        <div class="dhtmlgoodies_question">
            <h2 class="header">
                <span>
                    <img src="img/icon_expand.gif" id="dhtmlgoodies_question_expandImg">
                    Advanced
                </span>
            </h2>
        </div>

    <?php

    # movable content
    echo '<div class="dhtmlgoodies_answer">
            <div>';


        # Content
        echo '<table id="advanced_tab">';
          echo '<tr class="box_content">
                <td>

                <table border="0">';
                echo '<colgroup>
                        <col width="30">
                        <col>
                      </colgroup>';
                if ($class == "host"){
                    echo'<tr>
                        <td style="text-align: center">
                                <input type="image" src="'.ADVANCED_ICON_CLONE.'" value="clone" name="clone" style="width: 16px; height: 16px; border-style:none" onclick="document.advanced.submit();">
                        </td>
                        <td><a href="javascript:submitform(\'advanced\', \'clone\');">clone</a></td>
                      </tr>';
                }
                echo '<tr onclick="document.advanced.submit();">
                        <td style="text-align: center">
                            <input type="image" src="'.ADVANCED_ICON_MULTIMODIFY.'" value="multimodify" name="multimodify" style="width: 16px; height: 16px; border-style:none" onclick="document.advanced.submit();">
                        </td>
                        <td><a href="javascript:submitform(\'advanced\', \'multimodify\');">multi modify</a></td>
                      </tr>';
            echo '<tr onclick="document.advanced.submit();">
                    <td style="text-align: center">
                        <input type="image" src="'.ADVANCED_ICON_DELETE.'" value="multidelete" name="multidelete" style="width: 16px; height: 16px; border-style:none" onclick="document.advanced.submit();">
                    </td>
                    <td><a href="javascript:submitform(\'advanced\', \'multidelete\');">delete</a></td>
                  </tr>';
            echo '<tr>
                    <td style="text-align: center; height: 16px;">
                        <a href="javascript:swap_checkboxes(\'advanced_items\');"><img src="'.ADVANCED_ICON_SELECT.'" align="center" style="vertical-align: middle; width: 16px; height: 16px; border-style:none; margin: 0px; padding:0px;"></a>
                    </td>
                    <td><a href="javascript:swap_checkboxes(\'advanced_items\');">select all</a></td>
                  </tr>';
          echo '</table>
               </td>
              </tr>';
        echo '</table>';


    # Close answer (movable content)
    echo '  </div>
        </div>';


    # Close tab:
    ?>

    </div>
</div>


<?php

# activate movable content
echo js_prepare("initShowHideDivs('advanced_box', 0, true);");

?>
