<?php

declare(strict_types=1);

use Frontenda\Blocks\SectionContext;

/** @var SectionContext $context */

echo view('frontenda-blocks.section.reviews-1', [
    'context' => $context,
])->render();