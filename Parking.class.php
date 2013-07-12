<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get the parkings of belgium territory
 */

class IWayParking extends AResource{

     private $lang;
     private $region;
     private $from;
     private $area;
     private $max;

     
     public static function getParameters(){
	  return array("lang" => "Language in which the parkings should be returned", 
		       "region" => "region that you want data from",
		       "max" => "Maximum of parkings you want to retrieve",
		       "from" => "",
		       "area" => "Area around from parameter where you want to retrieve parkings"			
		);
     }

     public static function getRequiredParameters(){
	  return array();
     }

     public function setParameter($key,$val){
	  if($key == "lang"){
	       $this->lang = $val;
	  }
	  else if($key == "region"){
		$this->region = $val;
	  }
	  else if($key == "max"){
		$this->max = $val;
	  }
	  else if($key == "from"){
		$this->from = explode(",",$val);
	  }
	  else if($key == "area"){
		$this->area = $val;
	  }
     }

	private function getData(){
	 	R::setup(Config::$DB, Config::$DB_USER, Config::$DB_PASSWORD);

        $self->queryResult = R::find(
            'parkings',
            'url_request = :url_request',
            array(':url_request' => TDT::getPageUrl())
        ); 
     }
     
    public function call(){
	  	$this->getData();
        return $this->queryResults;
     }

    
 
     public static function getAllowedPrintMethods(){
	  return array("json","xml", "jsonp", "php", "html");
     }

     public static function getDoc(){
	  return "This is a function which will return all the belgium parkings";
     }

}

?>
