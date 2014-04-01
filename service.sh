#! /bin/bash

nohup hhvm cli.php slave > /tmp/process1.out 2>&1 &
nohup hhvm cli.php slave > /tmp/process2.out 2>&1 &
nohup hhvm cli.php slave > /tmp/process3.out 2>&1 &
nohup hhvm cli.php slave > /tmp/process4.out 2>&1 &

