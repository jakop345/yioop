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
 * @author Ravi Dhillon ravi.dhillon@yahoo.com, Chris Pollett
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
 * Library of functions used to create and extract n word grams
 *
 * @author Ravi Dhillon (Bigram Version), Chris Pollett (ngrams + rewrite +
 *  support for page count dumps)
 *
 * @package seek_quarry
 * @subpackage library
 */
class NWordGrams
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
     /**
      * Static copy of n-grams files
      * @var object
      */
    static $ngrams = NULL;
     /**
      * Name of the folder inside user work directory
      * that contains the input compressed XML file. The filter
      * file generated will also be stored in this folder.
      */
     const FILTER_FOLDER = "/search_filters/";
     /**
      * 
      */
     const BLOCK_SIZE = 8192;
     /**
      * Suffix appended to language tag to create the
      * filter file name containing bigrams.
      */
     const FILTER_SUFFIX = "grams.ftr";
     /**
      * Suffix appended to language tag to create the
      * text file name containing bigrams.
      */
     const TEXT_SUFFIX = "grams.txt";

     const REDIRECT = 0;
     const TITLE = 1;
     const PAGE_COUNT_DUMPS = 2;

    /**
     * Says whether or not phrase exists in the N word gram Bloom Filter
     *
     * @param $phrase what to check if is a bigram
     * @param string $lang language of bigrams file
     * @return true or false
     */
    static function ngramsContains($phrase, $lang, $num_gram = 2)
    {
        
        if(self::$ngrams == NULL || !isset(self::$ngrams[$num_gram])) {
            $lang_prefix = $lang;
            if(isset(self::$LANG_PREFIX[$lang])) {
                $lang_prefix = self::$LANG_PREFIX[$lang];
            }
            $filter_path = WORK_DIRECTORY .
                self::FILTER_FOLDER . $lang_prefix . "_{$num_gram}_" . 
                self::FILTER_SUFFIX;
            if (file_exists($filter_path)) {
                self::$ngrams[$num_gram] = BloomFilterFile::load($filter_path);
            } else  {
                return false;
            }
        }
        return self::$ngrams[$num_gram]->contains(strtolower($phrase));
    }

    /**
     * Creates a bloom filter file from a n word gram text file. The
     * path of n word gram text file used is based on the input $lang.
     * The name of output filter file is based on the $lang and the 
     * number n. Size is based on input number of n word grams .
     * The n word grams are read from text file, stemmed if a stemmer
     * is available for $lang and then stored in filter file.
     *
     * @param string $lang language to be used to stem bigrams.
     * @param int $num_gram number of words in grams we are storing
     * @param number $num_ngrams_found count of n word grams in text file.
     * @return none
     */
    static function createNWordGramsFilterFile($lang, $num_gram, 
        $num_ngrams_found)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $filter_path =
            WORK_DIRECTORY . self::FILTER_FOLDER . $lang_prefix .
            "_{$num_gram}_" . self::FILTER_SUFFIX;
        if (file_exists($filter_path)) {
            $ngrams = BloomFilterFile::load($filter_path);
        }
        else {
            $ngrams = new BloomFilterFile($filter_path, $num_ngrams_found);
        }

        $inputFilePath = WORK_DIRECTORY . self::FILTER_FOLDER .
            $lang_prefix . "_{$num_gram}_" . self::TEXT_SUFFIX;
        $fp = fopen($inputFilePath, 'r') or die("Can't open ngrams text file");
        while ( ($ngram = fgets($fp)) !== false) {
          $words = PhraseParser::
                  extractPhrasesOfLengthOffset(trim($ngram), 1, 0, $lang);
          if(strlen($words[0]) == 1) { // get rid of n grams like "a dog"
              continue;
          }
          $ngram_stemmed = implode(" ", $words);
          $ngrams->add(strtolower($ngram_stemmed));
        }
        fclose($fp);

        $ngrams->save();
    }

    /**
     * Generates a n word grams text file from input wikipedia xml file.
     * The input file can be a bz2 compressed or uncompressed.
     * The input XML file is parsed line by line and pattern for
     * n word gram is searched. If a n word gram is found it is added to the
     * array. After the complete file is parsed we remove the duplicate
     * n word grams and sort them. The resulting array is written to the
     * text file. The function returns the number of bigrams stored in
     * the text file.
     *
     * @param string $wiki_file compressed or uncompressed wikipedia
     *      XML file path to be used to extract bigrams.
     * @param string $lang Language to be used to create bigrams.
     * @param int $num_gram number of words in grams we are looking for
     * @param int $ngram_type where in Wiki Dump to extract grams from
     * @return number $num_ngrams_found count of bigrams in text file.
     */
    static function generateNWordGramsTextFile($wiki_file, $lang, 
        $num_gram = 2, $ngram_type = self::PAGE_COUNT_DUMPS, $max_terms = -1)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $wiki_file_path =
            WORK_DIRECTORY.self::FILTER_FOLDER.$wiki_file;
        if(strpos($wiki_file_path, "bz2") !== false) {
            $fr = bzopen($wiki_file_path, 'r') or
                die ("Can't open compressed file");
            $read = "bzread";
            $close = "bzclose";
        } else if (strpos($wiki_file_path, "gz") !== false) {
            $fr = gzopen($wiki_file_path, 'r') or
                die ("Can't open compressed file");
            $read = "gzread";
            $close = "gzclose";
        } else {
            $fr = fopen($wiki_file_path, 'r') or die("Can't open file");
            $read = "fread";
            $close = "fclose";
        }
        $ngrams_file_path
            = WORK_DIRECTORY.self::FILTER_FOLDER.$lang_prefix.
                "_{$num_gram}_".self::TEXT_SUFFIX;
        $ngrams = array();
        $input_buffer = "";
        $time = time();
        echo "Reading wiki file ...\n";
        $bytes = 0;
        $bytes_since_last_output = 0;
        $output_message_threshold = self::BLOCK_SIZE*self::BLOCK_SIZE;
        switch($ngram_type)
        {
            case self::TITLE:
                $pattern = '/<title>[a-z]+';
                $pattern_end = '<\/title>/';
                $replace_array = array('<title>','</title>');
            break;
            case self::REDIRECT:
                $pattern = '/#redirect\s\[\[[a-z]+';
                $pattern_end='\]\]/';
                $replace_array = array('#redirect [[',']]');
            break;
            case self::PAGE_COUNT_DUMPS:
                $pattern = '/^en\s[a-z]+';
                $pattern_end='/';
            break;
        }
        $repeat_pattern = "[\s|_][a-z0-9]+";
        if($num_gram != "all") {
            for($i = 1; $i < $num_gram; $i++) {
                $pattern .= $repeat_pattern;
            }
        } else {
            $pattern .= "($repeat_pattern)+";
        }
        $pattern .= $pattern_end;
        $replace_types = array(self::TITLE, self::REDIRECT);
