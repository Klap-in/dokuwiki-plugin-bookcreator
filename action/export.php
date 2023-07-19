<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

use dokuwiki\Extension\Event;

/**
 * Take care of exporting only pages in selection, and not the bookmanager page itself
 */
class action_plugin_bookcreator_export extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_EXPORT_POSTPROCESS', 'BEFORE', $this, 'replacePageExportBySelection');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'renameDoExportNSAction');
    }

    /**
     * Handle export request for exporting namespace renamed as normal html/text
     * export, the 'book_ns' is used to recognize the namespace. This way we can use the default handling of text
     * and html exports
     *
     * @param Event $event
     */
    public function renameDoExportNSAction(Event $event) {
        $allowedEvents = ['export_htmlns', 'export_textns'];
        if(in_array($event->data, $allowedEvents)) {
            $event->data = substr($event->data, 0, -2);
        }

        //export_xhtml is built-in xhtml export with header
        if($event->data === 'export_html') { // also act_clean() does this rename
            $event->data = 'export_xhtml';
        }
    }

    /**
     * Handle export request for exporting the selection as html or text
     *
     * @param Event $event
     */
    public function replacePageExportBySelection(Event $event) {
        if(!in_array($event->data['mode'], ['text', 'xhtml'])) {
            //skip other export modes
            return;
        }

        global $ID;
        global $INPUT;
        try{
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
            } elseif($INPUT->has('book_ns')) {
                //export triggered with export_textns or export_htmlns
                $list = $this->collectPagesOfNS();
            } else {
                //export is not from bookcreator
                return;
            }

            //remove default export version of current page
            $event->data['output'] = '';

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
            $event->data['output'] .= p_cached_output(wikiFN($page), $event->data['mode'], $page);
        }
        $ID = $keep;
    }


    /**
     * @return array list of pages from ns after filtering
     * @throws Exception
     */
    private function collectPagesOfNS(): array
    {
        global $INPUT, $conf;
        //check input for ns
        $pdfnamespace = cleanID($INPUT->str('book_ns'));
        if (!@is_dir(dirname(wikiFN($pdfnamespace . ':dummy')))) {
            throw new Exception($this->getLang('needns'));
        }

        //sort order
        $order = $INPUT->str('book_order', 'natural', true);
        $sortoptions = array('pagename', 'date', 'natural');
        if (!in_array($order, $sortoptions)) {
            $order = 'natural';
        }

        //search depth
        $depth = $INPUT->int('book_nsdepth', 0);
        if ($depth < 0) {
            $depth = 0;
        }

        //page search
        $result = array();
        $opts = array('depth' => $depth); //recursive all levels
        $dir = utf8_encodeFN(str_replace(':', '/', $pdfnamespace));
        search($result, $conf['datadir'], 'search_allpages', $opts, $dir);

        // exclude ids
        $excludes = $INPUT->arr('excludes');
        if (!empty($excludes)) {
            $result = array_filter($result, function ($item) use ($excludes) {
                return !in_array($item['id'], $excludes);
            });
        }
        // exclude namespaces
        $excludesns = $INPUT->arr('excludesns');
        if (!empty($excludesns)) {
            $result = array_filter($result, function ($item) use ($excludesns) {
                foreach ($excludesns as $ns) {
                    if (strpos($item['id'], $ns . ':') === 0) return false;
                }
                return true;
            });
        }

        //sorting
        if (count($result) > 0) {
            if ($order == 'date') {
                usort($result, array($this, '_datesort'));
            } elseif ($order == 'pagename' || $order == 'natural') {
                usort($result, array($this, '_pagenamesort'));
            }
        }

        $list = [];
        foreach ($result as $item) {
            $list[] = $item['id'];
        }

        if ($pdfnamespace !== '') {
            if (!in_array($pdfnamespace . ':' . $conf['start'], $list, true)) {
                if (file_exists(wikiFN(rtrim($pdfnamespace, ':')))) {
                    array_unshift($list, rtrim($pdfnamespace, ':'));
                }
            }
        }
        return $list;
    }
}
