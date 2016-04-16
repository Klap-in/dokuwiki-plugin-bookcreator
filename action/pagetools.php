<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Show book bar and pagetool button at a wiki page
 */
class action_plugin_bookcreator_pagetools extends DokuWiki_Action_Plugin {

    /**
     * Constructor
     */
    function __construct() {
//        $this->setupLocale();    //TODO required?
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'bookbar', array());
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, '_extendJSINFO');
        $controller->register_hook('TPL_ACTION_GET', 'BEFORE', $this, 'allowaddbutton');
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'addbutton');
    }

    /**
     *  Prints html of bookbar (performed before the wikipage content is output)
     *
     * @param Doku_Event $event event object by reference
     * @param array $param empty
     */
    public function bookbar(Doku_Event $event, $param) {
        if($event->data != 'show') return; // nothing to do for us

        if(!$this->isVisible($isbookbar = true)) return;

        /**
         * Display toolbar
         */
        echo "<div class='bookcreator__bookbar' style='vertical-align:bottom;'>";

        //add page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__add'>";
        echo '  <b>' . $this->getLang('toolbar') . '</b><br>';
        echo '  <a class="bookcreator__tglPgSelection" href="#">';
        echo "    <img src='" . DOKU_URL . "lib/plugins/bookcreator/images/add.png'>&nbsp;" . $this->getLang('addpage');
        echo "  </a>";
        echo "</div>";

        //remove page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__remove'>";
        echo '  <b>' . $this->getLang('toolbar') . '</b><br>';
        echo '  <a class="bookcreator__tglPgSelection" href="#">';
        echo "    <img src='" . DOKU_URL . "lib/plugins/bookcreator/images/del.png'>&nbsp;" . $this->getLang('removepage');
        echo "  </a>&nbsp;";
        echo "</div>";

        //pointer to Book Manager
        echo "<div class='bookcreator__panel' ><br>";
        echo "  <a href='" . wl($this->getConf('book_page')) . "'>";
        echo "    <img src='" . DOKU_URL . "lib/plugins/bookcreator/images/smallbook.png'>&nbsp;" . $this->getLang('showbook');
        echo "    (<span id='bookcreator__pages'>0</span> " . $this->getLang('pages') . ")";
        echo "  </a>";
        echo "</div>";

        // pointer to help
        echo "<div class='bookcreator__panel' style='float:right;'>";
        echo "  <a href='" . wl($this->getConf('help_page')) . "'>";
        echo "    <img src='" . DOKU_URL . "lib/plugins/bookcreator/images/help.png'>&nbsp;" . $this->getLang('help');
        echo "  </a>";
        echo "</div>";

        echo "</div>";

    }

    /**
     * Add additional info to $JSINFO
     *
     * @param Doku_Event $event
     * @param mixed $param not defined
     */
    public function _extendJSINFO(Doku_Event $event, $param) {
        global $JSINFO;

        $JSINFO['bookcreator']['areToolsVisible'] = $this->isVisible();
        $JSINFO['bookcreator']['showBookbar'] = $this->getConf('toolbar');
    }

    /**
     * Accepts the 'addtobook' action, while using the default action link properties.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function allowaddbutton(Doku_Event $event, $param) {
        if($event->data['type'] != 'plugin_bookcreator_addtobook') {
            return;
        }

        $event->preventDefault();
    }

    /**
     * Add 'add page'-button to pagetools
     *
     * @param Doku_Event $event
     * @param mixed $param not defined
     */
    public function addbutton(Doku_Event $event, $param) {
        global $lang;

        if($this->hasAccessToBookmanager() && $event->data['view'] == 'main') {
            //store string in global lang array
            $jslocal = $this->getLang('js');
            $lang['btn_plugin_bookcreator_addtobook'] = $jslocal['btn_addtobook'] ;

            $event->data['items'] =
                array_slice($event->data['items'], 0, -1, true) +
                array('plugin_bookcreator_addtobook' => tpl_action('plugin_bookcreator_addtobook', true, 'li', true, '<span>', '</span>')) +
                array_slice($event->data['items'], -1, 1, true);
        }
    }

    /**
     * Check if user should see the tools at a page
     *
     * @param bool $isbookbar do additional check for booktool
     * @return bool
     */
    private function isVisible($isbookbar = false) {
        global $ID;

        // show the bookbar?
        if($isbookbar && ($this->getConf('toolbar') == "never")) {
            return false;
        }

        if(cleanID($this->getConf('book_page')) == $ID) {
            return false;
        }

        // has read permissions to bookmanager page?
        if(!$this->hasAccessToBookmanager()) {
            return false;
        }

        // not skip page?
        $exists = false; //assume that page does not exists
        $id = $ID;
        resolve_pageid('', $id, $exists);

        $skipPagesRegexp = join("|", explode(",", preg_quote($this->getConf('skip_ids'))));
        if(!$exists || ($this->getConf('skip_ids') !== '' && preg_match("/$skipPagesRegexp/i", $ID))) {
            return false;
        }

        return true;
    }

    /**
     * Check if current user could access the bookmanager page
     *
     * @return bool has read access
     */
    private function hasAccessToBookmanager() {
        return auth_quickaclcheck(cleanID($this->getConf('book_page'))) >= AUTH_READ;
    }
}
