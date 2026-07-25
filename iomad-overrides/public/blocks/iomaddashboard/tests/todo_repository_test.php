<?php
// This file is part of Moodle - http://moodle.org/

namespace block_iomaddashboard;

use block_iomaddashboard\local\todo_repository;

/**
 * Private task ownership tests.
 *
 * @package    block_iomaddashboard
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_iomaddashboard\local\todo_repository
 */
final class todo_repository_test extends \advanced_testcase {
    /**
     * Users cannot update another user's tasks.
     */
    public function test_task_mutations_require_owner(): void {
        $this->resetAfterTest(true);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $repository = new todo_repository();
        $task = $repository->create($usera->id, 7, 'Review lesson plan');

        $this->assertCount(1, $repository->list_for_user($usera->id));
        $this->assertSame([], $repository->list_for_user($userb->id));
        $this->expectException(\dml_missing_record_exception::class);
        $repository->set_completed($task->id, $userb->id, true);
    }

    /**
     * An owner can complete and delete a task.
     */
    public function test_owner_can_complete_and_delete_task(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $repository = new todo_repository();
        $task = $repository->create($user->id, 3, 'Prepare feedback');

        $repository->set_completed($task->id, $user->id, true);
        $this->assertEquals(1, $repository->list_for_user($user->id)[0]->completed);
        $repository->delete($task->id, $user->id);
        $this->assertSame([], $repository->list_for_user($user->id));
    }
}
