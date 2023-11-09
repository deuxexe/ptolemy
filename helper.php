<?php
/**
 * DokuWiki Ptolemy
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brad Osborne <bosborne.csm@gmail.com>, Yuuki
 * 
 * Integrated with (and inspired by) the Struct plugin by Andreas Bohr.
 * 
 */

class helper_plugin_ptolemy extends DokuWiki_Plugin
{

    public function __construct(){

        $this->hlp_struct = plugin_load('helper', 'struct');
        if (!$this->hlp_struct) {
            if (defined('DOKU_UNITTEST')) throw new \Exception('Couldn\'t load struct.');
            return;
        }
    }
    /**
     * @param string $val
     *
     * @return array
     */
    
    public function getMapData($page) {
        global $conf;

        $locationNamespaces = array();
        $locationData = array();
        $opts = array();
        $opts['skipacl'] = 0; // no ACL skipping for XMLRPC
        $page = cleanID($page);

        $mapNamespace = substr($page, 0, strrpos($page, ':') );
        $mapData = str_replace(' ', '', $this->hlp_struct->getData($page)[$mapNamespace]);

        $mapData['map_page'] = $page;

        if (strpos($mapData['map_namespace'], ',') !== false){
          $locationNamespaces = explode(",",$mapData['map_namespace']);
        }else{
          array_push($locationNamespaces, $mapData['map_namespace']);
        }

        // For all desired namespaces..
        foreach ($locationNamespaces as &$locationNamespace){
          if(strlen($locationNamespace) > 0){
            $data = array();
            // Get all pages in the desired namespace
            $dir = utf8_encodeFN(str_replace(':', '/', $locationNamespace));
            search($data, $conf['datadir'], 'search_allpages', $opts, $dir);
    
            // For each page we found in the namespace, add it to our list of locations
            foreach ($data as &$element) {
                $elementData = $this->getLocationData($element);
                if(isset($elementData["marker_url"]) || isset($elementData["display_name"])){
                  array_push($locationData, $elementData);
                }
            }
            unset($data);
            unset($element);
          }
        }
        unset($locationNamespace);

        // Create the map object using the data we've gathered
        $mapObject = $this->buildMapObject($mapData,$locationData); // TODO - Follow locationNamespace

        try {
            return (json_encode($mapObject));
        } catch (StructException $e) {
            throw new RemoteException($e->getMessage(), 0, $e);
        }
    }
    
    function buildMapObject($mapData,$placesData){        
        $mapObject['mapConfiguration'] = $this->buildMapConfiguration($mapData);
        $mapObject['mapConfiguration']['map_page'] = $mapData['map_page'];
        $mapObject['markerLayers'] = $this->buildLayersObject($ns,$placesData,$mapObject['mapConfiguration']);

        return $mapObject;
    }

    function buildLayersObject($ns,$placesData,$mapData){
        $layersObject = array();
        $key = "";
        $visibility = "";
        $mapObject = null;

        foreach ($placesData as &$element) {
          if(isset($element["coords"]) && strlen($element["coords"])>0){
            if(!isset($element['parent_map']) || strlen($element['parent_map']) == 0 ){
              $element['parent_map'] = "";
            }else{
              // TODO: Test if Struct allows " in field names or not. This will not behave normally if so.
              $tempArray = explode("\"", $element['parent_map']);
              $element['parent_map'] = $tempArray[1];
              unset($tempArray);
            }

            if(!isset($element['showOwnMarkersOnly']) || strlen($element['showOwnMarkersOnly']) == 0 ){
              $element['showOwnMarkersOnly'] = "No";
            }

            // if a parent map is specified AND this marker belongs to it OR the map isn't restricted to only its own markers
            if(($element["parent_map"] == $mapData["map_page"]) || ($mapData["showOwnMarkersOnly"] == "No")){

              switch($element['visibility']){
                case "Landmark":
                  $visibility = "-2,-1,0,1";
                  break;
                case "Major RP Hub":
                  $visibility = "-1,0,1";
                  break;
                case "Minor RP Location":
                  $visibility = "0,1";
                  break;
                case "Trivial":
                  $visibility = "1";
                  break;
                default:
                  $visibility = "";
                  break;
              }

              if (!array_key_exists($element['visibility'], $layersObject)){
                  $layersObject[$element['visibility']] = array();
                  $layersObject[$element['visibility']]['layerName'] = $element['visibility'];
                  $layersObject[$element['visibility']]['layerVisibility'] = $visibility;
                  $layersObject[$element['visibility']]['objects'] = array();
              }

              switch($element["type"]){
                case "Point":
                  $mapObject = $this->buildMarker($element);
                  break;
                case "Line":
                  $mapObject = $this->buildLine($element);
                  break;
                case "Polygon":
                  $mapObject = $this->buildPolygon($element);
                  break;
                case "Label":
                  $mapObject = $this->buildLabel($element);
                  break;
              }
              if ($mapObject != null){
                array_push($layersObject[$element['visibility']]['objects'], $mapObject);
              }
              $mapObject = null;
            }
          }
        }
        unset($element);

        return array_values($layersObject);
    }

