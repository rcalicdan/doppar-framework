<?php

namespace Phaseolies\Database\Migration;

class Seeder
{
    /**
     * Call the given seeder class(es).
     *
     * @param array<int, class-string>|class-string $seeders
     */
    protected function call(array|string $seeders): void
    {
        $list = is_array($seeders) ? $seeders : [$seeders];

        foreach ($list as $seeder) {
            if (!class_exists($seeder)) {
                throw new \RuntimeException("Seeder class {$seeder} not found");
            }

            $instance = app($seeder);

            if (!is_object($instance)) {
                throw new \RuntimeException("Unable to instantiate seeder [{$seeder}]");
            }

            if (method_exists($instance, 'run')) {
                $instance->run();
            } else {
                throw new \RuntimeException("Seeder {$seeder} must have a run() method");
            }

            if (php_sapi_name() === 'cli') {
                echo "Seeded: {$seeder}\n";
            }
        }
    }
}