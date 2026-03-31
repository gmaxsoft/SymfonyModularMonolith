<?php

declare(strict_types=1);

namespace App\Modules\SampleModule\Controller;

use App\Modules\SampleModule\Service\SampleModuleService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SampleController
{
    public function __construct(
        private readonly SampleModuleService $sampleModuleService,
    ) {
    }

    #[Route('/sample-module/hello', name: 'sample_module_hello', methods: ['GET'])]
    public function hello(): JsonResponse
    {
        return new JsonResponse([
            'module' => 'SampleModule',
            'message' => $this->sampleModuleService->getWelcomeMessage(),
        ]);
    }
}
