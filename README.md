# php-sentiment-analyser
Sentiment Analysis PHP Class
--------------------
Author: Nick Duncan <nick@codecabin.co.za>

Contributors: Dylan Auty (regex, refactoring and logic)

This program is free software: you can redistribute it and/or modify it under the terms of the 
GNU General Public License as published by the Free Software Foundation, either version 3 of
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
 
USAGE:
--------------------
```
include ('sentiment_analyser.class.php');
$sa = new SentimentAnalysis();
$sa->initialize();
$sa->analyse("Thank you. This was the best customer service I have ever received.");
$score = $sa->return_sentiment_rating();
var_dump($score);
```

CONCEPT:
--------------------
This class serves three purposes:
1) Estimate the sentiment for a string based on emotion words, booster words, emoticons and polarity changers
2) Allow you to save analysed data into positive, negative or neutral datasets
3) Identify if we have any phrase matches on previously analysed positive, negative and neutral phrases

Should there be any high quality phrase matches, it would take precedent over the sentiment analysis and return 
the phrase match rating instead.


SENTIMENT ANALYSIS
--------------------
Strings are broken into tokenised arrays of single words. These words are analysed against TXT files that contain
emotion words with ratings, emoticons with ratings, booster words with ratings and possible polarity changers.

A score is then calculated based on this analyse and this forms the "Sentiment analysis score".


PHRASE ANALYSIS
--------------------
This function is key to identifying whether the phrase in questions can be 
compared to phrases that we have analysed and stored before. It uses Levenshtein
distance to calculate distance between 4,5,6,7,8,9 and 10 word length phrases against
the dataset we already have. We also make use of PHP's similar_text to double verify proximity.

This means that the more phrases we have analysed previously improves the entire dataset
and allows phrases to be more accurately scored against historical data.

1) the phrase is broken up into ngram lengths
2) The array is reverse sorted so we compare 10 word length phrases first, then 9, and so on
3) Phrases are matched against positive, negative and neutral phrases in the relevant TXT files
4) Only matches that meet the minimum levenshtein_min_distance and similiarity_min_distance are kept
