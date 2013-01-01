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
 *  Load the Italian stemmer via phrase_parser (5.4 hack)
 */
require_once BASE_DIR."/lib/phrase_parser.php";
/**
 *  Load the run function
 */
require_once BASE_DIR.'lib/unit_test.php';

/**
 * My code for testing the Italian stemming algorithm. The inputs for the
 * algorithm are words in
 * http://snowball.tartarus.org/algorithms/italian/voc.txt and the resulting
 * stems are compared with the stem words in
 * http://snowball.tartarus.org/algorithms/italian/output.txt
 *
 * @author Akshat Kukreti
 * @package seek_quarry
 * @subpackage test
 */

class ItStemmerTest extends UnitTest
{
    function setUp()
    {
        $this->test_objects['FILE1'] = new ItStemmer();
    }

    function tearDown()
    {
    }

    /**
     * Tests whether the stem funtion for the Italian stemming algorithm
     * stems words according to the rules of stemming. The function tests stem
     * by calling stem with the words in $test_words and compares the results
     * with the stem words in $stem_words
     *
     * $test_words is an array containing a set of words in Italian provided in
     * the snowball web page
     * $stem_words is an array containing the stems for words in $test_words
     */
    function stemmerTestCase()
    {
        $stem_dir = BASE_DIR.'/tests/test_files/italian_stemmer';

        //Test word set from snowball
        $test_words = file("$stem_dir/input_vocabulary.txt");
        //Stem word set from snowball for comparing results
        $stem_words = file("$stem_dir/stemmed_result.txt");

        /**
         * check if function stem correctly stems the words in $test_words by
         * comparing results with stem words in $stem_words
         */
        for($i = 0; $i < count($test_words); $i++){
            $word = trim($test_words[$i]);
            $stem = trim($stem_words[$i]);
            $this->assertEqual(
                $this->test_objects['FILE1']->stem($word),
                    $stem,"function stem correctly stems
                    $word to $stem");
        }
    }
}
?>
