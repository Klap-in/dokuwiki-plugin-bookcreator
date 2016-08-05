<?php
/**
 * BookCreator plugin : Create a book from some pages.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_bookcreator extends DokuWiki_Syntax_Plugin {

    /** @var helper_plugin_bookcreator */
    protected $hlp;

    /**
     * Constructor
     */
    public function __construct() {
        $this->hlp = plugin_load('helper', 'bookcreator');
    }
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
        if(substr($match, 0, 7) == 'ARCHIVE') {
            $type = 'archive';
        } else {
            $type = 'bookmanager';
        }

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

        if($type == "bookmanager") {
            if($mode == 'text' && $INPUT->str('do') == 'export_text') {
                $mode = 'xhtml';
            }

            if($mode == 'xhtml') {
                /** @var $renderer Doku_Renderer_xhtml */
                $renderer->info['cache'] = false;

                // verification that if the user can save / delete the selections
                $usercansave = (auth_quickaclcheck($this->getConf('save_namespace').':*') >= AUTH_CREATE);

                //intervents the normal export_* handling
                $do = $INPUT->str('do');
                $allowed_onscreen_exports = array(
                    'export_html',
                    'export_text'
                );
                if(in_array($do, $allowed_onscreen_exports)) {
                    //export as xhtml or text
                    $this->exportOnScreen($renderer);

                } else {
                    //show the bookmanager
                    $this->showBookManager($renderer, $usercansave);

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
                $form = new Doku_Form(array('method'=> 'post',
                                            'id'=>     'bookcreator__selections__list',
                                            'name'=>   'bookcreator__selections__list',
                                            'action'=> wl($bmpage)));
                if($bookmanager) {
                    $form->startFieldset($this->getLang('listselections'));
                    $form->addElement('<div class="message"></div>');
                }
                $form->addElement($this->_showlist($result, $bookmanager));
                $form->addHidden('do', '');
                $form->addHidden('task', '');
                $form->addHidden('page', '');
                if($bookmanager) {
                    $form->endFieldset();
                }

                $renderer->doc .= $form->getForm();
            }
            $selectionlistshown = true;
        }

    }



    /**
     * Handle export request for exporting the selection as html or text
     *
     * @param Doku_renderer_xhtml $renderer
     */
    private function exportOnScreen(&$renderer) {
        global $ID;
        global $INPUT;

        $json = new JSON(JSON_LOOSE_TYPE);
        $list = $json->decode($INPUT->str('selection', '', true));
        if(!is_array($list) || empty($list)) {
            http_status(400);
            print $this->getLang('empty');
            exit();
        }

        //remove first part of bookmanager page
        $renderer->doc = '';

        $render_mode = 'xhtml';
//        $lf_subst    = '';
        if($INPUT->str('do') == 'export_text') {
            $render_mode = 'text';
//            $lf_subst    = DOKU_LF;//'<br>';   //TODO: strange choice to replace by br
        }

        $keep = $ID;
        foreach($list as $page) {
            $ID = $page;
//            $renderer->doc .= str_replace(DOKU_LF, $lf_subst, p_cached_output(wikiFN($page), $render_mode, $page)); //p_wiki_xhtml($page,$REV,false);
            $renderer->doc .= p_cached_output(wikiFN($page), $render_mode, $page); //p_wiki_xhtml($page,$REV,false);

        }

        //add mark for removing everything after these rendered pages, see action component 'export'
        $renderer->doc .= '<!-- END EXPORTED PAGES -->';
        $ID = $keep;
    }

    /**
     * Displays the Bookmanager - Let organize selections and export them
     * Only visible when a selection is loaded from the save selections or from cookie FIXME
     *
     * @param Doku_renderer_xhtml $renderer
     * @param bool                $usercansave User has permissions to save the selection
     * @return bool false: empty cookie, true: selection found and bookmanager is rendered
     */
    private function showBookManager($renderer, $usercansave) {
        global $ID;
        $title = '';

        //start main container - open left column
        $renderer->doc .= "<div class='bookcreator__manager'>";
        // Display pagelists
        // - selected pages
        $renderer->doc .= "<div class='bookcreator__pagelist' >";
        $this->showPagelist($renderer, 'selected');
        $renderer->doc .= "<br />";

        // - excluded pages
        $renderer->doc .= '<div id="bookcreator__delpglst">';
        $this->showPagelist($renderer, 'deleted');
        $renderer->doc .= '</div>';

        // Reset current selection
        $form = new Doku_Form(array('method'=> 'post',
                                    'class'=> 'clearactive'));
        $form->addElement(form_makeButton('submit', '', $this->getLang('reset')));
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

        $form = new Doku_Form(array('method'=> 'post',
                                    'class'=> 'downloadselection'));
        $form->startFieldset($this->getLang('export'));
        $form->addElement($this->getLang('title')." ");
        $form->addElement(form_makeTextField('book_title', $title, '', '', 'edit', array('size'=> 30)));
        $form->addElement(form_makeCheckboxField('book_skipforbiddenpages', '1', 'Skip Forbidden Pages','','book_skipforbiddenpages'));
        $form->addElement(form_makeListboxField('do', $values, $selected, '', '', '', array('size'=> 1)));
        $form->addHidden('id', $ID);
        $form->addElement(form_makeButton('submit', '', $this->getLang('create')));
        $form->endFieldset();

        $renderer->doc .= $form->getForm();


        // Save current selection to a wikipage
        if($usercansave) {
            $form = new Doku_Form(array('method'=> 'post',
                                        'class'=> 'saveselection'));
            $form->startFieldset($this->getLang('saveselection'));
            $form->addElement('<div class="message"></div>');
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

        $renderer->doc .= "<div id='preparing-file-modal' title='{$this->getLang("titlepreparedownload")}' style='display: none;'>";
        $renderer->doc .=   $this->getLang('preparingdownload');
        $renderer->doc .= '    <div class="ui-progressbar-value ui-corner-left ui-corner-right" style="width: 100%; height:22px; margin-top: 20px;"></div>';
        $renderer->doc .= '</div>';

        $renderer->doc .= "<div id='error-modal' title='{$this->getLang("titleerrordownload")}' style='display: none;'>";
        $renderer->doc .= '<div class="downloadresponse">$this->getLang(\'faileddownload\')</div>';
        $renderer->doc .= '</div>';

        return true;
    }

    /**
     * Displays list of selected/deleted pages
     *
     * @param Doku_Renderer_xhtml $renderer
     * @param string              $selection 'deleted' or 'selected'
     */
    private function showPagelist($renderer, $selection) {
        if($selection == 'deleted') {
            $id          = 'deletedpagelist';
            $heading     = 'removed';
        } else {
            $id          = 'pagelist';
            $heading     = 'toprint';
        }

        $renderer->header($this->getLang($heading), 2, 0);
        $renderer->doc .= "<ul id=$id class='pagelist $selection'>";
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
     * @param array $result   results generated by search()
     * @param bool  $isbookmanager
     * @return string html of list
     */
    private function _showlist($result, $isbookmanager = false) {
        $output = '<ul>'.DOKU_LF;
        foreach($result as $item) {
            $output .= $this->hlp->createListitem($item, $isbookmanager);
        }
        $output .= '</ul>'.DOKU_LF;
        return $output;
    }
}
