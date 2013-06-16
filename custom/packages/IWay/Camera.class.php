<?php
/* Copyright (C) 2011 by iRail vzw/asbl
 *
 * Author: Quentin Kaiser <kaiserquentin@gmail.com>
 * License: AGPLv3
 *
 * This method of IWay will get the live highway cameras
 */
include_once "Geocoder.php";
class IWayCamera extends AResource{


  private $from;
  private $area;
  private $max;

     
  public static function getParameters(){
    return array(
      "max" => "Maximum of cameras you want to retrieve",
      "from" => "Geographic coordinates that you want data around (format : latitude,longitude)",
      "area" => "Area around <from> where you want to retrieve cameras"
    );
  }

  
  public static function getRequiredParameters(){
    return array();
  }

  
  public function setParameter($key,$val){
    if($key == "max"){
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
    
    $cameras = R::find("cameras", "1");
    $result = new stdClass();
    $j = 0; 
    for($i=0; $i < count($cameras); $i++){
      if($cameras[$i]->enabled){ 
        $result->item[$j] = new stdClass();
        $result->item[$j]->city = $cameras[$i]->city;
        $result->item[$j]->zone = $cameras[$i]->zone;
        $result->item[$j]->img = $cameras[$i]->img;
        $result->item[$j]->lat = $cameras[$i]->lat;
        $result->item[$j]->lng = $cameras[$i]->lng;
        $result->item[$j]->id = $j;
        $j++;
      }
    }
    return $result;
  }
   
   
  public function call(){
    
    $c = Cache::getInstance();
    $element = $c->get("cameras");
    
    if(is_null($element)){
      $element = $this->getData();
      $c->set("cameras", $element, 600);
    }

    /* From, area and proximity */
    if($this->from != "" && $this->area > 0){
      
      $items = array();
      for($i = 0; $i < count($element->item); $i++){
        $distance = Geocoder::distance(array("latitude"=>$this->from[0], "longitude"=>$this->from[1]),array("latitude"=>$element->item[$i]->lat, "longitude"=>$element->item[$i]->lng));
        
        if($distance < $this->area){
          $element->item[$i]->distance = $distance;
          array_push($items, $element->item[$i]);
        }
      }
      usort($items, 'Geocoder::cmpDistances');
      $element->item = $items;
    }

    /* Max parameter */
    //As elements are stored in cache, if a user request items with max parameter there will be missing items for next requests
    // so I use array_slice, that's NOT lazy :)
    if($this->max > 0 && $this->max < count($element->item))
      $element->item = array_slice($element->item, 0, $this->max);
      return $element;
    }

    
    public static function getAllowedPrintMethods(){
      return array("json","xml", "jsonp", "php", "html", "kml","map");
    }

    public static function getDoc(){
      return "Camera return a list of all known highway webcams";
    }
}
?>
