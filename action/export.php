<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gerrit Uitslag <klapinklapin@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

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
        $controller->register_hook('ACTION_EXPORT_POSTPROCESS', 'BEFORE', $this, '_exportOnlySelectedpages');
    }

    /**
     * The selected pages for the onscreen rendering are rendered plus the remaining part of the bookmanager page
     * (i.e. the wiki text below the bookmanager). That remaining part has to be removed.
     *
     * @param Doku_Event $event
     */
    public function _exportOnlySelectedpages(Doku_Event $event) {

        $pos = strrpos($event->data['output'],'<!-- END EXPORTED PAGES -->');
        if($pos === false) return;

        $event->data['output'] = substr($event->data['output'], 0, $pos);
    }
}
