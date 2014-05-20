/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2014
 * @filesource
 */


/* 
 The editor automatically renders the 
 editor buttons using this object as 
 configuration data.
 below is a valid list of button names
 {
 "wikibtn-bold"
 "wikibtn-italic"
 "wikibtn-underline"
 "wikibtn-strike"
 "wikibtn-nowiki"
 "wikibtn-hyperlink"
 "wikibtn-bullets"
 "wikibtn-numbers"
 "wikibtn-hr"
 "wikibtn-heading"
 "wikibtn-yioop"
 "wikibtn-table" 
 }
 */

/*
 global flag variable to check if browser is Older MSIE.
 */
var is_msie = false;

/*
 Arrays that store button configuration
 */
var all_buttons = [];
var buttons = [];

/*
    Object that buffers selection information.
 */
var buffer = {};


/**
 * editorize all the textareas on a page.
 * @param none
 * @returns undefined
 */
function editorizeAll()
{
    var text_areas = document.getElementsByTagName("textarea");
    var len = text_areas.length;
    var ids = new Array();

    for(i=0; i<len; i++)
    {
        if(text_areas[i].id){
            editorize(text_areas[i].id);
        }
    }
}

/*
 * User will be able to call this function to editor-ize the textareas.
 * textarea id is passed to keep track of button rendering and
 * button operation.
 * @param string id id of the text area to be editor-ized
 * @returns undefined
 */
function editorize(id)
{
    /*
     check if the editor div exists.
     */
    var node = document.getElementById(id);
    if (node === null) {
        /*
         If the div to render wiki_editor is not found, do nothing.
         */
        alert("No textarea found with id = " + id);
        return false;
    }

    if(document.selection){
        is_msie = true;
    }

    var button_string = "";

    /*
        Initialize tool bar buttons object
     */
    buttons[id] = {};

    /*
        filter the buttons according to the data-buttons
        attribute.
     */
    filterButtons(id);

    /*
        Buttons are rendered below.
     */
    for (var prop in buttons[id]) {
        var no_buttons = ['wikibtn-yioop', 'wikibtn-table', 'wikibtn-heading'];
        if (buttons[id].hasOwnProperty(prop)
            && (no_buttons.indexOf(prop) === -1)) {
            button_string += '<input type="button" class="' + prop;
            button_string += '" id="';
            button_string += prop;
            button_string += '" onmousedown="wikifySelection(\'';
            button_string += prop + '\',\''+ id +'\');">';
        }
    }

    /*
        Render the wiki-popup-prompt div used to prompt
        user input.
     */
    var editor_toolbar = '<div id="wiki-popup-prompt"';
    editor_toolbar += ' class="wiki-popup-prompt"';
    editor_toolbar += ' style="display: none;">';
    editor_toolbar += '</div>';
    editor_toolbar += '<div id="wiki-buttons-div" ';
    editor_toolbar += 'class="wiki-buttons-div">';
    editor_toolbar += button_string;

    /*
        check if heading was desired and render if was.
     */
    if (buttons[id].hasOwnProperty('wikibtn-heading')) {
        editor_toolbar += '<select class="heading" id="wikibtn-heading" ';
        editor_toolbar += ' value="heading" ';
        editor_toolbar += 'onchange="setHeading(\''+ id +'\');">';
        editor_toolbar += '<option selected="" ';
        editor_toolbar += 'disabled="">Heading</option>';
        editor_toolbar += '<option value="1">H1</option>';
        editor_toolbar += '<option value="2">H2</option>';
        editor_toolbar += '<option value="3">H3</option>';
        editor_toolbar += '<option value="4">H4</option>';
        editor_toolbar += '</select>';
    }

    /*
     check if table was desired and render if was.
     */
    if (buttons[id].hasOwnProperty('wikibtn-table')) {
        editor_toolbar += '<input type="button" class="wikibtn-table" ';
        editor_toolbar += ' id="wikibtn-table" ';
        editor_toolbar += 'onmousedown="insertTable(\''+ id +'\');">';
    }

    /*
     check if yioop search was desired and render if was.
     */
    if (buttons[id].hasOwnProperty('wikibtn-yioop')) {
        editor_toolbar += '<input type="button" ';
        editor_toolbar += ' class="wikibtn-yioop-search-widget"';
        editor_toolbar += 'id="wikibtn-yioop-search-widget"';
        editor_toolbar += 'onmousedown="insertYioopSearch(\''+ id +'\');">';
    }

    editor_toolbar += '</div><br>';

    /*
        Insert the toolbar div before the textarea
     */
    node.insertAdjacentHTML("beforebegin", editor_toolbar);

    return;
}


