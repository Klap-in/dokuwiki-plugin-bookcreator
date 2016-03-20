var Bookcreator = {

    _storage: [],
    isCurrentPageSelected: false,

    /**
     * Initiate storage
     */
    init: function() {
        this._load();
        this.isCurrentPageSelected = this.isSelected(JSINFO.id);
    },

    /**
     * Is pageid in stored selection
     *
     * @param {string} pageid
     * @returns {boolean}
     */
    isSelected: function(pageid) {
        return this._storage.indexOf(pageid) != -1;
    },

    /**
     * Delete or add current page from selection
     */
    toggleSelectionCurrentPage: function() {
        if(this.isCurrentPageSelected) {
            this.deletePage(JSINFO.id);
        } else {
            this.addPage(JSINFO.id);
        }
        this.isCurrentPageSelected = this.isSelected(JSINFO.id);
    },

    /**
     * Add pageid to selection
     *
     * @param pageid
     */
    addPage: function(pageid) {
        this._storage.push(pageid);
        this._save();
    },

    /**
     * Delete pageid from selection
     * @param pageid
     */
    deletePage: function(pageid) {
        var pos = this._storage.indexOf(pageid);
        if(pos == -1) return;

        this._storage.splice(pos, 1);
        this._save();
    },

    /**
     * Count number of selected pages
     *
     * @returns {Number}
     */
    count: function() {
        return this._storage.length;
    },
    /**
     * Save current selection in localStorage
     *
     * @private
     */
    _save: function() {
        window.localStorage.setItem('bookcreator_selectedpgs', JSON.stringify(this._storage));
    },

    /**
     * Load selection from localStorage
     *
     * @private
     */
    _load: function() {
        var source = window.localStorage.getItem('bookcreator_selectedpgs');

        try {
            Bookcreator._storage = JSON.parse(source) || [];
        } catch (E) {
            Bookcreator._storage = [];
        }
    },

    /**
     * Show the allowed tools to the user
     */
    showTools: function() {
        var $addtobookBtn = jQuery('.plugin_bookcreator_addtobook').parent(),
            $bookbar = jQuery('.bookcreator__bookbar');

        $addtobookBtn.show();

        if(JSINFO.bookcreator.showNoempty) {
            $bookbar.show();
        }
    },

    /**
     * Update the interface to current selection
     */
    updatePageTools: function() {
        //pagetool button
        var $addtobookBtn = jQuery('.plugin_bookcreator_addtobook');

        if ($addtobookBtn.length) { //exists the addtobook link
            var text = LANG.plugins.bookcreator['btn_' + (this.isCurrentPageSelected ? 'remove' : 'add') + 'tobook'];

            $addtobookBtn
                .toggleClass('remove', this.isCurrentPageSelected)
                .attr('title', text)
                .children('span').html(text);
        }

        //bookbar
        if(JSINFO.bookcreator.showNoempty) {
            jQuery("#bookcreator__add").toggle(!this.isCurrentPageSelected);
            jQuery("#bookcreator__remove").toggle(this.isCurrentPageSelected);
        }

        jQuery("#bookcreator__pages").html(this.count());
    },

    /**
     * Handle click at page add/remove buttons
     */
    clickPagetools: function(e) {
        e.preventDefault();

        Bookcreator.toggleSelectionCurrentPage();
        Bookcreator.updatePageTools();
    },

    /**
     * Sets up a storage change observer
     */
    setupUpdateObserver: function() {
        jQuery(window).on('storage', function() {
            Bookcreator._load();
            Bookcreator.updatePageTools();
        });

        //// handle cached navigation
        //if ('addEventListener' in window) {
        //    window.addEventListener('pageshow', function(event) {
        //        if (event.persisted) {
        //            Bookcreator._load();
        //        }
        //    }, false);
        //}
    }
};


jQuery(function () {
    if(!JSINFO.bookcreator.isVisible) return;

    Bookcreator.init();
    Bookcreator.setupUpdateObserver();

    // bookcreator tools at wiki page
    Bookcreator.showTools();
    Bookcreator.updatePageTools();
    jQuery('a.bookcreator__tglPgSelection').click(Bookcreator.clickPagetools);
    jQuery('.plugin_bookcreator_addtobook').click(Bookcreator.clickPagetools);


    ////bookmanager
    //var $pagelist = jQuery('div.bookcreator__pagelist');
    //if ($pagelist.length) {
    //    $pagelist.find('a.action')
    //        .click(Bookcreator.movePage);
    //    $pagelist.find('ul.pagelist.include,ul.pagelist.remove')
    //        .sortable({
    //            connectWith: "div.bookcreator__pagelist ul.pagelist",
    //            receive: Bookcreator.droppedInOtherlist,
    //            stop: Bookcreator.storePageOrder,
    //            distance: 5
    //        });
    //
    //    Bookcreator.toggleDeletelist();
    //    Bookcreator.storePageOrder()
    //}
    //
    ////add click handlers to Selectionslist
    //jQuery('form#bookcreator__selections__list a.action').click(Bookcreator.actionList);
});

var BookManager  = {

};
