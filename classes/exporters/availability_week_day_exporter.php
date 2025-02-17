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
 * Contains week day class for displaying the day in availability week view.
 *
 * @package    local_booking
 * @author     Mustafa Hajjar (mustafa.hajjar)
 * @copyright  BAVirtual.co.uk © 2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_booking\exporters;

defined('MOODLE_INTERNAL') || die();

use local_booking\local\participant\entities\student;
use core\external\exporter;
use renderer_base;

/**
 * Class for displaying the day on month view.
 */
class availability_week_day_exporter extends exporter {

    /**
     * @var {object} $slot data objects.
     */
    protected $slot;

    /**
     * @var bool $groupview Whether this is a single or all group view.
     */
    protected $groupview;

    /**
     * Constructor.
     *
     * @param \calendar_information $calendar The calendar information for the period being displayed
     * @param mixed $data Either an stdClass or an array of values.
     * @param array $related Related objects.
     */
    public function __construct($data, $related) {
        // Fix the url for today to be based on the today timestamp
        // rather than the calendar_information time set in the parent
        // constructor.
        $this->slot      = $data['slot'];
        $this->groupview = $data['groupview'];

        parent::__construct($data, $related);
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'type' => '\core_calendar\type_base',
        ];
    }

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_properties() {
        $return = parent::define_properties();
        $return = array_merge($return, [
            // These are additional params.
            'timestamp' => [
                'type' => PARAM_INT,
            ],
            'istoday' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'isweekend' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'daytitle' => [
                'type' => PARAM_RAW,
            ],
            'groupview' => [
                'type' => PARAM_BOOL,
                'default' => '',
            ],
            'slotavailable' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
        ]);

        return $return;
    }
    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties() {
        $return = parent::define_other_properties();
        $return = array_merge($return, [
            'slotmarked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'slotbooked' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'slotstatus' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'slotstatustooltip' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'slotcolor' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
        ]);

        return $return;
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {

        // Check if there is a slot that matches
        $slotstatus = '';
        $slotstatustooltip = '';
        if ($this->slot != null) {
            $slotstatus = $this->slot->slotstatus ?: 'selected';
            // add student name tooltip in group view
            if ($this->groupview) {
                $studentname = student::get_fullname($this->slot->userid);
                $slotstatustooltip = $studentname . '<br/>';
            }
            $slotstatustooltip .= $this->slot->bookinginfo ?: '';

        }

        $return = [
            'slotmarked' => $this->slot != null,
            'slotbooked' => $this->slot != null ? !empty($this->slot->slotstatus) : false,
            'slotstatus' => $slotstatus,
            'slotstatustooltip' => $slotstatustooltip,
            'slotcolor' => $this->slot != null ? $this->slot->slotcolor : 'white'
        ];

        return $return;
    }
}
