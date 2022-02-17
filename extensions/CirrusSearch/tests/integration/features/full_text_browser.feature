@clean @phantomjs
Feature: Full text search
  Background:
    Given I am at the search results page

  @setup_main @setup_namespaces
  Scenario Outline: Query string search
    When I search for <term>
    Then I am on a page titled Search results
      And <first_result> is the first search result
      But Two Words is <two_words_is_in> the search results
  Examples:
    | term                                 | first_result             | two_words_is_in |
    | catapult                             | Catapult                 | in              |
    | pickles                              | Two Words                | in              |
    | rdir                                 | Two Words                | in              |
    | talk:catapult                        | Talk:Two Words           | not in          |
    | talk:intitle:words                   | Talk:Two Words           | not in          |
    | template:pickles                     | Template:Template Test   | not in          |
    | pickles/                             | Two Words                | in              |
    | catapult/pickles                     | Two Words                | in              |
    | File:"Screenshot, for test purposes" | File:Savepage-greyed.png | not in          |
    # You can't search for text inside a <video> or <audio> tag
    | "JavaScript disabled"                | none                     | not in          |
    # You can't search for text inside the table of contants
    | "3.1 Conquest of Persian empire"     | none                     | not in          |
    # You can't search for the [edit] tokens that users can click to edit sections
    | "Succession of Umar edit"            | none                     | not in          |

  @setup_main
  Scenario Outline: Searching for empty-string like values
    When I search for <term>
    Then I am on a page titled <title>
      And there are no search results
      And there are no errors reported
  Examples:
    | term                    | title          |
    | the empty string        | Search         |
    | ♙                       | Search results |
    | %{exact: }              | Search results |
    | %{exact:      }         | Search results |
    | %{exact:              } | Search results |

  @javascript_injection
  Scenario: Searching for a page with javascript doesn't execute it (in this case, removing the page title)
    When I search for Javascript findme
    Then the title still exists

  @file_text
  Scenario: When you search for text that is in a file, you can find it!
    When I search for File:debian rhino
    Then File:Linux Distribution Timeline text version.pdf is the first search result and has an image link

  @js_and_css
  Scenario: JS pages don't corrupt the output
    When I search for User:Admin/some.js jQuery
    Then there is not alttitle on the first search result

  @js_and_css
  Scenario: CSS pages don't corrupt the output
    When I search for User:Admin/some.css jQuery
    Then there is not alttitle on the first search result

  @setup_main
  Scenario: Word count is output in the results
    When I search for Two Words
    Then there are search results with (4 words) in the data

  @setup_main @filenames
  Scenario Outline: Portions of file names
    When I search for <term>
    Then I am on a page titled Search results
      And <first_result> is the first search result
  Examples:
    |            term            |          first_result          |
    | File:Savepage-greyed.png   | File:Savepage-greyed.png       |
    | File:Savepage              | File:Savepage-greyed.png       |
    | File:greyed.png            | File:Savepage-greyed.png       |
    | File:greyed                | File:Savepage-greyed.png       |
    | File:Savepage png          | File:Savepage-greyed.png       |
    | File:No_SVG.svg            | File:No SVG.svg                |
    | File:No SVG.svg            | File:No SVG.svg                |
    | File:No svg                | File:No SVG.svg                |
    | File:svg.svg               | File:Somethingelse svg SVG.svg |

  @setup_main
  Scenario Outline: Text separated by a <br> tag is not jammed together
    When I search for <term>
    Then I am on a page titled Search results
    Then <page> is the first search result
    Examples:
      |       term      |       page      |
      |  Waffle Squash  |  Waffle Squash  |
      | Waffle Squash 2 | Waffle Squash 2 |
      |  wafflesquash   |       none      |

  # Just take too long to run on a regular basis
  # @huge
  # Scenario: Searches for a huge phrase is reasonably fast (faster than the 40 second timeout)
  #   Given there are 3000 pages named Zeros%s with contents 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
  #   When I search for 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0. 0.0.0.0.0.0.0.0.
  #   Then Zeros is in the first search result
