<?php
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
     * @param array $data  an array of data set up by the controller to be
     * be used in drawing the WebLayout and its View.
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
            isset($this->view->head_objects[$data['page']]['description'])) {
                e($this->view->head_objects[$data['page']]['description']);
        } else {
            e(tl('web_layout_description')); 
        } ?>" />
        <meta name="Author" content="<?php
            e(tl('web_layout_site_author')); ?>" />
        <meta charset="utf-8" />
        <?php if(MOBILE) {?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php }
            $path_info = (isset($_SERVER["PATH_INFO"])) ?
                $_SERVER["PATH_INFO"].'/' : '.';
            $aux_css = false;
            if(file_exists(APP_DIR.'/css/auxiliary.css')) {
                $aux_css = "./?c=resource&a=get&f=css&n=auxiliary.css";
            }
        ?>
        <link rel="shortcut icon"
            href="<?php e(FAVICON); ?>" />
        <link rel="stylesheet" type="text/css"
            href="<?php e($path_info); ?>/css/search.css" />
        <?php if($aux_css) { ?>
        <link rel="stylesheet" type="text/css"
            href="<?php e($aux_css); ?>" />
        <?php }?>
        <link rel="search" type="application/opensearchdescription+xml"
            href="<?php e(SEARCHBAR_PATH); ?>"
            title="Content search" />
        <?php if(isset($data['INCLUDE_STYLES'])) {
            foreach($data['INCLUDE_STYLES'] as $style_name) {
                e('<link rel="stylesheet" type="text/css"
                    href="'.$_SERVER["PATH_INFO"].'/css/'.
                    $style_name.'.css" />'."\n");
            }
        }
        ?>
        <style type="text/css">
        <?php
        $background_color = "#FFF";
        if(defined('BACKGROUND_COLOR')) {
            $background_color = isset($data['BACKGROUND_COLOR']) ?
                $data['BACKGROUND_COLOR'] : BACKGROUND_COLOR;
            ?>
            body
            {
                background-color: <?php e($background_color); ?>;
            }
            <?php
        }
        if(defined('BACKGROUND_IMAGE') && BACKGROUND_IMAGE) {
            $background_image = isset($data['BACKGROUND_IMAGE']) ?
                $data['BACKGROUND_IMAGE'] : BACKGROUND_IMAGE;
            ?>
            body
            {
                background-image: url(<?php e(html_entity_decode(
                    $background_image)); ?>);
                background-repeat: no-repeat;
                background-size: 11in;
            }
            body.mobile
            {
                background-size: 100%;
            }
            <?php
        }
        $foreground_color = "#FFF";
        if(defined('FOREGROUND_COLOR')) {
            $foreground_color = isset($data['FOREGROUND_COLOR']) ?
                $data['FOREGROUND_COLOR'] : FOREGROUND_COLOR;
            ?>
            .frame,
            .icon-upload,
            .current-activity,
            .light-content,
            .small-margin-current-activity,
            .suggest-list li span.unselected
            {
                background-color: <?php e($foreground_color); ?>;
            }
            .icon-upload
            {
                color: <?php e($foreground_color); ?>;
            }
            <?php
        }
        if(defined('SIDEBAR_COLOR')) {
            ?>
            .activity-menu h2
            {
                background-color: <?php if(isset($data['SIDEBAR_COLOR'])) {
                    e($data['SIDEBAR_COLOR']);
                } else {
                    e(SIDEBAR_COLOR);
                } ?>;
            }
            .light-content,
            .mobile .light-content
            {
                border: 16px solid <?php if(isset($data['SIDEBAR_COLOR'])) {
                    e($data['SIDEBAR_COLOR']);
                } else {
                    e(SIDEBAR_COLOR);
                } ?>;
            }
            <?php
        }
        if(defined('TOPBAR_COLOR')) {
            $top_color = (isset($data['TOPBAR_COLOR'])) ?
                $data['TOPBAR_COLOR'] : TOPBAR_COLOR;
            ?>
            .top-color,
            .suggest-list,
            .suggest-list li,
            .suggest-list li span.selected,
            .search-box {
                background-color: <?php e($top_color); ?>;
            }
            .top-bar,
            .landing-top-bar
            {
                background: <?php e($top_color); ?>;
                background: linear-gradient(to top, <?php
                    e($background_color); ?> 0%, <?php
                    e($top_color); ?> 30%, <?php e($top_color);
                    ?> 70%, <?php
                    e($background_color); ?> 100%);
            }
            <?php
        }
        ?>
        </style>
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
        ?>
        <script type="text/javascript" src="./scripts/basic.js" ></script>
        <?php
        if(isset($data['INCLUDE_SCRIPTS'])) {
            foreach($data['INCLUDE_SCRIPTS'] as $script_name) {
                if($script_name == "math") {
                    e('<script type="text/javascript"
                        src="https://cdn.mathjax.org/mathjax/latest/MathJax.js'.
                        '?config=TeX-MML-AM_HTMLorMML"></script>');
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
