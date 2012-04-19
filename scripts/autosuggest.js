/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Sandhya Vissapragada
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012s
 * @filesource
 */

/**
 * Steps to follow every time a key is up from the user end
 */
function askeyup(textobj) {
    var inputTerm = textobj.value;
    var res_obj = document.getElementById("aslist");
    searchList="";
    autosuggest(dictObj,inputTerm); 
    res_obj.innerHTML = searchList; 
    searchList="";
}

/**
 * To select a value from the dropdownlist and place in the search box 
 */
function aslitem_click(liObj)
{
    var res_obj = document.getElementById("aslist");
   // var astobj = document.getElementById("astbox");
    var astobj = document.getElementById("search-name"); 
    astobj.value = liObj.innerHTML;
    res_obj.innerHTML = "";
}

/**
 * Fetch words from the Trie and add to seachList with <li> </li> tags
 */
function getValues(arrayLevel, parent_word)
{
    if (arrayLevel != null && lastWord == 0 ) {
        for (key in arrayLevel) { 
            if (key != " " ) {
                getValues(arrayLevel[key], parent_word+key);
            } else {
                searchList += "<li onclick='aslitem_click(this);'>" 
                    + parent_word + "</li>";
                count++;
                // Display only top 6 words
                if(count == 6) {
                    lastWord = 1;
                }
            }
        }
    }
}

/**
 * If more than one character is entered, get the level of array to fetch the
 * words
 */
function exist(arrayLevel, searchTerm)
{
    var i;  
    for(i=1;i<searchTerm.length;i++) {
        if(arrayLevel == null){
            return false;
        }
        if (arrayLevel != 'null') {
            if(arrayLevel[searchTerm.charAt(i)] != 'null') { 
                arrayLevel = arrayLevel[searchTerm.charAt(i)];
            }
        }
        else {
            return false;
        }
    } 
    return arrayLevel;
}

/**
 * Entry point to find words for autosuggestion.Find the level of the array 
 * based on the number of characters entered by the user
 */
function autosuggest(arrayLevel, searchTerm)
{
    lastWord=0, count=0;
    if(arrayLevel == null || arrayLevel[" "] == " ") {
        return false;
    }
    if((searchTerm.length) > 1) {
        arrayLevel = exist(arrayLevel[searchTerm.charAt(0)], searchTerm);

    } else {
        arrayLevel = arrayLevel[searchTerm];
    }
    getValues(arrayLevel, searchTerm);
}

/**
 * Load the Trie(compressed with .gz extension) during the launch of website
 */
function loadTrie() 
{
    var xmlhttp;
    if (window.XMLHttpRequest) {
        // code for IE7i+, Firefox, Chrome, Opera, Safari
        xmlhttp=new XMLHttpRequest();
    } else {
        // code for IE6, IE5
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange=function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            dictObj = JSON.parse(xmlhttp.responseText);
        }
    }

    locale = document.documentElement.lang
    if(locale) {
        trie_loc = "./?c=resource&a=suggest&locale="+locale;
        xmlhttp.open("GET", trie_loc, true);
        xmlhttp.send();
    }
}
