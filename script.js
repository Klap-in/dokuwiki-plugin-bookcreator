/* DOKUWIKI:include script/jquery.fileDownload.js */

/**
 * Storage object for an array with a selection of pages
 *
 * @param key
 * @constructor
 */
function Storage(key) {
    this.localStorageKey = key;
    this._storage = [];
}
/**
 * Is pageid in stored selection
 *
 * @param {string} pageid
 * @returns {boolean}
 */
Storage.prototype.isSelected = function(pageid) {
    return this._storage.indexOf(pageid) != -1;
};

/**
 * Insert pageid at given position
 *
 * @param {string} pageid
 * @param {Number} position
 */
Storage.prototype.addPage = function(pageid, position) {
    if(typeof position === 'undefined') {
        this._storage.push(pageid); //add to the end
    } else {
        this._storage.splice(position, 0, pageid);
    }

    this._save();
};

/**
 * Move pageid inside selection to given position
 *
 * @param {string} pageid
 * @param {Number} position
 */
Storage.prototype.movePage = function(pageid, position) {
    if(!this._deletePage(pageid).length) return;

    this.addPage(pageid, position);
};

/**
 * Delete pageid from selection
 * @param pageid
 */
Storage.prototype.deletePage = function(pageid) {
    this._deletePage(pageid);
    this._save();
};

/**
 * Delete given pageid from storage
 *
 * @param pageid
 * @returns {Array} empty or with deleted entry
 * @private
 */
Storage.prototype._deletePage = function(pageid) {
    var pos = this._storage.indexOf(pageid);
    if(pos == -1) return [];

    return this._storage.splice(pos, 1);
};

/**
 * Empty the store
 */
Storage.prototype.clearAll = function() {
    this._storage = [];
    this._save();
};

/**
 * Returns array with pageids of selected books
 *
 * @returns {Array}
 */
Storage.prototype.getSelection = function() {
    return this._storage;
};

/**
 * Set a new selection at once and save
 *
 * @param {Array} selection
 */
Storage.prototype.setSelection = function(selection) {
    this._storage = selection;
    this._save();
};

/**
 * Count number of selected pages
 *
 * @returns {Number}
 */
Storage.prototype.count = function() {
    return this._storage.length;
};

/**
 * Save current selection in browser's localStorage
 *
 * @private
 */
Storage.prototype._save = function() {
    window.localStorage.setItem(this.localStorageKey, JSON.stringify(this._storage));
};

/**
 * Load selection from browser's localStorage
 */
Storage.prototype.load = function() {
    var source = window.localStorage.getItem(this.localStorageKey);

    try {
        this._storage = JSON.parse(source) || [];
    } catch (E) {
        this._storage = [];
    }
};

/**
 * Storage object for an property in browser's localStorage
 *
 * @param key
 * @constructor
 */
function CachedProperty(key) {
    this.localStorageKey = key;
}

CachedProperty.prototype.set = function(value) {
    window.localStorage.setItem(this.localStorageKey, value);
};

CachedProperty.prototype.get = function() {
    return window.localStorage.getItem(this.localStorageKey);
};

/**
 * Performs bookcreator functionality at wiki pages
 */
