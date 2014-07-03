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
 * Kent rollover backup script.
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        // The ID of the course to export.
        'course' => false,
    )
);

raise_memory_limit(MEMORY_UNLIMITED);

$prefs = array (
    'id' => $options['course'],
    'backup_aspirelists' => 1,
    'backup_assign' => 1,
    'backup_book' => 1,
    'backup_chat' => 1,
    'backup_choice' => 1,
    'backup_choicegroup' => 1,
    'backup_cla' => 1,
    'backup_data' => 1,
    'backup_facetoface' => 1,
    'backup_folder' => 1,
    'backup_forum' => 1,
    'backup_glossary' => 1,
    'backup_hotpot' => 1,
    'backup_imscp' => 1,
    'backup_label' => 1,
    'backup_lesson' => 1,
    'backup_lti' => 1,
    'backup_ouwiki' => 1,
    'backup_page' => 1,
    'backup_questionnaire' => 1,
    'backup_quiz' => 1,
    'backup_resource' => 1,
    'backup_scorm' => 1,
    'backup_streamingvideo' => 1,
    'backup_survey' => 1,
    'backup_thesis' => 1,
    'backup_url' => 1,
    'backup_wiki' => 1,
    'backup_workshop' => 1,
    'backup_turnitintool' => 0,
    'backup_turnitintooltwo' => 1
);

$destination = \local_rollover\Rollover::backup($prefs);
if ($destination === null) {
    exit(1);
}

echo $destination;
