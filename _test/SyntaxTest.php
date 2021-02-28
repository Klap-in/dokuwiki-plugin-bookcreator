<?php

declare(strict_types=1);

namespace dokuwiki\plugin\bookcreator\test;

use Doku_Handler;
use DokuWikiTest;
use syntax_plugin_bookcreator_bookmanager;

/**
 * Tests for the syntax component of the bookcreator plugin
 *
 * @group plugin_bookcreator
 * @group plugins
 */
class SyntaxTest extends DokuWikiTest
{

    public function syntaxProvider() {
        return [
            'archive syntax with full normal details' => [ '~~ARCHIVEBOOK:5&date~~', [ 'archive', 5, 'date' ] ],
            'archive syntax with full reversed details' => [ '~~ARCHIVEBOOK:title&7~~', [ 'archive', 7, 'title' ] ],
            'archive syntax with count only' => [ '~~ARCHIVEBOOK:8~~', [ 'archive', 8, 'date' ] ],
            'archive syntax with order only' => [ '~~ARCHIVEBOOK:title~~', [ 'archive', 10, 'title' ] ],
            'archive syntax with defaults' => [ '~~ARCHIVEBOOK~~', [ 'archive', 10, 'date' ] ],
            'archive syntax with full details but wrong ordername' => [ '~~ARCHIVEBOOK:5&unknownorder~~', [ 'archive', 5, 'date' ] ],
            'archive syntax with full reversed details but wrong ordername' => [ '~~ARCHIVEBOOK:unknownorder&7~~', [ 'archive', 7, 'date' ] ],
            'archive syntax with full details, zero count' => [ '~~ARCHIVEBOOK:date&0~~', [ 'archive', 0, 'date' ] ],
            'bookmanager syntax' => [ '~~BOOK~~', [ 'bookmanager', 10, 'date' ] ],
        ];
    }

    /**
     * @dataProvider syntaxProvider
     */
    public function test_ArchiveSyntax($syntax, $expectedData) {
        $dokuHandler = new Doku_Handler();
        $syntaxComponent = new syntax_plugin_bookcreator_bookmanager();

        $result = $syntaxComponent->handle(
            $syntax,
            5,
            1,
            $dokuHandler
        );

        self::assertEquals( $expectedData, $result );
    }
}