var Bookcreator = {


    selectedpages: new Storage('bookcreator_selectedpages'),
    isCurrentPageSelected: false,

    /**
     * Handle click at page add/remove buttons
     */
    clickAddRemoveButton: function(e) {
        e.preventDefault();

        Bookcreator.toggleSelectionCurrentPage();
        Bookcreator.updatePage();
    },

    /**
     * Sets up a storage change observer
     */
    setupUpdateObserver: function() {
        jQuery(window).on('storage', function() {
            Bookcreator.init();
            Bookcreator.updatePage();
        });

        //// handle cached navigation
        //if ('addEventListener' in window) {
        //    window.addEventListener('pageshow', function(event) {
        //        if (event.persisted) {
        //            Bookcreator.load();
        //        }
        //    }, false);
        //}
    },

    /**
     * Initiate storage
     */
    init: function() {
        this.selectedpages.load();
        this.isCurrentPageSelected = this.selectedpages.isSelected(JSINFO.id);
    },

    /**
     * Delete or add current page from selection
     */
    toggleSelectionCurrentPage: function() {
        if(this.isCurrentPageSelected) {
            this.selectedpages.deletePage(JSINFO.id);
        } else {
            this.selectedpages.addPage(JSINFO.id);
        }
        this.isCurrentPageSelected = this.selectedpages.isSelected(JSINFO.id);
    },

    /**
     * Update the interface to current selection
     */
    updatePage: function() {

        var $addtobookBtn = jQuery('.plugin_bookcreator_addtobook'),
            $bookbar = jQuery('.bookcreator__bookbar');

        //pagetool add/remove button
        $addtobookBtn.css( "display", "block");
        if ($addtobookBtn.length) { //exists the addtobook link
            var text = LANG.plugins.bookcreator['btn_' + (this.isCurrentPageSelected ? 'remove' : 'add') + 'tobook'];

            $addtobookBtn
                .toggleClass('remove', this.isCurrentPageSelected)
                .attr('title', text)
                .children('span').html(text);
        }

        //bookbar with add/remove button
        if(this.isBookbarVisible()) {
            jQuery("#bookcreator__add").toggle(!this.isCurrentPageSelected);
            jQuery("#bookcreator__remove").toggle(this.isCurrentPageSelected);

            jQuery("#bookcreator__pages").html(this.selectedpages.count());
        }
        $bookbar.toggle(this.isBookbarVisible())
    },

    /**
     * Is bookbar visible
     *
     * @returns {boolean}
     */
    isBookbarVisible: function() {

        if(!JSINFO.bookcreator.areToolsVisible) {
            //permissions, skip page
            return false;
        }

        if(JSINFO.bookcreator.showBookbar == 'always'
            || JSINFO.bookcreator.showBookbar == 'noempty' && this.selectedpages.count() > 0) {
            // always show, or noempty and count>0
            return true;
        } else {
            // never show or noempty and count=0
            return false;
        }
    }
};

