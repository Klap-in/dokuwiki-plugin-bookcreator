<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Show book bar and pagetool button at a wiki page
 */
class action_plugin_bookcreator_handleselection extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_handle_ajax');

    }


    /**
     * Handle ajax requests for:
     *  - store selection (pages+title), echo html of item with wiki link in stored-selections list
     *  - retrieve selection, echo json of list or html of items
     *
     * @param Doku_Event $event
     */
    public function _handle_ajax(Doku_Event $event) {
        if ($event->data !== 'plugin_bookcreator_call') {
            return;
        }

        $event->stopPropagation();
        $event->preventDefault();

        global $INPUT;
        $json = new JSON(JSON_LOOSE_TYPE);

        $action = $INPUT->post->str('action', '', true);
        $selections = $json->decode($INPUT->post->str('selection', '', true));

        switch($action) {
            case 'retrievePageinfo':
                $this->retrievePageInfo($selections);
                break;
//            case 'saveSelection':
//                $title =  $INPUT->post->str('bookcreator_selection_title');
//                $this->storeSelection($title, $selection);
//                break;
            default:
                //failed
        }


        // return html of


//        $json = new JSON();
//        header('Content-Type: application/json');
//        echo $json->encode($result);
    }

//    /**
//     * @param string $title
//     * @param array $selection
//     */
//    private function storeSelection($title, $selection) {
//
//
//        echo "";
//    }

    /**
     * Return the titles and urls to given pageids
     *
     * @param array[] $selection
     */
    private function retrievePageInfo($selection) {
        $result = array();
        if(!is_array($selection)) {
            $selection = array();
        }

        foreach($selection as $pageid) {
            $page = cleanID($pageid);

            $result[$page] = array(wl($page, false, true, "&"), $this->getTitle($page));
        }

        $json = new JSON();
        header('Content-Type: application/json');
        echo $json->encode($result);
    }

    /**
     * Construct a link title
     *
     * @see Doku_Renderer_xhtml::_getLinkTitle
     *
     * @param string $pageid
     * @return string
     */
    protected function getTitle($pageid) {
        global $conf;

        if(useHeading('navigation') && $pageid) {
            $heading = p_get_first_heading($pageid);
            if($heading) {
                return $this->_xmlEntities($heading);
            }
        }

        // Removes any Namespace from the given name but keeps casing and special chars
        if($conf['useslash']) {
            $pageid = strtr($pageid, ';/', ';:');
        } else {
            $pageid = strtr($pageid, ';', ':');
        }

        $name = noNSorNS($pageid);
        return $this->_xmlEntities($name);
    }

    /**
     * Escape string for output
     *
     * @see Doku_Renderer_xhtml::_xmlEntities
     *
     * @param string $string
     * @return string
     */
    protected function _xmlEntities($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }


//    /**
//     * echo json link+title or html
//     * @param string $title_or_page
//     */
//    private function updateListsFromStorage($title_or_page) {
//
//
//
////        $json = new JSON();
////        header('Content-Type: application/json');
////        echo $json->encode($result);
//    }
}
