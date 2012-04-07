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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the url parser library we'll be testing
 */
require_once BASE_DIR."/lib/phrase_parser.php"; 

/**
 *  Used to test that the PhraseParser class. Want to make sure bigram
 *  extracting works correctly
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class PhraseParserTest extends UnitTest
{
    /**
     * PhraseParser uses static methods so doesn't do anything right now
     */
    public function setUp()
    {
    }

    /**
     * PhraseParser uses static methods so doesn't do anything right now
     */
    public function tearDown()
    {
    }

    /**
     * 
     */
    public function extractPhrasesTestCase()
    {
        $phrase_string = <<< EOD
THE THE
‘Deep Space’ ‘Deep Space’ version of GIANT
©2012
EOD;
        $word_lists = PhraseParser::extractPhrasesInLists($phrase_string,
            MAX_PHRASE_LEN, "en-US", true);
        $words = array_keys($word_lists);

        $this->assertTrue(in_array("the the", $words), "Extract Bigram 1");
        $this->assertTrue(in_array("deep space", $words), "Extract Bigram 2");
        $this->assertTrue(in_array("deep", $words), "Unigrams still present 1");
        $this->assertTrue(in_array("space", $words),"Unigrams still present 2");
        $this->assertTrue(in_array("2012", $words), "Punctuation removal 1");
        $phrase_string = <<< EOD
THE . THE
EOD;
        $word_lists = PhraseParser::extractPhrasesInLists($phrase_string,
            MAX_PHRASE_LEN, "en-US", true);
        $words = array_keys($word_lists);
        $this->assertFalse(in_array("the the", $words), "No Bigram when ".
            "punctuation present");

        $phrase_string = <<< EOD
 百度一下，你就知道 
 .. 知 道 MP3 图 片 视 频 地 图 输入法 手写
拼音 关闭 空间 百科 hao123 | 更多>> 
About Baidu
EOD;
        $word_lists = PhraseParser::extractPhrasesInLists($phrase_string,
            MAX_PHRASE_LEN, "zh-CN", true);
        $words = array_keys($word_lists);
        $this->assertTrue(in_array("百度", $words), "Chinese test 1");
        $this->assertTrue(in_array("mp3", $words), "Chinese test 2");
        $this->assertTrue(in_array("ab", $words), "Chinese test 3");
        $this->assertFalse(in_array("", $words), "Chinese test 3");
        $this->assertFalse(in_array("下，", $words), "Chinese test 4");
    }

}
?>
