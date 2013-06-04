<?php
/**
 * BookCreator plugin : Create a book from some pages.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Luigi Micco <l.micco@tiscali.it>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC.'inc/search.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_bookcreator extends DokuWiki_Syntax_Plugin {
    
    var $usercansave;

    function getInfo() {
      return array(
              'author' => 'Luigi micco',
              'email'  => 'l.micco@tiscali.it',
              'date'   => '2010-04-19',
              'name'   => 'bookcreator Plugin (syntax component)',
              'desc'   => 'Allow to make a book (PDF or text) from selected pages',
              'url'    => 'http://www.bitlibero.com/dokuwiki/bookcreator-19.04.2010.zip',
              );
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~\w*?BOOK.*?~~', $mode, 'plugin_bookcreator');
    }

    //function getType() { return 'substition'; }
    function getType(){ return 'container';}
    function getPType(){ return 'block';}

    /**
     * Where to sort in?
     */
    function getSort(){
        return 190; 
    }


    function handle($match, $state, $pos, &$handler) {
      
      $match = substr($match, 2, -2); // strip markup
      if (substr($match, 0, 7) == 'ARCHIVE') $type = 'archive';
      else $type = 'book';

      $num = 10;
      $order = 'date';
      if ($type == 'archive') {
        list($junk, $params) = explode(':', $match, 2);
        list($param1, $param2) = explode('&', $params, 2);
        
        if (is_numeric($param1)) {
          $num = $param1;
          if (is_string($param2)) $order = $param2;
        } elseif (is_string($param1)) {
          $order = $param1;
          if (is_numeric($param2)) $num = $param2;
        }

      }

      return array($type, $num, $order);
    
    }

    function render($mode, &$renderer, $data) {
      global $ID;
      global $conf;
      global $INFO;
      global $lang;

      list($type, $num, $order) = $data;

      if ($type == "book") {
        $renderer->info['cache'] = false;
        if (($mode == 'text') && (isset($_GET['do']) && ($_GET['do'] == 'export_text') )) {
          $mode = 'xhtml';
        }

        if ($mode == 'xhtml') {

          // verifica che se l'utente può salvare/eliminare le selezioni
          $this->usercansave = (auth_quickaclcheck($this->getConf('save_namespace').':*') >= AUTH_CREATE);
          // verifica che se l'utente può salvare/eliminare le selezioni

          if ($this->usercansave) {        
            if ((isset($_POST['task'])) && ($_POST['task'] == "save")) {
              checkSecurityToken();
              if (isset($_COOKIE['list-pagelist'])) {
                if (isset($_POST['bookcreator_title'])) {
                  $list = explode("|", $_COOKIE['list-pagelist']);
                  $content = "====== ".$_POST['bookcreator_title']." ======".DOKU_LF;    
                  for ($n = 0; $n < count($list); $n++) {            
                    $page = $list[$n];
                    $content .= "  * [[:$page]]".DOKU_LF;    
                  }
                  saveWikiText($this->getConf('save_namespace').":".$_POST['bookcreator_title'],$content, "selection created");
                  msg($this->getLang('bookcreator_saved').": ".$this->getConf('save_namespace').":".$_POST['bookcreator_title']);
                } else {
                  msg($this->getLang('bookcreator_needtitle'));
                }
              } else {
                msg($this->getLang('bookcreator_empty'));
              }
            } elseif ((isset($_POST['task'])) && ($_POST['task'] == "del")) {
              saveWikiText($this->getConf('save_namespace').":".$_POST['page'],'', "selection removed");
              msg($this->getLang('bookcreator_deleted').": ".$this->getConf('save_namespace').":".$_POST['page']);
            }
          }
        
          if ((isset($_GET['do'])) || (isset($_GET['mddo']))) {
            if (($_GET['do'] == 'export_html') || ($_GET['do'] == 'export_text')) {
              if (isset($_COOKIE['list-pagelist'])) {
                $renderer->doc = '';
                $list = explode("|", $_COOKIE['list-pagelist']);
              }
              
              $render_mode = 'xhtml';
              $lf_subst = '';
              if ($_GET['do'] == 'export_text') {
                $render_mode = 'text';
                $lf_subst = '<br>';
              }
              
              for ($n = 0; $n < count($list); $n++) {            
                $page = $list[$n];
                $renderer->doc .= str_replace(DOKU_LF,$lf_subst,p_cached_output(wikiFN($page),$render_mode)); //p_wiki_xhtml($page,$REV,false);
              }  
            
            } 
          } else {
            $renderer->info['cache'] = FALSE;
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/core.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/events.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/css.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/coordinates.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/drag.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/dragsort.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/cookies.js"></script>';
            $renderer->doc .= '<script language="JavaScript" type="text/javascript" src="'.DOKU_URL.'lib/plugins/bookcreator/sorter/more.js"></script>';


            if (isset($_COOKIE['bookcreator']) || ((isset($_POST['task'])) && ($_POST['task'] == "read")) )  {
              $list = array();
              $i = 0;
              
              // c'è una selezione salvata da recuperare
              if ((isset($_POST['task'])) && ($_POST['task'] == "read")) {
                checkSecurityToken();
                $renderer->doc .= "
  <script type='text/javascript'><!--//--><![CDATA[//><!-- 
  book_removeAllPages('bookcreator');
  //--><!]]></script>";
                $select= rawWiki($this->getConf('save_namespace').":".$_POST['page']);
                $lines = explode("\n", $select);
                $nr = count($lines);
                for($n=0; $n<$nr; $n++) {
                  if (trim($lines[$n]) == '') continue;
                  if ((($n > 0) && substr($lines[$n], 0, 7) != "  * [[:") ) continue;
                  
                  if ($n === 0){
                    $lines[$n] = str_replace("====== ",'',$lines[$n]);
                    $lines[$n] = str_replace(" ======",'',$lines[$n]);
                    $title = $lines[$i];
                  } else {
                    $lines[$n] = str_replace("  * [[:",'',$lines[$n]);
                    $lines[$n] = str_replace("]]",'',$lines[$n]);
                    $list[$n] = $lines[$n];
                    $renderer->doc .= '
  <script type="text/javascript"><!--//--><![CDATA[//><!-- 
  book_changePage(\'bookcreator['.$list[$n].']\', 1, new Date(\'July 21, 2099 00:00:00\'), \'/\');
  //--><!]]></script>';
                    $i++;
                  }
                }
              // oppure quella appena selezionata
              } elseif (isset($_COOKIE['bookcreator']) )  {
                $fav=$_COOKIE['bookcreator'];

                //Se non ci sono pagine già inserite
                if ( ($fav == "") || ( count($fav) == 0) ) {
                  $renderer->doc .= $this->getLang('bookcreator_empty');
                  return;
                }
              
                foreach ($fav as $page => $cpt) {
                  list($cpt, $date) = explode(";", $cpt);
                  if ($cpt<1) continue;
                  $i++;
                  $list[$i] = $page;
                }
              }
              
              $renderer->doc .= "<table width='100%' border='0' ><tr>";
              $renderer->doc .= "<td width='60%' valign='top'>";

              // Pagine selezionate
              for ($n = 1; $n <= $i; $n++) {            
                $page = $list[$n];
                if ($n == 1) {
                  $renderer->header($this->getLang('bookcreator_toprint'), 2, 0);
                  $renderer->doc.= '<ul id="pagelist" class="boxes">';
                }
                $lien = $this->createLink($page);
                $renderer->doc.= '	<li itemID="'.$page.'">';
                $renderer->doc .= ' <a href="javascript:book_changePage(\'bookcreator['.$page.']\', 0, new Date(\'July 21, 2099 00:00:00\'), \'/\'); book_recharge();"><img src="'.DOKU_URL.'lib/plugins/bookcreator/images/remove.png" title="'.$this->getLang('bookcreator_remove').'" border="0" style="vertical-align:middle;" name="ctrl" /></a>&nbsp;&nbsp;';
                $renderer->doc .= $lien;
                $renderer->doc .= '</li>';
                if ( $n==$i ) {
                  $renderer->doc .= '</ul>'; 
                  $renderer->doc .= "<br />";
                }
              }
              // Pagine selezionate

              // Pagine escluse dal libro
              if (isset($fav))   {
                $i=0;
                foreach ($fav as $page => $cpt) {
                  list($cpt, $date) = explode(";", $cpt);
                  if ($cpt==0) {
                    if (!$i) {
                      $renderer->header($this->getLang('bookcreator_removed'), 2, 0);
                      $renderer->listu_open();
                    }  
                    $lien = $this->createLink($page);
                    $i++;
                    $renderer->doc.= "<div id=\"ex__$page\">";
                    $renderer->listitem_open(1);
                    $renderer->doc .= '<a href="javascript:book_changePage(\'bookcreator['.$page.']\', 1, new Date(\'July 21, 2099 00:00:00\'), \'/\');  book_recharge();"><img src="'.DOKU_URL.'lib/plugins/bookcreator/images/include.png" title="'.$this->getLang('bookcreator_include').'" border="0" style="vertical-align:middle;" name="ctrl" /></a> ';
                    $renderer->doc .= $lien;
                    $renderer->doc .= "</div>"; 
                    $renderer->listitem_close();
                  }
                }
                if ($i) $renderer->listu_close();
              }

              // azzera selezione
              $renderer->doc .= "<div align='center'>"; 
              $onclick = "javascript:if(confirm('".$this->getLang('bookcreator_reserconfirm')."')) {book_removeAllPages('bookcreator'); document.reset.submit();}";
              $renderer->doc .= '<form name="reset" class="button" method="get" action="'.wl($ID).'">';
              $renderer->doc .= "<input type='button' value='".$this->getLang('bookcreator_reset')."' class='button' onclick=\"".$onclick."\">";
              $renderer->doc .= "<input type='hidden' name='id' value='$ID'/>";
              $renderer->doc .= formSecurityToken(false);
              $renderer->doc .= '</form>';
              $renderer->doc .= '</div>';
              // azzera selezione

              $renderer->doc .= "</td>";
              $renderer->doc .= "<td width='40%' valign='top' >";

              $renderer->doc .= "<div align='center'>"; 
              
              //Esportazione PDF
              $renderer->doc .= '<form class="button" method="get" action="'.wl($ID).'" accept-charset="'.$lang['encoding'].'">';
              $renderer->doc .= "<fieldset style=\"text-align:left;\"><legend><b>".$this->getLang('bookcreator_export')."</b></legend>";
              $renderer->doc .= $this->getLang('bookcreator_title')." ";
              $renderer->doc .= '<input type="text" class="edit" value="'.$title.'" name="pdfbook_title" size="40" />';
              $renderer->doc .= '<select name="do" size="1">';
              $renderer->doc .= '<option value="export_html" selected="selected">'.$this->getLang('bookcreator_exportprint').'</option>';

              if (file_exists(DOKU_PLUGIN."text/renderer.php") && !plugin_isdisabled("text")) {
                $renderer->doc .= '<option value="export_text">'.$this->getLang('bookcreator_exporttext').'</option>';
              }

              if (file_exists(DOKU_PLUGIN."dw2pdf/action.php") && !plugin_isdisabled("dw2pdf")) {
                $renderer->doc .= '<option value="export_pdfbook" selected="selected">'.$this->getLang('bookcreator_exportpdf').'</option>';
              }
              
              $renderer->doc .= '</select>';
              $renderer->doc .= '<input type="submit" value="'.$this->getLang('bookcreator_create').'" class="button"/>
                  <input type="hidden" name="id" value="'.$ID.'" />';
              $renderer->doc .= '</fieldset>';
              $renderer->doc .= formSecurityToken(false);
              $renderer->doc .= '</form>';
              //Esportazione PDF
              
              if ($this->usercansave) {
                //Salva selezione
                $renderer->doc .= '<form class="button" method="post" action="'.wl($ID).'" accept-charset="'.$lang['encoding'].'">';
                $renderer->doc .= "<fieldset style=\"text-align:left;\"><legend><b>".$this->getLang('bookcreator_saveselection')."</b></legend>";
                $renderer->doc .= '<input type="text" class="edit" value="'.$title.'" name="bookcreator_title" />';
                $renderer->doc .= '<input type="submit" value="'.$this->getLang('bookcreator_save').'" class="button"/>';
                $renderer->doc .= '<input type="hidden" name="task" value="save" />
                    <input type="hidden" name="id" value="'.$ID.'" />';
                $renderer->doc .= '</fieldset>';
                $renderer->doc .= formSecurityToken(false);
                $renderer->doc .= '</form>';
                //Salva selezione
              }
              
              $renderer->doc .= '</div>';

              $renderer->doc .= "</tr></td>";
              $renderer->doc .= "</table>";

            } else {
              $renderer->doc .= $this->getLang('bookcreator_nocookies');
            }
              
            // genera la lista delle selezioni salvate
            $result = $this->_getlist($order);
            if (sizeof($result) > 0) {
              $renderer->doc .= '<form class="button" id="bookcreator__selections__list" name="bookcreator__selections__list" method="post" action="'.wl($ID).'">';
              $renderer->doc .= "<fieldset style=\"text-align:left;\"><legend><b>".$this->getLang('bookcreator_listselections')."</b></legend>";
              $this->_showlist($renderer, $result, true, true);
              $renderer->doc .= "<input type='hidden' name='task' value=''/>";
              $renderer->doc .= "<input type='hidden' name='page' value=''/>";
              $renderer->doc .= "<input type='hidden' name='id' value='$ID'/>";
              $renderer->doc .= formSecurityToken(false);
              $renderer->doc .= '</fieldset>';
              $renderer->doc .= '</form>';
            }  
            // genera la lista delle selezioni salvate
          }  
        }
        return false;
      } else {
      
        if ($mode == 'xhtml') {
          // genera la lista delle selezioni salvate
          $result = $this->_getlist($order, $num);
          if (sizeof($result) > 0) {
            $renderer->doc .= '<form class="button" id="bookcreator__selections__list" name="bookcreator__selections__list" method="post" action="'.wl($this->getConf('book_page')).'">';
            $this->_showlist($renderer, $result);
            $renderer->doc .= "<input type='hidden' name='task' value=''/>";
            $renderer->doc .= "<input type='hidden' name='page' value=''/>";
            $renderer->doc .= "<input type='hidden' name='id' value='".$this->getConf('book_page')."'/>";
            $renderer->doc .= formSecurityToken(false);
            $renderer->doc .= '</form>';
          }  
          // genera la lista delle selezioni salvate
        }
        return false;

      }
    }

    /**
     * usort callback to sort by file lastmodified time
     */
    function _datesort($a,$b){
        if($b['rev'] < $a['rev']) return -1;
        if($b['rev'] > $a['rev']) return 1;
        return strcmp($b['id'],$a['id']);
    }

    /**
     * usort callback to sort by file title
     */
    function _titlesort($a,$b){
        if($a['id'] <= $b['id']) return -1;
        if($a['id'] > $b['id']) return 1;
    }

    function _getlist($order, $limit=0) {
      global $conf;
      
      $result = array();
      $opts = array('depth' => 1,'listfiles' => true,'listdirs' => false,'skipacl' => false, 'pagesonly' => true,'meta' => true);
      $tt = str_replace(':','/',$this->getConf('save_namespace'));
      search(&$result,$conf['datadir'],'search_allpages',$opts,$tt);

      if (sizeof($result) > 0) {
      
        if($order == 'date'){
          usort($result,array($this,'_datesort'));
        }elseif($order == 'title'){
          usort($result,array($this,'_titlesort'));
        }
        
        if ($limit != 0) $result = array_slice($result, 0, $limit);
      }
      return $result;
    }

    
    function _showlist(&$renderer, $result, $showbin = false, $showtime = false) {

      $renderer->doc .= '
<script type="text/javascript"><!--//--><![CDATA[//><!-- 
function actionList(action,page) {
  var msg = "";
  var flag = true;
  var flagconfirm = true;
  if (action == "del") {
    msg = "'.$this->getLang('bookcreator_confirmdel').'";
  } else {
    if (book_countPages("bookcreator") == 0) {
      flag = false;
    }  
    msg = "'.$this->getLang('bookcreator_confirmload').'";
  }
  
  if (flag) flagconfirm = confirm(msg);
  if(flagconfirm) {
    document.bookcreator__selections__list.task.value=action;
    document.bookcreator__selections__list.page.value=page;
    document.bookcreator__selections__list.submit();
    return true;
  }  
}
//--><!]]></script>';
    
    
      $renderer->listu_open();
      foreach($result as $item){
        $itemtitle = p_get_first_heading($item['id']);
        $nons = noNS($item['id']);
        $renderer->listitem_open(1);
        if (($showbin) && (auth_quickaclcheck($item['id']) >= AUTH_DELETE)) {
          $renderer->doc .= "<a href=\"javascript:actionList('del','".$nons."');\" ><img src='".DOKU_URL."lib/plugins/bookcreator/images/remove.png' title='".$this->getLang('bookcreator_delselection')."' border='0' style='vertical-align:middle;' /></a> ";
        }  
        $renderer->doc .= "<a href='".wl($this->getConf('save_namespace').":".$nons)."'><img src='".DOKU_URL."lib/plugins/bookcreator/images/include.png' title='".$this->getLang('bookcreator_showpage')."' border='0' style='vertical-align:middle;' /></a> ";
        $renderer->doc .= "<a href=\"javascript:actionList('read','".$nons."');\" title='".$this->getLang('bookcreator_loadselection')."'>".$itemtitle."</a>";
        if ($showtime) $renderer->cdata(' ('.dformat($item['mtime']).')');
        $renderer->listitem_close();
      }
      $renderer->listu_close();
    }


    function createLink($page, $title="") {
      $pos = strrpos(utf8_decode($page), ':');
      $pageName = p_get_first_heading($page);
      if($pageName == NULL) {
        if($pos != FALSE) {
          $pageName = utf8_substr($page, $pos+1, utf8_strlen($page));
        } else {
          $pageName = $page;
        }
        $pageName = str_replace('_', ' ', $pageName);
      }    
      return "<a href='".wl($page, false, true, "&")."'>".$pageName."</a>";
   }
   
}
?>
