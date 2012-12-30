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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load search engine-wide configuration file */
require_once BASE_DIR.'/configs/config.php';

define("UNIT_TEST_MODE", true);
/**
 *  Load the queue server class we'll be testing
 */
require_once BASE_DIR."/bin/queue_server.php";

/**
 *  Used to test functions related to scheduling websites to crawl for
 *  a web crawl (the responsibility of a QueueServer)
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class QueueServerTest extends UnitTest
{
    /**
     * Creates a QueueServer object with an initial set of indexed file types
     */
    function setUp()
    {
        $INDEXED_FILE_TYPES = array("html", "txt");
        $this->test_objects['Q_SERVER'] =  new QueueServer($INDEXED_FILE_TYPES);
    }

    /**
     * Used to get rid of any object/files we created during a test case.
     * that need to be disposed of.
     */
    function tearDown()
    {
        // get rid of the queue_server from previous test case
        $this->test_objects['Q_SERVER'] = null;
    }

    /**
     * urlMemberSiteArray is a function called by both allowedToCrawlSite
     * disallowedToCrawlSite to test if a url belongs to alist of
     * regex's of urls or domain. This test function tests this functionality
     */
    function urlMemberSiteArrayTestCase()
    {
        $q_server = $this->test_objects['Q_SERVER'];
        $sites = array("http://www.example.com/",
            "http://www.cs.sjsu.edu/faculty/pollett/*/*/",
            "http://www.bing.com/video/search?*&*&",
            "http://*.cool.*/a/*/", "domain:ucla.edu");
       $test_urls = array(
            array("http://www.cs.sjsu.edu/faculty/pollett/", false,
                "regex url negative 1"),
            array("http://www.bing.com/video/search?", false,
                "regex url negative 2"),
            array("http://www.cool.edu/a", false,
                "regex url negative 3"),
            array("http://ucla.edu.com", false,
                "domain test negative"),
            array("http://www.cs.sjsu.edu/faculty/pollett/a/b/c", true,
                "regex url positive 1"),
            array("http://www.bing.com/video/search?a&b&c", true,
                "regex url positive 2"),
            array("http://www.cool.bob.edu/a/b/c", true,
                "regex url positive 3"),
            array("http://test.ucla.edu", true,
                "domain test positive"),
        );
        foreach($test_urls as $test_url) {
            $result = $q_server->urlMemberSiteArray($test_url[0], $sites);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }

    /**
     * allowedToCrawlSite check if a url is  matches a list of url
     * and domains stored in a QueueServer's allowed_sites and that it
     * is of an allowed to crawl file type. This function tests these
     * properties
     */
    function allowedToCrawlSiteTestCase()
    {
        $q_server = $this->test_objects['Q_SERVER'];
        $q_server->allowed_sites = array("http://www.example.com/",
            "domain:ca", "domain:somewhere.tv");
        $test_urls = array(
            array("http://www.yoyo.com/", true,
                "not restrict by url case", array(
                "restrict_sites_by_url" => false)),
            array("http://www.yoyo.com/", false,
                "simple not allowed case", array(
                "restrict_sites_by_url" => true)),
            array("http://www.example.com/", true,
                "simple allowed site", array(
                "restrict_sites_by_url" => true)),
            array("http://www.example.com/a.bad", false,
                "not allowed filetype"),
            array("http://www.example.com/a.txt", true,
                "allowed filetype"),
            array("http://www.uchicago.com/", false,
                "domain disallowed", array(
                "restrict_sites_by_url" => true)),
            array("http://www.findcan.ca/", true,
                "domain disallowed", array(
                "restrict_sites_by_url" => true)),
            array("http://somewhere.tv.com/", false,
                "domain disallowed", array(
                "restrict_sites_by_url" => true)),
            array("http://woohoo.somewhere.tv/", true,
                "domain disallowed", array(
                "restrict_sites_by_url" => true)),
        );

        foreach($test_urls as $test_url) {
            if(isset($test_url[3])) {
                foreach($test_url[3] as $field => $value) {
                    $q_server->$field = $value;
                }
            }
            $result = $q_server->allowedToCrawlSite($test_url[0]);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }

    /**
     * disallowedToCrawlSite check if a url is  matches a list of url
     * and domains stored in a QueueServer's disallowed_sites. This function
     * tests this properties (The test cases are similar to those of
     * urlMemberSiteArrayTestCase, but are using the disallowed_sites array)
     */
    function disallowedToCrawlSiteTestCase()
    {
        $q_server = $this->test_objects['Q_SERVER'];
        $q_server->disallowed_sites = array("http://www.example.com/",
            "http://www.cs.sjsu.edu/faculty/pollett/*/*/",
            "http://www.bing.com/video/search?*&*&",
            "http://*.cool.*/a/*/");

       $test_urls = array(
            array("http://www.cs.sjsu.edu/faculty/pollett/", false,
                "regex url negative 1"),
            array("http://www.bing.com/video/search?", false,
                "regex url negative 2"),
            array("http://www.cool.edu/a", false,
                "regex url negative 3"),
            array("http://www.cs.sjsu.edu/faculty/pollett/a/b/c", true,
                "regex url positive 1"),
            array("http://www.bing.com/video/search?a&b&c", true,
                "regex url positive 2"),
            array("http://www.cool.bob.edu/a/b/c", true,
                "regex url positive 3"),
        );

        foreach($test_urls as $test_url) {
            $result = $q_server->disallowedToCrawlSite($test_url[0]);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
}
?>