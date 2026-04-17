<?php

namespace App\Support;

/**
 * Process-wide flag used by model observers to short-circuit recursive
 * two-way syncs. The first observer in a sync chain flips the flag on;
 * any nested save fired by that observer sees the flag and skips its
 * own propagation, so A→B doesn't bounce back to A→B→A→…
 *
 * Usage inside an observer:
 *
 *     SyncGuard::wrap(function () use ($post) {
 *         $linked = $post->linked();
 *         $linked->fill([...])->save();
 *     });
 *
 * The nested observer for $linked will see SyncGuard::isSyncing() === true
 * and return early.
 */
class SyncGuard
{
    private static bool $active = false;

    public static function isSyncing(): bool
    {
        return self::$active;
    }

    /**
     * Run $fn while the flag is set. Re-entrant calls are no-ops so the
     * flag is cleared only by the outermost call that set it.
     */
    public static function wrap(callable $fn): void
    {
        if (self::$active) {
            $fn();

            return;
        }

        self::$active = true;
        try {
            $fn();
        } finally {
            self::$active = false;
        }
    }
}
