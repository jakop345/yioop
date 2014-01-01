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
 * Element responsible for drawing links to common subsearches
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class SubsearchElement extends Element
{
    /**
     *  Method responsible for drawing links to common subsearches
     *
     *  @param array $data makes use of the CSRF token for anti CSRF attacks
     */
    function render($data)
    {
        if(!SUBSEARCH_LINK) { return; }
        if(!isset($data["SUBSEARCHES"]) || $data["SUBSEARCHES"] == NULL) {
            $data["SUBSEARCHES"] = array();
        }
        array_unshift($data["SUBSEARCHES"], array("FOLDER_NAME" => "",
            "SUBSEARCH_NAME" => tl('subsearch_element_web')));
        if(!isset($data['SUBSEARCH'])) {
            $data['SUBSEARCH'] = "";
        }
        $drop_threshold = 3;
        if(MOBILE) {
        $drop_threshold = 0;
        }
        if(count($data["SUBSEARCHES"]) > $drop_threshold + 1) {
            $subdropdown = true;
        } else {
            $subdropdown = false;
        }
        ?>

            <div class="subsearch" >
            <ul class="out-list">
                <?php
                $i = 0;
                $class= "outer";
                foreach($data["SUBSEARCHES"] as $search) {
                    if ($subdropdown && $i >= $drop_threshold) {
                        e("<li class='outer'><span id='more-off'><a ".
                            " href='#' onclick=\"setDisplay('more-menu',".
                            " true); setDisplay('more-off',".
                            " false); setDisplay('more-on', 'inline')\" ><b>".
                            tl('subsearch_element_more').
                            "</b></a></span><span ".
                            "id='more-on'><a href='?' onclick=\"setDisplay(".
                            "'more-on', false); setDisplay('more-off',".
                            " 'inline'); setDisplay('more-menu',".
                            " false)\" \" ><b >"
                            .tl('subsearch_element_more')."</b>".
                            "</a></span><div id='more-menu'>".
                            "<ul class='in-list'>");
                        $subdropdown = false;
                        $class= "inner";
                    }
                    $i++;
                    $source = "?s={$search["FOLDER_NAME"]}";
                    $delim = "&amp;";
                    if($search["FOLDER_NAME"] == "") {
                        $source = "";
                        $delim = "?";
                    }
                    if($search['FOLDER_NAME'] == $data['SUBSEARCH']) {
                        e("<li class='$class'><b>".
                            "{$search['SUBSEARCH_NAME']}</b></li>");
                    } else {
                        $query = "";
                        if(isset($data[CSRF_TOKEN])) {
                            $query .= $delim.CSRF_TOKEN.
                                "=".$data[CSRF_TOKEN].
                                "&amp;c=search";
                            if(isset($data['QUERY']) &&
                                !isset($data['NO_QUERY'])) {
                                $query .= "&amp;q={$data['QUERY']}";
                            }
                        }
                        e("<li class='$class'><a href='$source$query'>".
                            "{$search['SUBSEARCH_NAME']}</a></li>");
                    }
                }
                if($i > $drop_threshold + 1) {
                    e("</ul></div></li>");
                }
                ?>
            </ul>
            </div>

        <?php
        }
}
?>
