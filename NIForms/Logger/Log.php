<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 6/5/17
 * Time: 12:33 PM
 */

namespace NIForms\Logger;


class Log
{
    /**
     * @var string
     *
     * Stage of the form processing.
     */
    public $stage;

    /**
     * @var string
     * "Name" of handler for the event.
     */
    public $handler;

    /**
     * @var mixed
     */
    public $level;

    /**
     * @var string
     */
    public $message;

    /**
     * @var array
     */
    public $context;

    /**
     * Log constructor.
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @param string|null $stage
     * @param string|null $handler
     */
    public function __construct($level, $message, array $context = array(), $stage = null, $handler = null)
    {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->stage = $stage;
        $this->handler = $handler;
    }
}