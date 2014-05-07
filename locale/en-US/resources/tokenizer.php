<?php
/**
 *  SeekQuarry/Yioop --
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
 *  @author Chris Pollett chris@pollett.org
 *  @package seek_quarry
 *  @subpackage locale
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2014
 *  @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * My stab at implementing the Porter Stemmer algorithm
 * presented http://tartarus.org/~martin/PorterStemmer/def.txt
 * The code is based on the non-thread safe C version given by Martin Porter.
 * Since PHP is single-threaded this should be okay.
 * Here given a word, its stem is that part of the word that
 * is common to all its inflected variants. For example,
 * tall is common to tall, taller, tallest. A stemmer takes
 * a word and tries to produce its stem.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage locale
 */

class EnTokenizer
{

    /**
     * Words we don't want to be stemmed
     * @var array
     */
    static $no_stem_list = array("titanic", "programming", "fishing", 'ins',
        "blues", "factorial", "pbs");

    /**
     * Phrases we would like yioop to rewrite before performing a query
     * @var array
     */
    static $semantic_rewrites = array(
        "ins" => 'uscis',
        "mimetype" => 'mime',
        "military" => 'armed forces',
        'full metal alchemist' => 'fullmetal alchemist',
        'bruce schnier' => 'bruce schneier',
    );
    /**
     * storage used in computing the stem
     * @var string
     */
    static $buffer;
    /**
     * Index of the current end of the word at the current state of computing
     * its stem
     * @var int
     */
    static $k;
    /**
     * Index to start of the suffix of the word being considered for
     * manipulation
     * @var int
     */
    static $j;

