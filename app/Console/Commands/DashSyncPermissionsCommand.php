<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\PermissionSyncService;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'dash:sync-permissions', description: 'Create missing permissions and grant them all to super_admin')]
#[Signature('dash:sync-permissions')]
final class DashSyncPermissionsCommand extends Command
{
    /**
     * Safe on every deployment: it only inserts rows that are missing and
     * never writes a file. Policies are generated while developing, with
     * shield:generate, and committed.
     */
    public function handle(PermissionSyncService $permissions): int
    {
        $result = $permissions->sync();

        foreach ($result['created'] as $permission) {
            $this->components->twoColumnDetail($permission, '<fg=green>created</>');
        }

        $this->components->info(sprintf(
            '%d permission(s) on the [%s] guard, %d new. The %s role holds all of them.',
            $result['total'],
            Role::GUARD,
            count($result['created']),
            Role::SUPER_ADMIN,
        ));

        return self::SUCCESS;
    }
}
