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
 * Like AsyncResult but for group of tasks
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class GroupResult
{
    /**
     * List of partial `AsyncResult` instances
     * @var array
     */
    public $children;

    /**
     * Transforming group of tasks into AsyncResult instances
     * @param array $tasks List of tasks
     */
    public function __construct($tasks) 
    {
        foreach ($tasks as $t) {
            $this->children[] = new AsyncResult($t); 
        }
    }

    /**
     * Calling AsyncResult's methods on list of instances
     * @param string $method Method name to execute
     * @param array  $args   Method's arguments
     * @return array List of method-calls returns
     */
    public function __call($method, $args) 
    {
        $all = array();
        foreach ($this->children as $t) {
            $all[] = call_user_func_array(array($t, $method), $args); 
        }
        if (in_array($method, array('isReady', 'isSuccess'))) {
            return !in_array(false, $all); 
        }
        return $all;
    }

    /**
     * Getting AsyncResult's properties on list of instances
     * @param string $property Property name to get
     * @return array List of instances' properties
     */
    public function __get($property) 
    {
        $all = array();
        foreach ($this->children as $t) {
            $all[] = $t->$property; 
        }
        if ($property == 'status') {
            return !in_array(false, $all); 
        }
        return $all;
    }
}
