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
 * Class for data access of course participants
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafa.hajjar)
 * @copyright  BAVirtual.co.uk © 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\local\participant\data_access;

use DateTime;

require_once($CFG->dirroot . "/lib/completionlib.php");

class participant_vault implements participant_vault_interface {

    // user tables
    const DB_USER = 'user';
    const DB_ROLE = 'role';
    const DB_ROLE_ASSIGN = 'role_assignments';
    const DB_GROUPS = 'groups';
    const DB_GROUPS_MEM = 'groups_members';

    // enrolment tables
    const DB_USER_ENROL = 'user_enrolments';
    const DB_ENROL = 'enrol';

    // course module tables
    const DB_MODULES = 'modules';
    const DB_COURSE_SECTIONS = 'course_sections';
    const DB_COURSE_MODS = 'course_modules';
    const DB_COURSE_COMP = 'course_completions';
    const DB_COURSE_MODS_COMP = 'course_modules_completion';

    // course assignment tables
    const DB_ASSIGN = 'assign';
    const DB_ASSIGN_GRADES = 'assign_grades';
    const DB_LESSON_TIMER = 'lesson_timer';

    // session booking tables
    const DB_BOOKING = 'local_booking_sessions';
    const DB_SLOTS = 'local_booking_slots';
    const DB_STATS = 'local_booking_stats';