    function buildMarker($element){
      $markerCoords = explode(",",preg_replace("/[^0-9,]/", "", $element['coords']));
      if (count($markerCoords) != 2){
        $markerCoords = array(0,0);
      }else{
        if (is_numeric($markerCoords[0])){
            $markerCoords[0] = intval($markerCoords[0]);
        }
        if (is_numeric($markerCoords[1])){
            $markerCoords[1] = intval($markerCoords[1]);
        }
      }

      if(isset($element['display_name']) && strlen($element['display_name']) > 0 ){
        $element['display_name'] = htmlspecialchars($element['display_name']);
      }else{
        $element['display_name'] = "";
      }
      
      if(isset($element['tooltip_content']) && strlen($element['tooltip_content']) > 0 ){
        $element['tooltip_content'] = htmlspecialchars($element['tooltip_content']);
      }else{
        $element['tooltip_content'] = "";
      }

      if(isset($element['show_label']) && strlen($element['show_label']) > 0 ){
        $element['show_label'] = "Yes";
      }else{
        $element['show_label'] = "";
      }

      // If we add more anchor options, we'll want to improve this. For now, just making sure that users don't make a mistake in the schema and cause problems.
      if(!isset($element['marker_anchor']) || strlen($element['marker_anchor']) == 0){
        $element['marker_anchor'] = "Bottom Left";
      }

      $markerData = array(
          "name"=> $element['display_name'],
          "iconUrl"=> $element['marker_url'],
          "iconDimensions" => $this->getMediaDimensions($element['marker_url']),
          "tooltipContent"=> $element['tooltip_content'],
          "tooltipLink" => "doku.php?id=$element[id]",
          "showLabel" => $element['show_label'],
          "markerAnchor" => $element['marker_anchor'],
          "priority" => $element['visibility'],
          "type"=> "Feature",
          "geometry"=> array(
            "type"=> "Point",
            "coordinates"=> array(
              $markerCoords[0],
              $markerCoords[1]
            )
          )
      );

      return $markerData;
    }

