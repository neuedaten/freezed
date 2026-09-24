<?php

namespace Neuedaten\Freezed\Exception;

/**
 * Thrown when a content source cannot be set up or delivers unusable items:
 * an unknown "source" value, a script that returns no array, an item without
 * a slug, two items with the same slug.
 */
class ContentSourceException extends \RuntimeException
{
}
