/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/*
 * Display a two second message in the message div at the top of the web page
 *
 * @param String msg  string to display
 */
function doMessage(msg)
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = msg;
    msg_timer = setInterval("undoMessage()", 2000);
}
/*
 * Undisplays the message display in the message div and clears associated
 * message display timer
 */
function undoMessage()
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = "";
    clearInterval(msg_timer);
}
/*
 * Function to set up a request object even in  older IE's
 *
 * @return Object the request object
 */
function makeRequest()
{
    try {
        request = new XMLHttpRequest();
    } catch (e) {
        try {
            request = new ActiveXObject('MSXML2.XMLHTTP');
        } catch (e) {
            try {
                request = new ActiveXObject('Microsoft.XMLHTTP');
            } catch (e) {
                return false;
            }
        }
    }
    return request;
}
/*
 * Make an AJAX request for a url and put the results as inner HTML of a tag
 * If the response is the empty string then the tag is not replaced
 *
 * @param Object tag  a DOM element to put the results of the AJAX request
 * @param String url  web page to fetch using AJAX
 */
function getPage(tag, url)
{
    var request = makeRequest();
    if (request) {

        var self = this;
        request.onreadystatechange = function()
        {
            if (self.request.readyState == 4) {
                tag.innerHTML = self.request.responseText;
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}
/*
 * Returns the position of the caret within anode
 *
 * @param String input type element
 */
function caret(node)
{
    if (node.selectionStart) {
        return node.selectionStart;
    } else if (!document.selection) {
        return false;
    }
    // old ie hack
    var insert_char = "\001",
            sel = document.selection.createRange(),
            dul = sel.duplicate(),
            len = 0;

    dul.moveToElementText(node);
    sel.text = insert_char;
    len = dul.text.indexOf(insert_char);
    sel.moveStart('character', -1);
    sel.text = "";
    return len;
}
/*
 * Shorthand for document.createElement()
 *
 * @param String name tag name of element desired
 * @return Element the create element
 */
function ce(name)
{
    return document.createElement(name);
}
/*
 * Shorthand for document.getElementById()
 *
 * @param String id  the id of the DOM element one wants
 */
function elt(id)
{
    return document.getElementById(id);
}
/*
 * Shorthand for document.getElementsByTagName()
 *
 * @param String name the name of the DOM element one wants
 */
function tag(name)
{
    return document.getElementsByTagName(name);
}
/*
 * Sets whether an elt is styled as display:none or block
 *
 * @param String id  the id of the DOM element one wants
 * @param mixed value  true means display block; false display none;
 *     anything else will display that value
 */
function setDisplay(id, value)
{
    obj = elt(id);
    if (value == true) {
        value = "block";
    }
    if (value == false) {
        value = "none";
    }
    obj.style.display = value;
}
/*
 * Toggles an element between display:none and display block
 * @param String id  the id of the DOM element one wants
 */
function toggleDisplay(id)
{
    obj = elt(id);
    if (obj.style.display == "block") {
        value = "none";
    } else {
        value = "block";
    }
    obj.style.display = value;
}

/*
 * Toggles Help element from display:none and display block. 
 * Also changes the width of the Current activity accordingly.
 * @param String id  the id of the DOM element for the help div
 */
function toggleHelp(id, isMobile) {
    var all_help_elements = document.getElementsByClassName('current-activity');
    var help_node = all_help_elements[0];
    toggleDisplay(id);
    obj = elt(id);
    var help_height = 164;//obj.clientHeight;

    if (isMobile === false) {
        var new_width;
        var decrease_width_by = (getCssProperty(obj, 'width') / 3);
        //Calculate pixel to inch. clientWidth only returns in pixels.
        if (obj.style.display === "none") {
            new_width = Math.floor(getCssProperty(help_node, 'width'))
                    + decrease_width_by;
        } else if (obj.style.display === "block") {
            new_width = Math.floor(getCssProperty(help_node, 'width'))
                    - decrease_width_by;
        }

        if (new_width !== undefined) {
            help_node.style.maxWidth = new_width + "px";
        }
    } else {
        //Calculate pixel to inch. clientWidth only returns in pixels.
        if (obj.style.display === "none") {
            help_node.style.top = getCssProperty(help_node, 'top')
                    - help_height + "px";
        } else if (obj.style.display === "block") {
            help_node.style.top = getCssProperty(help_node, 'top')
                    + help_height + "px";
        }
    }

    /*
     * gets the Css property given an element and property name. 
     * @param element elm, Strin property
     */
    function getCssProperty(elm, property) {
        //always returns in px
        return parseInt(window.getComputedStyle(elm, null)
                .getPropertyValue(property));
    }
}

//This is a JS function to display preview.
function parseWikiContent(wikitext) {
    var html = wikitext;

    html = listify(html);

    // Basic MediaWiki Syntax.
    // Replace newlines with <br />
    html = html.replace(/\n/gi, "<br />");

    //Regex replace for headings
    html = html.replace(/(?:^|\n)([=]+)(.*)\1/g,
            function (match, contents, t) {
                return '<h'
                        + contents.length + '>'
                        + t
                        + '</h'
                        + contents.length
                        + '>';
            });

    //Regex replace for Bold characters
    html = html.replace(/'''(.*?)'''/g, function (match, contents) {
        return '<b>' + contents + '</b>';
    });

    //Regex replace for Italic characters
    html = html.replace(/''(.*?)''/g, function (match, contents) {
        return '<i>' + contents + '</i>';
    });

    //Regex replace for HR
    html = html.replace(/----(.*?)/g, function (match, contents) {
        return '<hr>' + contents + '</hr>';
    });

    //Regex replace normal links
    html = html.replace(/\[(.*?)\]/g, function (match, contents) {
        return '<a href= "' + contents + '">' + contents + '</a>';
    });

    //Regex replace for external links
    html = html.replace(/[\[](http.*)[!\]]/g, function (match, contents) {
        var p = contents.replace(/[\[\]]/g, '').split(/ /);
        var link = p.shift();
        return '<a href="' + link + '">'
                + (p.length ? p.join(' ') : link)
                + '</a>';
    });

    //Regex replace for blocks
    html = html.replace(/(?:^|\n+)([^# =\*<].+)(?:\n+|$)/gm,
            function (match, contents) {
                if (contents.match(/^\^+$/))
                    return contents;
                return "\n<div>" + contents + "</div>\n";
            });

    return html;
}

function listify(str) {
    return str.replace(/(?:(?:(?:^|\n)[\*#].*)+)/g, function (match) {
        var listType = match.match(/(^|\n)#/) ? 'ol' : 'ul';
        match = match.replace(/(^|\n)[\*#][ ]{0,1}/g, "$1");
        match = listify(match);
        return '<'
                + listType + '><li>'
                + match.replace(/^\n/, '')
                .split(/\n/).join('</li><li>')
                + '</li></' + listType
                + '>';
    });
}

var getJSON = function (url, successHandler, errorHandler) {
    var xhr = makeRequest();
    xhr.open('GET', url, true);
    xhr.responseType = 'json';
    xhr.onload = function () {
        var status = xhr.status;
        if (status == 200) {
            successHandler && successHandler(xhr.response);
        } else {
            errorHandler && errorHandler(status);
        }
    };
    xhr.send();
};

function evalJSON(json) {
    return eval("(" + json + ")");
}

function displayHelpForId(help_point) {
    toggleHelp('help-frame',false);
    getJSON("?c=api&group_id=7&arg=read&a=wiki&page_name=" + help_point.id, function (data) {
        document.getElementById("help-frame-body").innerHTML = parseWikiContent(data.wiki_content);
    }, function (status) {
        alert('Something went wrong.');
    });
 event.preventDefault();
}