var BookManager  = {
    cache: {},
    booktitle: new CachedProperty('bookcreator_booktitle'),
    deletedpages: new Storage('bookcreator_deletedpages'),


    init: function() {
        this.deletedpages.load();
        Bookcreator.init();
    },

    setupUpdateObserver: function(){
        jQuery(window).on('storage', function(event) {
            if(event.key == Bookcreator.selectedpages.localStorageKey) {
                BookManager.init();
                BookManager.updateListsFromStorage();
            }
            if(event.key == BookManager.booktitle.localStorageKey) {
                BookManager.fillTitle();
            }
        })
    },

    /**
     * Retrieve missing pages and add to the page cache
     */
    updateListsFromStorage: function() {
        //get selection changes
        var notcachedpages = jQuery(Bookcreator.selectedpages.getSelection()).not(Object.keys(this.cache)).get();
        notcachedpages = notcachedpages.concat(jQuery(BookManager.deletedpages.getSelection()).not(Object.keys(this.cache)).get());

        //add to list at page and to cache
        function processRetrievedPages(pages) {
            if(pages.hasOwnProperty('selection')) {
                jQuery.extend(BookManager.cache, pages.selection);

                BookManager.updateLists();
            }

        }

        //retrieve data
        if(notcachedpages.length > 0) {
            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {
                    call: 'plugin_bookcreator_call',
                    action: 'retrievePageinfo',
                    selection: JSON.stringify(notcachedpages),
                    sectok: jQuery('input[name="sectok"]').val()
                },
                processRetrievedPages,
                'json'
            );
        } else {
            this.updateLists();
        }
    },

    /**
     * Use updated selected pages selection for updating deleted pages selection and gui
     */
    updateLists: function() {
        var $ul_deleted = jQuery('ul.pagelist.deleted'),
            $ul_selected = jQuery('ul.pagelist.selected'),
            deletedpages = BookManager.deletedpages.getSelection(),
            selectedpages = Bookcreator.selectedpages.getSelection();

        BookManager.refillList($ul_selected, selectedpages);

        //deleted pages selection could still contain re-added pages
        var filtereddeletedpages = jQuery(deletedpages).not(selectedpages).get();
        BookManager.deletedpages.setSelection(filtereddeletedpages);

        BookManager.refillList($ul_deleted, filtereddeletedpages);
    },

    /**
     * Empty the list in the gui and fill with pages from the stored selection
     *
     * @param {jQuery} $ul_selection    the unordered list element, to fill with pages
     * @param {Array} selection         array with the pageids of selected or deleted pages
     */
    refillList: function($ul_selection, selection) {
        //just empty
        $ul_selection.empty();

        //recreate li items
        var liopen1 = "<li class='level1' id='pg__",
            liopen2a = "' title='" + LANG.plugins.bookcreator.sortable +"'><a class='action' title='",
            liopen2b = "'></a>&nbsp;&nbsp;<a href='",
            liopen3 = "' title='" + LANG.plugins.bookcreator.showpage + "'>",
            liclose = "</a></li>";

        var actionlbl = $ul_selection.hasClass('selected') ? 'remove' : 'include',
            liopen2 = liopen2a + LANG.plugins.bookcreator[actionlbl] + liopen2b,
            i = 0,
            itemsToInsert = [];

        jQuery.each(selection, function(index, page){
            if(BookManager.cache[page]) {
                itemsToInsert[i++] = liopen1;
                itemsToInsert[i++] = page;
                itemsToInsert[i++] = liopen2;
                itemsToInsert[i++] = BookManager.cache[page][0];
                itemsToInsert[i++] = liopen3;
                itemsToInsert[i++] = BookManager.cache[page][1];
                itemsToInsert[i++] = liclose;
            } else {
                console.log('Not in cache: ' + page);
            }
        });
        //add to list
        $ul_selection.append(itemsToInsert.join(''));

        //update gui
        //jQuery('div.bookcreator__pagelist').find('ul.pagelist.selected,ul.pagelist.deleted').sortable('refresh');
        $ul_selection.sortable('refresh');
    },

    /**
     * Returns pageids from selection which are not in list at page
     *
     * @param {string} selectionname
     * @returns {Array}
     */
    getNotdisplayedPages: function(selectionname) {
        var sortedIDs,
            selection;

        sortedIDs = jQuery('div.bookcreator__pagelist').find('ul.pagelist.'+selectionname).sortable('toArray');
        //remove 'pg__'
        sortedIDs = jQuery.map(sortedIDs, function(id){
            return id.substr(4);
        });

        if(selectionname == 'selected') {
            selection = Bookcreator.selectedpages.getSelection();
        } else {
            selection = BookManager.deletedpages.getSelection();
        }

        return jQuery(selection).not(sortedIDs).get();
    },

    /**
     * Move between selections
     *
     * @param {string} pageid
     * @param {boolean} isAddAction
     * @param {Number} position or skip
     */
    toggleSelectedPage: function (pageid, isAddAction, position) {
        if (isAddAction) {
            Bookcreator.selectedpages.addPage(pageid, position);
            BookManager.deletedpages.deletePage(pageid);
        } else {
            Bookcreator.selectedpages.deletePage(pageid);
            BookManager.deletedpages.addPage(pageid, position);
        }
    },

    /**
     * handler for move buttons on the list items
     */
    movePage: function() {
        var $a = jQuery(this),
            $li = $a.parent(),
            pageid = $li.attr('id').substr(4);

        //true=add to selected list, false=remove from selected list
        var isAddAction = $li.parent().hasClass('deleted');

        //move page to other list
        var listclass = isAddAction ? 'selected' : 'deleted';
        $li.appendTo(jQuery('div.bookcreator__pagelist ul.pagelist.' + listclass));

        BookManager.toggleSelectedPage(pageid, isAddAction);

        //update interface
        $a.attr('title', LANG.plugins.bookcreator[isAddAction ? 'remove' : 'include']);
    },


    /**
     * List item is dropped in other pagelist
     *
     * @param event
     * @param ui
     */
    receivedFromOtherSelection: function (event, ui) {
        var pageid = ui.item.attr('id').substr(4),
            isAddAction = ui.item.parent().hasClass('selected'),
            position = ui.item.index();

        //store new status
        BookManager.toggleSelectedPage(pageid, isAddAction, position);

        //update layout
        ui.item.children('a.action').attr('title', LANG.plugins.bookcreator[isAddAction ? 'remove' : 'include']);
    },

    /**
     * Store start position of moved list item
     *
     * @param event
     * @param ui
     */
    startSort: function(event, ui) {
        ui.item.data("startindex", ui.item.index());
    },

    /**
     * Store whether list item is sorted within list, or from outside
     *
     * @param event
     * @param ui
     */
    updateSort: function(event, ui) {
        isItemSortedWithinList = !ui.sender;
    },

    /**
     * Handle sorting within list
     *
     * @param event
     * @param ui
     */
    stopSort: function(event, ui) {
        var isAddAction = ui.item.parent().hasClass('selected'),
            pageid = ui.item.attr('id').substr(4),
            startindex = ui.item.data("startindex"),
            endindex = ui.item.index();

        if(isItemSortedWithinList) {
            if(startindex != endindex) {
                if(isAddAction) {
                    Bookcreator.selectedpages.movePage(pageid, endindex);
                } else {
                    BookManager.deletedpages.movePage(pageid, endindex);
                }
            }

            isItemSortedWithinList = false;
        }
    },

    /**
     * click handler: Delete all selections of selected and deleted pages
     */
    clearSelections: function() {
        BookManager.deletedpages.clearAll();
        jQuery('ul.pagelist.deleted').empty();

        Bookcreator.selectedpages.clearAll();
        jQuery('ul.pagelist.selected').empty();
    },

    /**
     * click handler: load or delete saved selections
     */
    handleSavedselectionAction: function() {
        var $this = jQuery(this),
            action = ($this.hasClass('delete') ? 'delete' : 'load'),
            pageid = $this.parent().attr('id').substr(5);

        //confirm dialog
        var msg,
            comfirmed = false;
        if (action == "delete") {
            msg = LANG.plugins.bookcreator.confirmdel;
        } else {
            if (Bookcreator.selectedpages.count() == 0) {
                comfirmed = true;
            }
            msg = LANG.plugins.bookcreator.confirmload;
        }
        if (!comfirmed) {
            comfirmed = confirm(msg);
        }

        if (comfirmed) {
            function processResponse(data) {
                //action: loadSavedSelection
                if (data.hasOwnProperty('selection')) {
                    BookManager.clearSelections();
                    Bookcreator.selectedpages.setSelection(data.selection);
                    BookManager.updateListsFromStorage();
                    jQuery('input[name="book_title"]').val(data.title).trigger('change');
                }
                //action: deleteSavedSelection
                if (data.hasOwnProperty('deletedpage')) {
                    jQuery('#sel__' + data.deletedpage).remove();
                }

                var $msg = jQuery('#bookcreator__selections__list').find('.message');
                BookManager.setMessage($msg, data);
            }

            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {
                    call: 'plugin_bookcreator_call',
                    action: (action == 'load' ? 'loadSavedSelection' : 'deleteSavedSelection'),
                    savedselectionname: pageid,
                    sectok: jQuery('input[name="sectok"]').val()
                },
                processResponse,
                'json'
            );
        }
    },

    /**
     * Save selection at a wiki page
     */
    saveSelection: function($this) {
        var $fieldset = $this.parent(),
            $title = $fieldset.find('input[name="bookcreator_title"]'),
            title = $title.val();

        function processResponse(data) {
            if (data.hasOwnProperty('item')) {
                jQuery('#bookcreator__selections__list').find('ul').prepend(data.item);
                $title.val('');
            }

            var $msg = $fieldset.find('.message');
            BookManager.setMessage($msg, data);
        }

        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_bookcreator_call',
                action: 'saveSelection',
                savedselectionname: title,
                selection: JSON.stringify(Bookcreator.selectedpages.getSelection()),
                sectok: jQuery('input[name="sectok"]').val()
            },
            processResponse,
            'json'
        );
    },

    /**
     * Show temporary the succes or error message
     *
     * @param {jQuery} $msg
     * @param {Object} data
     */
    setMessage: function($msg, data) {
        var msg = false,
            state;

        function setMsg($msg, msg, state) {
            $msg.html(msg)
                .toggleClass('error', state == -1)
                .toggleClass('success', state == 1);
        }

        if(data.hasOwnProperty('error')) {
            msg = data.error;
            state = -1;
        } else if(data.hasOwnProperty('success')) {
            msg = data.success;
            state = 1;
        }

        if (msg) {
            setMsg($msg, msg, state);
            setTimeout(function () {
                setMsg($msg, '', 0);
            }, 1000 * 10);
        }
    },

    /**
     *
     */
    fillTitle: function() {
        var title = BookManager.booktitle.get();
        jQuery('input[name="book_title"]').val(title);
    },

    /**
     * Download the requested file
     *
     * @param event
     */
    downloadSelection: function(event) {
        var $this = jQuery(this),
            do_action = $this.find('select[name="do"]').val();

        if(do_action == 'export_html' || do_action == 'export_text') {
            //just extend the form
            $this.append(
                '<input type="hidden" name="selection" value="'
                + BookManager.htmlSpecialCharsEntityEncode(JSON.stringify(Bookcreator.selectedpages.getSelection()))
                + '" />'
            );
        } else {
            //download in background and shows dialog
            var formdata = $this.serializeArray();
            formdata.push({
                name: 'selection',
                value: JSON.stringify(Bookcreator.selectedpages.getSelection())
            });

            var $preparingFileModal = jQuery("#preparing-file-modal");
            $preparingFileModal.dialog({ modal: true });

            jQuery.fileDownload(
                window.location.href,
                {
                    successCallback: function (url) {
                        $preparingFileModal.dialog('close');
                    },
                    failCallback: function (responseHtml, url) {
                        $preparingFileModal.dialog('close');
                        jQuery("#error-modal")
                            .dialog({ modal: true })
                            .find('.downloadresponse').html(responseHtml);
                    },
                    httpMethod: "POST",
                    data: formdata
                }
            );

            event.preventDefault(); //otherwise a normal form submit would occur
        }

    },

    htmlSpecialCharsEntityEncode: function (str) {
        var htmlSpecialCharsRegEx = /[<>&\r\n"']/gm;
        var htmlSpecialCharsPlaceHolders = {
            '<': 'lt;',
            '>': 'gt;',
            '&': 'amp;',
            '\r': "#13;",
            '\n': "#10;",
            '"': 'quot;',
            "'": '#39;' /*single quotes just to be safe, IE8 doesn't support &apos;, so use &#39; instead */
        };
        return str.replace(htmlSpecialCharsRegEx, function(match) {
            return '&' + htmlSpecialCharsPlaceHolders[match];
        });
    }

};


