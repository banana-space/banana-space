#!/bin/sh

# $1 would be the title/substring of the title that parserTests.js uses to filter tests
# $2 would be the changetree -- copy-paste it from a failing test in parserTests-knownFailures.json
#
# Ex 1: debug_selser.sh "Say the magic word" "[[1,2,0,0,0,4,0,4,0,3,0,4,0,2,2,0,1,3,1,4,[4,0],3,[2],3,0,0,3,0,[2,0],0,4,0,[4,0],0,0,0,2,2,0,0,0,4,0]]"
# Ex 2: debug_selser.sh "Lists: 0. Outside nests" "[0,3,2]"
#
# "--trace selser" currently emits verbose selser and wts debugging output.
# So, you will want to pipe both stdout and stderr through less.
# You can copy this commandline to omit "--trace selser" if you dont want the verbose output.

node $(dirname $0)/parserTests --selser --dump dom:post-changes --no-blacklist --trace selser,wt-escape --filter "$1" --changetree "$2"
