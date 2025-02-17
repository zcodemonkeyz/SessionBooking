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
 * Session Booking Plugin
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafa.hajjar)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\local\session\entities;

use DateTime;
use \local_booking\local\slot\entities\slot;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for a booking class.
 *
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface booking_interface {

    /**
     * Loads this booking from the database.
     *
     * @return bool
     */
    public function load();

    /**
     * Saves this booking to database.
     *
     * @return bool
     */
    public function save();

    /**
     * Deletes this booking from database.
     *
     * @return bool
     */
    public function delete();

    /**
     * Persists booking confirmation to the database.
     *
     * @param string    Confirmation message
     * @return bool
     */
    public function confirm(string $confirmationmsg);

    /**
     * Get the status of the booking Confirmed or Tentative.
     *
     * @return string
     */
    public function confirmed();

    /**
     * Returns whether the booking conflicts with another
     * for the same instructor.
     *
     * @param int   $instructorid The instructor id making the booking.
     * @param int   $studentid    The student id the booking is for.
     * @param array $slottobook   The booking start & end timestamps.
     * @return {object?}
     */
    public static function conflicts(int $instructorid, int $studentid, array $slottobook);

    /**
     * Get the booking active status.
     *
     * @return bool
     */
    public function active();

    /**
     * Get whether the student didn't show for the booked session.
     *
     * @return bool
     */
    public function noshow();

    /**
     * Deactivates a booking after the session
     * has been conducted.
     *
     * @param string    Confirmation message
     * @return bool
     */
    public function deactivate();

    /**
     * Process booking cancellation and no-shows
     *
     * @param bool $noshow      Whether the student didn't show without prior notice
     * @return bool
     */
    public function cancel(bool $noshow = false);

    /**
     * Get the booking id for the booking.
     *
     * @return int
     */
    public function get_id();

    /**
     * Get the course id for the booking.
     *
     * @return int
     */
    public function get_courseid();

    /**
     * Get the course exercise id for the booking.
     *
     * @return int
     */
    public function get_exercise_id();

    /**
     * Get the instructor user id of the booking.
     *
     * @return int
     */
    public function get_instructorid();

    /**
     * Get the instructor name of the booking.
     *
     * @return string
     */
    public function get_instructorname();

    /**
     * Get the student user id of the booking.
     *
     * @return int
     */
    public function get_studentid();

    /**
     * Get the student name of the booking.
     *
     * @return string
     */
    public function get_studentname();

    /**
     * Get the slot object of booking.
     *
     * @return slot
     */
    public function get_slot();

    /**
     * Get the date timestamp of the booking.
     *
     * @return int
     */
    public function get_bookingdate();

    /**
     * Get the last booking date associated
     * with the course and exercise id for the student.
     *
     * @param int $courseid      The associated course id
     * @param int $studentid     The student id conducted the session
     * @param int $exerciseid    The exercise id for the session
     * @return DateTime          The date of last session for that exercise
     */
    public static function get_exercise_date(int $courseid, int $studentid, int $exerciseid);

    /**
     * Return the date object of the last booking past or future, otherwise return null.
     * For instructors it would be the date the instructor made the booking.
     * For students it would be the date of the session (slot.starttime)
     *
     * @param int  $courseid      The associated course id
     * @param int  $userid        The user id for the booked session
     * @param bool $isinstructor  Whether the user is an instructor
     * @return DateTime|null      The date of last session for that exercise
    */
    public static function get_last_booked_date(int $courseid, int $userid, bool $isinstructor = false);

    /**
     * Return the date object of the last booked session that had passed, otherwise return null.
     *
     * @param int  $courseid      The associated course id
     * @param int  $userid        The user id for the booked session
     * @param bool $isinstructor  Whether the user is an instructor
     * @return DateTime|null      The date of last session for that exercise
    */
    public static function get_last_session_date(int $courseid, int $userid, bool $isinstructor = false);

    /**
     * Get the current and next participant sessions.
     * If there is no active session,
     * only the current session is returned
     *
     * @param int $courseid      The associated course
     * @param int $studentid     The student id conducted the session
     * @param bool $isinstructor Whether the user is an instructor or not
     * @param array
    */
    public static function get_recent_bookings(int $courseid, int $userid, bool $isinstructor = false);

    /**
     * Get the total sessions for a user.
     *
     * @param int $courseid     The associated course id
     * @param int $userid       The user id
     * @param int $isinstructor Whether the user is an instructor
     * @return int              The total amount of sessions conducted for the student
     */
    public static function get_total_sessions(int $courseid, int $userid, bool $isinstructor = false);
}
