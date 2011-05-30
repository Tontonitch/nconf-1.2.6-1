<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>

<div class="tab_advanced">
    <div class="accordion">
        <h2 class="header">
            <span>
                Advanced
            </span>
        </h2>
    <?php
    # Content
    echo '<div class="box_content">';
        echo '<table>';
                echo'<tr>
                    <td width="30" style="text-align: center">
                        <a href="clone_service.php?id='.$host_ID.'">
                            <img src="'.ADVANCED_ICON_CLONE.'" style="border-style: none; margin: 0px; padding: 0px; vertical-align: middle; width: 16px; height: 16px;">
                        </a>
                    </td>
                    <td>
                        <a href="clone_service.php?id='.$host_ID.'">
                            clone a service to other hosts
                        </a>
                    </td>
                  </tr>';
        echo '</table>';
    echo '</div>';
    ?>
    </div>
</div>
<?php
