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

use IApplicationComponent;

/**
 * Base wrapper for celery client with implemented yii application component
 * interface
 *
 * @category API
 * @package  Celery
 * @author   Matej Čapkovič <matej.capkovic@websupport.sk>
 * @author   Tomáš Tatarko <tomas.tatarko@websupport.sk>
 * @license  http://choosealicense.com/licenses/mit/ MIT
 * @link     https://github.com/websupport-sk/celery-php-client Repository
 */
class YiiApplicationComponent extends Client implements IApplicationComponent
{
    /**
     * Open socket connection
     * @return void
     */
    public function init() 
    {
        $this->connect();
    }

    /**
     * Checking if socket has been initialized
     * @return boolean
     */
    public function getIsInitialized() 
    {
        return $this->socket ? true : false;
    }
}
