<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Composer's post-create-project hook. It starts the installer when there
 * is a person at a terminal to answer it, and otherwise says how to.
 */
#[AsCommand(name: 'dash:post-create', description: 'Run after composer create-project')]
#[Hidden]
#[Signature('dash:post-create')]
final class DashPostCreateCommand extends Command
{
    public function handle(): int
    {
        if ($this->canAskQuestions()) {
            // Not offered from here: this runs inside a Composer script, and
            // a development server must not live inside `composer
            // create-project` for the rest of the day.
            return $this->call('dash:install', ['--no-serve' => true]);
        }

        $this->newLine();
        $this->components->info('GhostDash is downloaded. One step is left:');
        $this->line('  Next: <options=bold>cd '.basename(base_path()).' && php artisan dash:install</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function canAskQuestions(): bool
    {
        // The bootstrap scripts set this: they start the installer
        // themselves, with the right options and a real terminal.
        if (filter_var(getenv('GHOSTDASH_SKIP_AUTO_INSTALL'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Composer exports this when it was started with --no-interaction,
        // which is what CI does.
        if (filter_var(getenv('COMPOSER_NO_INTERACTION'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // `laravel new` sets this while Composer creates the project: kits
        // are expected to wait. It then runs the hooks listed under
        // extra.laravel.installer in composer.json, which brings us back
        // here without the flag.
        if (filter_var(getenv('LARAVEL_INSTALLER_DEFER_HOOKS'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->input->isInteractive() && defined('STDIN') && stream_isatty(STDIN);
    }
}
