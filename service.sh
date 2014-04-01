#! /bin/bash

nohup php5 cli.php slave > /tmp/process1.out 2> /tmp/process1.err &
nohup php5 cli.php slave > /tmp/process2.out 2> /tmp/process2.err &

