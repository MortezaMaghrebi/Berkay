<?php

namespace Webkul\Berkay\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Berkay\Repositories\ArticleRepository;

class PublishStarterContent extends Command
{
    /** Register the non-destructive content installer. */
    protected $signature = 'berkay:publish-guides';

    /** Describe the content installer. */
    protected $description = 'Publish original Persian buying guides without overwriting existing articles.';

    /** Add only articles whose slugs do not already exist. */
    public function handle(ArticleRepository $articles): int
    {
        $content = json_decode(file_get_contents(__DIR__.'/../../Resources/content/starter-articles.json'), true, 512, JSON_THROW_ON_ERROR);

        foreach ($content as $article) {
            $previousBodyHash = $article['previous_body_sha256'] ?? null;
            unset($article['previous_body_sha256']);
            $existing = $articles->findOneByField('slug', $article['slug']);

            if (! $existing) {
                $articles->create(array_merge($article, ['status' => 'published', 'published_at' => now()]));
            } elseif ($previousBodyHash && hash_equals($previousBodyHash, hash('sha256', $existing->body))) {
                $articles->update(['body' => $article['body']], $existing->id);
            }
        }

        $this->info('Editorial guides are available in the magazine.');

        return self::SUCCESS;
    }
}
