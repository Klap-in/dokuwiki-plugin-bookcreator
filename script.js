

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
        $addtobookBtn.show();
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
    deletedpages: new Storage('bookcreator_deletedpages'),


    init: function() {
        this.deletedpages.load();
        Bookcreator.init();
    },

    setupUpdateObserver: function(){
        jQuery(window).on('storage', function() {
            BookManager.init();
            BookManager.updateListsFromStorage();
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
            jQuery.extend(BookManager.cache, pages);

            BookManager.updateLists();
        }

        //retrieve data
        if(notcachedpages.length > 0) {
            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {
                    call: 'plugin_bookcreator_call',
                    action: 'retrievePageinfo',
                    selection: JSON.stringify(notcachedpages)
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
     * Delete all selections of selected and deleted pages
     */
    clearSelections: function(event) {
        event.preventDefault();

        BookManager.deletedpages.clearAll();
        jQuery('ul.pagelist.deleted').empty();

        Bookcreator.selectedpages.clearAll();
        jQuery('ul.pagelist.selected').empty();
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

        //buttons at list
        $pagelist.find('ul')
            .on('click', 'a.action', BookManager.movePage);

        //sorting and drag-and-drop
        isItemSortedWithinList = false; //use in closure
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
        jQuery('form.clearactive button').on('click', BookManager.clearSelections);


        BookManager.updateListsFromStorage();
        BookManager.setupUpdateObserver();
    }

    ////add click handlers to Selectionslist
    //jQuery('form#bookcreator__selections__list a.action').click(Bookcreator.actionList); // stored selections


});