    /**
     * Get a participant from the database.
     *
     * @param int  $courseid The course id.
     * @param int  $userid   A specific user.
     * @param bool $active   Whether the user is actively enrolled.
     * @param bool $student  Whether the user is a student or not
     * @return {Object}      Array of database records.
     */
    public static function get_participant(int $courseid, int $userid = 0, bool $active = true, bool $student = true) {
        global $DB;

        $statssql = $student ? 's.lessonscomplete, s.lastsessiondate, s.currentexerciseid, s.nextexerciseid,
            IF(MAX(a.starttime) > UNIX_TIMESTAMP(), 1, 0) AS hasactiveposts, scc.timecompleted AS graduateddate,' :
            '0 AS lessonscomplete, 0 AS lastsessiondate, 0 AS currentexerciseid, 0 AS nextexerciseid, 0 AS hasactiveposts, 0 AS graduateddate';
        $conditionaljoin = $student ? 'INNER JOIN {' . self::DB_STATS . '} s ON s.userid = u.id LEFT JOIN {' . self::DB_COURSE_COMP . '} cc ON cc.userid = u.id AND cc.course = en.courseid' : '';
        $statsclause = $student ? ' AND s.courseid = :scourseid AND cc.timecompleted IS NULL' : '';
        $activeclause = $active ? ' AND ue.status = 0' : '';

        $sql = "SELECT u.id AS userid, " . $DB->sql_concat('u.firstname', '" "', 'u.lastname', '" "', 'u.alternatename') . " AS fullname,
                    MAX(ue.timecreated) AS enroldate, $statssql, ue.timemodified AS suspenddate, ue.status AS enrolstatus,
                    en.courseid AS courseid, u.lastlogin AS lastlogin,
                        (SELECT GROUP_CONCAT(shortname) FROM {" . self::DB_ROLE_ASSIGN . "} ra
                         INNER JOIN {" . self::DB_ROLE . "}  r ON r.id = ra.roleid
                         WHERE ra.userid = :ruserid AND ra.contextid = :contextid) AS roles
                FROM {" . self::DB_USER . "} u
                $conditionaljoin
                INNER JOIN {" . self::DB_USER_ENROL . "} ue on u.id = ue.userid
                INNER JOIN {" . self::DB_ENROL . "} en on ue.enrolid = en.id
                LEFT JOIN {" . self::DB_SLOTS . "} a ON a.userid = u.id AND a.courseid = en.courseid
                WHERE en.courseid = :courseid $statsclause
                    AND u.id = :userid $activeclause";

        $params = [
            'courseid'  => $courseid,
            'scourseid' => $courseid,
            'userid'    => $userid,
            'ruserid'   => $userid,
            'contextid' => \context_course::instance($courseid)->id
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get all active student from the database.
     *
     * @param int  $courseid    The course id.
     * @param bool $userid      A specific student for booking confirmation
     * @return {Object}         Array of database records.
     */
    public static function get_student(int $courseid, int $userid = 0) {
        global $DB;

        list($sql, $countsql) = self::get_criteria_sql($courseid, 'active', true, false, 'student', false, true);
        $params = [
            'userid'    => $userid,
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get all active students from the database.
     *
     * @param int $courseid         The course id.
     * @param string $filter        The filter to show students, inactive (including graduates), suspended, and default to active.
     * @param bool $includeonhold   Whether to include on-hold students as well
     * @param int $offset           The offset record for pagination
     * @param bool &$count          Reference to the count of students count before pagination
     * @param bool $requirescompletion Whether the course has lesson completion restriction
     * @return {Object}[]           Array of database records.
     */
    public static function get_students(
        int $courseid,
        string $filter = 'active',
        bool $includeonhold = false,
        int $offset = 0,
        int &$count = 0,
        bool $requirescompletion = true) {

        global $DB;

        list($sql, $countsql) = self::get_criteria_sql($courseid, $filter, $includeonhold, false, 'student', $requirescompletion);

        // get filtered students and their total count
        $count = $DB->count_records_sql($countsql);
        $students = $DB->get_records_sql($sql, null, $offset, LOCAL_BOOKING_DASHBOARDPAGESIZE);

        return $students;
    }

    /**
     * Get all active participants for a course for UI select controls (ids & fullname)
     *
     * @param int $courseid         The course id.
     * @param string $filter        The filter to show students, inactive (including graduates), suspended, and default to active.
     * @param bool $includeonhold   Whether to include on-hold students as well
     * @param string $roles         The roles of the participants
     * @return {Object}[]           Array of database records.
     */
    public static function get_participants_simple(int $courseid, string $filter = 'active', bool $includeonhold = false, string $roles = null) {
        global $DB;

        // $context = \context_course::instance($courseid);
        // list($enrolledsql, $enrolledparams) = get_enrolled_sql($context, 'local/booking:availabilityview', 0, true);

        // // Fields we need from the user table.
        // $userfieldsapi = \core_user\fields::for_identity($context)->with_userpic();
        // $userfieldssql = $userfieldsapi->get_sql('u', true, '', '', false);

        // // We want to query both the current context and parent contexts.
        // list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');


        // $params = array_merge($userfieldssql->params, $enrolledparams, $relatedctxparams);
        // $sql = "SELECT {$userfieldssql->selects}
        //         FROM {user} u
        //             {$userfieldssql->joins}
        //         JOIN ($enrolledsql) je ON je.id = u.id";

        list($sql, $countsql) = self::get_criteria_sql($courseid, $filter, true, true, $roles);

        return $DB->get_records_sql($sql);
    }

    /**
     * Get all active instructors for the course from the database.
     *
     * @param int $courseid The course id.
     * @param bool $courseadmins Indicates whether the instructor is an admin or not.
     * @return {Object}[]   Array of database records.
     */
    public static function get_instructors(int $courseid, bool $courseadmins = false) {
        global $DB;

        $roles = $courseadmins ?  LOCAL_BOOKING_SENIORINSTRUCTORROLE . '|' . LOCAL_BOOKING_FLIGHTTRAININGMANAGERROLE : LOCAL_BOOKING_INSTRUCTORROLE;

        list($sql, $countsql) = self::get_criteria_sql($courseid,'active', false, false, $roles);

        $instructors = $DB->get_records_sql($sql);
        return $instructors;
    }

    /**
     * Returns full username
     *
     * @param int       $userid           The user id.
     * @param bool      $includealternate Whether to include the user's alternate name.
     * @return string   $fullusername     The full participant username
     */
    public static function get_participant_name(int $userid, bool $includealternate = true) {
        global $DB;

        $fullusername = '';
        if ($userid != 0) {
            // Get the full user name
            $sql = 'SELECT ' . $DB->sql_concat('u.firstname', '" "',
                        'u.lastname', '" "', 'u.alternatename') . ' AS bavname, '
                        . $DB->sql_concat('u.firstname', '" "',
                        'u.lastname') . ' AS username
                    FROM {' . self::DB_USER . '} u
                    WHERE u.id = :userid';

            $param = ['userid'=>$userid];
            $userinfo = $DB->get_record_sql($sql ,$param);

            if (!empty($userinfo)) {
                $fullusername = $includealternate ? $userinfo->bavname : $userinfo->username;
            }
        }



        return $fullusername;
    }

    /**
     * Get student's enrolment date.
     *
     * @param int       $userid     The student user id in reference
     * @return DateTime $enroldate  The enrolment date of the student.
     */
    public function get_enrol_date(int $courseid, int $userid) {
        global $DB;

        $sql = 'SELECT ue.timecreated AS enroldate, ue.timemodified AS suspenddate
                FROM {' . self::DB_USER_ENROL . '} ue
                INNER JOIN {' . self::DB_ENROL . '} e ON e.id = ue.enrolid
                WHERE e.courseid = :courseid
                    AND ue.userid = :userid
                ORDER BY ue.timecreated DESC LIMIT 1';

        $params = [
            'courseid' => $courseid,
            'userid'  => $userid
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Returns the timestamp of the last
     * graded session.
     *
     * @param   int The user id
     * @param   int The course id
     * @return  stdClass The record containing timestamp of the last grading
     */
    public function get_last_graded_date(int $userid, int $courseid, bool $is_student) {
        global $DB;

        // parameter for the grades being retrieved: the student graded by instructor or grader grades
        $usertypesql = $is_student ? 'grader != -1 AND userid' : 'grader';
        // Get the student's grades
        $sql = 'SELECT timemodified
                FROM {' . self::DB_ASSIGN_GRADES . '} ag
                INNER JOIN {' . self::DB_COURSE_MODS . '} cm ON cm.instance = ag.assignment
                WHERE cm.course = :courseid
                AND cm.deletioninprogress = 0
                AND ' . $usertypesql . ' = :userid
                AND ag.timemodified > ' . (time() - LOCAL_BOOKING_PASTDATACUTOFFDAYS) . '
                ORDER BY timemodified DESC
                LIMIT 1';

        $params = [
            'courseid' => $courseid,
            'userid'  => $userid
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Returns the list of completed lesson ids
     * for a student in a course.
     *
     * @param   int     The student user id
     * @param   int     The course id
     * @return  array   List of completed lesson ids
     */
    public function get_student_completed_lesson_ids(int $userid, int $courseid) {
        global $DB;

        $sql = "SELECT GROUP_CONCAT(cm.id ORDER BY cs.section, LOCATE(cm.id, cs.sequence))
                FROM {" . self::DB_COURSE_MODS_COMP . "} cmc
                INNER JOIN {" . self::DB_COURSE_MODS . "} cm ON cm.id = cmc.coursemoduleid
                INNER JOIN {" . self::DB_MODULES . "} m ON m.id = cm.module
                INNER JOIN {" . self::DB_COURSE_SECTIONS . "} cs ON cs.id = cm.section
                WHERE
                    cmc.userid = :userid AND
                    cm.course = :courseid AND
                    cmc.completionstate >= " . COMPLETION_COMPLETE . " AND
                    m.name = 'lesson'";

        $params = [
            'userid'  => $userid,
            'courseid' => $courseid
        ];

        return $DB->get_record_sql($sql, $params);

    }

    /**
     * Returns the list of incomplete lessons for a student
     * prior to the upcoming next exercise.
     *
     * @param   int     The student user id
     * @param   int     The course id
     * @param   int     The next exercise id
     * @return  array   List of incomplete lesson mod ids
     */
    public function get_student_incomplete_lesson_ids(int $userid, int $courseid, int $nextexercise) {
        global $DB;

        $nextexercisesection = self::get_course_next_exercise_section($nextexercise);

        // get the student's grades
        $sql = 'SELECT cm.id, cm.course, cm.module, cm.instance, cs.section, cs.sequence
                FROM {' . self::DB_COURSE_MODS .'} cm
                INNER JOIN {' . self::DB_COURSE_SECTIONS . '} cs ON cs.id = cm.section
                INNER JOIN {' . self::DB_MODULES . '} as m ON m.id = cm.module
                WHERE cm.course = :courseid
                AND cm.deletioninprogress = 0
                AND cs.section <= :nextexercisesection
                AND m.name = "lesson"
                AND cm.instance NOT IN (SELECT lt.lessonid
                    FROM {' . self::DB_LESSON_TIMER . '} lt
                    WHERE lt.userid = :userid
                    AND lt.completed < ' . COMPLETION_COMPLETE .')
                ORDER BY cs.section ASC';

        $params = [
            'courseid' => $courseid,
            'userid'  => $userid,
            'nextexercisesection'  => $nextexercisesection->section,
        ];

        $lessonsnotcompleted = $DB->get_records_sql($sql, $params);

        // check the sequence for lessons with multiple assignments to make sure that
        // only lesson modules prior to the completed exercise are evaluated for completion
        $incompletesequence = [];
        if (!empty($lessonsnotcompleted)) {
            $incompletesequence = explode(',', array_values($lessonsnotcompleted)[0]->sequence);
            // check if any of the lesson modules incomplete are in the list prior to the next exercise
            $priortonext = array_slice($incompletesequence, 0, array_search($nextexercise, $incompletesequence));
            $incompletesequence = array_intersect($priortonext, array_keys($lessonsnotcompleted));
        }

        return $incompletesequence;
    }

    /**
     * Returns the section containing the next exercise.
     *
     * @return  stClass   Section
     */
    private static function get_course_next_exercise_section(int $nextexercise) {
        global $DB;

        // get the section containing the next exercise
        $sql = 'SELECT cs.section
                FROM {' . self::DB_COURSE_SECTIONS . '} cs
                INNER JOIN {' . self::DB_COURSE_MODS .'} cm ON cm.section = cs.id
                WHERE cm.id = :exerciseid
                    AND cm.deletioninprogress = 0';

        return $DB->get_record_sql($sql, ['exerciseid'=>$nextexercise]);
    }

    /**
     * Updates a user's profile field with a value
     *
     * @param   int    $userid  The student user id
     * @param   string $field   The field to be updated
     * @param   mixed  $value   The value to update to
     * @return  bool            Whether the comment was updated or not.
     */
    public function update_participant_field(int $userid, string $field, $value) {
        global $DB;

        return $DB->set_field('user', $field, $value, array('id' => $userid));
    }

    /**
     * Suspends the student's enrolment to a course.
     *
     * @param int   $courseid   The course the student is being unenrolled from.
     * @param int   $userid     The student user id in reference
     * @param int   $status     The status of the enrolment suspended = 1
     * @return bool             The result of the suspension action.
     */
    public function suspend(int $courseid, int $userid, int $status) {
        global $DB, $USER;

        $sql = 'UPDATE {' . static::DB_USER_ENROL . '} ue
                INNER JOIN {' . static::DB_ENROL . '} e ON e.id = ue.enrolid
                SET ue.status = :status, ue.timemodified = UNIX_TIMESTAMP(), ue.modifierid = ' . $USER->id . '
                WHERE e.courseid = :courseid
                    AND ue.userid = :userid';

        $params = [
            'courseid' => $courseid,
            'userid'  => $userid,
            'status'  => $status
        ];

        return $DB->execute($sql, $params);
    }

    /**
     * Get all active students from the database.
     *
     * @param  string $filter             The filter to show students, inactive (including graduates), suspended, and default to active.
     * @param  bool   $includeonhold      Whether to include on-hold students as well
     * @param  bool   $simple             To return sql for html Select user ids & names only
     * @param  ?      $roles              The roles of the participants
     * @param  bool   $requirescompletion Whether the course has lesson completion restriction
     * @param  bool   $byuserid           Whether to include a userid criteria
     * @return array  [$sql, $countsql]   The query SQL string
     */
    private static function get_criteria_sql(
        int $courseid,
        string $filter = 'active',
        bool $includeonhold = false,
        bool $simple = false,
        $roles = null,
        bool $requirescompletion = true,
        bool $byuserid = false) {

        global $DB;

        $context = \context_course::instance($courseid);
        $isstudent = !empty($roles) && $roles == 'student';
        $filter = $byuserid ? 'any' : $filter;

        // outer select statement
        $select = 'SELECT * FROM ';
        if ($simple) {
            // Fields we need from the user table.
            $userfieldsapi = \core_user\fields::for_identity($context)->with_userpic();
            $userfieldssql = $userfieldsapi->get_sql('u', true, '', '', false);
            $select = "SELECT $userfieldssql->selects FROM {" . self::DB_USER . "} u JOIN ";
        }

        // inner select fields statement
        $innerselect = '(SELECT u.id AS userid' . ($simple ? '' : ', ' . $DB->sql_concat('u.firstname', '" "', 'u.lastname', '" "', 'u.alternatename') . ' AS fullname');
        $innerselect .= !empty($roles) ? ", (SELECT GROUP_CONCAT(shortname) FROM {" . self::DB_ROLE_ASSIGN . "} ra
                         INNER JOIN {" . self::DB_ROLE . "}  r ON r.id = ra.roleid
                         WHERE ra.userid = u.id AND ra.contextid = $context->id) AS roles" : "";
        $innerselectcount = $innerselect;

        if (!$simple) {
            $innerselect .= $isstudent ? ', s.lessonscomplete,
                            s.lastsessiondate,
                            s.currentexerciseid,
                            s.nextexerciseid,
                            IF(s.lastsessiondate IS NULL OR s.lastsessiondate = 0, ue.timecreated, s.lastsessiondate) AS waitdate,
                            cc.timecompleted AS graduateddate,
                            @hasbooking := (SELECT b.active FROM {' . self::DB_STATS . '} st LEFT OUTER JOIN {' . self::DB_BOOKING . '} b ON b.studentid = st.userid
                                AND b.courseid = st.courseid WHERE b.studentid = u.id AND b.courseid = en.courseid ORDER BY b.id DESC LIMIT 1) AS booked,
                            IF((SELECT MAX(a.starttime) FROM {' . self::DB_SLOTS . '} a WHERE a.userid = u.id AND a.courseid = en.courseid) > UNIX_TIMESTAMP(), IF(@hasbooking=1, 0, 1), 0) AS hasactiveposts'
                :
                ', @maxbooktime := (SELECT MAX(b.timemodified) FROM mdl_local_booking_sessions b WHERE b.userid = u.id AND b.courseid = en.courseid),
                            @maxlogentrytime := (SELECT MAX(l.flightdate) FROM mdl_local_booking_logbooks l WHERE l.userid = u.id AND l.courseid = en.courseid),
    	                    IF(@maxlogentrytime > @maxbooktime, @maxlogentrytime, @maxbooktime) AS lastsessiondate';
            $innerselect .= ', en.courseid AS courseid, u.lastlogin AS lastlogin, ue.timecreated AS enroldate, ue.timemodified AS suspenddate, ue.status AS enrolstatus';
        }

        // inner selectfrom tables statement
        $innerfrom = ' FROM {' . self::DB_USER . '} u';
        $innerfrom .= ' INNER JOIN {' . self::DB_USER_ENROL . '} ue ON ue.userid = u.id' .
                 ' INNER JOIN {' . self::DB_ENROL . '} en ON en.id = ue.enrolid' .
                   ($isstudent && !$simple ? ' LEFT OUTER JOIN {' . self::DB_STATS . '} s ON s.userid = u.id AND s.courseid = en.courseid' : '');
                   $innerfrom .= $isstudent ? ' LEFT JOIN {' . self::DB_COURSE_COMP . '} cc ON cc.userid = u.id AND cc.course = en.courseid' : '';

        // inner select where statement
        $innerwhere = " WHERE en.courseid = $courseid AND u.deleted != 1 AND u.suspended = 0 ";

        // outer select where statement
        $outerwhere = '';
        if (!empty($roles) || $byuserid) {
            $outerwhere = !empty($roles) ? "roles " . ($isstudent ? "like '%$roles%'" : "REGEXP '$roles'") : "";
            $outerwhere .= !empty($outerwhere) && $byuserid ? " AND " : "";
            $outerwhere .= $byuserid ? "userid = :userid " : "" ;
            $outerwhere = !empty($outerwhere) ? " WHERE " . $outerwhere : "";
        }

        // outer select order by statement
        if (!$simple) {
            $orderby = $byuserid ? '' : ' ORDER BY ' . ($isstudent ? ($requirescompletion ? 'lessonscomplete DESC,' : '') . ' hasactiveposts DESC, booked DESC, waitdate ASC' : 'lastsessiondate DESC');
        }

        // inner select group by statement
        $innergroupby = ' GROUP BY u.id)  participants' . ($simple ? ' ON userid = u.id' : '');

        switch ($filter) {
            // cross reference course completion and on-hold group
            case 'active':
                $excludeonhold = " AND u.id NOT IN (
                            SELECT userid
                            FROM {" . self::DB_GROUPS_MEM . "} gm
                            INNER JOIN {" . self::DB_GROUPS . "} g on g.id = gm.groupid
                            WHERE g.courseid = $courseid AND g.name = '" . ($isstudent ? LOCAL_BOOKING_ONHOLDGROUP : LOCAL_BOOKING_INACTIVEGROUP) . "')";
                $innerwhere .= $includeonhold ? '' : $excludeonhold;
                $innerwhere .= ' AND ue.status = 0' . ($isstudent ? ' AND cc.timecompleted IS NULL' : '');
                break;

            case 'onhold':
                $innerwhere .= " AND ue.status = 0 AND cc.timecompleted IS NULL
                    AND u.id IN (
                        SELECT userid
                        FROM {" . self::DB_GROUPS_MEM . "} gm
                        INNER JOIN {" . self::DB_GROUPS . "} g on g.id = gm.groupid
                        WHERE g.courseid = $courseid AND g.name = '" . LOCAL_BOOKING_ONHOLDGROUP . "')";
                break;

            case 'suspended':
                $innerwhere .= ' AND ue.status = 1';
                $orderby = ' ORDER BY suspenddate, userid DESC';
                break;

            case 'graduates':
                $innerwhere .= " AND ue.status = 0 AND ( u.id IN (
                            SELECT userid
                            FROM {" . self::DB_GROUPS_MEM . "} gm
                            INNER JOIN {" . self::DB_GROUPS . "} g on g.id = gm.groupid
                            WHERE g.courseid = $courseid AND g.name = '" . LOCAL_BOOKING_GRADUATESGROUP . "')
                            OR cc.timecompleted IS NOT NULL)";
                break;

            case 'any':
                break;
        }

        $sql = $select . $innerselect . $innerfrom . $innerwhere . $innergroupby . $outerwhere . $orderby;

        $countsql = 'SELECT Count(userid) FROM ' . $innerselectcount . $innerfrom . $innerwhere  . $innergroupby . $outerwhere;

        return [$sql, $countsql];
    }
}