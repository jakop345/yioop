<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This view is used to display information about
 * crawls that have been made by this seek_quarry instance
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class CrawlstatusView extends View
{

    /**
     * An Ajax call from the Manage Crawl Element in Admin View triggers 
     * this view to be instantiated. The renderView method then draws statistics
     * about the currently active crawl.The $data is supplied by the crawlStatus
     * method of the AdminController.
     *
     * @param array $data   info about the current crawl status
     */ 
    public function renderView($data) {
        $base_url = "?c=admin&a=manageCrawl&YIOOP_TOKEN=".
            $data['YIOOP_TOKEN']."&arg=";
        ?>

        <h2><?php e(tl('crawlstatus_view_currently_processing')); ?></h2>
        <p><b><?php e(tl('crawlstatus_view_description')); ?></b> <?php 
        if(isset($data['DESCRIPTION'])) {
            e($data['DESCRIPTION']);
            ?>&nbsp;&nbsp;
            <button class="buttonbox" type="button" 
                onclick="javascript:document.location = '<?php 
                e($base_url); ?>stop'" ><?php 
                e(tl('managecrawl_element_stop_crawl'))?></button>
            <?php
        } else {
            e(tl('crawlstatus_view_no_description'));
        }
        ?></p>
        <p><b><?php e(tl('crawlstatus_view_time_started')); ?></b>
        <?php 
        if(isset($data['CRAWL_TIME'])) {  e(date("r",$data['CRAWL_TIME'])); } 
            else {e(tl('crawlstatus_view_no_crawl_time'));} ?></p>

        <p><b><?php e(tl('crawlstatus_view_total_urls')); ?></b> <?php 
            if(isset($data['COUNT'])) { e($data['COUNT']); } else {e("0");} 
            ?></p>
        <p><b><?php e(tl('crawlstatus_view_most_recent_fetcher')); ?></b>

        <?php
        if(isset($data['MOST_RECENT_FETCHER'])) {
            e($data['MOST_RECENT_FETCHER']); 
        } else {
            e(tl('crawlstatus_view_no_fetcher'));
        }
        ?></p>
        <h2><?php e(tl('crawlstatus_view_most_recent_urls')); ?></h2>
        <?php 
        if(isset($data['MOST_RECENT_URLS_SEEN']) && 
            count($data['MOST_RECENT_URLS_SEEN']) > 0) { 
            foreach($data['MOST_RECENT_URLS_SEEN'] as $url) {
                e("<p>$url</p>");
            }
        } else {
            e("<p>".tl('crawlstatus_view_no_recent_urls')."</p>");
        } 
        ?>

        <h2><?php e(tl('crawlstatus_view_previous_crawls'))?></h2>
        <?php 
        if(isset($data['RECENT_CRAWLS']) && count($data['RECENT_CRAWLS']) > 0) {
            ?>

            <table class="crawlstable">
            <tr><th><?php e(tl('crawlstatus_view_description'));?></th><th><?php 
                e(tl('crawlstatus_view_time_started')); ?></th>
            <th><?php e(tl('crawlstatus_view_total_urls'));?></th>
            <th colspan="3"><?php e(tl('crawlstatus_view_actions'));?></th></tr>
            <?php
            foreach($data['RECENT_CRAWLS'] as $crawl) {
            ?>
                <tr><td><b><?php e($crawl['DESCRIPTION']); ?></b></td><td> <?php
                    e(date("r", $crawl['CRAWL_TIME'])); ?></td>
                <td> <?php  e( $crawl['COUNT']); ?></td>
                <td><a href="<?php e($base_url); ?>resume&timestamp=<?php 
                    e($crawl['CRAWL_TIME']); ?>"><?php 
                    e(tl('crawlstatus_view_resume'));?></a></td>
                <td>
                <?php 
                if( $crawl['CRAWL_TIME'] != $data['CURRENT_INDEX']) { ?>
                    <a href="<?php e($base_url); ?>index&timestamp=<?php 
                        e($crawl['CRAWL_TIME']); ?>"><?php 
                        e(tl('crawlstatus_view_set_index')); ?></a>
                <?php 
                } else { ?>
                    <?php e(tl('crawlstatus_view_search_index')); ?>
                <?php
                }
                ?>
                </td>
                <td><a href="<?php e($base_url); 
                    ?>delete&timestamp=<?php e($crawl['CRAWL_TIME']); 
                    ?>"><?php e(tl('crawlstatus_view_delete')); ?></a></td>
                </tr>
            <?php
            }
            ?></table>
        <?php
        } else {
            e("<p class='red'>".tl('crawlstatus_view_no_previous_crawl')."</p>");
        }
        ?>
    <?php 
    }
}
?>