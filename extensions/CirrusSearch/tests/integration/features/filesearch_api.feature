@clean @api @filesearch
Feature: Searches with the file size filters

  Scenario Outline: filesize finds files with given size
    When I api search in namespace 6 for <search> -intitle:frozen
    Then there are <count> api search results
    And <musthave> is in the api search results
    And <mustnot> is not in the api search results
  Examples:
    | search        | count | musthave                                          | mustnot            |
    | filesize:>10  | 2     | File:Linux Distribution Timeline text version.pdf | File:OnCommons.svg |
    | filesize:<10  | 4     | File:DuplicatedLocally.svg                        | File:Linux Distribution Timeline text version.pdf |
    | filesize:10   | 2     | File:Linux Distribution Timeline text version.pdf | File:OnCommons.svg |
    | filesize:5,20 | 1     | File:Savepage-greyed.png                          | File:Linux Distribution Timeline text version.pdf |

  Scenario Outline: filetype finds files with given internal type
    When I api search in namespace 6 for <search> -intitle:frozen
    Then there are <count> api search results
    And <musthave> is in the api search results
    And <mustnot> is not in the api search results
    Examples:
      | search           | count | musthave                                          | mustnot                  |
      | filetype:bitmap  | 1     | File:Savepage-greyed.png                          | File:DuplicatedLocally.svg |
      | filetype:office  | 1     | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
      | filetype:Drawing | 4     | File:DuplicatedLocally.svg                        | File:Savepage-greyed.png |

  Scenario Outline: filemime finds files with given MIME type
    When I api search in namespace 6 for <search> -intitle:frozen
    Then there are <count> api search results
    And <musthave> is in the api search results
    And <mustnot> is not in the api search results
    Examples:
      | search                 | count | musthave                                          | mustnot                    |
      | filemime:image/PNG     | 1     | File:Savepage-greyed.png                          | File:DuplicatedLocally.svg |
      | filemime:image/svg+xml | 4     | File:DuplicatedLocally.svg                        | File:Savepage-greyed.png   |
      | filemime:application/pdf | 1   | File:Linux Distribution Timeline text version.pdf | File:OnCommons.svg         |

  Scenario Outline: Resolution filters find files with given dimensions
    When I api search in namespace 6 for <search> -intitle:frozen
    Then there are <count> api search results
    And <musthave> is in the api search results
    And <mustnot> is not in the api search results
  Examples:
    | search                 | count | musthave                                          | mustnot                  |
    | fileres:>1000          |  1    | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
    | filew:>1000            |  1    | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
    | fileh:>1000            |  1    | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
    | filewidth:>1000        |  1    | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
    | fileheight:>1000       |  1    | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |
    | fileres:300,600        |  1    | File:Savepage-greyed.png                          | DuplicatedLocally.svg    |
    | fileres:<500           |  1    | File:Savepage-greyed.png                          | File:Linux Distribution Timeline text version.pdf |
    | filew:300,900          |  5    | File:DuplicatedLocally.svg                        | File:Linux Distribution Timeline text version.pdf |
    | filew:<500             |  1    | File:Savepage-greyed.png                          | File:Linux Distribution Timeline text version.pdf |
    | fileh:>200             |  6    | File:Linux Distribution Timeline text version.pdf | anything |
    | filew:300,600 fileh:200,300 | 1 | File:Savepage-greyed.png                         | File:Linux Distribution Timeline text version.pdf |
    | intitle:linux filew:>300 | 1   | File:Linux Distribution Timeline text version.pdf | File:Savepage-greyed.png |

  Scenario Outline: Search failures
    When I api search in namespace 6 for <search>
    Then there are no api search results
  Examples:
    | search                     |
    | filetype:duck              |
    | filemime:text/html         |
    | fileres:<200               |
    | fileres:<500 fileres:>1000 |
    | fileres:100,1              |
    | filesize:10,1              |
    | filesise:10,1              |
    | filesize:duck              |
