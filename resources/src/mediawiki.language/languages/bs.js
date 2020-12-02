/*!
 * Bosnian (bosanski) language functions
 */

mw.language.convertGrammar = function ( word, form ) {
	var grammarForms = mw.language.getData( 'bs', 'grammarForms' );
	if ( grammarForms && grammarForms[ form ] ) {
		return grammarForms[ form ][ word ];
	}
	switch ( form ) {
		case 'instrumental': // instrumental
			word = 's ' + word;
			break;
		case 'lokativ': // locative
			word = 'o ' + word;
			break;
	}
	return word;
};
