<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
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
    var $helpers = array("pagination", "filetype", "displayresults",
        "videourl", "images");
    /** Names of element objects that the view uses to display itself 
     *  @var array
     */
    var $elements = array("signin", "subsearch", "footer");

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
        $data['LAND'] = (!isset($data['PAGES'])) ? 'landing-' : '';
        if(SIGNIN_LINK || SUBSEARCH_LINK) {?>


        <div class="<?php e($data['LAND']);?>topbar"><?php
            $this->subsearchElement->render($data);
            $this->signinElement->render($data);
            ?>

        </div>

        <?php
        }
        $logo = "resources/yioop.png";
        if(!isset($data['PAGES'])) {?>

        <div class="landing">
        <?php
        } else if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        ?>

        <h1 class="logo"><a href="./?YIOOP_TOKEN=<?php 
            e($data['YIOOP_TOKEN'])?>"><img 
            src="<?php e($logo); ?>" alt="<?php e(tl('search_view_title'));
                 ?>"
            /></a>
        </h1>
        <?php
        if(isset($data['PAGES'])) {?>

        <div class="serp">
        <?php
        }
        ?>

        <div class="searchbox">
            <form id="search-form" method="get" action='?'>
            <p>
            <input type="hidden" name="YIOOP_TOKEN" value="<?php 
                e($data['YIOOP_TOKEN']); ?>" />
            <input type="hidden" name="its" value="<?php e($data['its']); ?>" />
            <input type="text" <?php if(WORD_SUGGEST) { ?>
                autocomplete="off"  onkeyup="onTypeTerm(event, this)"
                onpaste="onTypeTerm(event, this)"
                <?php } ?>
                title="<?php e(tl('search_view_input_label')); ?>" 
                id="query-field" name="q" value="<?php 
                if(isset($data['QUERY'])) {
                    e(urldecode($data['QUERY']));} ?>" 
                placeholder="<?php e(tl('search_view_input_placeholder')); ?>"/>
            <button class="buttonbox" type="submit"><?php if(MOBILE) {
                    e('>');
                } else {
                e(tl('search_view_search')); } ?></button>
            </p>
            </form>
        </div>
        <div id="suggest-dropdown"> 
            <ul id="suggest-results" class="suggest-list">
            </ul>
        </div>
        <?php
        if(isset($data['PAGES'])) {
            ?>

        </div>

        <div class="serp-results">
            <h2 class="serp-stats"><?php 
                if(MOBILE) {
                } else {
                $num_results = min($data['TOTAL_ROWS'], 
                    $data['LIMIT'] + $data['RESULTS_PER_PAGE']);
                $limit = min($data['LIMIT'] + 1, $num_results);
                e(tl('search_view_query_results')); ?> (<?php 
                e(tl('search_view_calculated', $data['ELAPSED_TIME']));?> <?php
                e(tl('search_view_results', $limit, $num_results,
                    $data['TOTAL_ROWS'].")"));
                }
            ?></h2>
            <?php
            foreach($data['PAGES'] as $page) {
                if(isset($page[self::URL])) {
                    if(substr($page[self::URL], 0, 4) == "url|") {
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
            ?><div class='result'>
                <?php if(isset($page['IMAGES'])) {
                    $image_query = "?YIOOP_TOKEN={$data['YIOOP_TOKEN']}".
                            "&amp;c=search&amp;q={$data['QUERY']}";
                    $this->imagesHelper->render($page['IMAGES'], $image_query);
                    e( "           </div>");
                    continue;
                }?>

                <h2>
                <a href="<?php  e(htmlentities($url));  ?>" rel="nofollow"><?php
                 if(isset($page[self::THUMB]) && $page[self::THUMB] != 'NULL') {
                    ?><img src="<?php e($page[self::THUMB]); ?>" alt="<?php 
                        e($title); ?>"  /> <?php
                    $check_video = false;
                 } else {
                    echo $title;
                    if(isset($page[self::TYPE])) {
                        $this->filetypeHelper->render($page[self::TYPE]);
                    }
                    $check_video = true;
                }
                ?></a>
                </h2>
                <?php if($check_video) {
                    $this->videourlHelper->render($page[self::URL]);
                }
                ?>
                <p class="echolink" <?php e($subtitle); ?>><?php 
                    e(htmlentities(
                        UrlParser::simplifyUrl($url, 100))." ");
                ?></p>
                <?php if(!isset($page[self::ROBOT_METAS]) || 
                    !in_array("NOSNIPPET", $page[self::ROBOT_METAS])) {
                        echo "<p>".$this->displayresultsHelper->
                            render($page[self::DESCRIPTION])."</p>"; 
                    }?>
                <p class="gray"><?php
                if(isset($page[self::TYPE]) && $page[self::TYPE] != "link") {
                    if(CACHE_LINK && (!isset($page[self::ROBOT_METAS]) ||
                        !(in_array("NOARCHIVE", $page[self::ROBOT_METAS]) ||
                          in_array("NONE", $page[self::ROBOT_METAS])))) {
                    ?>
                    <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                            ?>&amp;c=search&amp;a=cache&amp;q=<?php 
                            e($data['QUERY']); ?>&amp;arg=<?php 
                            e(urlencode($url)); 
                            ?>&amp;its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'>
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
                        e(urlencode($url)); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'><?php 
                        e(tl('search_view_similar')); 
                    ?></a>.
                    <?php 
                    }
                    if(IN_LINK) { 
                    ?>

                    <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                        ?>&amp;c=search&amp;q=<?php 
                        e(urlencode("link:".$url)); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>" 
                        rel='nofollow'><?php 
                        e(tl('search_view_inlink')); 
                    ?></a>.
                    <?php 
                    }
                    if(IP_LINK && isset($page[self::IP_ADDRESSES])){
                    foreach($page[self::IP_ADDRESSES] as $address) {?>

                    <a href="?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                            ?>&amp;c=search&amp;q=<?php
                            e(urlencode('ip:'.$address));?>&amp;<?php
                            ?>its=<?php e($data['its']); ?>" 
                            rel='nofollow'>IP:<?php 
                            e("$address");?></a>. <?php 
                      } 
                    }
                    ?><?php
                    if(MOBILE) {e("<br />");}
                    e(tl('search_view_rank', 
                        number_format($page[self::DOC_RANK], 2)));
                    e(tl('search_view_relevancy',
                        number_format($page[self::RELEVANCE], 2) ));
                    e(tl('search_view_proximity',
                        number_format($page[self::PROXIMITY], 2) )." ");
                    e(tl('search_view_score', $page[self::SCORE]));
                ?>
                </p>
                <?php
                } ?>

            </div>

            <?php 
            } //end foreach
            $this->paginationHelper->render(
                $data['PAGING_QUERY']."&amp;YIOOP_TOKEN=".$data['YIOOP_TOKEN'],
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
            ?>

        </div>
        <?php
        }
        ?>

        <div class="landing-footer">
            <div><b><?php e($data['INDEX_INFO']);?></b> <?php
            if(isset($data["HAS_STATISTICS"]) && $data["HAS_STATISTICS"]) { 
            ?>[<a href="index.php?YIOOP_TOKEN=<?php e($data['YIOOP_TOKEN']);
                ?>&amp;c=statistics&amp;its=<?php e($data['its']);?>"><?php 
                e(tl('search_view_more_statistics')); ?></a>]
            <?php 
            }
            ?></div><?php  $this->footerElement->render($data);?>

        </div>
        <?php
        if(!isset($data['PAGES'])) {?>

        </div>

        <div class='landing-spacer'></div>
        <?php
        }

    }
}
?>
