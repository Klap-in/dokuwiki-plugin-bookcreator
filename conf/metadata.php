<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the for the bookcreator Plugin
 *
 * @author  Luigi Micco <l.micco@tiscali.it>
 */


$meta['toolbar'] = array('multichoice', '_choices' => array('never', 'always', 'noempty'));


$meta['book_page'] = array('string');
$meta['help_page'] = array('string');

$meta['save_namespace'] = array('string');

$meta['skip_ids'] = array('multicheckbox',
                          '_choices' => array( 'sidebar'
                                              ,'user'
                                              ,'group'
                                              ,'playground'
                                              ,'wiki:syntax'
                                              ,'wiki:ebook'
                                              )
                           ,'_combine' => array());

//Setup VIM: ex: et ts=2 enc=utf-8 :
