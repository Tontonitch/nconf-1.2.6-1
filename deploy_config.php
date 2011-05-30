<?php
    require_once 'config/main.php';
    require_once 'include/head.php';

    if(ALLOW_DEPLOYMENT == 1 && $_POST["status"] == "OK"){
        $post_data = array();

        $post_data['file'] = "@output/NagiosConfig.tgz";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, CONF_DEPLOY_URL );
        curl_setopt($ch, CURLOPT_POST, 1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if((defined('CONF_DEPLOY_USER') and CONF_DEPLOY_USER != "") and (defined('CONF_DEPLOY_PWD') and CONF_DEPLOY_PWD != "")){
        	curl_setopt($ch, CURLOPT_USERPWD, CONF_DEPLOY_USER.":".CONF_DEPLOY_PWD);
        }

        $postResult = curl_exec($ch);

        curl_close($ch);

        if($postResult == "OK"){
            // Log to history
            history_add("general", "config", "deployed");

            echo "<br><b>Config deployment completed successfully.</b>";
        }else{
            echo "<br><div id=attention>Config deployment failed!</div>";
        }
    }else{
        echo "<br><div id=attention>Deployment functionality is currently disabled.</div>";
    }

    require_once 'include/foot.php';
?>
