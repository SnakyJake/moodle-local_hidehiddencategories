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

//global $PAGE;
//class_alias("\\".$get_Class($PAGE),"local_hidehiddencategories\\lib\\page_parent_class");

//class local_hidehiddencategories_moodle_page extends page_parent_class{
class local_hidehiddencategories_moodle_page extends \moodle_page{
   
    public function get_course_renderer_class(){
        return get_Class(parent::get_renderer("core","course"));
    }
    public function get_course_management_renderer_class(){
        return get_Class(parent::get_renderer("core_course","management"));
    }
    
    public function get_renderer($component, $subtype = null, $target = null) {
        $renderer = parent::get_renderer($component, $subtype, $target);
        if($component === "core" && $subtype === "course"){
            $rc = new ReflectionClass(get_class($renderer));
            //method has not been overwritten by any other class/theme
            if($rc->getMethod("course_category")->class === "core_course_renderer"){
                global $CFG;
                require_once($CFG->dirroot.'/local/hidehiddencategories/classes/output/renderer.php');
                $renderer = $this->get_renderer("local_hidehiddencategories");
            }
        }
        if($component === "core_course" && $subtype === "management"){
            $rc = new ReflectionClass(get_class($renderer));
            //method has not been overwritten by any other class/theme
            if($rc->getMethod("category_listing")->class === "core_course_management_renderer"){
                global $CFG;
                require_once($CFG->dirroot.'/local/hidehiddencategories/classes/output/renderer.php');
                $renderer = $this->get_renderer("local_hidehiddencategories","management");
            }
        }
        return $renderer;
    }
}

