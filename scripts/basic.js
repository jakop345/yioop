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
    } catch(e) {
        try {
            request = new ActiveXObject('MSXML2.XMLHTTP');
        } catch(e) {
            try {
            request = new ActiveXObject('Microsoft.XMLHTTP');
            } catch(e) {
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
    if(request) {

        var self = this;
        request.onreadystatechange = function()
        {
            if(self.request.readyState == 4) {
                    tag.innerHTML = self.request.responseText;
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}
/*
 * Returns the position of the caret within a node
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
    sel.moveStart('character',-1);
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
    if(value == true)  {
        value = "block";
    }
    if(value == false) {
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
    if(obj.style.display == "block")  {
        value = "none";
    } else {
        value = "block";
    }
    obj.style.display = value;
}

current_activity_closed = true;
current_activity_top = 0;
/*
 * Toggles Help element from display:none and display block. 
 * Also changes the width of the Current activity accordingly.
 * @param {String} help element's id to toggle.
 * @param {String} isMobile flag true/false.
 */
function toggleHelp(id, isMobile, target_c) 
{
    var activity = (target_c === "admin") ? 'current-activity' 
    : 'small-margin-current-activity';
    var images = document.querySelectorAll(".wiki-resource-image");
    var all_help_elements =
            document.getElementsByClassName(activity);
    var help_node = all_help_elements[0];
    if (current_activity_closed === true) {
        current_activity_top = getCssProperty(help_node, 'top');
    }
    obj = elt(id);

    if (isMobile === false) {
        toggleDisplay(id);
        var new_width;
        var decrease_width_by = Math.floor(getCssProperty(obj, 'width') / 3);
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
        toggleDisplay(id);
        var height_after_toggle = (obj.clientHeight);
        //Calculate pixel to inch. clientWidth only returns in pixels.
        if (obj.style.display === "none") {
            //on closing, restore top
            help_node.style.top = current_activity_top + "px";
            current_activity_closed = true;
        } else if (obj.style.display === "block") {
            help_node.style.top = current_activity_top
                    + height_after_toggle + "px";
            /* The div.clientHeight doesnt include the height
            of the images inside the div before the images are completely
            loaded. So we iterate through the 
            image elements, as each image loads add the image
            height to the top of the current_activity div dynamically. */
            for (var i = 0; i < images.length; i++) {
                var image = images[i];
                image.onload = function(){
                    help_node.style.top =  getCssProperty(help_node, 'top') 
                            + this.height + 'px';
                };
            }
            current_activity_closed = false;
        }
    }
}

/*
 * gets the Css property given an element and property name.
 * @param {Element} elm to get the Css property for
 * @param {String} Css property name.
 */
function getCssProperty(elm, property)
{
    //always returns in px
    return parseInt(window.getComputedStyle(elm, null)
        .getPropertyValue(property));
}

/**
 * This is a JS function to convert yioop wiki markup to
 * html.
 * @param {String} wiki_text tobe parsed as HTML
 * @returns {String} parsed html
 */
function parseWikiContent(wiki_text, group_id, page_id)
{
    var html = wiki_text;
    
    /* note that line breaks from a text area are sent
    as \r\n , so make sure we clean them up to replace
    all \r\n with \n */
    html = html.replace(/\r\n/g, "\n");

    html = parseLists(html);

    //Regex replace normal links
    html = html.replace(/[^\[](http[^\[\s]*)/g, function (m, l) {
    // normal link
        return '<a href="' + l + '">' + l + '</a>';
    });

    //Regex replace for external links
    html = html.replace(/[\[](http.*)[!\]]/g, function (m, l) {
    // external link
        var p = l.replace(/[\[\]]/g, '').split(/ /);
        var link = p.shift();
        return '<a href="' + link + '">'
        + (p.length ? p.join(' ') : link) + '</a>';
    });

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

    //Regex for resource extraction.
    html = html.replace(/{{resource:(.+?)\|(.+?)}}/g,
            function (match, contents, desc) {
                return '<img src="' + "?c=resource&a=get&f=resources&g="
                + group_id
                + "&p=" + page_id
                + "&n=" + contents + '" alt="' + desc
                + '" class="wiki-resource-image"/>';
            });

    //Regex replace for HR
    html = html.replace(/----(.*?)/g, function (match, contents) {
        return '<hr>' + contents + '</hr>';
    });

    //replace nowiki with pre tags
    html = html.replace(/<nowiki>(.*?)<\/nowiki>/g, function(match, contents) {
        return '<pre>' + contents + '</pre>';
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

/**
 * Lists need to be recursively parsed. So the below function is used
 * to recursively convert wiki markup to html.
 * @param {String} str
 * @returns {String}
 */
function parseLists(str)
{
    return str.replace(/(?:(?:(?:^|\n)[\*#].*)+)/g, function (match) {
        var listType = match.match(/(^|\n)#/) ? 'ol' : 'ul';
        match = match.replace(/(^|\n)[\*#][ ]{0,1}/g, "$1");
        match = parseLists(match);
        return '<'
                + listType + '><li>'
                + match.replace(/^\n/, '')
                .split(/\n/).join('</li><li>')
                + '</li></' + listType
                + '>';
    });
}

/**
 * get does a GET HTTP call on the url passed. 
 * Also fires the callback functions passed as 
 * params appropriately.
 * 
 * @param {String} url
 * @param {String} response_type
 * @param {function} success_call_back
 * @param {function} error_handler
 */
var get = function (url, response_type, success_call_back, error_handler)
{
    var request = makeRequest();
    request.open('GET', url, true);
    request.responseType = response_type;
    request.onload = function () {
        var status = request.status;
        if (status == 200) {
            success_call_back && success_call_back(request.response);
        } else {
            error_handler && error_handler(status);
        }
    };
    request.send();
};

/**
 * takes in the help point id, uses it to fetch wiki content, then 
 * wiki content is being eval'd to be painted int he help pane.
 * Ajax call happens only if help needs to be displayed.
 * @param {object} help_point element
 * @param {String} is_mobile flag to check if the client is mobile
 * or not.
 */
function displayHelpForId(help_point,
                is_mobile,target_c,csrf_token_key,csrf_token_value)
{
    var help_group_id = 7;
    var c = 'api';
    var a = "wiki";
    var arg = "read";

    if ((elt("help-frame").style.display) === "block") {
        toggleHelp('help-frame', is_mobile,target_c);
        return;
    }
    get("?c=" + c + "&group_id=" + help_group_id + "&" +
            "arg=" + arg + "&" +
            "a=" + a + "&" +
            csrf_token_key +'=' + csrf_token_value + "&" +
            "page_name=" + help_point.getAttribute("data-pagename"),
            'json',
            function (data) {
                elt("help-frame-body").innerHTML = parseWikiContent(
                        data.wiki_content,
                        data.group_id,
                        data.page_id
                        );
                elt('page_name').innerHTML = data.page_name + ' <a href="'+
                        getEditLink(
                        target_c, 
                        csrf_token_key,
                        csrf_token_value,
                        help_group_id,
                        data.page_name) +'">'+
                        '[Edit]</a>' ;

                toggleHelp('help-frame', is_mobile,target_c);
            },
            function (status) {
                toggleHelp('help-frame', is_mobile,target_c);
            });
    event.preventDefault();
}

/**
 * Simple function to construct the Wiki Edit hyperlink with passed in params.
 * @param {type} target_c
 * @param {type} csrf_token
 * @param {type} group_id
 * @param {type} page_name
 * @returns {String}
 */
function getEditLink(target_c, 
                csrf_token_key, csrf_token_value, group_id, page_name)
{
    return '?c=' + target_c +
            '&'+ csrf_token_key +'=' + csrf_token_value +
            '&group_id=' + group_id +
            '&arg=edit' +
            '&a=wiki' +
            '&page_name=' + page_name;
}


/**
 * this function grabs the contents of the wiki text area, 
 * converts them into html then populates the preview 
 * div with parsed html contents. It takes in a page id and group id that are 
 * required to render image urls, If any exist.
 * @param {type} page_id
 * @param {type} group_id
 */
function renderPreview(page_id, group_id) 
{
    if (event.preventDefault)
        event.preventDefault();
    event.returnValue = false;
    var textArea = document.getElementById('wiki-page');
    var div = document.getElementById('wiki-preview');
    div.innerHTML = "<hr>" 
            + "<p>"
            + parseWikiContent(
            textArea.value,
            group_id,
            page_id) + "</p> <hr>";
}