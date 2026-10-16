<?php

declare(strict_types=1);

use App\SEO\FloristSchema;

/*
 * The few WordPress calls FloristSchema::graph() makes, answering for the
 * production host. Defined here rather than in wp-stubs.php because nothing
 * else needs them.
 */
if (! class_exists('WP_User')) {
    class WP_User
    {
        public function __construct(public int $ID) {}
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://kwiaty-bukiety.com.pl' . $path;
    }
}

if (! function_exists('wp_get_upload_dir')) {
    function wp_get_upload_dir(): array
    {
        return ['baseurl' => 'https://kwiaty-bukiety.com.pl/app/uploads'];
    }
}

if (! function_exists('get_user_by')) {
    function get_user_by(string $field, string $value): WP_User|false
    {
        $ids = $GLOBALS['kb_test_users'] ?? ['katrinamsl' => 1, 'igor' => 2, 'inna' => 3];

        return isset($ids[$value]) ? new WP_User($ids[$value]) : false;
    }
}

if (! function_exists('get_author_posts_url')) {
    function get_author_posts_url(int $id): string
    {
        $logins = [1 => 'katrinamsl', 2 => 'igor', 3 => 'inna'];

        return 'https://kwiaty-bukiety.com.pl/author/' . $logins[$id] . '/';
    }
}

describe('FloristSchema::graph', function () {
    afterEach(function () {
        unset($GLOBALS['kb_test_users']);
    });

    it('publishes the business the way production did', function () {
        $florist = FloristSchema::graph()[0];

        expect($florist['@type'])->toBe('Florist')
            ->and($florist['@id'])->toBe('https://kwiaty-bukiety.com.pl/#florist')
            ->and($florist['url'])->toBe('https://kwiaty-bukiety.com.pl/')
            ->and($florist['address'])->toBe([
                '@type' => 'PostalAddress',
                'streetAddress' => 'ul. Podwiejska 39',
                'addressLocality' => 'Milanówek',
                'postalCode' => '05-822',
                'addressCountry' => 'PL',
            ])
            ->and($florist['telephone'])->toBe('+48 509 879 036')
            ->and($florist['logo']['url'])->toBe('https://kwiaty-bukiety.com.pl/app/uploads/2024/11/logo.svg');
    });

    it('links the three authors both ways', function () {
        $graph = FloristSchema::graph();

        expect($graph)->toHaveCount(4)
            ->and($graph[0]['employee'])->toBe([
                ['@id' => 'https://kwiaty-bukiety.com.pl/author/katrinamsl/#person'],
                ['@id' => 'https://kwiaty-bukiety.com.pl/author/igor/#person'],
                ['@id' => 'https://kwiaty-bukiety.com.pl/author/inna/#person'],
            ])
            ->and($graph[3])->toMatchArray([
                '@type' => 'Person',
                'name' => 'Inna',
                'jobTitle' => 'Florystka',
                'url' => 'https://kwiaty-bukiety.com.pl/author/inna/',
                'worksFor' => ['@id' => 'https://kwiaty-bukiety.com.pl/#florist'],
            ]);
    });

    it('leaves out an author who does not exist, so no employee points at a 404', function () {
        $GLOBALS['kb_test_users'] = ['katrinamsl' => 1, 'inna' => 3];

        $graph = FloristSchema::graph();

        expect($graph)->toHaveCount(3)
            ->and(array_column($graph[0]['employee'], '@id'))->not->toContain('https://kwiaty-bukiety.com.pl/author/igor/#person');
    });

    it('drops the employee list when there are no authors at all', function () {
        $GLOBALS['kb_test_users'] = [];

        $graph = FloristSchema::graph();

        expect($graph)->toHaveCount(1)
            ->and($graph[0])->not->toHaveKey('employee');
    });
});
