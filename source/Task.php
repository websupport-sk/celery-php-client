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

use DateTime;
use Exception;

/**
 * Common task model
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class Task
{
    public $id;
    public $name;
    public $args = array();
    public $kwargs = array();
    public $immutable = true;
    public $result;
    public $callback;
    public $connection;
    public $taskset;
    public $eta;

    /**
     * Setting task internal properties
     * @param Client    $connection Celery' client instance
     * @param string    $name       Task name
     * @param array     $args       Task arguments
     * @param \DateTime $eta        Postponning task execution to specific time
     * @throws \Exception
     */
    public function __construct($connection, $name, $args, $eta=null) 
    {
        $this->connection = $connection;
        $this->name = $name;
        if (!is_array($args)) {
            throw new Exception("Args should be an array");
        }

        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $this->args[] = $v;
            } else {
                $this->kwargs[$k] = $v;
            }
        }

        if ($eta) {
            if (!$eta instanceof DateTime) {
                throw new Exception('eta is not instance of DateTime');
            }
            $this->eta = $eta;
        }

        $this->id = self::genUuid();
        $this->result = new AsyncResult($this);
    }

    /**
     * Sending task into celery queue
     * @param type $routingKey Routing name of the queue
     * @return AsyncResult
     * @throws \Exception
     */
    public function applyAsync($routingKey='default') 
    {
        foreach ($this->format() as $task) {
            $message = array(
            'body' => base64_encode(json_encode($task)),
            'headers' => array(),
            'content-type' => 'application/json',
            'content-encoding' => 'binary',
            'properties' => array(
            'body_encoding' => 'base64',
            'reply_to' => $task['id'],
            'delivery_info' => array(
            'priority' => 0,
            'routing_key' => $routingKey,
            'exchange' => $routingKey,
            ),
            'delivery_mode' => 2,
            'delivery_tag'  => $task['id'],
            ),
            );
            $success = $this->connection->executeCommand(
                'lPush',
                array($routingKey, json_encode($message))
            );
            if (!$success) {
                throw new Exception("Failed to post task ".$this->name);
            }
        }
        return $this->result;
    }

    /**
     * Gets list of callbacks for task or group of tasks
     * @return array
     */
    public function getAllCallbacks() 
    {
        return $this->callback
        ? array($this->callback) + $this->callback->getAllCallbacks()
        : array();
    }

    /**
     * Formatting task before putting into celery queue
     * @return array
     */
    protected function format() 
    {
        return array(array_merge(
            $this->formatBase($this), array(
            'id'=>$this->id,
            'callbacks' => $this->callback
            ? $this->formatCallbacks($this->getAllCallbacks())
            : null,
            )
        ));
    }

    /**
     * Basic decoration of task meta data
     * @param Task $task Instance of task to format
     * @return array
     */
    protected function formatBase(Task $task) 
    {
        return array(
        'task' => $task->name,
        'args' => $task->args,
        'kwargs' => (object) $task->kwargs,
        'immutable' => $task->immutable,
        'taskset' => $task->taskset,
        'eta' => $this->eta ? $this->eta->format(DateTime::RFC3339) : null,
        );
    }

    /**
     * Formatting tasks callbacks
     * @param array $tasks Instance of tasks
     * @return array
     */
    protected function formatCallbacks($tasks) 
    {
        $t = array_pop($tasks);
        if (!$t) {
            return; 
        }
        return array(array_merge(
            $this->formatBase($t), array(
            'options' => array(
            'task_id' => $t->id,
            'link' => $this->formatCallbacks($tasks),
            )
            )
        ));
    }

    /**
     * Generating unique UUID of the task
     * @return string
     */
    protected static function genUuid() 
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
