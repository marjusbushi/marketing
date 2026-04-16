<?php

namespace App\Services\ContentPlanner;

use App\Models\Content\ContentShareLink;
use Illuminate\Support\Facades\Hash;

class ShareLinkService
{
    public function createLink(string $shareableType, int $shareableId, int $userId, array $options = []): ContentShareLink
    {
        return ContentShareLink::create([
            'token' => ContentShareLink::generateToken(),
            'shareable_type' => $shareableType,
            'shareable_id' => $shareableId,
            'created_by' => $userId,
            'permission' => $options['permission'] ?? 'view',
            'password_hash' => isset($options['password']) ? Hash::make($options['password']) : null,
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    public function deactivateLink(ContentShareLink $link): void
    {
        $link->update(['is_active' => false]);
    }

    public function getLinksFor(string $shareableType, int $shareableId)
    {
        return ContentShareLink::where('shareable_type', $shareableType)
            ->where('shareable_id', $shareableId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function cleanupExpired(): int
    {
        return ContentShareLink::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }
}
