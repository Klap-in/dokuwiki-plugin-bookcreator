<?php

namespace dokuwiki\plugin\bookcreator;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class MenuItem
 *
 * Implements the PDF export button for DokuWiki's menu system
 *
 * @package dokuwiki\plugin\bookcreator
 */
class MenuItem extends AbstractItem {

    /** @var string do action for this plugin */
    protected $type = 'plugin_bookcreator__addtobook';

    /** @var string icon file */
    protected $svg = __DIR__ . '/images/book-plusmin.svg';

    /**
     * MenuItem constructor.
     */
    public function __construct() {
        parent::__construct();
//        global $REV;
//        if($REV) $this->params['rev'] = $REV;
    }

    /**
     * Get label from plugin language file
     *
     * @return string
     */
    public function getLabel() {
        $hlp = plugin_load('action', 'bookcreator_pagetools');
        $jslocal = $hlp->getLang('js');
        return $jslocal['btn_addtobook'];
    }
}