jQuery(function () {
    //Tools for selection a page
    if(JSINFO.bookcreator.areToolsVisible) {
        Bookcreator.init();
        Bookcreator.setupUpdateObserver();

        //bookbar buttons
        jQuery('a.bookcreator__tglPgSelection').click(Bookcreator.clickAddRemoveButton);
        //pagetool button
        jQuery('.plugin_bookcreator_addtobook').click(Bookcreator.clickAddRemoveButton);

        //gui
        Bookcreator.updatePage();
    }

    //bookmanager
    var $pagelist = jQuery('div.bookcreator__pagelist');
    if ($pagelist.length) {
        BookManager.init();

        //buttons at page lists
        $pagelist.find('ul')
            .on('click', 'a.action', BookManager.movePage);

        //sorting and drag-and-drop
        isItemSortedWithinList = false; //use in closure in stop and update handler
        $pagelist.find('ul.pagelist.selected,ul.pagelist.deleted')
            .sortable({
                connectWith: "div.bookcreator__pagelist ul.pagelist",
                receive: BookManager.receivedFromOtherSelection,
                start: BookManager.startSort,
                stop: BookManager.stopSort,
                update: BookManager.updateSort,
                distance: 5
            });

        //clear selection button
        jQuery('form.clearactive button').on('click', function(event) {
            event.preventDefault();
            BookManager.clearSelections();
        });

        BookManager.updateListsFromStorage();
        BookManager.fillTitle();
        BookManager.setupUpdateObserver();

        //save selection
        jQuery('form.saveselection button').on('click', function(event) {
            event.preventDefault();
            BookManager.saveSelection(jQuery(this));
        });

        jQuery('input[name="book_title"]').on('change', function() {
            var value = jQuery(this).val();
            BookManager.booktitle.set(value);
        });

        jQuery('form.downloadselection').on('submit', BookManager.downloadSelection);
    }

    //saved selection list
    jQuery('#bookcreator__selections__list').find('ul')
        .on('click', 'a.action', BookManager.handleSavedselectionAction);
});
