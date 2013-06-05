<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_bookcreator extends DokuWiki_Action_Plugin {

    var $temp;
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
    function register(&$contr) {
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_tpl_act', array());
        $contr->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'bookbar', array());
    }

    function _handle_tpl_act(&$event, $param) {

        global $ID;

        $i = 0;
        if(isset($_COOKIE['bookcreator'])) {
            $fav = $_COOKIE['bookcreator'];
            //load page data from cookie
            list($cpt, $date) = explode(";", $fav[$ID]);

            if($cpt == 0 || $cpt == "") {
                $cpt = 0;
            } else {
                $cpt = 1;
            }
            //count selected pages
            foreach($fav as $value) {
                if($value < 1) continue;
                $i = $i + 1;
            }
        } else {
            //no cookie info
            $cpt = 0;
        }
        $this->temp = $cpt;
        $this->num  = $i;

        if($event->data != 'addtobook') return;

        if(isset($_COOKIE['bookcreator'])) {
            //cookie available
            $fav = $_COOKIE['bookcreator'];

            list($cpt, $date) = explode(";", $fav[$ID]);

            if($cpt == 0 || $cpt == "") {
                //add this page
                $cpt       = 1;
                $this->num = $this->num + 1;
                $msg       = $this->getLang('pageadded');
            } else {
                //remove this page
                $cpt       = 0;
                $this->num = $this->num - 1;
                $msg       = $this->getLang('pageremoved');
            }
        } else {
            //no cookie available, this is first page added to selection
            $cpt       = 1;
            $this->num = $this->num + 1;
            $msg       = $this->getLang('pageadded');
        }
        if($this->getConf('toolbar') == "never") msg($msg);

        $this->temp = $cpt;
        setCookie("bookcreator[".$ID."]", "$cpt;".time(), time() + 60 * 60 * 24 * 7, '/');

        //Show the wikipage
        $event->data = 'show';
    }

    /**
     *  Prints bookbar
     *
     * @author     Luigi Micco <l.micco@tiscali.it>
     */
    function bookbar(&$event, $param) {
        global $ID;
        global $conf;

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
        if(!$exists || preg_match("/$sp/i", $ID))
            return;

        $cpt = $this->temp;

        /**
         * Display toolbar
         */
        echo "<div class='bookcreator__' style='vertical-align:bottom;'>";

        //add page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__add' style='"; // '>";
        if($cpt == 0 || $cpt == "") {
            echo "display:block;'>";
        } else {
            echo "display:none;'>";
        }
        echo '<b>'.$this->getLang('toolbar').'</b><br>';
        echo '<a href="javascript:book_updateSelection(\''.$ID.'\', 1); ">';
        echo "  <img src='".DOKU_URL."lib/plugins/bookcreator/images/add.png'>&nbsp;".$this->getLang('addpage');
        echo "</a>";
        echo "</div>";

        //remove page to selection
        echo "<div class='bookcreator__panel' id='bookcreator__remove' style='";
        if($cpt == 1) {
            echo "display:block;'>";
        } else {
            echo "display:none;'>";
        }
        echo '<b>'.$this->getLang('toolbar').'</b><br>';
        echo '<a href="javascript:book_updateSelection(\''.$ID.'\', 0); ">';
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
}

// vim:ts=4:sw=4:et:enc=utf-8:
