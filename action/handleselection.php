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

    /** @var helper_plugin_bookcreator */
    protected $hlp;
    protected $response;

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

        $this->response['error'] = '';

        if(!checkSecurityToken()) {
            $this->response['error'] .= 'Security Token did not match. Possible CSRF attack.';
        } else {

            $action = $INPUT->post->str('action', '', true);
            switch($action) {
                case 'retrievePageinfo':
                    $this->retrievePageInfo($this->getPOSTedSelection());
                    break;
                case 'saveSelection':
                    $title =  $INPUT->post->str('savedselectionname');
                    $this->saveSelection($title, $this->getPOSTedSelection());
                    break;
                case 'loadSavedSelection':
                    $page =  $INPUT->post->str('savedselectionname');
                    $this->loadSavedSelection($page);
                    break;
                case 'deleteSavedSelection':
                    $page =  $INPUT->post->str('savedselectionname');
                    $this->deleteSavedSelection($page);
                    break;
                default:
                    $this->response['error'] .= 'unknown action';
            }
        }

        $json = new JSON();
        header('Content-Type: application/json');
        echo $json->encode($this->response);
    }

    /**
     * Get POSTed selection
     *
     * @return array|mixed
     */
    protected function getPOSTedSelection() {
        global $INPUT;
        $json = new JSON(JSON_LOOSE_TYPE);

        $selection = $json->decode($INPUT->post->str('selection', '', true));
        if(!is_array($selection)) {
            $selection = array();
        }
        return $selection;
    }

    /**
     * Return the titles and urls to given pageids
     *
     * @param array[] $selection
     */
    private function retrievePageInfo($selection) {
        foreach($selection as $pageid) {
            $page = cleanID($pageid);
            if(auth_quickaclcheck($pageid) < AUTH_READ) {
                continue;
            }
            $this->response['selection'][$page] = array(wl($page, false, true, "&"), $this->getTitle($page));
        }
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
     * @param array[] $selection
     */
    private function saveSelection($savedSelectionName, $selection) {
        if(auth_quickaclcheck($this->getConf('save_namespace').':*') < AUTH_CREATE) {
            $this->response['error'] .= 'no access to namespace: ' . $this->getConf('save_namespace');
        }

        if(empty($selection)) {
            $this->response['error'] .= $this->getLang('empty');
        }

        if(empty($savedSelectionName)){
            $this->response['error'] .= $this->getLang('needtitle');
        }


        if(empty($this->response['error'])) {
            //generate content
            $content = "====== ".$savedSelectionName." ======".DOKU_LF;

            foreach($selection as $pageid) {
                $content .= "  * [[:$pageid]]".DOKU_LF;
            }

            $save_pageid = cleanID($this->getConf('save_namespace') . ":" . $savedSelectionName);
            saveWikiText($save_pageid, $content, $this->getLang('selectionstored'));

            $this->response['succes'] = sprintf($this->getLang('saved'), $save_pageid);

            $item = array(
                'id' => $save_pageid,
                'mtime' => filemtime(wikiFN($save_pageid))
            );
            $this->response['item'] = $this->hlp->createListitem($item, true);
        }
    }

    /**
     * Handle request for deleting of selection list
     *
     * @param string $page with saved selection
     */
    private function deleteSavedSelection($page) {
        if(auth_quickaclcheck($this->getConf('save_namespace').':*') < AUTH_CREATE) {
            $this->response['error'] .= 'no access to namespace: ' . $this->getConf('save_namespace');
        }

        $pageid = cleanID($this->getConf('save_namespace') . ":" . $page);

        if(!file_exists(wikiFN($pageid))){
            $this->response['error'] .= sprintf($this->getLang('selectiondontexist'), $pageid);
        }

        if(empty($this->response['error'])) {
            saveWikiText($pageid, '', $this->getLang('selectiondeleted'));
            $this->response['success'] = sprintf($this->getLang('deleted'), $pageid);
            $this->response['deletedpage'] = noNS($pageid);
        }
    }

    /**
     * Load the specified saved selection
     *
     * @param string $page with saved selection
     */
    protected function loadSavedSelection($page) {
        $pageid = cleanID($this->getConf('save_namespace') . ":" . $page);

        if(auth_quickaclcheck($pageid) < AUTH_READ) {
            $this->response['error'] .= sprintf($this->getLang('selectionforbidden'), $pageid);
        }

        if(!file_exists(wikiFN($pageid))) {
            $this->response['error'] .= sprintf($this->getLang('selectiondontexist'), $pageid);
        }

        if(empty($this->response['error'])) {
            list($title, $list) = $this->getSavedSelection($pageid);
            $this->response = array(
                'title' => $title,
                'selection' => $list
            );
        }
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
}
