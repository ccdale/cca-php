Radio Times Classes
===================

http://xmltv.radiotimes.com/xmltv gives a 14 day tv guide to UK television
broadcasts.

see http://www.birtles.org.uk/phpbb3/viewtopic.php?f=5&t=245#p943 for details
of the format.

All data is copyright the Radio Times website http://www.radiotimes.com

radiotimes.class.php
--------------------

This obtains the data from the Radio Times website for each named channel and
converts it to a php array.

```
Array
(
    [0] => Array
        (
            [title] => Good Morning Britain
            [subtitle] => 25, series 1
            [episode] => 
            [year] => 
            [director] => 
            [cast] => Array
                (
                    [Guest] => Array
                        (
                            [0] => Caroline Quentin
                            [1] => Neil Morrissey
                        )

                    [Presenter] => Array
                        (
                            [0] => Ben Shephard
                            [1] => Charlotte Hawkins
                            [2] => Ranvir Singh
                            [3] => John Stapleton
                        )

                )

            [premiere] => false
            [film] => false
            [repear] => false
            [subtitles] => true
            [widescreen] => true
            [newseries] => false
            [deafsigned] => false
            [blackandwhite] => false
            [filmstarrating] => 
            [filmcertificate] => 
            [genre] => Entertainment
            [description] => Former Men Behaving Badly stars Caroline Quentin and Neil Morrissey chat about being reunited in the play Relative Values, directed by Trevor Nunn. Plus, a lively mix of news and current affairs, health, entertainment and lifestyle features. Presented by Ben Shephard, Charlotte Hawkins, Ranvir Singh and John Stapleton.
            [choice] => false
            [date] => 30/05/2014
            [starttime] => 1401426000
            [endtime] => 1401435000
            [duration] => 150

        )
```

filldb.class.php
----------------

This obtains the data from the radiotimes class and fills the database with
it.  The structure of the database is in comments in the class.

filldb.php
----------

example executable showing how to use both classes.  
