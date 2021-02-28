<?php
/**
 * BookCreator plugin : Create a book from some pages.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

class syntax_plugin_bookcreator_exportsaved extends DokuWiki_Syntax_Plugin
{
    /**
     * @param string $mode
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('~~EXPORT.*?~~', $mode, 'plugin_bookcreator_exportsaved');
    }

    /**
     * Syntax Type
     *
     * @return string
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 190;
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  bool|array Return an array with all data you want to use in render, false don't add an instruction
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = substr($match, 2, -2); // strip markup
        list($type, $savedSelectionPage) = explode(':', $match, 2);
        //type: EXPORT...
        $type = strtolower(substr($type, 6));
        $exporturl = [
            'odt' => '?do=export_odtbook',
            'text' => '?do=export_text',
            'html' => '?do=export_html',
            'pdf' => '?do=export_pdfbook'
        ];
        if (!in_array($type, array_keys($exporturl))) {
            $type = 'pdf';
        }
        $link = $exporturl[$type];

        list($savedSelectionPage, $linktitle) = explode('|', $savedSelectionPage, 2);
        list($savedSelectionPage, $extraParameters) = explode('&', $savedSelectionPage, 2);

        $ns = $this->getConf('save_namespace');
        $savedSelectionPageid = cleanID($ns . ":" . $savedSelectionPage);
        $savedSelectionPageid = substr($savedSelectionPageid, strlen($ns) + 1);

        $link .= '&savedselection=' . $savedSelectionPageid . ($extraParameters ? '&' . $extraParameters : '');
        if ($linktitle) {
            $title = $linktitle;
        } else {
            $title = sprintf($this->getLang('exportselection'), $savedSelectionPage, $type);
        }

        return [
            'link' => $link,
            'title' => $title,
            'type' => $type
        ];
    }


    /**
     * include a link to the requested export of the saved selection
     *
     * @param string $format render mode e.g. text, xhtml, meta,...
     * @param Doku_Renderer &$renderer
     * @param array $data return of handle()
     * @return bool
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format == 'xhtml' && !is_a($renderer, 'renderer_plugin_dw2pdf')) {
            /** @var Doku_Renderer_xhtml $renderer */
            $link = $renderer->internallink($data['link'], $data['title'], null, true);

            // add class for adding file icons to the link
            $pos = strpos($link, 'class="');
            $link = substr_replace($link, 'mediafile mf_' . $data['type'] . ' ', $pos + 7, 0);
            $renderer->doc .= $link;
            return true;
        }
        return false;
    }
}
