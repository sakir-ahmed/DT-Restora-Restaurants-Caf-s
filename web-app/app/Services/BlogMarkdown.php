<?php

declare(strict_types=1);

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Turns the markdown an editor writes into HTML that is safe to show on the
 * public site: raw HTML is dropped, unsafe links are removed, external links
 * open in a new tab with nofollow, and images are not loaded from other sites.
 */
class BlogMarkdown
{
    public function toHtml(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new ExternalLinkExtension);

        // Pictures belong in the cover image; an inline image would pull in a file from anywhere.
        $environment->addRenderer(Image::class, new class implements NodeRendererInterface
        {
            public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
            {
                return e($childRenderer->renderNodes($node->children()));
            }
        }, 10);

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
