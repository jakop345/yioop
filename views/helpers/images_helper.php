<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";

/**
 * Helper used to draw thumbnails strips for images
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage helper
 */

class ImagesHelper extends Helper implements CrawlConstants
{

    /**
     *  Takes page summaries for image pages and the current query
     *  and draw a thumbnail strip so that clicking on an image goes to
     *  the cache of that image.
     *
     *  @param array $image_pages page data and thumbnails for images
     *  @param string $query the current search query
     *  @param string $subsearch name of subsearch page this image group on
     */
    function render($image_pages, $query, $subsearch)
    {
        if($subsearch != 'images') {?>
            <h2><a href="<?php e($query.'&s=images')?>"
                ><?php e(tl('images_helper_view_image_results'));?></a></h2>
        <?php
        }?>
            <div class="image-list">
        <?php
        $i = 0;
        $break_frequency = 5;
        foreach($image_pages as $page) {
            if(CACHE_LINK && (!isset($page[self::ROBOT_METAS]) ||
                !(in_array("NOARCHIVE", $page[self::ROBOT_METAS]) ||
                  in_array("NONE", $page[self::ROBOT_METAS])))) {
                $link = $query."&amp;a=cache&amp;arg=".
                    urlencode($page[self::URL]).
                    "&amp;its=".$page[self::CRAWL_TIME];
            } else {
                $link = htmlentities($page[self::URL]);
            }
        ?>
            <a href="<?php e($link); ?>" rel="nofollow"
            ><img src="<?php e($page[self::THUMB]); ?>" alt="<?php
                    e($page[self::TITLE]); ?>"  /></a>
        <?php
            $i++;
            if($i % $break_frequency == 0) {
                e('<br />');
            }
        }
        ?>
        </div>
        <?php
    }

}
?>