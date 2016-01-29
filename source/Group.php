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
 * Executing group of tasks (random order / simultaneously)
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class Group
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
    public $result;

    /**
     * Setting tasks to group on create
     * @param array $tasks Instance of Task class
     */
    public function __construct($tasks) 
    {
        $this->tasks = $tasks;
        $this->result = new GroupResult($tasks);
    }

    /**
     * Sending group of tasks into celery
     * @param string $routingKey Routing name of the queue to send tasks to
     * @return GroupResult
     */
    public function applyAsync($routingKey='default') 
    {
        foreach ($this->tasks as $t) {
            $t->applyAsync($routingKey);
        }
        return $this->result;
    }
}
