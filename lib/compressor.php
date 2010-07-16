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
 * A Compressor is used to apply a filter to objects before they are stored 
 * into a WebArchive. The filter is assumed to be invertible, and the typical 
 * intention is the filter carries out some kind of string compression.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */ 
 
interface Compressor
{
    /**
     * Applies the Compressor compress filter to a string before it is 
     * inserted into a WebArchive.
     *
     * @param string $str  string to apply filter to
     * @return string  the result of applying the filter
     */
    function compress($str);
    
    /**
     * Used to unapply the compress filter as when data is read out of a 
     * WebArchive.
     *
     * @param string $str  data read from a string archive
     * @return string result of uncompressing
     */
    function uncompress($str);
} 
?>
