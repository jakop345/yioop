<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014 Chris Pollett chris@pollett.org
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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element used to draw forms to set up the various external servers
 * that might be connected with a Yioop installation
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ServersettingsElement extends Element
{
    /**
     *  Method that draw forms to set up the various external servers
     * that might be connected with a Yioop installation
     *
     * @param array $data holds data on the profile elements which have been
     *      filled in as well as data about which form fields to display
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <form id="serverSettingsForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" />

        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="serverSettings" />
        <input type="hidden" name="arg" value="update" />
        <h2><?php e(tl('serversettings_element_server_settings'))?></h2>
        <div class="bold">
            <div class="top-margin">
            <fieldset><legend><?php
                e(tl('serversettings_element_name_server'))?></legend>
                <div ><b><label for="queue-fetcher-salt"><?php
                    e(tl('serversettings_element_name_server_key'))?></label></b>
                    <input type="text" id="queue-fetcher-salt" name="AUTH_KEY"
                        value="<?php e($data['AUTH_KEY']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="name-server-url"><?php
                    e(tl('serversettings_element_name_server_url'))?></label></b>
                    <input type="text" id="name-server-url" name="NAME_SERVER"
                        value="<?php e($data['NAME_SERVER']); ?>"
                        class="extra-wide-field" />
                </div>
                <?php if(class_exists("Memcache")) { ?>
                <div class="top-margin"><label for="use-memcache"><b><?php
                    e(tl('serversettings_element_use_memcache'))?></b></label>
                        <input type="checkbox" id="use-memcache"
                            name="USE_MEMCACHE" value="true" <?php
                            e($data['USE_MEMCACHE'] ? "checked='checked'" :
                                "" ); ?> />
                </div>
                <div id="memcache">
                    <div class="top-margin"><label for="memcache-servers"
                    ><b><?php e(tl('serversettings_element_memcache_servers'));
                    ?></b></label>
                    </div>
                    <textarea class="short-text-area" id="memcache-servers"
                    name="MEMCACHE_SERVERS"><?php e($data['MEMCACHE_SERVERS']);
                    ?></textarea>
                </div>
               <?php
                } ?>
                <div id="filecache">
                <div class="top-margin"><label for="use-filecache"><b><?php
                    e(tl('serversettings_element_use_filecache'))?></b></label>
                        <input type="checkbox" id="use-filecache"
                            name="USE_FILECACHE" value="true" <?php
                            e($data['USE_FILECACHE'] ? "checked='checked'" :
                                "" ); ?> />
                </div>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset class="extra-wide-field"><legend><?php
                e(tl('configure_element_database_setup'))?></legend>
                <div ><label for="database-system"><b><?php
                    e(tl('serversettings_element_database_system')); ?></b></label>
                    <?php $this->view->helper("options")->render(
                        "database-system", "DBMS",
                        $data['DBMSS'], $data['DBMS']);
                ?>
                </div>
                <div class="top-margin"><b><label for="database-name"><?php
                    e(tl('serversettings_element_databasename'))?></label></b>
                    <input type="text" id="database-name" name="DB_NAME"
                        value="<?php e($data['DB_NAME']); ?>"
                        class="wide-field" />
                </div>
                <div id="login-dbms">
                    <div class="top-margin"><b><label for="database-host"><?php
                        e(tl('serversettings_element_databasehost')); ?></label></b>
                        <input type="text" id="database-user" name="DB_HOST"
                            value="<?php e($data['DB_HOST']); ?>"
                            class="wide-field" />
                    </div>
                    <div class="top-margin"><b><label for="database-user"><?php
                        e(tl('serversettings_element_databaseuser'))?></label></b>
                        <input type="text" id="database-user" name="DB_USER"
                            value="<?php e($data['DB_USER']); ?>"
                            class="wide-field" />
                    </div>
                    <div class="top-margin"><b><label
                        for="database-password"><?php
                        e(tl('serversettings_element_databasepassword'));?></label>
                        </b> <input type="password" id="database-password"
                            name="DB_PASSWORD" value="<?php
                            e($data['DB_PASSWORD']); ?>" class="wide-field" />
                    </div>
                </div>
            </fieldset>
            </div>
            <div class = "top-margin">
            <fieldset class="extra-wide-field">
                <legend><label
                for="account-registration"><?php
                e(tl('serversettings_element_account_registration'));
                ?>
                </label></legend>
                    <?php $this->view->helper("options")->render(
                        "account-registration", "REGISTRATION_TYPE",
                        $data['REGISTRATION_TYPES'],
                        $data['REGISTRATION_TYPE']);
                ?>
                <div id="registration-info">
                <div class="top-margin"><b><label for="mail-sender"><?php
                    e(tl('serversettings_element_mail_sender'))?></label></b>
                    <input type="text" id="mail-server" name="MAIL_SENDER"
                        value="<?php e($data['MAIL_SENDER']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="use-php-mail"><?php
                    e(tl('serversettings_element_use_php_mail'))?></label></b>
                    <input type="checkbox" id="use-php-mail" name="USE_MAIL_PHP"
                        value="true" <?php if( $data['USE_MAIL_PHP']==true){
                        e("checked='checked'");}?> />
                </div>
                <div id="smtp-info">
                <div class="top-margin"><b><label for="mail-server"><?php
                    e(tl('serversettings_element_mail_server'))?></label></b>
                    <input type="text" id="mail-server" name="MAIL_SERVER"
                        value="<?php e($data['MAIL_SERVER']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-serverport"><?php
                    e(tl('serversettings_element_mail_serverport'))?></label></b>
                    <input type="text" id="mail-port" name="MAIL_SERVERPORT"
                        value="<?php e($data['MAIL_SERVERPORT']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-username"><?php
                    e(tl('serversettings_element_mail_username'))?></label></b>
                    <input type="text" id="mail-username" name="MAIL_USERNAME"
                        value="<?php e($data['MAIL_USERNAME']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-password"><?php
                    e(tl('serversettings_element_mail_password'))?></label></b>
                    <input type="password" id="mail-password"
                        name="MAIL_PASSWORD"
                        value="<?php e($data['MAIL_PASSWORD']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-security"><?php
                    e(tl('serversettings_element_mail_security'))?></label></b>
                    <input type="text" id="mail-security" name="MAIL_SECURITY"
                        value="<?php e($data['MAIL_SECURITY']); ?>"
                        class="wide-field" />
                </div>
                </div>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset><legend><?php
                e(tl('serversettings_element_proxy_title'))?></legend>
                <div ><b><label for="tor-proxies"><?php
                    e(tl('serversettings_element_tor_proxy'))?></label></b>
                    <input type="text" id="tor-proxies" name="TOR_PROXY"
                        value="<?php e($data['TOR_PROXY']); ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><label for="use-proxy"><b><?php
                    e(tl('serversettings_element_use_proxy_servers'));
                        ?></b></label>
                        <input type="checkbox" id="use-proxy"
                            name="USE_PROXY" value="true" <?php
                            e($data['USE_PROXY'] ? "checked='checked'" :
                                "" ); ?> />
                </div>
                <div id="proxy">
                    <div class="top-margin"><label for="proxy-servers"
                    ><b><?php e(tl('serversettings_element_proxy_servers'));
                    ?></b></label>
                    </div>
                    <textarea class="short-text-area" id="proxy-servers"
                    name="PROXY_SERVERS"><?php e($data['PROXY_SERVERS']);
                    ?></textarea>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset>
                <legend><label
                for="captcha-setting"><?php
                e(tl('serversettings_element_captcha_setting'));
                ?>
                </label></legend>
                    <?php $this->view->helper("options")->render(
                        "captcha-setting", "CAPTCHA_MODE",
                         $data['CAPTCHA_MODES'],
                         $data['CAPTCHA_MODE']);
                ?>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset>
                <legend><label
                for="authentication-mode"><?php
                e(tl('serversettings_element_authentication_mode'));
                ?>
                </label></legend>
                    <?php $this->view->helper("options")->render(
                        "authentication-mode", "AUTHENTICATION_MODE",
                         $data['AUTHENTICATION_MODES'],
                         $data['AUTHENTICATION_MODE']);
                ?>
            </fieldset>
            </div>
            <div class="top-margin center">
            <button class="button-box" type="submit"><?php
                e(tl('serversettings_element_submit')); ?></button>
            </div>
            </div>
        </form>
        </div>

    <?php
    }
}
?>
