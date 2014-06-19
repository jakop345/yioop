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
 * @author Snigdha Rao Parvatneni
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Register File Types We Handle*/
$add_extensions = array("xml");
if(!isset($INDEXED_FILE_TYPES)) {
    $INDEXED_FILE_TYPES = array();
}
$INDEXED_FILE_TYPES = array_merge($INDEXED_FILE_TYPES, $add_extensions);
$add_types = array(
    "text/gitxml" => "GitXmlProcessor"
);
if(!isset($PAGE_PROCESSORS)) {
    $PAGE_PROCESSORS = array();
}
$PAGE_PROCESSORS =  array_merge($PAGE_PROCESSORS, $add_types);
/**
 * Load the base class
 */
require_once BASE_DIR."/lib/processors/text_processor.php";
/**
 * So can extract parts of the URL if need to guess lang
 */
require_once BASE_DIR."/lib/url_parser.php";
/**
 * Parent class common to all processors used to create crawl summary
 * information  that involves basically text data
 *
 * @author Snigdha Rao Parvatneni
 * @package seek_quarry
 * @subpackage processor
 */
class GitXmlProcessor extends TextProcessor
{
}
?>
