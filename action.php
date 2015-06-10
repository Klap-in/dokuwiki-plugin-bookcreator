<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class action_plugin_bookcreator
 */
class action_plugin_bookcreator extends DokuWiki_Action_Plugin {

    var $selected;
    var $num;

    /**
     * Constructor
     */
    function __construct() {
        $this->setupLocale();
    }

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_tpl_act', array());
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'bookbar', array());
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, '_extendJSINFO');
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'addbutton');
    }

    /**
     * Read data from cookie and handle the 'addtobook' action
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty
     */
    function _handle_tpl_act(Doku_Event $event, $param) {
        //unify the action
        $do = $event->data;
        if(is_array($do)) {
            list($do) = array_keys($do);
        }

        //set selection in cookie before html is outputted
        switch($do) {
            case  'readsavedselection':
                if(checkSecurityToken()) {

                    $id = cleanID($this->getConf('save_namespace').":".$_POST['page']);
                    $hasaccess = (auth_quickaclcheck($id) >= AUTH_READ);
                    if($hasaccess) {
                        //clear selection
                        $this->clearSelectionCookie();

                        //load new selection
                        list($title, $list) = $this->loadSavedSelection($id);

                        $this->setCookie("bookcreator_title", "$title");
                        $_COOKIE['bookcreator_title'] = $title;

                        foreach($list as $pageid => $selected) {
                            $this->setCookie("bookcreator[".$pageid."]", "$selected");
                            $_COOKIE['bookcreator'][$pageid] = $selected;
                        }
                    }
                }

                $event->data = 'show';
                return;

            case 'clearactiveselection':
                if(checkSecurityToken()) {
                    $this->clearSelectionCookie();
                }

                $event->data = 'show';
                return;

            case 'addtobook':
            case 'show': //num field is required later in the bookbar() event handler
                if(!in_array($event->data, array('show', 'addtobook'))) return;
                global $ID;

                //default: do not select page
                $this->selected = false;
                $i         = 0;

                //check data stored in the cookie
                if(!empty($_COOKIE['bookcreator'])) {
                    $selection = $_COOKIE['bookcreator'];
                    //load page data from cookie
                    $selected = $selection[$ID];

                    if($selected == 0 || $selected == "") {
                        $this->selected = false;
                    } else {
                        $this->selected = true;
                    }

                    //count selected pages
                    foreach($selection as $value) {
                        if($value < 1) continue;
                        $i = $i + 1;
                    }
                }
                $this->num = $i;

                // further handle only the 'addtobook' action
                if($event->data != 'addtobook') return;

                // has access to bookmanager?
                if(auth_quickaclcheck(cleanID($this->getConf('book_page'))) < AUTH_READ) {
                    msg($this->getLang('nobookmanageraccess'), -1);
                    $event->data = 'show';
                    return;
                }

                //toggle page selection
                if($this->selected == false) {
                    //add this page
                    $this->selected = true;
                    $this->num = $this->num + 1;
                    $msg       = $this->getLang('pageadded');
                } else {
                    //remove this page
                    $this->selected = false;
                    $this->num = $this->num - 1;
                    $msg       = $this->getLang('pageremoved');
                }

                //show message when toolbar isn't displayed
                if($this->getConf('toolbar') == "never") {
                    msg($msg, 1);
                }

                $this->setCookie("bookcreator[" . $ID . "]", ($this->selected ? "1" : "0"));

                //Change action to: show the wikipage
                $event->data = 'show';
                //trigger reload (due to changed ACT & this fake POST) to remove url parameter
                $_SERVER['REQUEST_METHOD'] = 'POST';
        }
    }

    /**
     * Clear current selection
     */
    private function clearSelectionCookie() {
        //clear list
        if(isset($_COOKIE['bookcreator'])) {
            if(is_array($_COOKIE['bookcreator'])) {
                foreach($_COOKIE['bookcreator'] as $pageid => $selected) {
                    $this->setCookie("bookcreator[" . $pageid . "]", "", time() - 60 * 60);
                }
            }
            unset($_COOKIE['bookcreator']);
        }
        //clear title
        if(isset($_COOKIE['bookcreator_title'])) {
            $this->setCookie("bookcreator_title", "", time() - 60 * 60);
            unset($_COOKIE['bookcreator_title']);
        }
    }

    /**
     * Load a Saved Selection from a page
     *
     * @param string $pageid pagename containing the selection
     * @return array(title, array(list))
     */
    public function loadSavedSelection($pageid) {
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
                $line  = str_replace("====== ", '', $line);
                $title = str_replace(" ======", '', $line);
            } else {
                $line        = str_replace("  * [[:", '', $line);
                $line        = str_replace("]]", '', $line);
                list($link, /* $title */) = explode('|', $line, 2);
                $link = trim($link);
                if($link == '') {
                    continue;
                }
                $list[$link] = 1;
            }
        }

        return array($title, $list);
    }

    /**
     *  Prints bookbar (performed before the wikipage content is output)
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty
     *
     * @author     Luigi Micco <l.micco@tiscali.it>
     */
    public function bookbar(Doku_Event $event, $param) {
        global $ID;

        if($event->data != 'show') return; // nothing to do for us

        // show or not the toolbar ?
        if((($this->getConf('toolbar') == "never") || ($this->getConf('toolbar') == "noempty")) && ($this->num == 0)) {
            return;
        }
        if($this->getConf('toolbar') == "never") {
            $state = $this->selected ? 'true' : 'false';
            echo "<div id='bookcreator__memory' style='display: none;' data-isselected=$state></div>";
            return;
        }

        //has read no permissions to bookmanager page?
        if(auth_quickaclcheck(cleanID($this->getConf('book_page'))) < AUTH_READ) {
            return;
        }

        // find skip pages
        $exists = false; //assume that page does not exists
        $id     = $ID;
        resolve_pageid('', $id, $exists);

        $sp = join("|", explode(",", preg_quote($this->getConf('skip_ids'))));
        if(!$exists || ($this->getConf('skip_ids') !== '' && preg_match("/$sp/i", $ID))) {
            return;
        }

        /**
         * Display toolbar
         */
        echo "<div class='bookcreator__' style='vertical-align:bottom;'>";

        //add page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__add' style='"; // '>";
        if($this->selected == false) {
            echo "display:block;'>";
        } else {
            echo "display:none;'>";
        }
        echo '<b>'.$this->getLang('toolbar').'</b><br>';
        echo '<a class="bookcreator__tglPgSelection" href="#">';
        echo "  <img src='".DOKU_URL."lib/plugins/bookcreator/images/add.png'>&nbsp;".$this->getLang('addpage');
        echo "</a>";
        echo "</div>";

        //remove page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__remove' style='";
        if($this->selected == true) {
            echo "display:block;'>";
        } else {
            echo "display:none;'>";
        }
        echo '<b>'.$this->getLang('toolbar').'</b><br>';
        echo '<a class="bookcreator__tglPgSelection" href="#">';
        echo "  <img src='".DOKU_URL."lib/plugins/bookcreator/images/del.png'>&nbsp;".$this->getLang('removepage');
        echo "</a>&nbsp;";
        echo "</div>";

        //pointer to Book Manager
        echo "<div class='bookcreator__panel' ><br>";
        echo "  <a href='".wl($this->getConf('book_page'))."'>";
        echo "    <img src='".DOKU_URL."lib/plugins/bookcreator/images/smallbook.png'>&nbsp;".$this->getLang('showbook')." (";
        echo "    <span id='bookcreator__pages'>";
        echo        $this->num;
        echo "    </span> ".$this->getLang('pages').")";
        echo "  </a>";
        echo "</div>";

        // pointer to help
        echo "<div class='bookcreator__panel' style='float:right;'>";
        echo "  <a href='".wl($this->getConf('help_page'))."'>";
        echo "    <img src='".DOKU_URL."lib/plugins/bookcreator/images/help.png'>&nbsp;".$this->getLang('help');
        echo "  </a>";
        echo "</div>";

        echo "</div>";

    }

    /**
     * Add additional info to $JSINFO
     *
     * @author Gerrit Uitslag <klapinklapin@gmail.com>
     *
     * @param Doku_Event $event
     * @param mixed      $param not defined
     */
    public function _extendJSINFO(Doku_Event $event, $param) {
        global $JSINFO, $conf, $ID;

        $showpagetools = true;
        //has no read permissions to bookmanager page?
        if(auth_quickaclcheck(cleanID($this->getConf('book_page'))) < AUTH_READ) {
            $showpagetools = false;
        }

        // find skip pages
        $exists = false; //assume that page does not exists
        $id     = $ID;
        resolve_pageid('', $id, $exists);

        $skipPagesRegexp = join("|", explode(",", preg_quote($this->getConf('skip_ids'))));
        if(!$exists || ($this->getConf('skip_ids') !== '' && preg_match("/$skipPagesRegexp/i", $ID))) {
            $showpagetools = false;
        }

        $JSINFO['showbookcreatorpagetool'] = $showpagetools;

        $JSINFO['DOKU_COOKIE_PARAM'] = array(
            'path' => empty($conf['cookiedir']) ? DOKU_REL : $conf['cookiedir'],
            'secure' => $conf['securecookie'] && is_ssl(),
        );
    }

    /**
     * Set a cookie
     *
     * @param string     $name  cookie name
     * @param string     $value cookie value
     * @param int|string $expire
     *     - The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch.
     *     - or 'week' is converted to expire time of a week
     */
    private function setCookie($name, $value, $expire = 'week') {
        global $conf;
        $cookieDir = empty($conf['cookiedir']) ? DOKU_REL : $conf['cookiedir'];

        if($expire == 'week') {
            $expire = time() + 60 * 60 * 24 * 7;
        }

        setCookie($name, $value, $expire, $cookieDir, '', ($conf['securecookie'] && is_ssl()));
    }

    /**
     * Add 'add page'-button to pagetools
     *
     * @param Doku_Event $event
     * @param mixed      $param not defined
     */
    public function addbutton(Doku_Event $event, $param) {
        global $ID;

        if(auth_quickaclcheck(cleanID($this->getConf('book_page'))) >= AUTH_READ && $event->data['view'] == 'main') {
            $jslocal = $this->getLang('js');

            $event->data['items'] = array_slice($event->data['items'], 0, -1, true) +
                                    array('addtobook' =>
                                        '<li>'
                                        .'    <a href='.wl($ID, array('do' => 'addtobook')).'  class="action addtobook" rel="nofollow" title="'.$jslocal['btn_addtobook'].'">'
                                        .'        <span>'.$jslocal['btn_addtobook'].'</span>'
                                        .'    </a>'
                                        .'</li>'
                                    ) +
                                    array_slice($event->data['items'], -1, 1, true);
        }
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
