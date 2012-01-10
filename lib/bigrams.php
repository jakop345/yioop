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
 * @author Ravi Dhillon ravi.dhillon@yahoo.com
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the stem word functions
 */
foreach(glob(BASE_DIR."/lib/stemmers/*_stemmer.php")
    as $filename) {
    require_once $filename;
}

/**
 * Load the Bloom Filter File
 */
require_once BASE_DIR."/lib/bloom_filter_file.php";

/**
 * Load the Phrase Parser File
 */
require_once BASE_DIR."/lib/phrase_parser.php";

/**
 * Library of functions used to create and extract bigrams
 *
 * @author Ravi Dhillon
 *
 * @package seek_quarry
 * @subpackage library
 */
class Bigrams
{

    /**
     * Language tags and their corresponding bigram prefix
     * @var array
     */
     static $LANG_PREFIX = array(
        'en' => "en",
        'en-US' => "en",
        'en-GB' => "en",
        'en-CA' => "en"
     );

     const FILTER_FOLDER = "/search_filters/";
     const FILTER_SUFFIX = "_bigrams.ftr";
     const TEXT_SUFFIX = "_bigrams.txt";

    /**
     * Extracts Bigrams from input set of phrases.
     *
     * @param array $phrases subject to bigram check
     * @param string $lang Language to be used to stem bigrams.
     * @return array of bigrams and phrases
     */
    static function extractBigrams($phrases, $lang)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $filter_path = WORK_DIRECTORY . 
            self::FILTER_FOLDER . $lang_prefix . self::FILTER_SUFFIX;
        if (file_exists($filter_path)) {
            $bigrams = BloomFilterFile::load($filter_path);
        }
        else {
            return $phrases;
        }
        $bigram_phrases = array();
        $num_phrases = count($phrases);
        $i = 0;
        $j = 1;
        while($j < $num_phrases){
            $pair = $phrases[$i]." ".$phrases[$j];
            if($bigrams->contains(strtolower($pair))){
                $bigram_phrases[] = $pair;
                $i += 2;
                $j += 2;
            }
            else{
                $bigram_phrases[] = $phrases[$i];
                $i += 1;
                $j += 1;
            }
        }
        if($j == $num_phrases) {
            $bigram_phrases[] = $phrases[$j - 1];
        }

        return $bigram_phrases;
    }



    /**
     * Creates a bloom filter file from a bigram text file
     *
     * @param string $lang Language to be used to stem bigrams.
     * @param number $num_of_bigrams Count of bigrams in text file.
     * @return none
     */
    static function createBigramFilterFile($lang, $num_of_bigrams)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $filter_path = 
            WORK_DIRECTORY . self::FILTER_FOLDER . $lang_prefix . 
            self::FILTER_SUFFIX;
        if (file_exists($filter_path)) {
            $bigrams = BloomFilterFile::load($filter_path);
        }
        else {
            $bigrams = new BloomFilterFile($filter_path, 
                    $num_of_bigrams, 10000);
        }

        $inputFilePath = WORK_DIRECTORY . self::FILTER_FOLDER . 
            $lang_prefix . self::TEXT_SUFFIX;
        $fp = fopen($inputFilePath, 'r') or die("Can't open bigrams text file");
        while ( ($bigram = fgets($fp)) !== false) {
          $words 
              = PhraseParser::
                  extractPhrasesOfLengthOffset(trim($bigram), 1, 0, $lang);
          if(strlen($words[0]) == 1){
              continue;
          }
          $bigram_stemmed = implode(" ", $words);
          $bigrams->add(strtolower($bigram_stemmed));
        }
        fclose($fp);

        $bigrams->save();
    }

    /**
     * Generates a bigrams text file from input wikimedia xml file.
     *
     * @param string $wiki_file wikimedia XML file name to be used to extract bigrams.
     * @param string $lang Language to be used to create bigrams.
     * @return number $num_of_bigrams Count of bigrams in text file.
     */
    static function generateBigramsTextFile($wiki_file, $lang)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $wiki_file_path = WORK_DIRECTORY.self::FILTER_FOLDER.$wiki_file;
        $fr = fopen($wiki_file_path, 'r') or die("Can't open xml file");
        $bigrams_file_path 
            = WORK_DIRECTORY.self::FILTER_FOLDER.$lang_prefix.self::TEXT_SUFFIX;
        $fw = fopen($bigrams_file_path, 'w') or die("Can't open text file");
        $bigrams = array();
        while ( ($input_text = fgetss($fr)) !== false) {
            $input_text = strtolower($input_text);
            $pattern = '/#redirect\s\[\[[a-z]+[\s|_][a-z0-9]+\]\]/';
            preg_match($pattern, $input_text, $matches);
            if(count($matches) == 1) {
                $bigram = str_replace(
                    array('#redirect [[',']]'), "", $matches[0]);
                $bigram = str_replace("_", " ", $bigram);
                $bigrams[] = $bigram;
            }
        }
        $bigrams = array_unique($bigrams);
        $num_of_bigrams = count($bigrams);
        sort($bigrams);
        $bigrams_string = implode("\n", $bigrams);
        fwrite($fw, $bigrams_string);
        fclose($fr);
        fclose($fw);
        return $num_of_bigrams;
    }
}
