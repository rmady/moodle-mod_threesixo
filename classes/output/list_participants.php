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
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @package    mod_threesixty
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_threesixty\output;

defined('MOODLE_INTERNAL') || die();

use mod_threesixty\api;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_participants implements renderable, templatable {

    /** @var stdClass The 360 instance.  */
    protected $threesixty;

    /** @var int The user ID of the respondent. */
    protected $userid;

    /** @var array The array of participants for the 360 feedback, excluding the respondent. */
    protected $participants = [];

    protected $canviewreports = false;

    /**
     * list_participants constructor.
     * @param stdClass $threesixty The 360 instance.
     * @param int $userid The respondent's user ID.
     * @param bool $init
     */
    public function __construct($threesixty, $userid, $participants, $canviewreports = false) {
        $this->userid = $userid;
        $this->threesixty = $threesixty;
        $this->participants = $participants;
        $this->canviewreports = $canviewreports;
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->threesixtyid = $this->threesixty->id;
        $data->participants = [];
        $anonymous = $this->threesixty->anonymous;

        foreach ($this->participants as $user) {
            $member = new stdClass();

            // Name column.
            $member->name = fullname($user);

            // Status column.
            // By default the user viewing the participants page can respond if there's a submission record.
            $canrespond = !empty($user->statusid);
            if ($canrespond) {
                switch ($user->status) {
                    case api::STATUS_IN_PROGRESS: // In Progress.
                        $member->statusinprogress = true;
                        break;
                    case api::STATUS_COMPLETE: // Completed.
                        $member->statuscompleted = true;
                        // If anonymous mode and completed, user won't be able to respond anymore.
                        if ($anonymous) {
                            $canrespond = false;
                        }
                        break;
                    case api::STATUS_DECLINED: // Declined.
                        $member->statusdeclined = true;
                        // If declined, user won't be able to respond anymore.
                        $canrespond = false;
                        break;
                    default:
                        $member->statuspending = true;
                        break;
                }

                $member->statusid = $user->statusid;
            }

            // Action buttons column.
            // View action.
            $member->reportslink = false;
            if ($this->canviewreports) {
                // When the user can't provide feedback to the participants but can view reports.
                if (empty($user->statusid)) {
                    $member->statusviewonly = true;
                }
                $reportslink = new moodle_url('/mod/threesixty/report.php');
                $reportslink->params([
                    'threesixty' => $this->threesixty->id,
                    'touser' => $user->userid,
                ]);
                $member->reportslink = $reportslink->out();
            }

            // Show action buttons depending on status.
            if ($canrespond) {
                $respondurl = new moodle_url('/mod/threesixty/questionnaire.php');
                $respondurl->params([
                    'threesixty' => $this->threesixty->id,
                    'submission' => $user->statusid,
                ]);
                $member->respondlink = $respondurl->out();
                $member->declinelink = true;
            }

            $data->participants[] = $member;
        }

        return $data;
    }
}
