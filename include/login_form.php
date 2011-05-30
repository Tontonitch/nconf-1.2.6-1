<table border="0">
    <tr>
        <td>
            <?php

                if ( !isset($_SESSION["group"]) ) {

                    if ( !empty($_GET["goto"]) ){
                        $url = $_GET["goto"];
                    }else{
                        $url = "index.php";
                    }

                    ?>
                    <table>
                        <tr>
                            <td><br><?php echo VERSION_STRING."<br><br>".COPYRIGHT_STRING; ?></td>
                        </tr>
                    </table>
                    <br><br>
                    <form action="<?php echo $url; ?>" method="POST">
                    <table border=0 frame=box rules=none cellspacing=2 cellpadding=2>
                        <tr>
                            <td width=75>
                                &nbsp;<b>Login as:</b>
                            </td>
                            <td>
                                <input style="width:200px" type="text" name="username">
                            </td>
                        </tr>
                        <tr>
                            <td width=75>
                                &nbsp;<b>Password:</b>
                            </td>
                            <td>
                                <input style="width:200px" type="password" name="password">
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2><br>
                                <input type="hidden" name="authenticate" value="yes">
                                <input style="width:75px" type="submit" value="login">
                            </td>
                        </tr>
                    </table>
                    </form>
                    <?php

                }

            ?>
        </td>
    </tr>
    <tr>
        <td>
            <table>
                <tr>
                    <td width="600" colspan=2>
                        <?php
                            if ( isset($_SESSION["group"]) ) { 
                                # This will only be displayed on the main "home" page (when authenticated)
                                echo VERSION_STRING."<br><br>";
                                echo COPYRIGHT_STRING."<br><br>";
                                echo DISCLAIMER_STRING."<br><br>";
                                echo POWERED_BY_LOGOS;
                            }else{
                                # This will be displayed on the login screen
                                echo "<br><br>".POWERED_BY_LOGOS;
                            }
                        ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
