<?php

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('space_user', function (Blueprint $table) {
            $table->foreignUuid('space_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('viewer');
            $table->timestamps();
            $table->primary(['space_id', 'user_id']);
        });

        $spaceIds = Space::pluck('id')->all();

        User::where('status', 'active')->get()->each(function (User $user) use ($spaceIds) {
            if ($spaceIds === []) {
                return;
            }

            $user->visibleSpaces()->syncWithoutDetaching(
                collect($spaceIds)->mapWithKeys(fn ($id) => [$id => ['access_level' => 'viewer']])->all()
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_user');
    }
};
