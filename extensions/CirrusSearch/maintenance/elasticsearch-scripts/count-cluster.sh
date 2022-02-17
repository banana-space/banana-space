#!/bin/bash

echo index,codfw,eqiad,labsearch
for i in `curl -s elastic2001.codfw.wmnet:9200/_cat/aliases?h=a | egrep '(_content|_general) *$'`; do
	EQ_DOCS=`curl -s elastic1001.eqiad.wmnet:9200/$i/_count | jq .count`;
	COD_DOCS=`curl -s elastic2001.codfw.wmnet:9200/$i/_count | jq .count`;
	LS_DOCS=`curl -s nobelium.eqiad.wmnet:9200/$i/_count | jq .count`;
	echo "$i,$COD_DOCS,$EQ_DOCS,$LS_DOCS";
done
