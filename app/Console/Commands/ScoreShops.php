<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\KhaajaScore;
use Illuminate\Console\Command;

class ScoreShops extends Command
{
    protected $signature = 'shops:score';

    protected $description = 'Recalculate every shop\'s Kh-aaja score for homepage ranking';

    public function handle(KhaajaScore $score): int
    {
        $count = 0;

        Shop::query()->whereNull('suspended_at')->chunkById(100, function ($shops) use ($score, &$count) {
            foreach ($shops as $shop) {
                $score->refresh($shop);
                $count++;
            }
        });

        $this->info("Scored {$count} shop(s).");

        return self::SUCCESS;
    }
}
