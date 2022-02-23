<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details.
 *
 * @package    local_hidehiddencategories
 * @author     Jakob Heinemann <jakob@jakobheinemann.de>
 * @copyright  Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Overwrites global $PAGE object of type moodle_page
 *
 */
function local_hidehiddencategories_after_config(){
    global $PAGE;
    require_once("classes/lib/moodle_page.php");
    $PAGE = new local_hidehiddencategories_moodle_page($PAGE);
}

/**
 * Deletes invisible categories in navigation
 * Moves grand children in their positions
 * 
 * @param global_navigation $navigation
 */
function local_hidehiddencategories_extend_navigation(global_navigation $navigation) {
    if ($cats = $navigation->find_all_of_type(global_navigation::TYPE_CATEGORY)) {
        foreach($cats as $cat){
            if(!$cat->display){
                if($cat->has_children()){
                    foreach($cat->children as $child){
                        if($child->type == global_navigation::TYPE_CATEGORY){
                            $child->remove();
                            $cat->parent->add_node($child);
                        }
                    }
                    $cat->remove();
                }
            }
        }
    }
}

