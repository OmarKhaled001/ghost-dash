<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Installer\EnvFile;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'dash:seo', description: 'Switch SEO on or off: the SEO menu in the panel, <x-seo> and GET /api/v1/seo')]
#[Signature('dash:seo
        {--disable : Switch SEO off again. Nothing that was entered is deleted}')]
final class DashSeoCommand extends Command
{
    public function handle(): int
    {
        $enable = ! $this->option('disable');

        // A server's environment comes from its deployment, not from a
        // command run on the server.
        if ($this->laravel->isProduction()) {
            $this->components->error('In production, set GHOSTDASH_SEO='.($enable ? 'true' : 'false').' in the server environment and run php artisan optimize.');

            return self::FAILURE;
        }

        new EnvFile($this->laravel->environmentPath().DIRECTORY_SEPARATOR.'.env')
            ->set(['GHOSTDASH_SEO' => $enable ? 'true' : 'false']);

        config(['ghostdash.seo.enabled' => $enable]);

        if ($this->laravel->configurationIsCached()) {
            $this->call('config:clear');
        }

        if (! $enable) {
            $this->components->info('SEO is off. What was entered is kept for when it is switched on again.');

            return self::SUCCESS;
        }

        $this->components->info('SEO is on (GHOSTDASH_SEO=true in .env).');
        $this->components->bulletList([
            'The panel has an SEO menu: Site SEO for the whole site, Pages for SEO per page.',
            'Admins need the manage_seo permission for Site SEO, and the SEO page permissions for Pages. Super admins have both.',
            'In a Blade layout, put <x-seo /> inside <head>. A separate frontend reads GET /api/v1/seo?path=/about.',
            'On a server, set GHOSTDASH_SEO=true in its environment as well.',
        ]);

        return self::SUCCESS;
    }
}
