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

namespace local_rollover;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Rollover stuff
 */
class Rollover
{
    /** Rollover ID */
    private $id;

    /** Rollover settings */
    private $settings;

    /**
     * Begin a rollover.
     */
    public function __construct($settings) {
        $this->id = uniqid('rollover-');
        $this->settings = $settings;

        $this->setup();
    }

    /**
     * Static backup method.
     */
    public static function backup($settings) {
        global $CFG;

        $controller = new backup\controllers\rollover($settings['id'], $settings);
        $controller->execute_plan();

        $result = $controller->get_results();
        $file = $result['backup_destination'];

        if ($file->get_contenthash()) {
            $packer = get_file_packer('application/vnd.moodle.backup');

            $destination = $CFG->tempdir . '/backup/' . $file->get_contenthash();

            $file->extract_to_pathname($packer, $destination);
            $file->delete();

            return $destination;
        }

        return null;
    }

    /**
     * Setup.
     */
    private function setup() {
        global $CFG;

        // Ensure we have a valid backup directory.
        if (!file_exists($CFG->tempdir . '/backup')) {
            if (!mkdir($CFG->tempdir . '/backup')) {
                throw new \moodle_exception('Could not create backup directory!');
            }
        }

        // Ensure we have the settings we need.
        if (!isset($this->settings['id'])) {
            throw new \moodle_exception('Must specify ID to roll into!');
        }

        if (!isset($this->settings['folder'])) {
            throw new \moodle_exception('Must specify folder to roll from!');
        }
    }

    /**
     * Do the rollover.
     */
    public function go() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $this->migrate_data();
            $this->manipulate_data();
            $this->import();
        } catch (\moodle_exception $e) {
            $DB->rollback_delegated_transaction($transaction, $e);
        }

        $transaction->allow_commit();
    }

    /**
     * Move the data folder to our backup folder.
     */
    private function migrate_data() {
        global $CFG;

        $from = escapeshellcmd($this->settings['folder']);
        $to = escapeshellcmd($CFG->tempdir . '/backup/' . $this->id);

        exec("mv $from $to", $out, $return);

        if ($return != 0) {
            throw new \moodle_exception('Could not move backup folder!');
        }
    }

    /**
     * Manipulate data prior to rollover.
     */
    private function manipulate_data() {
        global $CFG;

        $xml = $CFG->tempdir . '/backup/' . $this->id . '/moodle_backup.xml';

        $doc = new \DOMDocument();
        if (!$doc->load($xml)) {
            throw new \moodle_exception('Could not load backup file <' . $xml . '>');
        }

        $xpath = new \DOMXPath($doc);

        // Remove all turnitintool activities.
        $query = "/moodle_backup/information/contents/activities/activity[modulename/text()='turnitintool']";
        $this->remove_nodes($xpath, $query);

        // Remove all turnitintool settings.
        $query = "/moodle_backup/information/settings/setting[activity/text()[contains(.,'turnitintool')]]";
        $this->remove_nodes($xpath, $query);

        if ($doc->save($xml) === false) {
            throw new \moodle_exception('Could not overwrite backup file <' . $xml . '>');
        }
    }

    /**
     * Manipulate a DOM Document - remove a path.
     */
    private function remove_nodes($xpath, $query) {
        $nodes = $xpath->query($query);

        // Remove them.
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Run the import.
     */
    private function import() {
        global $CFG;

        $controller = new \restore_controller(
            $this->id,
            $this->settings['id'],
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            2,
            \backup::TARGET_EXISTING_ADDING
        );

        if (!$controller->execute_precheck()) {
            throw new \moodle_exception("Plan pre-check failed. " . var_dump($controller->get_precheck_results()));
        }

        $controller->execute_plan();
    }
}