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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Used by subclasses, so have succinct access (i.e., can use self:: rather than CrawlConstants::) to constants like:
 *  CrawlConstants::TITLE, CrawlConstants::DESCRIPTION, etc.
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Base abstract class common to all processors used to create crawl summary information from images
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
abstract class ImageProcessor implements CrawlConstants
{

    /**
     *  Extract summary data from the image provided in $page together the url in $url where it was downloaded from
     *  
     *  ImageProcessor class defers a proper implementation of this method to subclasses
     *  @param string $page  the image represented as a character string
     *  @param string $url  the url where the image was downloaded from
     *  @return array summary information including a thumbnail and a description (where the description is just the url)
     */
    static function process($page, $url) { return NULL;} 

    /**
     * Used to create a thumbnail from an image object
     *
     * @param $image - image object with image
     *
     */
    static function createThumb($image)
    {
        $thumb = imagecreatetruecolor(50, 50);
        if( isset($image) && $image != NULL && is_object($image) ) {
            $size_x = imagesx($image);
            $size_y = imagesy($image);

            @imagecopyresampled($thumb, $image, 0,0, 0,0, 50, 50, $size_x, $size_y);
            imagedestroy($image);
        }
        imagejpeg( $thumb, CRAWL_DIR."/cache/thumb.jpg", 100 );
        imagedestroy($thumb);
        $thumb_string = file_get_contents(CRAWL_DIR."/cache/thumb.jpg");

        return $thumb_string;
    }

}

?>
