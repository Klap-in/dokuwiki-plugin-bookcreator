<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

/**
 * Show book bar and pagetool button at a wiki page
 */
class action_plugin_bookcreator_handleselection extends DokuWiki_Action_Plugin {

    /** @var helper_plugin_bookcreator */
    protected $hlp;

    /**
     * Constructor
     */
    public function __construct() {
        $this->hlp = plugin_load('helper', 'bookcreator');
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_handle_ajax');
    }


    /**
     * Handle ajax requests for the book manager
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

        try {
            if(!checkSecurityToken()) {
                throw new Exception('Security Token did not match. Possible CSRF attack.');
            }

            $action = $INPUT->post->str('action', '', true);
            switch($action) {
                case 'retrievePageinfo':
                    $response = $this->retrievePageInfo($this->getPOSTedSelection());
                    break;
                case 'saveSelection':
                    $title = $INPUT->post->str('savedselectionname');
                    $response = $this->saveSelection($title, $this->getPOSTedSelection());
                    break;
                case 'loadSavedSelection':
                    $page = $INPUT->post->str('savedselectionname');
                    $response = $this->loadSavedSelection($page);
                    break;
                case 'deleteSavedSelection':
                    $page = $INPUT->post->str('savedselectionname');
                    $response = $this->deleteSavedSelection($page);
                    break;
                case 'searchPages':
                    $namespace = $INPUT->post->str('ns');
                    $recursive = $INPUT->post->str('r');
                    $response = $this->searchPages($namespace, $recursive);
                    break;
                default:
                    $response['error'] = 'unknown action ';
            }
        } catch(Exception $e){
            $response['error'] = $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Get POSTed selection
     *
     * @return array
     */
    protected function getPOSTedSelection() {
        global $INPUT;

        $selection = json_decode($INPUT->post->str('selection', '', true), true);
        if(!is_array($selection)) {
            $selection = array();
        }
        return $selection;
    }

    /**
     * Return the titles and urls to given pageids
     *
     * @param array $selection
     * @return array with slection
     */
    private function retrievePageInfo($selection) {
        $response['selection'] = array();
        foreach($selection as $pageid) {
            $page = cleanID($pageid);
            if(auth_quickaclcheck($pageid) < AUTH_READ) {
                continue;
            }
            $response['selection'][$page] = array(wl($page, false, true, "&"), $this->getTitle($page));
        }
        return $response;
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

    /**
     * Handle request for saving the selection list
     *
     * Selection is saved as bullet list on a wikipage
     *
     * @param string $savedSelectionName Title for saved selection
     * @param array $selection
     * @return array with message and item for the list of saved selections
     * @throws Exception
     */
    private function saveSelection($savedSelectionName, $selection) {
        if(auth_quickaclcheck($this->getConf('save_namespace').':*') < AUTH_CREATE) {
            throw new Exception('no access to namespace: ' . $this->getConf('save_namespace'));
        }
        if(empty($selection)) {
            throw new Exception($this->getLang('empty'));
        }
        if(empty($savedSelectionName)){
            throw new Exception($this->getLang('needtitle'));
        }

        //generate content
        $content = "====== ".$savedSelectionName." ======".DOKU_LF;

        foreach($selection as $pageid) {
            $content .= "  * [[:$pageid]]".DOKU_LF;
        }

        $save_pageid = cleanID($this->getConf('save_namespace') . ":" . $savedSelectionName);
        saveWikiText($save_pageid, $content, $this->getLang('selectionstored'));

        $response['success'] = sprintf($this->getLang('saved'), $save_pageid);

        $item = array(
            'id' => $save_pageid,
            'mtime' => filemtime(wikiFN($save_pageid))
        );
        $response['item'] = $this->hlp->createListitem($item, true);
        return $response;
    }

    /**
     * Handle request for deleting of selection list
     *
     * @param string $page with saved selection
     * @return array with message and deleted page name
     * @throws Exception
     */
    private function deleteSavedSelection($page) {
        if(auth_quickaclcheck($this->getConf('save_namespace').':*') < AUTH_CREATE) {
            throw new Exception('no access to namespace: ' . $this->getConf('save_namespace'));
        }

        $pageid = cleanID($this->getConf('save_namespace') . ":" . $page);

        if(!file_exists(wikiFN($pageid))){
            throw new Exception(sprintf($this->getLang('selectiondontexist'), $pageid));
        }

        saveWikiText($pageid, '', $this->getLang('selectiondeleted'));
        $response['success'] = sprintf($this->getLang('deleted'), $pageid);
        $response['deletedpage'] = noNS($pageid);
        return $response;
    }

    /**
     * Load the specified saved selection
     *
     * @param string $page with saved selection
     * @return array with title and a list of pages
     * @throws Exception
     */
    public function loadSavedSelection($page) {
        $pageid = cleanID($this->getConf('save_namespace') . ":" . $page);

        if($page === '') {
            throw new Exception(sprintf($this->getLang('selectiondontexist'), $pageid .':'));
        }

        if(auth_quickaclcheck($pageid) < AUTH_READ) {
            throw new Exception(sprintf($this->getLang('selectionforbidden'), $pageid));
        }

        if(!file_exists(wikiFN($pageid))) {
            throw new Exception(sprintf($this->getLang('selectiondontexist'), $pageid));
        }

        list($title, $list) = $this->getSavedSelection($pageid);
        $response['title'] = $title;
        $response['selection'] = $list;
        return $response;
    }

    /**
     * Returns title and list of pages from a Saved Selection
     *
     * @param string $pageid pagename containing the selection
     * @return array(title, array(list))
     */
    protected function getSavedSelection($pageid) {
        $title = '';
        $list  = array();

        $pagecontent = rawWiki($pageid);
        $lines       = explode("\n", $pagecontent);

        foreach($lines as $i => $line) {
            //skip nonsense
            if(trim($line) == '') continue;
            if((($i > 0) && substr($line, 0, 7) != "  * [[:")) continue;

            //read title and list
            if($i === 0) {
                $title = trim(str_replace("======", '', $line));
            } else {
                $line        = str_replace("  * [[:", '', $line);
                $line        = str_replace("]]", '', $line);
                list($id, /* $title */) = explode('|', $line, 2);
                $id = cleanID($id);
                if($id == '') {
                    continue;
                }
                $list[] = $id;
            }
        }

        return array($title, $list);
    }

    /**
     * Returns an array of pages in the given namespace.
     *
     * @param string $ns The namespace to search in
     * @param boolean $recursive Search in sub-namespaces too?
     * @return array with a list pages
     */
    protected function searchPages($ns, $recursive) {
        global $conf;

        // Use inc/search.php
        if ($recursive == 'true') {
            $opts = array();
        } else {
            $count = substr_count($ns, ':');
            $opts = array('depth' => 1+$count);
        }
        $items = array();
        $ns = trim($ns, ':');
        $ns = utf8_encodeFN(str_replace(':', '/', $ns));
        search($items, $conf['datadir'], 'search_allpages', $opts, $ns);

        // Generate result.
        $pages = array();
        foreach ($items as $item) {
            $pages [] = $item['id'];
        }

        $response['pages'] = $pages;
        return $response;
    }
}
