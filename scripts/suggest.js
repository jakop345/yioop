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
 * @author Sandhya Vissapragada, Chris Pollett
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012s
 * @filesource
 */

/**
 *  Constants for key codes will handle
 */
KeyCodes = new Object();
KeyCodes.UP_ARROW = 38;
KeyCodes.DOWN_ARROW = 40;

/**
 * Maximum number of search terms to display
 */
MAX_DISPLAY = 6;
/**
 * Height of a search term in pixels
 */
FONT_HEIGHT = 24;
/**
 * Used to delimit the end of a term in a trie.
 * The value below is the default define. Might be set
 * what the trie object loaded says in loadTrie
 */
END_OF_TERM_MARKER = " ";

/**
 * Steps to follow every time a key is up from the user end
 * Handles up/dowm arrow keys
 *
 * @param Event event current event 
 * @return String text_field Current value from the search box
 *
 */
function onTypeTerm(event, text_field) 
{
    var key_code_pressed;
    var term_array;
    var input_term = text_field.value;
    var suggest_results = elt("suggest-results");
    var suggest_dropdown = elt("suggest-dropdown");
    var scroll_pos = 0;
    var tmp_pos = 0;

    concat_term = "";
    if(window.event) { // IE8 and earlier
        key_code_pressed = event.keyCode;
    } else if(event.which) { // IE9/Firefox/Chrome/Opera/Safari
        key_code_pressed = event.which;
    }
    term_array = input_term.split(" ");
    concat_array = input_term.split(" ", term_array.length - 1);
    if (input_term != "") {
        for(var i = 0; i < concat_array.length; i++) {
            concat_term += concat_array[i] + " ";
        }
        concat_term = concat_term.trim();
    }    
    input_term = term_array[term_array.length - 1];

    // behavior if typing keys other than up or down (notice call termSuggest)
    if (key_code_pressed != KeyCodes.DOWN_ARROW && 
        key_code_pressed != KeyCodes.UP_ARROW) {
        search_list = "";
        termSuggest(dictionary, input_term);
        if(count <= 1) {
            search_list = "";
        }
        suggest_dropdown.scrollTop =  0;
        suggest_results.innerHTML = search_list;
        cursor_pos = -1; 
        num_items = count;
        if (num_items == 0 || search_list == "") {
            suggest_dropdown.className = "";
            suggest_dropdown.style.height = "0";
            suggest_dropdown.style.visibility = "hidden";
            suggest_results.style.visibility = "hidden";
        } else {
            suggest_dropdown.className = "dropdown";
            suggest_results.style.visibility = "visible";
            suggest_dropdown.style.visibility = "visible";
            suggest_dropdown.style.height = (FONT_HEIGHT * MAX_DISPLAY) + "px";
        }
    }
    // behavior on up down arrows
    if(suggest_results.style.visibility == "visible") {
        if(key_code_pressed == KeyCodes.DOWN_ARROW) { 
            if (cursor_pos < 0) { 
                cursor_pos = 0;
                setSelectedTerm(cursor_pos, "selected");
            } else {
                if(cursor_pos < num_items - 1) {
                    setSelectedTerm(cursor_pos, "unselected");
                    cursor_pos++;
                }
                setSelectedTerm(cursor_pos, "selected");
            }
            scroll_count = 1;
            scroll_pos = (cursor_pos - MAX_DISPLAY > 0) ? 
                (cursor_pos - MAX_DISPLAY + 1) : 1;
            suggest_dropdown.scrollTop = scroll_pos * FONT_HEIGHT;
        } else if(key_code_pressed == KeyCodes.UP_ARROW) {
            if (cursor_pos < 0) {
                cursor_pos = 0;
                setSelectedTerm(cursor_pos, "selected");
            } else {
                if(cursor_pos > 0) {
                    setSelectedTerm(cursor_pos, "unselected");
                    cursor_pos--;
                }
                setSelectedTerm(cursor_pos, "selected");
            }
            scroll_pos = (cursor_pos - MAX_DISPLAY + scroll_count > 0) ? 
                (cursor_pos - MAX_DISPLAY + scroll_count) : 1;
            scroll_count = (MAX_DISPLAY > scroll_count) ? scroll_count + 1 :
                MAX_DISPLAY;
            suggest_dropdown.scrollTop = scroll_pos * FONT_HEIGHT;
        }
    }
}

/**
 * To select an suggest value while up/down arrow keys are being used
 * and place in the search box
 *
 * @param int pos index in the list items of suggest terms
 * @param String class_value value for CSS class attribute for that list item
 */
function setSelectedTerm(pos, class_value)
{
    var query_field_object = elt("query-field");
    query_field_object.value = elt("term" + pos).title;
    elt("term" + pos).className = class_value;
}

/**
 * To selects a term from the suggest dropdownlist and performs as search
 *
 * @param String term what was clicked on
 */
function termClick(term)
{
    var results_dropdown = elt("suggest-results");
    var query_field_object = elt("query-field");
    query_field_object.value = term;
    results_dropdown.innerHTML = "";
    elt("suggest-dropdown").style.display = "none";
    elt("search-form").submit();
}


