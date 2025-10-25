<?php

declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Parsedown;

/**
 * Markdown Helper
 *
 * Provides methods for rendering Markdown content to HTML in views.
 * Uses Parsedown for markdown parsing with XSS protection.
 *
 * Usage in templates:
 * ```php
 * echo $this->Markdown->toHtml($markdownText);
 * ```
 */
class MarkdownHelper extends Helper
{
    /**
     * Parsedown instance for markdown parsing
     *
     * @var \Parsedown
     */
    protected Parsedown $parsedown;

    /**
     * Initialize the helper and create Parsedown instance
     *
     * @param array $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->parsedown = new Parsedown();

        // Enable safe mode to prevent XSS attacks
        $this->parsedown->setSafeMode(true);

        // Enable markup escaping for additional security
        $this->parsedown->setMarkupEscaped(true);
    }

    /**
     * Convert markdown text to HTML
     *
     * @param string|null $markdown The markdown text to convert
     * @return string The HTML output
     */
    public function toHtml(?string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        return $this->parsedown->text($markdown);
    }

    /**
     * Convert markdown text to HTML (inline version, no block elements)
     *
     * Useful for rendering markdown in contexts where block elements
     * like paragraphs shouldn't be created.
     *
     * @param string|null $markdown The markdown text to convert
     * @return string The HTML output
     */
    public function toInlineHtml(?string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        return $this->parsedown->line($markdown);
    }
}
