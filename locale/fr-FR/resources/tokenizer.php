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
 * This class has a collection of methods for French locale specific
 * tokenization. In particular, it has a stemmer, a stop word remover (for
 * use mainly in word cloud creation). The stemmer is my stab at re-implementing 
 * the stemmer algorithm given at http://snowball.tartarus.org and was
 * inspired by http://snowball.tartarus.org/otherlangs/french_javascript.txt
 * Here given a word, its stem is that part of the word that
 * is common to all its inflected variants. For example,
 * tall is common to tall, taller, tallest. A stemmer takes
 * a word and tries to produce its stem.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage locale
 */
class FrTokenizer
{
    /**
     * French vowels
     * @var string
     */
    static $vowel = 'aeiouyàâëéèêïîôûù';
    /**
     * Words we don't want to be stemmed
     * @var array
     */
    static $no_stem_list = array("titanic");
    /**
     * Storage used in computing the stem
     * @var string
     */
    static $buffer;
    /**
     * $rv is approximately the string after the first vowel in the $word we
     * want to stem
     * @var string
     */
    static $rv;
    /**
     * Position in $word to stem of $rv
     * @var int
     */
    static $rv_index;
    /**
     * $r1 is the region after the first non-vowel following a vowel, or the end
     * of the word if there is no such non-vowel.
     * @var string
     */
    static $r1;
    /**
     * Position in $word to stem of $r1
     * @var int
     */
    static $r1_index;
    /**
     * $r2 is the region after the first non-vowel following a vowel in $r1, or 
     * the end of the word if there is no such non-vowel
     * @var string
     */
    static $r2;
    /**
     * Position in $word to stem of $r2
     * @var int
     */
    static $r2_index;
    /**
     * Stub function which could be used for a word segmenter.
     * Such a segmenter on input thisisabunchofwords would output
     * this is a bunch of words
     *
     * @param string $pre_segment  before segmentation
     * @return string should return string with words separated by space
     *     in this case does nothing
     */
    static function segment($pre_segment)
    {
        return $pre_segment;
    }
    /**
     * Removes the stop words from the page (used for Word Cloud generation)
     *
     * @param string $page the page to remove stop words from.
     * @return string $page with no stop words
     */
    static function stopwordsRemover($page)
    {
        $stop_words = array('alors', 'au', 'aucuns', 'aussi', 'autre', 'avant',
            'avec', 'avoir', 'bon', 'car', 'ce', 'cela', 'ces', 'ceux',
            'chaque', 'ci', 'comme', 'comment', 'dans', 'des', 'du', 'dedans',
            'dehors', 'depuis', 'deux', 'devrait', 'doit', 'donc', 'dos',
            'droite', 'début', 'elle', 'elles', 'en', 'encore', 'essai', 'est',
            'et', 'eu', 'fait', 'faites', 'fois', 'font', 'force', 'haut',
            'hors', 'ici', 'il', 'ils', 'je', 'juste', 'la', 'le', 'les','leur',
            'là', 'ma', 'maintenant', 'mais', 'mes', 'mine', 'moins', 'mon',
            'mot', 'même', 'ni', 'nommés', 'notre', 'nous', 'nouveaux', 'ou',
            'où', 'par', 'parce', 'parole', 'pas', 'personnes', 'peut', 'peu',
            'pièce', 'plupart', 'pour', 'pourquoi', 'quand', 'que', 'quel',
            'quelle', 'quelles', 'quels', 'qui', 'sa', 'sans', 'ses',
            'seulement', 'si', 'sien', 'son', 'sont', 'sous', 'soyez', 'sujet',
            'sur', 'ta', 'tandis', 'tellement', 'tels', 'tes', 'ton', 'tous',
            'tout', 'trop', 'très', 'tu','valeur', 'voie', 'voient', 'vont',
            'votre','vous','vu','ça','étaient', 'état', 'étions', 'été',
            'être');
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of a French word
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    static function stem($word)
    {
        if(in_array($word, self::$no_stem_list)) {
            return $word;
        }
        $before_process = mb_strtolower($word);
        self::$buffer = $before_process;
        self::computeNonVowels();
        self::computeNonVowelRegions();
        self::step1(); //suffix removal
        $word_step1 = self::$buffer;
        if(self::step2a($before_process) //verb suffixes beginning with i
            && $word_step1 == self::$buffer) { 
            self::step2b(); //other verb suffixes
        }
        if(mb_strtolower(self::$buffer) != $before_process) {
            self::step3();
        } else {
            self::step4();
        }
        self::step5(); //un-double
        self::step6(); //un-accent
        self::$buffer = mb_strtolower(self::$buffer);
        return self::$buffer;
    }
    /**
     * If a vowel shouldn't be treated as a volume it is capitalized by
     * this method. (Operations done on buffer.)
     */
    static function computeNonVowels()
    {
        $vowel = static::$vowel;
        self::$buffer = preg_replace("/qu/u", "qU", self::$buffer);
        self::$buffer = preg_replace("/([$vowel])u([$vowel])/u", '$1U$2',
            self::$buffer);
        self::$buffer = preg_replace("/([$vowel])i([$vowel])/u", '$1I$2',
            self::$buffer);
        self::$buffer = preg_replace("/([$vowel])y/u", '$1Y',
            self::$buffer);
        self::$buffer = preg_replace("/y([$vowel])/u", 'Y$1',
            self::$buffer);
    }
    /**
     * $r1 is the region after the first non-vowel following a vowel, or the end
     * of the word if there is no such non-vowel.
     * $r2 is the region after the first non-vowel following a vowel in $r1, or 
     * the end of the word if there is no such non-vowel
     */
    static function computeNonVowelRegions()
    {
        $word = self::$buffer;
        $vowel = static::$vowel;
        self::$rv = "";
        self::$rv_index = -1;
        if(preg_search('/^(par|col|tap)/', $word) != -1 ||
            preg_search("/^[$vowel]{2}/", $word) != -1) {
            self::$rv = substr($word, 3);
            self::$rv_index = 3;
        } else {
            self::$rv_index = preg_search("/[$vowel]/", $word, 1);
            if(self::$rv_index != -1){
                self::$rv_index += 1;
                self::$rv = substr($word, self::$rv_index);
            } else {
                self::$rv_index = strlen($word);
            }
        }
        preg_match("/[$vowel][^$vowel]/", $word, $matches,
            PREG_OFFSET_CAPTURE);
        self::$r1 = "";
        $len = strlen($word);
        self::$r1_index = isset($matches[0][1]) ? $matches[0][1] + 2 : $len;
        if(self::$r1_index != $len) {
            self::$r1 = substr($word, self::$r1_index);
        }
        if(self::$r1_index != $len) {
            preg_match("/[$vowel][^$vowel]/", self::$r1, $matches,
                PREG_OFFSET_CAPTURE);
            self::$r2_index = isset($matches[0][1]) ? $matches[0][1] + 2 : $len;
            if(self::$r2_index != $len) {
                self::$r2 = substr(self::$r1, self::$r2_index);
                self::$r2_index += self::$r1_index;
            }
        }
        if(self::$r1_index != $len && self::$r1_index < 3) {
            self::$r1_index = 3;
            self::$r1 = substr($word, 3);
        }
    }
    /**
     * Standard suffix removal
     */
    static function step1()
    {
        $word = self::$buffer;
        $vowel = static::$vowel;
        $rv_index = self::$rv_index;
        $r1_index = self::$r1_index;
        $r2_index = self::$r2_index;
        $a_index = array();
        $patterns = array('/(ance|iqUe|isme|able|iste|eux|'.
            'ances|iqUes|ismes|ables|istes)$/',
            '/(atrice|ateur|ation|atrices|ateurs|ations)$/',
            '/(logie|logies)$/', '/(usion|ution|usions|utions)$/',
            '/(ence|ences)$/', '/(ement|ements)$/', '/(ité|ités)$/',
            '/(if|ive|ifs|ives)$/', '/(eaux)$/', '/(aux)$/', '/(euse|euses)$/',
            "/[^$vowel](issement|issements)".'$/', '/(amment)$/',
            '/(emment)$/', "/[$vowel]".'(ment|ments)$/');
        $i = 1;
        foreach($patterns as $pattern) {
            $a_index[$i] = preg_search($pattern, $word);
            $i++;
        }
        if($a_index[1] != -1 && $a_index[1] >= $r2_index) {
            $word = substr($word, 0, $a_index[1]);
        } else if($a_index[2] != -1 && $a_index[2] >= $r2_index) {
            $word = substr($word, 0, $a_index[2]);
            $a2_index2 = preg_search('/(ic)$/', $word);
            if($a2_index2 != -1 && $a2_index2 >= $r2_index){
                $word = substr($word, 0, $a2_index2);
                    //if preceded by ic, delete if in R2,
            } else { //else replace by iqU
                $word = preg_replace('/(ic)$/u','iqU', $word);
            }
        } else if($a_index[3] != -1 && $a_index[3] >= $r2_index) {
            //replace with log if in R2
            $word = preg_replace('/(logie|logies)$/','log', $word);
        } else if($a_index[4] != -1 && $a_index[4] >= $r2_index){
            //replace with u if in R2
            $word = preg_replace('/(usion|ution|usions|utions)$/u','u', $word);
        } else if($a_index[5] != -1 && $a_index[5] >= $r2_index) {
            //replace with ent if in R2
            $word = preg_replace('/(ence|ences)$/u','ent', $word);
        } else if($a_index[12] != -1 && $a_index[12] >= $r1_index) {
            //+1- amendment to non-vowel
            $word = substr($word, 0, $a_index[12] + 1);
        } else if($a_index[6] != -1 && $a_index[6] >= $rv_index) {
            $word = substr($word, 0, $a_index[6]);
            if(preg_search('/(iv)$/', $word) >= $r2_index) {
                $word = preg_replace('/(iv)$/u', '', $word);
                if(preg_search('/(at)$/', $word) >= $r2_index){
                    $word = preg_replace('/(at)$/u', '', $word);
                }
            } else if(($a6_index2 = preg_search('/(eus)$/', $word)) != -1) {
                if($a6_index2 >= $r2_index){
                    $word = substr($word, 0, $a6_index2);
                } else if($a6_index2 >= $r1_index){
                    $word = substr($word, 0, $a6_index2) . "eux";
                }
            } else if(preg_search('/(abl|iqU)$/', $word) >= $r2_index) {
                //if preceded by abl or iqU, delete if in R2,
                $word = preg_replace('/(abl|iqU)$/u', '', $word);
            } else if(preg_search('/(ièr|Ièr)$/', $word) >= $rv_index) {
                //if preceded by abl or iqU, delete if in R2,
                $word = preg_replace('/(ièr|Ièr)$/u', 'i', $word);
            }
        } else if($a_index[7] != -1 && $a_index[7] >= $r2_index) {
            //delete if in R2
            $word = substr($word, 0, $a_index[7]);
            /*if preceded by abil, delete if in R2, else replace by abl,
              otherwise,
             */
            if(($a7_index2 = preg_search('/(abil)$/', $word)) != -1) {
                if($a7_index2 >= $r2_index){
                    $word = substr($word, 0, $a7_index2);
                } else {
                    $word = substr($word, 0, $a7_index2) . "abl";
                }
            } else if(($a7_index3 = preg_search('/(ic)$/', $word)) != -1) {
                if($a7_index3 >= $r2_index) {
                    //if preceded by ic, delete if in R2,
                    $word = substr($word, 0, $a7_index3);
                } else {
                    //else replace by iqU
                    $word = preg_replace('/(ic)$/u', 'iqU', $word);
                }
            } else if(preg_search('/(iv)$/', $word) != $r2_index){
                $word = preg_replace('/(iv)$/u', '', $word);
            }
        } else if($a_index[8] != -1 && $a_index[8] >= $r2_index) {
            $word = substr($word, 0, $a_index[8]);
            if(preg_search('/(at)$/', $word) >= $r2_index) {
                $word = preg_replace('/(at)$/', '', $word);
                if(preg_search('/(ic)$/', $word) >= $r2_index) {
                    $word = preg_replace('/(ic)$/u', '', $word);
                } else { 
                    $word = preg_replace('/(ic)$/u', 'iqU', $word);
                }
            }
        } else if($a_index[9] != -1) {
            $word = preg_replace('/(eaux)/u', 'eau', $word);
        } else if($a_index[10] >= $r1_index) {
            $word = preg_replace('/(aux)/u', 'al', $word);
        } else if($a_index[11] != -1 ){
            $a11_index2 = preg_search('/(euse|euses)$/u', $word);
            if($a11_index2 >= $r2_index){
                $word = substr($word, 0, $a11_index2);
            } else if($a11_index2 >= $r1_index){
                $word = substr($word, 0, $a11_index2) . "eux";
            }
        }  else if($a_index[13] != -1 && $a_index[13] >= $rv_index) {
            $word = preg_replace('/(amment)$/u', 'ant', $word);
        } else if($a_index[14] != -1 && $a_index[14] >= $rv_index) {
            $word = preg_replace('/(emment)$/u','ent', $word);
        } else if($a_index[15] != -1 && $a_index[15] >= $rv_index) {
            $word = substr($word, 0, $a_index[15] + 1);
        }
        self::$buffer = $word;
    }
    /**
     * Stem verb suffixes beginning i
     */
    static function step2a($ori_word)
    {
        $vowel = static::$vowel;
        if($ori_word == mb_strtolower(self::$buffer) ||
            preg_match('/(amment|emment|ment|ments)$/', $ori_word)) {
            $b1_regex = "/([^$vowel])".'(âmes|ât|âtes|i|ie|ies|ir|ira|irai|'.
                'iraIent|irais|irait|iras|irent|irez|iriez|irions|irons|'.
                'iront|is|issaIent|issais|issait|issant|issante|issantes|'.
                'issants|isse|issent|isses|issez|issiez|issions|issons|it)$/iu';
            if(preg_search($b1_regex, self::$buffer) >= self::$rv_index) {
                self::$buffer = preg_replace($b1_regex, '$1', self::$buffer);
            }
            return true;
        }
        return false;
    }
    /**
     * Stem other verb suffixes
     */
    static function step2b()
    {
        $word = self::$buffer;
        if (preg_search('/(ions)$/', $word) >= self::$r2_index) {
            $word = preg_replace('/(ions)$/u', '', $word);
        } else {
            $b2_regex = '/(é|ée|ées|és|èrent|er|era|erai|eraIent|erais|erait|'.
                'eras|erez|eriez|erions|erons|eront|ez|iez)$/iu';
            if (preg_search($b2_regex, $word) >= self::$rv_index) {
                $word = preg_replace($b2_regex, '', $word);
            } else {
                $b3_regex = '/e(âmes|ât|âtes|a|ai|aIent|ais|ait|ant|ante|antes'.
                    '|ants|as|asse|assent|asses|assiez|assions)$/iu';
                if (preg_search($b3_regex, $word) >= self::$rv_index) {
                    $word = preg_replace($b3_regex, '', $word);
                } else {
                    $b3_regex2 = '/(âmes|ât|âtes|a|ai|aIent|ais|ait|ant|ante|'.
                        'antes|ants|as|asse|assent|asses|assiez|assions)$/iu';
                    if (preg_search($b3_regex2, $word) >= self::$rv_index) {
                        $word = preg_replace($b3_regex2, '', $word);
                    }
                }
            }
        }
        self::$buffer = $word;
    }
    /**
     * Gets rid of cedille's (make c's) and words ending with Y (make i)
     */
    static function step3()
    {
        $word = self::$buffer;
        $word = preg_replace('/Y$/', 'i', $word);
        $word = preg_replace('/ç/', 'c', $word);
        self::$buffer = $word;
    }
    /**
     * If the word ends in an s, not preceded by a, i, o, u, è or s, delete it.
     */
    static function step4()
    {
        $word = self::$buffer;
        if (preg_search('/([^aiouès])s$/', $word) >= self::$rv_index) {
            $word = preg_replace('/([^aiouès])s$/u', '$1', $word);
        }
        $e1_index = preg_search('/ion$/', $word);
        if ($e1_index >= self::$r2_index &&
            preg_search('/[st]ion$/', $word) >= self::$rv_index) {
            $word = substr($word, 0, $e1_index);
        } else {
            $e2_index = preg_search('/(ier|ière|Ier|Ière)$/', $word);
            if($e2_index != -1 && $e2_index >= self::$rv_index) {
                $word = substr($word, 0, $e2_index)."i";
            } else {
                if(preg_search('/e$/', $word) >= self::$rv_index) {
                    $word = preg_replace('/e$/u', '', $word);   //delete last e
                } else if(preg_search('/guë$/', $word) >= self::$rv_index) {
                    $word = preg_replace('/guë$/u', 'gu');
                }
            }
        }
        self::$buffer = $word;
    }
    /**
     * Un-double letter end
     */
    static function step5()
    {
        self::$buffer = preg_replace("/(en|on)(n)$/u", '$1',
            self::$buffer);
        self::$buffer = preg_replace("/(ett)$/u", 'et',
            self::$buffer);
        self::$buffer = preg_replace("/(el|eil)(l)$/u", '$1',
            self::$buffer);
    }
    /**
     * Un-accent end
     */
    static function step6()
    {
        $vowel = static::$vowel;
        self::$buffer = preg_replace("/[éè]([^$vowel]+)$/u", 'e$1',
            self::$buffer);
        self:$buffer = mb_strtolower(self::$buffer);
    }
}
?>
