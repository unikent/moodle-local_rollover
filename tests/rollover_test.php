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
     * Run all rollovers.
     */
    private function rollover() {
        $task = new \local_rollover\task\backups();
        $task->execute();
        $task = new \local_rollover\task\imports();
        $task->execute();
    }

    /**
     * Test we can schedule a rollover.
     */
    public function test_schedule() {
        global $SHAREDB;

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
        global $DB;

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
     * Test the rollover re-processes CLA.
     */
    public function test_cla_rollover() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module1 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));
        $module3 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $module4 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));

        // Create another course.
        $course2 = $this->getDataGenerator()->create_course();
        $module5 = $this->getDataGenerator()->create_module('cla', array('course' => $course2));
        $module6 = $this->getDataGenerator()->create_module('cla', array('course' => $course2));

        // Create rollover skeletons.
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();

        // Do the rollover.
        \local_rollover\Rollover::schedule("testing", $course1->id, $course3->id);
        \local_rollover\Rollover::schedule("testing", $course2->id, $course4->id);
        $this->rollover();

        // The test!
        $this->assertEquals(8, $DB->count_records('course_modules', array(
            'course' => $course3->id
        )));

        $this->assertEquals(4, $DB->count_records('cla', array(
            'course' => $course3->id
        )));

        $this->assertEquals(2, $DB->count_records('course_modules', array(
            'course' => $course4->id
        )));

        $this->assertEquals(2, $DB->count_records('cla', array(
            'course' => $course4->id
        )));
    }

    /**
     * Test the rollover re-processes CLA.
     */
    public function test_cla_rollover_pre_process() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();
        $module1 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));
        $course2 = $this->getDataGenerator()->create_course();

        // Sanity checks.
        $this->assertEquals(1, $DB->count_records('cla', array(
            'course' => $course1->id
        )));

        $this->assertEquals(1, $DB->count_records('cla_note'));

        $this->assertEquals(0, $DB->get_field('cla', 'rolled_over', array(
            'course' => $course1->id
        )));

        $this->assertEquals(0, $DB->get_field('cla', 'rolled_over_inactive', array(
            'course' => $course1->id
        )));

        // Do the rollover.
        \local_rollover\Rollover::schedule("testing", $course1->id, $course2->id);
        $this->rollover();

        // The test!
        $this->assertEquals(1, $DB->count_records('course_modules', array(
            'course' => $course2->id
        )));

        $this->assertEquals(1, $DB->count_records('cla', array(
            'course' => $course2->id
        )));

        $this->assertEquals(1, $DB->get_field('cla', 'rolled_over', array(
            'course' => $course2->id
        )));

        $this->assertEquals(1, $DB->get_field('cla', 'rolled_over_inactive', array(
            'course' => $course2->id
        )));

        // Also, it should have added a note, as well as rolling over the previous note.
        $this->assertEquals(3, $DB->count_records('cla_note'));
    }

    /**
     * Test rollover re-processes CLA files.
     */
    public function test_cla_files_rollover() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();
        $module1 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));

        // Create directory.
        $dir = $CFG->dataroot . '/cla/';
        check_dir_exists($dir);

        // Add a file.
        $filename = 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz.txt';
        file_put_contents($dir . $filename, 'Hey There!');

        $module1->reference = $filename;
        $DB->update_record('cla', $module1);

        // Create rollover skeleton.
        $course2 = $this->getDataGenerator()->create_course();

        // Do the rollover.
        \local_rollover\Rollover::schedule("testing", $course1->id, $course2->id);

        $task = new \local_rollover\task\backups();
        $task->execute();

        unlink($dir . $filename);

        $task = new \local_rollover\task\imports();
        $task->execute();

        $this->assertTrue(file_exists($dir . $filename));

        $this->assertEquals(1, $DB->count_records('cla', array(
            'course' => $course2->id
        )));

        $this->assertEquals($filename, $DB->get_field('cla', 'reference', array(
            'course' => $course2->id
        )));

        unlink($dir . $filename);
    }

    /**
     * Test rollover re-processes CLA multi-level files.
     */
    public function test_cla_multi_level_files_rollover() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();
        $module1 = $this->getDataGenerator()->create_module('cla', array('course' => $course1));

        // Create directory.
        $dir = $CFG->dataroot . '/cla/a/b/';
        check_dir_exists($dir);
        $dir = $CFG->dataroot . '/cla/';

        // Add a file.
        $filename = 'a/b/a.txt';
        file_put_contents($dir . $filename, 'Hey There!');

        $module1->reference = $filename;
        $DB->update_record('cla', $module1);

        // Create rollover skeleton.
        $course2 = $this->getDataGenerator()->create_course();

        // Do the rollover.
        \local_rollover\Rollover::schedule("testing", $course1->id, $course2->id);

        $task = new \local_rollover\task\backups();
        $task->execute();

        unlink($dir . $filename);

        $task = new \local_rollover\task\imports();
        $task->execute();

        $this->assertTrue(file_exists($dir . $filename));

        $this->assertEquals(1, $DB->count_records('cla', array(
            'course' => $course2->id
        )));

        $this->assertEquals($filename, $DB->get_field('cla', 'reference', array(
            'course' => $course2->id
        )));

        unlink($dir . $filename);
    }

    /**
     * Test the rollover processes removes section0 news and aspirelist modules.
     */
    public function test_skeleton_rollover() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course1 = $this->getDataGenerator()->create_course();

        // Order is important here as lowest id is used for module removal.
        $module1 = $this->getDataGenerator()->create_module('forum', array('course' => $course1));
        $module3 = $this->getDataGenerator()->create_module('aspirelists', array('course' => $course1));
        $module2 = $this->getDataGenerator()->create_module('forum', array('course' => $course1));
        $course2 = $this->getDataGenerator()->create_course();

        // Sanity checks.
        $this->assertEquals(2, $DB->count_records('forum', array(
            'course' => $course1->id
        )));

        $this->assertEquals(3, $DB->count_records('course_modules', array(
            'course' => $course1->id
        )));

        // Do the rollover.
        \local_rollover\Rollover::schedule("testing", $course1->id, $course2->id);
        $this->rollover();

        // The tests.
        $this->assertEquals(1, $DB->count_records('course_modules', array(
            'course' => $course2->id
        )));

        $this->assertEquals(1, $DB->count_records('forum', array(
            'course' => $course2->id
        )));

        $this->assertEquals(0, $DB->count_records('aspirelists', array(
            'course' => $course2->id
        )));
    }

    /**
     * Test course::is_empty
     */
    public function test_course_is_empty() {
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