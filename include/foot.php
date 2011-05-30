<!-- BEGIN "foot.php" -->

    </div> <!-- END OF DIV "maincontent" -->
</div>     <!-- END OF DIV "mainwindow" -->
<!-- empty clear div for space to footer (without that, it will not work correctly when footer has clear:both! -->
<div class="clearer"></div>
<div id="footer">
    <div>
        <?php
        echo '<b>Info:</b><br>';
        if (!empty($info)) echo $info;
        echo '<br>';

        if ($error != ""){
            echo '<b>Error:</b><br><font color="red">'.$error.'</font><br>';
        }

        if (DEBUG_MODE == 1){
            echo '<hr>
                        <b>Debug:</b><br>'.$debug.'<br>
            ';
            
            # COOKIE
            echo '<hr><b>COOKIE:</b><br>';
            echo '<img id="swap_icon_DEBBUG_COOKIE" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_COOKIE\')"> print_r</a>';
            echo '<div id="DEBBUG_COOKIE" style="display: none">';
                echo "<pre>";
                    print_r($_COOKIE);
                echo "</pre>";
            echo '</div>';
            echo '<h2>print_r($_COOKIE)</h2>';
            echo '<img id="swap_icon_DEBBUG_COOKIE2" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_COOKIE2\')"> var_dump</a>';
            echo '<div id="DEBBUG_COOKIE2" style="display: none">';
                echo "<pre>";
                    var_dump($_COOKIE);
                echo "</pre>";
            echo '</div>';

            # POST
            echo '<hr><b>POST:</b><br>';
            echo '<img id="swap_icon_DEBBUG_POST" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_POST\')"> print_r</a>';
            echo '<div id="DEBBUG_POST" style="display: none">';
                echo "<pre>";
                    print_r($_POST);
                echo "</pre>";
            echo '</div>';
            echo '<h2>print_r($_POST)</h2>';
            echo '<img id="swap_icon_DEBBUG_POST2" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_POST2\')"> var_dump</a>';
            echo '<div id="DEBBUG_POST2" style="display: none">';
                echo "<pre>";
                    var_dump($_POST);
                echo "</pre>";
            echo '</div>';
            

            # SESSION
            $session_footer_output = $_SESSION;
            # remove serverlist (is obsolet for debuging)
            if ( isset($session_footer_output["cmdb_serverlist"]) ) unset($session_footer_output["cmdb_serverlist"]);

            echo '<hr><b>SESSION:</b><br>';
            echo '<img id="swap_icon_DEBBUG_SESSION" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_SESSION\')"> print_r</a>';
            echo '<div id="DEBBUG_SESSION" style="display: none">';
                echo "<pre>";
                    print_r($session_footer_output);
                echo "</pre>";
            echo '</div>';
            echo '<h2>print_r($_SESSION)</h2>';
            echo '<img id="swap_icon_DEBBUG_SESSION2" src="img/icon_expand.gif"/><a href="javascript:swap_visible(\'DEBBUG_SESSION2\')"> var_dump</a>';
            echo '<div id="DEBBUG_SESSION2" style="display: none">';
                echo "<pre>";
                    var_dump($session_footer_output);
                echo "</pre>";
            echo '</div>';


        }
        ?>
          &nbsp;

    </div>
</div> <!-- END OF DIV "footer" -->

</body>
</html>
