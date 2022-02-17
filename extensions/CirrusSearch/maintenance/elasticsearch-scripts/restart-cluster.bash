#!/usr/bin/env bash
set -e

es_server_prefix=elastic10
es_server_suffix=.eqiad.wmnet
first_server_index=7
nb_of_servers_in_cluster=31

for i in $(seq -w ${first_server_index} ${nb_of_servers_in_cluster}); do
    server="${es_server_prefix}${i}${es_server_suffix}"

    echo "ready to start restart ${server}"
    echo "make sure icinga alerts are disabled for ${server}"
    echo "please log the action to \#wikimedia-operations"
    echo "!log restarting elasticsearch server ${server}"
    echo "[ENTER] to continue, [CTRL]-[C] to stop"
    read

    echo "disabling replication"
    until ssh ${server} es-tool stop-replication
    do
        echo "failed to stop replication, trying again"
    done
    echo "flushing markers"
    ssh ${server} curl -s -XPOST '127.0.0.1:9200/_flush/synced?pretty'

    echo "rebooting server"
    ssh ${server} sudo reboot

    echo "waiting for server to be up"
    until ssh ${server} true &> /dev/null; do
        echo -n .
        sleep 1
    done
    echo "server is up"

    echo "waiting for elasticsearch to be started"
    until ssh ${server} curl -s 127.0.0.1:9200/_cat/health; do
        echo -n '.'
        sleep 1
    done
    echo "elasticsearch is started"

    echo "enabling replication"
    until ssh ${server} es-tool start-replication
    do
        echo "failed to start replication, trying again"
    done

    echo "waiting for cluster recovery"
    ssh ${server} "until curl -s 127.0.0.1:9200/_cat/health | grep green; do echo -n .; sleep 10; done"

    echo "${server} upgraded, please test"
    echo "re-enable icinga alerts for ${server}"
    echo "[ENTER] to continue, [CTRL]-[C] to stop"
    read
    echo "Done for ${server}"
    echo "=============================================="
done
