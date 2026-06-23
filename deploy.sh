#!/bin/bash

set -e

cd /home/clients/a3718940730c81023c4a2f7dadfef260/sites/tempo.antoninpamart.fr

git pull
php bin/console tailwind:build
php bin/console asset-map:compile
php bin/console cache:clear --env=prod

echo "Deploy terminé."
