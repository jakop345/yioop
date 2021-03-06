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
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";
/**
 * Helper used to draw links and snippets for RSS feeds
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */
class FeedsHelper extends Helper implements CrawlConstants
{

    /**
     * Takes page summaries for RSS pages and the current query
     * and draws list of news links and a link to the news link subsearch
     * page if applicable.
     *
     * @param array $feed_pages page data from news feeds
     * @param string $base_query the  query_string prefix
     * @param string $query the current search query
     * @param string $subsearch name of subsearch page this image group on
     * @param boolean $open_in_tabs whether new links should be opened in
     *    tabs
     */
    function render($feed_pages, $base_query, $query, $subsearch,
        $open_in_tabs = false)
    {
        if($subsearch != 'news') {
            $not_news = true;
            ?>
            <h2><a href="<?php e("$base_query&amp;q=$query&amp;s=news"); ?>"
                ><?php e(tl('feeds_helper_view_feed_results',
                $query));?></a></h2>
        <?php
        } else {
            $not_news = false;
        }?>
            <div class="feed-list">
        <?php
        $time = time();
        foreach($feed_pages as $page) {
            $pub_date = $page[self::SUMMARY_OFFSET][0][4];
            $encode_source = urlencode(
                urlencode($page[self::SOURCE_NAME]));
            if(isset($page[self::URL])) {
                if(strncmp($page[self::URL], "url|", 4) == 0) {
                    $url_parts = explode("|", $page[self::URL]);
                    $url = $url_parts[1];
                    $title = UrlParser::simplifyUrl($url, 60);
                    $subtitle = "title='".$page[self::URL]."'";
                } else {
                    $url = $page[self::URL];
                    $title = $page[self::TITLE];
                    if(strlen(trim($title)) == 0) {
                        $title = UrlParser::simplifyUrl($url, 60);
                    }
                    $subtitle = "";
                }
            } else {
                $url = "";
                $title = isset($page[self::TITLE]) ? $page[self::TITLE] :"";
                $subtitle = "";
            }
            $pub_date = $this->getPubdateString($time, $pub_date);
            if($not_news) {
        ?>
                <div class="blockquote">
                <a href="<?php e($page[self::URL]); ?>" rel="nofollow" <?php
                if($open_in_tabs) {  ?> target="_blank" <?php }
                ?>><?php  e($page[self::TITLE]); ?></a>
                <a class="gray-link" rel='nofollow' href="<?php e($base_query.
                    "&amp;q=media:news:".$encode_source.
                    "&amp;s=news");?>" ><?php  e($page[self::SOURCE_NAME]."</a>"
                    ."<span class='gray'> - $pub_date</span>");
                 ?></span>
                </div>
        <?php
            } else {
        ?>
                <div class="results">
                <h2><a href="<?php e($page[self::URL]); ?>" rel="nofollow" <?php
                if($open_in_tabs) { ?> target="_blank" <?php }
                ?>><?php  e($page[self::TITLE]); ?></a>.
                <a class="gray-link" rel='nofollow' href="<?php e($base_query.
                    "&amp;q=media:news:".$encode_source.
                    "&amp;s=news");?>" ><?php  e($page[self::SOURCE_NAME]."</a>"
                    ."<span class='gray'> - $pub_date</span>");
                 ?></h2>
                <p class="echo-link" <?php e($subtitle); ?>><?php
                    e(UrlParser::simplifyUrl($url, 100)." ");
                ?></p>
                <?php
                $description = isset($page[self::DESCRIPTION]) ?
                    $page[self::DESCRIPTION] : "";
                e("<p>$description</p>");
                ?>
                </div>
        <?php
            }
        }
        ?>
        </div>
        <?php
    }
    /**
     * Write as an string in the current locale the difference between the
     * publication date of a post and the current time
     *
     * @param int $time timestamp for current time
     * @param int $pub_date timestamp for feed_item publication
     * @return string in the current locale the time difference
     */
    function getPubdateString($time, $pub_date)
    {
        $delta = $time - $pub_date;
        if($delta < ONE_DAY) {
            $num_hours = ceil($delta/ONE_HOUR);
            if($num_hours <= 2) {
                if($num_hours > 1) {
                    $pub_date = tl('feeds_helper_view_onehour');
                } else {
                    $num_minutes = floor($delta/ONE_MINUTE);
                    $remainder_seconds = $delta % ONE_MINUTE;
                    $pub_date =
                        tl('feeds_helper_view_minsecs', $num_minutes,
                            $remainder_seconds);
                }
            } else {
                $pub_date =
                    tl('feeds_helper_view_hourdate', $num_hours);
            }
        } else {
            $pub_date = date("d/m/Y", $pub_date);
        }
        return $pub_date;
    }
}
?>
