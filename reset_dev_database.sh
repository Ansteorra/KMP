#! /bin/sh.

cd ./app
bin/cake migrations rollback -t 000000000000
bin/cake migrations migrate
bin/cake migrations seed --seed DevLoad
