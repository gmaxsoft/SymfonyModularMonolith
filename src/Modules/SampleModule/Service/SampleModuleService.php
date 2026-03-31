<?php

declare(strict_types=1);

namespace App\Modules\SampleModule\Service;

use App\Modules\SampleModule\Repository\SampleItemRepository;

final class SampleModuleService
{
    public function __construct(
        private readonly SampleItemRepository $items,
    ) {
    }

    public function getWelcomeMessage(): string
    {
        $count = $this->items->count([]);

        return \sprintf('SampleModule is active (sample_items in database: %d).', $count);
    }
}
