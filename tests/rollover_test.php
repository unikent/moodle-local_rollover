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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the category manager
 */
class local_rollover_tests extends \local_connect\tests\connect_testcase
{
    /**
     * Test we can schedule a rollover.
     */
    public function test_schedule() {
        global $CFG, $DB, $SHAREDB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();

        $this->assertEquals(0, $SHAREDB->count_records('rollovers'));
        $result = \local_rollover\Rollover::schedule("testing", 1, $course1->id);
        $this->assertEquals(1, $SHAREDB->count_records('rollovers'));
    }

    /**
     * Test the rollover process.
     */
    public function test_rollover() {
        global $CFG, $DB, $SHAREDB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();
        $module1 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module3 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));

        // Create an empty course to rollover into.
        $course2 = $this->getDataGenerator()->create_course();

        // Get the rollover object.
        $rollover = new \local_rollover\Course($course2->id);
        $this->assertTrue($rollover->is_empty());
        $this->assertEquals(\local_rollover\Rollover::STATUS_NONE, $rollover->get_status());
        $this->assertEquals(3, $DB->count_records('course_modules', array(
            'course' => $course1->id
        )));
        $this->assertEquals(0, $DB->count_records('course_modules', array(
            'course' => $course2->id
        )));

        $result = \local_rollover\Rollover::schedule("testing", $course1->id, $course2->id);

        $this->assertEquals(\local_rollover\Rollover::STATUS_SCHEDULED, $rollover->get_status());

        // Now run a backup.
        $task = new \local_rollover\task\backups();
        $task->execute();

        $this->assertEquals(\local_rollover\Rollover::STATUS_BACKED_UP, $rollover->get_status());
        $this->assertTrue($rollover->is_empty());

        // Now run a restore.
        $task = new \local_rollover\task\imports();
        $task->execute();

        // Check things worked.
        $this->assertEquals(\local_rollover\Rollover::STATUS_COMPLETE, $rollover->get_status());
        $this->assertFalse($rollover->is_empty());
        $this->assertEquals(3, $DB->count_records('course_modules', array(
            'course' => $course1->id
        )));
        $this->assertEquals(3, $DB->count_records('course_modules', array(
            'course' => $course2->id
        )));
    }

    /**
     * Test course::is_empty
     */
    public function test_course_is_empty() {
        global $CFG, $DB, $SHAREDB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();

        $rollover = new \local_rollover\Course($course1->id);
        $this->assertTrue($rollover->is_empty());

        // Add modules.
        $module1 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module3 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $this->assertFalse($rollover->is_empty());
    }
}