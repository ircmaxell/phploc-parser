#! /bin/bash

PROCS=`pgrep hhvm | wc -l`

while [ `pgrep hhvm | wc -l` -lt "2" ]; do
    echo "Spawning new process"
    nohup hhvm cli.php --max-requests="5" slave > /dev/null 2>&1 &
done
