#! /bin/bash

nohup hhvm cli.php --max-requests="5" slave > /tmp/process1.out 2> /tmp/process1.err &
nohup hhvm cli.php --max-requests="5" slave > /tmp/process2.out 2> /tmp/process2.err &

