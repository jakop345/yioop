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
/**
 * This is class is used to handle the
 * captcha settings for Yioop
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class TextcaptchasettingsModel extends Model
{
    /**
     * Given the identifier string for the question, return the translation_id
     * of a string given the identifier_string, for the captcha question
     *
     * @param string $identifier_string which is the identifier string for
     * a captcha question
     * @return string the identifier string for a captcha question
     */

    function getTranslationId($identifier_string)
    {

         $sql = "SELECT DISTINCT(TRANSLATION_ID) FROM TRANSLATION ". 
             "WHERE IDENTIFIER_STRING = :identifier_string";
         $result = $this->db->execute($sql,
            array(":identifier_string" => $identifier_string));
         if($result){
             $row = $this->db->fetchArray($result);
         }
         return $row['TRANSLATION_ID'];
    }

    /**
     * Computes and returns identifier_string for a captcha question
     * given captcha_locale and captcha_type
     *
     * @param string captcha_type either captcha or recovery which is a part of
     * the identifier string for a question
     * @param string question_type either most or least which is a
     * part of the identifier string for a captcha/recovery question
     *
     * @return string the identifier_string for a captcha question
     */

    function getIdentifierStringQuestion($captcha_type, $question_type)
    {
        $identifier_string_question = "";
        $row = "";

        // Most Captcha questions
         if($captcha_type == CAPTCHA && $question_type == MOST) {
            $identifier_string_filter = "db_most_question%";
            $most_captcha_sql = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ".
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ".
                "LIKE :identifier_string_filter ".
                "ORDER BY IDENTIFIER_STRING DESC";
            $result = $this->db->execute($most_captcha_sql,
                array(":identifier_string_filter" =>
                $identifier_string_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
            $identifier_string_index = str_replace("db_most_question", "",
                $row['IDENTIFIER_STRING']);
            /*
                Converts $identifier_index to Integer
                Checks if $identifier_index is an integer.
                If yes, adds 1 to it
              */
            if(!is_numeric($identifier_string_index)) {
                return false;
            } else {
                $identifier_index = (int)$identifier_string_index;
                $identifier_index += 1;
            }
            $identifier_string_question = str_replace("db_most_question%",
                "db_most_question", $identifier_string_filter);
            $identifier_string_question =
                $identifier_string_question.$identifier_index;


         // Least Captcha Questions
         } else if($captcha_type == CAPTCHA && $question_type == LEAST) {
             $identifier_string_filter = "db_least_question%";
            $least_captcha_sql = "SELECT TRANSLATION_ID, ". 
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ".
                "LIKE :identifier_string_filter ". 
                "ORDER BY IDENTIFIER_STRING DESC";
            $result = $this->db->execute($least_captcha_sql,
                array(":identifier_string_filter" =>
                $identifier_string_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
            $identifier_string_index = str_replace("db_least_question", "",
                $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index to Integer
               Checks if $identifier_index is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($identifier_string_index)) {
               return false;
            } else {
               $identifier_index = (int)$identifier_string_index;
               $identifier_index += 1;
            }
            $identifier_string_question = str_replace("db_least_question%",
                "db_least_question", $identifier_string_filter);
            $identifier_string_question =
                $identifier_string_question.$identifier_index;

        // Most Preferences Questions
        } else if($captcha_type == RECOVERY && $question_type == MOST) {
             $identifier_string_filter = "db_most_prefquestion%";
            $most_preferences_sql = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter ". 
                "ORDER BY IDENTIFIER_STRING DESC";
            $result = $this->db->execute($most_preferences_sql,
                array(":identifier_string_filter" =>
                $identifier_string_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
           $identifier_string_index = str_replace("db_most_prefquestion", "",
                $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index to Integer
               Checks if $identifier_index is an integer.
               If yes, adds 1 to it
             */
           if(!is_numeric($identifier_string_index)) {
               return false;
           } else {
               $identifier_index = (int)$identifier_string_index;
               $identifier_index += 1;
           }
           $identifier_string_question = str_replace("db_most_prefquestion%",
                "db_most_prefquestion", $identifier_string_filter);
           $identifier_string_question =
               $identifier_string_question.$identifier_index;

          // Least Preferences Questions
          } else if($captcha_type == RECOVERY && $question_type == LEAST) {
              $identifier_string_filter = "db_least_prefquestion%";
            $least_preferences_sql = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter ". 
                "ORDER BY IDENTIFIER_STRING DESC";
            $result = $this->db->execute($least_preferences_sql,
                array(":identifier_string_filter" =>
                $identifier_string_filter));
             if($result) {
                 $row = $this->db->fetchArray($result);
             }
            $identifier_string_index = str_replace("db_least_prefquestion", "",
                $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index to Integer
               Checks if $identifier_index is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($identifier_string_index)) {
                return false;
            } else {
                $identifier_index = (int)$identifier_string_index;
                $identifier_index += 1;
            }
            $identifier_string_question = str_replace("db_least_prefquestion%",
                "db_least_prefquestion", $identifier_string_filter);
            $identifier_string_question =
                $identifier_string_question.$identifier_index;

        }
         return $identifier_string_question;
    }

    /**
     * Computes and returns identifier_string for a captcha choice list
     * given captcha_locale and captcha_type
     *
     * @param string captcha_type either captcha or recovery which is a part of
     * the identifier string for a question
     * @param string question_type either most or least which is a
     * part of the identifier string for a captcha/recovery question
     *
     * @return string the identifier_string for a captcha choice list
     */

    function getIdentifierStringChoices($captcha_type, $question_type)
    {
        $identifier_string_choices = "";
        $row = "";

        // Most Captcha choices
         if($captcha_type == CAPTCHA && $question_type == MOST) {
            $identifier_string_filter_choices = "db_most_captcha_choices%";
            $most_captcha_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter_choices ".
                "ORDER BY IDENTIFIER_STRING DESC";
            $result_choices = $this->db->execute($most_captcha_sql_choices,
                array(":identifier_string_filter_choices" =>
                $identifier_string_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
            $identifier_string_index_choices =
                str_replace("db_most_captcha_choices", "",
                $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index_choices to Integer
               Checks if $identifier_index_choices is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($identifier_string_index_choices)){
                return false;
            } else {
                $identifier_index_choices =
                    (int)$identifier_string_index_choices;
                $identifier_index_choices += 1;
            }
            $identifier_string_choices =
                str_replace("db_most_captcha_choices%",
                "db_most_captcha_choices", $identifier_string_filter_choices);
            $identifier_string_choices =
                $identifier_string_choices.$identifier_index_choices;

         // Least Captcha choices
         } else if($captcha_type == CAPTCHA && $question_type == LEAST) {
             $identifier_string_filter_choices = "db_least_captcha_choices%";
            $least_captcha_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter_choices ".
                "ORDER BY IDENTIFIER_STRING DESC";
            $result_choices = $this->db->execute($least_captcha_sql_choices,
                array(":identifier_string_filter_choices" =>
                $identifier_string_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
           $identifier_string_index_choices =
               str_replace("db_least_captcha_choices", "",
               $row['IDENTIFIER_STRING']);
           /*
              Converts $identifier_index_choices to Integer
              Checks if $identifier_index_choices is an integer.
              If yes, adds 1 to it
            */
           if(!is_numeric($identifier_string_index_choices)) {
               return false;
           } else {
               $identifier_index_choices =
                   (int)$identifier_string_index_choices;
               $identifier_index_choices += 1;
           }
           $identifier_string_choices =
               str_replace("db_least_captcha_choices%",
               "db_least_captcha_choices", $identifier_string_filter_choices);
           $identifier_string_choices =
               $identifier_string_choices.$identifier_index_choices;

        // Most Preferences choices
        } else if($captcha_type == RECOVERY && $question_type == MOST) {
             $identifier_string_filter_choices = "db_most_prefchoices%";
            $most_preferences_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter_choices ".
                "ORDER BY IDENTIFIER_STRING DESC";
            $result_choices = $this->db->execute($most_preferences_sql_choices,
                array(":identifier_string_filter_choices" =>
                $identifier_string_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
           $identifier_string_index_choices =
               str_replace("db_most_prefchoices", "",
               $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index_choices to Integer
               Checks if $identifier_index_choices is an integer.
               If yes, adds 1 to it
             */
           if(!is_numeric($identifier_string_index_choices)) {
               return false;
           } else {
               $identifier_index_choices =
                   (int)$identifier_string_index_choices;
               $identifier_index_choices += 1;
           }
           $identifier_string_choices = str_replace("db_most_prefchoices%",
               "db_most_prefchoices", $identifier_string_filter_choices);
           $identifier_string_choices =
               $identifier_string_choices.$identifier_index_choices;

          // Least Preferences choices
          } else if($captcha_type == RECOVERY && $question_type == LEAST) {
              $identifier_string_filter_choices = "db_least_prefchoices%";
            $least_preferences_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(IDENTIFIER_STRING) AS IDENTIFIER_STRING ". 
                "FROM TRANSLATION WHERE IDENTIFIER_STRING ". 
                "LIKE :identifier_string_filter_choices ".
                "ORDER BY IDENTIFIER_STRING DESC";
            $result_choices =
                $this->db->execute($least_preferences_sql_choices,
                array(":identifier_string_filter_choices" =>
                $identifier_string_filter_choices));
            if($result_choices) {
                 $row = $this->db->fetchArray($result_choices);
            }
            $identifier_string_index_choices =
                str_replace("db_least_prefchoices", "",
                $row['IDENTIFIER_STRING']);
            /*
               Converts $identifier_index_choices to Integer
               Checks if $identifier_index_choices is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($identifier_string_index_choices)) {
                return false;
            } else {
                $identifier_index_choices =
                    (int)$identifier_string_index_choices;
                $identifier_index_choices += 1;
            }
            $identifier_string_choices = str_replace("db_least_prefchoices%",
                "db_least_prefchoices", $identifier_string_filter_choices);
            $identifier_string_choices =
                $identifier_string_choices.$identifier_index_choices;

        } 
         return $identifier_string_choices;
    }

    /**
     * Computes and returns method_name for a captcha/recovery question
     * given captcha_locale and captcha_type
     *
     * @param string captcha_type either captcha or recovery which is a part of
     * the identifier string for a question
     * @param string question_type either most or least which is a
     * part of the identifier string for a captcha/recovery question
     *
     * @return string the method name for a captcha/recovery question
     */

    function getMethodNameQuestion($captcha_type, $question_type)
    {
        $method_name_question = "";
        $row = "";

        // Most Captcha questions
         if($captcha_type == CAPTCHA && $question_type == MOST) {
            $method_name_filter = "captcha_question_most%";
            $most_captcha_sql = "SELECT TRANSLATION_ID, MAX(METHOD_NAME) ".
                "AS METHOD_NAME FROM CAPTCHA WHERE METHOD_NAME ". 
                "LIKE :method_name_filter ORDER BY METHOD_NAME DESC";
            $result = $this->db->execute($most_captcha_sql,
                array(":method_name_filter" => $method_name_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
            $method_name_index = str_replace("captcha_question_most", "",
                $row['METHOD_NAME']);
            /*
                Converts $method_index to Integer
                Checks if $method_index is an integer.
                If yes, adds 1 to it
              */
            if(!is_numeric($method_name_index)) {
                return false;
            } else {
                $method_index = (int)$method_name_index;
                $method_index += 1;
            }
            $method_name_question = str_replace("captcha_question_most%",
                "captcha_question_most", $method_name_filter);
            $method_name_question = $method_name_question.$method_index;

        // Least Captcha questions
         } else if($captcha_type == CAPTCHA && $question_type == LEAST) {
             $method_name_filter = "captcha_question_least%";
            $least_captcha_sql = "SELECT TRANSLATION_ID, MAX(METHOD_NAME) ".
                "AS METHOD_NAME FROM CAPTCHA WHERE METHOD_NAME ". 
                "LIKE :method_name_filter ORDER BY METHOD_NAME DESC";
            $result = $this->db->execute($least_captcha_sql,
                array(":method_name_filter" => $method_name_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
           $method_name_index = str_replace("captcha_question_least", "",
                $row['METHOD_NAME']);
            /*
                Converts $method_index to Integer
                Checks if $method_index is an integer.
                If yes, adds 1 to it
              */
           if(!is_numeric($method_name_index)) {
                return false;
           } else {
                $method_index = (int)$method_name_index;
                $method_index += 1;
           }
           $method_name_question = str_replace("captcha_question_least%",
                "captcha_question_least", $method_name_filter);
           $method_name_question = $method_name_question.$method_index;

        // Most Preferences Questions
        } else if($captcha_type == RECOVERY && $question_type == MOST) {
             $method_name_filter = "preferences_question_most%";
            $most_preferences_sql = "SELECT TRANSLATION_ID, MAX(METHOD_NAME) ".
                "AS METHOD_NAME FROM PREFERENCES WHERE METHOD_NAME ". 
                "LIKE :method_name_filter ORDER BY METHOD_NAME DESC";
            $result = $this->db->execute($most_preferences_sql,
                array(":method_name_filter" => $method_name_filter));
            if($result) {
                $row = $this->db->fetchArray($result);
            }
           $method_name_index = str_replace("preferences_question_most", "",
                $row['METHOD_NAME']);
            /*
                Converts $method_index to Integer
                Checks if $method_index is an integer.
                If yes, adds 1 to it
              */
           if(!is_numeric($method_name_index)) {
               return false;
           } else {
               $method_index = (int)$method_name_index;
               $method_index += 1;
           }
           $method_name_question = str_replace("preferences_question_most%",
                "preferences_question_most", $method_name_filter);
           $method_name_question = $method_name_question.$method_index;

         // Least Preferences Questions
          } else if($captcha_type == RECOVERY && $question_type == LEAST) {
              $method_name_filter = "preferences_question_least%";
            $least_preferences_sql = "SELECT TRANSLATION_ID, ". 
                "MAX(METHOD_NAME) AS METHOD_NAME FROM PREFERENCES WHERE ". 
                "METHOD_NAME LIKE :method_name_filter ". 
                "ORDER BY METHOD_NAME DESC";
             $result = $this->db->execute($least_preferences_sql,
                array(":method_name_filter" => $method_name_filter));
             if($result) {
                 $row = $this->db->fetchArray($result);
             }
            $method_name_index = str_replace("preferences_question_least", "",
                $row['METHOD_NAME']);
            /*
               Converts $method_index to Integer
               Checks if $method_index is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($method_name_index)) {
                return false;
            } else {
                $method_index = (int)$method_name_index;
                $method_index += 1;
            }
            $method_name_question = str_replace("preferences_question_least%",
                "preferences_question_least", $method_name_filter);
            $method_name_question = $method_name_question.$method_index;

        } 
         return $method_name_question;
    }

    /**
     * Computes and returns method_name for a captcha/recovery choice list
     * given captchaLocale and captchaType
     *
     * @param string captcha_type either captcha or recovery which is a part of
     * the identifier string for a question
     * @param string question_type either most or least which is a
     * part of the identifier string for a captcha/recovery question
     *
     * @return string the method_name for a captcha/preference choice list
     */

    function getMethodNameChoices($captcha_type, $question_type)
    {
        $method_name_choices = "";
        $row = "";

        // Most Captcha choices
         if($captcha_type == CAPTCHA && $question_type = MOST) {
            $method_name_filter_choices = "captcha_choices_most%";
            $most_captcha_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(METHOD_NAME) AS METHOD_NAME FROM CAPTCHA WHERE ".
                "METHOD_NAME LIKE :method_name_filter_choices ".
                "ORDER BY METHOD_NAME DESC";
            $result_choices = $this->db->execute($most_captcha_sql_choices,
                array(":method_name_filter_choices" =>
                $method_name_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
            $method_name_index_choices = str_replace("captcha_choices_most",
                "", $row['METHOD_NAME']);
            /*
               Converts $method_index_choices to Integer
               Checks if $method_index_choices is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($method_name_index_choices)) {
                return false;
            } else {
            $method_index_choices = (int)$method_name_index_choices;
            $method_index_choices += 1;
            }
            $method_name_choices = str_replace("captcha_choices_most%",
                "captcha_choices_most", $method_name_filter_choices);
            $method_name_choices = $method_name_choices.$method_index_choices;

        // Least Captcha choices
         } else if($captcha_type == CAPTCHA && $question_type == LEAST) {
             $method_name_filter_choices = "captcha_choices_least%";
            $least_captcha_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(METHOD_NAME) AS METHOD_NAME FROM CAPTCHA WHERE ".
                "METHOD_NAME LIKE :method_name_filter_choices ".
                "ORDER BY METHOD_NAME DESC";
            $result_choices = $this->db->execute($least_captcha_sql_choices,
                array(":method_name_filter_choices" =>
                $method_name_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
           $method_name_index_choices = str_replace("captcha_choices_least",
                "", $row['METHOD_NAME']);
            /*
               Converts $method_index_choices to Integer
               Checks if $method_index_choices is an integer.
               If yes, adds 1 to it
             */
           if(!is_numeric($method_name_index_choices)) {
                return false;
           } else {
                $method_index_choices = (int)$method_name_index_choices;
                $method_index_choices += 1;
           }
           $method_name_choices = str_replace("captcha_choices_least%",
                "captcha_choices_least", $method_name_filter_choices);
           $method_name_choices = $method_name_choices.$method_index_choices;

        // Most Preferences choices
        } else if($captcha_type == RECOVERY && $question_type == MOST) {
             $method_name_filter_choices = "preferences_choices_most%";
            $most_preferences_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(METHOD_NAME) AS METHOD_NAME FROM PREFERENCES WHERE ".
                "METHOD_NAME LIKE :method_name_filter_choices ".
                "ORDER BY METHOD_NAME DESC";
            $result_choices = $this->db->execute($most_preferences_sql_choices,
                array(":method_name_filter_choices" =>
                $method_name_filter_choices));
            if($result_choices){
                $row = $this->db->fetchArray($result_choices);
            }
           $method_name_index_choices = str_replace("preferences_choices_most",
                "", $row['METHOD_NAME']);
            /*
               Converts $method_index_choices to Integer
               Checks if $method_index_choices is an integer.
               If yes, adds 1 to it
             */
           if(!is_numeric($method_name_index_choices)) {
                return false;
           } else {
               $method_index_choices = (int)$method_name_index_choices;
               $method_index_choices += 1;
           }
           $method_name_choices = str_replace("preferences_choices_most%",
                "preferences_choices_most", $method_name_filter_choices);
           $method_name_choices = $method_name_choices.$method_index_choices;

         // Least Preferences choices
          } else if($captcha_type == RECOVERY && $question_type == LEAST) {
              $method_name_filter_choices = "preferences_choices_least%";
            $least_preferences_sql_choices = "SELECT TRANSLATION_ID, ".
                "MAX(METHOD_NAME) AS METHOD_NAME FROM PREFERENCES WHERE ".
                "METHOD_NAME LIKE :method_name_filter_choices ".
                "ORDER BY METHOD_NAME DESC";
            $result_choices = $this->db->execute($least_preferences_sql_choices,
                array(":method_name_filter_choices" =>
                $method_name_filter_choices));
            if($result_choices) {
                $row = $this->db->fetchArray($result_choices);
            }
            $method_name_index_choices = str_replace("preferences_choices_least",
                "", $row['METHOD_NAME']);
            /*
               Converts $method_index_choices to Integer
               Checks if $method_index_choices is an integer.
               If yes, adds 1 to it
             */
            if(!is_numeric($method_name_index_choices)) {
                return false;
            } else {
                $method_index_choices = (int)$method_name_index_choices;
                $method_index_choices += 1;
            }
            $method_name_choices = str_replace("preferences_choices_least%",
                "preferences_choices_least", $method_name_filter_choices);
            $method_name_choices = $method_name_choices.$method_index_choices;

        }
         return $method_name_choices;
    }

    /**
     * Computes and returns locale_id for a given captcha_locale
     *
     * @param string $captcha_locale
     * @return string the locale_id for a given captcha_locale
     */

    function getLocaleId($captcha_locale)
    {
        $sql = "SELECT DISTINCT(LOCALE_ID) from LOCALE WHERE
            LOCALE_TAG = :captcha_locale";
        $result = $this->db->execute($sql,
            array(":captcha_locale" => $captcha_locale));
         if($result) {
             $row = $this->db->fetchArray($result);
         }
        return $row['LOCALE_ID'];
    }

    /**
     * Function for adding captcha/recovery question to the database
     *
     * @param string captcha_type which is either captcha/recovery
     * @param string question_type which is either most/least
     * @param string captcha_locale which is a list of locales to choose from
     * @param string captcha_question which is a captcha/recovery
     *      question to be added
     * @param string captcha_choices which is a list of captcha/recovery choices
     * @return bool value; returns true if the question-choice pair
     *      is added to the database
     */

    function addCaptchaDataToDatabase($captcha_type, $question_type,
                $captcha_locale, $captcha_question, $captcha_choices)
    {
        $db = $this->db;
        // Gets identifier string for insertion into the TRANSLATION table
        $identifier_string_question =
            $this->getIdentifierStringQuestion($captcha_type, $question_type);
        $identifier_string_choices =
            $this->getIdentifierStringChoices($captcha_type, $question_type);
        /*
           Gets method name for insertion into the CAPTCHA and
           PREFERENCES tables
         */
        $method_name_question =
            $this->getMethodNameQuestion($captcha_type, $question_type);
        $method_name_choices =
            $this->getMethodNameChoices($captcha_type, $question_type);

        // Insert into TRANSLATION table
        $sql_translation = "INSERT INTO TRANSLATION (IDENTIFIER_STRING) ".
            "VALUES(?)";
        $result_translation = $db->execute($sql_translation,
            array($identifier_string_question));
        if(!$result_translation) {
           return false;
        }
        $sql_translation = "INSERT INTO TRANSLATION (IDENTIFIER_STRING) ".
            "VALUES(?)";
        $result_translation = $db->execute($sql_translation,
            array($identifier_string_choices));
        if(!$result_translation) {
           return false;
        }
        $locale_id = $this->getLocaleId($captcha_locale);
        $translation_id_question =
            $this->getTranslationId($identifier_string_question);
        $translation_id_choices =
            $this->getTranslationId($identifier_string_choices);

        /*
           Insert into CAPTCHA and PREFERENCES tables
           Captcha and Preferences Questions
         */
        if($captcha_type == CAPTCHA) {
            $sql_captcha = "INSERT INTO CAPTCHA (TRANSLATION_ID, METHOD_NAME) ".
                "VALUES(?,?)";
            $sql_captcha_choices = "INSERT INTO CAPTCHA (TRANSLATION_ID, ".
                "METHOD_NAME) VALUES(?,?)";
            $result_captcha = $db->execute($sql_captcha,
                array($translation_id_question, $method_name_question));
            $result_captcha_choices = $db->execute($sql_captcha_choices,
                array($translation_id_choices, $method_name_choices));
            if(!$result_captcha) {
                return false;
            }
            if(!$result_captcha_choices) {
                return false;
            }
        } else if($captcha_type == RECOVERY) {
            $sql_recov = "INSERT INTO PREFERENCES (TRANSLATION_ID, METHOD_NAME) ".
                "VALUES(?,?)";
            $sql_recov_choices = "INSERT INTO PREFERENCES (TRANSLATION_ID, ".
                "METHOD_NAME) VALUES(?,?)";
            $result_recov = $db->execute($sql_recov,
                array($translation_id_question, $method_name_question));
            $result_recov_choices = $db->execute($sql_recov_choices,
                array($translation_id_choices, $method_name_choices));
            if(!$result_recov) {
                return false;
            }
            if(!$result_recov_choices) {
                return false;
            }
        } else {
            print_r("Not the right question type!");
        }

        // Insert into QUESTION_CHOICES_MAPPING table
        $sql_captcha = "INSERT INTO QUESTION_CHOICE_MAPPING ".
            "(TRANSLATION_ID_QUESTION, TRANSLATION_ID_CHOICES) VALUES(?,?)";
        $result_captcha = $db->execute($sql_captcha,
            array($translation_id_question, $translation_id_choices));
        if(!$result_captcha) {
            return false;
        }

        // Insert into TRANSLATION_LOCALE table
        $sql_translation_locale = "INSERT INTO TRANSLATION_LOCALE ".
            "(TRANSLATION_ID, LOCALE_ID, TRANSLATION) VALUES(?,?,?) ";
        $result = $db->execute($sql_translation_locale,
            array($translation_id_question, $locale_id, $captcha_question));
        if(!$result) {
            return false;
        }
        $result = $db->execute($sql_translation_locale,
            array($translation_id_choices, $locale_id, $captcha_choices));
        if(!$result) {
            return false;
        }
        return true;
    }

    /**
     * Function for fetching captcha/recovery question from the database
     *
     * @return string returns the captcha/recovery questions from the database
     */

    function fetchCaptchaPrefQuestionChoices()
    {
      $db = $this->db;
      $sql = "SELECT rowid as translation_locale_id, TRANSLATION_ID, ".
        "TRANSLATION AS questionChoices FROM TRANSLATION_LOCALE WHERE ".
        "TRANSLATION_ID IN (SELECT DISTINCT TRANSLATION_ID_QUESTION ".
        "FROM QUESTION_CHOICE_MAPPING)";
      $result = $db->execute($sql);
      $data = array();
      if($result) {
          $rows = array();
          while($row = $this->db->fetchArray($result)) {
             $data['translation_locale'][]= $row;
          }
      }
      return ($data['translation_locale']);
    }

    /**
     * Function for deleting captcha/recovery questions and their corresponding
     * choices from the database
     *
     * @param string the captcha questions text_captcha_delete_questions
     */

    function deleteCaptchaDataFromDatabase($text_captcha_delete_questions)
    {
      $db = $this->db;
      if($text_captcha_delete_questions) {
        $translation_locale_ids = implode(",", $text_captcha_delete_questions);
        /*
           Append CHOICES rowids to the set of QUESTION rowids that have to be
           deleted from TRANSLATION_LOCALE
         */
        $sql_get_choice_row_ids = "SELECT TL.ROWID AS rowid FROM ".
            "TRANSLATION_LOCALE AS TL, (SELECT QCM.TRANSLATION_ID_CHOICES ".
            "AS TRANSLATION_ID, TL.LOCALE_ID AS LOCALE_ID FROM ".
            "QUESTION_CHOICE_MAPPING AS QCM, (SELECT TRANSLATION_ID, ".
            "LOCALE_ID FROM TRANSLATION_LOCALE WHERE ROWID ".
            "IN (".$translation_locale_ids.")) AS TL WHERE ".
            "QCM.TRANSLATION_ID_QUESTION = TL.TRANSLATION_ID) AS ". 
            "CHOICES_LOCALE WHERE TL.TRANSLATION_ID = ". 
            "CHOICES_LOCALE.TRANSLATION_ID AND ".
            "TL.LOCALE_ID = CHOICES_LOCALE.LOCALE_ID";
        $result = $db->execute($sql_get_choice_row_ids);
        if($result) {
            while($row = $this->db->fetchArray($result)) {
               $translation_locale_ids .= ",".$row['rowid'];
            }
        }
        // DELETE QUESTIONS & CHOICES FROM TRANSLATION_LOCALE
        $sql_question_delete = "DELETE FROM TRANSLATION_LOCALE WHERE ".
            "ROWID IN ($translation_locale_ids)";
        $result = $db->execute($sql_question_delete);
        $purge_translation_id_arr = array();
        $sql_purge_translation_ids = "SELECT TRANSLATION_ID FROM TRANSLATION ".
            "WHERE TRANSLATION_ID NOT IN (SELECT DISTINCT TRANSLATION_ID ".
            "FROM TRANSLATION_LOCALE)";
        $result = $db->execute($sql_purge_translation_ids);
        if($result) {
            while($row = $this->db->fetchArray($result)){
               $purge_translation_id_arr[]= $row['TRANSLATION_ID'];
            }
        }
        $purge_translation_ids = implode(",", $purge_translation_id_arr);
        if($purge_translation_ids) {
            /*
               Delete from other tables if the translation_id of the row that is
               being deleted in translation_locale is not present in any other
               row (other than the row being deleted).
             */
            // DELETE FROM PREFERENCES
            $sql_delete_preferences = "DELETE FROM PREFERENCES WHERE ".
                "TRANSLATION_ID IN ($purge_translation_ids)";
            $result = $db->execute($sql_delete_preferences);
            // DELETE FROM CAPTCHA
            $sql_delete_captcha = "DELETE FROM CAPTCHA WHERE ".
                "TRANSLATION_ID IN ($purge_translation_ids)";
            $result = $db->execute($sql_delete_captcha);
            // DELETE FROM QUESTION_CHOICE_MAPPING
            $sql_delete_QCM = "DELETE FROM QUESTION_CHOICE_MAPPING WHERE ".
                "TRANSLATION_ID_QUESTION IN ($purge_translation_ids) OR ".
                "TRANSLATION_ID_CHOICES IN (".$purge_translation_ids.")";
            $result = $db->execute($sql_delete_QCM);
            // DELETE FROM TRANSLATION
            $sql_delete_translation = "DELETE FROM TRANSLATION WHERE ".
                "TRANSLATION_ID IN ($purge_translation_ids)";
            $result = $db->execute($sql_delete_translation);

        }
      }
    }

    /**
     * Function for getting the translation id for the method names
     * for the selected locale.
     * Gives the corresponding translated question-choice pair for the chosen
     * method name from the CAPTCHA/PREFERENCES table, for the locale id and
     * translation id.
     * @param a string locale_tag that is the locale tag for the chosen locale
     *
     * @return returns a boolean value
     */

    function getTranslationIdMethodNameMap($locale_tag){
        if(!$locale_tag) {
            $locale_tag = DEFAULT_LOCALE;
        }
        $db = $this->db;
        $sql = "SELECT LOCALE_ID FROM LOCALE WHERE LOCALE_TAG = ? ".
            $db->limitOffset(1);
        $result = $db->execute($sql, array($locale_tag));
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $translated_values = array();
        $sql = "SELECT TRANSLATION, TRANSLATION_ID FROM TRANSLATION_LOCALE ".
            "WHERE LOCALE_ID = $locale_id AND TRANSLATION_ID IN (SELECT ".
            "TRANSLATION_ID FROM CAPTCHA UNION SELECT TRANSLATION_ID ".
            "FROM PREFERENCES)";
        $result = $db->execute($sql);
        if(!$result) {
            return false;
        }
        while($row = $this->db->fetchArray($result)) {
             $translated_values[$row['TRANSLATION_ID']] = $row['TRANSLATION'];
        }
        $sql = "SELECT TRANSLATION_ID, METHOD_NAME, 'CAPTCHA' AS TYPE FROM ".
            "CAPTCHA UNION SELECT TRANSLATION_ID, METHOD_NAME, 'RECOVERY' ".
            "AS TYPE FROM PREFERENCES";
        $result = $db->execute($sql);
        if($result) {
            $data = array(
                'recovery_tids' => array(),
                'captcha_tids' => array(),
                'least_tids' => array(),
                'most_tids' => array(),
                'map' => array()
            );
            while($row = $this->db->fetchArray($result)) {
               $data['map'][$row['TRANSLATION_ID']] =
                   $translated_values[$row['TRANSLATION_ID']];
               if($row['TYPE'] == 'CAPTCHA') {
                   $data['captcha_tids'][] = $row['TRANSLATION_ID'];
               }
               if($row['TYPE'] == 'RECOVERY') {
                   $data['recovery_tids'][] = $row['TRANSLATION_ID'];
               }
               $least_or_most = (strpos($row['METHOD_NAME'],
                   'least') !== FALSE)? 'least_tids': 'most_tids';
               $data[$least_or_most][] = $row['TRANSLATION_ID'];
            }
            return $data;
        }
        return false;
    }

    /**
     * Function for getting the translation id map for question choices
     */

    function getQuestionChoicesMap(){
        $db = $this->db;
        $sql = "SELECT * FROM QUESTION_CHOICE_MAPPING";
        $result = $db->execute($sql);
        if($result){
            $question_choice_map = array();
            while($row = $this->db->fetchArray($result)){
                $question_choice_map[$row['TRANSLATION_ID_QUESTION']]
                    = $row['TRANSLATION_ID_CHOICES'];
            }
            return $question_choice_map;
        }
        return false;
    }

    /**
     * Function for editing the selected question choices
     * The selected question-choice pair(s) are populated in a newly created
     * textbox.
     *
     * @param string text_captcha_edit_questions, the question selected
     *        to be edited
     */

    function editCaptchaData($text_captcha_edit_questions){
        if(!$text_captcha_edit_questions){
            return;
        }
        $db = $this->db;
        $rowid_tid_map = array();
        $question_choice_tid_map = array();
        $question_choice_rowid_map = array();
        $rowid_translation_map = array();
        $tid_lid_rowid_map = array();
        $rowid_localeid_map = array();

        $translation_locale_ids = implode(",", $text_captcha_edit_questions);
        $sql = "SELECT rowid, TRANSLATION_ID, LOCALE_ID, TRANSLATION ".
            "FROM TRANSLATION_LOCALE WHERE rowid ". 
            "IN (".$translation_locale_ids.")";
        $result = $db->execute($sql);
        if($result) {
           while($row = $this->db->fetchArray($result)) {
               $rowid_tid_map[$row['rowid']] = $row['TRANSLATION_ID'];
               $rowid_translation_map[$row['rowid']] = $row['TRANSLATION'];
               $rowid_localeid_map[$row['rowid']] = $row['LOCALE_ID'];
           }
        }
        $sql = "SELECT * FROM QUESTION_CHOICE_MAPPING WHERE ".
            "TRANSLATION_ID_QUESTION in (".implode(",", $rowid_tid_map).")";
        $result = $db->execute($sql);
        if($result) {
           while($row = $this->db->fetchArray($result)) {
               $question_choice_tid_map[$row['TRANSLATION_ID_QUESTION']] =
                   $row['TRANSLATION_ID_CHOICES'];
           }
        }
        $sql = "SELECT rowid, TRANSLATION_ID, TRANSLATION, LOCALE_ID ".
            "FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID ". 
            "IN (".implode(",", $question_choice_tid_map).")";
        $result = $db->execute($sql);
        if($result) {
           while($row = $this->db->fetchArray($result)) {
               $rowid_translation_map[$row['rowid']] = $row['TRANSLATION'];
               $tid_lid_rowid_map[$row['TRANSLATION_ID']][$row['LOCALE_ID']] =
                   $row['rowid'];
           }
        }
        foreach($text_captcha_edit_questions as $question_rowid) {
            $question_locale_id = $rowid_localeid_map[$question_rowid];
            $question_tid = $rowid_tid_map[$question_rowid];
            $choices_tid = $question_choice_tid_map[$question_tid];
            $choices_rowid =
                $tid_lid_rowid_map[$choices_tid][$question_locale_id];
            $question_choice_rowid_map[$question_rowid] = $choices_rowid;
        }
        $data = array("question_choice_rowid_map" =>
            $question_choice_rowid_map, "rowid_translation_map" =>
            $rowid_translation_map);
        return $data;
    }

    /**
     * Function for updating the selected question choices
     * The selected question-choice pair(s) are updated in the database
     *
     * @param rowid_translation_map which is the map for the translation id
     *        and its corresponding rowid
     */

    function updateCaptchaStringInTranslationLocale($rowid_translation_map) {
        foreach($rowid_translation_map as $rowid => $translation) {
            $sql = "UPDATE TRANSLATION_LOCALE SET TRANSLATION = :translation ".
                "WHERE rowid = :rowid";
            $this->db->execute($sql,
                array(":translation"=>$translation, ":rowid"=>$rowid));
        }
    }
}

 ?>