/**
 * Method to return standard buttons as an object.
 * @param none
 * @returns object
 */
function getStandardButtonsObject()
{
    return {
        'wikibtn-bold': ['---', '---'],
        'wikibtn-italic': ['--', '--'],
        'wikibtn-underline': ['<u>', '</u>'],
        'wikibtn-strike': ['<s>', '</s>'],
        'wikibtn-nowiki': ['<nowiki>', '</nowiki>'],
        'wikibtn-hyperlink': ['[', ']'],
        'wikibtn-bullets': ['* ' + tl['wiki_js_bullet'] + ' \n'],
        'wikibtn-numbers': ['# ' + tl['wiki_js_enum'] + ' \n'],
        'wikibtn-hr': ['---- \n']
    };
}

/**
 * Filters the buttons according to exclusion/inclusion rules.
 * If the 'data-buttons' attribute value starts with 'all' - Only the buttons
 * need to be excluded should be followed, with their name specified with a '!'
 * prefix.
 * Any buttons with no '!' prefix will eb ignored
 * example : all,!bol,!italic will render editor with all buttns excluding bold
 * and italic
 *
 * If the 'data-buttons' attribute value does not starts with 'all', any button
 * names followed will only be included in the editor.
 * Any buttons with '!' prefix will be ignored.
 * example : bol,italic will render editor only with bold and italic buttons.
 *
 * @param no params passed. Uses global vars.
 * @return undefined
 */
function filterButtons(textarea_id)
{
    all_buttons[textarea_id] = getStandardButtonsObject();
    all_buttons[textarea_id]['wikibtn-heading'] = '';
    all_buttons[textarea_id]['wikibtn-yioop'] = '';
    all_buttons[textarea_id]['wikibtn-table'] = '';

    var wiki_text_div = document.getElementById(textarea_id);
    var wiki_buttons = wiki_text_div.getAttribute("data-buttons");
    if (wiki_buttons) {
        var wiki_buttons_array = wiki_buttons.split(',');
        var buttons_array_length = wiki_buttons_array.length;
        var included_buttons = new Array();
        var excluded_buttons = new Array();
        var exc = false;
        if (wiki_buttons_array[0].trim() === 'all') {
            exc = true;
        }
        for (var i = 0; i < buttons_array_length; i++) {
            wiki_buttons_array[i] = wiki_buttons_array[i].trim();
            var firstChar = wiki_buttons_array[i].charAt(0);

            if (wiki_buttons_array[i] &&
                    (exc === true) &&
                    firstChar === '!') {
                wiki_buttons_array[i] = wiki_buttons_array[i].substr(1);

                if (all_buttons[textarea_id].hasOwnProperty(
                    wiki_buttons_array[i])
                    ) {
                    excluded_buttons.push(wiki_buttons_array[i]);
                    delete all_buttons[textarea_id][wiki_buttons_array[i]];
                }
            } else if (wiki_buttons_array[i] &&
                    (exc === false)) {

                if (all_buttons[textarea_id].hasOwnProperty(
                    wiki_buttons_array[i])
                   ) {
                    included_buttons.push(wiki_buttons_array[i]);
                    buttons[textarea_id][wiki_buttons_array[i]] =
                            all_buttons[textarea_id][wiki_buttons_array[i]];
                }
            }
        }
    } else {
        buttons[textarea_id] = all_buttons[textarea_id];
    }

    if (Object.keys(buttons[textarea_id]).length === 0) {
        buttons[textarea_id] = all_buttons[textarea_id];
    }

    return;
}

/**
 * Util function to create a Dom element,
 * given an element name and an object with
 * attributes as key value pairs.
 *
 * @param string element_name is the tagname of the element to be
 * created and returned.
 * @param object attributes is a javascript object of attributes sent
 * as key value pairs.
 * @return Element
 */
