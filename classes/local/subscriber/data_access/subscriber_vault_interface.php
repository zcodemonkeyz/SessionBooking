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
 * slot vault interface
 *
 * @package    local_booking
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace local_booking\local\subscriber\data_access;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for the subscriber_vault class
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafa.hajjar)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface subscriber_vault_interface {

    /**
     * Get a based on its id
     *
     * @param int   $courseid The course id
     * @return bool           Whether the course is subscribed or not
     */
    public static function is_course_enabled(int $courseid);

    /**
     * Checks the stats table to check if the subscribed course has any student status or not.
     *
     * @param int   $courseid The course id
     * @return bool Whether the course is subscribed or not
     */
    public static function student_progress_exists(int $courseid);

    /**
     * Get a based on its id
     *
     * @param int   $courseid The course id
     * @return bool           Whether the course is subscribed or not
     */
    public static function add_student_progress(int $courseid);

    /**
     * Removes user stats data once student is unenroled from the course
     *
     * @param int $courseid The course id
     * @param int $userid   The assign module id
     * @return bool
     */
    public static function delete_student_progress(int $courseid, int $userid);
}
