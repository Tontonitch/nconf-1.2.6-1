<?php
echo '<h2 class="header"><span>Update</span></h2>';

#if (!empty($step) ){
echo '
    <div class="box_content">
        <table border=0 width=188>
                <tr>
                    <td>
                        ';
                        if ( !empty($step) AND $step == 0 ){
                            echo '<div class="link_with_tag_active">compatibility check</div>';
                        }else{
                            echo '<div class="link_with_tag">compatibility check</div>';
                        }

                        # steps:
                        for ($i = 1; $i< 4; $i++){
                            echo '<br><div class="';
                            if ( $i == $step ){
                                echo "link_with_tag_active";
                            }else{
                                echo "link_with_tag";
                            }
                            echo '">step '.($i).'</div>';
                        }
                        echo '
                    </td>
                </tr>

        </table>
    </div>
    ';

#}

/*
if (!empty($_SESSION) ){
echo '
    <h2 class="header"><span>Restart</span></h2>
    <div class="box_content">
        <table border=0 width=188>
                <tr>
                    <td>
                        <a class="link_with_tag" href="INSTALL.php?logout=1" >restart installation</a>
                    </td>
                </tr>
        </table>
    </div>
    ';

}
*/

?>
