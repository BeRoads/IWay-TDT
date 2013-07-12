<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get forecast about trafic jams and travel times in belgium
 */
include_once 'simple_html_dom.php';

class IWayForecast extends AResource{

     
    private $type;
    
    public static function getParameters(){
		return array(
			"type" => "The type of forecast that you want to retrieve. Available : traveltime, traficjam."
		);
	}

    
    public static function getRequiredParameters(){
		return array("type");
    }

    
    public function setParameter($key,$val){
		if($key == "type"){
			$this->type = $val;
	  	}
    }

	
	private function getData($type){
		
		$result = new stdClass();
		$result->item = array();
		
		if(!strcmp($type, "traveltime")){
			$url = "http://www.rtbf.be/services/mobilinfo/previsions-trafic";
			$data = utf8_encode(TDT::HttpRequest($url)->data);
			$html = str_get_html($data);

		        $times = $html->find('div[class=head] span');
		        $lengths = $html->find('span[class=indicationPannel]');
		
		        for($i=0; $i<count($times); $i++){
		                $result->item[$i] = new stdClass();
		                preg_match("/(\d\d)-(\d\d)-(\d\d\d\d)?/",$times[$i]->innertext,$match);
		                $result->item[$i]->time = mktime(0, 0, 0, $match[2], $match[1], $match[3]);
		                preg_match("/(\w+)<br \/>(\d+)-(\d+) km?/", $lengths[$i]->innertext, $match);
		                $result->item[$i]->from = $match[2];
		                $result->item[$i]->to = $match[3];              
		        }
		}
		else if(!strcmp($type, "traficjam")){
			$url = "http://www.rtbf.be/services/mobilinfo/temps-parcours";
			$data = utf8_encode(TDT::HttpRequest($url)->data);
			$html = str_get_html($data);
		        $forecasts = $html->find('div[id=mobilTabs-2] table tr');
			$i=0;
		        foreach($forecasts as $tr){
		                $element->item[$i++] = new stdClass();
		                $tds = $tr->find('td');
		                for($i=0; $i<count($tds); $i++){
		                        if($i==0)
		                                $result->item[$i]->from = $tds[$i]->innertext;
		                        if($i==1)
		                                $result->item[$i]->to = $tds[$i]->innertext;
		                        if($i==2){
		                                preg_match("/(\d+) mins (\+(\d+))?/", $tds[$i], $match);
		                                $result->item[$i]->current_time = $match[1];
		                        }
		                        if($i==3){
		                                preg_match("/(\d+) mins?/", $tds[$i], $match);
		                                $result->item[$i]->normal_time = $match[1];
		                        }
		                }
		        }		
		}		
		return $result;
     }
     

    public function call(){
     
	$c = Cache::getInstance();
	$element = $c->get("forecast".$this->type);
	if(is_null($element)){
		$element = $this->getData();
		$c->set("forecast".$this->type, $element, 300);
	}
	return $element;
     }

    public static function getAllowedPrintMethods(){
		return array("json","xml", "jsonp", "php", "html", "kml", "map");
    }

    
    public static function getDoc(){
		return "Forecast return data about travel times and trafic jam"; 
    }
}
?>
