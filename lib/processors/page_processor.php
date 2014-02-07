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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Used by subclasses, so have succinct access (i.e., can use self:: rather
 * than CrawlConstants::) to constants like:
 * CrawlConstants::TITLE, CrawlConstants::DESCRIPTION, etc.
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Base class common to all processors of web page data
 *
 * Subclasses PageProcessor stored in 
 *      WORK_DIRECTORY/app/lib/processors
 * will be detected by Yioop. So one can add code there to make it easier
 * to upgrade Yioop. I.e., your site specific code can stay in the work
 * directory and you merely need to replace the Yioop folder when upgrading.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
abstract class PageProcessor implements CrawlConstants
{
    /**
     * indexing_plugins which might be used with the current processor
     *
     * @var array
     */
    var $plugin_instances;

    /**
     * Max number of chars to extract for description from a page to index.
     * Only words in the description are indexed.
     * @var int
     */
    static $max_description_len;

    /**
     *  Set-ups the any indexing plugins associated with this page
     *  processor
     *
     *  @param array $plugins an array of indexing plugins which might
     *      do further processing on the data handles by this page
     *      processor
     */
    function __construct($plugins = array(), $max_description_len = NULL) {
        $this->plugin_instances = $plugins;
        if($max_description_len != NULL) {
            self::$max_description_len = $max_description_len;
        } else {
            self::$max_description_len = MAX_DESCRIPTION_LEN;
        }
    }

    /**
     *  Method used to handle processing data for a web page. It makes
     *  a summary for the page (via the process() function which should
     *  be subclassed) as well as runs any plugins that are associated with
     *  the processors to create sub-documents
     *
     * @param string $page string of a web document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of
     *      the information in $page also has a subdocs array containing any
     *      subdocuments returned from a plugin. A subdocumenst might be
     *      things like recipes that appeared in a page or tweets, etc.
     */
    function handle($page, $url)
    {
        $summary = $this->process($page, $url);
        if($summary != NULL && isset($this->plugin_instances) &&
            is_array($this->plugin_instances) ) {
            $summary[self::SUBDOCS] = array();
            foreach($this->plugin_instances as $plugin_instance) {
                $subdoc = NULL;
                $class_name = get_class($plugin_instance);
                $subtype = lcfirst(substr($class_name, 0, -strlen("Plugin")));
                $subdocs_description = $plugin_instance->pageProcessing(
                    $page, $url);
                if(is_array($subdocs_description)
                    && count($subdocs_description) != 0) {
                    foreach($subdocs_description as $subdoc_description) {
                        $subdoc[self::TITLE] = $subdoc_description[self::TITLE];
                        $subdoc[self::DESCRIPTION] =
                            $subdoc_description[self::DESCRIPTION];
                        $subdoc[self::LANG] = $summary[self::LANG];
                        $subdoc[self::LINKS] = $summary[self::LINKS];
                        $subdoc[self::PAGE] = $page;
                        $subdoc[self::SUBDOCTYPE] = $subtypes;
                        $summary[self::SUBDOCS][] = $subdoc;
                    }
                }
                $plugin_instance->pageSummaryProcessing($summary);
            }
        }
        return $summary;
    }

    /**
     * Should be implemented to compute a summary based on a
     * text string of a document. This method is called from
     * @see handle($page, $url)
     *
     * @param string $page string of a document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of
     *      the information in $page
     */
    abstract function process($page, $url);
}

?>
