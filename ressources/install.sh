#!/bin/bash

if [ -f /var/www/html/plugins/spotify/ressources/spotify_version ]; then
	rm /var/www/html/plugins/spotify/ressources/spotify_version
fi
  
cd ../../plugins/spotify/ressources
  
echo "Début d'nstallation des dependances"

echo 0 > /tmp/spotify_dependancy

sudo rm -rf nodes_modules
sudo rm package-lock.json
  
echo 20 > /tmp/spotify_dependancy
  
sudo npm install spotify-web-api-node --save

echo 60 > /tmp/spotify_dependancy
  
sudo npm install https --save

echo 80 > /tmp/spotify_dependancy

sudo npm install dateformat --save

echo 100 > /tmp/spotify_dependancy

rm /tmp/spotify_dependancy
  
echo "Fin d'nstallation des dependances"

touch /var/www/html/plugins/spotify/ressources/spotify_version
  
exit 0