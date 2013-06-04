function book_changePage(name, value, expires, path, domain, secure) {
  var curCookie = name + "=" + escape(value) +
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
  return unescape(dc.substring(begin + prefix.length, end));
}

// cancella la pagina della selezione
function book_removeAllPages(rootname) {

  var cookie_date = new Date ( );  // current date & time
  cookie_date.setTime ( cookie_date.getTime() - 1 );
  
  var tmp = "";
  var n = -1;

  var thecookie = document.cookie.split(";");
  for (var i = 0;i < thecookie.length;i++) {
    n=thecookie[i].indexOf("=");
    tmp = (n>-1)?thecookie[i].substr(0,n):thecookie[i];
    
    while (tmp.substring(0,1) == ' '){
      tmp = tmp.substring(1, tmp.length);
    }

    if (tmp.substr(0, rootname.length) == rootname ) {
      document.cookie = tmp + "=; path=/; expires=" + cookie_date.toGMTString();
    }  
  }
  
}


// conta le pagine presenti
function book_countPages(rootname) {
  var tmp = "";
  var n = -1;
  var k = 0;

  var thecookie = document.cookie.split(";");
  for (var i = 0;i < thecookie.length;i++) {
    n=thecookie[i].indexOf("=");
    tmp = (n>-1)?thecookie[i].substr(0,n):thecookie[i];
    
    while (tmp.substring(0,1) == " "){
      tmp = tmp.substring(1, tmp.length);
    }

    if (tmp.substr(0, rootname.length) == rootname ) {
      if (book_getPage(tmp) == 1) {
        k = k + 1;
      }  
    }  
  }
  return k;
}

// ricarica la pagina
function book_recharge() {
  window.location.reload();
}

