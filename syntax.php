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

    /**
     * @param string $mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~\w*?BOOK.*?~~', $mode, 'plugin_bookcreator');
    }

    /**
     * Syntax Type
     *
     * @return string
     */
    function getType() {
        return 'container';
    }

    /**
     * Paragraph Type
     *
     * @return string
     */
    function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 190;
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        $match = substr($match, 2, -2); // strip markup
        if(substr($match, 0, 7) == 'ARCHIVE') $type = 'archive';
        else $type = 'book';

        $num   = 10;
        $order = 'date';
        if($type == 'archive') {
            list(/* $junk */, $params) = explode(':', $match, 2);
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
    function render($mode, Doku_Renderer $renderer, $data) {
        global $ID;
        global $INPUT;

        list($type, $num, $order) = $data;

        if($type == "book") {
            $renderer->info['cache'] = false;

            if($mode == 'text' && $INPUT->get->str('do') == 'export_text') {
                $mode = 'xhtml';
            }

            if($mode == 'xhtml') {
                /** @var $renderer Doku_Renderer_xhtml */

                // verification that if the user can save / delete the selections
                $usercansave = (auth_quickaclcheck($this->getConf('save_namespace').':*') >= AUTH_CREATE);

                if($usercansave) {
                    //save or delete selection
                    if($INPUT->post->str('task') == "save" && checkSecurityToken()) {
                        $this->saveSelection();
                    } elseif($INPUT->post->str('task') == "delete" && checkSecurityToken()) {
                        $this->deleteSelection();
                    }
                }

                $do = $INPUT->get->str('do');
                $allowed_onscreen_exports = array(
                    'export_html',
                    'export_text'
                );
                if(in_array($do, $allowed_onscreen_exports)) {
                    //export as xhtml or text
                    $this->exportOnScreen($renderer);

                } else {
                    $renderer->info['cache'] = false;

                    //search for the current selection in cookie
                    $foundlist = false;
                    if(isset($_COOKIE['bookcreator'])) {
                        $currentselection = $_COOKIE['bookcreator'];

                        //empty?
                        if(($currentselection == "") || (count($currentselection) == 0)) {
                            $renderer->doc .= $this->getLang('empty');
                        } else {
                            $foundlist = true;
                        }
                    }

                    //show the bookmanager when there are pages selected, otherwise a message.
                    if($foundlist) {
                        $this->showBookManager($renderer, $usercansave);
                    } else {
                        //no selection available
                        $renderer->doc .= $this->getLang('nocookies');
                    }

                    // Displays the list of saved selections
                    $this->renderSelectionslist($renderer, $bookmanager = true, $ID, $order);
                    $renderer->doc .= "<br />";
                }
            }
            return false;

        } else {
            // type == archive

            if($mode == 'xhtml') {
                /** @var $renderer Doku_Renderer_xhtml */
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
     * @param string              $bmpage pageid of the page with the Book Manager
     * @param string              $order sort by 'title' or 'date'
     * @param int                 $num number of listed items, 0=all items
     *
     * if in the Book Manager, the delete buttons are displayed
     * the list with save selections is only displayed once, and the bookmanager with priority
     */
    public function renderSelectionslist($renderer, $bookmanager, $bmpage, $order, $num = 0) {
        static $selectionlistshown = false;

        if($selectionlistshown == true) {
            $renderer->doc .= $this->getLang('duplicate');

        } else {
            $result = $this->_getlist($order, $num);
            if(sizeof($result) > 0) {
                $form = new Doku_Form(array('method'=> 'post', 'id'=> 'bookcreator__selections__list', 'name'=> 'bookcreator__selections__list', 'action'=> wl($bmpage)));
                if($bookmanager) $form->startFieldset($this->getLang('listselections'));
                $form->addElement($this->_showlist($result, $bookmanager, $bookmanager));
                $form->addHidden('do', '');
                $form->addHidden('task', '');
                $form->addHidden('page', '');
                if($bookmanager) $form->endFieldset();

                $renderer->doc .= $form->getForm();
            }
            $selectionlistshown = true;
        }

    }

    /**
     * Handle request for saving the selection list
     *
     * Selection is saved as bullet list on a wikipage
     */
    private function saveSelection() {
        global $INPUT;

        if(!empty($_COOKIE['list-pagelist'])) {
            $bookcreator_title = $INPUT->post->str('bookcreator_title');

            if(!empty($bookcreator_title)) {
                $list = explode("|", $_COOKIE['list-pagelist']);

                //generate content
                $content = "====== ".$bookcreator_title." ======".DOKU_LF;
                for($n = 0; $n < count($list); $n++) {
                    $page = $list[$n];
                    $content .= "  * [[:$page]]".DOKU_LF;
                }

                saveWikiText($this->getConf('save_namespace').":".$bookcreator_title, $content, "selection created");
                msg($this->getLang('saved').": ".$this->getConf('save_namespace').":".$bookcreator_title, 1);
            } else {
                msg($this->getLang('needtitle'), -1);
            }
        } else {
            msg($this->getLang('empty'), -1);
        }
    }

    /**
     * Handle request for deleting of selection list
     */
    private function deleteSelection() {
        global $INPUT;
        $page = $INPUT->post->str('page');

        saveWikiText($this->getConf('save_namespace').":".$page, '', "selection removed");
        msg($this->getLang('deleted').": ".$this->getConf('save_namespace').":".$page, 1);
    }

    /**
     * Handle export request for exporting the selection as pdf or text
     *
     * @param Doku_renderer_xhtml $renderer
     */
    private function exportOnScreen(&$renderer) {
        global $ID;
        global $INPUT;

        $list = array();
        if(isset($_COOKIE['list-pagelist'])) {
            $renderer->doc = '';
            $list          = explode("|", $_COOKIE['list-pagelist']);
        }

        $render_mode = 'xhtml';
        $lf_subst    = '';
        if($INPUT->get->str('do') == 'export_text') {
            $render_mode = 'text';
            $lf_subst    = '<br>';
        }

        $keep = $ID;
        foreach($list as $page) {
            $ID = $page;
            $renderer->doc .= str_replace(DOKU_LF, $lf_subst, p_cached_output(wikiFN($page), $render_mode, $page)); //p_wiki_xhtml($page,$REV,false);
        }
        $ID = $keep;
    }

    /**
     * Displays the Bookmanager - Let organize selections and export them
     * Only visible when a selection is loaded from the save selections or from cookie
     *
     * @param Doku_renderer_xhtml $renderer
     * @param bool                $usercansave User has permissions to save the selection
     * @return bool false: empty cookie, true: selection found and bookmanager is rendered
     */
    private function showBookManager($renderer, $usercansave) {
        global $ID;
        global $INPUT;
        $title = '';

        // get a saved selection array from file
        $list = $_COOKIE['bookcreator'];

        // title
        $bookcreator_title = $INPUT->post->str('bookcreator_title');
        if(!empty($bookcreator_title)) {
            $title = $bookcreator_title;
        } elseif(isset($_COOKIE['bookcreator_title'])) {
            $title = $_COOKIE['bookcreator_title'];
        }

        //start main container - open left column
        $renderer->doc .= "<div class='bookcreator__manager'><div class='bookcreator__pagelist' >";

        // Display pagelists
        // - selected pages
        $this->showPagelist($renderer, $list, 'include');
        $renderer->doc .= "<br />";

        // - excluded pages
        $renderer->doc .= '<div id="bookcreator__delpglst">';
        $this->showPagelist($renderer, $list, 'remove');
        $renderer->doc .= '</div>';

        // Reset current selection
        $form = new Doku_Form(array('method'=> 'post', 'class'=> 'clearactive'));
        $form->addElement(form_makeButton('submit', 'clearactiveselection', $this->getLang('reset')));
        $renderer->doc .= "<div align='center'>";
        $renderer->doc .= $form->getForm();
        $renderer->doc .= "</div>";

        //close left column - open right column
        $renderer->doc .= "</div>";
        $renderer->doc .= "<div class='bookcreator__actions'>";

        // PDF Export
        $values   = array('export_html'=> $this->getLang('exportprint'));
        $selected = 'export_html';
        if(file_exists(DOKU_PLUGIN."text/renderer.php") && !plugin_isdisabled("text")) {
            $values['export_text'] = $this->getLang('exporttext');
        }
        if(file_exists(DOKU_PLUGIN."odt/action/export.php") && !plugin_isdisabled("odt")) {
            $values['export_odtbook'] = $this->getLang('exportodt');
            $selected                 = 'export_odtbook';
        }
        if(file_exists(DOKU_PLUGIN."dw2pdf/action.php") && !plugin_isdisabled("dw2pdf")) {
            $values['export_pdfbook'] = $this->getLang('exportpdf');
            $selected                 = 'export_pdfbook';
        }

        $form = new Doku_Form(array('method'=> 'get'));
        $form->startFieldset($this->getLang('export'));
        $form->addElement($this->getLang('title')." ");
        $form->addElement(form_makeTextField('book_title', $title, '', '', 'edit', array('size'=> 40)));
        $form->addElement(form_makeListboxField('do', $values, $selected, '', '', '', array('size'=> 1)));
        $form->addHidden('id', $ID);
        $form->addElement(form_makeButton('submit', '', $this->getLang('create')));
        $form->endFieldset();

        $renderer->doc .= $form->getForm();

        // Save current selection to a wikipage
        if($usercansave) {
            $form = new Doku_Form(array('method'=> 'post'));
            $form->startFieldset($this->getLang('saveselection'));
            $form->addElement(form_makeTextField('bookcreator_title', $title, '', '', 'edit'));
            $form->addHidden('task', 'save');
            $form->addElement(form_makeButton('submit', '', $this->getLang('save')));
            $form->endFieldset();

            $renderer->doc .= $form->getForm();
        }

        //close containers
        $renderer->doc .= '</div>';
        $renderer->doc .= "</div><div class='clearer'></div>";
        $renderer->doc .= "<br />";

        return true;
    }

    /**
     * Displays list of selected/deleted pages
     *
     * @param Doku_Renderer_xhtml $renderer
     * @param array               $list array with pageids as key, status as value
     * @param string              $action 'remove' or 'include'
     */
    private function showPagelist($renderer, $list, $action) {
        $jslang = $this->getLang('js');
        if($action == 'remove') {
            $id          = 'deletedpagelist';
            $heading     = 'removed';
            $actiontitle = $jslang['include'];
        } else {
            $id          = 'pagelist';
            $heading     = 'toprint';
            $actiontitle = $jslang['remove'];
        }

        $renderer->header($this->getLang($heading), 2, 0);
        $renderer->doc .= "<ul id=$id class='pagelist $action'>";
        foreach($list as $pageid => $cpt) {
            if(($action == 'remove' && $cpt == 0) || ($action == 'include' && $cpt == 1)) {
                $renderer->doc .= "<li class='level1' id='pg__$pageid' title='{$this->getLang('sortable')}'>";
                $renderer->doc .= "<a class='action' title='$actiontitle'></a>";
                $renderer->doc .= '&nbsp;&nbsp;';
                $renderer->doc .= $this->createLink($pageid);
                $renderer->listitem_close();
            }
        }
        $renderer->listu_close();
    }

    /**
     * usort callback to sort by file lastmodified time
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    function _datesort($a, $b) {
        if($b['rev'] < $a['rev']) return -1;
        if($b['rev'] > $a['rev']) return 1;
        return strcmp($b['id'], $a['id']);
    }

    /**
     * usort callback to sort by file title
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    function _titlesort($a, $b) {
        if($a['id'] <= $b['id']) return -1;
        if($a['id'] > $b['id']) return 1;
        return 0;
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
     * @param array               $result
     * @param bool                $showbin
     * @param bool                $showtime
     * @return string html of list
     */
    private function _showlist($result, $showbin = false, $showtime = false) {
        $output = '<ul>'.DOKU_LF;
        foreach($result as $item) {
            $itemtitle = p_get_first_heading($item['id']);
            $nons      = noNS($item['id']);

            $output .= "<li class='level1' id='sel__$nons'>";
            if(($showbin) && (auth_quickaclcheck($item['id']) >= AUTH_DELETE)) {
                $output .= "<a class='action delete' href='#delete'>";
                $output .= "  <img src='".DOKU_URL."lib/plugins/bookcreator/images/remove.png' title='{$this->getLang('delselection')}' />";
                $output .= "</a>";
            }
            $output .= "<a href='".wl($this->getConf('save_namespace').":".$nons)."'>";
            $output .= "  <img src='".DOKU_URL."lib/plugins/bookcreator/images/include.png' title='{$this->getLang('showbook')}' />";
            $output .= "</a>";
            $output .= "<a class='action read' href='#read' title='{$this->getLang('loadselection')}'>$itemtitle</a>";
            if($showtime) $output .= ' ('.dformat($item['mtime']).')';
            $output .= '</li>'.DOKU_LF;
        }
        $output .= '</ul>'.DOKU_LF;
        return $output;
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
        return "<a href='".wl($page, false, true, "&")."' title='{$this->getLang('showpage')}'>".$pageName."</a>";
    }

}
