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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage locale
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Chinese specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage locale
 */

class ZhTokenizer
{
    /**
     * Removes the stop words from the page
     * @param string $page the page to remove stop words from.
     * @return string $page with no stop words
     */
    static function stopwordsRemover($page)
    {
        return $page;
    }
    /**
     * A word segmenter.
     * Such a segmenter on input thisisabunchofwords would output
     * this is a bunch of words
     *
     * @param string $pre_segment  before segmentation
     * @return string with words separated by space
     */
    static function segment($pre_segment)
    {
        return PhraseParser::reverseMaximalMatch($pre_segment, "zh-CN");
    }

    /**
     * For a language with word stems computes a stem of a word. For
     * Chinese just returns false.
     *
     * As an example of the stem of a word consider: jumps, jumping, jumpy,
     * all have jump as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    static function stem($word)
    {
        return false;
    }
}
?>
