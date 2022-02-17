<?php

/**
 * Mirandese (mwl) data for Elasticsearch analysis config.
 */

$mirandeseStopwords = [
	// Mirandese stop word list adapted from
	// https://github.com/AthenaLisbonne/Mirandese/blob/master/Mirandese%20stop%20words.txt,
	// which is in turn translated from a Portuguese stop word list.
	// Distributed under the BSD License.
	'de', 'a', 'la', 'l', 'que', 'quei', 'i', 'an', 'ne', 'en', 'un', 'pa', 'para',
	'cun', 'nó', 'nan', 'nun', 'ua', 'ũa', 'ls', 'los', 'se', 'na', 'por', 'mais',
	'más', 'las', 'cumo', 'mas', 'al', 'el', 'sou', 'sue', 'ó', 'u', 'ou', 'quando',
	'muito', 'mui', 'mi', 'mos', 'nos', 'yá', 'you', 'tamien', 'solo', 'pul', 'pula',
	'anté', 'até', 'esso', 'isso', 'eilha', 'antre', 'açpuis', 'adepuis', 'adespuis',
	'apuis', 'çpuis', 'depuis', 'sien', 'sin', 'mesmo', 'miesmo', 'als', 'sous',
	'quien', 'nas', 'me', 'mi', 'esse', 'eilhes', 'tu', 'essa', 'nun', 'nien', 'nin',
	'sues', 'miu', 'mie', 'nua', 'nũa', 'puls', 'eilhas', 'qual', 'nós', 'le',
	'deilhes', 'essas', 'esses', 'pulas', 'este', 'del', 'tu', 'ti', 'te', 'bós',
	'bos', 'les', 'mius', 'mies', 'tou', 'tue', 'tous', 'tues', 'nuosso', 'nuossa',
	'nuossos', 'nuossas', 'deilha', 'deilhas', 'esta', 'estes', 'estas', 'aquel',
	'aqueilha', 'aqueilhes', 'aqueilhas', 'esto', 'isto', 'aqueilho', 'aquilho',
	'stou', 'stá', 'stamos', 'stan', 'stube', 'stubo', 'stubimos', 'stubírun',
	'staba', 'stábamos', 'stában', 'stubira', 'stubíramos', 'steia', 'stéiamos',
	'stemos', 'stéian', 'sten', 'stubisse', 'stubíssemos', 'stubíssen', 'stubir',
	'stubirmos', 'stubíren', 'hei', 'hai', 'há', 'hemos', 'han', 'hoube', 'houbimos',
	'houbírun', 'houbira', 'houbíramos', 'haba', 'haia', 'hábamos', 'háiamos',
	'hában', 'háian', 'houbisse', 'habisse', 'houbíssemos', 'habíssemos',
	'houbíssen', 'habíssen', 'houbir', 'houbirmos', 'houbíren', 'sou', 'somos',
	'son', 'sano', 'era', 'éramos', 'éran', 'fui', 'fui', 'fumos', 'fúrun', 'fura',
	'fúramos', 'seia', 'séiamos', 'séian', 'fusse', 'fússemos', 'fússen', 'fur',
	'furmos', 'fúren', 'serei', 'será', 'seremos', 'seran', 'serano', 'serie',
	'seriemos', 'serien', 'tengo', 'ten', 'tenemos', 'ténen', 'tenie', 'teniemos',
	'tenien', 'tube', 'tubo', 'tubimos', 'tubírun', 'tubira', 'tubíramos', 'tenga',
	'téngamos', 'téngan', 'tubisse', 'tubíssemos', 'tubíssen', 'tubir', 'tubirmos',
	'tubíren', 'tenerei', 'tenerá', 'teneremos', 'teneran', 'teneria', 'teneriemos',
	'tenerien',
	// the following words were commented out in the original Portuguese stop word
	// list, and were also commented out in the Mirandese translation. It's not clear
	// that they need to be skipped, so they are included here until it becomes clear
	// they are a problem. (Our use of the plain index means these are still
	// available for exact matching in phrases.)
	'ye', 'fui', 'ten', 'ser', 'hai', 'há', 'stá', 'era', 'tener', 'stan', 'tenie',
	'fúran', 'ténen', 'habie', 'seia', 'será', 'tengo', 'fusse',
];

return $mirandeseStopwords;
