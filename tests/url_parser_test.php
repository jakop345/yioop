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

/**
 *  Load the url parser library we'll be testing
 */
require_once BASE_DIR."/lib/url_parser.php";

/**
 *  Used to test that the UrlParser class. For now, want to see that the
 *  method canonicalLink is working correctly and that
 *  isPathMemberRegexPaths (used in robot_processor.php) works
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class UrlParserTest extends UnitTest
{
    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    function setUp()
    {
    }

    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    function tearDown()
    {
    }

    /**
     * Check if can go from a relative link, base link to a complete link
     * in various different ways
     */
    function canonicalLinkTestCase()
    {
        $test_links = array(
            array(".", "http://www.example.com/",
                "http://www.example.com/", "root dir0"),
            array("/bob.html", "http://www.example.com/",
                "http://www.example.com/bob.html", "root dir1"),
            array("bob.html", "http://www.example.com/",
                "http://www.example.com/bob.html", "root dir2"),
            array("bob", "http://www.example.com/",
                "http://www.example.com/bob", "root dir3"),
            array("bob", "http://www.example.com",
                "http://www.example.com/bob", "root dir4"),
            array("http://print.bob.com/bob", "http://www.example.com",
                "http://print.bob.com/bob", "root dir5"),
            array("/.", "http://www.example.com/",
                "http://www.example.com/", "root dir6"),
            array("//slashdot.org", "http://www.slashdot.org",
                "http://slashdot.org/", "slashdot dir"),
            array("bob", "http://www.example.com/a",
                "http://www.example.com/a/bob", "sub dir1"),
            array("../bob", "http://www.example.com/a",
                "http://www.example.com/bob", "sub dir2"),
            array("../../bob", "http://www.example.com/a",
                NULL, "sub dir3"),
            array("./bob", "http://www.example.com/a",
                "http://www.example.com/a/bob", "sub dir4"),
            array("bob.html?a=1", "http://www.example.com/a",
                "http://www.example.com/a/bob.html?a=1", "query 1"),
            array("bob?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/a/bob?a=1&b=2", "query 2"),
            array("/?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/?a=1&b=2", "query 3"),
            array("?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/a/?a=1&b=2", "query 4"),
            array("b/b.html?a=1&b=2", "http://www.example.com/a/c",
                "http://www.example.com/a/c/b/b.html?a=1&b=2", "query 5"),
            array("b/b.html?a=1&b=2?c=4", "http://www.example.com/a/c",
                "http://www.example.com/a/c/b/b.html?a=1&b=2?c=4", "query 6"),
            array("b#1", "http://www.example.com/",
                "http://www.example.com/b#1", "fragment 1"),
            array("b?a=1#1", "http://www.example.com/",
                "http://www.example.com/b?a=1#1", "fragment 2"),
            array("b?a=1#1#2", "http://www.example.com/",
                "http://www.example.com/b?a=1#1#2", "fragment 3"),
            array("#a", "http://www.example.com/c:d",
                "http://www.example.com/c:d#a", "fragment 4"),
        );

        foreach($test_links as $test_link) {
            $result = UrlParser::canonicalLink($test_link[0],
                $test_link[1], false);
            $this->assertEqual($result, $test_link[2], $test_link[3]);
        }

    }


    /**
     * Check is a path matches with a list of paths presumably coming from
     * a robots.txt file
     */
    function isPathMemberRegexPathsTestCase()
    {
        $path = array();
        $robot_paths = array();
        $results = array();
        $tests = array(
            array("/bobby", array("/bob"), true, "Substring Positive"),
            array("/bobby", array("/alice", "/f/g/h/d"), false,
                "Substring Negative 1"),
            array("/bobby/", array("/bobby/bay", "/f/g/h/d", "/yo"), false,
                "Substring Negative 2"),
            array("/bay/bobby/", array("/bobby/", "/f/g/h/d", "/yo"), false,
                "Substring Negative 3 (should match start)"),
            array("http://test.com/bay/bobby/",
                array("/bobby/", "/f/g/h/d", "/yo"), false,
                "Substring Negative 4 (should match start)"),
            array("/a/bbbb/c/", array("/bobby/bay", "/a/*/c/", "/yo"), true,
                "Star Positive 1"),
            array("/a/bbbb/d/", array("/bobby/bay", "/a/*/c/", "/yo"), false,
                "Star Negative 1"),
            array("/test.html?a=b", array("/bobby/bay", "/*?", "/yo"), true,
                "Star Positive 2"),
            array("/test.html", array("/bobby/bay", "/*.html$", "/yo"), true,
                "Dollar Positive 1"),
            array("/test.htmlish", array("/bobby/bay", "/*.html$", "/yo"),false,
                "Dollar Negative 1"),
            array("/test.htmlish", array("/bobby/bay", "*", "/yo"),true,
                "Degenerate 1"),
            array("/test.html", array("/bobby/bay", "/**.html$", "/yo"), true,
                "Degenerate 2"),
            array("/videos/search?q=Angelina+Jolie",
                 array("/videos/search?"), true,
                "End With Question Regex Case 1"),
        );
        foreach ($tests as $test) {
            list($path, $robot_paths, $result, $description) = $test;
            $this->assertEqual(UrlParser::isPathMemberRegexPaths($path,
                $robot_paths), $result, $description);
        }
    }

    /**
     * Tests simplifyUrl function used on SERP pages
     */
    function simplifyUrlTestCase()
    {
        $test_urls = array(
            array("http://www.example.com/", 100,
                "www.example.com", "HTTP Domain only"),
            array("https://www.example.com/", 100,
                "www.example.com", "HTTPS Domain only"),
            array("http://www.superreallylongexample.com/", 25,
                "www.superreallylonge...e.com", "Domain truncate"),
            array("http://www.example.com/word1/word2/word3/word4", 25,
                "www.example.com/word...word4", "Path truncate"),
        );

        foreach($test_urls as $test_url) {
            $result = UrlParser::simplifyUrl($test_url[0], $test_url[1]);
            $this->assertEqual($result, $test_url[2], $test_url[3]);
        }
    }

    /**
     * urlMemberSiteArray is a function called by both allowedToCrawlSite
     * disallowedToCrawlSite to test if a url belongs to alist of
     * regex's of urls or domain. This test function tests this functionality
     */
    function urlMemberSiteArrayTestCase()
    {
        $sites = array("http://www.example.com/",
            "http://www.cs.sjsu.edu/faculty/pollett/*/*/",
            "http://www.bing.com/video/search?*&*&",
            "http://*.cool.*/a/*/", "domain:ucla.edu",
            "domain:foodnetwork.com");
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
            array("http://www.foodnetworkstore.com/small-appliances/", false,
                "domain test negative"),
        );
        foreach($test_urls as $test_url) {
            $result = UrlParser::urlMemberSiteArray($test_url[0], $sites,
                "s");
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
}
?>
