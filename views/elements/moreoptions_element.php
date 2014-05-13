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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element responsible for drawing the page with more
 * search option, account, and tool info
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class MoreoptionsElement extends Element
{

    /**
     *  Method responsible for drawing the page with more
     *  search option, account, and tool info
     *
     *  @param array $data to draw links on page
     */
    function render($data)
    {
        $max_column_num = 10;
        if(MOBILE) {
            $num_columns = 1;
        } else {
            $num_columns = 4;
        }
        $max_items = $max_column_num * $num_columns;
        $subsearches = array_slice($data["SUBSEARCHES"], $max_items *
            $data['MORE_PAGE']);
        $spacer = "";
        $prev_link = false;
        $next_link = false;
        if($data['MORE_PAGE'] > 0) {
            $prev_link = true;
        }
        $num_remaining = count($subsearches);
        if($num_remaining > $max_items) {
            $next_link = true;
            $subsearches = array_slice($subsearches, 0,
                $max_items);
        }
        if($next_link && $prev_link) {
            $spacer = "&nbsp;&nbsp;--&nbsp;&nbsp;";
        }
        $num_rows = ceil(count($subsearches)/$num_columns);
        ?>
        <h2><?php e(tl('moreoptions_element_other_searches'))?></h2>
        <table>
        <tr class="align-top">
        <?php
        $cur_row = 0;
        foreach($subsearches as $search) {
            if($cur_row == 0) {
                e("<td><ul class='square-list'>");
                $ul_open = true;
            }
            $cur_row++;
            if(!$search['SUBSEARCH_NAME']) {
                $search['SUBSEARCH_NAME'] = $search['LOCALE_STRING'];
            }
            $query = ($search["FOLDER_NAME"] == "") ? "?":
                "?s={$search["FOLDER_NAME"]}";
            $query .= (isset($data[CSRF_TOKEN])) ? "&amp;".CSRF_TOKEN.
                "=".$data[CSRF_TOKEN] : "";
            e("<li><a href='$query'>".
                "{$search['SUBSEARCH_NAME']}</a></li>");
            if($cur_row >= $num_rows) {
                $ul_open = false;
                e("</ul></td>");
                $cur_row = 0;
            }
        }
        if($ul_open) {
            e("</ul></td>");
        }
        ?>
        </tr>
        </table>
        <div class="indent"><?php
            if($prev_link) {
                e("<a href='./?a=more&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                    "&amp;more_page=".($data['MORE_PAGE'] -1)."'>".
                    tl('moreoptions_element_previous')."</a>");
            }
            e($spacer);
            if($next_link) {
                e("<a href='./?a=more&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                    "&amp;more_page=".($data['MORE_PAGE'] + 1)."'>".
                    tl('moreoptions_element_next')."</a>");
            }
        ?></div>
        <h2 class="reduce-top"><?php
            e(tl('moreoptions_element_my_accounts'))?></h2>
        <table class="reduce-top">
        <tr><td><ul class='square-list'><li><a href="./?c=settings&amp;<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN])?>&amp;l=<?php
                e(getLocaleTag());
                e((isset($data['its'])) ? '&amp;its='.$data['its'] : '');
                ?>"><?php
                e(tl('signin_element_settings')); ?></a></li>
            <?php if(!MOBILE) { ?>
                </ul></td>
                <td><ul  class='square-list'>
            <?php
            }
            if(!isset($data["ADMIN"]) || !$data["ADMIN"]) {
                ?><li><a href="./?c=admin"><?php
                    e(tl('signin_element_signin')); ?></a></li><?php
            } else {
                ?><li><a href="./?c=admin&amp;<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN])?>"><?php
                        e(tl('signin_element_admin')); ?></a></li><?php
            }
            if(!MOBILE) {e('</ul></td>');}
            ?>

            <?php
            if((!isset($data["ADMIN"]) || !$data["ADMIN"]) &&
                in_array(REGISTRATION_TYPE, array('no_activation',
                'email_registration', 'admin_activation'))) {
                if(!MOBILE){ e("<td><ul  class='square-list'>"); } ?>
                <li><a href="./?c=register&amp;a=createAccount&amp;<?php
                        e(CSRF_TOKEN."=".$data[CSRF_TOKEN])?>&amp;"><?php
                        e(tl('signin_view_create_account')); ?></a></li>
                </ul></td>
                <?php
            }
            ?>
        </tr>
        </table>
        <?php
        $token_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN];
        $tools = array();
        if(in_array(REGISTRATION_TYPE, array('no_activation',
            'email_registration', 'admin_activation'))) {
            $tools[$token_url . "&amp;c=register&amp;a=suggestUrl"] =
                tl('moreoptions_element_suggest');
        }
        $tools[$token_url . "&amp;c=group&amp;a=wiki&amp;arg=pages"] =
            tl('moreoptions_element_wiki_pages');
        if($tools != array()) {
             ?>
            <h2 id="tools" class="reduce-top"><?php
                e(tl('moreoptions_element_tools'))?></h2>
            <table class="reduce-top">
            <tr class="align-top">
            <?php
            $cur_row = 0;
            foreach($tools as $tool_url => $tool_name) {
                if($cur_row == 0) {
                    e("<td><ul class='square-list'>");
                    $ul_open = true;
                }
                $cur_row++;
                e("<li><a href='$tool_url'>$tool_name</a></li>");
                if($cur_row >= $num_rows) {
                    $ul_open = false;
                    e("</ul></td>");
                    $cur_row = 0;
                }
            }
            if($ul_open) {
                e("</ul></td>");
            }
            ?>
            </tr>
            </table>
            <?php
        }
    }
}
?>
