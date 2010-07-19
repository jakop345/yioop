<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * Extracts strings of the
 * SeekQuarry project for localization
 * It then merges the data to each locale.
 * This is essentially a subset of the code as that is in {@link LocaleModel}.
 * The main difference was the code in this file was developed first and
 * was intended to be run from the command line. LocaleModel is intended to
 * be used with the search engine's web interface.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage locale
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/** Calculate base directory of script */
define("BASE_DIR", substr($_SERVER['DOCUMENT_ROOT'].$_SERVER['PWD'].
    $_SERVER["SCRIPT_NAME"], 0, 
    -strlen("locale/extract_merge.php")));

/** Loads config info */
require_once BASE_DIR."/configs/config.php";

/**
 * Directories to try to extract translatable identifier strings from
 * @var array
 */
$extract_dirs = array("controllers", "views");
/**
 * File extensions of files to try to extract translatable strings from
 * @var array
 */
$extensions = array("php");

/**
 *  Global locale data as that come from the general.ini file
 *  @var array
 */
$strings = getTranslateStrings($extract_dirs, $extensions) ;
/**
 *  Lines that form essentially a ini file of data of msg_id msg_string pairs
 *  interspersed with comments on which files which string come from
 *  @var array
 */
$general_ini = parse_ini_file(LOCALE_DIR."/general.ini", true);

updateLocales($general_ini, $strings);

/**
 * Cycles through locale subdirectories in LOCALE_DIR, for each
 * locale it merges out the current gwneral_ini and strings data.
 * It deletes identifiers that are not in strings, it adds new identifiers
 * and it leaves existing identifier translation pairs untouched.
 *
 * @param array $general_ini  data that would typically come from the 
 *      general.ini file
 * @param array $strings lines from what is equivalent to an ini file of 
 *      msg_id msg_string pairs these lines also have comments on the file 
 *      that strings were extracted from
 * 
 */
function updateLocales($general_ini, $strings)
{
  $path = LOCALE_DIR;
  if(!$dh = @opendir($path)) {
    die("Couldn't read locale directory!\n");
  }
  while (($obj = readdir($dh)) !== false) {
     if($obj == '.' || $obj == '..') {
         continue;
     }
     $cur_path = $path . '/' . $obj;
     if (is_dir($cur_path)) {
        updateLocale($general_ini, $strings, $path, $obj);
     }
  } 
}

/**
 * Updates the configure.ini file for a particular locale. 
 *  
 * The configure.ini has general information (at this point not really being 
 * used) about all locales together with specific msg_id (identifiers to be 
 * translated) and msg_string (translation) data. updateLocale takes line data 
 * coming from the general.ini file, strings extracted from documents that 
 * might need to be translation, as well as the old configure.ini file (this 
 * might have existing translations), and combines these to produce a new 
 * configure.ini file
 *
 * @param array $general_ini data from the general.ini file
 * @param array $strings line array data extracted from files in directories 
 *      that have strings in need of translation
 * @param string $dir the directory of all the locales
 * @param string $locale  the particular locale in $dir to update
 */
function updateLocale($general_ini, $strings, $dir, $locale)
{
    $old_configure = array();
    $cur_path = $dir . '/' . $locale;
    if(file_exists($cur_path.'/configure.ini')) {
        $old_configure = parse_ini_file($cur_path.'/configure.ini', true);
    }
    $n = array();
    $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK ***** 
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
                if(isset($old_configure[$general_name][$name])) {
                    $n[] = $name.' = "'.
                        addslashes($old_configure[$general_name][$name]).'"';
                } else {
                    $n[] = $name.' = "'.$value.'"';
                }
            }
        } else {
            if(isset($old_configure[$general_name])) {
                $n[] = $general_name.' = "'.
                    addslashes($old_configure[$general_name]).'"';
            } else {
                $n[] = $name.' = "'.$value.'"';
            }
        }
    }

    $n[] = ";\n; Strings to translate on various pages\n;";
    $n[] = "[strings]";
    foreach($strings as $string) {
        if( isset($string[0]) && $string[0] == ";") {
            $n[] = $string;
        } else {
            if(isset($old_configure['strings'][$string])) {
                $n[] = $string.' = "'.
                    addslashes($old_configure['strings'][$string]).'"';
            } else {
                $n[] = $string.' = ""';
            }
        }
    }
  
  $out = implode("\n", $n);
  file_put_contents($cur_path.'/configure.ini', $out);
}


/**
 * Searches the directories provided looking for files matching the extensions 
 * provided. When such a file is found it is loaded and scanned for tl() 
 * function calls. The identifier string in this function call is then 
 * extracted and added to a line array of strings to be translated. This line
 * array is formatted so that each line looks like a line that might occur in 
 * an PHP ini file. To understand this format one can look at the 
 * parse_ini_string function in the PHP manual or look at the configure.ini 
 * files in the locale directory
 *
 * @param array $extract_dirs directories to start looking for files with 
 *      strings to be translated
 * @param array $extensions file extensions of files which might contain such 
 *      strings
 * @return array of lines for any ini file of msg_id msg_string pairs
 */
function getTranslateStrings($extract_dirs, $extensions) 
{
    $strings = array();
    foreach($extract_dirs as $dir) {
        $path = BASE_DIR."/".$dir;
        $dir_strings = traverseExtractRecursive($path, $extensions);
        if(count($dir_strings) > 0) {
            $strings[] = ";";
            $strings[] = "; $path";
            $strings = array_merge($strings, $dir_strings);
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

    if(!$dh = @opendir($dir)) {
        return array();
    }

    while (($obj = readdir($dh)) !== false) {
        if($obj == '.' || $obj == '..') {
            continue;
        }

        $cur_path = $dir . '/' . $obj;
        if (is_dir($cur_path)) {
            $dir_strings = traverseExtractRecursive($cur_path, $extensions);
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

    return $strings;
    closedir($dh);

    return;
}
?>
