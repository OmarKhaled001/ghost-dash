<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Stops the installer with a message meant for the person running it.
 * Everything done so far stays done: re-running continues from here.
 */
final class InstallationFailed extends RuntimeException {}
