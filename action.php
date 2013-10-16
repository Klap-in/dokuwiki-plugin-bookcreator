<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_bookcreator extends DokuWiki_Action_Plugin {

    var $cpt;
    var $num;

    /**
     * Constructor
     */
    function action_plugin_bookcreator() {
        $this->setupLocale();
    }

    /**
     * register the eventhandlers
     */
    function register(Doku_Event_Handler $contr) {
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_tpl_act', array());
        $contr->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'bookbar', array());
        $contr->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, '_extendJSINFO');
    }

    /**
     * Read data from cookie and handle the 'addtobook' action
     *
     * @param Doku_Event $event  event object by reference
     * @param array      $param  empty
     */
    function _handle_tpl_act(&$event, $param) {
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

                        foreach($list as $pageid => $cpt) {
                            $this->setCookie("bookcreator[".$pageid."]", "$cpt");
                            $_COOKIE['bookcreator'][$pageid] = $cpt;
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
            case 'show':
                if(!in_array($event->data, array('show', 'addtobook'))) return;
                global $ID;

                //default: do not select page
                $this->cpt = false;
                $i         = 0;

                //check data stored in the cookie
                if(isset($_COOKIE['bookcreator'])) {
                    $fav = $_COOKIE['bookcreator'];
                    //load page data from cookie
                    $cpt = $fav[$ID];

                    if($cpt == 0 || $cpt == "") {
                        $this->cpt = false;
                    } else {
                        $this->cpt = true;
                    }

                    //count selected pages
                    foreach($fav as $value) {
                        if($value < 1) continue;
                        $i = $i + 1;
                    }
                }
                $this->num = $i;

                //further handle only the 'addtobook' action
                if($event->data != 'addtobook') return;

                //toggle page selection
                if($this->cpt == false) {
                    //add this page
                    $this->cpt = true;
                    $this->num = $this->num + 1;
                    $msg       = $this->getLang('pageadded');
                } else {
                    //remove this page
                    $this->cpt = false;
                    $this->num = $this->num - 1;
                    $msg       = $this->getLang('pageremoved');
                }

                //show message when toolbar isn't displayed
                if($this->getConf('toolbar') == "never") msg($msg, 1);

                $this->setCookie("bookcreator[" . $ID . "]", ($this->cpt ? "1" : "0"));

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
        if(isset($_COOKIE['bookcreator'])) {
            //clear list
            foreach($_COOKIE['bookcreator'] as $pageid => $value) {
                $this->setCookie("bookcreator[".$pageid."]", "", time() - 60 * 60);
            }
            unset($_COOKIE['bookcreator']);

            //clear title
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
                $list[$line] = 1;
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
    function bookbar(&$event, $param) {
        global $ID;

        if($event->data != 'show') return; // nothing to do for us

        //assume that page does not exists
        $exists = false;
        $id     = $ID;
        resolve_pageid('', $id, $exists);

        // show or not the toolbar ?
        if(($this->getConf('toolbar') == "never") || (($this->getConf('toolbar') == "noempty") && ($this->num == 0)))
            return;

        // find skip pages
        $sp = join("|", explode(",", preg_quote($this->getConf('skip_ids'))));
        if(!$exists || ($this->getConf('skip_ids') !== '' && preg_match("/$sp/i", $ID)))
            return;

        /**
         * Display toolbar
         */
        echo "<div class='bookcreator__' style='vertical-align:bottom;'>";

        //add page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__add' style='"; // '>";
        if($this->cpt == false) {
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
        if($this->cpt == true) {
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
    function _extendJSINFO(&$event, $param) {
        global $JSINFO, $ID, $conf;
        $JSINFO['hasbookcreatoraccess'] = (int)(auth_quickaclcheck(cleanID($this->getConf('book_page'))) >= AUTH_READ);
        $JSINFO['wikipagelink'] = wl($ID);
        $JSINFO['DOKU_COOKIEPATH'] = empty($conf['cookiedir']) ? DOKU_REL : $conf['cookiedir'];
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
}

// vim:ts=4:sw=4:et:enc=utf-8:
