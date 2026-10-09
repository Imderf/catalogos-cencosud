<?php

use Database\Seeders\BrandSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed([RoleSeeder::class, SectorSeeder::class, BrandSeeder::class]))
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
