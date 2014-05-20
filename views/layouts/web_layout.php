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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage layout
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Layout used for the seek_quarry Website
 * including pages such as search landing page
 * and settings page
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage layout
 */
class WebLayout extends Layout
{

    /**
     * Responsible for drawing the header of the document containing
     * Yioop! title and including basic.js. It calls the renderView method of
     * the View that lives on the layout. If the QUERY_STATISTIC config setting
     * is set, it output statistics about each query run on the database.
     * Finally, it draws the footer of the document.
     *
     *  @param array $data  an array of data set up by the controller to be
     *  be used in drawing the WebLayout and its View.
     */
    function render($data)
    {
    ?>
    <!DOCTYPE html>

    <html lang="<?php e($data['LOCALE_TAG']);
        ?>" dir="<?php e($data['LOCALE_DIR']);?>">

        <head>
        <title><?php if(isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['title']))
            e($this->view->head_objects[$data['page']]['title']);
        else e(tl('web_layout_title')); ?></title>
    <?php if(isset($this->view->head_objects['robots'])) {?>
        <meta name="ROBOTS" content="<?php
            e($this->view->head_objects['robots']) ?>" />
    <?php } ?>
        <meta name="description" content="<?php
        if(isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['description']))
                e($this->view->head_objects[$data['page']]['description']);
        else e(tl('web_layout_description')); ?>" />
        <meta name="Author" content="Christopher Pollett" />
        <meta name="description" content="<?php
            e(tl('web_layout_description')); ?>" />
        <meta charset="utf-8" />
        <?php if(MOBILE) {?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php }
            $path_info = (isset($_SERVER["PATH_INFO"])) ?
                $_SERVER["PATH_INFO"].'/' : '.';
        ?>
        <link rel="shortcut icon"
            href="<?php e($path_info); ?>favicon.ico" />
        <link rel="stylesheet" type="text/css"
            href="<?php e($path_info); ?>/css/search.css" />
            <link rel="stylesheet" type="text/css"
                  href="<?php e($path_info); ?>/css/editor.css" />
        <link rel="search" type="application/opensearchdescription+xml"
            href="<?php e(NAME_SERVER."yioopbar.xml");?>"
            title="Content search" />
        </head>
        <?php
            $data['MOBILE'] = (MOBILE) ? 'mobile': '';
        ?>

        <body class="html-<?php e($data['BLOCK_PROGRESSION']);?> html-<?php
            e($data['LOCALE_DIR']);?> html-<?php e($data['WRITING_MODE'].' '.
            $data['MOBILE']);?>" >
        <div id="message" ></div><?php
        $this->view->renderView($data);
        if(QUERY_STATISTICS) { ?>

        <div class="query-statistics">
        <?php
            e("<h1>".tl('web_layout_query_statistics')."</h1>");
            e("<div><b>".
                $data['YIOOP_INSTANCE']
                ."</b><br /><br />");
            e("<b>".tl('web_layout_total_elapsed_time',
                 $data['TOTAL_ELAPSED_TIME'])."</b></div>");
            foreach($data['QUERY_STATISTICS'] as $query_info) {
                e("<div class='query'><div>".$query_info['QUERY'].
                    "</div><div><b>".
                    tl('web_layout_query_time',
                        $query_info['ELAPSED_TIME']).
                        "</b></div></div>");
            }
        ?>

        </div>
        <?php
        }
        // Temp changes for testing.
        $data["INCLUDE_SCRIPTS"][] = "wikify";

        //set up an array of translation for javascript-land
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
        $data['SCRIPT'] .= " editorizeAll();";

        ?>

        <script type="text/javascript" src="./scripts/basic.js" ></script>
        <?php
        if(isset($data['INCLUDE_SCRIPTS'])) {
            foreach($data['INCLUDE_SCRIPTS'] as $script_name) {
                if($script_name == "math") {
                    e('<script type="text/javascript"
                        src="http://cdn.mathjax.org/mathjax/latest/MathJax.js?'.
                        'config=TeX-MML-AM_HTMLorMML"></script>');
                } else {
                    e('<script type="text/javascript"
                        src="'.$_SERVER["PATH_INFO"].'/scripts/'.
                        $script_name.'.js" ></script>');
                }
            }
        }
        if(isset($data['INCLUDE_LOCALE_SCRIPT'])) {
                e('<script type="text/javascript"
                    src="./locale/'.$data["LOCALE_TAG"].
                    '/resources/locale.js" ></script>');
        }
        ?>
        <script type="text/javascript" >
        <?php
        if(isset($data['SCRIPT'])) {
            e($data['SCRIPT']);
        }
        ?></script>
        </body>
    </html><?php
    }
}
?>
