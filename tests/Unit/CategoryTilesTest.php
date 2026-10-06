<?php

declare(strict_types=1);

use App\Catalog\CategoryTiles;

describe('CategoryTiles::normalize', function () {
    it('keeps a row that names a category', function () {
        $tiles = CategoryTiles::normalize([
            ['category' => '62', 'image' => '12', 'name' => 'RÓŻA'],
        ]);

        expect($tiles)->toBe([
            ['category' => 62, 'image' => 12, 'name' => 'RÓŻA'],
        ]);
    });

    it('drops a row with no category, because the tile would lead nowhere', function () {
        $tiles = CategoryTiles::normalize([
            ['category' => 0, 'image' => 12, 'name' => 'Lilia'],
            ['image' => 12, 'name' => 'Frezja'],
        ]);

        expect($tiles)->toBe([]);
    });

    it('keeps a row whose picture and caption are empty, because the category fills them', function () {
        $tiles = CategoryTiles::normalize([
            ['category' => 74],
        ]);

        expect($tiles)->toBe([
            ['category' => 74, 'image' => 0, 'name' => ''],
        ]);
    });

    it('strips markup from the caption and refuses negative ids', function () {
        $tiles = CategoryTiles::normalize([
            ['category' => -5, 'image' => 1, 'name' => 'x'],
            ['category' => 65, 'image' => -5, 'name' => '<b>Gerbera</b>'],
        ]);

        expect($tiles)->toBe([
            ['category' => 65, 'image' => 0, 'name' => 'Gerbera'],
        ]);
    });

    it('ignores anything that is not a list of rows', function () {
        expect(CategoryTiles::normalize('not a list'))->toBe([])
            ->and(CategoryTiles::normalize(null))->toBe([])
            ->and(CategoryTiles::normalize([null, 'x']))->toBe([]);
    });
});
