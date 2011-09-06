#!/bin/bash
nohup php smtpd.php > smtp.log 2> smtp.err < /dev/null &
