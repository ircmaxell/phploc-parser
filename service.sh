#! /bin/bash

nohup hhvm cli.php --max-requests="5" slave > /dev/null 2>&1 &
nohup hhvm cli.php --max-requests="5" slave > /dev/null 2>&1 &

