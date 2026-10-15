<?php

namespace Deployer;

require 'recipe/laravel.php';

// Set variables
set('default_timeout', null);
set('repository', 'git@github.com:grlf/one-time-secret.git');


// SSH goes to the droplet's IP, so it doesn't depend on where DNS points.
// `dep provision` creates deploy_path as the deployer user, so it has to be
// somewhere that user can write without sudo.
host('production')
    ->set('keep_releases', 5)
    ->set('hostname', 'secret.greenleafmedia.com')
    ->set('branch', 'master')
    ->set('deploy_path', '~/secret.greenleafmedia.com')
    ->set('remote_user', 'deployer');


// `@vite` reads public/build/manifest.json at runtime; Laravel never generates
// it for you. Cloud/Forge run `npm run build` as part of their pipelines, but
// this is a hand-rolled Deployer setup, so we have to do it ourselves.
desc('Install node modules and build Vite assets for the new release');
task('npm:build', function () {
    run('cd {{release_or_current_path}} && npm ci && npm run build');
});
after('deploy:vendors', 'npm:build');

// Secrets live in Redis; the app has no database to migrate.
task('artisan:migrate')->disable();