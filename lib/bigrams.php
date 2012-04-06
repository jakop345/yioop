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

     /**
      * @constant Name of the folder inside user work directory
      * that contains the input compressed XML file. The filter
      * file generated will also be stored in this folder.
      */
     const FILTER_FOLDER = "/search_filters/";
     /**
      * @constant Suffix appended to langauge tag to create the
      * filter file name containing bigrams.
      */
     const FILTER_SUFFIX = "_bigrams.ftr";
     /**
      * @constant Suffix appended to langauge tag to create the
      * text file name containing bigrams.
      */
     const TEXT_SUFFIX = "_bigrams.txt";

    /**
     * Extracts Bigrams from input set of phrases. If a filter file
     * is not available for $lang we just return the input phrases.
     * Each pair of phrases is searched in filter file to check if
     * it is a bigram. If a pair passes the bigram check we add it
     * to the array of phrases as a single phrase otherwise individual
     * phrases are added to the array. The resultant array of phrases
     * is returned at the end.
     *
     * @param array $phrases subject to bigram check
     * @param string $lang Language to be used to stem bigrams.
     * @return array of bigrams and phrases
     */
    static function extractBigrams($phrases, $lang)
    {
        static $bigrams = NULL;
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $filter_path = WORK_DIRECTORY .
            self::FILTER_FOLDER . $lang_prefix . self::FILTER_SUFFIX;
        if ($bigrams == NULL && file_exists($filter_path)) {
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
            } else {
                $bigram_phrases[] = $phrases[$i];
                $i++;
                $j++;
            }
        }
        if($j == $num_phrases) {
            $bigram_phrases[] = $phrases[$j - 1];
        }
        return $bigram_phrases;
    }

    /**
     * Creates a bloom filter file from a bigram text file. The
     * path of bigram text file used is based on the input $lang.
     * The name of output filter file is based on the $lang and
     * size based on input number of bigrams .
     * The bigrams are read from text file, stemmed if a stemmer
     * is available for $lang and then stored in filter file.
     *
     * @param string $lang language to be used to stem bigrams.
     * @param number $num_bigrams Count of bigrams in text file.
     * @return none
     */
    static function createBigramFilterFile($lang, $num_bigrams)
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
            $bigrams = new BloomFilterFile($filter_path, $num_bigrams);
        }

        $inputFilePath = WORK_DIRECTORY . self::FILTER_FOLDER .
            $lang_prefix . self::TEXT_SUFFIX;
        $fp = fopen($inputFilePath, 'r') or die("Can't open bigrams text file");
        while ( ($bigram = fgets($fp)) !== false) {
          $words = PhraseParser::
                  extractPhrasesOfLengthOffset(trim($bigram), 1, 0, $lang);
          if(strlen($words[0]) == 1) { // get rid of bigrams like "a dog"
              continue;
          }
          $bigram_stemmed = implode(" ", $words);
          $bigrams->add(strtolower($bigram_stemmed));
        }
        fclose($fp);

        $bigrams->save();
    }

    /**
     * Generates a bigrams text file from input wikipedia xml file.
     * The input file can be a bz2 compressed or uncompressed, if
     * the file is compressed it is uncompressed by calling the function
     * uncompressBz2File($compressed_wiki_file_path).
     * The input XML file is parsed line by line and pattern for
     * bigram is searched. If a bigram is found it is added to the
     * array. After the complete file is parsed we remove the duplicate
     * bigrams and sort them. The resulting array is written to the
     * text file. The function returns the number of bigrams stored in
     * the text file.
     *
     * @param string $wiki_file compressed or uncompressed wikipedia
     *      XML file path to be used to extract bigrams.
     * @param string $lang Language to be used to create bigrams.
     * @return number $num_bigrams Count of bigrams in text file.
     */
    static function generateBigramsTextFile($wiki_file, $lang)
    {
        $lang_prefix = $lang;
        if(isset(self::$LANG_PREFIX[$lang])) {
            $lang_prefix = self::$LANG_PREFIX[$lang];
        }
        $compressed_wiki_file_path =
            WORK_DIRECTORY.self::FILTER_FOLDER.$wiki_file;
        $found = strpos($compressed_wiki_file_path, "bz2");
        if($found == false){
            $wiki_file_path = $compressed_wiki_file_path;
        }
        else{
            $wiki_file_path =
                self::uncompressBz2File($compressed_wiki_file_path);
        }
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
        $num_bigrams = count($bigrams);
        sort($bigrams);
        $bigrams_string = implode("\n", $bigrams);
        fwrite($fw, $bigrams_string);
        fclose($fr);
        fclose($fw);
        return $num_bigrams;
    }

    /**
     * Uncompress the compressed Bz2 xml file specified by input
     * parameter $compressed_wiki_file_path. The $buffer_size
     * variable specifies the size of block which is read in one
     * iteration from the compressed file. The uncompressed xml
     * file is stored in the same directory as the compressed file.
     * The name of this file is generated by removing ".bz2" from
     * the end of compressed file name. The name of uncompressed
     * file is returned by the function.
     *
     * @param string $compressed_wiki_file_path bz2 compressed
     *     wikipedia XML file path.
     * @return string $wiki_file_path Uncompressed xml file path.
     */
    static function uncompressBz2File($compressed_wiki_file_path)
    {
        $wiki_file_path = str_replace('.bz2', '', $compressed_wiki_file_path);
        $bz = bzopen($compressed_wiki_file_path, 'r');
        $out_file = fopen($wiki_file_path, 'w');
        $buffer_size = 8092;

        do {
            $block = bzread($bz, $buffer_size);
            if($block!==false)
                fwrite($out_file, $block);
        }
        while($block);

        fclose($out_file);
        bzclose($bz);
        return $wiki_file_path;
    }
}
