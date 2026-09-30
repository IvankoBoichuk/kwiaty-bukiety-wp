<?php

declare(strict_types=1);

use Frontenda\Blocks\SectionContext;

/** @var SectionContext $context */

echo view('frontenda-blocks.section.benefits-2', [
    'context' => $context,
])->render();
