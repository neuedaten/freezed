<?php

namespace Neuedaten\Freezed\Exception;

/**
 * Thrown when a build would read a file outside the directories Freezed is
 * allowed to touch: the project directory and the configured assetRoots.
 * Also raised for an assetRoots entry that breaks that rule itself.
 */
class PathNotAllowedException extends \RuntimeException
{
}
