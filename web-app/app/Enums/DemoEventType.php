<?php

declare(strict_types=1);

namespace App\Enums;

enum DemoEventType: string
{
    case SessionStart = 'session_start';
    case PageView = 'page_view';
    case MenuView = 'menu_view';
    case ItemView = 'item_view';
    case AddToCart = 'add_to_cart';
    case CheckoutStart = 'checkout_start';
    case OrderPlaced = 'order_placed';
    case AdminLogin = 'admin_login';
    case AdminView = 'admin_view';
    case TourStep = 'tour_step';
    case FeatureClick = 'feature_click';
    case CtaClick = 'cta_click';

    /**
     * Funnel stage this event proves the visitor reached, or null when it
     * does not move the funnel.
     */
    public function stage(): ?DemoStage
    {
        return match ($this) {
            self::MenuView, self::ItemView => DemoStage::BrowsedMenu,
            self::AddToCart => DemoStage::AddedToCart,
            self::CheckoutStart => DemoStage::Checkout,
            self::OrderPlaced => DemoStage::Ordered,
            self::AdminLogin, self::AdminView => DemoStage::TriedAdmin,
            default => null,
        };
    }
}