function createElement(element_name, attributes)
{
    var element_x = document.createElement(element_name);

    for (var property in attributes) {
        if (attributes.hasOwnProperty(property)) {
            element_x.setAttribute(property, attributes[property]);
        }
    }
    return element_x;
}

/**
 * creates a label element and returns it.
 *
 * @param string for_att the attribute for value in the label.
 * @param string label_name the value need to eb assigned.
 * @returns Element label
 */
function createLabel(for_att, label_name)
{
    var label = createElement("label", {
        "for": for_att
    });

    label.innerHTML = label_name;

    return label;
}

/**
 * This is a util function to
 * create a dropdown element.
 *
 * @param string name of the dropdown, used for class,id & value attributes.
 * @param string function name to be called "onchange".
 * @param object option parameters in the dropdown sent as a js object.
 * @param string default_selected name of the default selected option.
 */
function createDropDown(dropdown_name, action, options, default_selected_text)
{
    var select = createElement("select", {
        "class": dropdown_name,
        "id": dropdown_name,
        "value": dropdown_name,
        "onchange": action + "();"
    });
    var option = document.createElement("option");
    if (default_selected_text) {
        option.innerHTML = default_selected_text;
    }
    option.setAttribute("selected", "");
    option.setAttribute("disabled", "");
    select.add(option);
    for (var optionObj in options) {
        if (options.hasOwnProperty(optionObj)) {
            var option = document.createElement("option");
            option.text = optionObj;
            option.value = options[optionObj];
            select.add(option);
        }
    }
    return select;
}

/**
 * This function returns the selection(from text_area)
 * information in an object. The obejct contains
 * selected text, entre prefix and entire suffix strings to the
 * selected text.
 *
 * @param no params passed.
 * @return object
 */
function getSelection(textarea_id)
{
    /*
     Select the DOM element - wikiText
     */
    var text_area = document.getElementById(textarea_id);

    /*
     IE?
     */
    if (document.selection) {
        var selection_bookmark = document.selection
                .createRange()
                .getBookmark();
        var sel = text_area.createTextRange();
        sel.moveToBookmark(selection_bookmark);
        var sleft = text_area.createTextRange();
        sleft.collapse(true);
        sleft.setEndPoint("EndToStart", sel);
        text_area.selectionStart = sleft.text.length;
        text_area.selectionEnd = sleft.text.length + sel.text.length;
        text_area.selectedText = sel.text;
        var selected_text_prefix = text_area.value.substring(0,
                text_area.selectionStart);
        var selection = sel.text;
        var selected_text_suffix = text_area.value.substring(
                text_area.selectionEnd,
                text_area.textLength);
    }
    /*
     Mozilla?
     */
    else if (typeof (text_area.selectionStart) !== "undefined") {
        /**
         Things are pretty straight forward in Mozilla based
         browsers & IE > 10.
         We can get the selectionStart & selectionEnd character
         position directly.
         Then compute the prefix and suffix using substr method.
         */
        var selected_text_prefix = text_area.value.substr(0,
                text_area.selectionStart);
        var selection = text_area.value.substr(
                text_area.selectionStart,
                text_area.selectionEnd - text_area.selectionStart);
        var selected_text_suffix = text_area.value.substr(
                text_area.selectionEnd);
    }
    var obj = {};
    obj.selection = selection;
    obj.selected_text_prefix = selected_text_prefix;
    obj.selected_text_suffix = selected_text_suffix;
    setCaretPosition(text_area, text_area.selectionEnd);
    return obj;
}

/**
 * MSIE has a problem displaying \n as libe breaks
 * we use this fucntions to normalise all the libebreaks
 * to be backward compatible with MSIE.
 *
 * @param string text to be normalized.
 * @return string
 */
function normalizeNewlinesForMSIE(text)
{
    return text.replace(/(\r\n|\r|\n)/g, '\n\r');
}


/**
 * This function can be used to set
 * the caret position in the ctrl object.
 *
 * @param element ctrl element in which the caret has to be set.
 * @param int pos position of the caret to be set.
 * @return undefined
 */
