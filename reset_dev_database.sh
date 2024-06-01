#! /bin/sh.

cd ./app
bin/cake migrations rollback
bin/cake migrations migrate
bin/cake migrations seed --seed LoadDev
