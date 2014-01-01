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
 * Helper used to draw thumbnails for video sites
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */

class VideourlHelper extends Helper
{

    /**
     *  Used to check if a url is the url of a video site and if so
     *  draw a link with a thumbnail from the video.
     *  @param string $url to check if of a video site
     *  @param array $video_sources video sites url info to check $url against
     *  @param boolean $open_in_tabs whether new links should be opened in
     *     tabs
     */
    function render($url, $video_sources, $open_in_tabs = false)
    {
        if(strncmp($url, "url", 3) == 0) {
            $link_url_parts = explode("|", $url);
            if(count($link_url_parts) > 1) {
                $url = htmlentities($link_url_parts[1]);
            }
        } else {
            $url = htmlentities($url);
        }
        foreach($video_sources as $source) {
            $url_expression = $source['SOURCE_URL'];
            $expression_parts = explode("{}", $url_expression);
            $found_id = false;
            if(stripos($url, $expression_parts[0]) !== false) {
                $id = substr($url, strlen($expression_parts[0]));
                $len = 0;
                if(isset($expression_parts[1]) &&
                    ($len = strlen($expression_parts[1])) > 0) {
                    if(($pos = stripos($id, $expression_parts[1])) > 0) {
                        $id = substr($id, 0, $pos);
                        $found_id = true;
                    }
                } else if ($len == 0) {
                    $found_id = true;
                }
            }
            if($found_id) {
                $thumb_expression = $source['THUMB_URL'];
                $thumb_parts = explode("{}", $thumb_expression);
                $thumb_url = $thumb_parts[0] . $id;
                if(isset($thumb_parts[1])) {
                    $thumb_url .= $thumb_parts[1];
                }
                ?><a class="video-link" href="<?php e($url); ?>" <?php
                if($open_in_tabs) { ?> target="_blank" <?php }?>><img
                class="thumb" src="<?php e($thumb_url); ?>"
                alt="Thumbnail for <?php e($id); ?>" />
                <img class="video-play" src="resources/play.png" alt="" />
                </a>
                <?php
            }
        }

    }

}
?>