function setCaretPosition(ctrl, pos)
{
    if (ctrl.setSelectionRange)
    {
        ctrl.focus();
        ctrl.setSelectionRange(pos, pos);
    }
    else if (ctrl.createTextRange) {
        var range = ctrl.createTextRange();
        range.collapse(true);
        range.moveEnd('character', pos);
        range.moveStart('character', pos);
        range.select();
    }
}

/**
 * This will check for type of the button to
 * render(looking at the array length, and renders the button
 * on the editor.
 *
 * @param string name identifier to the wiki task to be performed.
 * @return undefined
 */
function wikifySelection(name, textarea_id)
{
    var length = 0;
    if (is_msie) {
        for (var prop in buttons[textarea_id]) {
            if (buttons[textarea_id].hasOwnProperty(prop)) {
                if (prop === name) {
                    length = buttons[textarea_id][prop].length;
                }
            }
        }
    } else {
        length = buttons[textarea_id][name].length;
    }

    if (length === 2) {
        wikify(
           buttons[textarea_id][name][0].replace(new RegExp('-', 'g'), "\'"),
           buttons[textarea_id][name][1].replace(new RegExp('-', 'g'), "\'"),
           name,
           textarea_id
        );
    } else if (length === 1) {
        insertTextAtCursor(buttons[textarea_id][name][0],textarea_id);
    } else {
        return;
    }
}

/**
 * This is the main function that takes
 * prefix and suffix characters for wikifying
 * selected text. Selected text will be obtained using
 * the function-getSelection();
 *
 * @param string wiki_prefix prefix to be added to wikify the selected text.
 * @param string wiki_suffix suffix to be added to wikify the selected text.
 * @param string task_name name of the task to render default selection text.
 * @return undefined
 */
function wikify(wiki_prefix, wiki_suffix, task_name, textarea_id)
{
    var br = '';
    /*
     Select the DOM element - wikiText
     */
    var text_area = document.getElementById(textarea_id);
    var obj = getSelection(textarea_id);
    var selection = obj.selection;
    var selected_text_prefix = obj.selected_text_prefix;
    var selected_text_suffix = obj.selected_text_suffix;
    if (!selection && wiki_prefix === '[') {
        if (!is_msie) {
            wiki_popup_prompt = document.getElementById('wiki-popup-prompt');
            if (wiki_popup_prompt.hasChildNodes()) {
                /*
                 Remove childnodes, if any exist.
                 */
                while (wiki_popup_prompt.hasChildNodes()) {
                    wiki_popup_prompt.removeChild(wiki_popup_prompt.lastChild);
                }
            }
            wiki_popup_prompt.appendChild(createHyperlinkForm(textarea_id));
            buffer = obj;
            popupToggle();
        } else {
            requestHyperlinkInput(textarea_id);
        }
    }

    if (!selection) {
        br = '\n';
        selection = tl[task_name.replace('wikibtn-', 'wiki_js_')];
    }

    /*
     Now Add the wrap the selected text between the wiki stuff,
     and then wrap the selected
     text between actual selected text's prefix and suffix.
     Replace the text_area contents with the result.
     */
    if(selection){
    text_area.value = selected_text_prefix +
            wiki_prefix +
            selection +
            wiki_suffix + br +
            selected_text_suffix;
    }
}

/*
 * This is a special fucntion for processing user input
 * when the user clicks on hyperlink, and inserts a hyper link.
 *
 * @returns String
 */
function requestHyperlinkInput(textarea_id)
{
    if (!is_msie) {
        popupToggle();

        var title = document
                .getElementById('wikify-enter-link-title-placeholder')
                .value;
        var link = document
                .getElementById('wikify-enter-link-target-placeholder')
                .value;
        if (!title || !link) {
            return false;
        }


    } else {
        var title = prompt(tl['wiki_js_enter_link_title_placeholder'],
                tl['wiki_js_enter_link_title_placeholder']);
        var link = prompt(tl['wiki_js_enter_link_placeholder'],
                tl['wiki_js_enter_link_placeholder']);
        if (!title || !link) {
            return undefined;
        }

    }

    insertTextAtCursor("[" + link + " "  + title + "]" + '\n',textarea_id);
}

/**
 * This function takes in number of rows, columns and
 * header_text(if desired) and constructs a table in wiki markup.
 *
 * @param int rows number of rows intended in the table.
 * @param int cols number of cols intended in the table.
 * @param string example_text placeholder text to be inserted in each
 * table field.
 * @param string header_text if header text is intended, placeholder
 * text for column header.
 * @return string
 */
