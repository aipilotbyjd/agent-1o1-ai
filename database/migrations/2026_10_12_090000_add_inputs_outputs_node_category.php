<?php

use App\Enums\NodeCategory as NodeCategoryEnum;
use Database\Seeders\NodeCategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Input and Output steps are filed under their own core category. Core
 * categories otherwise only arrive through `NodeCategorySeeder`, so a
 * database that was already seeded gets it (and the shifted order) by
 * re-running that seeder, which upserts by slug. An unseeded one is left for
 * the seeder itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('node_categories')->where('slug', NodeCategoryEnum::TriggersEvents->value)->exists()) {
            (new NodeCategorySeeder)->run();
        }
    }

    public function down(): void
    {
        DB::table('node_categories')->where('slug', NodeCategoryEnum::InputsOutputs->value)->delete();
    }
};
