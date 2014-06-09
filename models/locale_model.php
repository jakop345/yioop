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
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Loads base model class if necessary */
require_once BASE_DIR."/models/model.php";
/** Loads code to be able to make canonical urls */
require_once BASE_DIR."/lib/url_parser.php";
/**
 * Function for comparing two locale arrays by locale tag so can sort
 *
 * @param array $a an associative array of locale info
 * @param array $b an associative array of locale info
 *
 * @return int -1, 0, or 1 depending on which is alphabetically smaller or if
 *      they are the same size
 */
function lessThanLocale($a, $b) {
    if ($a["LOCALE_TAG"] == $b["LOCALE_TAG"]) {
        return 0;
    }
    return ($a["LOCALE_TAG"] < $b["LOCALE_TAG"]) ? -1 : 1;
}
/**
 * Used to encapsulate information about a locale (data about a language in
 * a given region).
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class LocaleModel extends Model
{
    /**
     * Used to store ini file data of the current locale
     * @var array
     */
    var $configure = array();
    /**
     * Used to store ini file data of the default locale (will use if no
     * translation current locale)
     * @var array
     */
    var $default_configure = array();
    /**
     * IANA tag name of current locale
     * @var string
     */
    var $locale_tag;
    /**
     * Locale name as a string it locale name's language
     * @var string
     */
    var $locale_name;
    /**
     * Combination of text direction and block progression as a string. Has one
     * of four values: lr-tb, rl-tb, tb-lr, tb-rl. Other possible values for
     * things like Arabic block quoted in Mongolian not supported
     * @var string
     */
    var $writing_mode;
    /**
     * Directories to try to extract translatable identifier strings from
     * @var array
     */
    var $extract_dirs = array("controllers", "views", "lib/indexing_plugins");
    /**
     * File extensions of files to try to extract translatable strings from
     * @var array
     */
    var $extensions = array("php");
    /**
     *  Associations of the form
     *      name of field for web forms => database column names/abbreviations
     *  In this case, things will in general map to the LOCALES tables in the 
     *  Yioop database
     *  @var array
     */
    var $search_table_column_map = array("name"=>"LOCALE_NAME",
        "tag"=>"LOCALE_TAG", "mode" => "WRITING_MODE");
    /**
     *  These fields if present in $search_array (used by @see getRows() ),
     *  but with value "0", will be skipped as part of the where clause
     *  but will be used for order by clause
     * @var array
     */
    var $any_fields = array("mode");
    /** {@inheritDoc} */
    function selectCallback($args = NULL)
    {
        return "LOCALE_ID, LOCALE_TAG, LOCALE_NAME, WRITING_MODE";
    }
    /**
     *  This is called after each row is retrieved by getRows. This method
     *  Then reads in the corresponding statistics.txt file (or rebuilds
     *  it from the configure.ini file if it is out of date) to add to the
     *  row percent translated info.
     *
     *  @param string $locale one getRows row corresponding to a given locale
     *  @param mixed $args additional arguments that might be used for this
     *      method (none used for this sub-class)
     *  @return $locale row with PERCENT_WITH_STRINGS field added
     */
    function rowCallback($locale, $args)
    {
        /*
            the statistics text file contains info used to calculate
            what fraction of strings have been translated
         */
        $tag_prefix = LOCALE_DIR."/".$locale['LOCALE_TAG'];
        if(!file_exists($tag_prefix)) {
            mkdir($tag_prefix); //create locale_dirs that are missing
            $this->db->setWorldPermissionsRecursive($tag_prefix);
        }
        if(!file_exists("$tag_prefix/statistics.txt") ||
            filemtime("$tag_prefix/statistics.txt") <
            filemtime("$tag_prefix/configure.ini")) {
            $tmp = parse_ini_with_fallback(
                "$tag_prefix/configure.ini");
            $num_ids = 0;
            $num_strings = 0;
            foreach ($tmp['strings'] as $msg_id => $msg_string) {
                $num_ids++;
                if(strlen($msg_string) > 0) {
                    $num_strings++;
                }
            }
            $locale['PERCENT_WITH_STRINGS'] =
                floor(100 * $num_strings/$num_ids);
            file_put_contents("$tag_prefix/statistics.txt",
                serialize($locale['PERCENT_WITH_STRINGS']));
        } else {
            $locale['PERCENT_WITH_STRINGS'] =
                unserialize(
                    file_get_contents("$tag_prefix/statistics.txt"));
        }
        return $locale;
    }
    /**
     * Loads the provided locale's configure file (containing transalation) and
     * calls setlocale to set up locale specific string formatting
     * (for to format numbers, etc.)
     *
     * @param string $locale_tag  the tag of the locale to use as the current
     *      locale
     */
    function initialize($locale_tag)
    {
        $this->configure = parse_ini_with_fallback(
            LOCALE_DIR."/$locale_tag/configure.ini");
        if($locale_tag != DEFAULT_LOCALE) {
            $this->default_configure = parse_ini_with_fallback(
                LOCALE_DIR."/".DEFAULT_LOCALE."/configure.ini");
        }
        $this->locale_tag = $locale_tag;
        $sql = "SELECT LOCALE_NAME, WRITING_MODE ".
            " FROM LOCALE WHERE LOCALE_TAG ='$locale_tag'";
        $result = $this->db->execute($sql);
        $row = false;
        if($result) {
            $row = $this->db->fetchArray($result);
        }
        $this->locale_name = $row['LOCALE_NAME'];
        $this->writing_mode = $row['WRITING_MODE'];
        $locale_tag_parts = explode("_", $locale_tag);
        setlocale(LC_ALL, $locale_tag, $locale_tag.'.UTF-8',
            $locale_tag.'.UTF8',  $locale_tag.".TCVN", $locale_tag.".VISCII",
            $locale_tag_parts[0], $locale_tag_parts[0].'.UTF-8',
            $locale_tag_parts[0].'.UTF8', $locale_tag_parts[0].".TCVN");
        //hacks for things that didn't work from the above
        if($locale_tag == 'vi_VN') {
            setlocale(LC_NUMERIC, 'fr_FR.UTF-8');        }

    }
    /**
     *  Returns information about all available locales
     *
     *  @return array rows of locale information
     */
    function getLocaleList()
    {
        $db = $this->db;
        $sql = "SELECT LOCALE_ID, LOCALE_TAG, LOCALE_NAME, WRITING_MODE ".
            " FROM LOCALE";
        $result = $db->execute($sql);
        $i = 0;
        $locales = array();
        while($locales[$i] = $db->fetchArray($result)) {
            /*
                the statistics text file contains info used to calculate
                what fraction of strings have been translated
             */
            $tag_prefix = LOCALE_DIR."/".$locales[$i]['LOCALE_TAG'];
            if(!file_exists($tag_prefix)) {
                mkdir($tag_prefix); //create locale_dirs that are missing
                $this->db->setWorldPermissionsRecursive($tag_prefix);
            }
            if(!file_exists("$tag_prefix/statistics.txt") ||
                filemtime("$tag_prefix/statistics.txt") <
                filemtime("$tag_prefix/configure.ini")) {

                $tmp = parse_ini_with_fallback(
                    "$tag_prefix/configure.ini");
                $num_ids = 0;
                $num_strings = 0;
                foreach ($tmp['strings'] as $msg_id => $msg_string) {
                    $num_ids++;
                    if(strlen($msg_string) > 0) {
                        $num_strings++;
                    }
                }
                $locales[$i]['PERCENT_WITH_STRINGS'] =
                    floor(100 * $num_strings/$num_ids);
                file_put_contents("$tag_prefix/statistics.txt",
                    serialize($locales[$i]['PERCENT_WITH_STRINGS']));
            } else {
                $locales[$i]['PERCENT_WITH_STRINGS'] =
                    unserialize(
                        file_get_contents("$tag_prefix/statistics.txt"));
            }
            $i++;

        }
        unset($locales[$i]); //last one will be null
        usort($locales,"lessThanLocale");

        return $locales;
    }

    /**
     *   Check if there is a locale with tag equal to $locale_tag
     *
     *   @param string $locale_tag to check for
     *   @return bool whether or not has exists
     */
    function checkLocaleExists($locale_tag)
    {
        $db = $this->db;
        $params = array($locale_tag);
        $sql = "SELECT COUNT(*) AS NUM FROM LOCALE WHERE
            LOCALE_TAG=? ";
        $result = $db->execute($sql, $params);
        if(!$row = $db->fetchArray($result) ) {
            return false;
        }
        if($row['NUM'] <= 0) {
            return false;
        }
        return true;
    }
    /**
     *   Returns the locale name, tag, and writing mode for tag $locale_tag
     *
     *   @param string $locale_tag to get name for
     *   @return string name of locale
     */
    function getLocaleInfo($locale_tag)
    {
        $db = $this->db;
        $params = array($locale_tag);
        $sql = "SELECT * FROM LOCALE WHERE
            LOCALE_TAG=? ";
        $result = $db->execute($sql, $params);
        if(!$row = $db->fetchArray($result) ) {
            return "";
        }
        return $row;
    }
    /**
     *   Returns the name of the locale for tag $locale_tag
     *
     *   @param string $locale_tag to get name for
     *   @return string name of locale
     */
    function getLocaleName($locale_tag)
    {
        $db = $this->db;
        $params = array($locale_tag);
        $sql = "SELECT LOCALE_NAME FROM LOCALE WHERE
            LOCALE_TAG=? ";
        $result = $db->execute($sql, $params);
        if(!$row = $db->fetchArray($result) ) {
            return "";
        }
        return $row["LOCALE_NAME"];
    }
    /**
     * Adds information concerning a new locale to the database
     *
     * @param string $locale_name the name of the locale in the locale's
     *      language
     * @param string $locale_tag the IANA langauge tag for the locale
     * @param string $writing_mode  a combination of the horizontal and
     *      vertical text direction used for writing in the locale
     */
    function addLocale($locale_name, $locale_tag, $writing_mode)
    {
        $sql = "INSERT INTO LOCALE (LOCALE_NAME, LOCALE_TAG,
            WRITING_MODE) VALUES (?, ?, ?)";
        $this->db->execute($sql, array($locale_name, $locale_tag,
            $writing_mode));
        if(!file_exists(LOCALE_DIR."/$locale_tag")) {
            mkdir(LOCALE_DIR."/$locale_tag");
            $this->db->setWorldPermissionsRecursive(LOCALE_DIR."/$locale_tag");
        }
    }
    /**
     *  Remove a locale from the database
     *
     *  @param string $locale_tag the IANA language tag for the locale to remove
     */
    function deleteLocale($locale_tag)
    {
        $sql = "DELETE FROM LOCALE WHERE LOCALE_TAG = ?";
        $this->db->execute($sql, array($locale_tag));
        if(file_exists(LOCALE_DIR."/$locale_tag")) {
            $this->db->unlinkRecursive(LOCALE_DIR."/$locale_tag", true);
        }
    }
    /**
     *  Used to update the fields stored in a LOCALE row according to
     *  an array holding new values
     *
     *  @param array $locale_indo updated values for a LOCALE row
     */
    function updateLocaleInfo($locale_info)
    {
        $locale_id = $locale_info['LOCALE_ID'];
        unset($locale_info['LOCALE_ID']);
        unset($locale_info['LOCALE_NAME']);
        $sql = "UPDATE LOCALE SET ";
        $comma ="";
        $params = array();
        foreach($locale_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE LOCALE_ID=?";
        $params[] = $locale_id;
        $this->db->execute($sql, $params);
    }
    /**
     * For each translatable identifier string (either static from a
     * configure ini file, or dynamic from the db)
     * return its name together with its translation into the given locale
     * if such a translation exists.
     *
     *  @param string $locale_tag the IANA language tag to translate string into
     *  @return array  rows of identfier string - translation pairs
     */
    function getStringData($locale_tag)
    {
        $db = $this->db;
        $data = parse_ini_with_fallback(
            LOCALE_DIR."/$locale_tag/configure.ini");
        $data = $data['strings'];

        //hacky. Join syntax isn't quite the same between sqlite and mysql
        if(in_array(DBMS, array('sqlite3'))) {
            $sql = "SELECT T.IDENTIFIER_STRING AS MSG_ID, ".
                "TLL.TRANSLATION AS MSG_STRING " .
                "FROM TRANSLATION T LEFT JOIN ".
                //sqlite supports left but not right outer join
                "(TRANSLATION_LOCALE TL JOIN LOCALE L ON ".
                "L.LOCALE_TAG = ? AND ".
                "L.LOCALE_ID = TL.LOCALE_ID) TLL " .
                "ON T.TRANSLATION_ID = TLL.TRANSLATION_ID";
        } else {
            $sql = "SELECT T.IDENTIFIER_STRING AS MSG_ID, ".
                "TL.TRANSLATION AS MSG_STRING " .
                "FROM TRANSLATION T LEFT JOIN ".
                "(TRANSLATION_LOCALE TL JOIN LOCALE L ON ".
                "L.LOCALE_TAG = ? AND L.LOCALE_ID = TL.LOCALE_ID) ".
                "ON T.TRANSLATION_ID = TL.TRANSLATION_ID";
        }
        $result = $this->db->execute($sql, array($locale_tag));
        while($row = $this->db->fetchArray($result)) {
            $data[$row['MSG_ID']] = $row['MSG_STRING'];
        }
        return $data;
    }
    /**
     * Updates the identifier_string-translation pairs
     * (both static and dynamic) for a given locale
     *
     * @param string $locale_tag  the IANA language tag to update the strings of
     * @param array $new_strings  rows of identifier string - translation pairs
     */
    function updateStringData($locale_tag, $new_strings)
    {
        $db = $this->db;
        $sql = "SELECT LOCALE_ID FROM LOCALE WHERE LOCALE_TAG = ? " .
            $db->limitOffset(1);
        $result = $db->execute($sql, array($locale_tag));
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];

        list($general_ini, $strings) = $this->extractMergeLocales();
        $select_sql = "SELECT TRANSLATION_ID FROM TRANSLATION ".
            "WHERE IDENTIFIER_STRING = ? " . $db->limitOffset(1);
        $delete_sql = "DELETE FROM TRANSLATION_LOCALE ".
            "WHERE TRANSLATION_ID =? AND LOCALE_ID = ?";
        $insert_sql = "INSERT INTO TRANSLATION_LOCALE VALUES (?, ?, ?)";
        foreach($new_strings as $msg_id => $msg_string) {
            if(strcmp($msg_id, strstr($msg_id, "db_")) == 0) {
                $result = $db->execute($select_sql, array($msg_id));
                $row = $db->fetchArray($result);
                $translate_id = $row['TRANSLATION_ID'];
                $result = $db->execute($delete_sql, array($translate_id,
                    $locale_id));
                $result = $db->execute($insert_sql, array($translate_id,
                    $locale_id, $msg_string));
                $new_strings[$msg_id] = false;
            }
        }
        array_filter($new_strings);
        $data['strings'] = $new_strings;
        $this->updateLocale(
            $general_ini, $strings, LOCALE_DIR, $locale_tag, $data);
    }
    /**
     * Translate an array consisting of an identifier string together with
     *      additional variable parameters into the current locale.
     *
     * Suppose the identifier string was some_view_fraction_received and two
     * additional arguments 5 and 10 were given. Suppose further that its
     * translation into the current locale (say en_US) was "%s out of %s".
     * Then the string returned by translate would be "5 out of 10".
     *
     * @param array $arr an array consisting of an identifier string followed
     *      optionally by parameter values.
     * @return mixed the translation of the identifier string into the
     *      current locale where all %s have been replaced by the corresponding
     *      parameter values. Returns false if no translation
     */
    function translate($arr) {
        if(!is_array($arr)) {return; }
        $num_args = count($arr);
        if($num_args < 1) {return; }
        $msg_id = $arr[0];

        $args = array_slice($arr, 1);
        $msg_string = false;
        if(isset($this->configure['strings'][$msg_id])) {
            $msg_string = $this->configure['strings'][$msg_id];
        }
        if($msg_string == "" &&
            isset($this->default_configure['strings'][$msg_id])) {
            $msg_string = $this->default_configure['strings'][$msg_id];
        }
        if($msg_string !== false) {
            $msg_string = vsprintf($msg_string, $args);
        }
        return $msg_string;
    }
    /**
     *  Get the current IANA language tag being used by the search engine
     *
     *  @return string an IANA language tag
     */
    function getLocaleTag()
    {
        return $this->locale_tag;
    }
    /**
     *  The text direction of the current locale being used by the text engine
     *
     *  @return string either ltr (left-to-right) or rtl (right-to-left)
     */
    function getLocaleDirection()
    {
        switch($this->writing_mode)
        {
            case "lr-tb":
                return "ltr";
            break;
            case "rl-tb":
                return "rtl";
            break;
            case "tb-rl":
                return "ltr";
            break;
            case "tb-lr":
                return "ltr";
            break;
        }
        return "ltr";
    }
    /**
     * The direction that blocks (such as p or div tags) should be drawn in
     * the current locale
     *
     * @return string a direction which is one of tb -- top-bottom,
     *      rl -- right-to-left, or lr -- left-to-right
     */
    function getBlockProgression()
    {
        switch($this->writing_mode)
        {
            case "lr-tb":
                return "tb";
            break;
            case "rl-tb":
                return "tb";
            break;
            case "tb-rl":
                return "rl";
            break;
            case "tb-lr":
                return "lr";
            break;
        }
        return "tb";
    }
    /**
     * Get the writing mode of the current locale (text and block directions)
     *
     * @return string the current writing mode
     */
    function getWritingMode()
    {
        return $this->writing_mode;
    }
    /**
     * Used to extract identifier strings from files with correct extensions,
     * then these strings are merged with existing extracted strings for each
     * locale as well as their translations (if an extract string has a
     * translation the translation is untouched by this process).
     *
     * @param array $force_folders which locale subfolders should be forced
     *      updated to the fallback dir's version
     *
     * @return array a pair consisting of the data from the general.ini file
     *      together with an array of msg_ids msg_strings.
     */
    function extractMergeLocales($force_folders = array())
    {
        $list = $this->getLocaleList();
            // getLocaleList will also create any missing locale dirs
        $strings =
            $this->getTranslateStrings($this->extract_dirs, $this->extensions);
        $general_ini = parse_ini_with_fallback(LOCALE_DIR."/general.ini");
        $this->updateLocales($general_ini, $strings, $force_folders);
        return array($general_ini, $strings);
    }
    /**
     *  Cycles through locale subdirectories in LOCALE_DIR, for each
     *  locale it merges out the current general_ini and strings data.
     *  It deletes identifiers that are not in strings, it adds new identifiers
     *  and it leaves existing identifier translation pairs untouched.
     *
     * @param array $general_ini  data that would typically come from the
     *      general.ini file
     * @param array $string lines from what is equivalent to an ini file
     *      of msg_id msg_string pairs these lines also have comments on the
     *      file that strings were extracted from
     * @param array $force_folders which locale subfolders should be forced
     *      updated to the fallback dir's version
     *
     */
    function updateLocales($general_ini, $strings, $force_folders = array())
    {
        $path = LOCALE_DIR;
        if(!$dh = @opendir($path)) {
            die("Couldn't read locale directory!\n");
        }
        while (($obj = readdir($dh)) !== false) {
            if($obj[0] == '.') {
                continue;
            }
            $cur_path = $path . '/' . $obj;
            if (is_dir($cur_path)) {
                $this->updateLocale($general_ini, $strings, $path, $obj,
                    NULL, $force_folders);
            }
        }
    }
    /**
     * Updates the configure.ini file and static pages for a particular locale.
     *
     * The configure.ini has general information (at this point not really
     * being used) about all locales together with specific msg_id (identifiers
     * to be translated) and msg_string (translation) data. updateLocale takes
     * line data coming from the general.ini file, strings extracted from
     * documents that might need to be translation, the old configure.ini file
     * (this might have existing translations),  as well as new translation
     * data that might come from a localizer via a web form and
     * combines these to produce a new configure.ini file
     *
     * @param array $general_ini data from the general.ini file
     * @param array $strings line array data extracted from files in
     *      directories that have strings in need of translation
     * @param string $dir the directory of all the locales
     * @param string $locale the particular locale in $dir to update
     * @param array $new_configure translations of identifier strings from
     *      another source such as a localizer using a web form
     * @param array $force_folders which locale subfolders should be forced
     *      updated to the fallback dir's version
     */
    function updateLocale($general_ini, $strings,
        $dir, $locale, $new_configure = NULL, $force_folders = array())
    {
        $old_configure = array();
        $cur_path = $dir . '/' . $locale;
        if(file_exists($cur_path.'/configure.ini')) {
            $old_configure = parse_ini_with_fallback(
                $cur_path.'/configure.ini');
        }
        $fallback_path = FALLBACK_LOCALE_DIR. '/' . $locale;
        if(file_exists($fallback_path . '/configure.ini')) {
            $fallback_configure = parse_ini_with_fallback(
                $fallback_path . '/configure.ini');
        }
        if(file_exists($fallback_path.'/resources')) {
            if(in_array("resources", $force_folders)) {
                rename($cur_path.'/resources', $cur_path.
                    '/resources'.time().'old');
            }
            $this->updateLocaleSubFolder($cur_path.'/resources',
                $fallback_path.'/resources', array("js", "php", "ftr",
                "txt.gz"));
        }
        $n = array();
        $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK *****
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
;
;  This program is free software: you can redistribute it and/or modify
;  it under the terms of the GNU General Public License as published by
;  the Free Software Foundation, either version 3 of the License, or
;  (at your option) any later version.
;
;  This program is distributed in the hope that it will be useful,
;  but WITHOUT ANY WARRANTY; without even the implied warranty of
;  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;  GNU General Public License for more details.
;
;  You should have received a copy of the GNU General Public License
;  along with this program.  If not, see <http://www.gnu.org/licenses/>.
;  ***** END LICENSE BLOCK *****
;
; configure.ini
;
; $locale configuration file
;
EOT;
        foreach($general_ini as $general_name => $general_value) {
            if(is_array($general_value)) {
                $n[] = "[$general_name]";
                foreach($general_value as $name => $value) {
                    $n[] = $this->updateTranslation(
                        $new_configure[$general_name],
                        $old_configure[$general_name],
                        $fallback_configure[$general_name],
                        $name, $value);
                }
            } else {
                $n[] = $this->updateTranslation($new_configure,
                    $old_configure, $fallback_configure, $general_name);
            }
        }
        $n[] = ";\n; Strings to translate on various pages\n;";
        $n[] = "[strings]";
        foreach($strings as $string) {
            if( isset($string[0]) && $string[0] == ";") {
                $n[] = $string;
            } else {
                $n[] = $this->updateTranslation($new_configure['strings'],
                    $old_configure['strings'], $fallback_configure['strings'],
                    $string);
            }
        }
        $out = implode("\n", $n);
        $out .= "\n";
        file_put_contents($cur_path.'/configure.ini', $out);
    }
    /**
     *  Computes a string of the form string_id = 'translation' for a string_id
     *  from among translation array data in $new_configure (most preferred,
     *  probably come from recent web form data), $old_configure
     *  (probably from work dir), and $fallback_configure (probably from base
     *  dir of Yioop instance, least preferred).
     *
     *  @param array $new_configure string_id => translation pairs
     *  @param array $old_configure string_id => translation pairs
     *  @param array $fallback_configure string_id => translation pairs
     *  @param string $string_id an id to translate
     *  @param string $default_value value to use if no configuration
     *      has a translation for a string_id
     *  @return string translation in format describe above
     */
    function updateTranslation($new_configure, $old_configure,
        $fallback_configure, $string_id, $default_value = "")
    {
        $translation = $string_id . ' = "'.
            addslashes($this->lookupTranslation($new_configure, $old_configure,
            $fallback_configure, $string_id, $default_value)).'"';
        return $translation;
    }
    /**
     *  Translates a string_id from among translation array data in
     *  $new_configure (most preferred, probably come from recent web form
     *  data), $old_configure  (probably from work dir), and $fallback_configure
     *  (probably from base  dir of Yioop instance, least preferred).
     *
     *  @param array $new_configure string_id => translation pairs
     *  @param array $old_configure string_id => translation pairs
     *  @param array $fallback_configure string_id => translation pairs
     *  @param string $string_id an id to translate
     *  @param string $default_value value to use if no configuration
     *      has a translation for a string_id
     *  @return string translation of string id
     */
    function lookupTranslation($new_configure, $old_configure,
        $fallback_configure, $string_id, $default_value = "")
    {
        $new_translation = $this->isTranslated($string_id, $new_configure);
        $old_translation = $this->isTranslated($string_id, $old_configure);
        if($new_translation || ( isset($new_configure[$string_id]) &&
            $new_configure[$string_id] === "" && $old_translation )) {
            $translation = $new_configure[$string_id];
        } else if($this->isTranslated($string_id, $old_configure)) {
            $translation = $old_configure[$string_id];
        } else if($this->isTranslated($string_id, $fallback_configure)) {
            $translation = $fallback_configure[$string_id];
        } else {
            $translation = $default_value;
        }
        return $translation;
    }
    /**
     * Checks if the given string_id has a translation in translations
     *
     * @param string $string_id what to check if translated
     * @param array $translations of form string_id => translation
     *      defaults to current configuration
     * @return bool whether a translation of nonzero length exists
     */
    function isTranslated($string_id, $translations = false)
    {
        if($translations === false) {
            $translations = $this->configure['strings'];
        }
        return isset($translations[$string_id]) &&
            strlen($translations[$string_id]) > 0;
    }
    /**
     *  Copies over subfolder items of the correct file extensions
     *  which exists in a fallback directory, but not in the actual directory
     *  of a locale.
     *
     *  @param string $locale_pages_path static page directory to which will
     *     copy
     *  @param string $fallback_pages_path static page directory from which will
     *     copy
     *  @param array $file_extensions an array of strings names of file
     *      extensions for example: .txt.gz .thtml .php ,etc
     */
    function updateLocaleSubFolder($locale_pages_path, $fallback_pages_path,
        $file_extensions) {
        $change = false;
        if(!file_exists($locale_pages_path)) {
            mkdir($locale_pages_path);
            $change = true;
        }
        foreach($file_extensions as $file_extension) {
            foreach(glob($fallback_pages_path."/*.$file_extension")
                as $fallback_page_name) {
                $basename = basename($fallback_page_name);
                $locale_page_name = "$locale_pages_path/$basename";
                if(!file_exists($locale_page_name)) {
                    copy($fallback_page_name, $locale_page_name);
                    $change = true;
                }
            }
        }
        $this->db->setWorldPermissionsRecursive($locale_pages_path);
    }
    /**
     * Searches the directories provided looking for files matching the
     * extensions provided. When such a file is found it is loaded and scanned
     * for tl() function calls. The identifier string in this function call is
     * then extracted and added to a line array of strings to be translated.
     * This line array is formatted so that each line looks like a line that
     * might occur in an PHP ini file. To understand this format one can look at
     * the parse_ini_string function in the PHP manual or look at the
     * configure.ini files in the locale directory
     *
     * @param array $extract_dirs directories to start looking for files with
     *      strings to be translated
     * @param array $extensions file extensions of files which might contain
     *      such strings
     * @return array of lines for any ini file of msg_id msg_string pairs
     */
    function getTranslateStrings($extract_dirs, $extensions)
    {
        $strings = array();
        $base_dirs = array(BASE_DIR, APP_DIR);
        foreach($extract_dirs as $dir) {
            foreach($base_dirs as $base_dir) {
                $path = $base_dir."/".$dir;
                $dir_strings =
                    $this->traverseExtractRecursive($path, $extensions);
                if(count($dir_strings) > 0) {
                    $strings[] = ";";
                    $strings[] = "; $path";
                    $strings = array_merge($strings, $dir_strings);
                }
            }
        }
        return $strings;
    }
    /**
     * Traverses a directory and its subdirectories looking for files
     * whose extensions come from the extensions array. As the traversal
     * is done a strings array is created. Each time a file is found of
     * any identifiers of strings that need to be translated are added to
     * the strings array. In addition, ini style comments are added givne the
     * line file and line number of the item to be translated
     *
     * @param string $dir current directory to start looking for files with
     *      strings to be translated
     * @param array $extensions  file extensions of files which might contain
     *      such strings
     * @return array of lines for any ini file of msg_id msg_string pairs
     */
    function traverseExtractRecursive($dir, $extensions)
    {
        $strings = array();
        if(!is_dir($dir) || !$dh = @opendir($dir)) {
            return array();
        }
        while (($obj = readdir($dh)) !== false) {
            if($obj == '.' || $obj == '..') {
                continue;
            }
            $cur_path = $dir . '/' . $obj;
            if(is_dir($cur_path)) {
                 $dir_strings =
                    $this->traverseExtractRecursive($cur_path, $extensions);
                 if(count($dir_strings) > 0) {
                      $strings[] = ";";
                      $strings[] = "; $cur_path";
                      $strings = array_merge($strings, $dir_strings);
                 }
            }
            if(is_file($cur_path)) {
                $path_parts = pathinfo($cur_path);
                $extension = (isset($path_parts['extension'])) ?
                    $path_parts['extension'] : "";
                if(in_array($extension, $extensions)) {
                    $lines = file($cur_path);
                    $num_lines = count($lines);
                    for($i = 0; $i < $num_lines; $i++) {
                        $num_matches = preg_match_all(
                           '/tl\([\'|\"]?([[:word:]]+?)[\'|\"]?[(\))|(\s+\,)]/',
                           $lines[$i], $to_translates);
                        if($num_matches > 0) {
                            $strings[] = ";";
                            $strings[] = "; $obj line: $i";
                            $strings = array_merge($strings, $to_translates[1]);
                        }
                    }
                }
            }
        }
        closedir($dh);
        return $strings;
    }
}
 ?>
