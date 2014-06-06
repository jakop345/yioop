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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Element responsible for displaying info about a given crawl mix
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class EditmixElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    function render($data)
    {?>
        <div class="current-activity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=mixCrawls&amp;<?php
            e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>"
        ><?php e(tl('editmix_element_back_to_mix'))?></a>
        </div>
        <h2><?php e(tl('mixcrawls_element_edit_mix'))?></h2>
        <form id="mixForm" method="get" action=''>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="editmix" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="mix[TIMESTAMP]"
            value="<?php e($data['MIX']['TIMESTAMP']);?>" />
        <div class="top-margin"><label for="mix-name"><?php
            e(tl('mixcrawls_element_mix_name')); ?></label>
            <input type="text" id="mix-name" name="mix[NAME]"
                value="<?php if(isset($data['MIX']['NAME'])) {
                    e($data['MIX']['NAME']); } ?>" maxlength="80"
                    class="wide-field"/>
        </div>
        <h3><?php e(tl('mixcrawls_element_mix_components'))?></h3>
        <div>
        [<a href='javascript:addFragment(1, <?php
            e(MAX_MIX_FRAGMENTS);?>, <?php e('"'.
                tl('mixcrawls_element_too_many').'"');
            ?>)'><?php
            e(tl('mixcrawls_element_add_fragment')); ?></a>]
        </div>
        <div id="mix-tables" >
        </div>
        <div class="center slight-pad"><button class="button-box"
            type="submit"><?php
                e(tl('mixcrawls_element_save_button')); ?></button></div>
        </form>
        </div>
    <?php
    }
}
?>