function createWikiTable(rows, cols, example_text, header_text)
{
    var br = "\n";
    var table = "{|" + br;
    for (i = 0; i < rows; i++) {
        if (header_text && i === 0) {
            table = table + "|- " + br;
            table = table + "! ";
            for (j = 0; j < cols; j++) {
                table = table + header_text + " ";
                if (j < (cols - 1)) {
                    table = table + "!!" + " ";
                } else {
                    table = table + br;
                }
            }
        }
        table = table + "|- " + br;
        table = table + "| ";

        for (j = 0; j < cols; j++) {
            table = table + example_text + " ";
            if (j < (cols - 1)) {
                table = table + "||" + " ";
            } else {
                table = table + br;
            }
        }
    }
    table = table + "|}";
    return table;
}

/**
 * function to insert
 * yioop search widget.
 *
 * @param no params passed.
 * @return undefined
 */
function insertYioopSearch(textarea_id)
{
    if (!is_msie) {
        wiki_popup_prompt = document.getElementById('wiki-popup-prompt');

        if (wiki_popup_prompt.hasChildNodes()) {
            /*
             Remove childnodes, if any exist.
             */
            while (wiki_popup_prompt.hasChildNodes()) {
                wiki_popup_prompt.removeChild(
                    wiki_popup_prompt.lastChild
                );
            }
        }
        wiki_popup_prompt.appendChild(
            createYioopWidgetInputForm(
                textarea_id
            )
        );
        popupToggle();
    } else {
        useInputForYioop(textarea_id);
    }
}

/**
 * For newer browsers , this uses overlay input form to get the
 * size of the Yioop widget to load.
 *
 * @returns undefined
 */
function useInputForYioop(textarea_id)
{
    if (is_msie) {
        var size = prompt(tl['wiki_js_prompt_search_size']);
    } else {
        popupToggle();
        var element = document.getElementById(
            "wikify-prompt-for-yioop-size"
        );
        var size = element.options[element.selectedIndex].text;
    }
    var widget_obj = {};
    var yioop_obj = {};
    yioop_obj.size = size;
    widget_obj.search = objToString(yioop_obj);

    insertTextAtCursor(
        "{" + objToString(widget_obj) + "}\n",
        textarea_id
    );
}

/**
 * Util function to Stringify an JS Object
 *
 * @param object js_object the javascript object to be converted to string.
 * @return string
 */
function objToString(js_object)
{
    var json_string = [];
    for (var property in js_object) {
        if (js_object.hasOwnProperty(property)) {
            json_string.push('"' +
                    property +
                    '"' +
                    ':' +
                    js_object[property]
                    );
        }
    }
    json_string.push();
    return '{' + json_string.join(',') + '}';
}

/**
 * This is invoked by the editor to insert a wiki table.
 * This functions takes care okf the user input for rows/columns/etc
 * and leverages createWikiTable function to construct the table.
 *
 * @param no params passed
 * @return undefined
 */
function insertTable(textarea_id)
{
    if (!is_msie) {
        wiki_popup_prompt = document.getElementById('wiki-popup-prompt');

        if (wiki_popup_prompt.hasChildNodes()) {
            /*
             Remove childnodes, if any exist.
             */
            while (wiki_popup_prompt.hasChildNodes()) {
                wiki_popup_prompt.removeChild(wiki_popup_prompt.lastChild);
            }
        }

        wiki_popup_prompt.appendChild(
            createTableCreationPromptElement(textarea_id)
        );
        popupToggle();
    } else {
        useInputForTable(textarea_id);
    }

}


/**
 * accepts input from user to create a table.
 * For older MSIE browsers prompts are used and for newer browsers an overlay
 * popup is rendered to take input.
 * @param none.
 * @returns undefined
 */
function useInputForTable(textaread_id)
{
    if (is_msie) {
        var cols = prompt(tl['wiki_js_prompt_for_table_cols']);
        var rows = prompt(tl['wiki_js_prompt_for_table_rows']);
        var headcheck = confirm(tl['wiki_js_prompt_heading']);

    } else {
        var rows = document
                .getElementById('wiki-prompt-cols')
                .value;
        var cols = document
                .getElementById('wiki-prompt-cols')
                .value;
        var headcheck = document
                .getElementById('wiki-prompt-insert-heading')
                .checked;
    }

    insertTableFromInput(rows, cols, headcheck, textaread_id);
}

