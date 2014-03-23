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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/**
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/configs")));
require_once BASE_DIR.'/configs/config.php';

/* get the database library */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Get base class for profile_model.php*/
require_once BASE_DIR."/models/model.php";

/** For ProfileModel::createDatabaseTables method*/
require_once BASE_DIR."/models/profile_model.php";

/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

$profile_model = new ProfileModel(DB_NAME, false);
$db_class = ucfirst(DBMS)."Manager";

$dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME);
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db = new $db_class();
    //we deliberately set DN_NAME blank so just connect to DBMS not DB.
    $host = str_ireplace("dbname=".$dbinfo['DB_NAME'],"",
    DB_HOST); // to get rid of database from dsn postgres
    $host = str_ireplace("database=".$dbinfo['DB_NAME'],"",
        $host); // informix, ibm (use connection string DSN)
    $host = str_replace(";;",";", $host);
    $db->connect($host, DB_USER, DB_PASSWORD, "");
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
    $db->disconnect();

    $db->connect(); // default connection goes to actual DB
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
    $db = new $db_class();
    $db->connect();
}

if(!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}

$db->execute("INSERT INTO VERSION VALUES (19)");

$creation_time = microTimestamp();

//default account is root without a password
$sql ="INSERT INTO USERS VALUES (".ROOT_ID.", 'admin', 'admin','root',
        'root@dev.null', '".crawlCrypt('')."', '".ACTIVE_STATUS.
        "', '".crawlCrypt('root'.AUTH_KEY.$creation_time)."','$creation_time')";

$db->execute($sql);
// public account is an inactive account for used for public permissions
//default account is root without a password
$sql ="INSERT INTO USERS VALUES (".PUBLIC_USER_ID.", 'all', 'all','public',
        'public@dev.null', '".crawlCrypt('')."', '".INACTIVE_STATUS.
        "', '".crawlCrypt('public'.AUTH_KEY.$creation_time)."',
        '$creation_time')";
$db->execute($sql);

//default public group with group id 1
$creation_time = microTimestamp();
$sql = "INSERT INTO GROUPS VALUES(".PUBLIC_GROUP_ID.",'Public','".
    $creation_time."','".ROOT_ID."', '".PUBLIC_JOIN."', '".GROUP_READ.
    "')";
$db->execute($sql);

