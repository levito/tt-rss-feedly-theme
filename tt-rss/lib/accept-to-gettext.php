<?php
/*
 * accept-to-gettext.inc -- convert information in 'Accept-*' headers to
 * gettext language identifiers.
 * Copyright (c) 2003, Wouter Verhelst <wouter@debian.org>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * Usage:
 *
 *  $locale=al2gt(<array of supported languages/charsets in gettext syntax>,
 *                <MIME type of document>);
 *  setlocale('LC_ALL', $locale); // or 'LC_MESSAGES', or whatever...
 *
 * Example:
 *
 *  $langs=array('nl_BE.ISO-8859-15','nl_BE.UTF-8','en_US.UTF-8','en_GB.UTF-8');
 *  $locale=al2gt($langs, 'text/html');
 *  setlocale('LC_ALL', $locale);
 *
 * Note that this will send out header information (to be
 * RFC2616-compliant), so it must be called before anything is sent to
 * the user.
 * 
 * Assumptions made:
 * * Charset encodings are written the same way as the Accept-Charset
 *   HTTP header specifies them (RFC2616), except that they're parsed
 *   case-insensitive.
 * * Country codes and language codes are the same in both gettext and
 *   the Accept-Language syntax (except for the case differences, which
 *   are dealt with easily). If not, some input may be ignored.
 * * The provided gettext-strings are fully qualified; i.e., no "en_US";
 *   always "en_US.ISO-8859-15" or "en_US.UTF-8", or whichever has been
 *   used. "en.ISO-8859-15" is OK, though.
 * * The language is more important than the charset; i.e., if the
 *   following is given:
 * 
 *   Accept-Language: nl-be, nl;q=0.8, en-us;q=0.5, en;q=0.3
 *   Accept-Charset: ISO-8859-15, utf-8;q=0.5
 *
 *   And the supplied parameter contains (amongst others) nl_BE.UTF-8
 *   and nl.ISO-8859-15, then nl_BE.UTF-8 will be picked.
 * 
 * $Log: accept-to-gettext.inc,v $
 * Revision 1.1.1.1  2003/11/19 19:31:15  wouter
 * * moved to new CVS repo after death of the old
 * * Fixed code to apply a default to both Accept-Charset and
 *   Accept-Language if none of those headers are supplied; patch from
 *   Dominic Chambers <dominic@encasa.com>
 *
 * Revision 1.2  2003/08/14 10:23:59  wouter
 * Removed little error in Content-Type header syntaxis.
 *
 * 2007-04-01
 * add '@' before use of arrays, to avoid PHP warnings.
 */

/* not really important, this one; perhaps I could've put it inline with
 * the rest. */
function find_match($curlscore,$curcscore,$curgtlang,$langval,$charval,
                    $gtlang)
{
  if($curlscore < $langval) {
    $curlscore=$langval;
    $curcscore=$charval;
    $curgtlang=$gtlang;
  } else if ($curlscore == $langval) {
    if($curcscore < $charval) {
      $curcscore=$charval;
      $curgtlang=$gtlang;
    }
  }
  return array($curlscore, $curcscore, $curgtlang);
}

function al2gt($gettextlangs, $mime) {
  /* default to "everything is acceptable", as RFC2616 specifies */
  $acceptLang=(($_SERVER["HTTP_ACCEPT_LANGUAGE"] == '') ? '*' :
      $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
  $acceptChar=(($_SERVER["HTTP_ACCEPT_CHARSET"] == '') ? '*' :
      $_SERVER["HTTP_ACCEPT_CHARSET"]);
  $alparts=@preg_split("/,/",$acceptLang);
  $acparts=@preg_split("/,/",$acceptChar);
  
  /* Parse the contents of the Accept-Language header.*/
  foreach($alparts as $part) {
    $part=trim($part);
    if(preg_match("/;/", $part)) {
      $lang=@preg_split("/;/",$part);
      $score=@preg_split("/=/",$lang[1]);
      $alscores[$lang[0]]=$score[1];
    } else {
      $alscores[$part]=1;
    }
  }

  /* Do the same for the Accept-Charset header. */

  /* RFC2616: ``If no "*" is present in an Accept-Charset field, then
   * all character sets not explicitly mentioned get a quality value of
   * 0, except for ISO-8859-1, which gets a quality value of 1 if not
   * explicitly mentioned.''
   * 
   * Making it 2 for the time being, so that we
   * can distinguish between "not specified" and "specified as 1" later
   * on. */
  $acscores["ISO-8859-1"]=2;

  foreach($acparts as $part) {
    $part=trim($part);
    if(preg_match("/;/", $part)) {
      $cs=@preg_split("/;/",$part);
      $score=@preg_split("/=/",$cs[1]);
      $acscores[strtoupper($cs[0])]=$score[1];
    } else {
      $acscores[strtoupper($part)]=1;
    }
  }
  if($acscores["ISO-8859-1"]==2) {
    $acscores["ISO-8859-1"]=(isset($acscores["*"])?$acscores["*"]:1);
  }

  /* 
   * Loop through the available languages/encodings, and pick the one
   * with the highest score, excluding the ones with a charset the user
   * did not include.
   */
  $curlscore=0;
  $curcscore=0;
  $curgtlang=NULL;
  foreach($gettextlangs as $gtlang) {

    $tmp1=preg_replace("/\_/","-",$gtlang);
    $tmp2=@preg_split("/\./",$tmp1);
    $allang=strtolower($tmp2[0]);
    $gtcs=strtoupper($tmp2[1]);
    $noct=@preg_split("/-/",$allang);

    $testvals=array(
         array(@$alscores[$allang], @$acscores[$gtcs]),
     array(@$alscores[$noct[0]], @$acscores[$gtcs]),
     array(@$alscores[$allang], @$acscores["*"]),
     array(@$alscores[$noct[0]], @$acscores["*"]),
     array(@$alscores["*"], @$acscores[$gtcs]),
     array(@$alscores["*"], @$acscores["*"]));

    $found=FALSE;
    foreach($testvals as $tval) {
      if(!$found && isset($tval[0]) && isset($tval[1])) {
        $arr=find_match($curlscore, $curcscore, $curgtlang, $tval[0],
              $tval[1], $gtlang);
        $curlscore=$arr[0];
        $curcscore=$arr[1];
        $curgtlang=$arr[2];
    $found=TRUE;
      }
    }
  }

  /* We must re-parse the gettext-string now, since we may have found it
   * through a "*" qualifier.*/
  
  $gtparts=@preg_split("/\./",$curgtlang);
  $tmp=strtolower($gtparts[0]);
  $lang=preg_replace("/\_/", "-", $tmp);
  $charset=$gtparts[1];

  header("Content-Language: $lang");
  header("Content-Type: $mime; charset=$charset");

  return $curgtlang;
}

?>
