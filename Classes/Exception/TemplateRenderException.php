<?php

namespace Neuedaten\Freezed\Exception;

/**
 * Thrown when a content template fails to render.
 *
 * Carries a human-readable identifier of the affected template so the CLI can
 * report "which template / which error" without dumping a full stack trace.
 */
class TemplateRenderException extends \RuntimeException
{
    protected string $template;

    public function __construct(string $template, string $message, ?\Throwable $previous = null)
    {
        $this->template = $template;
        parent::__construct($message, 0, $previous);
    }

    public function getTemplate(): string
    {
        return $this->template;
    }
}
