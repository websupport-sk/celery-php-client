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
 * Base client class
 *
 * This class is used as centralized client which communicates with redis
 * socket connection and sends task to queue.
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class Client
{
    /**
     * Celery's (redis) host name
     * @var string
     */
    public $host;

    /**
     * Celery's (redis) port
     * @var integer
     */
    public $port;

    /**
     * Database index to use
     * @var integer
     */
    public $db;

    /**
     * Password used for authentication
     * @var string
     */
    public $password;

    /**
     * Connection timeout
     * @var integer
     */
    public $timeout;

    /**
     * Connection socket
     * @var stream resource
     */
    protected $socket;

    /**
     * Setting connection's default parameters
     * @param string  $host Celery's (redis) host name
     * @param integer $port Celery's (redis) port
     * @param integer $db   Database index to use
     */
    public function __construct($host='127.0.0.1', $port=6379, $db=0) 
    {
        $this->host = $host;
        $this->port = $port;
        $this->db = $db;
    }

    /**
     * Simple and straightforward way to post simple task into queue
     * @param string    $name       Celery's task name
     * @param array     $args       Task arguments
     * @param string    $routingKey Routing name of queue to use
     * @param \DateTime $eta        Postponing task to specific date/time
     * @return AsyncResult
     */
    public function postTask(
        $name,
        $args = array(),
        $routingKey = 'default',
        $eta = null
    ) {
        $task = $this->task($name, $args, $eta);
        $task->applyAsync($routingKey);
        return $task->result;
    }

    /**
     * Creating task instance
     * @param string    $name Celery's task name
     * @param array     $args Task arguments
     * @param \DateTime $eta  Postponing task to specific date/time
     * @return Task
     */
    public function task($name, $args = array(), $eta=null) 
    {
        return new Task($this, $name, $args, $eta);
    }

    /**
     * Creating group from given list of tasks
     * @param array $tasks List of Task instances
     * @return Group
     */
    public function group($tasks) 
    {
        return new Group($tasks);
    }

    /**
     * Creating chain from given list of tasks
     * @param array $tasks List of Task instances
     * @return Chain
     */
    public function chain($tasks) 
    {
        return new Chain($tasks);
    }

    /**
     * Creating group from given list of tasks
     * @param array $tasks List of Task instances
     * @param Task  $final Instance of final task to execute
     * @return Chord
     */
    public function chord($tasks, $final) 
    {
        return new Chord($tasks, $final);
    }

    /**
     * Gets task's message body
     * @param string $id Task ID
     * @return boolean
     */
    public function getMessageBody($id) 
    {
        $result = $this->executeCommand(
            'GET',
            array(sprintf("%s%s", 'celery-task-meta-', $id))
        );
        if ($result) {
            return json_decode($result);
        } else {
            return false;
        }
    }

    /**
     * Makes connection to redis server
     * @return void
     * @throws \Exception
     */
    protected function connect() 
    {
        $this->socket = @stream_socket_client(
            $this->host.':'.$this->port,
            $errorNumber,
            $errorDescription,
            $this->timeout ? $this->timeout : ini_get('default_socket_timeout')
        );
        if ($this->socket) {
            if ($this->password!==null) {
                $this->executeCommand('AUTH', array($this->password)); 
            }
            $this->executeCommand('SELECT', array($this->db));
        } else {
            throw new Exception(
                'Failed to connect to redis: '.$errorDescription,
                (int)$errorNumber
            );
        }
    }

    /**
     * Executes requested command on active socket connection
     * @param string $name   Command's name
     * @param type   $params Command's arguments
     * @return mixed
     */
    public function executeCommand($name,$params=array()) 
    {
        if ($this->socket===null) {
            $this->connect(); 
        }

        array_unshift($params, $name);
        $command='*'.count($params)."\r\n";
        foreach ($params as $arg) {
            $command.='$'.strlen($arg)."\r\n".$arg."\r\n"; 
        }

        fwrite($this->socket, $command);
        return $this->parseResponse(implode(' ', $params));
    }

    /**
     * Reading response and parsing response according to opeartion type
     * @return mixed
     * @throws \Exception
     */
    protected function parseResponse() 
    {
        if (($line=fgets($this->socket)) === false) {
            throw new Exception('Failed reading data from redis socket.'); 
        }
        $type=$line[0];
        $line=substr($line, 1, -2);
        switch ($type) {
        case '+': // Status reply
            return true;
        case '-': // Error reply
            throw new Exception('Redis error: '.$line);
        case ':': // Integer reply
            // no cast to int as it is in the range of a signed 64 bit integer
            return $line;
        case '$': // Bulk replies
            if ($line=='-1') {
                return null; 
            }
            $length=$line+2;
            $data='';
            while ($length>0) {
                if (($block=fread($this->socket, $length))===false) {
                    throw new Exception(
                        'Failed reading data from redis connection socket.'
                    ); 
                }
                $data.=$block;
                $length-= function_exists('mb_strlen')
                ? mb_strlen($block, '8bit')
                : strlen($block);
            }
            return substr($data, 0, -2);
        case '*': // Multi-bulk replies
            $count=(int)$line;
            $data=array();
            for ($i=0;$i<$count;$i++) {
                $data[]=$this->parseResponse(); 
            }
            return $data;
        default:
            throw new Exception('Unable to parse data received from redis.');
        }
    }
}