    /**
     *
     */
    static function segment($pre_segment)
    {
        return $pre_segment;
    }
    /**
     * Removes the stop words from the page
     *
     * @param string $page the page to remove stop words from.
     * @return string $page with no stop words
     */
    static function stopwordsRemover($page)
    {
        $stop_words = array('a','able','about','above','abst',
        'accordance','according','based','accordingly','across','act',
        'actually','added','adj','affected','affecting','affects','after',
        'afterwards','again','against','ah','all','almost','alone','along',
        'already','also','although','always','am','among','amongst','an','and',
        'announce','another','any','anybody','anyhow','anymore','anyone',
        'anything','anyway','anyways','anywhere','apparently','approximately',
        'are','aren','arent','arise','around','as','aside','ask','asking','at',
        'auth','available','away','awfully','b','back','be','became','because',
        'become','becomes','becoming','been','before','beforehand','begin',
        'beginning','beginnings','begins','behind','being','believe','below',
        'beside','besides','between','beyond','biol','both','brief','briefly',
        'but','by','c','ca','came','can','cannot','cant','cause','causes',
        'certain','certainly','co','com','come','comes','contain','containing',
        'contains','could','couldnt','d','date','did','didnt',
        'different','do','does','doesnt','doing',
        'done','dont','down','downwards',
        'due','during','e','each','ed','edu','effect','eg','eight','eighty',
        'either','else','elsewhere','end',
        'ending','enough','especially','et',
        'et-al','etc','even','ever','every',
        'everybody','everyone','everything'
        ,'everywhere','ex','except','f','far','few','ff','fifth','first',
        'five','fix','followed','following','follows','for','former',
        'formerly','forth','found','four','from','further','furthermore',
        'g','gave','get','gets','getting','give','given','gives','giving','go',
        'goes','gone','got','gotten','h','had','happens','hardly','has','hasnt',
        'have','havent','having','he','hed','hence','her','here','hereafter',
        'hereby','herein','heres','hereupon','hers','herself','hes','hi','hid',
        'him','himself','his','hither','home','how','howbeit',
        'however','hundred','i','id','ie','if','ill',
        'im','immediate','immediately',
        'importance','important','in','inc','indeed','index','information',
        'instead','into','invention','inward','is','isnt','it','itd','itll',
        'its','itself','ive','j','just','k','keep','keeps',
        'kept','kg','km','know',
        'known','knows','l','largely','last','lately',
        'later','latter','latterly',
        'least','less','lest','let','lets','like','liked','likely','line',
        'little','ll','look','looking','looks','ltd','m','made','mainly','make',
        'makes','many','may','maybe','me','mean','means','meantime','meanwhile',
        'merely','mg','might','million','miss','ml','more','moreover','most',
        'mostly','mr','mrs','much','mug','must','my','myself','n','na','name',
        'namely','nay','nd','near','nearly','necessarily','necessary','need',
        'needs','neither','never','nevertheless','new','next',
        'nine','ninety','no',
        'nobody','non','none','nonetheless','noone',
        'nor','normally','nos','not',
        'noted','nothing','now','nowhere','o','obtain',
        'obtained','obviously','of',
        'off','often','oh','ok','okay','old','omitted','on','once','one','ones',
        'only','onto','or','ord','other','others',
        'otherwise','ought','our','ours',
        'ourselves','out','outside','over','overall','owing','own','p','page',
        'pages','part','particular','particularly',
        'past','per','perhaps','placed',
        'please','plus','poorly','possible','possibly','potentially','pp',
        'predominantly','present','previously',
        'primarily','probably','promptly',
        'proud','provides','put','q','que','quickly','quite','qv','r','ran',
        'rather','rd','re','readily','really','recent','recently','ref','refs',
        'regarding','regardless','regards','related','relatively','research',
        'respectively','resulted','resulting',
        'results','right','run','s','said',
        'same','saw','say','saying','says','sec',
        'section','see','seeing','seem',
        'seemed','seeming','seems',
        'seen','self','selves','sent','seven','several',
        'shall','she','shed','shell',
        'shes','should','shouldnt','show','showed','shown','showns','shows',
        'significant','significantly','similar','similarly','since',
        'six','slightly',
        'so','some','somebody','somehow','someone','somethan',
        'something','sometime',
        'sometimes','somewhat','somewhere','soon',
        'sorry','specifically','specified',
        'specify','specifying','still','stop','strongly','sub','substantially',
        'successfully','such','sufficiently','suggest','sup','sure','t','take',
        'taken','taking','tell','tends','th','than',
        'thank','thanks','thanx','that',
        'thatll','thats','thatve','the','their',
        'theirs','them','themselves','then',
        'thence','there','thereafter','thereby','thered','therefore','therein',
        'therell','thereof','therere','theres','thereto','thereupon','thereve',
        'these','they','theyd','theyll','theyre',
        'theyve','think','this','those',
        'thou','though','thoughh','thousand','throug',
        'through','throughout','thru',
        'thus','til','tip','to','together','too',
        'took','toward','towards','tried',
        'tries','truly','try','trying','ts','twice','two','u','un','under',
        'unfortunately','unless','unlike','unlikely','until','unto','up','upon',
        'ups','us','use','used','useful','usefully','usefulness','uses','using',
        'usually','v','value','various','ve','very',
        'via','viz','vol','vols','vs',
        'w','want','wants','was','wasnt','way','we',
        'wed','welcome','well','went',
        'were','werent','weve','what','whatever',
        'whatll','whats','when','whence',
        'whenever','where','whereafter','whereas','whereby','wherein','wheres',
        'whereupon','wherever','whether','which','while','whim','whither','who',
        'whod','whoever','whole','wholl','whom','whomever','whos','whose','why',
        'widely','willing','wish','with','within',
        'without','wont','words','world',
        'would','wouldnt','www','x','y','yes','yet','you','youd','youll','your',
        'youre','yours','yourself','yourselves','youve','z','zero');

        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of an English word
     *
     * For example, jumps, jumping, jumpy, all have jump as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    static function stem($word)
    {
        if(in_array($word, self::$no_stem_list)) {
            return $word;
        }

        self::$buffer = $word;

        self::$k = strlen($word) - 1;
        self::$j = self::$k;
        if(self::$k <= 1) return $word;

        self::step1ab();
        self::step1c();
        self::step2();
        self::step3();
        self::step4();
        self::step5();

        return substr(self::$buffer, 0, self::$k + 1);
    }

    /**
     * Checks to see if the ith character in the buffer is a consonant
     *
     * @param int $i the character to check
     * @return if the ith character is a constant
     */
    private static function cons($i)
    {
        switch (self::$buffer[$i])
        {
            case 'a': case 'e': case 'i': case 'o': case 'u':
                return false;
            case 'y':
                return ($i== 0 ) ? true : !self::cons($i - 1);
            default:
                return true;
        }
    }

    /**
     * m() measures the number of consonant sequences between 0 and j. if c is
     * a consonant sequence and v a vowel sequence, and [.] indicates arbitrary
     * presence,
     *  <pre>
     *    [c][v]       gives 0
     *    [c]vc[v]     gives 1
     *    [c]vcvc[v]   gives 2
     *    [c]vcvcvc[v] gives 3
     *    ....
     *  </pre>
     */
    private static function m()
    {
        $n = 0;
        $i = 0;
        while(true) {
            if ($i > self::$j) return $n;
            if (!self::cons($i)) break;
            $i++;
        }

        $i++;


        while(true) {
            while(true) {
                if ($i > self::$j) return $n;
                if (self::cons($i)) break;
                $i++;
            }
            $i++;
            $n++;

            while(true)
            {
                if ($i > self::$j) return $n;
                if (!self::cons($i)) break;
                $i++;
            }
            $i++;
        }
    }

    /**
     * Checks if 0,...$j contains a vowel
     *
     * @return bool whether it does not
     */

    private static function vowelinstem()
    {
        for ($i = 0; $i <= self::$j; $i++) {
            if (!self::cons($i)) return true;
        }
        return false;
    }

    /**
     * Checks if $j,($j-1) contain a double consonant.
     *
     * @return bool if it does or not
     */

    private static function doublec($j)
    {
        if ($j < 1) return false;
        if (self::$buffer[$j] != self::$buffer[$j - 1]) return false;
        return self::cons($j);
    }

    /**
     * Checks whether the letters at the indices $i-2, $i-1, $i in the buffer
     * have the form consonant - vowel - consonant and also if the second c is
     * not w,x or y. this is used when trying to restore an e at the end of a
     * short word. e.g.
     *<pre>
     *    cav(e), lov(e), hop(e), crim(e), but
     *    snow, box, tray.
     *</pre>
     * @return bool whether the letters at indices have the given form
     */

    private static function cvc($i)
    {
        if ($i < 2 || !self::cons($i) || self::cons($i - 1) ||
            !self::cons($i - 2)) return false;

        $ch = self::$buffer[$i];
        if ($ch == 'w' || $ch == 'x' || $ch == 'y') return false;

        return true;
    }

    /**
     * Checks if the buffer currently ends with the string $s
     *
     * @param string $s string to use for check
     * @return bool whether buffer currently ends with $s
     */

    private static function ends($s)
    {
        $len = strlen($s);
        $loc = self::$k - $len + 1;

        if($loc < 0 ||
            substr_compare(self::$buffer, $s, $loc, $len) != 0) return false;

        self::$j = self::$k - $len;

        return true;
    }

    /**
     * setto($s) sets (j+1),...k to the characters in the string $s, readjusting
     * k.
     *
     * @param string $s string to modify the end of buffer with
     */

    private static function setto($s)
    {
        $len = strlen($s);
        $loc = self::$j + 1;
        self::$buffer = substr_replace(self::$buffer, $s, $loc, $len);
        self::$k = self::$j + $len;
    }

    /**
     * Sets the ending in the buffer to $s if the number of consonant sequences
     * between $k and $j is positive.
     *
     * @param string $s what to change the suffix to
     */
    private static function r($s)
    {
        if (self::m() > 0) self::setto($s);
    }

    /** step1ab() gets rid of plurals and -ed or -ing. e.g.
     * <pre>
     *     caresses  ->  caress
     *     ponies    ->  poni
     *     ties      ->  ti
     *     caress    ->  caress
     *     cats      ->  cat
     *
     *     feed      ->  feed
     *     agreed    ->  agree
     *     disabled  ->  disable
     *
     *     matting   ->  mat
     *     mating    ->  mate
     *     meeting   ->  meet
     *     milling   ->  mill
     *     messing   ->  mess
     *
     *     meetings  ->  meet
     * </pre>
     */

    private static function step1ab()
    {
        if (self::$buffer[self::$k] == 's') {
            if (self::ends("sses")) {
                self::$k -= 2;
            } else if (self::ends("ies")) {
                self::setto("i");
            } else if (self::$buffer[self::$k - 1] != 's') {
                self::$k--;
            }
        }
        if (self::ends("eed")) {
            if (self::m() > 0) self::$k--;
        } else if ((self::ends("ed") || self::ends("ing")) &&
            self::vowelinstem()) {
            self::$k = self::$j;
            if (self::ends("at")) {
                self::setto("ate");
            } else if (self::ends("bl")) {
                self::setto("ble");
            } else if (self::ends("iz")) {
                self::setto("ize");
            } else if (self::doublec(self::$k)) {
                self::$k--;
                $ch = self::$buffer[self::$k];
                if ($ch == 'l' || $ch == 's' || $ch == 'z') self::$k++;
            } else if (self::m() == 1 && self::cvc(self::$k)) {
                self::setto("e");
            }
       }
    }

    /**
     * step1c() turns terminal y to i when there is another vowel in the stem.
     */

    private static function step1c()
    {
        if (self::ends("y") && self::vowelinstem()) {
            self::$buffer[self::$k] = 'i';
        }
    }


    /**
     * step2() maps double suffices to single ones. so -ization ( = -ize plus
     * -ation) maps to -ize etc.Note that the string before the suffix must give
     * m() > 0.
     */
    private static function step2()
    {
        if(self::$k < 1) return;
        switch (self::$buffer[self::$k - 1])
        {
            case 'a':
                if (self::ends("ational")) { self::r("ate"); break; }
                if (self::ends("tional")) { self::r("tion"); break; }
                break;
            case 'c':
                if (self::ends("enci")) { self::r("ence"); break; }
                if (self::ends("anci")) { self::r("ance"); break; }
                break;
            case 'e':
                if (self::ends("izer")) { self::r("ize"); break; }
                break;
            case 'l':
                if (self::ends("bli")) { self::r("ble"); break; }
                if (self::ends("alli")) { self::r("al"); break; }
                if (self::ends("entli")) { self::r("ent"); break; }
                if (self::ends("eli")) { self::r("e"); break; }
                if (self::ends("ousli")) { self::r("ous"); break; }
                break;
            case 'o':
                if (self::ends("ization")) { self::r("ize"); break; }
                if (self::ends("ation")) { self::r("ate"); break; }
                if (self::ends("ator")) { self::r("ate"); break; }
                break;
            case 's':
                if (self::ends("alism")) { self::r("al"); break; }
                if (self::ends("iveness")) { self::r("ive"); break; }
                if (self::ends("fulness")) { self::r("ful"); break; }
                if (self::ends("ousness")) { self::r("ous"); break; }
                break;
            case 't':
                if (self::ends("aliti")) { self::r("al"); break; }
                if (self::ends("iviti")) { self::r("ive"); break; }
                if (self::ends("biliti")) { self::r("ble"); break; }
                break;
            case 'g':
                if (self::ends("logi")) { self::r("log"); break; }

        }
    }

    /**
     * step3() deals with -ic-, -full, -ness etc. similar strategy to step2.
     */

    private static function step3()
    {
        switch (self::$buffer[self::$k])
        {
            case 'e':
                if (self::ends("icate")) { self::r("ic"); break; }
                if (self::ends("ative")) { self::r(""); break; }
                if (self::ends("alize")) { self::r("al"); break; }
                break;
            case 'i':
                if (self::ends("iciti")) { self::r("ic"); break; }
                break;
            case 'l':
                if (self::ends("ical")) { self::r("ic"); break; }
                if (self::ends("ful")) { self::r(""); break; }
                break;
            case 's':
                if (self::ends("ness")) { self::r(""); break; }
                break;
        }
    }

    /**
     * step4() takes off -ant, -ence etc., in context <c>vcvc<v>.
     */
    private static function step4()
    {
        if(self::$k < 1) return;
        switch (self::$buffer[self::$k - 1])
        {
            case 'a':
                if (self::ends("al")) break;
                return;
            case 'c':
                if (self::ends("ance")) break;
                if (self::ends("ence")) break;
                return;
            case 'e':
                if (self::ends("er")) break;
                return;
            case 'i':
                if (self::ends("ic")) break;
                return;
            case 'l':
                if (self::ends("able")) break;
                if (self::ends("ible")) break;
                return;
            case 'n':
                if (self::ends("ant")) break;
                if (self::ends("ement")) break;
                if (self::ends("ment")) break;
                if (self::ends("ent")) break;
                return;
            case 'o':
                if (self::ends("ion") && self::$j >= 0 &&
                    (self::$buffer[self::$j] == 's' ||
                    self::$buffer[self::$j] == 't')) break;
                if (self::ends("ou")) break;
                return;
            /* takes care of -ous */
            case 's':
                if (self::ends("ism")) break;
                return;
            case 't':
                if (self::ends("ate")) break;
                if (self::ends("iti")) break;
                    return;
            case 'u':
                if (self::ends("ous")) break;
                return;
            case 'v':
                if (self::ends("ive")) break;
                return;
            case 'z':
                if (self::ends("ize")) break;
                return;
            default:
                return;
        }
        if (self::m() > 1) self::$k = self::$j;
    }

    /** step5() removes a final -e if m() > 1, and changes -ll to -l if
     *  m() > 1.
     */

    private static function step5()
    {
        self::$j = self::$k;

        if (self::$buffer[self::$k] == 'e') {
            $a = self::m();
            if ($a > 1 || $a == 1 && !self::cvc(self::$k - 1)) self::$k--;
        }
        if (self::$buffer[self::$k] == 'l' &&
            self::doublec(self::$k) && self::m() > 1) self::$k--;
    }


}
?>