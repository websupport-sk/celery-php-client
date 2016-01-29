<?php

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * PHP Version 5.3
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */

namespace Websupport\Celery;

/**
 * Executing groups of tasks at random with one final task to summarize them all
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class Chord extends Task
{
    /**
     * List of group's tasks to execute
     * @var array
     */
    public $tasks;

    /**
     * Instance of final task
     * @var Task
     */
    public $final;

    /**
     * Instance of group result execution
     * @var GroupResult
     */
    public $parents;

    /**
     * Setting tasks to group on create
     * @param array $tasks Instance of Task class
     * @param Task  $final Instance of final task to execute
     */
    public function __construct($tasks, $final) 
    {
        $this->tasks = $tasks;
        $this->final = $final;
        $this->name = 'celery.chord';
        $this->connection = $final->connection;
        $this->result = new AsyncResult($final);
        $this->parents = new GroupResult($tasks);
    }

    /**
     * Wrapping and formatting tasks before putting into celery
     * @return array
     */
    protected function format() 
    {
        $this->taskset = self::genUuid();
        $k = sprintf("%s%s", 'celery-taskset-meta-', $this->taskset);
        $s = array();
        foreach ($this->tasks as $t) {
            $t->taskset = $this->taskset;
            $s[] = array(array($t->id, null), null);
        }
        $v = json_encode(array("result" => array($this->taskset, $s)));
        $this->final->connection->executeCommand('SETEX', array($k, 86400, $v));

        $res = array();
        foreach ($this->tasks as $task) {
            $res[] = array_merge(
                $this->formatBase($task), array(
                'id'=>$task->id,
                'chord' => $this->formatChord($this->final, count($this->tasks)),
                )
            );
        }
        return $res;
    }

    /**
     * Wrapping and formatting chord before putting into celery
     * @param Task $task Instance of task to format
     * @param type $size Chord's size
     * @return array
     */
    protected function formatChord(Task $task, $size) 
    {
        return array_merge(
            $this->formatBase($task), array(
            'options' => array("task_id" => $task->id),
            'chord_size' => $size,
            )
        );
    }
}
