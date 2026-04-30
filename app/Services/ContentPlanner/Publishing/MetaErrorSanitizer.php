<?php

namespace App\Services\ContentPlanner\Publishing;

/**
 * Strip secrets out of any string that might leak into logs, the
 * `error_message` column, or the UI failure banner.
 *
 * Guzzle / Laravel HTTP-client exceptions sometimes echo the full request
 * URL into the message — including the `?access_token=EAA…` query param
 * we attach to every Graph API call. Without this filter, a single network
 * blip would persist a long-lived page token in plain DB text and render
 * it to anyone with planner view access.
 *
 * Apply at the boundary: catch the exception, sanitize, THEN log / persist /
 * return. Never trust raw `$e->getMessage()`.
 */
class MetaErrorSanitizer
{
    public static function redact(?string $message): string
    {
        if ($message === null || $message === '') {
            return '';
        }

        // Most common: ?access_token=EAA…&… or &access_token=EAA…
        $message = preg_replace('/([?&]access_token=)[^&\s"\'\\\\]+/i', '$1***', $message);

        // Bearer headers occasionally end up in stringified exceptions.
        $message = preg_replace('/(Bearer\s+)[A-Za-z0-9._-]+/i', '$1***', $message);

        // Belt-and-suspenders: any raw EAA-prefixed page-token-looking string
        // (Meta page tokens always start with EAA and are >40 chars).
        $message = preg_replace('/EAA[A-Za-z0-9]{40,}/', 'EAA***', $message);

        return $message;
    }
}
