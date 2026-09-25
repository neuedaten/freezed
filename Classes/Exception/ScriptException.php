<?php

namespace Neuedaten\Freezed\Exception;

/**
 * Thrown when a build or install hook from the "scripts" configuration could
 * not be started or exited with a non-zero status.
 */
class ScriptException extends \RuntimeException
{
}
