/**
 * The Namespace picker dialog
 * 
 * Based on the code from "The Link Wizard"/linkwiz.js
 * from Andreas Gohr <gohr@cosmocode.de> and Pierre Spring <pierre.spring@caillou.ch>
 * 
 * @author LarsDW223
 */
var bc_nspicker = {
    $picker: null,
    $entry: null,
    result: null,
    timer: null,
    textArea: null,
    selected: null,
    selection: null,

    /**
     * Initialize the bc_nspicker by creating the needed HTML
     * and attaching the eventhandlers
     */
    init: function($editor){
        // position relative to the text area
        var pos = $editor.position();

        // create HTML Structure
        if(bc_nspicker.$picker)
            return;
        bc_nspicker.$picker = jQuery(document.createElement('div'))
               .dialog({
                   autoOpen: false,
                   draggable: true,
                   title: LANG.plugins.bookcreator.namespace_picker,
                   resizable: false
               })
               .html(
                    '<div>'+LANG.plugins.bookcreator.select_namespace+' <input type="text" class="edit" id="bc__nspicker_entry" autocomplete="off" />'+
                        '<input type="button" value="Select" id="bc__nspicker_select">'+
                        '<input type="button" value="Cancel" id="bc__nspicker_cancel">'+
                    '</div>'+
                    '<div id="bc__nspicker_result"></div>'
                    )
               .parent()
               .attr('id','bc__nspicker')
               .css({
                    'position':    'absolute',
                    'top':         (pos.top+20)+'px',
                    'left':        (pos.left+80)+'px'
                   })
               .hide()
               .appendTo('.dokuwiki:first');

        bc_nspicker.textArea = $editor[0];
        bc_nspicker.result = jQuery('#bc__nspicker_result')[0];

        // scrollview correction on arrow up/down gets easier
        jQuery(bc_nspicker.result).css('position', 'relative');

        bc_nspicker.$entry = jQuery('#bc__nspicker_entry');
        if(JSINFO.namespace){
            bc_nspicker.$entry.val(JSINFO.namespace+':');
        }

        // attach event handlers
        jQuery('#bc__nspicker .ui-dialog-titlebar-close').click(bc_nspicker.hide);
        jQuery('#bc__nspicker_select').click(bc_nspicker.selectNamespace);
        jQuery('#bc__nspicker_cancel').click(bc_nspicker.hide);
        bc_nspicker.$entry.keyup(bc_nspicker.onEntry);
        jQuery(bc_nspicker.result).on('click', 'a', bc_nspicker.onResultClick);
    },

    /**
     * handle all keyup events in the entry field
     */
    onEntry: function(e){
        if(e.keyCode == 37 || e.keyCode == 39){ //left/right
            return true; //ignore
        }
        if(e.keyCode == 27){ //Escape
            bc_nspicker.hide();
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        if(e.keyCode == 38){ //Up
            bc_nspicker.select(bc_nspicker.selected -1);
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        if(e.keyCode == 40){ //Down
            bc_nspicker.select(bc_nspicker.selected +1);
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        if(e.keyCode == 13){ //Enter
            if(bc_nspicker.selected > -1){
                var $obj = bc_nspicker.$getResult(bc_nspicker.selected);
                if($obj.length > 0){
                    bc_nspicker.resultClick($obj.find('a')[0]);
                }
            }else if(bc_nspicker.$entry.val()){
                bc_nspicker.insertLink(bc_nspicker.$entry.val());
            }

            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        bc_nspicker.autocomplete();
    },

    /**
     * Get one of the results by index
     *
     * @param   num int result div to return
     * @returns jQuery object
     */
    $getResult: function(num) {
        return jQuery(bc_nspicker.result).find('div').eq(num);
    },

    /**
     * Select the given result
     */
    select: function(num){
        if(num < 0){
            bc_nspicker.deselect();
            return;
        }

        var $obj = bc_nspicker.$getResult(num);
        if ($obj.length === 0) {
            return;
        }

        bc_nspicker.deselect();
        $obj.addClass('selected');

        // make sure the item is viewable in the scroll view

        //getting child position within the parent
        var childPos = $obj.position().top;
        //getting difference between the childs top and parents viewable area
        var yDiff = childPos + $obj.outerHeight() - jQuery(bc_nspicker.result).innerHeight();

        if (childPos < 0) {
            //if childPos is above viewable area (that's why it goes negative)
            jQuery(bc_nspicker.result)[0].scrollTop += childPos;
        } else if(yDiff > 0) {
            // if difference between childs top and parents viewable area is
            // greater than the height of a childDiv
            jQuery(bc_nspicker.result)[0].scrollTop += yDiff;
        }

        bc_nspicker.selected = num;
    },

    /**
     * deselect a result if any is selected
     */
    deselect: function(){
        if(bc_nspicker.selected > -1){
            bc_nspicker.$getResult(bc_nspicker.selected).removeClass('selected');
        }
        bc_nspicker.selected = -1;
    },

    /**
     * Handle clicks in the result set an dispatch them to
     * resultClick()
     */
    onResultClick: function(e){
        if(!jQuery(this).is('a')) {
            return;
        }
        e.stopPropagation();
        e.preventDefault();
        bc_nspicker.resultClick(this);
        return false;
    },

    /**
     * Handles the "click" on a given result anchor
     */
    resultClick: function(a){
        //bc_nspicker.$entry.val(a.title);
        //if(a.title == '' || a.title.substr(a.title.length-1) == ':'){
        if(a.title == '' || a.title.substr(a.title.length-1) == ':'){
            bc_nspicker.$entry.val(a.title);
            bc_nspicker.autocomplete_exec();
        }
        else{
            if (jQuery(a.nextSibling).is('span')) {
                bc_nspicker.insertLink(a.nextSibling.innerHTML);
            }else{
                bc_nspicker.insertLink('');
            }
        }
    },

    /**
     * Start the page/namespace lookup timer
     *
     * Calls autocomplete_exec when the timer runs out
     */
    autocomplete: function(){
        if(bc_nspicker.timer !== null){
            window.clearTimeout(bc_nspicker.timer);
            bc_nspicker.timer = null;
        }

        bc_nspicker.timer = window.setTimeout(bc_nspicker.autocomplete_exec,350);
    },

    /**
     * Executes the AJAX call for the page/namespace lookup
     */
    autocomplete_exec: function(){
        var $res = jQuery(bc_nspicker.result);
        bc_nspicker.deselect();
        $res.html('<img src="'+DOKU_BASE+'lib/images/throbber.gif" alt="" width="16" height="16" />')
            .load(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'linkwiz',
                q: bc_nspicker.$entry.val()
            }
        );
    },

    /**
     * Show the link wizard
     */
    show: function(){
        bc_nspicker.selection  = DWgetSelection(bc_nspicker.textArea);
        bc_nspicker.$picker.show();
        bc_nspicker.$entry.focus();
        bc_nspicker.autocomplete();

        // Move the cursor to the end of the input
        var temp = bc_nspicker.$entry.val();
        bc_nspicker.$entry.val('');
        bc_nspicker.$entry.val(temp);
    },

    /**
     * Hide the link wizard
     */
    hide: function(){
        bc_nspicker.$picker.hide();
        bc_nspicker.textArea.focus();
    },

    /**
     * Toggle the link wizard
     */
    toggle: function(){
        if(bc_nspicker.$picker.css('display') == 'none'){
            bc_nspicker.show();
        }else{
            bc_nspicker.hide();
        }
    },

    /**
     * Select Namespace.
     * Add all pages in the selected Namespace to the book, show
     * window with added pages and then close/hide the Namespace
     * picker.
     */
    selectNamespace: function(){
        var $resultlinks = jQuery('#bc__nspicker_result a');

        content = LANG.plugins.bookcreator.added_pages+"\n\n";

        // Intentionally skip index 0
        pages = 0;
        $resultlinks.each (function(index) {
            if (index > 0) {
                name = jQuery(this).html();

                // Only add pages, not namespaces
                if (name.charAt(name.length-1) != ':') {
                    content += name+"\n";
                    pages++;
                    Bookcreator.selectedpages.addPage(name);
                }
            }
        });
        if (pages == 0) {
            content += LANG.plugins.bookcreator.no_pages_selected+"\n";
        }
        BookManager.updateListsFromStorage();
        window.alert(content);
        bc_nspicker.hide();
    },
};
