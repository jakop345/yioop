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
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This View is responsible for drawing the
 * screen for recovering a forgotten password
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class RecoverView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     *  Draws the recover password web page and the page one get after
     *  following the recover password email
     *
     *  @param array $data  contains the anti CSRF token
     *      the view, data for captcha and recover dropdowns
     */
    function renderView($data)
    {
        $logo = "resources/yioop.png";
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        $missing = array();
        if(isset($data['MISSING'])) {
            $missing = $data['MISSING'];
        }
        $activity = (isset($data["RECOVER_COMPLETE"])) ?
            "recoverComplete" : "processRecoverData";
        ?>
        <div class="landing non-search">
        <div class="small-top">
            <h1 class="logo"><a href="./?<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN]) ?>"><img
                src="<?php e($logo); ?>" alt="Yioop!"/></a>
                <span> - <?php e(tl('recover_view_recover_password'));
                ?></span></h1>
            <form method="post" action="#">
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="<?php e($activity); ?>" />
            <?php if(isset($_SESSION["random_string"])) { ?>
            <input type='hidden' name='nonce_for_string'
            id='nonce_for_string' />
            <input type='hidden' name='random_string' id='random_string'
                value='<?php e($_SESSION["random_string"]);?>' />
            <input type='hidden' name='time1' id='time1'
                value='<?php e($_SESSION["request_time"]);?>' />
            <input type='hidden' name='level' id='level'
                value='<?php e($_SESSION["level"]);?>' />
            <input type='hidden' name='time' id='time'
                value='<?php e(time());?>'/>
            <?php
            }
            if(isset($data["RECOVER_COMPLETE"])) { ?>
                <input type="hidden" name="user" value="<?php
                    e($data['user']); ?>" />
                <input type="hidden" name="time" value="<?php
                    e($data['time']); ?>" />
                <input type="hidden" name="finish_hash" value="<?php
                    e($data['finish_hash']); ?>" />
            <?php
            }
            ?>
            <div class="register">
                <table>
                    <?php
                    if(isset($data["RECOVER_COMPLETE"])) {
                        ?>
                        <tr>
                            <th class="table-label">
                                <label for="password"><?php
                                    e(tl('register_view_password'));
                                ?></label>
                            </th>
                            <td class="table-input">
                                <input id="password" type="password"
                                    class="narrow-field" maxlength="80"
                                    name="password" value="" /></td>
                        </tr>
                        <tr>
                            <th class="table-label">
                                <label for="repassword"><?php
                                     e(tl('register_view_retypepassword'));
                                ?></label>
                            </th>
                            <td class="table-input">
                                <input id="repassword" type="password"
                                    class="narrow-field" maxlength="80"
                                    name="repassword" value="" /></td>
                        </tr>
                        <?php
                    } else {
                    ?>
                        <tr>
                        <th class="table-label"><label for="username">
                            <?php
                            e(tl('recover_view_username')); ?></label>
                        </th>
                        <td class="table-input">
                            <input id="username" type="text"
                                class="narrow-field" maxlength="80"
                                name="user" autocomplete="off"
                                value = "<?php e($data['USER']); ?>"/>
                            <?php echo in_array("user", $missing)
                                ?'<span class="red">*</span>':'';?></td>
                        </tr>
                    <?php
                    }
                    if($activity == "recoverComplete") {
                        $question_sets = array(
                            tl('register_view_account_recovery')
                                =>$data['RECOVERY']);
                    } else {
                       if(isset($_SESSION["random_string"])) {
                            $question_sets = array();
                        } else {
                            $question_sets = array(
                            tl('register_view_human_check')=>
                            $data['CAPTCHAS']);
                        }
                    }
                    if(isset($_SESSION["random_string"])) {
                        $i = 0;
                        foreach($question_sets as $name => $set) {
                            $first = true;
                            $num = count($set);
                            foreach($set as $question) {
                                if($first) { ?>
                                    <tr><th class="table-label"
                                        rowspan='<?php e($num); ?>'><?php
                                        e($name);
                                    ?></th><td class="table-input border-top">
                                <?php
                                } else { ?>
                                    <tr><td class="table-input">
                                <?php
                                }
                                $this->helper("options")->render(
                                    "question-$i", "question_$i",
                                    $question, $data["question_$i"]);
                                $first = false;
                                e(in_array("question_$i", $missing)
                                    ?'<span class="red">*</span>':'');
                                e("</td></tr>");
                                $i++;
                            }
                        }
                    }
                    ?>
                    <tr>
                        <td></td>
                        <td class="table-input border-top">
                            <input type="hidden"
                                name="<?php e(CSRF_TOKEN);?>"
                                value="<?php e($data[CSRF_TOKEN]); ?>"/>
                            <button  type="submit"><?php
                                e(tl('recover_view_recover_password'));
                            ?></button>
                        </td>
                    </tr>
                </table>
            </div>
            </form>
            <div class="signin-exit">
                <ul>
                <li><a href="."><?php
                e(tl('signin_view_return_yioop')); ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <div class='tall-landing-spacer'></div>
        <?php  if(isset($_SESSION["random_string"])) {?>
        <script type="text/javascript" >
            document.addEventListener('DOMContentLoaded', function() {
            var body = tag(body);
            body.onload = findNonce('nonce_for_string', 'random_string'
                , 'time', 'level');
            }, false);
        </script>
        <?php
        }
    }
}