    function buildLine($element){

      $lineArray = explode(";",$element['coords']);
      foreach($lineArray as $innerKey=>$polygonCoord){
        $tempArray = explode(",",preg_replace("/[^0-9,]/", "", $polygonCoord));
        if (is_numeric( $tempArray[0] )){ $tempArray[0] = intval($tempArray[0]); }else{ $tempArray[0] = 0; }
        if (is_numeric( $tempArray[1] )){ $tempArray[1] = intval($tempArray[1]); }else{ $tempArray[1] = 0; }
        $lineArray[$innerKey] = array($tempArray[0],$tempArray[1]);
      }

      if(isset($element['display_name']) && strlen($element['display_name']) > 0 ){
        $element['display_name'] = htmlspecialchars($element['display_name']);
      }else{
        $element['display_name'] = "";
      }
      
      if(isset($element['tooltip_content']) && strlen($element['tooltip_content']) > 0 ){
        $element['tooltip_content'] = htmlspecialchars($element['tooltip_content']);
      }else{
        $element['tooltip_content'] = "";
      }

      if(isset($element['show_label']) && strlen($element['show_label']) > 0 ){
        $element['show_label'] = "Yes";
      }else{
        $element['show_label'] = "";
      }

      if(!isset($element['color']) || !preg_match('/^#[a-f0-9]{6}$/i', $element['color'])){
        $element['color'] = "#0090C0";
      }

      $markerData = array(
          "name"=> $element['display_name'],
          "tooltipContent"=> $element['tooltip_content'],
          "tooltipLink" => "doku.php?id=$element[id]",
          "showLabel" => $element['show_label'],
          "priority" => $element['visibility'],
          "type"=> "Feature",
          "color"=> $element['color'],
          "geometry"=> array(
            "type"=> "LineString",
            "coordinates"=> $lineArray
          )
      );

      return $markerData;
    }

    function buildPolygon($element){
      $polygonOuterArray = explode("|",str_replace(array("[","]"),"",$element['coords']));
    
      foreach($polygonOuterArray as $key=>$polygonInnerArray){
        $polygonInnerArray = explode(";",$polygonInnerArray);
        foreach($polygonInnerArray as $innerKey=>$polygonCoord){
          $tempArray = explode(",",preg_replace("/[^0-9,]/", "", $polygonCoord));
          if (is_numeric( $tempArray[0] )){ $tempArray[0] = intval($tempArray[0]); }else{ $tempArray[0] = 0; }
          if (is_numeric( $tempArray[1] )){ $tempArray[1] = intval($tempArray[1]); }else{ $tempArray[1] = 0; }
          $polygonInnerArray[$innerKey] = array($tempArray[0],$tempArray[1]);
        }
        $polygonOuterArray[$key] = $polygonInnerArray;
      }

      if(isset($element['display_name']) && strlen($element['display_name']) > 0 ){
        $element['display_name'] = htmlspecialchars($element['display_name']);
      }else{
        $element['display_name'] = "";
      }
      
      if(isset($element['tooltip_content']) && strlen($element['tooltip_content']) > 0 ){
        $element['tooltip_content'] = htmlspecialchars($element['tooltip_content']);
      }else{
        $element['tooltip_content'] = "";
      }

      if(isset($element['show_label']) && strlen($element['show_label']) > 0 ){
        $element['show_label'] = "Yes";
      }else{
        $element['show_label'] = "";
      }

      if(!isset($element['color']) || !preg_match('/^#[a-f0-9]{6}$/i', $element['color'])){
        $element['color'] = "#0090C0";
      }

      $markerData = array(
          "name"=> $element['display_name'],
          "tooltipContent"=> $element['tooltip_content'],
          "tooltipLink" => "doku.php?id=$element[id]",
          "tooltipDirection" => "center",
          "showLabel" => $element['show_label'],
          "priority" => $element['visibility'],
          "type"=> "Feature",
          "color"=> $element['color'],
          "geometry"=> array(
            "type"=> "Polygon",
            "coordinates"=> $polygonOuterArray
          )
      );

      return $markerData;
    }

