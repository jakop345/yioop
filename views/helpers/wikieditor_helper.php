<?php
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com
 * @package seek_quarry
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if (!defined('BASE_DIR')) {
    echo "BAD REQUEST";
    exit();
}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR . "/views/helpers/helper.php";

/**
 * This is a helper class for adding a wikieditor to an
 * existing text area, by passing the textarea idas a parameter.
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage helper
 */

class WikiEditorHelper extends Helper
{

     function include_js(){
        if (isset($data["INCLUDE_SCRIPTS"])) {
            $data["INCLUDE_SCRIPTS"] = array();
        }

        $data["INCLUDE_SCRIPTS"][] = "wikify";

        //set up an array of translation for javascript-land
        if(isset($data['SCRIPT'])){
            $data['SCRIPT'] .= "tl = {".
                'wiki_js_search_size_small :"'.
                tl('wiki_js_search_size_small') .
                '",' . 'wiki_js_search_size_medium :"'.
                tl('wiki_js_search_size_medium').'",'.
                'wiki_js_search_size_large :"'.
                tl('wiki_js_search_size_large').'",'.
                'wiki_js_search_size :"'.
                tl('wiki_js_search_size').'",'.
                'wiki_js_prompt_heading :"'.
                tl('wiki_js_prompt_heading').'",'.
                'wiki_js_example_placeholder :"'.
                tl('wiki_js_example_placeholder').'",'.
                'wiki_js_table_title_placeholder :"'.
                tl('wiki_js_table_title_placeholder').'",'.
                'wiki_js_formbtn_submit :"'.
                tl('wiki_js_formbtn_submit').'",'.
                'wiki_js_formbtn_cancel :"'.
                tl('wiki_js_formbtn_cancel').'",'.
                'wiki_js_bold :"'.
                tl('wiki_js_bold') .
                '",' . 'wiki_js_italic :"'.
                tl('wiki_js_italic').'",'.
                'wiki_js_underline :"'.
                tl('wiki_js_underline').'",'.
                'wiki_js_strike :"'.
                tl('wiki_js_strike').'",'.
                'wiki_js_heading1 :"'.
                tl('wiki_js_heading1').'",'.
                'wiki_js_heading2 :"'.
                tl('wiki_js_heading2').'",'.
                'wiki_js_heading3 :"'.
                tl('wiki_js_heading3').'",'.
                'wiki_js_heading4 :"'.
                tl('wiki_js_heading4').'",'.
                'wiki_js_bullet :"'.
                tl('wiki_js_bullet').'",'.
                'wiki_js_enum :"'.
                tl('wiki_js_enum').'",'.
                'wiki_js_nowiki :"'.
                tl('wiki_js_nowiki').'",'.
                'wiki_js_prompt_search_size :"'.
                tl('wiki_js_prompt_search_size').'",'.
                'wiki_js_prompt_for_table_cols :"'.
                tl('wiki_js_prompt_for_table_cols').'",'.
                'wiki_js_prompt_for_table_rows :"'.
                tl('wiki_js_prompt_for_table_rows').'",'.
                'wiki_js_enter_link_title_placeholder :"'.
                tl('wiki_js_enter_link_title_placeholder').'",'.
                'wiki_js_enter_link_placeholder :"'.
                tl('wiki_js_enter_link_placeholder').'"'.
                '};';
        }

    }
    function render($textarea_id_array)
    {
        if (isset($textarea_id_array)) {
            $this->include_js();
            foreach ($textarea_id_array as $textarea_id) {
                $data['SCRIPT'] .= "editorize('" . $textarea_id . "');" . " \n";
            }
        }
    }

    function renderAll()
    {
        if (isset($textarea_id_array)) {
            $this->include_js();
            foreach ($textarea_id_array as $textarea_id) {
                $data['SCRIPT'] .= "editorize();" . " \n";
            }
        }
    }
}
?>