$x =0;
        while (!feof($fr)) {
            $input_text = $read($fr, self::BLOCK_SIZE);
            $len = strlen($input_text);
            if($len == 0) break;
            $bytes += $len;
            $bytes_since_last_output += $len;
            if($bytes_since_last_output > $output_message_threshold) {
                echo "Have now read ".$bytes." many bytes. " .
                    "Peak memory so far ".memory_get_peak_usage().
                    " Elapsed time so far:".(time() - $time)."\n";
                $bytes_since_last_output = 0;
            }
            $input_buffer .= strtolower($input_text);
            $lines = explode("\n", $input_buffer);
            $input_buffer = array_pop($lines);
            foreach($lines as $line) {
                preg_match($pattern, $line, $matches);
                if(count($matches) > 0) {
                    if($ngram_type == self::PAGE_COUNT_DUMPS) {
                        $line_parts = explode(" ", $matches[0]);
                        if(isset($line_parts[1]) && isset($line_parts[2])) {
                            $ngram = mb_ereg_replace("_", " ", $line_parts[1]);
                            if(strpos($ngram, " ") > 1) {
                                $ngrams[$ngram] = $line_parts[2];

                            }
                        }
                    } else {
                        $ngram = mb_ereg_replace(
                            $replace_array, "", $matches[0]);
                        $ngram = mb_ereg_replace("_", " ", $ngram);
                        $ngrams[] = $ngram;
                    }
                }
            }
        }
        if($ngram_type == self::PAGE_COUNT_DUMPS) {
            arsort($ngrams);
            $ngrams = array_keys($ngrams);
        }
        $ngrams = array_unique($ngrams);
        $num_ngrams_found = count($ngrams);
        if($max_terms > 0 && $num_ngrams_found > $max_terms) {

            $ngrams = array_slice($ngrams, 0, $max_terms);
        }
        sort($ngrams);
        $num_ngrams_found = count($ngrams);
        $ngrams_string = implode("\n", $ngrams);
        file_put_contents($ngrams_file_path, $ngrams_string);
        $close($fr);
        return $num_ngrams_found;
    }

}
