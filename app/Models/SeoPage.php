<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityLog;
use Database\Factories\SeoPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * SEO for one page of the website, on top of the site-wide SEO settings.
 *
 * @property int $id
 * @property string $path
 * @property string|null $title
 * @property string|null $description
 * @property string|null $keywords
 * @property string|null $og_image_path
 * @property string|null $canonical_url
 * @property bool|null $robots_index
 * @property bool|null $robots_follow
 */
#[Fillable(['path', 'title', 'description', 'keywords', 'og_image_path', 'canonical_url', 'robots_index', 'robots_follow'])]
final class SeoPage extends Model
{
    /** @use HasFactory<SeoPageFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityLog::Seo)
            ->logOnly(['path', 'title', 'description', 'keywords', 'og_image_path', 'canonical_url', 'robots_index', 'robots_follow'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }
}
