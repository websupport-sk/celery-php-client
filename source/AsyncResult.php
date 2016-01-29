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

use Exception;

/**
 * Fetching and other jobs with task async results
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class AsyncResult
{
    public $task;
    public $body;

    /**
     * Creating result instance from given task
     * @param Task $task Instance of executed task
     */
    public function __construct(Task $task) 
    {
        $this->task = $task;
    }

    /**
     * Checks if task is completed
     * @return boolean
     */
    protected function getCompleteResult() 
    {
        $r = $this->task->connection->getMessageBody($this->task->id);

        if ($r !== false
            && isset($r->status)
            && !in_array($r->status, array('STARTED', 'RECEIVED', 'PENDING'))
        ) {
            $this->body = $r;
        }

        if ($r !== false) {
            return $r;
        }

        return false;
    }

    /**
     * Checks if task is ready
     * @return booelan
     */
    public function isReady() 
    {
        $r = $this->getCompleteResult();
        return $r !== false
        && isset($r->status)
        && !in_array($r->status, array('STARTED', 'RECEIVED', 'PENDING'));
    }

    /**
     * Checks if task has completed successfully
     * @return booelan
     */
    public function isSuccess() 
    {
        return $this->getStatus() == 'SUCCESS';
    }

    /**
     * Gets task result status
     * @return string
     */
    public function getStatus() 
    {
        if (!$this->body) {
            throw new Exception('Called getStatus before task was ready');
        }
        return $this->body->status;
    }

    /**
     * Gets task result traceback
     * @return array
     */
    public function getTraceback() 
    {
        if (!$this->body) {
            throw new Exception('Called getTraceback before task was ready');
        }
        return $this->body->traceback;
    }

    /**
     * Gets task's result
     * @return string
     */
    public function getResult() 
    {
        if (!$this->body) {
            throw new Exception('Called getResult before task was ready');
        }
        return $this->body->result;
    }

    /**
     * Waiting for task to complete
     * @param integer $timeout  Max number of seconds to wait
     * @param float   $interval Time interval of repeated checks
     * @return string
     * @throws \Exception
     */
    public function get($timeout = 10, $interval = 0.5) 
    {
        $interval_us = (int) ($interval * 1000000);

        $startTime = microtime(true);
        while (microtime(true) - $startTime < $timeout) {
            if ($this->isReady()) {
                break;
            }
            usleep($interval_us);
        }

        if (!$this->isReady()) {
            throw new Exception(
                sprintf(
                    'task %s(%s) did not return after %d seconds',
                    $this->task->name,
                    json_encode($this->task->args), $timeout
                )
            );
        }

        return $this->getResult();
    }

    /**
     * Accessing class methods return as properties
     * @param string $property Property name to get
     * @return string
     * @throws \Exception
     */
    public function __get($property) 
    {
        if ($property == 'result' || $property == 'traceback') {
            if ($this->isReady()) {
                $n = 'get'.$property;
                return $this->$n();
            } else {
                return null;
            }
        } elseif ($property == 'status') {
            if ($this->isReady()) {
                return $this->getStatus();
            } else {
                return 'PENDING';
            }
        }
        throw new Exception(
            'Property "'.get_class($this).'.'.$property.'" is not defined.'
        );
    }
}