$now = time();
$db->execute("INSERT INTO ROLE VALUES (".ADMIN_ROLE.", 'Admin' )");
$db->execute("INSERT INTO ROLE VALUES (".USER_ROLE.", 'User' )");
$db->execute("INSERT INTO USER_ROLE VALUES (".ROOT_ID.", ".ADMIN_ROLE.")");
$db->execute("INSERT INTO USER_GROUP VALUES (".ROOT_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (".PUBLIC_USER_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$public_pages = array("404", "409", "blog", "bot", "privacy",
    "captcha_time_out", "suggest_day_exceeded", "terms");
foreach($public_pages as $page) {
    $sql = "INSERT INTO ACCESS VALUES ('".$page."', '".
        PUBLIC_GROUP_ID."', 'group')";
    $db->execute($sql);
}

/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$locales = array(
    array('en-US', 'English', 'lr-tb'),
    array('ar', 'العربية', 'rl-tb'),
    array('de', 'Deutsch', 'lr-tb'),
    array('es', 'Español', 'lr-tb'),
    array('fr-FR', 'Français', 'lr-tb'),
    array('he', 'עברית', 'rl-tb'),
    array('in-ID', 'Bahasa', 'lr-tb'),
    array('it', 'Italiano', 'lr-tb'),
    array('ja', '日本語', 'lr-tb'),
    array('ko', '한국어', 'lr-tb'),
    array('pl', 'Polski', 'lr-tb'),
    array('pt', 'Português', 'lr-tb'),
    array('ru', 'Русский', 'lr-tb'),
    array('th', 'ไทย', 'lr-tb'),
    array('vi-VN', 'Tiếng Việt', 'lr-tb'),
    array('zh-CN', '中文', 'lr-tb'),
    array('kn', 'ಕನ್ನಡ', 'lr-tb'),
    array('hi', 'हिन्दी', 'lr-tb'),
    array('tr', 'Türkçe', 'lr-tb'),
    array('fa', 'فارسی', 'rl-tb'),
    array('te',
    'తెలుగు', 'lr-tb'),
);

$i = 1;
foreach($locales as $locale) {
    $db->execute("INSERT INTO LOCALE VALUES ($i, '{$locale[0]}',
        '{$locale[1]}', '{$locale[2]}')");
    $locale_index[$locale[0]] = $i;
    $i++;
}

$activities = array(
    "manageAccount" => array('db_activity_manage_account',
        array(
            "en-US" => 'Manage Account',
            "fa" => 'مدیریت حساب',
            "fr-FR" => 'Modifier votre compte',
            "ja" => 'アカウント管理',
            "ko" => '사용자 계정 관리',
            "vi-VN" => 'Quản lý tài khoản',
            "zh-CN" => '管理帳號',
        )),
    "manageUsers" => array('db_activity_manage_users',
        array(
            "en-US" => 'Manage Users',
            "fa" => 'مدیریت کاربران',
            "fr-FR" => 'Modifier les utilisateurs',
            "ja" => 'ユーザー管理',
            "ko" => '사용자 관리',
            "vi-VN" => 'Quản lý tên sử dụng',
            "zh-CN" => '管理使用者',
        )),
    "manageRoles" => array('db_activity_manage_roles',
        array(
            "en-US" => 'Manage Roles',
            "fa" => 'مدیریت نقش‌ها',
            "fr-FR" => 'Modifier les rôles',
            "ja" => '役割管理',
            "ko" => '사용자 권한 관리',
            "vi-VN" => 'Quản lý chức vụ',
        )),
    "manageGroups" => array('db_activity_manage_groups',
        array(
            "en-US" => 'Manage Groups',
            "fr-FR" => 'Modifier les groupes',
        )),
    "manageCrawls" => array('db_activity_manage_crawl',
        array(
            "en-US" => 'Manage Crawl',
            "fa" => 'مدیریت خزش‌ها',
            "fr-FR" => 'Modifier les indexes',
            "ja" => '検索管理',
            "ko" => '크롤 관리',
            "vi-VN" => 'Quản lý sự bò',
        )),
    "groupFeeds" => array('db_activity_group_feeds',
        array(
            "en-US" => 'My Group Feeds',
        )),
    "mixCrawls" => array('db_activity_mix_crawls',
        array(
            "en-US" => 'Mix Crawls',
            "fa" => 'ترکیب‌های خزش‌ها',
            "fr-FR" => 'Mélanger les indexes',
        )),
    "manageClassifiers" => array('db_activity_manage_classifiers',
        array(
            "en-US" => 'Manage Classifiers',
            "fa" => '',
            "fr-FR" => 'Classificateurs',
        )),
    "pageOptions" => array('db_activity_file_options',
        array(
            "en-US" => 'Page Options',
            "fa" => 'تنظیمات صفحه',
            "fr-FR" => 'Options de fichier',
        )),
    "resultsEditor" => array('db_activity_results_editor',
        array(
            "en-US" => 'Results Editor',
            "fa" => 'ویرایشگر نتایج',
            "fr-FR" => 'Éditeur de résultats',
        )),
    "searchSources" => array('db_activity_search_services',
        array(
            "en-US" => 'Search Sources',
            "fa" => 'منابع جستجو',
            "fr-FR" => 'Sources de recherche',
        )),
    "manageMachines" => array('db_activity_manage_machines',
        array(
            "en-US" => 'Manage Machines',
            "fa" => 'مدیریت دستگاه‌ها',
            "fr-FR" => 'Modifier les ordinateurs',
        )),
    "manageLocales" => array('db_activity_manage_locales',
        array(
            "en-US" => 'Manage Locales',
            "fa" => 'مدیریت زبان‌ها',
            "fr-FR" => 'Modifier les lieux',
            "ja" => 'ローケル管理',
            "ko" => '로케일 관리',
            "vi-VN" => 'Quản lý miền địa phương',
        )),
    "serverSettings" => array('db_activity_server_settings',
        array(
            "en-US" => 'Server Settings',
            "fr-FR" => 'Serveurs',
        )),
    "configure" => array('db_activity_configure',
        array(
            "en-US" => 'Configure',
            "fa" => 'پیکربندی',
            "fr-FR" => 'Configurer',
            "ja" => '設定',
            "ko" => '구성',
            "vi-VN" => 'Sắp xếp hoạt động dựa theo hoạch định',
        ))
);

$i = 1;
foreach($activities as $activity => $translation_info) {
    // set-up activity
    $db->execute("INSERT INTO ACTIVITY VALUES ($i, $i, '$activity')");
    //give admin role the ability to have that activity
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".ADMIN_ROLE.", $i)");
    $db->execute("INSERT INTO TRANSLATION
            VALUES($i,'{$translation_info[0]}')");
    foreach($translation_info[1] as $locale_tag => $translation) {
        $index = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES ($i, $index,
            '$translation')");
    }
    $i++;
}

$new_user_activities = array(
    "manageAccount",
    "manageGroups",
    "mixCrawls",
    "groupFeeds"
);

foreach($new_user_activities as $new_activity) {
    $i = 1;
    foreach($activities as $key => $value) {
        if($new_activity == $key){
        //give new user role the ability to have that activity
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".
                USER_ROLE.", $i)");
        }
        $i++;
    }
}

$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
    'YouTube', 'video', 'http://www.youtube.com/watch?v={}&',
    'http://img.youtube.com/vi/{}/2.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
    'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}/',
    'http://www.metacafe.com/thumb/{}.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
    'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
    'http://www.dailymotion.com/thumbnail/video/{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634198',
    'Vimeo', 'video', 'http://player.vimeo.com/video/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634199',
    'Break.com', 'video', 'http://www.break.com/index/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634200',
    'Yahoo News', 'rss', 'http://news.yahoo.com/rss/',
    '', 'en')");

$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 'media:video site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 1, 1,
    'media:news')");

$db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
    'images','m:2',50)");
$db->execute("INSERT INTO TRANSLATION VALUES (1002,'db_subsearch_images')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 1, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 2, 'الصور' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 5, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 15, 'Hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 16, '图象
' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 20, 'تصاویر' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
    'videos','m:3',10)");
$db->execute("INSERT INTO TRANSLATION VALUES (1003,'db_subsearch_videos')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 1, 'Videos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 2, 'فيديو' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 5, 'Vidéos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 15, 'Thâu hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 16, '录影
' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 20, 'ویدیوها' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_news',
    'news','m:4',20)");
$db->execute("INSERT INTO TRANSLATION VALUES (1004,'db_subsearch_news')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 1, 'News' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 2, 'أخبار' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 5, 'Actualités' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 15, 'Tin tức' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 16, '新闻

' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 20, 'اخبار' )");

$db->disconnect();
if(in_array(DBMS, array('sqlite','sqlite3' ))){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}
echo "Create DB succeeded\n";
?>