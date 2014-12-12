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
 * @author Pushkar Umaranikar (edited Chris Pollett)
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2014
 * @filesource
 */
/**
 * This script shows or blocks the textarea for including ad script on respective 
 * location depending upon the ad location that user has selected.
 */
window.onload = function()
{
    showHideScriptdiv();
}
/**
 * Method to show/block div including text area depending upon location selected for 
 * the advertisement to display on search results page.
 */
function showHideScriptdiv()
{
     /*
      * Get the radio button list represnting location for the 
      * advertisement.
     */
    var adserverconfig=document.getElementsByName('AD_LOCATION');
    /*
     * Show/ block div with text area depending upon the radio
     * button value.
     */
      var adAlign = [
      ['block','block','none'],//top[top,global,side]
      ['none','block','block'],//side
      ['block','block','block'],//both
      ['none','none','none'],//none
     ]
      for(var i = 0;i < adserverconfig.length;i++){
        if(adserverconfig[i].checked){
            elt('top-adscript-config').style.display = adAlign[i][0];
            elt('global-adscript-config').style.display = adAlign[i][1];
            elt('side-adscript-config').style.display = adAlign[i][2];
            break;
        }
      }
}