/**
 * Using the input from the user, like number of rows,cols etc.
 * builds a table in wiki markup and inserts at cursor.
 * @param int rows number of rows desired
 * @param int cols number of cols desired
 * @param boolean headcheck heading desired or not.
 * @returns undefined
 */
function insertTableFromInput(rows, cols, headcheck, textaread_id)
{
    if (!is_msie) {
        popupToggle();
    }

    if (!cols || !rows) {
        return;
    }

    if (headcheck) {
        var table = createWikiTable(
                rows,
                cols,
                tl['wiki_js_example_placeholder'],
                tl['wiki_js_table_title_placeholder']);
    } else {
        table = createWikiTable(
                rows,
                cols,
                tl['wiki_js_example_placeholder']);
    }
    insertTextAtCursor(table + '\n', textaread_id);
}

/**
 * Accepts text as parameter to be inserted at caret's
 * current position.
 *
 * @param string text string that is intended to be placed at current cursor.
 * @return undefined
 */
function insertTextAtCursor(text,textaread_id)
{
    var field = document.getElementById(textaread_id);
    if (document.selection) {
        var range = document.selection.createRange();
        if (!range || range.parentElement() !== field) {
            field.focus();
            range = field.createTextRange();
            range.collapse(false);
        }
        range.text = text;
        range.collapse(false);
        range.select();
    } else {
        field.focus();
        var val = field.value;
        var selStart = field.selectionStart;
        var caretPos = selStart + text.length;
        field.value = val.slice(0, selStart) +
                text + val.slice(field.selectionEnd);
        field.setSelectionRange(caretPos, caretPos);
    }
}

/**
 * Used to set the headings
 * for selected text.
 *
 * @param no params passed.
 * @return undefined
 */
function setHeading(textarea_id)
{
    var heading_size = document.getElementById("wikibtn-heading").value;
    var markup_text = fillChars("=",
            heading_size);
    document.getElementById("wikibtn-heading").selectedIndex = 0;
    wikify(
        markup_text,
        markup_text,
        "wikibtn-heading" + heading_size ,
        textarea_id
    );
}

/**
 * This is a simple util
 * function to fill a character
 * array and return as a string,
 * given a character and array count.
 *
 * @param string c caharacter to be filled into an array.
 * @param int n number of times the character to eb repeated.
 * @return string
 */
function fillChars(c, n)
{
    for (var e = ""; e.length < n; ) {
        e += c;
    }
    return e;
}

/**
 * This toggles the popup used for
 * prompting user input.
 * @returns boolean
 */
function popupToggle()
{
    var wiki_popup_prompt_div = document.getElementById(
        'wiki-popup-prompt'
    );
    if(wiki_popup_prompt_div.style.display == "none"){
        wiki_popup_prompt_div.style.display = "";
    }else{
        wiki_popup_prompt_div.style.display = "none";
    }
    return false;
}


/**
 *
 * Created an input prompt HTML elemtn with 2 text fields and a checkbox.
 * @param no params passed
 * @returns Element
 */
