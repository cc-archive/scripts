#!/bin/sh

HOST=creativecommons.org
URL_PATH=/
TIMEOUT=60
CCENGINE_MAXMEM=750000
CCENGINE_MEM=$(ps u -d | grep runzope | grep -v grep | awk '{print $6}')

HAS_ISSUE="false"
SYN_RECV=0
ESTABLISHED=0
TIME_WAIT=0
FIN_WAIT1=0
FIN_WAIT2=0


# First check cc.engine, as it may be causing
# resource problems for Varnish and/or Apache

# Make sure that cc.engine isn't using too much memory
if [ $CCENGINE_MEM -gt $CCENGINE_MAXMEM ]; then
    echo "cc.engine memory usage at $CCENGINE_MEM"
    echo "Restarting cc.engine ..."
    /etc/init.d/cc_engine-run-cc_engine restart
    HAS_ISSUE="true"
fi

# Check Apache on port 8080
wget -O - --timeout=${TIMEOUT} http://${HOST}:8080${URL_PATH} &> /dev/null
if [ $? -ne 0 ]
then
    echo "Apache seems to be having a problem.  Restarting Apache ..."
    /etc/init.d/apache2 stop
    /etc/init.d/apache2 start
    # Wait a couple seconds for Apache to settle down
    sleep 5
    HAS_ISSUE="true"
fi

# Check Varnish on port 80
wget -O - --timeout=${TIMEOUT} http://${HOST}${URL_PATH} &> /dev/null
if [ $? -ne 0 ]
then
    echo "Varnish seems to be having a problem.  Restarting Varnish ..."
    /etc/init.d/varnish stop
    /etc/init.d/varnish start
    HAS_ISSUE="true"
fi

# Output some basic stats of connections states
# near the time of this problem
if [ $HAS_ISSUE = "true" ]
then
    CONNS=$(netstat -nA inet | grep -v 127\.0)
    IFS=$'\n'
    for conn in $CONNS
    do
        STATE=$(echo $conn | awk '{print $6}')
        case $STATE in
            SYN_RECV)
                SYN_RECV=$(($SYN_RECV + 1))
                ;;
            ESTABLISHED)
                ESTABLISHED=$(($ESTABLISHED + 1))
                ;;
            TIME_WAIT)
                TIME_WAIT=$(($TIME_WAIT + 1))
                ;;
            FIN_WAIT1)
                FIN_WAIT1=$(($FIN_WAIT1 + 1))
                ;;
            FIN_WAIT2)
                FIN_WAIT2=$(($FIN_WAIT2 + 1))
                ;;
        esac
    done

    # Display the stats for each state
    echo ""
    echo "SYN_RECV: $SYN_RECV"
    echo "ESTABLISHED: $ESTABLISHED"
    echo "TIME_WAIT: $TIME_WAIT"
    echo "FIN_WAIT1: $FIN_WAIT1"
    echo "FIN_WAIT2: $FIN_WAIT2"
    echo ""

    # Display the first 15 lines of top
    top -b -n 1 | head -n 15

    # Display the number of unique connections (IPs)
    #UNIQ_CONNS=$(echo $CONNS | awk '{print $5}' | cut -d: -f1 | sort | uniq | wc -l)
    #echo $UNIQ_CONNS;
fi
