<?php
/**
 * DokuWiki Plugin Bookcreator (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Gerrit Uitslag <klapinklapin@gmail.com>
 */

/**
 * Class helper_plugin_bookcreator
 */
class helper_plugin_bookcreator extends DokuWiki_Plugin {

    /**
     * Create a item for list of available saved selections
     *
     * @param array $item with at least the entries:
     *   - string 'id'    pageid
     *   - int    'mtime' unixtime modification date
     * @param bool $isbookmanager   if in bookmanager, show delete button(if allowed) and date
     * @return string
     */
    public function createListitem($item, $isbookmanager = false) {
        $itemtitle = p_get_first_heading($item['id']);
        $nons      = noNS($item['id']);
        $url       = wl($this->getConf('save_namespace').":".$nons);

        $out = "<li class='level1 bkctrsavsel__$nons' data-page-id='$nons'>";
        if(($isbookmanager) && (auth_quickaclcheck($item['id']) >= AUTH_DELETE)) {
            $out .= "<a class='action delete' href='#deletesavedselection' title='{$this->getLang('delselection')}'>"
                 . inlineSVG(__DIR__ . '/images/notebook-remove-outline.svg')
                 . "</a>&nbsp;";
        }
        $out .= "<a class='action load' href='#loadsavedselection' title='{$this->getLang('loadselection')}'>"
             . inlineSVG(__DIR__ . '/images/notebook-edit-outline.svg')
             . "</a>&nbsp;"
             . "<a href='$url' title='{$this->getLang('showselection')}'>".inlineSVG(__DIR__ . '/images/notebook-outline.svg')." $itemtitle</a>";
        if($isbookmanager) {
            $out .= ' ('.dformat($item['mtime']).')';
        }
        $out .= '</li>';

        return $out;
    }
}