function createTableCreationPromptElement(textarea_id)
{

    var wiki_prompt_for_table = createElement("div", {
        "id": 'wiki-prompt-for-table',
        "class": 'wiki-prompt-for-table',
        "style": 'margin-top:7cm;'
    });

    wiki_prompt_for_table.appendChild(
            createLabel("rows", tl['wiki_js_prompt_for_table_rows'])
            );

    wiki_prompt_for_table.appendChild(createElement("input", {
        "type": "text",
        "name": 'wiki-prompt-rows',
        "id": 'wiki-promt-rows'
    }));

    wiki_prompt_for_table.appendChild(document.createElement('br'));

    wiki_prompt_for_table.appendChild(
            createLabel("cols", tl['wiki_js_prompt_for_table_cols']
                    ));

    wiki_prompt_for_table.appendChild(createElement("input", {
        "type": "text",
        "name": 'wiki-prompt-cols',
        "id": 'wiki-prompt-cols'
    }));

    wiki_prompt_for_table.appendChild(document.createElement('br'));

    var checkbox = createElement("input", {
        "type": "checkbox",
        "name": 'wiki-prompt-insert-heading',
        "id": 'wiki-prompt-insert-heading'
    });
    wiki_prompt_for_table.appendChild(
            document.createTextNode(tl['wiki_js_prompt_heading']
                    ));
    wiki_prompt_for_table.appendChild(checkbox);
    wiki_prompt_for_table.appendChild(document.createElement('br'));
    var submitbtn = createElement("button", {
        "name": 'submit',
        "onmousedown": "useInputForTable('"+textarea_id+"')"
    });
    submitbtn.innerHTML = tl['wiki_js_formbtn_submit'];
    wiki_prompt_for_table.appendChild(submitbtn);
    var closebtn = createElement("button", {
        "name": 'close',
        "onmousedown": "popupToggle()"
    });
    closebtn.innerHTML = tl['wiki_js_formbtn_cancel'];
    wiki_prompt_for_table.appendChild(closebtn);
    return wiki_prompt_for_table;
}

/**
 * Creates an input prompt HTML elemtn with 2 text fields for link title
 * and link target.
 * @param no params passed
 * @returns Element
 */
function createHyperlinkForm(textarea_id)
{
    var wiki_prompt_for_hyperlink = createElement("div", {
        "id": 'wiki-prompt-for-hyperlink',
        "class": 'wiki-prompt-for-hyperlink',
        "style": 'margin-top:7cm;'
    });

    wiki_prompt_for_hyperlink.appendChild(
            createLabel(
                    "wikify-enter-link-title-placeholder",
                    tl['wiki_js_enter_link_title_placeholder']));
    wiki_prompt_for_hyperlink.appendChild(createElement("input", {
        "type": "text",
        "name": 'wikify-enter-link-title-placeholder',
        "id": 'wikify-enter-link-title-placeholder'
    }));
    wiki_prompt_for_hyperlink.appendChild(document.createElement('br'));
    wiki_prompt_for_hyperlink.appendChild(
            createLabel(
                    "wikify-enter-link-target-placeholder",
                    tl['wiki_js_enter_link_placeholder']));
    wiki_prompt_for_hyperlink.appendChild(createElement("input", {
        "type": "text",
        "name": 'wikify-enter-link-target-placeholder',
        "id": 'wikify-enter-link-target-placeholder'
    }));
    wiki_prompt_for_hyperlink.appendChild(document.createElement('br'));
    var submitbtn = createElement("button", {
        "name": 'submit',
        "onmousedown": "requestHyperlinkInput('"+ textarea_id +"')"
    });
    submitbtn.innerHTML = tl['wiki_js_formbtn_submit'];

    wiki_prompt_for_hyperlink.appendChild(submitbtn);
    var closebtn = createElement("button", {
        "name": 'close',
        "onmousedown": "popupToggle()"
    });
    closebtn.innerHTML = tl['wiki_js_formbtn_cancel'];
    wiki_prompt_for_hyperlink.appendChild(closebtn);
    return wiki_prompt_for_hyperlink;
}

/**
 * Creates the input fields on a form for yioop search widget.
 * @param none
 * @returns undefined
 */
function createYioopWidgetInputForm(textarea_id)
{
    var wiki_prompt_for_yioop = createElement("div", {
        "id": 'wiki-prompt-for-yioop',
        "class": 'wiki-prompt-for-yioop',
        "style": 'margin-top:7cm;'
    });
    wiki_prompt_for_yioop.appendChild(
            document.createTextNode(
                    tl['wiki_js_prompt_search_size']
                    ));
    wiki_prompt_for_yioop.appendChild(document.createElement('br'));

    var options = {};
    options[tl['wiki_js_search_size_small']] = 1;
    options[tl['wiki_js_search_size_medium']] = 2;
    options[tl['wiki_js_search_size_large']] = 3;

    wiki_prompt_for_yioop.appendChild(createDropDown(
            "wikify-prompt-for-yioop-size",
            tl['wiki_js_prompt_search_size'],
            options
    , tl['wiki_js_search_size']));
    wiki_prompt_for_yioop.appendChild(document.createElement('br'));
    var submitbtn = createElement("button", {
        "name": 'submit',
        "onmousedown": "useInputForYioop('"+ textarea_id +"')"
    });
    submitbtn.innerHTML = tl['wiki_js_formbtn_submit'];

    wiki_prompt_for_yioop.appendChild(submitbtn);
    var closebtn = createElement("button", {
        "name": 'close',
        "onmousedown": "popupToggle()"
    });
    closebtn.innerHTML = tl['wiki_js_formbtn_cancel'];
    wiki_prompt_for_yioop.appendChild(closebtn);
    return wiki_prompt_for_yioop;
}

