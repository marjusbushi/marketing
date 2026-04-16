<?php

namespace App\Enums;

/**
 * Marketing-scoped permissions.
 *
 * Mirrors the marketing-relevant subset of DIS PermissionEnum.
 * Values MUST match what's stored in app_role_permissions
 * so HasMarketingRole::hasMarketingPermission() works correctly.
 */
enum MarketingPermissionEnum: string
{
    // ── Module Access ─────────────────────────────
    case MODULE_MARKETING_ACCESS = 'module.marketing_access';

    // ── Content Planner ───────────────────────────
    case CONTENT_PLANNER_VIEW    = 'content_planner.view';
    case CONTENT_PLANNER_CREATE  = 'content_planner.create';
    case CONTENT_PLANNER_EDIT    = 'content_planner.edit';
    case CONTENT_PLANNER_DELETE  = 'content_planner.delete';
    case CONTENT_PLANNER_APPROVE = 'content_planner.approve';
    case CONTENT_PLANNER_PUBLISH = 'content_planner.publish';
    case CONTENT_PLANNER_MANAGE  = 'content_planner.manage';

    // ── Analytics ─────────────────────────────────
    case ANALYTICS_VIEW   = 'analytics.view';
    case ANALYTICS_MANAGE = 'analytics.manage';

    // ── Influencer (profile management only) ──────
    case INFLUENCER_VIEW_ANY = 'influencer.view_any';
    case INFLUENCER_VIEW     = 'influencer.view';
    case INFLUENCER_CREATE   = 'influencer.create';
    case INFLUENCER_UPDATE   = 'influencer.update';

    // ── Influencer Products (delegated to DIS) ────
    case INFLUENCER_PRODUCT_VIEW_ANY = 'influencer_product.view_any';
    case INFLUENCER_PRODUCT_VIEW     = 'influencer_product.view';
    case INFLUENCER_PRODUCT_CREATE   = 'influencer_product.create';
    case INFLUENCER_PRODUCT_ACTIVATE = 'influencer_product.activate';
    case INFLUENCER_PRODUCT_RETURN   = 'influencer_product.return';
    case INFLUENCER_PRODUCT_CONVERT  = 'influencer_product.convert';
    case INFLUENCER_PRODUCT_CANCEL   = 'influencer_product.cancel';

    /**
     * All permission values as a flat array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Permissions that a marketing-admin role should have.
     */
    public static function adminPermissions(): array
    {
        return self::values();
    }

    /**
     * Permissions that a marketing-viewer role should have.
     */
    public static function viewerPermissions(): array
    {
        return [
            self::MODULE_MARKETING_ACCESS->value,
            self::CONTENT_PLANNER_VIEW->value,
            self::ANALYTICS_VIEW->value,
            self::INFLUENCER_VIEW_ANY->value,
            self::INFLUENCER_VIEW->value,
            self::INFLUENCER_PRODUCT_VIEW_ANY->value,
            self::INFLUENCER_PRODUCT_VIEW->value,
        ];
    }
}
