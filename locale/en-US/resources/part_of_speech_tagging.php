<?php
/**
 * SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @author Shailesh Padave based on Ian Barber's Code
 * @package seek_quarry
 * @subpackage locale
 * @license http://www.gnu.org/licenses/ GPLv3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource http://phpir.com/part-of-speech-tagging
 */
/**
 * This class is used to get part of speech tagging for given input.
 * @package seek_quarry
 * @subpackage locale
 */
class PartOfSpeechTagger
{
    private $dictionary;
    public function __construct($lexicon)
    {
        $fh = fopen($lexicon, 'r');
        while($line = fgets($fh)) {
                $tags = explode(' ', $line);
                $this->dictionary[strtolower(array_shift($tags))] = $tags;
        }
        fclose($fh);
    }
    public function tag($text)
    {
        preg_match_all("/[\w\d]+/", $text, $matches);
        $nouns = array('NN', 'NNS');
        $result = array();
        $i = 0;
        foreach($matches[0] as $token) {
            // default to a common noun
            $result[$i] = array('token' => $token, 'tag' => 'NN');
            // remove trailing full stops
            if(substr($token, -1) == '.') {
                    $token = preg_replace('/\.+$/', '', $token);
            }
            // get from dictionary if set
            if(isset($this->dictionary[strtolower($token)])) {
                    $result[$i]['tag'] =
                        $this->dictionary[strtolower($token)][0];
            }
            // Converts verbs after 'the' to nouns
            if($i > 0) {
                    if($result[$i - 1]['tag'] == 'DT' &&
                             in_array($result[$i]['tag'],
                                            array('VBD', 'VBP', 'VB'))) {
                            $result[$i]['tag'] = 'NN';
                    }
            }
            // Convert noun to number if . appears
            if($result[$i]['tag'][0] == 'N' && strpos($token, '.') !== false) {
                    $result[$i]['tag'] = 'CD';
            }
            // Convert noun to past particle if ends with 'ed'
            if($result[$i]['tag'][0] == 'N' && substr($token, -2) == 'ed') {
                    $result[$i]['tag'] = 'VBN';
            }
            // Anything that ends 'ly' is an adverb
            if(substr($token, -2) == 'ly') {
                    $result[$i]['tag'] = 'RB';
            }
            // Common noun to adjective if it ends with al
            if(in_array($result[$i]['tag'], $nouns)
                                    && substr($token, -2) == 'al') {
                    $result[$i]['tag'] = 'JJ';
            }
            // Noun to verb if the word before is 'would'
            if($i > 0) {
                    if($result[$i]['tag'] == 'NN'
                            && strtolower($result[$i-1]['token']) == 'would') {
                            $result[$i]['tag'] = 'VB';
                    }
            }
            // Convert noun to plural if it ends with an s
            if($result[$i]['tag'] == 'NN' && substr($token, -1) == 's') {
                    $result[$i]['tag'] = 'NNS';
            }
            // Convert common noun to gerund
            if(in_array($result[$i]['tag'], $nouns)
                            && substr($token, -3) == 'ing') {
                    $result[$i]['tag'] = 'VBG';
            }
            /* If we get noun noun, and the second can be a verb,
             * convert to verb
             */
            if($i > 0) {
                if(in_array($result[$i]['tag'], $nouns)
                        && in_array($result[$i-1]['tag'], $nouns)
                        && isset($this->dictionary[strtolower($token)])) {
                    if(in_array('VBN', $this->dictionary[strtolower($token)])) {
                            $result[$i]['tag'] = 'VBN';
                    } else if(in_array('VBZ',
                            $this->dictionary[strtolower($token)])) {
                            $result[$i]['tag'] = 'VBZ';
                    }
                }
            }
            $i++;
        }
        return $result;
    }
    /* Function for print the results
     * @param $tags input string on which Part of speech processing is performed
     * @return $output_string with customized part of speech for query input
     */
    static function printTag($tags)
    {
        $output_string = null;
        $noun_arr = array("NN","NNS","NNP","NNPS","PRP","PRP$","WP","WP");
        $verb_arr = array("VB","VBD","VBG","VBN","VBP","VBZ");
        $adj_arr = array("JJ","JJR","JJS");
        $adv_arr = array("RB","RBR","RBS","WRB");
        foreach($tags as $t) {
            if(in_array(trim($t['tag']),$noun_arr)==1) {
                $output_string =
                    $output_string . $t['token'] . "~" . "NN" .  " ";
            } else if(in_array(trim($t['tag']),$verb_arr)==1) {
                $output_string =
                    $output_string . $t['token'] . "~" . "VB" .  " ";
            } else if(in_array(trim($t['tag']),$adj_arr)==1) {
                $output_string =
                    $output_string . $t['token'] . "~" . "AJ" .  " ";
            } else if(in_array(trim($t['tag']),$adv_arr)==1) {
                $output_string =
                    $output_string . $t['token'] . "~" . "AV" .  " ";
            } else {
                $output_string =
                $output_string . $t['token'] . "~" . $t['tag'] .  " ";
            }
        }
        return $output_string;
    }
    /* Function for part of speech for given query
     * @param $string input string from user
     * @return $output_string with part of speech processed on query input
     */
    static function getPartOfSpeechTagging($string)
    {
        $lang = guessLocaleFromString($string);
        // Initialize the directory named as lexicon.txt
        $tagger = new PartOfSpeechTagger(LOCALE_DIR . "/".
                $lang ."/resources/lexicon.txt");
        $tags = $tagger->tag($string);
        $output_string  = self::printTag($tags);
        return $output_string;
    }
}
?>