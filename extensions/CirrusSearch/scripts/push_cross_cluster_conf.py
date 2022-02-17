#!/usr/bin/env python

import argparse
import requests
import json

# Simple script to help setup cross cluster conf

def main(args):
    server = args.server
    seeds = args.ccc

    if len(seeds) == 0:
        raise ValueError("seeds file required")

    conf = {}
    for cc in seeds:
        clusterconf = as_cc(cc)
        conf.update(clusterconf)

    if len(conf) != len(seeds):
        print(json.dumps(clusterconf, indent=4))
        raise ValueError("Duplicated cluster names?")

    conf = {
        "persistent": {
            "search.remote": conf
        }
    }
    resp = requests.put(server, json=conf)
    if resp.status_code >= 400:
        raise Exception("Failed to proceed: {}".format(resp.text))


def as_cc(cc):
    splitted = cc.split('=', 2)
    if len(splitted) != 2:
        raise ValueError("clustername=seedfile format required")
    name, seedfile = splitted
    seeds = []
    with open(seedfile) as file:
        seeds = file.read().splitlines()
    if len(seeds) == 0:
        raise ValueError("seedfile {} must contain at least one seed".format(seedfile))

    return {
        name: {
            "seeds": seeds
        }
    }

parser = argparse.ArgumentParser(description="elastic: push crosscluster conf")

parser.add_argument('server')
parser.add_argument('--ccc', help='cross cluster conf: name=seedsfile', nargs="*")

if __name__ == '__main__':
    main(parser.parse_args())
