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
	 static $LANGPREFIX = array(
		'en' => "en",
		'en-US' => "en",
		'en-GB' => "en",
		'en-CA' => "en"
     );

     const FOLDER = "/search_filters/";
     const FILTERSUFFIX = "_bigrams.ftr";
     const TEXTSUFFIX = "_bigrams.txt";

    /**
     * Extracts Bigrams from input set of phrases.
     *
     * @param array $phrases subject to bigram check
     * @param string $lang Language to be used to stem bigrams.
     * @return array of bigrams and phrases
     */
    static function extractBigrams($phrases,$lang)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANGPREFIX[$lang])) {
		    $lang_prefix = self::$LANGPREFIX[$lang];
        }
        $filterFilePath 
		    = WORK_DIRECTORY.self::FOLDER.$lang_prefix.self::FILTERSUFFIX;
        if (file_exists($filterFilePath)) {
            $bigrams = BloomFilterFile::load($filterFilePath);
        }
        else {
            return $phrases;
        }
        $bigramsAndPhrases = array();
        $num_phrases = count($phrases);
        $i = 0;
        $j = 1;
        while($j<$num_phrases){
            $pair = $phrases[$i]." ".$phrases[$j];
            if($bigrams->contains(strtolower($pair))){
                $bigramsAndPhrases[] = $pair;
                $i += 2;
                $j += 2;
            }
            else{
                $bigramsAndPhrases[] = $phrases[$i];
                $i += 1;
                $j += 1;
            }
        }
        if($j==$num_phrases){
            $bigramsAndPhrases[] = $phrases[$j-1];
        }

        return $bigramsAndPhrases;
    }



    /**
     * Creates a bloom filter file from a bigram text file
     *
     * @param string $lang Language to be used to stem bigrams.
     * @param number $num_of_bigrams Count of bigrams in text file.
     * @return none
     */
    static function createBigramFilterFile($lang,$num_of_bigrams)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANGPREFIX[$lang])) {
            $lang_prefix = self::$LANGPREFIX[$lang];
        }
        $filterFilePath 
		    = WORK_DIRECTORY.self::FOLDER.$lang_prefix.self::FILTERSUFFIX;
        if (file_exists($filterFilePath)) {
            $bigrams = BloomFilterFile::load($filterFilePath);
        }
        else {
            $bigrams 
			    = new BloomFilterFile($filterFilePath, 
				    $num_of_bigrams, 10000);
        }
    
        $inputFilePath 
		    = WORK_DIRECTORY.self::FOLDER.$lang_prefix.self::TEXTSUFFIX;
        $fp = fopen($inputFilePath, 'r') or die("Can't open bigrams text file");
        while ( ($bigram = fgets($fp)) !== false) {
          $words 
		      = PhraseParser::
			      extractPhrasesOfLengthOffset(trim($bigram),1,0,$lang);
          if(strlen($words[0])==1){
              continue;
          }
          $bigram_stemmed = implode(" ",$words);
          $bigrams->add(strtolower($bigram_stemmed));
        }
        fclose($fp);

        $bigrams->save();
    }

    /**
     * Generates a bigrams text file from input xml file.
     *
     * @param string $inputXML XML file name to be used to extract bigrams.
     * @param string $lang Language to be used to create bigrams.
     * @return number $num_of_bigrams Count of bigrams in text file.
     */
    static function generateBigramsTextFile($inputXML,$lang)
    {
        ini_set("memory_limit","1024M");
        $lang_prefix = $lang;
		if(isset(self::$LANGPREFIX[$lang])) {
		    $lang_prefix = self::$LANGPREFIX[$lang];
		}
        $xmlFilePath = WORK_DIRECTORY.self::FOLDER.$inputXML;
        $fr = fopen($xmlFilePath, 'r') or die("Can't open xml file");
        $bigramsFilePath 
		    = WORK_DIRECTORY.self::FOLDER.$lang_prefix.self::TEXTSUFFIX;
        $fw = fopen($bigramsFilePath, 'w') or die("Can't open text file");
        $bigrams = array();
        while ( ($inputText = fgetss($fr)) !== false) {
            $inputText = strtolower($inputText);
            $pattern = '/#redirect\s\[\[[a-z]+[\s|_][a-z0-9]+\]\]/';
            preg_match($pattern, $inputText, $matches);
            if(count($matches)==1){
                $bigram 
				    = str_replace(array('#redirect [[',']]'),"",$matches[0]);
                $bigram = str_replace("_"," ",$bigram);
                $bigrams[] = $bigram;
            }
        }
        $bigrams = array_unique($bigrams);
        $num_of_bigrams = count($bigrams);
        sort($bigrams);
        $bigramsStr = implode("\n",$bigrams);
        fwrite($fw,$bigramsStr);
        fclose($fr);
        fclose($fw);
        return $num_of_bigrams;
    }
}
