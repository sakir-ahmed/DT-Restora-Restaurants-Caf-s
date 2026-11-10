<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Print CSS for invoices: one set of rules per paper size and one per style.
 * The JS copy in resources/js/lib/receipt.js (used for sales that are still
 * queued offline) mirrors these rules; change both together.
 */
final class ReceiptStyle
{
    public const LAYOUTS = ['classic', 'compact', 'bold', 'modern', 'detailed', 'minimal'];

    public const PAPERS = ['58mm', '80mm', 'a5', 'a4'];

    public const DEFAULT_PAPER = '80mm';

    /** Paper sizes that are wide enough for a table with a rate column. */
    public static function isWide(string $paper): bool
    {
        return in_array($paper, ['a5', 'a4'], true);
    }

    /**
     * Whether the item list is drawn as a table (item, qty, rate, amount).
     */
    public static function isTabular(string $layout, string $paper): bool
    {
        return $layout === 'detailed' || self::isWide($paper);
    }

    public static function css(string $layout, string $paper): string
    {
        $layout = in_array($layout, self::LAYOUTS, true) ? $layout : 'classic';
        $paper = in_array($paper, self::PAPERS, true) ? $paper : self::DEFAULT_PAPER;

        $page = match ($paper) {
            '58mm' => '@page { size: 58mm auto; margin: 0; } body { width: 50mm; padding: 3mm 0; font-size: 10px; line-height: 1.35; }',
            'a5' => '@page { size: A5; margin: 10mm; } body { width: auto; max-width: 128mm; padding: 0; font-size: 13px; line-height: 1.5; }',
            'a4' => '@page { size: A4; margin: 14mm; } body { width: auto; max-width: 182mm; padding: 0; font-size: 14px; line-height: 1.5; }',
            default => '@page { size: 80mm auto; margin: 0; } body { width: 72mm; padding: 4mm 0; font-size: 11px; line-height: 1.45; }',
        };

        $screen = self::isWide($paper)
            ? '@media screen { body { background: #fff; padding: 12mm; box-shadow: 0 0 8px rgba(0,0,0,.25); max-width: none; width: '.($paper === 'a4' ? '210mm' : '148mm').'; box-sizing: border-box; } }'
            : '@media screen { body { background: #fff; padding: 16px 0; box-shadow: 0 0 8px rgba(0,0,0,.25); } }';

        $style = match ($layout) {
            'compact' => 'body { font-size: 90%; line-height: 1.3; } .hr { margin: 3px 0; } .addon-line { font-size: 90%; }',
            'bold' => '.hr { border-top: 2px solid #000; } .grand td { font-size: 150%; padding: 4px 0; border-top: 2px solid #000; border-bottom: 2px solid #000; }',
            'modern' => '.brand { background: #000; color: #fff; padding: 6px 4px; margin-bottom: 6px; } .brand .large { font-size: 125%; } .hr { border-top: 1px solid #999; } .meta { display: flex; justify-content: space-between; gap: 8px; } .grand td { background: #000; color: #fff; padding: 5px 6px; font-size: 125%; } table.items thead th { border-bottom: 1px solid #000; }',
            'detailed' => '.hr { border-top: 1px solid #000; } table.items thead th { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 0; } table.items tbody td { border-bottom: 1px dotted #999; padding: 3px 0; } .grand td { border-top: 1px solid #000; border-bottom: 3px double #000; padding: 4px 0; }',
            'minimal' => '.hr { border-top: 0; margin: 8px 0; } .bold { font-weight: 600; } .grand td { border-top: 1px solid #000; padding-top: 4px; } .addon-line { color: #333; }',
            default => '',
        };

        return <<<CSS
        {$page}
        body { font-family: 'Noto Sans Bengali', 'Courier New', monospace; margin: 0 auto; color: #000; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .large { font-size: 118%; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        table.items td, table.items th { vertical-align: top; padding: 1px 0; }
        td.right, th.right { text-align: right; }
        th { text-align: left; font-weight: 700; }
        .addon-line { padding-left: 8px; font-size: 92%; }
        .totals td { padding: 1px 0; }
        {$screen}
        {$style}
        @media print { .no-print { display: none; } body { box-shadow: none; } }
        CSS;
    }
}
