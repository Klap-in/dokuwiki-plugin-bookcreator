<?php
/**
 * DokuWiki Plugin Bookcreator (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class helper_plugin_bookcreator
 */
class helper_plugin_bookcreator extends DokuWiki_Plugin {

    /**
     * Create a item for list of available saved selections
     *
     * @param $item array with at least the entries:
     *   - string 'id'    pageid
     *   - int    'mtime' unixtime modification date
     * @param bool $isbookmanager   if in bookmanager, show delete button(if allowed) and date
     * @return string
     */
    public function createListitem($item, $isbookmanager = false) {
        $itemtitle = p_get_first_heading($item['id']);
        $nons      = noNS($item['id']);
        $url       = wl($this->getConf('save_namespace').":".$nons);

        $out = "<li class='level1' id='sel__$nons'>";
        if(($isbookmanager) && (auth_quickaclcheck($item['id']) >= AUTH_DELETE)) {
            $out .= "<a class='action delete' href='#delete'>";
            $out .= "<img src='".DOKU_URL."lib/plugins/bookcreator/images/remove.png' title='{$this->getLang('delselection')}'/>";
            $out .= "</a>&nbsp;&nbsp";
        }
        $out .= "<a class='action load' href='#load'>";
        $out .= "<img src='".DOKU_URL."lib/plugins/bookcreator/images/include.png' title='{$this->getLang('loadselection')}'/>";
        $out .= "</a>&nbsp;&nbsp;";
        $out .= "<a href='$url' title='{$this->getLang('showselection')}'>$itemtitle</a>";
        if($isbookmanager) {
            $out .= ' ('.dformat($item['mtime']).')';
        }
        $out .= '</li>'.DOKU_LF;

        return $out;
    }
}
