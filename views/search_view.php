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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Web page used to present search results
 * It is also contains the search box for
 * people to types searches into
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */ 

class SearchView extends View implements CrawlConstants
{
    /** Names of helper objects that the view uses to help draw itself 
     *  @var array
     */
    var $helpers = array("pagination", "filetype", "displayresults");
    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("signin");

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     *  Draws the main landing pages as well as search result pages
     *
     *  @param array $data  PAGES contains all the summaries of web pages
     *  returned by the current query, $data also contains information
     *  about how the the query took to process and the total number
     *  of results, how to fetch the next results, etc.
     *
     */
    public function renderView($data) 
    {
        $this->signinElement->render($data);
        if(!isset($data['PAGES'])) {
            e('<div class="landing">');
        }
        ?>
        <h1 class="logo"><a href="./?YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN'])?>"><img 
            src="resources/yioop.png" alt="<?php e(tl('search_view_title')); ?>" 
            /></a></h1>
        <div class="searchbox">

        <form id="searchForm" method="get" action='?'>
        <p>
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="its" value="<?php e($data['its']); ?>" />
        <input type="text" title="<?php e(tl('search_view_input_label')); ?>" 
            id="search-name" name="q" value="<?php if(isset($data['QUERY'])) {
            e(urldecode($data['QUERY']));} ?>" 
            placeholder="<?php e(tl('search_view_input_placeholder')); ?>" />
        <button class="buttonbox" type="submit"><?php 
            e(tl('search_view_search')); ?></button>
        </p>
        </form>
        </div>
        <?php
        if(isset($data['PAGES'])) {
            ?>
            <h2><?php e(tl('search_view_query_results')); ?> (<?php 
                e(tl('search_view_calculated', $data['ELAPSED_TIME']));?> <?php
                e(tl('search_view_results', $data['LIMIT'], 
                    min($data['TOTAL_ROWS'], 
                    $data['LIMIT'] + $data['RESULTS_PER_PAGE']), 
                    $data['TOTAL_ROWS'])); 
            ?> )</h2>
            <?php
            foreach($data['PAGES'] as $page) {?>
                <div class='result'> 
                <h2>
                <a href="<?php if(isset($page[self::TYPE]) 
                    && $page[self::TYPE] != "link") {
                        e($page[self::URL]); 
                    } else {
                        e(strip_tags($page[self::TITLE]));
                    } ?>" ><?php
                 if(isset($page[self::THUMB]) && $page[self::THUMB] != 'NULL') {
                    ?><img src="<?php e($page[self::THUMB]); ?>" alt="<?php 
                        e($page[self::TITLE]); ?>"  /> <?php
                 } else {
                    echo $page[self::TITLE];
                    if(isset($page[self::TYPE])) {
                        $this->filetypeHelper->render($page[self::TYPE]);
                    }
                }
                ?></a></h2>
                <p><?php 
                echo $this->displayresultsHelper->
                    render($page[self::DESCRIPTION]); ?></p>
                <p class="echolink" ><?php if(isset($page[self::URL])){
                    e(substr($page[self::URL],0, 200)." ");}
                    e(tl('search_view_rank', 
                        number_format($page[self::DOC_RANK], 2)));
                    e(tl('search_view_relevancy',
                        number_format($page[self::RELEVANCE], 2) ));
                    e(tl('search_view_proximity',
                        number_format($page[self::PROXIMITY], 2) )." ");
                    e(tl('search_view_score', $page[self::SCORE]));
                if(isset($page[self::TYPE]) && $page[self::TYPE] != "link") {
                    if(CACHE_LINK) {
                    ?>
                        <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                            ?>&amp;c=search&amp;a=cache&amp;q=<?php 
                            e($data['QUERY']); ?>&amp;arg=<?php 
                            e(urlencode($page[self::URL])); 
                            ?>&amp;its=<?php e($page[self::CRAWL_TIME]); ?>" >
                        <?php
                        if($page[self::TYPE] == "text/html" || 
                            stristr($page[self::TYPE], "image")) {
                            e(tl('search_view_cache'));

                        } else {
                            e(tl('search_view_as_text'));
                        }
                        ?></a>.
                    <?php 
                    }
                    if(SIMILAR_LINK) { 
                    ?> 
                    <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                        ?>&amp;c=search&amp;a=related&amp;arg=<?php 
                        e(urlencode($page[self::URL])); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" ><?php 
                        e(tl('search_view_similar')); 
                    ?></a>.
                    <?php 
                    }
                    if(IN_LINK) { 
                    ?>
                        <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                        ?>&amp;c=search&amp;q=<?php 
                        e(urlencode("link:".$page[self::URL])); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" ><?php 
                        e(tl('search_view_inlink')); 
                    ?></a>.
                    <?php 
                    }
                    if(IP_LINK) { 
                    ?>
                    <?php if(isset($page[self::IP_ADDRESSES])){
                          foreach($page[self::IP_ADDRESSES] as $address) {?> 
                            <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                                ?>&amp;c=search&amp;q=<?php
                                e(urlencode('ip:'.$address));?>&amp;<?php
                                ?>its=<?php e($data['its']); ?>" >IP:<?php 
                                e("$address");?></a>. <?php 
                          } 
                        }?></p>
                <?php
                    }
                } ?>
                </div>

            <?php 
            } //end foreach
            $this->paginationHelper->render(
                $data['PAGING_QUERY']."&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN'], 
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        }
        ?><div class="landing-footer">
            <div><b><?php e($data['INDEX_INFO']);?></b></div>
            <div><a href="http://www.seekquarry.com/"><?php
            e(tl('search_view_developed_seek_quarry')); ?></a></div>

        </div><?php
        if(!isset($data['PAGES'])) {
            e("</div><div class='landing-spacer'></div>");
        }

    }
}
?>
