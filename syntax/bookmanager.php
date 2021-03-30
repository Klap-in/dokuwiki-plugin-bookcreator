<?php
/**
 * BookCreator plugin : Create a book from some pages.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
use dokuwiki\Form\Form;

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_bookcreator_bookmanager extends DokuWiki_Syntax_Plugin {

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
        $this->Lexer->addSpecialPattern('~~\w*?BOOK.*?~~', $mode, 'plugin_bookcreator_bookmanager');
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

            $sortoptions = ['date', 'title'];
            if(is_numeric($param1)) {
                $num = (int) $param1;
                if(in_array($param2, $sortoptions)) {
                    $order = $param2;
                }
            } elseif(in_array($param1, $sortoptions)) {
                $order = $param1;
                if(is_numeric($param2)) {
                    $num = (int)$param2;
                }
            } elseif(is_numeric($param2)) {
                $num = (int) $param2;
            }
        }

        return array($type, $num, $order);

    }

    /**
     * @param string        $format render mode e.g. text, xhtml, meta,...
     * @param Doku_Renderer &$renderer
     * @param array         $data return of handle()
     * @return bool
     */
    function render($format, Doku_Renderer $renderer, $data) {
        global $ID;
        global $INPUT;

        list($type, $num, $order) = $data;

        if($type == "bookmanager") {
            if($format == 'text' && $INPUT->str('do') == 'export_text') {
                $format = 'xhtml';
            }

            if($format == 'xhtml') {
                /** @var Doku_Renderer_xhtml $renderer */
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

            if($format == 'xhtml') {
                /** @var Doku_Renderer_xhtml $renderer */
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
        $result = $this->getlist($order, $num);
        if(sizeof($result) > 0) {
            $form = new Form(['action'=> wl($bmpage)]);
            $form->addClass('bookcreator__selections__list');

            if($bookmanager) {
                $form->addFieldsetOpen($this->getLang('listselections'));
                $form->addHTML('<div class="message"></div>');
            }
            $form->addHTML($this->showlist($result, $bookmanager));
            $form->setHiddenField('do', '');
            $form->setHiddenField('task', '');
            $form->setHiddenField('page', '');
            if($bookmanager) {
                $form->addFieldsetClose();
            }

            $renderer->doc .= $form->toHTML();
        }
    }



    /**
     * Handle export request for exporting the selection as html or text
     *
     * @param Doku_renderer_xhtml $renderer
     */
    private function exportOnScreen($renderer) {
        global $ID;
        global $INPUT;
        try{
            $list = array();
            if($INPUT->has('selection')) {
                //export current list from the bookmanager
                $list = json_decode($INPUT->str('selection', '', true), true);
                if(!is_array($list) || empty($list)) {
                    throw new Exception($this->getLang('empty'));
                }
            } elseif($INPUT->has('savedselection')) {
                //export a saved selection of the Bookcreator Plugin
                /** @var action_plugin_bookcreator_handleselection $SelectionHandling */
                $SelectionHandling = plugin_load('action', 'bookcreator_handleselection');
                $savedselection = $SelectionHandling->loadSavedSelection($INPUT->str('savedselection'));
                $list = $savedselection['selection'];
            }

            //remove first part of bookmanager page
            $renderer->doc = '';

            $render_mode = 'xhtml';
            if($INPUT->str('do') == 'export_text') {
                $render_mode = 'text';
            }

            $skippedpages = array();
            foreach($list as $index => $pageid) {
                if(auth_quickaclcheck($pageid) < AUTH_READ) {
                    $skippedpages[] = $pageid;
                    unset($list[$index]);
                }
            }
            $list = array_filter($list, 'strlen'); //use of strlen() callback prevents removal of pagename '0'

            //if selection contains forbidden pages throw (overridable) warning
            if(!$INPUT->bool('book_skipforbiddenpages') && !empty($skippedpages)) {
                $msg = hsc(join(', ', $skippedpages));
                throw new Exception(sprintf($this->getLang('forbidden'), $msg));
            }
        } catch (Exception $e) {
            http_status(400);
            print $e->getMessage();
            exit();
        }
        $keep = $ID;
        foreach($list as $page) {
            $ID = $page;
            $renderer->doc .= p_cached_output(wikiFN($page), $render_mode, $page);
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
//        $title = '';

        //start main container - open left column
        $renderer->doc .= "<div class='bookcreator__manager'>";
        // Display pagelists
        // - selected pages
        $renderer->doc .= "<div class='bookcreator__pagelist' >";
        $this->showPagelist($renderer, 'selected');
        $renderer->doc .= "<br />";

        // Add namespace to selection

        $form = new dokuwiki\Form\Form();
        $form->addClass('selectnamespace');
        $form->addButton('selectns', $this->getLang('select_namespace'))
            ->attr('type', 'submit');

        $renderer->doc .= "<div class='bookcreator__selectns'>";
        $renderer->doc .= $form->toHTML();
        $renderer->doc .= "</div>";

        // - excluded pages
        $renderer->doc .= '<div id="bookcreator__delpglst">';
        $this->showPagelist($renderer, 'deleted');
        $renderer->doc .= '</div>';

        // Reset current selection
        $form = new Form();
        $form->addClass('clearactive');
        $form->addButton('resetselection', $this->getLang('reset'))
            ->attr('type', 'submit');

        $renderer->doc .= '<div>';
        $renderer->doc .= $form->toHTML();
        $renderer->doc .= '</div>';

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

        $form = new Form();
        $form->addClass('downloadselection');

        $form->addFieldsetOpen($this->getLang('export'));

        $form->addHTML($this->getLang('title')." ");
        $form->addTextInput('book_title')
            ->addClass('edit')
            ->attrs(['size'=> 30]);
        $form->addCheckbox('book_skipforbiddenpages', $this->getLang('skipforbiddenpages'))
            ->addClass('book_skipforbiddenpages'); //note: class extra at input
        $form->addDropdown('do', $values)
            ->val($selected)
            ->attrs(['size'=> 1]);
        $form->setHiddenField('outputTarget', 'file');
        $form->setHiddenField('id', $ID);
        $form->addButton('exportselection', $this->getLang('create'))->attr('type', 'submit');

        $form->addFieldsetClose();

        $renderer->doc .= $form->toHTML();


        // Save current selection to a wikipage
        if($usercansave) {
            $form = new Form();
            $form->addClass('saveselection');


            $form->addFieldsetOpen($this->getLang('saveselection'));

            $form->addHTML('<div class="message"></div>');
            $form->addTextInput('bookcreator_title')
                ->addClass('edit');
            $form->setHiddenField('task', 'save');
            $form->addButton('saveselection', $this->getLang('save'))->attr('type', 'submit');

            $form->addFieldsetClose();

            $renderer->doc .= $form->toHTML();
        }

        //close containers
        $renderer->doc .= '</div>'
                        . "</div><div class='clearer'></div>"
                        . "<br />";

        $renderer->doc .= "<div id='preparing-file-modal' title='{$this->getLang("titlepreparedownload")}' style='display: none;'>"
                        . $this->getLang('preparingdownload')
                        . '    <div class="ui-progressbar-value ui-corner-left ui-corner-right" style="width: 100%; height:22px; margin-top: 20px;"></div>'
                        . '</div>';

        $renderer->doc .= "<div id='error-modal' title='{$this->getLang("titleerrordownload")}' style='display: none;'>"
                        . "    <div class='downloadresponse'>{$this->getLang('faileddownload')}</div>"
                        . '</div>';

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
     * @param int    $limit maximum number of selections, 0=all
     * @return array
     */
    private function getlist($order, $limit = 0) {
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
    private function showlist($result, $isbookmanager = false) {
        $output = '<ul>'.DOKU_LF;
        foreach($result as $item) {
            $output .= $this->hlp->createListitem($item, $isbookmanager);
        }
        $output .= '</ul>'.DOKU_LF;
        return $output;
    }
}
