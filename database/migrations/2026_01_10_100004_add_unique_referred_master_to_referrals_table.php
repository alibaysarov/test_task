<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('referrals')
            ->select('referred_master_id')
            ->groupBy('referred_master_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('referred_master_id');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot add unique referrals.referred_master_id: duplicate referred master IDs exist: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('referrals', function ($table) {
            $table->unique('referred_master_id');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function ($table) {
            $table->dropUnique(['referred_master_id']);
        });
    }
};
