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

/*
 * Do not rename this file to services.php
 * it is only internally used by install.php and uninstall.php
 * 
 * These functions will be replaces (overwritten) in database table 
 * external_functions and are replaced in extenallib.php
 * 
 */
$functions = array(
    'core_course_get_categories' => array(
        'component' => 'moodle',
        'classname' => 'local_hidehiddencategories_core_course_external',
        'methodname' => 'get_categories',
        'classpath' => 'local/hidehiddencategories/externallib.php',
        'description' => 'Return category details overwritten by hidehiddencategories',
        'type' => 'read',
        'capabilities' => 'moodle/category:viewhiddencategories',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
);