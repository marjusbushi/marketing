<?php

namespace App\Console\Commands;

use App\Enums\MarketingPermissionEnum as PermissionEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FixInfluencerPermissions extends Command
{
    protected $signature = 'permissions:fix-influencer {--role=1 : ID e rolit (default: 1 për super-admin)}';
    protected $description = 'Shton lejet e influencer-ve për një rol të caktuar';

    public function handle(): int
    {
        $roleId = $this->option('role');
        
        $this->info("Duke shtuar lejet e influencer-ve për rolin ID: {$roleId}");

        $newPermissions = [
            PermissionEnum::INFLUENCER_VIEW_ANY->value,
            PermissionEnum::INFLUENCER_VIEW->value,
            PermissionEnum::INFLUENCER_CREATE->value,
            PermissionEnum::INFLUENCER_UPDATE->value,
            PermissionEnum::INFLUENCER_PRODUCT_VIEW_ANY->value,
            PermissionEnum::INFLUENCER_PRODUCT_VIEW->value,
            PermissionEnum::INFLUENCER_PRODUCT_CREATE->value,
            PermissionEnum::INFLUENCER_PRODUCT_ACTIVATE->value,
            PermissionEnum::INFLUENCER_PRODUCT_RETURN->value,
            PermissionEnum::INFLUENCER_PRODUCT_CONVERT->value,
            PermissionEnum::INFLUENCER_PRODUCT_CANCEL->value,
        ];

        $role = DB::table('roles')->where('id', $roleId)->first();
        
        if (!$role) {
            $this->error("Roli me ID {$roleId} nuk u gjet!");
            return 1;
        }

        $existingPermissions = json_decode($role->permissions, true) ?? [];
        $updatedPermissions = array_unique(array_merge($existingPermissions, $newPermissions));

        DB::table('roles')
            ->where('id', $roleId)
            ->update([
                'permissions' => json_encode(array_values($updatedPermissions)),
                'updated_at'  => now(),
            ]);

        // Pastro cache-in e lejeve
        Cache::flush();
        $this->info('Cache u pastrua.');

        $this->info('Lejet u shtuan me sukses!');
        $this->info('Lejet e reja:');
        foreach ($newPermissions as $perm) {
            $this->line("  - {$perm}");
        }

        return 0;
    }
}
