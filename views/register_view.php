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
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This View is responsible for drawing the login
 * screen for the admin panel of the Seek Quarry app
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage view
 */

class RegisterView extends View
{

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     *  Draws the create account web page.
     *
     *  @param array $data  contains the anti CSRF token
     *  the view
     */
    function renderView($data) 
    {
        $logo = "resources/yioop.png";
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        ?>
        <div class="landing non-search">
            <h1 class="logo"><a href="./?<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN]) ?>"><img
                src="<?php e($logo); ?>" alt="Yioop!"/></a>
                <span> - <?php e(tl('register_view_create_account'));
                ?></span></h1>
            <form class="user_settings" method="post" action="#">
                <div class="login">
                    <table>
                        <tr>
                            <td class="table-label"><b>
                                <label for="firstname"><?php
                                    e(tl('register_view_firstname'));
                                ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <?php $first ="";
                                if(isset($data['FIRST'])) {
                                    $first = $data['FIRST'];
                                }?>
                                <input id="firstname" type="text"
                                    class="narrow-field" maxlength="80"
                                    name="first" autocomplete="off"
                                    value = "<?php e($first); ?>"/>
                            </td>
                            <td><?php echo in_array("first", $data)
                                    ?'<span class="red">*</span>':''; ?></td>
                        </tr>
                        <tr>
                            <td class="table-label"><b>
                                <label for="lastname"><?php
                                    e(tl('register_view_lastname'));
                                ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <?php $last ="";
                                if(isset($data['LAST'])) {
                                    $last = $data['LAST'];
                                }?>
                                <input id="lastname" type="text"
                                    class="narrow-field" maxlength="80"
                                    name="last" autocomplete="off"
                                    value = "<?php e($last); ?>"/>
                            </td>
                            <td><?php echo in_array("last", $data)
                                     ?'<span class="red">*</span>':'';?></td>
                        </tr>
                        <tr>
                            <td class="table-label"><b><label for="username">
                                <?php
                                e(tl('register_view_username')); ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <?php $user ="";
                                if(isset($data['USER'])) {
                                    $user = $data['USER'];
                                }?>
                                <input id="username" type="text"
                                    class="narrow-field" maxlength="80"
                                    name="user" autocomplete="off"
                                    value = "<?php e($user); ?>"/>
                            </td>
                            <td><?php echo in_array("user", $data)
                                    ?'<span class="red">*</span>':'';?></td>
                        </tr>
                        <tr>
                            <td class="table-label"><b><label for="email"><?php
                                e(tl('register_view_email')); ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <?php $email ="";
                                if(isset($data['EMAIL'])) {
                                    $email = $data['EMAIL'];
                                }?><input id="email" type="text"
                                    class="narrow-field" maxlength="80"
                                    name="email" autocomplete="off"
                                    value = "<?php e($email); ?>"/>
                            </td>
                            <td><?php echo in_array("email", $data)
                                    ? '<span class="red">*</span>':'';?></td>
                        </tr>
                        <tr>
                            <td class="table-label"><b>
                                <label for="password"><?php
                                    e(tl('register_view_password'));
                                ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <input id="password" type="password"
                                    class="narrow-field" maxlength="80"
                                    name="password"/></td>
                            <td><?php echo in_array("password", $data)
                                    ? '<span class="red">*</span>':'';?></td>
                        </tr>
                        <tr>
                            <td class="table-label"><b>
                                <label for="repassword"><?php
                                     e(tl('register_view_retypepassword'));
                                ?></label>:</b>
                            </td>
                            <td class="table-input">
                                <input id="repassword" type="password"
                                    class="narrow-field" maxlength="80"
                                    name="repassword"/></td>
                            <td><?php echo in_array("repassword", $data)
                                    ?'<span class="red">*</span>':'';?></td>
                        </tr>
                        <input type="hidden"
                                name="<?php e(CSRF_TOKEN);?>"
                                value="<?php e($data[CSRF_TOKEN]); ?>"/>
                        <tr>
                            <td></td>
                            <td class="center">
                                <button  type="submit" name="submit"
                                    value="register"><?php
                                    e(tl('register_create_account'));
                                ?></button>
                            </td>
                        </tr>
                    </table>
                </div>
            </form>
                <div class="signin-exit"><a href="."><?php
                    e(tl('signin_view_return_yioop')); ?></a>
                </div>
        </div>
            <div class='landing-spacer'></div>
    <?php
        }
    }
    ?>
