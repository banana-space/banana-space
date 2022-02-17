<?php

/**
 * Polish (pl) stop word data for Elasticsearch analysis config.
 */

$polishStopwords = [
	// Polish stop word list adapted from the Carrot2 project stop words list
	// https://github.com/carrot2/carrot2/blob/189299c48163ae4ab79967e06fe54dcaaa2e89b1/core/carrot2-util-text/src-resources/stopwords.pl
	// Distributed under the BSD License. See http://www.opensource.org/licenses/bsd-license.html
	// Also see http://project.carrot2.org/license.html

	// added additional term 'o.o' because with the standard tokenizer, 'o.o.' is converted to 'o.o'
	// keep the original in case a different tokenizer is ever used
	'vol', 'o.o.', 'o.o', 'mgr', 'godz', 'zł', 'www', 'pl', 'ul', 'tel', 'hab', 'prof', 'inż',
	'dr', 'i', 'u', 'aby', 'albo', 'ale', 'ani', 'aż', 'bardzo', 'bez', 'bo', 'bowiem', 'by',
	'byli', 'bym', 'był', 'była', 'było', 'były', 'być', 'będzie', 'będą', 'chce', 'choć',
	'co', 'coraz', 'coś', 'czy', 'czyli', 'często', 'dla', 'do', 'gdy', 'gdyby', 'gdyż',
	'gdzie', 'go', 'ich', 'im', 'inne', 'iż', 'ja', 'jak', 'jakie', 'jako', 'je', 'jednak',
	'jednym', 'jedynie', 'jego', 'jej', 'jest', 'jeszcze', 'jeśli', 'jeżeli', 'już', 'ją',
	'kiedy', 'kilku', 'kto', 'która', 'które', 'którego', 'której', 'który', 'których',
	'którym', 'którzy', 'lat', 'lecz', 'lub', 'ma', 'mają', 'mamy', 'mi', 'miał', 'mimo',
	'mnie', 'mogą', 'może', 'można', 'mu', 'musi', 'na', 'nad', 'nam', 'nas', 'nawet', 'nic',
	'nich', 'nie', 'niej', 'nim', 'niż', 'no', 'nowe', 'np', 'nr', 'o', 'od', 'ok', 'on',
	'one', 'oraz', 'pan', 'po', 'pod', 'ponad', 'ponieważ', 'poza', 'przed', 'przede', 'przez',
	'przy', 'raz', 'razie', 'roku', 'również', 'się', 'sobie', 'sposób', 'swoje', 'są', 'ta',
	'tak', 'takich', 'takie', 'także', 'tam', 'te', 'tego', 'tej', 'temu', 'ten', 'teraz',
	'też', 'to', 'trzeba', 'tu', 'tych', 'tylko', 'tym', 'tys', 'tzw', 'tę', 'w', 'we', 'wie',
	'więc', 'wszystko', 'wśród', 'właśnie', 'z', 'za', 'zaś', 'ze', 'że', 'żeby', 'ii', 'iii',
	'iv', 'vi', 'vii', 'viii', 'ix', 'xi', 'xii', 'xiii', 'xiv', 'xv',
];

return $polishStopwords;
