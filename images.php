<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * Images entry point for Yioop!
 * search site. Used to both get and display image
 * search results. Makes use of the main index.php
 * entry point.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

/** 
 *  Set that want only image results
 */
    define("MEDIA", "Images");
    if(!isset($_REQUEST['num'])) {
        $_REQUEST['num']= 50;
    }
    if(isset($_REQUEST['c']) && $_REQUEST['c'] != 'search') {
        $pathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);
        require_once($pathinfo["dirname"]."/error.php");
        exit();
    }
    require_once("index.php");
?>
