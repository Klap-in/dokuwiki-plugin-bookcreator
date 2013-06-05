<?php
/**
 * BookCreator plugin : Create a book from some pages.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(DOKU_INC.'inc/search.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_bookcreator extends DokuWiki_Syntax_Plugin {

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~\w*?BOOK.*?~~', $mode, 'plugin_bookcreator');
    }

    function getType() {
        return 'container';
    }

    function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 190;
    }

    function handle($match, $state, $pos, &$handler) {

        $match = substr($match, 2, -2); // strip markup
        if(substr($match, 0, 7) == 'ARCHIVE') $type = 'archive';
        else $type = 'book';

        $num   = 10;
        $order = 'date';
        if($type == 'archive') {
            list($junk, $params) = explode(':', $match, 2);
            list($param1, $param2) = explode('&', $params, 2);

            if(is_numeric($param1)) {
                $num = $param1;
                if(is_string($param2)) $order = $param2;
            } elseif(is_string($param1)) {
                $order = $param1;
                if(is_numeric($param2)) $num = $param2;
            }

        }

        return array($type, $num, $order);

    }

    /**
     * @param string        $mode render mode e.g. text, xhtml, meta,...
     * @param Doku_Renderer &$renderer
     * @param array         $data return of handle()
     * @return bool
     */
    function render($mode, &$renderer, $data) {
        global $ID;

        list($type, $num, $order) = $data;

        if($type == "book") {
            $renderer->info['cache'] = false;
            if(($mode == 'text') && (isset($_GET['do']) && ($_GET['do'] == 'export_text'))) {
                $mode = 'xhtml';
            }

            if($mode == 'xhtml') {
                /** @var $renderer Doku_Renderer_xhtml */

                // verification that if the user can save / delete the selections
                $usercansave = (auth_quickaclcheck($this->getConf('save_namespace').':*') >= AUTH_CREATE);

                if($usercansave) {
                    //save or delete selection
                    if(isset($_POST['task']) && ($_POST['task'] == "save") && checkSecurityToken()) {
                        $this->saveSelection();
                    } elseif(isset($_POST['task']) && ($_POST['task'] == "del") && checkSecurityToken()) {
                        $this->deleteSelection();
                    }
                }

                if(isset($_GET['do']) || isset($_GET['mddo'])) { //TODO what does 'mddo' mean?
                    //export as xhtml or text
                    if(($_GET['do'] == 'export_html') || ($_GET['do'] == 'export_text')) {
                        $this->exportOnScreen($renderer);
                    }

                } else {
                    $renderer->info['cache'] = false;
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/core.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/events.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/css.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/coordinates.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/drag.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/dragsort.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/cookies.js"></script>';
                    $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/more.js"></script>';

                    //if a selection in cookie or read from file, the Book Manager is displayed
                    $foundlist = false;
                    if(isset($_COOKIE['bookcreator']) || (isset($_POST['task']) && $_POST['task'] == "read")) {
                        $foundlist = $this->showBookManager($renderer, $usercansave);
                    }
                    //no selection available
                    if(!$foundlist) {
                        $renderer->doc .= $this->getLang('nocookies');
                    }

                    // Displays the list of saved selections
                    $this->renderSelectionslist($renderer, $bookmanager = true, $ID, $order);
                }
            }
            return false;

        } else {
            // type == archive

            if($mode == 'xhtml') {
                // generates the list of saved selections
                $this->renderSelectionslist($renderer, $bookmanager = false, $this->getConf('book_page'), $order, $num);
            }
            return false;

        }
    }

    /**
     * Generates the list of save selections
     *
     * @param Doku_Renderer_xhtml $renderer
     * @param bool                $bookmanager whether this list is displayed in the Book Manager
     * @param string              $page pageid of the page with the Book Manager
     * @param string              $order sort by 'title' or 'date'
     * @param int                 $num number of listed items, 0=all items
     *
     * if in the Book Manager, the delete buttons are displayed
     */
    public function renderSelectionslist(&$renderer, $bookmanager, $page, $order, $num = 0) {
        $result = $this->_getlist($order, $num);
        if(sizeof($result) > 0) {
            $renderer->doc .= '<form class="button" id="bookcreator__selections__list" name="bookcreator__selections__list" method="post" action="'.wl($page).'">';
            if($bookmanager) $renderer->doc .= "<fieldset style=\"text-align:left;\"><legend><b>{$this->getLang('listselections')}</b></legend>";
            $this->_showlist($renderer, $result, $bookmanager, $bookmanager);
            $renderer->doc .= "<input type='hidden' name='task' value=''/>";
            $renderer->doc .= "<input type='hidden' name='page' value=''/>";
            $renderer->doc .= "<input type='hidden' name='id' value='$page'/>";
            $renderer->doc .= formSecurityToken(false);
            if($bookmanager) $renderer->doc .= '</fieldset>';
            $renderer->doc .= '</form>';
        }
    }

    /**
     * Handle request for saving the selection list
     *
     * Selection is saved as bullet list on a wikipage
     */
    private function saveSelection() {
        if(isset($_COOKIE['list-pagelist'])) {
            if(isset($_POST['bookcreator_title'])) {
                $list = explode("|", $_COOKIE['list-pagelist']);

                //generate content
                $content = "====== ".$_POST['bookcreator_title']." ======".DOKU_LF;
                for($n = 0; $n < count($list); $n++) {
                    $page = $list[$n];
                    $content .= "  * [[:$page]]".DOKU_LF;
                }

                saveWikiText($this->getConf('save_namespace').":".$_POST['bookcreator_title'], $content, "selection created");
                msg($this->getLang('saved').": ".$this->getConf('save_namespace').":".$_POST['bookcreator_title']);
            } else {
                msg($this->getLang('needtitle'));
            }
        } else {
            msg($this->getLang('empty'));
        }
    }

    /**
     * Handle export request for exporting the selection as pdf or text
     *
     * @param Doku_renderer_xhtml $renderer
     */
    private function exportOnScreen(&$renderer) {
        $list = array();
        if(isset($_COOKIE['list-pagelist'])) {
            $renderer->doc = '';
            $list          = explode("|", $_COOKIE['list-pagelist']);
        }

        $render_mode = 'xhtml';
        $lf_subst    = '';
        if($_GET['do'] == 'export_text') {
            $render_mode = 'text';
            $lf_subst    = '<br>';
        }

        for($n = 0; $n < count($list); $n++) {
            $page = $list[$n];
            $renderer->doc .= str_replace(DOKU_LF, $lf_subst, p_cached_output(wikiFN($page), $render_mode)); //p_wiki_xhtml($page,$REV,false);
        }
    }

    /**
     * Handle request for deleting of selection list
     */
    private function deleteSelection() {
        saveWikiText($this->getConf('save_namespace').":".$_POST['page'], '', "selection removed");
        msg($this->getLang('deleted').": ".$this->getConf('save_namespace').":".$_POST['page']);
    }

    /**
     * Load a Saved Selection from a page
     *
     * @param Doku_Renderer_xhtml $renderer
     * @return array(title, array(list))
     */
    private function retrieveSavedSelection(&$renderer) {
        $list  = array();
        $title = '';

        $renderer->doc .= "
    <script type='text/javascript'><!--//--><![CDATA[//><!--
    book_removeAllPages('bookcreator');
    //--><!]]></script>";
        $select = rawWiki($this->getConf('save_namespace').":".$_POST['page']);
        $lines  = explode("\n", $select);

        foreach($lines as $i => $line) {
            //skip nonsense
            if(trim($line) == '') continue;
            if((($i > 0) && substr($line, 0, 7) != "  * [[:")) continue;

            //store title and list
            if($i === 0) {
                $line = str_replace("====== ", '', $line);
                $title = str_replace(" ======", '', $line);
            } else {
                $line = str_replace("  * [[:", '', $line);
                $line = str_replace("]]", '', $line);
                $list[]  = $line;

                //add to cookie
                $renderer->doc .= '
    <script type="text/javascript"><!--//--><![CDATA[//><!--
    book_changePage(\'bookcreator['.$line.']\', 1, new Date(\'July 21, 2099 00:00:00\'), \'/\');
    //--><!]]></script>';
            }
        }

        return array($title, $list);
    }

    /**
     * Read the active selection from the cookie
     *
     * @param array $fav selected pages as stored in the cookie
     * @return array list of pages selected for the book
     */
    private function readActiveSelection($fav) {
        $list = array();
        foreach($fav as $page => $cpt) {
            list($cpt, $date) = explode(";", $cpt);
            if($cpt < 1) continue;

            $list[] = $page;
        }
        return $list;
    }

    /**
     * Displays the Bookmanager - Let organize selections and export them
     * Only visible when a selection is loaded from the save selections or from cookie
     *
     * @param Doku_renderer_xhtml $renderer
     * @param bool                $usercansave User has permissions to save the selection
     * @return bool false: empty cookie, true: selection found and bookmanager is rendered
     */
    private function showBookManager(&$renderer, $usercansave) {
        global $lang, $ID;
        $title = '';
        $list = array();

        // to retrieve a saved selection
        if(isset($_POST['task']) && ($_POST['task'] == "read") && checkSecurityToken()) {
            list($title, $list) = $this->retrieveSavedSelection($renderer);

            // or the newly selected
        } elseif(isset($_COOKIE['bookcreator'])) {
            $fav = $_COOKIE['bookcreator'];

            //If there are no pages already inserted don't display Book Manager
            if(($fav == "") || (count($fav) == 0)) {
                $renderer->doc .= $this->getLang('empty');
                return false;
            }
            $list = $this->readActiveSelection($fav);

        }

        //start main container - open left column
        $renderer->doc .= "<table width='100%' border='0' ><tr>";
        $renderer->doc .= "<td width='60%' valign='top'>";

        // Display selected pages
        $renderer->header($this->getLang('toprint'), 2, 0);
        $renderer->doc .= '<ul id="pagelist" class="boxes">';
        foreach($list as $page) {
            $lien = $this->createLink($page);
            $renderer->doc .= '<li itemID="'.$page.'">';
            $renderer->doc .= '  <a href="javascript:book_changePage(\'bookcreator['.$page.']\', 0, new Date(\'July 21, 2099 00:00:00\'), \'/\'); book_recharge();">';
            $renderer->doc .= '    <img src="'.DOKU_URL.'lib/plugins/bookcreator/images/remove.png" title="'.$this->getLang('remove').'" border="0" style="vertical-align:middle;" name="ctrl" />';
            $renderer->doc .= '  </a>&nbsp;&nbsp;';
            $renderer->doc .= $lien;
            $renderer->doc .= '</li>';
        }
        $renderer->doc .= '</ul>';
        $renderer->doc .= "<br />";

        // Excluded pages from the book
        if(isset($fav)) {
            $i = 0;
            foreach($fav as $page => $cpt) {
                list($cpt, $date) = explode(";", $cpt);
                if($cpt == 0) {
                    if(!$i) {
                        $renderer->header($this->getLang('removed'), 2, 0);
                        $renderer->listu_open();
                    }
                    $lien = $this->createLink($page);
                    $i++;

                    $renderer->doc .= "<div id=\"ex__$page\">";
                    $renderer->listitem_open(1);
                    $renderer->doc .= '<a href="javascript:book_changePage(\'bookcreator['.$page.']\', 1, new Date(\'July 21, 2099 00:00:00\'), \'/\');  book_recharge();">';
                    $renderer->doc .= '  <img src="'.DOKU_URL.'lib/plugins/bookcreator/images/include.png" title="'.$this->getLang('include').'" border="0" style="vertical-align:middle;" name="ctrl" />';
                    $renderer->doc .= '</a> ';
                    $renderer->doc .= $lien;
                    $renderer->doc .= "</div>";
                    $renderer->listitem_close();
                }
            }
            if($i) $renderer->listu_close();
        }

        // reset selection
        $onclick = "javascript:if(confirm('".$this->getLang('reserconfirm')."')) {book_removeAllPages('bookcreator'); document.reset.submit();}";
        $renderer->doc .= "<div align='center'>";
        $renderer->doc .= '  <form name="reset" class="button" method="get" action="'.wl($ID).'">';
        $renderer->doc .= "    <input type='button' value='".$this->getLang('reset')."' class='button' onclick=\"$onclick\">";
        $renderer->doc .= "    <input type='hidden' name='id' value='$ID'/>";
        $renderer->doc .= formSecurityToken(false);
        $renderer->doc .= '  </form>';
        $renderer->doc .= '</div>';
        //  reset selection

        //close left column - open right column
        $renderer->doc .= "</td>";
        $renderer->doc .= "<td width='40%' valign='top' >";

        $renderer->doc .= "<div align='center'>";

        // PDF Export
        $renderer->doc .= '<form class="button" method="get" action="'.wl($ID).'" accept-charset="'.$lang['encoding'].'">';
        $renderer->doc .= "  <fieldset style=\"text-align:left;\"><legend><b>".$this->getLang('export')."</b></legend>";
        $renderer->doc .= $this->getLang('title')." ";
        $renderer->doc .= '    <input type="text" class="edit" value="'.$title.'" name="pdfbook_title" size="40" />';
        $renderer->doc .= '    <select name="do" size="1">';
        //options of export select:
        $renderer->doc .= '    <option value="export_html" selected="selected">'.$this->getLang('exportprint').'</option>';
        if(file_exists(DOKU_PLUGIN."text/renderer.php") && !plugin_isdisabled("text")) {
            $renderer->doc .= '<option value="export_text">'.$this->getLang('exporttext').'</option>';
        }
        if(file_exists(DOKU_PLUGIN."dw2pdf/action.php") && !plugin_isdisabled("dw2pdf")) {
            $renderer->doc .= '<option value="export_pdfbook" selected="selected">'.$this->getLang('exportpdf').'</option>';
        }

        $renderer->doc .= '    </select>';
        $renderer->doc .= '    <input type="submit" value="'.$this->getLang('create').'" class="button"/>';
        $renderer->doc .= '    <input type="hidden" name="id" value="'.$ID.'" />';
        $renderer->doc .= '  </fieldset>';
        $renderer->doc .= formSecurityToken(false);
        $renderer->doc .= '</form>';
        // PDF Export

        if($usercansave) {
            //Save selection
            $renderer->doc .= '<form class="button" method="post" action="'.wl($ID).'" accept-charset="'.$lang['encoding'].'">';
            $renderer->doc .= "  <fieldset style=\"text-align:left;\"><legend><b>".$this->getLang('saveselection')."</b></legend>";
            $renderer->doc .= '    <input type="text" class="edit" value="'.$title.'" name="bookcreator_title" />';
            $renderer->doc .= '    <input type="submit" value="'.$this->getLang('save').'" class="button"/>';
            $renderer->doc .= '    <input type="hidden" name="task" value="save" />';
            $renderer->doc .= '    <input type="hidden" name="id" value="'.$ID.'" />';
            $renderer->doc .= '  </fieldset>';
            $renderer->doc .= formSecurityToken(false);
            $renderer->doc .= '</form>';
            //Save selection
        }

        $renderer->doc .= '</div>';

        //close container
        $renderer->doc .= "</tr></td>";
        $renderer->doc .= "</table>";
        return true;
    }

    /**
     * usort callback to sort by file lastmodified time
     */
    function _datesort($a, $b) {
        if($b['rev'] < $a['rev']) return -1;
        if($b['rev'] > $a['rev']) return 1;
        return strcmp($b['id'], $a['id']);
    }

    /**
     * usort callback to sort by file title
     */
    function _titlesort($a, $b) {
        if($a['id'] <= $b['id']) return -1;
        if($a['id'] > $b['id']) return 1;
    }

    /**
     * Lists saved selections, by looking up corresponding pages in the reserverd namespace
     *
     * @param string $order sort by 'date' or 'title'
     * @param int    $limit maximum number of selections
     * @return array
     */
    private function _getlist($order, $limit = 0) {
        global $conf;

        $ns      = cleanID($this->getConf('save_namespace'));
        $tt      = utf8_encodeFN(str_replace(':', '/', $ns));
        $nsdepth = count(explode('/', $tt));
        $result  = array();
        $opts    = array(
            'depth'   => $nsdepth + 1,
            'skipacl' => false
        );
        $ns      = cleanID($this->getConf('save_namespace'));
        $tt      = utf8_encodeFN(str_replace(':', '/', $ns));
        search($result, $conf['datadir'], 'search_allpages', $opts, $tt);
        if(sizeof($result) > 0) {

            if($order == 'date') {
                usort($result, array($this, '_datesort'));
            } elseif($order == 'title') {
                usort($result, array($this, '_titlesort'));
            }

            if($limit != 0) $result = array_slice($result, 0, $limit);
        }
        return $result;
    }

    /**
     * Displays the Selection List
     *
     * @param Doku_Renderer_xhtml $renderer
     * @param array               $result
     * @param bool                $showbin
     * @param bool                $showtime
     */
    private function _showlist(&$renderer, $result, $showbin = false, $showtime = false) {
        $renderer->listu_open();
        foreach($result as $item) {
            $itemtitle = p_get_first_heading($item['id']);
            $nons      = noNS($item['id']);
            $imagebase = DOKU_URL."lib/plugins/bookcreator/images/";
            $renderer->listitem_open(1);
            if(($showbin) && (auth_quickaclcheck($item['id']) >= AUTH_DELETE)) {
                $renderer->doc .= "<a href=\"javascript:actionList('del','{$nons}');\" >";
                $renderer->doc .= "  <img src='{$imagebase}remove.png' title='{$this->getLang('delselection')}' border='0' style='vertical-align:middle;' />";
                $renderer->doc .= "</a>";
            }
            $renderer->doc .= "<a href='".wl($this->getConf('save_namespace').":".$nons)."'>";
            $renderer->doc .= "  <img src='{$imagebase}include.png' title='{$this->getLang('showpage')}' border='0' style='vertical-align:middle;' />";
            $renderer->doc .= "</a>";
            $renderer->doc .= "<a href=\"javascript:actionList('read','$nons');\" title='{$this->getLang('loadselection')}'>$itemtitle</a>";
            if($showtime) $renderer->cdata(' ('.dformat($item['mtime']).')');
            $renderer->listitem_close();
        }
        $renderer->listu_close();
    }

    /**
     * Returns html for link to given page, performs a lookup for header
     *
     * @param        $page
     * @return string html of referer
     */
    private function createLink($page) {
        $pos      = strrpos(utf8_decode($page), ':');
        $pageName = p_get_first_heading($page);
        if($pageName == NULL) {
            if($pos != false) {
                $pageName = utf8_substr($page, $pos + 1, utf8_strlen($page));
            } else {
                $pageName = $page;
            }
            $pageName = str_replace('_', ' ', $pageName);
        }
        return "<a href='".wl($page, false, true, "&")."'>".$pageName."</a>";
    }

}
