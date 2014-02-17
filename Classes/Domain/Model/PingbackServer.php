<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PingbackServer
 *
 * @author Michael
 */


 function uptime_func($method_name, $params, $app_data) {
        return 'Das erste läuft';
    
        }

     function greeting_func($method_name, $params, $app_data){
    
    $name = $params[0]; 
    return "hello, $name . Wie gehts dir?";
    
}
function pingback_func($method_name, $params, $app_data) {
        $targetLink = $params[0]; 
        $sourceLink = $params[1]; 
             /* var_dump($params);die(); 
              var_dump($app_Data);*/
         $pongback = new Pongback(); 
          $pongback->saveSourceURL($params[1]); 
    return "Kommt von $sourceLink geht zu $targetLink"; 
   
        
    }
    
   
   
 $xmlrpc_server = xmlrpc_server_create();
  
 xmlrpc_server_register_method($xmlrpc_server, 'greeting', 'greeting_func'); 
 xmlrpc_server_register_method($xmlrpc_server, 'uptime', 'uptime_func'); 
 xmlrpc_server_register_method($xmlrpc_server, 'pingback.ping', 'pingback_func'); 
 $request_xml = $HTTP_RAW_POST_DATA; 
 $response = xmlrpc_server_call_method($xmlrpc_server, $request_xml,'');
 print $response; 

 xmlrpc_server_destroy($xmlrpc_server); 
        
 
class PingbackServer {
    //put your code here
}
