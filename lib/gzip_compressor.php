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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Loads the base class if needed 
 */
require_once "compressor.php";

 
/**
 * Implementation of a Compressor using GZIP/GUNZIP as the filter.
 * More details on these algorithms can be found at 
 * {@link http://en.wikipedia.org/wiki/Gzip}
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */ 
class GzipCompressor implements Compressor
{
    /** Constructor does nothing
     */
    function __construct() {}

    /**
     * Applies the Compressor compress filter to a string before it is inserted 
     * into a WebArchive. In this case, applying the filter means gzipping.
     *
     * @param string $str  string to apply filter to
     * @return string  the result of applying the filter
     */
    function compress($str)
    {
        return gzcompress($str, 9);
    }

    /**
     * Used to unapply the compress filter as when data is read out of a 
     * WebArchive. In this case, unapplying the filter means gunzipping.
     *
     * @param string $str  data read from a string archive
     * @return string result of uncompressing
     */
     function uncompress($str)
    {
        return gzuncompress($str);
    }

} 
?>
