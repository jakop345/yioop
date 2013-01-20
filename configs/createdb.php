<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/** Calculate base directory of script
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

$profile_model = new ProfileModel();
$db_class = ucfirst(DBMS)."Manager";
$db = new $db_class();
$db->connect();

$dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_NAME" => DB_NAME,
    "DB_PASSWORD" => DB_PASSWORD);
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
}
$db->selectDB(DB_NAME);

if(!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}

$db->execute("INSERT INTO VERSION VALUES (15)");

//default account is root without a password
$sql ="INSERT INTO USER VALUES (1, 'root', '".crawlCrypt('')."' ) ";
$db->execute($sql);

/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$db->execute("INSERT INTO LOCALE VALUES (1, 'en-US', 'English', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (2, 'ar', 'العربية', 'rl-tb')");
$db->execute("INSERT INTO LOCALE VALUES (3, 'de', 'Deutsch', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (4, 'es', 'Español', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (5, 'fr-FR', 'Français', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (6, 'he', 'עברית', 'rl-tb')");
$db->execute("INSERT INTO LOCALE VALUES (7, 'in-ID', 'Bahasa', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (8, 'it', 'Italiano', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (9, 'ja', '日本語', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (10, 'ko', '한국어', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (11, 'pl', 'Polski', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (12, 'pt', 'Português', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (13, 'ru', 'Русский', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (14, 'th', 'ไทย', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (
    15, 'vi-VN', 'Tiếng Việt', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (16, 'zh-CN', '中文', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (
    17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (
    18, 'hi', 'हिन्दी', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (19, 'tr', 'Türkçe', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (20, 'fa', 'فارسی', 'rl-tb')");
$db->execute("INSERT INTO LOCALE VALUES (21, 'te', 'తెలుగు', 'lr-tb')");

$sql ="INSERT INTO ROLE VALUES (1, 'Admin' ) ";
$db->execute($sql);

$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 1)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 2)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 3)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 4)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 5)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 6)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 7)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 8)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 9)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 10)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 11)");

$db->execute("INSERT INTO ACTIVITY VALUES (1, 1, 'manageAccount')");
$db->execute("INSERT INTO ACTIVITY VALUES (2, 2, 'manageUsers')");
$db->execute("INSERT INTO ACTIVITY VALUES (3, 3, 'manageRoles')");
$db->execute("INSERT INTO ACTIVITY VALUES (4, 4, 'manageCrawls')");
$db->execute("INSERT INTO ACTIVITY VALUES (5, 5, 'mixCrawls')");
$db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'pageOptions')");
$db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'resultsEditor')");
$db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'searchSources')");
$db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageMachines')");
$db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'manageLocales')");
$db->execute("INSERT INTO ACTIVITY VALUES (11, 11, 'configure')");

$db->execute("INSERT INTO TRANSLATION VALUES (1,'db_activity_manage_account')");
$db->execute("INSERT INTO TRANSLATION VALUES (2, 'db_activity_manage_users')");
$db->execute("INSERT INTO TRANSLATION VALUES (3, 'db_activity_manage_roles')");
$db->execute("INSERT INTO TRANSLATION VALUES (4, 'db_activity_manage_crawl')");
$db->execute("INSERT INTO TRANSLATION VALUES (5, 'db_activity_mix_crawls')");
$db->execute("INSERT INTO TRANSLATION VALUES (6, 'db_activity_file_options')");
$db->execute("INSERT INTO TRANSLATION VALUES (7,'db_activity_results_editor')");
$db->execute("INSERT INTO TRANSLATION VALUES(8,'db_activity_search_services')");
$db->execute("INSERT INTO TRANSLATION VALUES(9,'db_activity_manage_machines')");
$db->execute("INSERT INTO TRANSLATION VALUES (10,
    'db_activity_manage_locales')");
$db->execute("INSERT INTO TRANSLATION VALUES (11, 'db_activity_configure')");


$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 1, 'Manage Account' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 1, 'Manage Users')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 1, 'Manage Roles')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 1, 'Manage Crawls')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 1, 'Mix Crawls')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Page Options')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Results Editor')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 1, 'Search Sources')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 1, 'Manage Machines')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 1, 'Manage Locales')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 1, 'Configure')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 5,
    'Modifier votre compte' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 5,
    'Modifier les utilisateurs')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5,
    'Modifier les rôles')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5,
    'Modifier les indexes')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5,
    'Mélanger les indexes')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5,
    'Options de fichier')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
    'Éditeur de résultats')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
    'Sources de recherche')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5,
    'Modifier les ordinateurs')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5,
    'Modifier les lieux')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 5,
    'Configurer')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    1, 9, 'アカウント管理' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    2, 9, 'ユーザー管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    3, 9, '役割管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    4, 9, '検索管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    10, 9, 'ローケル管理')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    11, 9, '設定')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 10,
    '사용자 계정 관리' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    2, 10, '사용자 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 10,
    '사용자 권한 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    4, 10, '크롤 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    10, 10, '로케일 관리')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    11, 10, '구성')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 15,
    'Quản lý tài khoản' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 15,
    'Quản lý tên sử dụng')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 15,
    'Quản lý chức vụ')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
    4, 15, 'Quản lý sự bò')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15,
    'Quản lý miền địa phương')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 15,
    'Sắp xếp hoạt động dựa theo hoạch định')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 16,
    '管理帳號')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 16,
    '管理使用者')");


$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 20,
     'مدیریت حساب' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 20,
    'مدیریت کاربران')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 20,
    'مدیریت نقش‌ها')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 20,
'مدیریت خزش‌ها')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 20,
    'ترکیب‌های خزش‌ها')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 20,
    'تنظیمات صفحه')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 20,
    'ویرایشگر نتایج')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 20,
'منابع جستجو')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 20,
    'مدیریت دستگاه‌ها')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 20,
    'مدیریت زبان‌ها')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 20,
    'پیکربندی')");

$db->execute("INSERT INTO USER_ROLE VALUES (1, 1)");

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

$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images')");
$db->execute("INSERT INTO MIX_GROUPS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos')");
$db->execute("INSERT INTO MIX_GROUPS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 'media:video site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news')");
$db->execute("INSERT INTO MIX_GROUPS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 1, 1,
    'media:news no:cache')");

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
