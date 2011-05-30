<?php


if( ( isset($_GET["item"]) ) AND ($_GET["item"] != "") ){
    $config_class = $_GET["item"]; 

    $query = "SELECT id_attr,predef_value,datatype,fk_show_class_items 
                            FROM ConfigAttrs,ConfigClasses 
                            WHERE visible='no' 
                            AND id_class=fk_id_class 
                            AND config_class='$config_class'
    ";
    $result = db_handler($query, "result", "Load not visible attrs");
    
    while($entry = mysql_fetch_assoc($result)){

        if( ($entry["datatype"] == "text") OR ($entry["datatype"] == "select") ){
            $output = '<input type="hidden" name="'.$entry["id_attr"].'" value="'.$entry["predef_value"].'">';
            echo $output;
            $output = str_replace("<", "", $output);
            message($debug, "Hidden Field:". $output);

        }elseif( $entry["datatype"] == "assign_one" ){


            $query2 = 'SELECT fk_id_item 
                                    FROM ConfigValues,ConfigAttrs 
                                    WHERE id_attr=fk_id_attr 
                                    AND naming_attr="yes" 
                                    AND fk_id_class="'.$entry["fk_show_class_items"].'"
                                    AND attr_value="'.$entry["predef_value"].'";
            ';
            $result2 = db_handler($query2, "result", "not visible: Load linked item");
            while($entry2 = mysql_fetch_assoc($result2)){
                $output2 = '<input type="hidden" name="'.$entry["id_attr"].'[]" value="'.$entry2["fk_id_item"].'">';
                echo $output2;
                $output2 = str_replace("<", "", $output2);
                message($debug, "Hidden Field:". $output2);
            }

        }elseif( ($entry["datatype"] == "assign_many") OR ($entry["datatype"] == "assign_cust_order") ){
            
            # split predef value
            $predef_values = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
            foreach ($predef_values AS $predef_value){
                $query2 = 'SELECT fk_id_item 
                            FROM ConfigValues,ConfigAttrs 
                            WHERE id_attr=fk_id_attr 
                            AND naming_attr="yes" 
                            AND fk_id_class="'.$entry["fk_show_class_items"].'"
                            AND attr_value="'.$predef_value.'";
                          ';
                $entry2 = db_handler($query2, "getOne", "not visible assign_MANY/CUST_ORDER: Load linked item");
                $output2 = '<input type="hidden" name="'.$entry["id_attr"].'[]" value="'.$entry2.'">';
                echo $output2;
                $output2 = str_replace("<", "", $output2);
                message($debug, "Hidden Field:". $output2);
            }
        }
        
    }

}


?>
