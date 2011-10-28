<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * This file contains global functions connected to upgrading the database
 * and locales between different versions of Yioop!
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

 /**
 * Checks to see if the locale data of Yioop! in the work dir is older than the
 * currently running Yioop!
 */
function upgradeLocaleCheck()
{
    global $locale_tag;
    $config_name = LOCALE_DIR."/$locale_tag/configure.ini";
    $fallback_config_name = 
        FALLBACK_LOCALE_DIR."/$locale_tag/configure.ini";
    if(filemtime($fallback_config_name) > filemtime($config_name)) {
        return "locale";
    }
    return false;
}

/**
 * If the locale data of Yioop! in the work directory is older than the
 * currently running Yioop! then this function is called to at least
 * try to copy the new strings into the old profile.
 */
function upgradeLocale()
{
    global $locale;
    $locale = new LocaleModel();
    $locale->extractMergeLocales();
}

/**
 * Checks to see if the database data of Yioop! is from an older version
 * of Yioop! than the currently running Yioop!
 */
function upgradeDatabaseCheck()
{
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";

    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if($row['ID'] == 1) {
            return false;
        }
    }
    return true;
}

/**
 * If the database data of Yioop!  is older than the version of the
 * currently running Yioop! then this function is called to try
 * upgrade the database to the new version
 */
function upgradeDatabase()
{
    $versions = array(0, 1, 2);
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";
    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if(isset($row['ID']) && in_array($row['ID'], $versions)) {
            $current_version = $row['ID'];
        } else {
            $current_version = 0;
        }
    } else {
        $current_version = 0;
    }
    $key = array_search($current_version, $versions);
    $versions = array_slice($versions, $current_version + 1);
    foreach($versions as $version) {
        $upgradeDB = "upgradeDatabaseVersion$version";
        $upgradeDB($model->db);
    }
}

/**
 * Upgrades a Version 0 version of the Yioop! database to a Version 1 version
 * @param resource $db database handle to use to upgrade 
 */
function upgradeDatabaseVersion1($db)
{
    $db->execute("CREATE TABLE VERSION( ID INTEGER PRIMARY KEY)");
    $db->execute("INSERT INTO VERSION VALUES (1)");
    $db->execute("CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
        "SESSION VARCHAR(4096))");
}

/**
 * Upgrades a Version 1 version of the Yioop! database to a Version 2 version
 * @param resource $db database handle to use to upgrade 
 */
function upgradeDatabaseVersion2($db)
{
    $db->execute("DELETE FROM VERSION;");
    $db->execute("INSERT INTO VERSION VALUES (2)");
    $db->execute("ALTER TABLE USER ADD UNIQUE ( USER_NAME )" );
    $db->execute("INSERT INTO LOCALE VALUES (17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
    $db->execute("INSERT INTO LOCALE VALUES (18, 'hi', 'हिन्दी', 'lr-tb')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5, 
        'Modifier les rôles')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5, 
        'Modifier les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5, 
        'Mélanger les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5, 
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5, 
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5, 
        'Configurer')");

}
?>
