<?php
namespace Deployer;

require 'recipe/craftcms.php';

set('application', 'carlosmartin.dev');
set('repository', 'git@github.com:cmartinespinosa/carlosmartin.dev.git');
set('keep_releases', 3);

host('production')
    ->setHostname('your-server.com')
    ->setRemoteUser('your-user')
    ->setDeployPath('/var/www/your-domain')
    ->setIdentityFile('~/.ssh/id_ed25519')
    ->setSshMultiplexing(false);
