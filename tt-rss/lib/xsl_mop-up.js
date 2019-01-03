//  -*-coding: latin-1;-*-
// Time-stamp: "2006-05-17 22:06:46 ADT" sburke@cpan.org

// A workaround for XSL-to-XHTML systems that don't
//  implement XSL 'disable-output-escaping="yes"'.
//
// sburke@cpan.org, Sean M. Burke.
//  - I hereby release this JavaScript code into the public domain.

var is_decoding;
var DEBUG = 0;

function complaining (s) { alert(s);  return new Error(s,s); }

if(!(   document.getElementById && document.getElementsByName ))
 throw complaining("Your browser is too old to render this page properly."
  + "  Consider going to getfirefox.com to upgrade.");

function check_decoding () {
  var d = document.getElementById('cometestme');
  if(!d) {
    throw complaining("Can't find an id='cometestme' element?");
  } else if(!('textContent' in d)) {
    // It's a browser with a halfassed DOM implementation (like IE6)
    // that doesn't implement textContent!  Assume that if it's that
    // dumb, it probably doesn't implement disable-content-encoding.

  } else {
    var ampy = d.textContent;
    if(DEBUG > 1) { alert("Got " + ampy); }

    if(ampy == undefined) throw complaining("'cometestme' element has undefined text content?!");
    if(ampy == ''       ) throw complaining("'cometestme' element has empty text content?!"    );

    if      (ampy == "\x26"	) { is_decoding =  true; }
    else if (ampy == "\x26amp;" ) { is_decoding = false; }
    else		     { throw complaining('Insane value: "' + ampy + '"!'); }
  }

  var msg =
   (is_decoding == undefined) ? "I can't tell whether the XSL processor supports disable-content-encoding!D"
   : is_decoding ? "The XSL processor DOES support disable-content-encoding"
   : "The XSL processor does NOT support disable-content-encoding"
  ;
  if(DEBUG) alert(msg);
  return msg;
}


function go_decoding () {
  check_decoding();

  if(is_decoding) {
    DEBUG && alert("No work needs doing -- already decoded!");
    return;
  }

  var to_decode = document.getElementsByName('decodeme');
  if(!( to_decode && to_decode.length )) {
    DEBUG && alert("No work needs doing -- no elements to decode!");
    return;
  }

  if(!(  ( "innerHTML"   in to_decode[0]) &&  ( "textContent" in to_decode[0])  ))
    throw complaining( "Your JavaScript version doesn't implement DOM " +
     "properly enough to show this page correctly.  " +
     "Consider going to getfirefox.com to upgrade.");

  var s;
  for(var i = to_decode.length - 1; i >= 0; i--) { 
    s = to_decode[i].textContent;

    if(
      s == undefined ||
      (s.indexOf('&') == -1 && s.indexOf('<') == -1)
    ) {
      // the null or markupless element needs no reworking
    } else {
      to_decode[i].innerHTML = s;  // that's the magic
    }
  }

  return;
}

//End