/* PolyFill code start */


/*
 If the indexOf is not supported in the current browser
 add the support.
 */
if (!('indexOf' in Array.prototype)) {
    Array.prototype.indexOf= function(element, array_index)
    {
        if (array_index===undefined) array_index= 0;
        if (array_index<0) array_index+= this.length;
        if (array_index<0) array_index= 0;
        for (var n= this.length; array_index<n; array_index++)
            if (array_index in this && this[array_index]===element)
                return array_index;
        return -1;
    };
}

/*
 Adds Object.Keys support for older browsers. Not required for newer browsers.
 https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference
 */
if (!Object.keys) {
    Object.keys = (function ()
    {
        'use strict';
        var hasOwnProperty = Object.prototype.hasOwnProperty,
            hasDontEnumBug = !({toString: null})
                .propertyIsEnumerable('toString'),
            dontEnums = [
                'toString',
                'toLocaleString',
                'valueOf',
                'hasOwnProperty',
                'isPrototypeOf',
                'propertyIsEnumerable',
                'constructor'
            ],
            dontEnumsLength = dontEnums.length;

        return function (obj)
        {
            if (typeof obj !== 'object'
                && (typeof obj !== 'function' || obj === null)) {
                throw new TypeError('Object.keys called on non-object');
            }

            var result = [], prop, i;

            for (prop in obj) {
                if (hasOwnProperty.call(obj, prop)) {
                    result.push(prop);
                }
            }

            if (hasDontEnumBug) {
                for (i = 0; i < dontEnumsLength; i++) {
                    if (hasOwnProperty.call(obj, dontEnums[i])) {
                        result.push(dontEnums[i]);
                    }
                }
            }
            return result;
        };
    }());
}



/*
 Create String.trim if it's not natively available.
 */
if (!String.prototype.trim){
    String.prototype.trim = function()
    {
        return this.replace(/^\s+|\s+$/g, '');
    };
}

/*
 Create HTMLElement's toString functionaality
 */
HTMLElement.prototype.toString =  (function()
{
    var DIV = document.createElement("div");

    if ('outerHTML' in DIV)
        return function() {
            return this.outerHTML;
        };

    return function() {
        var div = DIV.cloneNode();
        div.appendChild(this.cloneNode(true));
        return div.innerHTML;
    };

})();


/*
 If insertAdjacentElement is not defined, add the functionality.
 */
if(typeof HTMLElement != "undefined"
        && !HTMLElement.prototype.insertAdjacentElement
    ){
    HTMLElement.prototype.insertAdjacentElement = function (position,dom_node)
    {
        switch (position){
            case 'beforebegin':
                this.parentNode.insertBefore(dom_node,this);
                break;
            case 'afterbegin':
                this.insertBefore(dom_node,this.firstChild);
                break;
            case 'beforeend':
                this.appendChild(dom_node);
                break;
            case 'afterend':
                if (this.nextSibling) {
                    this.parentNode.insertBefore(dom_node,this.nextSibling);
                }
                else {
                    this.parentNode.appendChild(dom_node);
                }
                break;
        }
    };

    /*
     insertAdjacentHTML is leverages insertAdjacentElement. It takes in
     htmlString to be inserted at a position instead of a HTML element.
     http://developer.mozilla.org/en-US/docs/Web/API/Element.insertAdjacentHTML
     */
    HTMLElement.prototype.insertAdjacentHTML = function (position,html_string)
    {
        var r = this.ownerDocument.createRange();
        r.setStartBefore(this);
        var html_node = r.createContextualFragment(html_string);
        this.insertAdjacentElement(position,html_node);
    };
}