<?php

namespace Perimeterx;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class PerimeterxLogger extends AbstractLogger
{

    protected $debug_mode;
    protected $debug_prefix;
    protected $error_prefix;

    public function __construct($pxConfig) {
        $this->debug_mode = $pxConfig['debug_mode'];
        $this->debug_prefix = "[PerimeterX - DEBUG][{$pxConfig['app_id']}] -";
        $this->error_prefix = "[PerimeterX - ERROR][{$pxConfig['app_id']}] -";
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (!$this->debug_mode) {
            return;
        }

        $valid_log_levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        if (!in_array($level, $valid_log_levels)) {
            throw new InvalidArgumentException($level . ' is not a defined level in the PSR-3 specification.');
        }

        error_log($this->interpolate((string)$message, $context, $level));
    }

    /**
     * interpolate the message
     *
     * > 1.2 Message
     * > - The message MAY contain placeholders which implementors MAY replace with values from the context array.
     * > - Placeholder names MUST correspond to keys in the context array.
     * > - Placeholder names MUST be delimited with a single opening brace { and a single closing brace }. There MUST NOT be any whitespace between the delimiters and the placeholder name.
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    private function interpolate($message, array $context = [], $level)
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        $full_message;
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        $full_message = strtr($message, $replace);

        // interpolate replacement values into the message and return
        return $level === LogLevel::DEBUG ? "$this->debug_prefix$full_message" : "$this->error_prefix$full_message";
    }
}
