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
 * Execution tasks in specific order
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class Chain extends Task
{
    /**
     * List of group's tasks to execute
     * @var array
     */
    public $tasks;

    /**
     * Instance of group result execution
     * @var GroupResult
     */
    public $parents;

    /**
     * Setting tasks to group on create
     * @param array $tasks Instance of Task class
     */
    public function __construct($tasks) 
    {
        $this->tasks = $tasks;
        $this->name = 'celery.chain';
        $t = array_pop($tasks); // $tasks was modified!
        $this->connection = $t->connection;
        $this->result = new AsyncResult($t);
        $this->parents = new GroupResult($tasks);
    }

    /**
     * Wrapping and formatting tasks before putting into celery
     * @return array
     */
    protected function format() 
    {
        $callbacks = array_reverse($this->tasks);
        $tasks = array(array_pop($callbacks)); // pop first

        $res = array();
        foreach ($tasks as $task) {
            $res[] = array_merge(
                $this->formatBase($task), array(
                'id' => $task->id,
                'callbacks' => $this->formatCallbacks($callbacks),
                )
            );
        }
        return $res;
    }
}
