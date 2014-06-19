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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load search engine-wide configuration file
 */
require_once BASE_DIR.'/configs/config.php';
/**
 * Load the PdfProcessor class we are going to test
 */
require_once BASE_DIR."/lib/processors/pdf_processor.php";
/**
 * Load the base unit test class
 */
require_once BASE_DIR."/lib/unit_test.php";
/**
 * Load the Crawl constants required for the summary
 */
 require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * UnitTest for the PdfProcessor class. A PdfProcessor is used to process
 * a .pdf file and extract summary from it. This
 * class tests the processing of an .pdf file.
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage test
 */
class PdfProcessorTest extends UnitTest implements CrawlConstants
{
    /**
     * Creates a new PdfProcessor object using the test.pdf
     * file made from the seekquarry landing page
     */
    function setUp()
    {
        $pdf_object = new PdfProcessor();
        $url = "http://www.yioop.com/test.pdf";
        $filename = BASE_DIR."/tests/test_files/test.pdf";
        $page = file_get_contents($filename);
        $summary = $pdf_object->process($page, $url);
        $this->test_objects['summary'] = $summary;
    }
    /**
     * Delete any files associated with our test on PdfProcessor (in this case
     * none)
     */
    function tearDown()
    {
    }
    /**
     * Test case to check whether words known to be in the PDF were extracted
     * is retrieved correctly.
     */
    function wordExtractionTestCase()
    {
        $words = explode(" ",
            $this->test_objects['summary'][self::DESCRIPTION]);
        $this->assertTrue(in_array("Documentation", $words),
            "Word Extraction 1");
        $this->assertTrue(in_array("Yioop", $words),
            "Word Extraction 2");
        $this->assertTrue(in_array("Open", $words),
            "Word Extraction 3");
    }

}
