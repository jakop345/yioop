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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
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
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";
    /**
     * Represent extension of Git urls
     */
    const GIT_EXTENSION = ".git";
    /**
     * Draws the main landing pages as well as search result pages
     *
     * @param array $data  PAGES contains all the summaries of web pages
     * returned by the current query, $data also contains information
     * about how the the query took to process and the total number
     * of results, how to fetch the next results, etc.
     *
     */
    function renderView($data)
    {
        $data['LAND'] = (!isset($data['PAGES'])) ? 'landing-' : '';
        if(SIGNIN_LINK || SUBSEARCH_LINK) {?>
        <div class="<?php e($data['LAND']);?>top-bar"><?php
            $this->element("subsearch")->render($data);
            $this->element("signin")->render($data);
            ?>
        </div>
        <?php
        }
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token_string = ($logged_in) ? CSRF_TOKEN."=".$data[CSRF_TOKEN] : "";
        $logo = LOGO;
        $is_landing = (!isset($data['PAGES']) && !isset($data['MORE']));
        if($is_landing) { ?>
            <div class="landing">
        <?php
        } else if(MOBILE) {
            $logo = M_LOGO;
        }
        ?>
        <h1 class="logo"><a href="./<?php if($logged_in) {
            e("?".$token_string);
            } ?>"><img
            src="<?php e($logo); ?>" alt="<?php e(tl('search_view_title'));
                 ?>"
            /></a>
        </h1>
        <?php
        if(isset($data['PAGES'])){?>
            <div class="serp">
            <?php
        }
        ?>
        <div class="search-box">
            <form id="search-form" method="get" action='?'
                onsubmit="processSubmit()">
            <p>
            <?php if(isset($data["SUBSEARCH"]) && $data["SUBSEARCH"] != "") { ?>
            <input type="hidden" name="s" value="<?php
                e($data['SUBSEARCH']); ?>" />
            <?php } ?>
            <?php if($logged_in) { ?>
            <input id="csrf-token" type="hidden" name="<?php e(CSRF_TOKEN); ?>"
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <?php } ?>
            <input id="its-value" type="hidden" name="its" value="<?php
                e($data['its']); ?>" />
            <input type="text" <?php if(WORD_SUGGEST) { ?>
                autocomplete="off"  onkeyup="onTypeTerm(event, this)"
                <?php } ?>
                title="<?php e(tl('search_view_input_label')); ?>"
                id="query-field" name="q" value="<?php
                if(isset($data['QUERY']) && !isset($data['NO_QUERY'])) {
                    e(urldecode($data['QUERY']));} ?>"
                placeholder="<?php e(tl('search_view_input_placeholder')); ?>"/>
            <button class="button-box" type="submit"><img
                src='./resources/search-button.png'
                alt='<?php e(tl('search_view_search')); ?>'/></button>
            </p>
            </form>
        </div>
        <div id="suggest-dropdown">
            <ul id="suggest-results" class="suggest-list">
            </ul>
        </div>
        <?php
        if(isset($data['PAGES']) && !isset($data['MORE'])) {
            ?></div><?php
            $this->renderSearchResults($data);
        } else if(isset($data['MORE'])) {
            if(isset($data['INDEX_INFO']) && $data['INDEX_INFO']) {
                $top ="";
                if(!MOBILE) {
                    $top = "class='medium-top'";
                }
                e("</div><div $top>");
            }
            $this->element("moreoptions")->render($data);
            if(isset($data['INDEX_INFO']) && $data['INDEX_INFO']) {
                e("</div>");
            }
        }
        ?>
        <div class="landing-footer">
            <div><b><?php
            if(isset($data['INDEX_INFO'])) {
                e($data['INDEX_INFO']);
            } else {
                e(tl('search_view_no_index_set'));
            } ?></b> <?php
            if(isset($data["HAS_STATISTICS"]) && $data["HAS_STATISTICS"]) {
            ?>[<a href="index.php?<?php e($token_string);
                ?>&amp;c=statistics&amp;its=<?php e($data['its']);?>"><?php
                e(tl('search_view_more_statistics')); ?></a>]
            <?php
            }
            ?></div><?php $this->element("footer")->render($data);?>

        </div>
        <?php
        if($is_landing) { ?>
            </div>
            <div class='landing-spacer'></div>
            <?php
        }
    }
    /**
     * Used to draw the results of a query to the Yioop Search Engine
     *
     * @param array $data an associative array containing a PAGES field needed
     *     to render search result
     */
    function renderSearchResults($data)
    {
        $is_landing = (!isset($data['PAGES']) && !isset($data['MORE']));
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token_string = ($logged_in) ? CSRF_TOKEN . "=". $data[CSRF_TOKEN] .
            "&" : "";
        $token_string_amp = ($logged_in) ?
            CSRF_TOKEN . "=" . $data[CSRF_TOKEN]."&amp;" : "";
        ?>
        <div <?php if(WORD_SUGGEST) { e('id="spell-check"'); } ?>
            class="spell"><span class="hidden"
        >&nbsp;</span></div>
        <h2 class="serp-stats"><?php
            if(MOBILE) {
            } else {
            $num_results = min($data['TOTAL_ROWS'],
                $data['LIMIT'] + $data['RESULTS_PER_PAGE']);
            $limit = min($data['LIMIT'] + 1, $num_results);
             ?> <?php
            e(tl('search_view_calculated', $data['ELAPSED_TIME']));?> <?php
            e(tl('search_view_results', $limit, $num_results,
                $data['TOTAL_ROWS']));
            }
        ?></h2>
        <?php
        if((!$is_landing) && in_array(AD_LOCATION, array('top', 'both') ) ) { ?>
            <div class="top-adscript"><?php e($data['TOP_ADSCRIPT']); ?></div>
        <?php
        } ?>
        <?php
        if(!$is_landing && !MOBILE &&
            in_array(AD_LOCATION, array('side', 'both') ) ) { ?>
            <div class="side-adscript"><?php e($data['SIDE_ADSCRIPT']); ?></div>
        <?php
        } ?>
        <?php
        $similar_words = $data['THESAURUS_VARIANTS'];
        $use_thesaurus = WORD_SUGGEST && count($similar_words) > 0 && !MOBILE;
        if($use_thesaurus) { ?>
            <div id="thesaurus-results" class="thesaurus">
            <?php
                e(tl('search_view_thesaurus_results'));
                foreach ($similar_words as $word) {
                    e("<br />");
                    ?><span><a href="?<?php e($token_string_amp);
                    ?>its=<?php e($data['its']);?>&amp;q=<?php
                        e($word);?>"><?php e($word); ?></a></span>
                    <?php
                }
            ?>
            </div>
        <?php
        }
        if($use_thesaurus) { ?>
            <div class="thesaurus-serp-results"> <?php
        } else { ?>
            <div class="serp-results">
        <?php } ?>
            <?php
            foreach($data['PAGES'] as $page) {
                if(isset($page[self::URL])) {
                    if(substr($page[self::URL], 0, 4) == "url|") {
                        $url_parts = explode("|", $page[self::URL]);
                        $url = $url_parts[1];
                        $link_url = $url;
                        $title = UrlParser::simplifyUrl($url, 60);
                        $subtitle = "title='".$page[self::URL]."'";
                    } else {
                        $url = $page[self::URL];
                        if(substr($url, 0, 7) == "record:") {
                            $link_url="?".$token_string.
                            "c=search&a=cache&q=".$data['QUERY'].
                            "&arg=".urlencode($url)."&its=".
                            $page[self::CRAWL_TIME];
                        } else {
                            $link_url = $url;
                        }
                        $title = mb_convert_encoding($page[self::TITLE],
                            "UTF-8", "UTF-8");
                        if(strlen(trim($title)) == 0) {
                            $title = UrlParser::simplifyUrl($url, 60);
                        }
                        $subtitle = "";
                    }
                } else {
                    $url = "";
                    $link_url = $url;
                    $title = isset($page[self::TITLE]) ? $page[self::TITLE] :"";
                    $subtitle = "";
                }
            ?><div class='result'>
                <?php
                $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] :
                    "";
                $base_query = "?".$token_string_amp.
                        "c=search";
                if(isset($page['IMAGES'])) {
                    $this->helper("images")->render($page['IMAGES'],
                        $base_query."&amp;q={$data['QUERY']}", $subsearch);
                    e( "           </div>");
                    continue;
                } else if(isset($page['FEEDS'])){
                    $this->helper("feeds")->render($page['FEEDS'],
                        $base_query, $data['QUERY'],  $subsearch,
                        $data['OPEN_IN_TABS']);
                    e( "           </div>");
                    continue;
                }
                ?>
                <h2>
                <?php
                    if(strpos($link_url, self::GIT_EXTENSION)) { ?>
                    <a href="?<?php e($token_string_amp);
                        ?>c=search&amp;a=cache&amp;q=<?php
                        e($data['QUERY']); ?>&amp;arg=<?php
                        e(urlencode($url));
                        ?>&amp;its=<?php e($page[self::CRAWL_TIME]);?>
                        &amp;repository=git"
                        rel='nofollow'>
                <?php } else { ?>
                    <a href="<?php  e(htmlentities($link_url));  ?>"
                        rel="nofollow"
                        <?php if($data["OPEN_IN_TABS"]) {
                        ?> target="_blank" <?php }?> >
                <?php }
                 if(isset($page[self::THUMB]) && $page[self::THUMB] != 'NULL') {
                    ?><img src="<?php e($page[self::THUMB]); ?>" alt="<?php
                        e($title); ?>"  /> <?php
                    $check_video = false;
                 } else {
                    echo $title;
                    if(isset($page[self::TYPE])) {
                        $this->helper("filetype")->render($page[self::TYPE]);
                    }
                    $check_video = true;
                }
                ?></a>
                </h2>
                <?php if($check_video) {
                    $this->helper("videourl")->render($url,
                        $data['VIDEO_SOURCES'], $data["OPEN_IN_TABS"]);
                }
                if(!MOBILE && isset($page[self::WORD_CLOUD]) &&
                    is_array($page[self::WORD_CLOUD])) { ?>
                    <p><span class="echo-link" <?php e($subtitle); ?>><?php
                        e(UrlParser::simplifyUrl($url, 40)." ");
                    ?></span><?php
                    $cloud = $page[self::WORD_CLOUD];
                    $i = 1;
                    e("<span class='word-cloud-spacer'>".
                        tl('search_view_word_cloud')."</span>");
                        $len = 0;
                    foreach($cloud as $word) {
                        $len += strlen($word);
                        if($len > 40) { break; }
                        ?><span class="word-cloud">
                        <a class='word-cloud-<?php e($i)?>'
                        href="?<?php e($token_string_amp);
                            ?>its=<?php e($data['its']);
                            ?>&amp;q=<?php e($word);?>"><?php
                            e($this->helper("displayresults")->
                            render($word)."</a></span>");
                        $i++;
                    }
                } else { ?>
                    <p><span class="echo-link" <?php e($subtitle); ?>><?php
                        e(UrlParser::simplifyUrl($url, 100)." ");
                    ?></span><?php
                }?></p>
                <?php if(!isset($page[self::ROBOT_METAS]) ||
                    !in_array("NOSNIPPET", $page[self::ROBOT_METAS])) {
                        $description = isset($page[self::DESCRIPTION]) ?
                            $page[self::DESCRIPTION] : "";
                        $description = mb_convert_encoding($description,
                            "UTF-8", "UTF-8");
                        e("<p>".$this->helper("displayresults")->
                            render($description)."</p>");
                    }?>
                <p class="serp-links-score"><?php
                $aux_link_flag = false;
                if(isset($page[self::TYPE]) && $page[self::TYPE] != "link") {
                    if(CACHE_LINK && (!isset($page[self::ROBOT_METAS]) ||
                        !(in_array("NOARCHIVE", $page[self::ROBOT_METAS]) ||
                          in_array("NONE", $page[self::ROBOT_METAS])))) {
                        $aux_link_flag = true;
                    ?>
                    <a href="?<?php e($token_string_amp);
                            ?>c=search&amp;a=cache&amp;q=<?php
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
                        $aux_link_flag = true;
                    ?>
                    <a href="?<?php e($token_string_amp);
                        ?>c=search&amp;a=related&amp;arg=<?php
                        e(urlencode($url)); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>"
                        rel='nofollow'><?php
                        e(tl('search_view_similar'));
                    ?></a>.
                    <?php
                    }
                    if(IN_LINK) {
                        $aux_link_flag = true;
                    ?>
                    <a href="?<?php e($token_string_amp);
                        ?>c=search&amp;q=<?php
                        e(urlencode("link:".$url)); ?>&amp;<?php
                        ?>its=<?php e($page[self::CRAWL_TIME]); ?>"
                        rel='nofollow'><?php
                        e(tl('search_view_inlink'));
                    ?></a>.
                    <?php
                    }
                    if(IP_LINK && isset($page[self::IP_ADDRESSES])){
                    foreach($page[self::IP_ADDRESSES] as $address) {?>
                    <a href="?<?php e($token_string_amp);
                            ?>c=search&amp;q=<?php
                            e(urlencode('ip:'.$address));?>&amp;<?php
                            ?>its=<?php e($data['its']); ?>"
                            rel='nofollow'>IP:<?php
                            e("$address");?></a>. <?php
                      }
                    }
                    ?>
                <?php
                }
                if(MOBILE && $aux_link_flag) {e("<br />");}
                if(isset($page[self::SCORE])) {
                    ?><span title="<?php
                    e(tl('search_view_rank',
                        number_format($page[self::DOC_RANK], 2))."\n");
                    e(tl('search_view_relevancy',
                        number_format($page[self::RELEVANCE], 2) )."\n");
                    e(tl('search_view_proximity',
                        number_format($page[self::PROXIMITY], 2) )."\n");
                    if(isset($page[self::THESAURUS_SCORE]) &&
                        $page[self::THESAURUS_SCORE] > 0) {
                        e(tl('search_view_thesaurus_score',
                            number_format($page[self::THESAURUS_SCORE], 2))."\n");
                    }
                    if(isset($page[self::USER_RANKS])) {
                        foreach($page[self::USER_RANKS] as $label => $score) {
                            e($label.":".number_format($score/6553.6, 2)."\n");
                        }
                    }
                    ?>" ><?php
                    e(tl('search_view_score', $page[self::SCORE]));?></span>
                    <?php
                }
                ?>
                </p>
            </div>
            <?php
            } //end foreach
            $append_url = (isset($data["ADMIN"]) && $data["ADMIN"]) ?
                "&amp;".CSRF_TOKEN."=". $data[CSRF_TOKEN] : "";
            $this->helper("pagination")->render(
                $data['PAGING_QUERY'].$append_url."&amp;its=".$data['its'],
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
            ?>
        </div>
    <?php
    }
}
?>
