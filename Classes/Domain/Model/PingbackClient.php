<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RequestPingback
 *
 * @author Michael
 */
class PingbackClient extends PHTH\Pongback\Domain\Model\Pingback{
        
    protected $ClientUrlForHeader;
    
    public function ownWebsiteURL(){
      $this-> ClientUrlForHeader = 'http://www.'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']; 
    }
    
    
}
