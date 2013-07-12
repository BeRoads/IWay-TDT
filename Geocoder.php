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
    public static function geocode($address, $tool = "gmap") {
	error_log("Geocoding ".$address);
	$c = Cache::getInstance();
	if($c->get($tool."_requests") != null){
		$total = $c->get($tool."_requests");
		$total++;
		$c->set($tool."_requests", $total, 86400); 
	}

	array_push(Geocoder::$from, $address);
        //gmap api geocoding tool
        if($tool=="gmap") {
	    $request_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&sensor=false";
            
	    $response = TDT::HttpRequest($request_url);
	    sleep(1);
	    $json = json_decode($response->data);
            $status = $json->status;
	    error_log($status);
            //successful geocode
            if (strcmp($status, "OK") == 0) {
                $geocode_pending = false;
                $coordinates = $json->results[0]->geometry->location;
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

	$keywords = array(
                        array("fr" => "à", "en" => "in", "nl" => "in", "de" => "der"),
                        array("fr" => "vers", "en" => "to", "nl" => "naar", "de" => "nach"),
                        array("fr" => "la", "en" => "the", "nl" => "de", "de" => "der"),
			array("fr" => "à hauteur de", "en" => "ter hoogte van", "nl" => "ter hoogte van", "de" => "ter hoogte van"),
			array("fr"=>"en direction de", "en" => "richting", "nl" => "richting", "de" => "richting")
        );

	if($region=="federal" || $region == "flanders"){
                preg_match("/[\s\S]* " . $keywords[0][$language] . " ([\s\S]*)/", $data, $match);
                if(count($match)==2){
			$data = $match[1];
		}else{
			preg_match("/[\s\S]* ([\s\S]*) -> [\s\S]*/", $data, $match);
        		if(count($match)==2){
				$data = $match[1];
			}else{
				preg_match("/[\s\S]* ".$keywords[1][$language] . " ([\s\S]*)/", $data, $match); 
				//preg_match("/[\s\S]* ".$keywords[3][$language] . " ([\s\S]*) " . $keywords[4][$language] . " [\s\S]*/", $data, $match);
				$data = (count($match)==2?$match[1]:null);
			}
		}
	}
    else{
		throw new Exception("Wrong region parameter, please retry.");
    }
	
	if($data==null){
		return array("longitude" => 0, "latitude" =>0);
	}
	$data = utf8_encode($data) . ", Belgium";
	if($coords = Geocoder::isGeocoded($data)){
	 	return $coords;
	}else{
		return Geocoder::geocode($data);
	}           
    }
};
?>
