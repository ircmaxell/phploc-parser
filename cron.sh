#! /bin/bash

PROCS=`pgrep hhvm | wc -l`

cd /parser

while [ `pgrep hhvm | wc -l` -lt "2" ]; do
    echo "Spawning new process"
    nohup hhvm cli.php --max-requests="3" slave >> /tmp/process.log 2>&1 &
    sleep 1
done
