<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

use dokuwiki\plugin\bookcreator\MenuItem;

/**
 * Show book bar and pagetool button at a wiki page
 */
class action_plugin_bookcreator_pagetools extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'bookbar');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, '_extendJSINFO');
        $controller->register_hook('TPL_ACTION_GET', 'BEFORE', $this, 'allowaddbutton');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'addsvgbutton');
    }

    /**
     *  Prints html of bookbar (performed before the wikipage content is output)
     *
     * @param Doku_Event $event event object by reference
     */
    public function bookbar(Doku_Event $event) {
        if($event->data != 'show') return; // nothing to do for us

        if(!$this->isVisible(true)) return;

        /**
         * Display toolbar
         */
        $html = "<div class='bookcreator__bookbar' style='vertical-align:bottom;'>";

        //add page to selection
        $html .= '<div class="bookcreator__panel" id="bookcreator__add">
                      <b>' . $this->getLang('toolbar') . '</b><br>
                      <a class="bookcreator__tglPgSelection bc__bookmarkplus" href="#">
                       '. inlineSVG(__DIR__ . '/../images/bookmark-plus.svg') . '
                        &nbsp;' . $this->getLang('addpage') . '
                    </a>
                  </div>';

        //remove page to selection
        $html .= '<div class="bookcreator__panel" id="bookcreator__remove">
                      <b>' . $this->getLang('toolbar') . '</b><br>
                      <a class="bookcreator__tglPgSelection bc__bookmarkmin" href="#">
                      ' . inlineSVG(__DIR__ . '/../images/bookmark-minus.svg') . '
                      &nbsp;' . $this->getLang('removepage') . '
                      </a>&nbsp;
                  </div>';

        //pointer to Book Manager
        $html .= '<div class="bookcreator__panel" >
                      <br>
                      <a href="' . wl($this->getConf('book_page')) . '" class="bc__manager">
                      ' . inlineSVG(__DIR__ . '/../images/notebook-edit-outline.svg') . '
                      &nbsp;' . $this->getLang('showbook') . '(<span id="bookcreator__pages">0</span> ' . $this->getLang('pages') . ')
                      </a>
                  </div>';

        // pointer to help
        $html .= '<div class="bookcreator__panel" style="float:right;">
                      <a href="' . wl($this->getConf('help_page')) . '" class="bc__bookmarkhelp">
                      ' . inlineSVG(__DIR__ . '/../images/help-circle.svg') . '
                      &nbsp;' . $this->getLang('help') . '
                      </a>
                  </div>';

        $html .= '</div>';
        echo $html;

    }

    /**
     * Add additional info to $JSINFO
     *
     * @param Doku_Event $event
     */
    public function _extendJSINFO(Doku_Event $event) {
        global $JSINFO;

        $JSINFO['bookcreator']['areToolsVisible'] = $this->isVisible();
        $JSINFO['bookcreator']['showBookbar'] = $this->getConf('toolbar');
    }

    /**
     * Accepts the 'addtobook' action, while using the default action link properties.
     *
     * @param Doku_Event $event
     */
    public function allowaddbutton(Doku_Event $event) {
        if($event->data['type'] != 'plugin_bookcreator_addtobook') {
            return;
        }

        $event->preventDefault();
    }

    /**
     * Add 'export pdf' button to page tools, new SVG based mechanism
     *
     * @param Doku_Event $event
     */
    public function addsvgbutton(Doku_Event $event) {
        if($event->data['view'] != 'page') return;
        array_splice($event->data['items'], -1, 0, [new MenuItem()]);
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
        $exists = page_exists($ID);

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
