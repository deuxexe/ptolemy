<?php
/**
 * DokuWiki Ptolemy (Syntax)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brad Osborne <bosborne.csm@gmail.com>, Yuuki
 * 
 * Integrated with (and inspired by) the Struct plugin by Andreas Bohr.
 * 
 */
 
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_ptolemy extends DokuWiki_Syntax_Plugin {

    public function __construct()
    {
        $this->hlp_ptolemy = plugin_load('helper', 'ptolemy');
        if (!$this->hlp_ptolemy) {
            if (defined('DOKU_UNITTEST')) throw new \Exception('Couldn\'t load ptolemy.');
            return;
        }
    }
 
    public function getType(){ return 'protected'; }
    public function getSort(){ return 159; }
    public function connectTo($mode) { $this->Lexer->addEntryPattern('~~MAP\|(?=.*?~~)',$mode,'plugin_ptolemy'); }
    public function postConnect() { $this->Lexer->addExitPattern('~~','plugin_ptolemy'); }
 
    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        switch ($state) {
          case DOKU_LEXER_ENTER : return array($state, '');
          case DOKU_LEXER_UNMATCHED :  
            if(strpos($match, '|') !== false){
                list($namespace, $coordString, $zoom) = preg_split("/\|/u", $match);
            }else{
                $namespace = $match[0];
            }
            $mapData = $this->hlp_ptolemy->getMapData($namespace);

            if(!isset($coordString) || strpos($coordString, ',') === false){
                $mapConfigurationData = json_decode($mapData)->{"mapConfiguration"};
                $centerCoords[0] = intval($mapConfigurationData->{"mapWidth"})/2;
                $centerCoords[1] = intval($mapConfigurationData->{"mapHeight"})/2;
            }else{
                $centerCoords = explode(',', preg_replace("/[^0-9,]/", "", $coordString));
            }

            if (!isset($zoom) || !is_numeric($zoom)){
                $zoom = 0;
            }else{ 
                $zoom = intval($zoom);
            };

            return array($state,array($namespace, $centerCoords, $zoom, $mapData));

          case DOKU_LEXER_EXIT :       return array($state, '');
        }
        return array();
    }
 
    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode == 'xhtml'){
            /** @var Doku_Renderer_xhtml $renderer */
            list($state,$match) = $data;
            $matchJson = json_encode($match);
            switch ($state) {
                case DOKU_LEXER_ENTER :     
                    $renderer->doc .= "";  
                    break;
                case DOKU_LEXER_UNMATCHED :
                    if(count($match)>1){
                        list($namespace, $coordString, $initZoom, $mapData) = $match;

                        $renderer->doc .= "
                        <script type=\"text/javascript\">
                            if (typeof ptolemyData != 'undefined'
                            && ptolemyData != null
                            && ptolemyData.length != null
                            && ptolemyData.length > 0) {
                                ptolemyData.push($mapData);
                            }else{
                                ptolemyData = [];
                                ptolemyData.push($mapData);
                            }
                        </script>
                        <div class='ptolemy-wrapper'><div class='ptolemy-map' data-initial-x='$coordString[0]' data-initial-y='$coordString[1]' data-initial-zoom='$initZoom'></div></div>";
                    }else{
                        $renderer->doc .= "<b>Map syntax error.</b><br>
                        Format: ~~MAP|map page|coord-x,coord-y|zoom~~<br>
                        Found: ~~MAP|$match[0]~~<br>
                        If your syntax seems correct, ensure that your namespace has the appropriate struct data.";
                    }
                    break;
                case DOKU_LEXER_EXIT :       
                    $renderer->doc .= "<br>"; 
                    break;
            }
            return true;
        }
        return false;
    }
}