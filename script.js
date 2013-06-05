
function book_changePage(name, value, expires, path, domain, secure) {
    var curCookie = name + "=" + encodeURIComponent(value) +
        ((expires) ? "; expires=" + expires.toGMTString() : "") +
        ((path) ? "; path=" + path : "") +
        ((domain) ? "; domain=" + domain : "") +
        ((secure) ? "; secure" : "");
    document.cookie = curCookie;
}
// recupera lo stato di una pagina
function book_getPage(name) {
    var dc = document.cookie;
    var prefix = name + "=";
    var begin = dc.indexOf("; " + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
    }
    else begin += 2;
    var end = document.cookie.indexOf(";", begin);
    if (end == -1) end = dc.length;
    return decodeURIComponent(dc.substring(begin + prefix.length, end));
}

// clears the selection page
function book_removeAllPages(rootname) {

    var cookie_date = new Date();  // current date & time
    cookie_date.setTime(cookie_date.getTime() - 1);

    var tmp = "";
    var n = -1;

    var thecookie = document.cookie.split(";");
    for (var i = 0; i < thecookie.length; i++) {
        n = thecookie[i].indexOf("=");
        tmp = (n > -1) ? thecookie[i].substr(0, n) : thecookie[i];

        while (tmp.substring(0, 1) == ' ') {
            tmp = tmp.substring(1, tmp.length);
        }

        if (tmp.substr(0, rootname.length) == rootname) {
            document.cookie = tmp + "=; path=/; expires=" + cookie_date.toGMTString();
        }
    }

}


/**
 * counts the pages
 *
 * @param rootname
 * @return {Number} number of pages
 */
function book_countPages(rootname) {
    var tmp = "";
    var n = -1;
    var k = 0;

    var thecookie = document.cookie.split(";");
    for (var i = 0; i < thecookie.length; i++) {
        n = thecookie[i].indexOf("=");
        tmp = (n > -1) ? thecookie[i].substr(0, n) : thecookie[i];

        while (tmp.substring(0, 1) == " ") {
            tmp = tmp.substring(1, tmp.length);
        }

        if (tmp.substr(0, rootname.length) == rootname) {
            if (book_getPage(tmp) == 1) {
                k = k + 1;
            }
        }
    }
    return k;
}

/**
 * reload the page
 */
function book_recharge() {
    window.location.reload();
}

/**
 * Handle read or delete of a Selection from the Selection List
 *
 * @param {string} action 'del' or 'read'
 * @param {string} page name of page with the list of selected pages
 * @return {Boolean}
 */
function actionList(action,page) {
    var msg = "";
    var flag = true;
    var flagconfirm = true;
    if (action == "del") {
        msg = LANG.plugins.bookcreator.confirmdel;
    } else {
        if (book_countPages("bookcreator") == 0) {
            flag = false;
        }
        msg = LANG.plugins.bookcreator.confirmload;
    }

    if (flag) flagconfirm = confirm(msg);
    if(flagconfirm) {
        document.bookcreator__selections__list.task.value=action;
        document.bookcreator__selections__list.page.value=page;
        document.bookcreator__selections__list.submit();
        return true;
    }
}

/**
 * Toggle element with given id
 *
 * @param {string} id of the element
 */
function book_revertLink(id) {
    var elem = jQuery("#"+id)[0];
    if (document && elem)
        if (elem.style.display=="block")
            elem.style.display="none";
        else
            elem.style.display="block";
}

/**
 * Update cookie and toggle the add/remove buttons in toolbar
 *
 * @param {string} id    pageid
 * @param {int}    value 1=add, 0=remove
 */
function book_updateSelection(id, value) {
    book_changePage("bookcreator["+id+"]", value, new Date("July 21, 2099 00:00:00"), "/");
    book_revertLink("bookcreator__remove");
    book_revertLink("bookcreator__add");
    jQuery("#bookcreator__pages")[0].innerHTML= book_countPages("bookcreator");
}

