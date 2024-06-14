#! /bin/sh.

cd ./app
bin/cake resetDatabase
bin/cake updateDatabase
bin/cake migrations seed --seed DevLoad
