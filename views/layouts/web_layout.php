<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010, 2011
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
    public function render($data) {
    ?>
    <!DOCTYPE html>

    <html lang="<?php e($data['LOCALE_TAG']);
        ?>" dir="<?php e($data['LOCALE_DIR']);?>">

        <head>
            <title><?php e(tl('web_layout_title')); ?></title>

            <meta name="Author" content="Christopher Pollett" />

            <meta name="description" content="<?php 
                e(tl('web_layout_description')); ?>" />
            <meta charset="utf-8" />
            <link rel="shortcut icon"   href="favicon.ico" />
            <link rel="stylesheet" type="text/css" href="css/search.css" />
            <linl rel="search" type="application/opensearchdescription+xml" 
                href="<?php e(QUEUE_SERVER."yioopbar.xml");?>"
                title="Content search" />

        </head>
        <body class="html-<?php e($data['BLOCK_PROGRESSION']);?> html-<?php 
            e($data['LOCALE_DIR']);?> html-<?php e($data['WRITING_MODE']);?>">
            <div id="message" ></div>
            <?php
                $this->view->renderView($data);
                if(QUERY_STATISTICS) { ?>
                <div id="query-statistics">
                <?php
                    e("<h1>".tl('web_layout_query_statistics')."</h1>");
                    e("<b>".tl('web_layout_total_elapsed_time',
                         $data['TOTAL_ELAPSED_TIME'])."</b>"); 
                    foreach($data['QUERY_STATISTICS'] as $query_info) {
                        e("<div class='query'><div>".$query_info['QUERY'].
                            "</div><div><b>".
                            tl('web_layout_query_time', 
                                $query_info['ELASPED_TIME']).
                                "</b></div></div>");
                    }
                ?>
                </div>
                <?php }
            ?>
            <script type="text/javascript" src="./scripts/basic.js" ></script>
            <script type="text/javascript" >
            <?php
            if(isset($data['SCRIPT'])) {
                e($data['SCRIPT']);
            }
            ?>
            </script>
        </body>
    </html>
    <?php
    }
}
?>
