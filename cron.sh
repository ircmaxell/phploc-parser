#! /bin/bash

PROCS=`pgrep hhvm | wc -l`

cd /parser

while [ `pgrep hhvm | wc -l` -lt "4" ]; do
    echo "Spawning new process"
    nohup hhvm cli.php --max-requests="10" slave >> /tmp/process.log 2>&1 &
    sleep 1
done
