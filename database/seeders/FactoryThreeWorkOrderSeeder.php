<?php

namespace Database\Seeders;

class FactoryThreeWorkOrderSeeder extends Factory3WorkOrderSeeder
{
    public function run(int $count = 20): void
    {
        $previousFactoryId = env('SEED_FACTORY_ID');

        $this->setEnv('SEED_FACTORY_ID', 3);

        try {
            parent::run($count);
        } finally {
            if ($previousFactoryId !== null) {
                $this->setEnv('SEED_FACTORY_ID', (int) $previousFactoryId);
            } else {
                $this->clearEnv('SEED_FACTORY_ID');
            }
        }
    }

    private function setEnv(string $key, int $value): void
    {
        $stringValue = (string) $value;
        putenv("{$key}={$stringValue}");
        $_ENV[$key] = $stringValue;
        $_SERVER[$key] = $stringValue;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

class DatabaseSeedersFactoryThreeWorkOrderSeeder extends FactoryThreeWorkOrderSeeder
{
}