/**
 * Fetch words from the Trie and add to seachList with <li> </li> tags
 *
 * @param Array trie_array contains all search terms
 * @param String parent_word the prefix want to find sub-term for in trie
 * @param String highlighted_word parent_word, root_word + "<b>" + rest of 
 *   parent
 */
function getTrieTerms(trie_array, parent_word, highlighted_word) 
{
    var search_terms;
    var highlighted_terms;

    if (trie_array != null) {
        for (key in trie_array) {
            if (key != END_OF_TERM_MARKER ) {
                getTrieTerms(trie_array[key], parent_word + key, 
                    highlighted_word + key);
            } else {
                search_terms = concat_term.trim() + " " + decode(parent_word);
                search_terms = search_terms.trim();
                highlighted_terms = concat_term.trim() + " " 
                    + decode(highlighted_word) + "</b>";
                search_list += "<li><span id='term" +count+
                    "' class='unselected' onclick = 'void(0)' " +
                    "title='"+search_terms+"' " +
                    "onmouseup='termClick(\""+search_terms+"\")'"+
                    ">" + highlighted_terms + "</span></li>";
                count++;
            }
        }
    }
}

/**
 * Returns the sub trie_array under term in
 * trie_array. If term does not exist in the trie_array
 * returns false
 *
 * @param String term  what to look up
 * @return Array trie_array sub trie_array under term
 */
function exist(trie_array, term)
{
    for(var i = 1; i< term.length; i++) {
        if(trie_array == null){
            return false;
        }
        if (trie_array != 'null') {
            tmp = getUnicodeCharAndNextOffset(term, i);
            if(tmp == false) return false;
            next_char = tmp[0];
            i = tmp[1];
            enc_char = encode(next_char);
            if(trie_array[enc_char] != 'null') {
                trie_array = trie_array[enc_char];
            }
        }
        else {
            return false;
        }
    }
    return trie_array;
}

/**
 * Entry point to find word completions/suggestions. Finds the portion of
 * trie_aray beneath term. Then using this subtrie get the first six entries.
 * Six is specified in get values.
 *
 * @param Array trie_array - a nested array represent a trie
 * @param String term - what to look up suggestions for
 * @sideeffect global Array search_list has list of first six entries
 */
function termSuggest(trie_array, term)
{
    last_word = false;
    count = 0;
    search_list = "";
    term = term.toLowerCase();
    var tmp;
    if(trie_array == null) {
        return false;
    }
    if((term.length) > 1) {
        tmp = getUnicodeCharAndNextOffset(term, 0);
        if(tmp == false) return false;
        start_char = tmp[0];
        enc_chr = encode(start_char);
        trie_array = exist(trie_array[enc_chr], term);
    } else {
        trie_array = trie_array[term];
    }
    getTrieTerms(trie_array, term, term + "<b>");
    short_max = MAX_DISPLAY - count;
    for(var i = 0; i < short_max; i++) {
        search_list += "<li><span class='unselected'>&nbsp;</span></li>";
    }
}

/* wrappers to save typing */
function decode(str) {
    str = str.replace(/\+/g, '%20');
    return decodeURIComponent(str);
}
/* wrappers to save typing */
function encode(str)
{
    return encodeURIComponent(str);
}

/**
 * Extract next Unicode Char beginning at offset i in str returns Array
 * with this character and the next offset
 *
 * This is based on code found at:
 * https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects
 * /String/charAt
 * 
 * @param String str what to get the next character out of
 * @param int i current offset into str
 * @return Array pair of Unicode character beginning at i and the next offset
 */
function getUnicodeCharAndNextOffset(str, i)
{
    var code = str.charCodeAt(i);
    if (isNaN(code)) {
        return '';
    }
    if (code < 0xD800 || code > 0xDFFF) {
        return [str.charAt(i), i];
    }
    if (0xD800 <= code && code <= 0xDBFF) {
        if (str.length <= i + 1) {
            return false;
        }  
        var next = str.charCodeAt(i + 1);
        if (0xDC00 > next || next > 0xDFFF) {
            return false;
        }
        return [str.charAt(i) + str.charAt(i + 1), i + 1];
    }
    if (i === 0) {
        return false;
    }
    var prev = str.charCodeAt(i-1);
    if (0xD800 > prev || prev > 0xDBFF) {
        return false;
    }
    return [str.charAt(i + 1), i + 1];
}

/**
 * Load the Trie(compressed with .gz extension) during the launch of website
 * Trie's are represented using nested arrays.
 */
function loadTrie() 
{
    var request = makeRequest();
    if(request) {
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                trie = JSON.parse(request.responseText);
                dictionary = trie["trie_array"];
                END_OF_TERM_MARKER = trie["end_marker"];
            }
            END_OF_TERM_MARKER = (typeof END_OF_TERM_MARKER == 'undefined') ? 
                ' ' : END_OF_TERM_MARKER;
        }

        locale = document.documentElement.lang;
        if(locale) {
            trie_loc = "./?c=resource&a=suggest&locale=" + locale;
            request.open("GET", trie_loc, true);
            request.send();
        }
    }
}
document.getElementsByTagName("body")[0].onload = loadTrie;