    function buildLabel($element){
      $markerCoords = explode(",",preg_replace("/[^0-9,]/", "", $element['coords']));
      if (count($markerCoords) != 2){
        $markerCoords = array(0,0);
      }else{
        if (is_numeric($markerCoords[0])){
            $markerCoords[0] = intval($markerCoords[0]);
        }
        if (is_numeric($markerCoords[1])){
            $markerCoords[1] = intval($markerCoords[1]);
        }
      }

      if(isset($element['display_name']) && strlen($element['display_name']) > 0 ){
        $element['display_name'] = htmlspecialchars($element['display_name']);
      }
      
      if(isset($element['tooltip_content']) && strlen($element['tooltip_content']) > 0 ){
        $element['tooltip_content'] = htmlspecialchars($element['tooltip_content']);
      }else{
        $element['tooltip_content'] = "";
      }

      // If we add more anchor options, we'll want to improve this. For now, just making sure that users don't make a mistake in the schema and cause problems.
      if(!isset($element['marker_anchor']) || strlen($element['marker_anchor']) == 0){
        $element['marker_anchor'] = "Bottom Left";
      }

      if(!isset($element['color']) || !preg_match('/^#[a-f0-9]{6}$/i', $element['color'])){
        $element['color'] = "#0090C0";
      }
      
      $markerData = array(
          "name" => $element['display_name'],
          "tooltipContent" => $element['tooltip_content'],
          "tooltipLink" => "doku.php?id=$element[id]",
          "markerAnchor" => $element['marker_anchor'],
          "tooltipDirection" => "center",
          "showLabel" => "Yes",
          "priority" => $element['visibility'],
          "type" => "Feature",
          "color"=> $element['color'],
          "labelClass" => preg_replace('/[^a-zA-Z0-9\']/', "-", strtolower($element['visibility'])),
          "geometry" => array(
            "type" => "Label",
            "coordinates"=> array(
              $markerCoords[0],
              $markerCoords[1]
            )
          )
      );

      return $markerData;
    }

    function getLocationData($location){
      // Get the page's struct data, validate it, and prepare it for formatting
      $elementData = $this->hlp_struct->getData($location['id']);
      if(is_null($elementData) || count($elementData) == 0){
        return null;
      }

      // Some locations might require additional fetching or setup, so let's do that now
      foreach ($elementData as &$structData){
        if (isset($structData["coords"]) && strlen($structData["coords"])>0){
          $structData['id'] = $location['id'];
          // Region
          if (strpos($structData["coords"],"[") !== false && strpos($structData["coords"],"]") !== false){
            $structData['type'] = "Polygon";
            return $structData;
          // Line
          }else if (strpos($structData["coords"],";") !== false){
            $structData['type'] = "Line";
            return $structData;
          // Marker
          }else if (isset($structData['marker_image']) && strlen($structData['marker_image']) > 0){
            $structData['type'] = "Point";
            $marker_path = $structData['marker_image'];            
            $structData['marker_url'] = "./lib/exe/fetch.php?media=$marker_path";
            return $structData;
          // Label
          }else if (isset($structData['display_name']) && strlen($structData['display_name']) > 0){
            $structData['type'] = "Label";
            return $structData;
          }
        }
      }
      unset($structData);
      return null;
    }

    function buildMapConfiguration($mapData){
      $mapDimensions = explode(',', preg_replace("/[^0-9,]/", "", $mapData['map_dimensions']));
      $mapZooms = explode(',', $mapData['map_zoom_minmax']);
      $mapObject = array();

      if(strlen($mapData['map_media']) > 0){
        $map_path = substr($mapData['map_media'], 1, strlen($mapData['map_media']));
        $mapUrl = "./lib/exe/fetch.php?media=$map_path";
      }

      if(!isset($mapData['show_own_markers_only']) || strlen($mapData['show_own_markers_only']) == 0){
        $mapData['show_own_markers_only'] = "No";
      }

      $mapDimensions = $this->getMediaDimensions($mapUrl);

      $mapConfiguration = array(
          "filename" => $mapUrl,
          "mapWidth" => intval($mapDimensions[0]),
          "mapHeight" => intval($mapDimensions[1]),
          "minZoom" => intval($mapZooms[0]),
          "maxZoom" => intval($mapZooms[1]),
          "showOwnMarkersOnly" => $mapData['show_own_markers_only']
      );

      return $mapConfiguration;
    }

    function getMediaDimensions($image){
      $dims = array();

      $image = substr($image, strpos($image, '=')+1, strlen($image));

      $info = getimagesize(mediaFN($image, ''));
      $dims[0] = (int) $info[0];
      $dims[1] = (int) $info[1];

      return $dims;
    }
}