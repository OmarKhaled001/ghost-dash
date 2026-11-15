<?php

declare(strict_types=1);

namespace App\Support\Telescope;

use App\Support\SensitiveData;
use Laravel\Telescope\Watchers\RequestWatcher;
use Override;

/**
 * Telescope hides request and response parameters by exact path from the
 * top level, which misses the two shapes this application actually sends:
 * Livewire posts form fields as components.0.updates."data.password" and
 * repeats them inside a JSON snapshot, and the API returns its token at
 * data.token. Everything the watcher is about to store (headers, payload,
 * session, JSON responses) passes through hideParameters(), so masking by
 * key name there covers all of them at any depth.
 */
final class RedactingRequestWatcher extends RequestWatcher
{
    /**
     * @param  mixed  $data
     * @param  array<int, string>  $hidden
     * @return mixed
     */
    #[Override]
    protected function hideParameters($data, $hidden)
    {
        $data = parent::hideParameters($data, $hidden);

        return is_array($data) ? SensitiveData::redact($data, insideJson: true) : $data;
    }
}
