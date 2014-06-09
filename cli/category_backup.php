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
 * This file backs up all courses in a category, then gives you the tgz.
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/setuplib.php');
require_once($CFG->libdir . '/filelib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        // The ID of the category to export.
        'category' => false,
        // Compatibility with < 2.6 Moodles.
        'compatibility' => false
    )
);

if (!$options['category']) {
    cli_error("Must specify category with --category=id!");
}

raise_memory_limit(MEMORY_HUGE);

if ($options['compatibility']) {
    $CFG->enabletgzbackups = false;
}

$backuppath = $CFG->tempdir . '/backup/' . $options['category'];
fulldelete($backuppath);
make_writable_directory($backuppath);


cli_heading("Running Category Backup" . ($options['compatibility'] ? ' (compatibility mode)' : ''));

$username = exec('logname');
$user = $DB->get_record('user', array(
    'username' => $username
));

if ($user) {
    echo "Detected user: {$user->username}.\n";
} else {
    $user = get_admin();
    echo "No valid username detected - using admin.\n";
}

\core\session\manager::set_user($user);

$courses = $DB->get_fieldset_sql("
    SELECT c.id FROM {course} c
    INNER JOIN {course_categories} cc
      ON cc.id=c.category
    WHERE cc.path LIKE :cata
      OR cc.path LIKE :catb
", array(
    "cata" => "%/" . $options['category'] . "/%",
    "catb" => "%/" . $options['category']
));

$files = array();
$prefs = array();

foreach ($courses as $course) {
    cli_separator();
    echo "Backing up course $course...\n";

    $controller = new \local_rollover\backup\controllers\simple($course, $prefs);
    $controller->execute_plan();
    $result = $controller->get_results();

    $file = $result['backup_destination'];
    $hash = $file->get_contenthash();
    if (!$hash) {
        cli_problem("Failed!\n");
        continue;
    }

    // Okay we have a backup file, copy it to temp dir.
    $filename = $backuppath . '/' . $hash;
    file_put_contents($filename, $file->get_content());
    $files[$course . ".mbz"] = $filename;
    $file->delete();

    echo "Finished!\n";
    cli_separator();
}

cli_heading("Packing up");

$newfile = $CFG->tempdir . '/backup/' . $options['category'] . '.tgz';

// Delete old file if it exists.
@unlink($newfile);

// Pack all the files up.
$packer = get_file_packer('application/x-gzip');
if (!$packer->archive_to_pathname($files, $newfile)) {
    cli_error('Error whilst trying to tar files. You will need to zip it up yourself.');
}

cli_heading("Cleaning Up");

fulldelete($backuppath);

cli_heading("Finished!");