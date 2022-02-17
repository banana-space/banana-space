Origins of the utr30.txt file

UTR30 was a proposal drafted by the Unicode Consortium but was withdrawn
because of lack of consensus.
See https://unicode.org/reports/tr30/.
Despite that this proposal has been used in lucene in the analysis-icu module.
But because it's not part of unicode the way to generate this data is not
trivial.
It's a concatenation of multiple files. The output used by lucene is a binary
file suited to be loaded as a Normalizer2.
These apis are not available in PHP so the idea is to generate a rule file that
can be understood by Transliterator.
Source files:
https://github.com/apache/lucene-solr/tree/master/lucene/analysis/icu/src/data/utr30

Assemble the files into a single normlize rule file:
gennorm2 . BasicFoldings.txt DiacriticFolding.txt DingbatFolding.txt HanRadicalFolding.txt NativeDigitFolding.txt -o combined.nrm --combined

1/ Edit combined.nrm to transform any unicode code reference with the
notation understood by the Transliterator:
0020 should be \u0020

2/ Remove the last four lines (from 1D185..1D18B), for some reasons they cannot be loaded with the rest of the file.

3/ Join all lines with a ';'

4/ Prepend the NFD rules, case folding, naive accent removal rules

::NFD;::Upper;::Lower;::[:Nonspacing Mark:] Remove;::NFC;[\_\-\'\u2019\u02BC]>\u0020

This basically means:
Decompose, upper case, lower case, remove combining accents, Recompose, fold some chars to space.
BasicFoldings.txt DiacriticFolding.txt DingbatFolding.txt HanRadicalFolding.txt NativeDigitFolding.txt will
be applied after these.
