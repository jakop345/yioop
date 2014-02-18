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
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";

/**
 * Used to create links to go backward/forwards and search a database
 * tables. HTML table with data representing a
 * database table  might have millions of rows so want to
 * limit what the user actually gets at one time and just
 * allow the user to "page" through in increments of
 * 10, 20, 50, 100, 200 rows at a time.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
class PagingtableHelper extends Helper
{
    /**
     *  The choices for how many rows out of the database table to display
     *  @var array
     */
    var $show_choices = array(
        10 => 10, 20 => 20, 50 => 50, 100 => 100, 200=> 200
    );

    /**
     *  Used to render the  links to go
     *  backwards and forwards through a databse table. We have two separate
     *  functions for the mobile and desktop drawers.
     */
    function render($data)
    {
        if(MOBILE) {
            $this->mobileTableControls($data);
        } else {
            $this->desktopTableControls($data);
        }
    }

    /**
     *  Draws the heading before a paging table as well as the controls
     *  for what rows to see (mobile phone case).
     *
     *  @param array $data needed for dropdown values for number of groups to
     *      display
     */
    function mobileTableControls($data)
    {
        $activity = $data['ACTIVITY'];
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=$activity";
        ?>
        <h2><?php e($data['TABLE_TITLE']); ?>&nbsp;&nbsp;[<a 
                href="<?php e($base_url . '&amp;arg=search');
                ?>"><?php e(tl('pagingtable_helper_search'));?></a>]</h2>
        <div>
            <form  method="get" action='#' >
            <?php
            $name = isset($data['NAME']) ? $data['NAME'] : "";
            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if($name != "") {
                $bound_url .="&amp;name=".$name;
            } ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="<?php e($activity); ?>" />
            <?php
            e("<b>".tl('pagingtable_helper_show')."</b>");
            $data['VIEW']->helper("options")->render(
                "num-show", "num_show", $this->show_choices,
                $data['num_show'], true);
            e("<br />");
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;num_show=<?php 
                    e($data['num_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('pagingtable_helper_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_TOTAL'])."</b>");
            if($data['END_ROW'] < $data['NUM_TOTAL']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;num_show=<?php 
                    e($data['num_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            </form>
        </div>
        <?php
    }

    /**
     *  Draws the heading before the user table as well as the controls
     *  for what user to see (desktop, laptop, tablet case).
     *
     *  @param array $data needed for dropdown values for number of groups to
     *      display
     */
    function desktopTableControls($data)
    {
        $activity = $data['ACTIVITY'];
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=$activity";
        $class = 'class="table-margin float-opposite"';
        $top = false;
        if(isset( $data['NO_FLOAT_TABLE'] ) &&  $data['NO_FLOAT_TABLE'] ){
            $class = "";
            $top = true;
        }
        if($top) {
            ?>
            <h2><?php e($data['TABLE_TITLE']); ?></h2>
            <?php
        }
        ?>
        <div <?php e($class); ?>>
            <form  method="get" action='#' >
            <?php
            $name = isset($data['NAME']) ? $data['NAME'] : "";

            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if($name != "") {
                $bound_url .="&amp;name=".$name;
            }
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;show=<?php 
                    e($data['num_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('pagingtable_helper_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_TOTAL'])."</b>");
            if($data['END_ROW'] < $data['NUM_TOTAL']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;num_show=<?php 
                    e($data['num_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="<?php e($activity); ?>" />
            <?php
                e("<b>".tl('pagingtable_helper_show')."</b>");
                $data['VIEW']->helper("options")->render(
                    "num-show", "num_show",  $this->show_choices,
                    $data['num_show'], true);
            ?>
            [<a href="<?php e($base_url . '&amp;arg=search');
                ?>"><?php e(tl('pagingtable_helper_search'));?></a>]
            </form>
        </div>
        <?php if(!$top) {
            ?>
            <h2><?php e($data['TABLE_TITLE']); ?></h2>
            <?php
        }
    }
}
?>
