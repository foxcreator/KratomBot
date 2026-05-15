<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Конвертує HTML з Filament RichEditor (Trix) у HTML, що підтримується
 * Telegram Bot API (parse_mode=HTML).
 *
 * Telegram дозволяє лише: <b>, <i>, <u>, <s>, <a>, <code>, <pre>, <blockquote>, <tg-spoiler>.
 * Усе інше треба трансформувати у текст з \n або у вкладений дозволений тег.
 */
class TelegramHtmlConverter
{
    public function convert(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"?><div>' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        $result = $root ? $this->renderChildren($root) : '';

        $result = preg_replace("/\n{3,}/", "\n\n", $result);

        return trim($result);
    }

    protected function renderChildren(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->renderNode($child);
        }

        return $out;
    }

    protected function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->escape($node->wholeText);
        }

        if (!$node instanceof DOMElement) {
            return $this->renderChildren($node);
        }

        $name = strtolower($node->nodeName);

        return match ($name) {
            'b', 'strong' => '<b>' . $this->renderChildren($node) . '</b>',
            'i', 'em' => '<i>' . $this->renderChildren($node) . '</i>',
            'u' => '<u>' . $this->renderChildren($node) . '</u>',
            's', 'del', 'strike' => '<s>' . $this->renderChildren($node) . '</s>',
            'code' => '<code>' . $this->renderChildren($node) . '</code>',
            'pre' => '<pre>' . $this->renderChildren($node) . '</pre>',
            'blockquote' => '<blockquote>' . trim($this->renderChildren($node)) . '</blockquote>',
            'a' => $this->renderLink($node),
            'br' => "\n",
            'p', 'div' => $this->renderBlock($node),
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '<b>' . trim($this->renderChildren($node)) . "</b>\n\n",
            'ul' => $this->renderList($node, true),
            'ol' => $this->renderList($node, false),
            'li' => $this->renderChildren($node),
            default => $this->renderChildren($node),
        };
    }

    protected function renderBlock(DOMElement $node): string
    {
        $inner = $this->renderChildren($node);
        $trimmed = trim($inner);

        if ($trimmed === '') {
            return "\n";
        }

        return $trimmed . "\n\n";
    }

    protected function renderLink(DOMElement $node): string
    {
        $inner = $this->renderChildren($node);
        $href = $node->getAttribute('href');

        if ($href === '') {
            return $inner;
        }

        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        return '<a href="' . $href . '">' . $inner . '</a>';
    }

    protected function renderList(DOMElement $node, bool $unordered): string
    {
        $items = [];
        $index = 1;

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'li') {
                $inner = trim($this->renderChildren($child));
                $prefix = $unordered ? '•' : ($index++ . '.');
                $items[] = $prefix . ' ' . $inner;
            }
        }

        if (empty($items)) {
            return '';
        }

        return implode("\n", $items) . "\n\n";
    }

    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }
}
