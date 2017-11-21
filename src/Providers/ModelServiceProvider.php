<?php

namespace Slimkit\PlusLive\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModelServiceProvider extends ServiceProvider
{
    /**
     * Register the model service.
     *
     * @return void
     */
    public function register()
    {
        // Register morph map for polymorphic relations.
        $this->registerMorphMap();

        // Register user model macros tu the application.
        $this->registerUserMacros();
    }

    /**
     * Register user model macros tu the application.
     *
     * @return void
     */
    protected function registerUserMacros()
    {
        // The is define macro to User example.
        // User::macro('plus-lives', function () {
        //     return $this->hasMany(\Slimkit\PlusLive\Models\plus-live::class);
        // });
    }

    /**
     * Register morph map for polymorphic relations.
     *
     * @return void
     */
    protected function registerMorphMap()
    {
        // $this->morphMap([
        //     'plus-lives' => \Slimkit\PlusLive\Models\plus-live::class,
        // ]);
    }

    /**
     * Set or get the morph map for polymorphic relations.
     *
     * @param array|null $map
     * @param bool $merge
     * @return array
     */
    protected function morphMap(array $map = null, bool $merge = true): array
    {
        return Relation::morphMap($map, $merge);
    }
}
