<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 6/5/17
 * Time: 12:10 PM
 */

namespace NIForms;

require_once __DIR__ . '/Logger/Log.php';
require_once __DIR__ . '/Psr/Log/LoggerInterface.php';
require_once __DIR__ . '/Psr/Log/AbstractLogger.php';
require_once __DIR__ . '/Psr/Log/LogLevel.php';

use NIForms\Logger\Log;
use NIForms\Psr\Log\AbstractLogger;

/**
 * Class Logger
 * @package NIForms
 *
 * PSR-3 Log implementation
 */
class Logger extends AbstractLogger
{
    /**
     * @var string[]
     */
    private $stack_stage = array();

    /**
     * @var string[]
     */
    private $stack_handler = array();
    /**
     * @var Log[]
     */
    private $logs = array();

    /**
     * @return $this
     */
    public function clearStage()
    {
        $this->stack_stage = array();
        return $this;
    }

    /**
     * @return $this
     */
    public function clearHandler()
    {
        $this->stack_handler = array();
        return $this;
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     */
    public function log($level, $message, array $context = array())
    {
        $this->logs[] = new Log(
            $level,
            $message,
            $context,
            $this->getCurrentStage(),
            $this->getCurrentHandler()
        );
    }

    /**
     * @return string
     */
    public function getCurrentStage()
    {
        if ($this->countStage() > 0) {
            $current_stage = $this->popStage();
            $this->pushStage($current_stage);
        } else {
            $current_stage = null;
        }
        return $current_stage;
    }

    /**
     * @return int
     */
    public function countStage()
    {
        return count($this->stack_stage);
    }

    /**
     * @return string
     */
    public function popStage()
    {
        $current_stage = array_pop($this->stack_stage);
        return $current_stage;
    }

    /**
     * @param string $current_stage
     * @return $this
     */
    public function pushStage($current_stage)
    {
        array_push($this->stack_stage, $current_stage);
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentHandler()
    {
        if ($this->countHandler() > 0) {
            $current_handler = $this->popHandler();
            $this->pushHandler($current_handler);
        } else {
            $current_handler = null;
        }
        return $current_handler;
    }

    /**
     * @return int
     */
    public function countHandler()
    {
        return count($this->stack_handler);
    }

    /**
     * @return string
     */
    public function popHandler()
    {
        $current_handler = array_pop($this->stack_handler);
        return $current_handler;
    }

    /**
     * @param string $current_hander
     * @return $this
     */
    public function pushHandler($current_hander)
    {
        array_push($this->stack_handler, $current_hander);
        return $this;
    }

    /**
     * Basic filter log functionality
     *
     * $filter array is key is the field to match with and the value is what the field's value should be.  Value can be
     * an array, in which case, the log field must match one of the options.
     *
     * All filters must match.
     *
     * @param array $filter
     * @return array
     */
    public function getFilteredLogs(array $filter = array())
    {
        $logs = $this->getLogs();

        $filtered_logs = array_filter($logs, function ($v, $k) use ($filter) {
            assert($v instanceof Log);

            // Loop through all filters and return false on first mis-match.
            foreach ($filter as $filter_key => $filter_value) {
                $log_value = $v->{$filter_key};
                if (is_array($filter_value)) {
                    if (!in_array($log_value, $filter_value)) {
                        return false;
                    }
                } else {
                    if ($log_value != $filter_value) {
                        return false;
                    }
                }
            }
            return true;
        });

        return $filtered_logs;
    }

    /**
     * Returns all current logs
     *
     * @return Log[]
     */
    public function getLogs()
    {
        return $this->logs;
    }
}