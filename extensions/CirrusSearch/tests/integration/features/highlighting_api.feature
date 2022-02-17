@clean @highlighting @api
Feature: Highlighting
  @setup_main
  Scenario Outline: Found words are highlighted
    When I api search for <term>
    Then <highlighted_title> is the highlighted title of the first api search result
      And <highlighted_text> is the highlighted snippet of the first api search result
  Examples:
    | term                       | highlighted_title        | highlighted_text                                 |
    | two words                  | *Two* *Words*            | ffnonesenseword catapult pickles anotherword     |
    | pickles                    | Two Words                | ffnonesenseword catapult *pickles* anotherword   |
    | ffnonesenseword pickles    | Two Words                | *ffnonesenseword* catapult *pickles* anotherword |
    | two words catapult pickles | *Two* *Words*            | ffnonesenseword *catapult* *pickles* anotherword |
    | template:test pickle       | Template:Template *Test* | *pickles*                                        |
    # Verify highlighting the presence of accent squashing
    | Africa test                | *África*                 | for *testing*                                    |
    # Verify highlighting on large pages.
    | "discuss problems of social and cultural importance" | Rashidun Caliphate | community centers as well where the faithful gathered to *discuss* *problems* *of* *social* *and* *cultural* *importance*. During the caliphate of Umar as many as four thousand |
    | "discuss problems of social and cultural importance"~ | Rashidun Caliphate | community centers as well where the faithful gathered to *discuss* *problems* *of* *social* *and* *cultural* *importance*. During the caliphate of Umar as many as four thousand |
    # Auxiliary text
    | tallest alborz             | Rashidun Caliphate       | Mount Damavand, Iran's *tallest* mountain is located in *Alborz* mountain range. |

  Scenario: Even stopwords are highlighted
    When I api search for the once and future king
    Then *The* *Once* *and* *Future* *King* is the highlighted title of the first api search result

  Scenario: Found words are highlighted even if found by different analyzers
    When I api search for "threatening the unity" community
    Then Troubles emerged soon after Abu Bakr's succession, *threatening* *the* *unity* and stability of the new *community* and state. Apostasy had actually begun in the lifetime is the highlighted snippet of the first api search result

  @headings
  Scenario: Found words are highlighted in headings
    When I api search for "i am a heading"
    Then *I* *am* *a* *heading* is the highlighted sectionsnippet of the first api search result

  @headings
  Scenario: References are not included in headings
    When I api search for "Reference in heading"
    Then *Reference* *in* *heading* is the highlighted sectionsnippet of the first api search result

  Scenario: Found words are highlighted in headings even in large documents
    When I api search for "Succession of Umar"
    Then *Succession* *of* *Umar* is the highlighted sectionsnippet of the first api search result

  Scenario: Found words are highlighted in text even in large documents
    When I api search for Allowance to non-Muslims
    Then *Allowance* *to* *non*-*Muslims* is in the highlighted snippet of the first api search result

  Scenario: Found words are highlighted in text even in large documents
    When I api search for "Allowance to non-Muslims"
    Then *Allowance* *to* *non*-*Muslims* is in the highlighted snippet of the first api search result

  Scenario: Words are not found in image captions unless there are no matches in the page
    When I api search for The Rose Trellis Egg
    Then *The* *Rose* *Trellis* Faberge *Egg* is a jewelled enameled imperial Easter *egg* made in St. Petersburg, Russia under *the* supervision of *the* jeweler Peter Carl is the highlighted snippet of the first api search result

  @headings
  Scenario: Found words are highlighted in headings even if they contain both a phrase and a non-phrase
    When I api search for i "am a heading"
    Then *I* *am* *a* *heading* is the highlighted sectionsnippet of the first api search result

  @headings
  Scenario: Found words are highlighted in headings when searching for a non-strict phrase
    When I api search for "i am a heading"~
    Then *I* *am* *a* *heading* is the highlighted sectionsnippet of the first api search result

  @headings
  Scenario: Found words are highlighted in headings even in large documents when searching in a non-strict phrase
    When I api search for "Succession of Umar"~
    Then *Succession* *of* *Umar* is the highlighted sectionsnippet of the first api search result

  Scenario: Found words are highlighted in headings even in large documents when searching in a non-strict phrase
    When I api search for "Allowance to non-Muslims"~
    Then *Allowance* *to* *non*-*Muslims* is in the highlighted snippet of the first api search result

  @headings
  Scenario: The highest scoring heading is highlighted AND it doesn't contain html even if the heading on the page does
    When I api search for "bold heading"
    Then I am a *bold* *heading* is the highlighted sectionsnippet of the first api search result

  @headings
  Scenario: HTML comments in headings are not highlighted
    When I api search for "Heading with html comment"
    Then *Heading* *with* *html* *comment* is the highlighted sectionsnippet of the first api search result

  Scenario: Redirects are highlighted
    When I api search for rdir
    Then *Rdir* is the highlighted redirectsnippet of the first api search result

  Scenario: The highest scoring redirect is highlighted
    When I api search for crazy rdir
    Then *Crazy* *Rdir* is the highlighted redirectsnippet of the first api search result

  Scenario: Highlighted titles don't contain underscores in the namespace
    When I api search for user_talk:test
    Then User talk:*Test* is the highlighted title of the first api search result

  Scenario: Highlighted text prefers the beginning of the article
    When I api search for Rashidun Caliphate
    Then Template:Infobox Former Country Template:History of the Arab States The *Rashidun* *Caliphate* (Template:lang-ar al-khilafat ar-Rāshidīyah), comprising the first is the highlighted snippet of the first api search result
    When I api search for caliphs
    Then collectively named the Ulema. The first four *caliphs* are called the Rashidun, meaning the Rightly Guided *Caliphs*, because they are believed to have followed is the highlighted snippet of the first api search result

  @references
  Scenario: References don't appear in highlighted section titles
    When I api search for "Reference Section"
    Then *Reference* *Section* is the highlighted sectionsnippet of the first api search result

  @references
  Scenario: References ([1]) don't appear in highlighted text
    When I api search for Reference Text Highlight Test
    Then *Reference* *Text* foo baz bar is the highlighted snippet of the first api search result

  @references
  Scenario: References are highlighted if you search for them
    When I api search for Reference foo bar baz Highlight Test
    Then *Reference* Text *foo* *baz* *bar* is the highlighted snippet of the first api search result

  @programmer_friendly
  Scenario: camelCase is highlighted correctly
    When I api search for namespace aliases
    Then $wg*Namespace**Aliases* is the highlighted title of the first api search result

  @file_text
  Scenario: When you search for text that is in a file if there are no matches on the page you get the highlighted text from the file
    When I api search for File:debian rhino
    Then File:Linux Distribution Timeline text version.pdf is the first api search result
      And *Debian* is in the highlighted snippet of the first api search result
      And Arco-*Debian* is in the highlighted snippet of the first api search result
      And Black*Rhino* is in the highlighted snippet of the first api search result
      And the first api search result is a match to file content

  @file_text
  Scenario: When you search for text that is in a file if there are matches on the page you get those
    When I api search for File:debian rhino linux
    Then File:Linux Distribution Timeline text version.pdf is the first api search result
      And *Linux* distribution timeline. is the highlighted snippet of the first api search result

  @redirect
  Scenario: Redirects containing &s are highlighted
    Given a page named Highlight & Ampersand exists with contents #REDIRECT [[Main Page]]
    When I api search for Highlight Ampersand
    Then *Highlight* &amp; *Ampersand* is the highlighted redirectsnippet of the first api search result

  @redirect
  Scenario: The best matched redirect is highlighted
    Given a page named Rrrrtest Foorr exists with contents #REDIRECT [[Main Page]]
      And a page named Rrrrtest Foorr Barr exists with contents #REDIRECT [[Main Page]]
      And a page named Rrrrtest exists with contents #REDIRECT [[Main Page]]
    When I api search for Rrrrtest Foorr Barr
    Then *Rrrrtest* *Foorr* *Barr* is the highlighted redirectsnippet of the first api search result

  @redirect
  Scenario: Long redirects are highlighted
    Given a page named Joint Declaration of the Government of the United Kingdom of Great Britain and Northern Ireland and the Government of the People's Republic of China on the Question of Hong Kong exists with contents #REDIRECT [[Main Page]]
    When I api search for Joint Declaration of the Government of the United Kingdom of Great Britain and Northern Ireland and the Government of the People's Republic of China on the Question of Hong Kong
    Then *Joint* *Declaration* *of* *the* *Government* *of* *the* *United* *Kingdom* *of* *Great* *Britain* *and* *Northern* *Ireland* *and* *the* *Government* *of* *the* *People's* *Republic* *of* *China* *on* *the* *Question* *of* *Hong* *Kong* is the highlighted redirectsnippet of the first api search result

  @category
  Scenario: Category only matches just get a text summary and have the category highlighted in the alttitle
    When I api search for TemplateTagged
    Then ffnonesenseword catapult pickles anotherword is the highlighted snippet of the first api search result
     And *TemplateTagged* is the highlighted categorysnippet of the first api search result

  @insource
  Scenario: insource:"" highlights the source
    When I api search for insource:"p2 Byzantine Empire"
    Then |*p2*                     = *Byzantine* *Empire* is in the highlighted snippet of the first api search result

  @insource @regex
  Scenario: insource:// highlights the source using only the regex
    When I api search for insource:"a" insource:/b c/ -rashidun
    Then a *b c* is in the highlighted snippet of the first api search result

  @insource @regex
  Scenario: insource:// works on multi-byte strings
    When I api search for insource:"rashidun" insource:/p2 *= Byzantine Empire/
    Then |*p2                     = Byzantine Empire* is in the highlighted snippet of the first api search result
