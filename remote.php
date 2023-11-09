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

class remote_plugin_ptolemy extends DokuWiki_Remote_Plugin {

    public function __construct()
    {
        parent::__construct();
        $this->hlp_ptolemy = plugin_load('helper', 'ptolemy');
        if (!$this->hlp_ptolemy) {
            if (defined('DOKU_UNITTEST')) throw new \Exception('Couldn\'t load ptolemy.');
            return;
        }
    }

    public function _getMethods() {
        return array(
            'getMapData' => array(
                'args' => array(),
                'return' => 'json'
            )
        );
    }
 
    public function getMapData($namespace) {
        try {
            $mapData = $this->hlp_ptolemy->getMapData($namespace);
            return (json_encode($mapData));
        } catch (StructException $e) {
            throw new RemoteException($e->getMessage(), 0, $e);
        }

    }
    
}