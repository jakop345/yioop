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
 * Enumerative interface for common constants between WordIterator and
 * IndexArchiveBundle
 *
 * These constants are used as fields in arrays. They are negative to 
 * distinguish them from normal array elements 0, 1, 2... However, this
 * means you need to be slightly careful if you try to sort the array
 * as this might screw things up
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
interface IndexingConstants
{
    const COUNT = -1;
    const END_BLOCK = -2;
    const LIST_OFFSET = -3;
    const POINT_BLOCK = -4;
    const PARTIAL_COUNT = -5;
    const NAME = -6;
}
?>
