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
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * This View is responsible for drawing the login
 * screen for the admin panel of the Seek Quarry app
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class SigninView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";
    /**
     * Draws the login web page.
     *
     * @param array $data  contains the anti CSRF token
     * the view
     */
    function renderView($data)
    {
        $logged_in = isset($data['ADMIN']) && $data['ADMIN'];
        $logo = LOGO;
        if(MOBILE) {
            $logo = M_LOGO;
        }?>
        <div class="landing non-search">
        <h1 class="logo"><a href="./<?php if($logged_in) {
                e('?'.CSRF_TOKEN."=".$data[CSRF_TOKEN]);
            }?>"><img src="<?php e($logo); ?>" alt="<?php
                e($this->logo_alt_text); ?>" /></a><span> - <?php
                e(tl('signin_view_signin')); ?></span></h1>
        <?php if (isset($data['AUTH_ITERATION'])) { ?>
                <form  method="post" id="zkp-form" action="#"
                    onsubmit="generateKeys('zkp-form','username', <?php
                    ?>'password', 'fiat-shamir-modulus', '<?php
                    e($_SESSION['SALT_VALUE']); ?>', <?php 
                    e($data['AUTH_ITERATION']); ?>)" >
                <input type="hidden" name="fiat_shamir_modulus"
                    id="fiat-shamir-modulus"
                    value="<?php e($data['FIAT_SHAMIR_MODULUS']) ?>"/>
                <input type="hidden" id="salt-value" name="salt_value" />
                <input type="hidden" id="auth-message"
                    name="auth_message" value="<?php 
                    e(tl('sigin_view_signing_in')); ?>" />
                <input type="hidden" id="auth-fail-message"
                    name="auth_fail_message" value="<?php 
                    e(tl('sigin_view_login_failed')); ?>" />
        <?php } else {?>
                <form method="post" action="#">
        <?php } ?>
        <div class="login">
            <table>
            <tr>
            <td class="table-label" ><b><label for="username"><?php
                e(tl('signin_view_username')); ?></label>:</b></td><td
                    class="table-input"><input id="username" type="text"
                    class="narrow-field" maxlength="80" name="u"/>
            </td><td></td></tr>
            <tr>
            <td class="table-label" ><b><label for="password"><?php
                e(tl('signin_view_password')); ?></label>:</b></td><td
                class="table-input"><input id="password" type="password"
                class="narrow-field" maxlength="80" name="p" /></td>
            <td><input type="hidden" name="<?php e(CSRF_TOKEN);?>"
                    id="CSRF-TOKEN" value="<?php e($data[CSRF_TOKEN]); ?>" />
                <input type="hidden" name="c" value="admin" />
            </td>
            </tr>
            <tr><td>&nbsp;</td><td class="center">
            <button  type="submit" ><?php
                e(tl('signin_view_login')); ?></button>
            </td><td>&nbsp;</td></tr>
            </table>
        </div>
        </form>
        <div class="signin-exit">
            <ul>
                <?php
                if(in_array(REGISTRATION_TYPE, array('no_activation',
                    'email_registration', 'admin_activation'))) {
                    ?>
                    <li><a href="./?c=register&amp;a=recoverPassword<?php
                        if($logged_in) {
                            e('&amp;'.CSRF_TOKEN."=".$data[CSRF_TOKEN]);
                        } ?>" ><?php
                        e(tl('signin_view_recover_password')); ?></a></li>
                    <li><a href="./?c=register&amp;a=createAccount<?php
                        if($logged_in) {
                            e('&amp;'.CSRF_TOKEN."=".$data[CSRF_TOKEN]);
                        }?>"><?php
                        e(tl('signin_view_create_account')); ?></a></li>
                <?php
                }
            ?>
                <li><a href="."><?php
                    e(tl('signin_view_return_yioop')); ?></a></li>
            </ul>
        </div>
        </div>
        <div class='landing-spacer'></div>
        <?php
    }
}
?>
