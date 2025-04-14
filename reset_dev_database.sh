#! /bin/sh.
#use this to reset the database for development

cd ./app
bin/cake resetDatabase
bin/cake updateDatabase
#bin/cake migrations seed --seed DevLoad
