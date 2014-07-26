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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
ini_set("gd.jpeg_ignore_warning", 1);
/** Register File Types We Handle*/
$INDEXED_FILE_TYPES[] = "jpg";
$INDEXED_FILE_TYPES[] = "jpeg";
$IMAGE_TYPES[] = "jpg";
$IMAGE_TYPES[] = "jpeg";
$PAGE_PROCESSORS["image/jpeg"] = "JpgProcessor";
/** Used for the getDocumentFilename method in UrlParser */
require_once BASE_DIR."/lib/url_parser.php";
/** Load base class, if needed */
require_once BASE_DIR."/lib/processors/image_processor.php";
/**
 * Used to create crawl summary information
 * for JPEG files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class JpgProcessor extends ImageProcessor
{
    /**
     * {@inheritDoc}
     *
     * @param string $page  the image represented as a character string
     * @param string $url  the url where the image was downloaded from
     * @return array summary information including a thumbnail and a
     *     description (where the description is just the url)
     */
    function process($page, $url)
    {
        if(is_string($page)) {
            $image = @imagecreatefromstring($page);
            $thumb_string = self::createThumb($image);
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = "Image of ".
                UrlParser::getDocumentFilename($url);
            $summary[self::LINKS] = array();
            $summary[self::PAGE] =
                "<html><body><div><img src='data:image/jpeg;base64," .
                base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                "' /></div></body></html>";
            $summary[self::THUMB] = 'data:image/jpeg;base64,'.
                base64_encode($thumb_string);
        }
        return $summary;
    }
}
?>
