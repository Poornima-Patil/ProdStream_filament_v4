<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FactoryThreeDataSeeder extends Seeder
{
    /**
     * Set runtime environment variables so the existing seeders
     * target the desired factory and counts.
     */
    protected function setEnv(string $key, mixed $value): void
    {
        $value = (string) $value;
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function run(): void
    {
        $factoryId = 3;

        // Configure target factory and supporting options for dependent seeders.
        $this->setEnv('SEED_FACTORY_ID', $factoryId);
        $this->setEnv('NEW_OPERATORS_COUNT', 15);       // ensure UsersTableSeeder creates operator accounts
        $this->setEnv('SEED_CUSTOMER_COUNT', 5);
        $this->setEnv('SEED_WORK_START_DATE', now()->startOfMonth()->toDateString());

        // Order is important because several seeders depend on prior data.
        $this->call([
            DepartmentsTableSeeder::class,
            ShiftsTableSeeder::class,
            MachineGroupsTableSeeder::class,
            OperatorProficienciesTableSeeder::class,
            HoldReasonsTableSeeder::class,
            ScrappedReasonsTableSeeder::class,
            CustomerInformationTableSeeder::class,
            PartNumbersTableSeeder::class,
            MachinesTableSeeder::class,
            UsersTableSeeder::class,
            OperatorsTableSeeder::class,
            PurchaseOrdersTableSeeder::class,
            BomsTableSeeder::class,
        ]);
    }
}
