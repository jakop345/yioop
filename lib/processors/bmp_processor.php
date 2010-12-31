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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Used for the getDocumentFilename method in UrlParser */
require_once BASE_DIR."/lib/url_parser.php";
/** Load base class, if needed */
require_once BASE_DIR."/lib/processors/image_processor.php";

/**
 * Used to create crawl summary information 
 * for BMP and ICO files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class BmpProcessor extends ImageProcessor
{

    /**
     * {@inheritdoc}
     */
    public static function process($page, $url)
    {
        if(is_string($page)) {
            file_put_contents(CRAWL_DIR."/cache/tmp.bmp", $page); 
            $image = @imagecreatefrombmp(CRAWL_DIR."/cache/tmp.bmp");
            $thumb_string = self::createThumb($image);
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = "Image of ".
                UrlParser::getDocumentFilename($url);
            $summary[self::LINKS] = array();
            $summary[self::PAGE] = 
                "<html><body><div><img src='data:image/bmp;base64," .
                base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                "' /></div></body></html>";
            $summary[self::THUMB] = 'data:image/jpeg;base64,'.
                base64_encode($thumb_string);
        }
        return $summary;
    }

}

?>
