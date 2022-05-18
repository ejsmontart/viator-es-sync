#!/bin/bash

while true; do 

# sleep 2 minutes and repeat, we sleep first to let elastic search boot up
sleep 120; 

# run the job
php /usr/src/viatorsync/scripts/synchronize.php

done
