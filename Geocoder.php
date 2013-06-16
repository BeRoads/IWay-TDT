<?php

/* Copyright (C) 2011 by iRail vzw/asbl */
/* 
  This file is part of iWay.

  iWay is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  iWay is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with iWay.  If not, see <http://www.gnu.org/licenses/>.

  http://www.beroads.com

  Source available at http://github.com/QKaiser/IWay
 */

/**
 * All functionnalities about geolocation (get coordinates from API like 
 * GMap, Bing or OSM; compute distance between coordinates).
 */

class Geocoder {

    /*  
	static vars so when we are asking coordinates for a place that have been geocoded previously, we return coordinates
	that we have stored before
    */

    public static $from = array();
    public static $from_coordinates = array();


    public static function distance($from, $to){

		
	$earth_radius = 6371.00; // km

	$delta_lat = $to["latitude"]-$from["latitude"];
	$delta_lon = $to["longitude"]-$from["longitude"]; 

	  $alpha    = $delta_lat/2;
	  $beta     = $delta_lon/2;
	  $a        = sin(deg2rad($alpha)) * sin(deg2rad($alpha)) + cos(deg2rad($from["latitude"])) * cos(deg2rad($to["latitude"])) * sin(deg2rad($beta)) * sin(deg2rad($beta)) ;
	  $c        = asin(min(1, sqrt($a)));
	  $distance = 2*$earth_radius * $c;
	  return round($distance);
	   
	}


    public static function cmpDistances($a, $b){

	if($a == $b)
		return 0;
	else 
		return ($a->distance < $b->distance) ? -1 : 1;
    }
    public static function sortByDistance($array){

	usort($array, Geocoder::cmpDistances);

    }
    public static function geocode($address, $tool = "osm") {

	$c = Cache::getInstance();
	if($c->get($tool."_requests") != null){
		$total = $c->get($tool."_requests");
		$total++;
		$c->set($tool."_requests", $total, 86400); 
	}

	array_push(Geocoder::$from, $address);
        //gmap api geocoding tool
        if($tool=="gmap") {
	    $request_url = "https://maps.googleapis.com/maps/api/geocode/xml?address=" . urlencode(utf8_encode($address)) . "&sensor=false";
            $data = TDT::HttpRequest($request_url);
	    $xml = simplexml_load_string($data->data);
            $status = $xml->GeocodeResponse->status;

            //successful geocode
            if (strcmp($status, "OK") == 0) {

                $geocode_pending = false;
                $coordinates = $xml->GeocodeResponse->result->geometry->location;
		array_push(Geocoder::$from_coordinates, array("longitude"=> $coordinates->lng, "latitude" => $coordinates->lat));
                
            }
	    //nothin' !
	    else if (strcmp($status, "ZERO_RESULTS") == 0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
            //too much requests, gmap server can't handle it
            else if (strcmp($status, "OVER_QUERY_LIMIT") == 0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
	    //lack of sensor ?
	    else if (strcmp($status, "REQUEST_DENIED") == 0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
            else if (strcmp($status, "INVALID_REQUEST") == 0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
	    //server side error, we can retry later
	    else if (strcmp($status, "UNKNOWN_ERROR") == 0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
	    else {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
	    return Geocoder::$from_coordinates[count(Geocoder::$from_coordinates)-1];
        }
        //openstreetmap geocoding tool (Nominatim)
        else if($tool=="osm") {

	    error_log("Geocoding ".$address);
            $base_url = "http://nominatim.openstreetmap.org/search/be/".urlencode($address)."/?format=json&addressdetails=0&limit=1&countrycodes=be";

            $json = TDT::HttpRequest($base_url)->data;

	    $data = json_decode($json);
	    
            if(count($data)==0) {
                array_push(Geocoder::$from_coordinates, array("longitude" => 0,"latitude" => 0));
            }
            else {
                $place = $data[0];
                array_push(Geocoder::$from_coordinates, array("longitude" => (string)$place->lon, "latitude" => (string)$place->lat));
            }
	    return Geocoder::$from_coordinates[count(Geocoder::$from_coordinates)-1];
        }
        //bing map api geocoding tool
        else if($tool=="bing") {
            throw new Exception("Not yet implemented.");
        }
        else {
            throw new Exception("Wrong tool parameter, please retry.");
        }
    }

    public static function isGeocoded($address){
	if($index = array_search($address, Geocoder::$from)){
		return Geocoder::$from_coordinates[$index];
	}else{
		return false;
	}
    } 
		

    public static function geocodeData($data, $region, $language) {

	 $keywords = array(array("fr" => "à", "en" => "at", "nl" => "in", "de" => "der"),array("fr" => "vers", "en" => "to", "nl" => "richting", "de" => "nach"));
        if($region=="wallonia") {
	     
		
	    
	     $data = explode($keywords[0][$language], $data);
	     if(count($data) > 1){
		     $data = explode($keywords[1][$language], $data[1]);
		     $data = $data[0];     
	     
	             if(count(explode("-", $data)) >= 2){
			$data = explode("-", $data);
			$data = $data[0];
		     }	
	    }else{
		$data = $data[0];	
	    }     
	     
	     //check of already geocoded
	     if($coords = Geocoder::isGeocoded($data)){
		 	return $coords;
	     }else{
		 	return Geocoder::geocode($data);
	     }           
        }
	else if($region=="flanders") {

	    $tab = explode("->", $data);
	  
            if(count($tab) >= 2){
		$tab = preg_split('/ /', $tab[1], -1, PREG_SPLIT_OFFSET_CAPTURE);
		if(isset($tab[1][0])){
			$tab = $tab[1][0];
		    if($coords = Geocoder::isGeocoded($tab)){
			return $coords;
		    }else{
			return Geocoder::geocode($tab);
		    }
		}
		
            }else{
		 $tab = explode(" ", $data);
		 if($coords = Geocoder::isGeocoded($tab[1])){
			return $coords;
		 }else{
		 	return Geocoder::geocode($tab[1]);
		 }
	    }
        }
        else if($region=="federal"){
             
	    if(strstr($data, $keywords[0][$language]) != false) {
                $tab = explode($keywords[0][$language], $data);
                
		if($coords = Geocoder::isGeocoded($tab[1])){
			return $coords;
		 }else{
		 	return Geocoder::geocode($tab[1]);
		 }
                
            }
            else {
                return array("longitude" => 0,"latitude" => 0);
            }
        }
        else{
            throw new Exception("Wrong region parameter, please retry.");
        }

    }
};
?>
