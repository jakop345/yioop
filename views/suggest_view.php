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
 * View repsonsible for drawing the form where a user can suggest aURL
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */

class SuggestView extends View
{

    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     *  Draws the form where a user can suggest a url
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
        ?>
        <div class="landing non-search">
        <div class="small-top">
            <h1 class="logo"><a href="./?<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN]) ?>"><img
                src="<?php e($logo); ?>" alt="Yioop!"/></a>
                <span> - <?php e(tl('suggest_view_suggest_url'));
                ?></span></h1>
            <p class="center"><?php e(tl('suggest_view_instructions'));?></p>
            <form method="post" action="#">
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
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="suggestUrl" />
            <input type="hidden" name="arg" value="save" />
            <input type="hidden" name="build_time" value="<?php
                e($data['build_time']); ?>" />
                <div class="register">
                    <table>
                        <tr>
                            <th class="table-label"><label for="url">
                                <?php
                                e(tl('suggest_view_url')); ?></label>
                            </th>
                            <td class="table-input">
                                <input id="url" type="text"
                                    class="narrow-field" maxlength="100"
                                    name="url"
                                    value = "<?php e($data['url']); ?>"/>
                                <?php echo in_array("url", $missing)
                                    ?'<span class="red">*</span>':'';?></td>
                        </tr>
                        <tr>
                        <?php
                        if(!isset($_SESSION["random_string"])){
                        $question_sets = array(
                            tl('register_view_human_check')=>$data['CAPTCHAS']);
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
                            <?php if(isset($_SESSION["randomString"])) { ?>
                            <td class="table-input">
                            <?php } else { ?>
                               <td class="table-input border-top">
                            <?php }?>
                               <input type="hidden"
                                name="<?php e(CSRF_TOKEN);?>"
                                value="<?php e($data[CSRF_TOKEN]); ?>"/>
                                <button class="sides-margin" type="submit">
                                <?php e(tl('suggest_view_submit_url')); ?>
                                </button>
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
        <script>
            document.addEventListener('DOMContentLoaded', function() {
            var body = tag(body);
            body.onload = findNonce('nonce_for_string', 'random_string',
                'time1', 'level');
            }, false);
        </script>
        <?php
        }
    }
?>