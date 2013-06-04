<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_bookcreator extends DokuWiki_Action_Plugin {

    var $temp;
    var $num;
    
    /**
     * return some info
     */
    function getInfo() {
      return array(
              'author' => 'Luigi micco',
              'email'  => 'l.micco@tiscali.it',
              'date'   => '2010-04-19',
              'name'   => 'bookcreator Plugin (action component)',
              'desc'   => 'Allow to make a book (PDF or text) from selected pages',
              'url'    => 'http://www.bitlibero.com/dokuwiki/bookcreator-19.04.2010.zip',
              );
    }

    /**
     * Constructor
     */
    function action_plugin_bookcreator() {
      $this->setupLocale();
    }
                              
    /**
     * register the eventhandlers
     */
    function register(&$contr) {
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_handle_tpl_act', array());
        $contr->register_hook('TPL_ACT_RENDER','BEFORE',$this,'bookbar',array());
    }
  
    function _handle_tpl_act(&$event, $param) {
    
			global $conf, $ID; 

      $i = 0;
      if (isset($_COOKIE['bookcreator'])) {
        $fav=$_COOKIE['bookcreator'];

        list($cpt, $date)=explode(";", $fav[$ID]);

        if ($cpt==0 || $cpt=="" ) { 
          $cpt = 0;
        } else {
          $cpt=1;
        }  
        
        foreach ($fav as $value) {
          if ($value<1) continue;
          $i = $i + 1;
        }
      }
      else $cpt=0;
      $this->temp = $cpt;
      $this->num = $i;

			if($event->data != 'addtobook') return;

      $this->diff = 0;
      if (isset($_COOKIE['bookcreator'])) {
        $fav=$_COOKIE['bookcreator'];

        list($cpt, $date)=explode(";", $fav[$ID]);

        if ($cpt==0 || $cpt=="" ) { 
          $cpt = 1;
          $this->num = $this->num +1 ;
          $msg = $this->getLang('bookcreator_pageadded');
        } else {
          $cpt=0;
          $this->num = $this->num -1;
          $msg = $this->getLang('bookcreator_pageremoved');
        }  
      } else {
        $cpt=1;
        $this->num = $this->num +1 ;
        $msg = $this->getLang('bookcreator_pageadded');
      }  
      if ($this->getConf('toolbar') == "never") msg($msg);
      
      $this->temp = $cpt;
      setCookie("bookcreator[".$ID."]","$cpt;".time(), time()+60*60*24*7, '/');
      $event->data = 'show';
    }



    /**
     *  Prints bookbar
     *  @author     Luigi Micco <l.micco@tiscali.it>
     */
    function bookbar(&$event, $param) {
      global $ID;
      global $conf;

      if ($event->data != 'show') return; // nothing to do for us

      /*
      *  assume that page does not exists
      */
      $exists = false;
      $id = $ID;
      resolve_pageid('',$id,$exists);

      /*
      *  show or not the toolbar ?
      */
      if (($this->getConf('toolbar') == "never") || (($this->getConf('toolbar') == "noempty") && ($this->num == 0) ) )
          return;
          
      /*
      *  find skip pages
      */
      $sp = join("|",explode(",",preg_quote($this->getConf('skip_ids'))));
      if (!$exists || preg_match("/$sp/i",$ID)) 
          return;


      $cpt = $this->temp;

      echo '<script type="text/javascript"><!--//--><![CDATA[//><!--
function book_revertLink(id) {
  if (document && $(id)) 
    if ($(id).style.display=="block") 
      $(id).style.display="none";
    else
      $(id).style.display="block";
}

function book_updateSelection(id, value) {
  book_changePage("bookcreator["+id+"]", value, new Date("July 21, 2099 00:00:00"), "/");
  book_revertLink("bookcreator__remove");
  book_revertLink("bookcreator__add");
  $("bookcreator__pages").innerHTML= book_countPages("bookcreator");
}
//--><!]]></script>';

      echo "<div class='bookcreator__' style='vertical-align:bottom;'>";
      echo "<div class='bookcreator__panel' id='bookcreator__add' style='"; // '>";
      if ($cpt==0 || $cpt=="" ) { 
        echo "display:block;'>";
      } else {
        echo "display:none;'>";
      }  
      echo '<b>'.$this->getLang('bookcreator_toolbar').'</b><br><a href="javascript:book_updateSelection(\''.$ID.'\', 1); ">';
      echo "<img src='".DOKU_URL."lib/plugins/bookcreator/images/add.png'>&nbsp;".$this->getLang('bookcreator_addpage')."</a>";
      echo "</div>";
      
      echo "<div class='bookcreator__panel' id='bookcreator__remove' style='";
      if ($cpt==1) { 
        echo "display:block;'>";
      } else {
        echo "display:none;'>";
      }  
      echo '<b>'.$this->getLang('bookcreator_toolbar').'</b><br><a href="javascript:book_updateSelection(\''.$ID.'\', 0); ">';
      echo "<img src='".DOKU_URL."lib/plugins/bookcreator/images/del.png'>&nbsp;".$this->getLang('bookcreator_removepage')."</a>&nbsp;";
      echo "</div>";
      
      echo "<div class='bookcreator__panel' >";
      echo "<br><a href='".wl($this->getConf('book_page'))."'><img src='".DOKU_URL."lib/plugins/bookcreator/images/smallbook.png'>&nbsp;".$this->getLang('bookcreator_showbook')." (";
      echo "<span id='bookcreator__pages'>";
      echo  $this->num;
      echo "</span> ".$this->getLang('bookcreator_pages').")";
      echo "</a></div>";
      echo "<div class='bookcreator__panel' style='float:right;'>";
      echo "<a href='".wl($this->getConf('help_page'))."'><img src='".DOKU_URL."lib/plugins/bookcreator/images/help.png'>&nbsp;".$this->getLang('bookcreator_help')."</a>";
      echo "</div>";
      echo "</div>";
     
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
