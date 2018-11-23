<?php
/**
 * Sentiment Analysis PHP Class
 *
 * @version 1.0
 * @author  Nick Duncan <nick@codecabin.co.za>
 * @since   2016-11-01
 *
 * Contributors: Dylan Auty (regex, refactoring and logic)
 *
 *  
 * This program is free software: you can redistribute it and/or modify it under the terms of the 
 * GNU General Public License as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *  
 * USAGE:
 *
 * include ('sentiment_analyser.class.php');
 * $sa = new SentimentAnalysis();
 * $sa->initialize();
 * $sa->analyse("Thank you. This was the best customer service I have ever received.");
 * $score = $sa->return_sentiment_rating();
 * var_dump($score);
 *
 * CONCEPT:
 *  
 * This class serves three purposes:
 * 1) Estimate the sentiment for a string based on emotion words, booster words, emoticons and polarity changers
 * 2) Allow you to save analysed data into positive, negative or neutral datasets
 * 3) Identify if we have any phrase matches on previously analysed positive, negative and neutral phrases
 *
 * Should there be any high quality phrase matches, it would take precedent over the sentiment analysis and return 
 * the phrase match rating instead.
 *
 * 
 * SENTIMENT ANALYSIS
 * Strings are broken into tokenised arrays of single words. These words are analysed against TXT files that contain
 * emotion words with ratings, emoticons with ratings, booster words with ratings and possible polarity changers.
 *
 * A score is then calculated based on this analyse and this forms the "Sentiment analysis score".
 *
 * 
 * PHRASE ANALYSIS
 *
 * This function is key to identifying whether the phrase in questions can be 
 * compared to phrases that we have analysed and stored before. It uses Levenshtein
 * distance to calculate distance between 4,5,6,7,8,9 and 10 word length phrases against
 * the dataset we already have. We also make use of PHP's similar_text to double verify proximity.
 *
 * This means that the more phrases we have analysed previously improves the entire dataset
 * and allows phrases to be more accurately scored against historical data.
 *
 * 1) the phrase is broken up into ngram lengths
 * 2) The array is reverse sorted so we compare 10 word length phrases first, then 9, and so on
 * 3) Phrases are matched against positive, negative and neutral phrases in the relevant TXT files
 * 4) Only matches that meet the minimum levenshtein_min_distance and similiarity_min_distance are kept
 *
 * 
 *
 * 
*/


class SentimentAnalysis {

    private $mention = array();
    private $original_text; /* original string to be analysed */
    private $output_mention = array(); //variable to display the processed mention with styling
    private $phrase_proximity = array(); // build a list of phrases that are similar to phrases we've scored before
    private $lexicon_array = array(); // index of positive and negative words
    private $enhancer_array = array(); // index of enhancers
    private $polarize_array = array(); // index of polarize words, example: "not"
    private $idiom_array = array(); // index of idioms
    private $good_phrases = array(); // index of good phrases
    private $bad_phrases = array(); // index of bad phrases
    private $neutral_phrases = array(); // index of neutral phrases
    private $emoticon_array = array(); // index of eemoticons
    private $sentiment_score = 0; // initial score 2.5 = neutral
    private $sentiment_score_positive = 0; // initial score 0 = neutral
    private $sentiment_score_negative = 0; // initial score 0 = neutral
    private $polarity = 0; // determines the polarity of the mention
    private $active_index = array(); // this is the set of words linked to their analysed sentiment
    private $number_words = 0;
    private $number_positive_words = 0;
    private $number_negative_words = 0;
    private $number_sentiment_words = 0;
    private $preferred_match_type = '';
    private $formatted_output_mention;


/* ADJUSTABLES */


/**
 * Set the neutral score for a sentiment rating.
 * @var floatval
 */
private $sentiment_rating = 2.5;

/**
 * Set the minimum neutral score
 * @var floatval
 */
private $min_neutral = 2.3;

/**
 * Set the maximum neutral score
 * @var floatval
 */
private $max_neutral = 2.7;



/**
 * Set the minimum acceptable levenshtein distance 
 * @var int
 */
private $levenshtein_min_distance = 15;


/**
 * Set the minimum acceptable similiarity distance 
 * @var floatval
 */
private $similiarity_min_distance = 65.00;



    /**
     * Set the minimum acceptable levenshtein distance for outputting the confirm button
     * @var int
     */
    private $levenshtein_min_submit_distance = 5;


        public function initialize() {
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/EmotionLookupTable.txt","lexicon_array",true);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/BoosterWordList.txt","enhancer_array",true);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/NegatingWordList.txt","polarize_array",false);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/IdiomLookupTable.txt","idion_array",true);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/EmoticonLookupTable.txt","emoticon_array",true);
                
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/negative_data.txt","bad_phrases",true);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/positive_data.txt","good_phrases",true);
                $this->import_lexicons(dirname(dirname(__FILE__))."/data/neutral_data.txt","neutral_phrases",true);

        }




        /**
         * Analyse the text
         * @param  string   $text   The text to be analysed
         * @return void
         */
        public function analyse($text) {

            /* set the defaults */
            unset($this->mention);
            unset($this->output_mention);
            $this->output_mention = array();
            $this->sentiment_score_positive = 0;
            $this->sentiment_score_negative = 0;
            $this->sentiment_score = 0;

            $this->number_words = 0;
            $this->number_positive_words = 0;
            $this->number_negative_words = 0;
            $this->number_sentiment_words = 0;

            if ($text == '') { return; }



            $this->pre_original_text = $text;


            $this->mention = $this->tokenise($text);
            $this->mention = $this->remove_stop_words($this->mention);
            $this->original_text = implode($this->mention," ");

            $this->number_words = count($this->mention);


            /**
             * This creates the "SENTIMENT ANALYSIS" section of the ouput. This will not be
             * used if there is a phrase match
             */
            $this->analyse_single_words();
            $this->analyse_emoticons();
            $this->analyse_idioms();

            /**
             * This creates the "PHRASE MATCH" section. Phrase matches are considered more
             * accurate then generic sentiment analysis as we have manually vouched for
             * the score and input previously. This means the system will learn from your input.
             */
            $this->analyse_phrases();


        }

        /**
         * Remove all stop words from the string
         *
         * @todo This needs to be broken out into a standalone text file
         * 
         * @param  array    $tokenised_array Tokenised array of the original string
         * @return array
         */
        private function remove_stop_words($tokenised_array) {
            $stopwords = array("#", "a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", "along", "already", "also","although","always","am","among", "amongst", "amoungst", "amount",  "an", "and", "another", "any","anyhow","anyone","anything","anyway", "anywhere", "are", "around", "as",  "at", "back","be","became", "because","become","becomes", "becoming", "been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom","but", "by", "call", "can", "co", "con", "could", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", "each", "eg", "eight", "either", "eleven","else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", "everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", "four", "from", "front", "full", "further", "get", "give", "go", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", "hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "ie", "if", "in", "inc", "indeed", "interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", "meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "nothing", "now", "nowhere", "of", "off", "often", "on", "once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own","part", "per", "perhaps", "please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", "since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", "system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", "therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", "to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "via", "was", "we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", "wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", "would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");
            $new_array = array();
            foreach ($stopwords as $word) {
                foreach ($tokenised_array as $key => $val) {
                    if ($val == $word) {
                        unset($tokenised_array[$key]);
                    }
                    preg_match('/(\s+|^)@\S+/',$val,$match);
                    if (isset($match[0])) {
                        unset($tokenised_array[$key]);   
                    }
                }
            }
            $cnt = 0;
            foreach ($tokenised_array as $key => $val) {
                $new_array[$cnt] = $val;
                $cnt++;
            }

            return $new_array;

        }



        /**
         * Check if there are emoticons in the string
         *
         * @todo  We need a way to check for non-utf8 emoticons, such as those used on twitter
         * 
         * @return void
         */
        private function analyse_emoticons() {
            foreach ($this->emoticon_array as $emoticon) {
                $rating = floatval($emoticon['rating']);
                $i = count($this->output_mention);
                foreach ($this->mention as $mention_word) {
                    if ($emoticon['word'] == $mention_word) {
                        $this->output_mention[$i]['matched'] = $mention_word;
                        $this->output_mention[$i]['rating'] = $rating;
                        $this->output_mention[$i]['type'] = 'emoticon';
                        //$this->output_mention[$i] = "<li>2$mention_word($rating)</li>";
                        $this->number_sentiment_words++;

                        if ($rating > 0) { $this->number_positive_words++; }
                        if ($rating < 0) { $this->number_negative_words++; }

                        $this->alter_sentiment($rating,0); // increase/decreate sentiment score
                    }
                    $i++;
                }
                }
        }

        /**
         * Analyse single words against the emotion words in the TXT file
         * 
         * We analyse each individual word against those in the emotion TXT file. We then
         * identify if there is a "booster" word before it and apply the relevant rating for that
         * booster word. We also identify if there are any "polarity changers" that could reverse
         * the rating. For example "I am *not* happy".
         * 
         * @return void
         */
        private function analyse_single_words() {

            foreach ($this->lexicon_array as $word) {
                $wild_card_match = false;
                $check_word = str_replace("*","",$word['word']);
                if (strpos($word['word'],"*")) { $wild_card_match = true; } // check if the word in the lexion is a wild card match or not.
                $rating = floatval($word['rating']);

                $i = count($this->output_mention);
                $mention_i = 0;

                foreach ($this->mention as $mention_word) {
                    if ($wild_card_match) { $regex = "^$check_word"; } else { $regex = "\b^$check_word\b"; } //check if we need to apply the wild card check or not.
                    preg_match("/$regex/", $mention_word, $matches); // check the word to the lexicon
                    if (!$matches) {
                        // look if it is perhaps an emoticon
                        foreach ($this->emoticon_array as $emoticon) {
                            if ($emoticon['word'] == $mention_word) {

                            }
                        }
                    }

                    if ($matches) {
                        $this->output_mention[$i]['matched'] = $mention_word;
                        $this->output_mention[$i]['rating'] = $rating;
                        $this->output_mention[$i]['type'] = 'single_word';
                        $this->number_sentiment_words++;
                   

                        /* LOOK FOR ENHANCERS */
                        // check if there are any enhancers before (or after) the word
                        $enhancer_rating = 0;
                        if (isset($this->mention[$mention_i-1])) {
                            $enhancer_rating = floatval($this->determine_enhancer($this->mention[$mention_i-1]));
                            if ($enhancer_rating) {
                                //echo "found enhancer (".$this->mention[$i-1].") $enhancer_rating \n";

                                if ($enhancer_rating > 0) { $this->number_positive_words++; }
                                if ($enhancer_rating < 0) { $this->number_negative_words++; }

                                $this->output_mention[$i+1]['matched'] = $this->mention[$mention_i-1];
                                $this->output_mention[$i+1]['rating'] = $enhancer_rating;
                                $this->output_mention[$i+1]['type'] = 'enhancer';
                                $this->output_mention[$i+1]['enhancer'] = true;
                                $this->number_sentiment_words++;
                            }
                        }

                        /* LOOK FOR POLARITY CHANGERS */
                        if (isset($this->mention[$mention_i-1])) {
                            $polarised = $this->determine_negative_polarity($this->mention[$mention_i-1]);
                            if ($polarised) {
                                $this->output_mention[$i+1]['matched'] = $this->mention[$mention_i-1];
                                /* change the rating to negative */
                                $rating = -$this->output_mention[$i]['rating'];
                                $this->output_mention[$i]['rating'] = $rating;
                                $this->output_mention[$i+1]['type'] = 'polarity_changer';
                                $this->output_mention[$i+1]['polarity'] = true;
                            }
                        }

                        if ($rating > 0) { $this->number_positive_words++; }
                        if ($rating < 0) { $this->number_negative_words++; }

                        $this->alter_sentiment($rating,$enhancer_rating); // increase/decrease sentiment score
                    }
                    $mention_i++;
                    $i++;
                }
            }
        }



        /**
         * Analyse phrases against stored phrases
         *
         * This function is key to identifying whether the phrase in questions can be 
         * compared to phrases that we have analysed and stored before. It uses Levenshtein
         * distance to calculate distance between 4,5,6,7,8,9 and 10 word length phrases against
         * the dataset we already have. We also make use of PHP's similar_text to double verify proximity.
         *
         * This means that the more phrases we have analysed previously improves the entire dataset
         * and allows phrases to be more accurately scored against historical data.
         *
         * 1) the phrase is broken up into ngram lengths
         * 2) The array is reverse sorted so we compare 10 word length phrases first, then 9, and so on
         * 3) Phrases are matched against positive, negative and neutral phrases in the relevant TXT files
         * 4) Only matches that meet the minimum levenshtein_min_distance and similiarity_min_distance are kept
         *
         * 
         * @return void
         */
        private function analyse_phrases() {
            $original_string = $this->tokenise($this->original_text,'string');

            $preg_array = array();
            $preg_string_singular = "(\w+)";
            $preg_start = "(\w+)";

            /* create a positive look ahead */
            $preg_end = "(?=(\w+))";
            $preg_string = $preg_string_singular;
            for ($i = 2; $i <= 10; $i++) {
                $preg_array[$i] = array();
                $preg_string = $preg_start;
                for ($x = 2; $x <= $i-1; $x++) {
                    $preg_string = $preg_string. " ".$preg_string_singular;
                }
                $preg_string = $preg_string . ' '. $preg_end;
                $preg_array[$i]['preg_string'] = $preg_string;

                preg_match_all('/'.$preg_array[$i]['preg_string'].'/', $original_string, $matches , PREG_SET_ORDER);
                if ($i == 2) { $a = array_map( function($a) { return $a[1].' '.$a[2]; }, $matches ); }
                if ($i == 3) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3]; }, $matches ); }
                if ($i == 4) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4]; }, $matches ); }
                if ($i == 5) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5]; }, $matches ); }
                if ($i == 6) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5].' '.$a[6]; }, $matches ); }
                if ($i == 7) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5].' '.$a[6].' '.$a[7]; }, $matches ); }
                if ($i == 8) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5].' '.$a[6].' '.$a[7].' '.$a[8]; }, $matches ); }
                if ($i == 9) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5].' '.$a[6].' '.$a[7].' '.$a[8].' '.$a[9]; }, $matches ); }
                if ($i == 10) { $a = array_map( function($a) { return $a[1].' '.$a[2].' '.$a[3].' '.$a[4].' '.$a[5].' '.$a[6].' '.$a[7].' '.$a[8].' '.$a[9].' '.$a[10]; }, $matches ); }
                

                $preg_array[$i]['matches'] = $a;
            }
            
            krsort($preg_array);
            unset($preg_array[2]);
            unset($preg_array[3]);
            unset($preg_array[4]);
            $i = count($this->phrase_proximity);
            $sorted_array = array();
            

            foreach ($preg_array as $key => $phrase_array) {

                foreach ($this->good_phrases as $key => $word) {
                    $wild_card_match = false;
                    $check_word = str_replace("*","",$word['word']);
                    if (strpos($word['word'],"*")) { $wild_card_match = true; } // check if the word in the lexion is a wild card match or not.
                    $rating = floatval($word['rating']);
                    // run through each word in the mention for a possible match to the lexicon array

                    foreach ($phrase_array['matches'] as $mention_word) {
                        $levenshtein = levenshtein($mention_word, $word['word']);
                        if ($levenshtein <= $this->levenshtein_min_distance) {
                            similar_text($mention_word, $word['word'],$sim_percent);
                            if ($sim_percent >= $this->similiarity_min_distance) {
                                $i++;
                            
                                $this->phrase_proximity[$i]['matches_from'] = $mention_word;
                                $this->phrase_proximity[$i]['matches_with'] = $word['word'];
                                $this->phrase_proximity[$i]['rating_modifier'] = $rating;
                                $this->phrase_proximity[$i]['levenshtein'] = $levenshtein;
                                $this->phrase_proximity[$i]['similarity'] = $sim_percent;
                            }
                             
                        }
                        
                    }
                }


                foreach ($this->bad_phrases as $key => $word) {
                    $wild_card_match = false;
                    $check_word = str_replace("*","",$word['word']);
                    if (strpos($word['word'],"*")) { $wild_card_match = true; } // check if the word in the lexion is a wild card match or not.
                    $rating = floatval($word['rating']);

                    foreach ($phrase_array['matches'] as $mention_word) {
                        $levenshtein = levenshtein(trim($mention_word), trim($word['word']));
                        if ($levenshtein <= $this->levenshtein_min_distance) {
                            
                            similar_text($mention_word, $word['word'],$sim_percent);
                            if ($sim_percent >= $this->similiarity_min_distance) {
                                $i++;
                                $this->phrase_proximity[$i]['matches_from'] = $mention_word;
                                $this->phrase_proximity[$i]['matches_with'] = $word['word'];
                                $this->phrase_proximity[$i]['rating_modifier'] = $rating;
                                $this->phrase_proximity[$i]['levenshtein'] = $levenshtein;
                                $this->phrase_proximity[$i]['similarity'] = $sim_percent;

                            }
                           
                        }
                         
                    }
                }
                foreach ($this->neutral_phrases as $key => $word) {
                $wild_card_match = false;
                $check_word = str_replace("*","",$word['word']);
                if (strpos($word['word'],"*")) { $wild_card_match = true; } // check if the word in the lexion is a wild card match or not.
                $rating = floatval($word['rating']);

                foreach ($phrase_array['matches'] as $mention_word) {
                    $levenshtein = levenshtein($mention_word, $word['word']);
                    if ($levenshtein <= $this->levenshtein_min_distance) {
                        
                        similar_text($mention_word, $word['word'],$sim_percent);
                        if ($sim_percent >= $this->similiarity_min_distance) {
                            $i++;
                            $this->phrase_proximity[$i]['matches_from'] = $mention_word;
                            $this->phrase_proximity[$i]['matches_with'] = $word['word'];
                            $this->phrase_proximity[$i]['rating_modifier'] = $rating;
                            $this->phrase_proximity[$i]['levenshtein'] = $levenshtein;
                            $this->phrase_proximity[$i]['similarity'] = $sim_percent;

                        }
                       
                    }
                     
                }
            }


            }

            /**
             * We now try find the best phrase match and bring that to the top of the array. Everything
             * else doesnt matter at this point, we just want the best match.
             * 
             */
            foreach ($this->phrase_proximity as $key => $val) {
                $lev = $val['levenshtein'];
                
                $tmp_array = $val;

                if ($lev < $this->phrase_proximity[1]['levenshtein']) {
                    $this->phrase_proximity[$key] = $this->phrase_proximity[1];
                    $this->phrase_proximity[1] = $tmp_array;
                }
            }
        }

        /**
         * Analyse all words against stored idioms
         *
         * Idiom example: "whats up"
         * @return void
         */
        private function analyse_idioms() {
            $original_string = $this->tokenise($this->original_text,'string');

            $i = count($this->output_mention);
            foreach ($this->idiom_array as $idiom) {
                $rating = floatval($idiom['rating']);

                /* is idiom in the original string? */
                if (strpos($idiom['word'], $original_string) !== false) {
                    $i++;
                    $this->output_mention[$i]['matched'] = $idiom['word'];
                    $this->output_mention[$i]['rating'] = $rating;
                    $this->output_mention[$i]['type'] = 'idiom';

                    $this->number_sentiment_words++;

                    if ($rating > 0) { $this->number_positive_words++; }
                    if ($rating < 0) { $this->number_negative_words++; }

                    $this->alter_sentiment($rating,0); // increase/decreate sentiment score
                }
                



            }
        }

        /**
         * Alters the sentiment score based on the rating coming through and whether there was an enhancer rating with it
         * @param  floatval     $rating          Rating
         * @param  floatval     $enhancer_rating Enhancer rating
         * @return void
         */
        private function alter_sentiment($rating,$enhancer_rating) {
            if ($rating < 0) {
                $this->sentiment_score = $this->sentiment_score + abs($rating) + ($enhancer_rating);
                $this->sentiment_score_negative = $this->sentiment_score_negative + abs($rating) + $enhancer_rating;
            }
            else {
                $this->sentiment_score = $this->sentiment_score + abs($rating) + ($enhancer_rating);
                $this->sentiment_score_positive = $this->sentiment_score_positive + abs($rating) + $enhancer_rating;
            }
        }



        /**
         * Display a semi-decent set of important data
         *
         * @todo  Needs love.
         * 
         * @return string
         */
        public function return_sentiment_calculations() {

            $msg = "Positive:".$this->return_positive_sentiment();
            $msg .= "<br />\nNegative:".$this->return_negative_sentiment();
            $msg .= "<br />\nTotal words:".$this->return_number_words();
            $msg .= "<br />\nSentiment words:".$this->return_number_sentiment_words();
            $msg .= "<br />\nPositive words:".$this->return_number_positive_words();
            $msg .= "<br />\nNegative words:".$this->return_number_negative_words();
            $msg .= "<br />\nSentiment Rating:".$this->return_sentiment_rating();
            //$msg .= "<br />\nOutput:<ul>".$this->formatted_output_mention."</ul>";
            return $msg;


        }
        /**
         * Return the tokenised mention
         * @return array
         */
        public function return_tokenized_mention() {
            return $this->mention;
        }

        /**
         * Return the sentiment score
         * @return floatval
         */
        public function return_sentiment_total() {
            return $this->sentiment_score;
        }

        /**
         * Return the positive sentiment score
         * @return floatval
         */
        public function return_positive_sentiment() {
            return $this->sentiment_score_positive;
        }

        /**
         * Return the negative sentiment score
         * @return floatval
         */
        public function return_negative_sentiment() {
            return $this->sentiment_score_negative;
        }

        /**
         * Returns the amount of words that were analysed
         * @return int
         */
        public function return_number_words() {
            return $this->number_words;
        }

        /**
         * Returns the amount of positive words found
         * @return int
         */
        public function return_number_positive_words() {
            return $this->number_positive_words;
        }

        /**
         * Returns the amount of negative words found
         * @return int
         */
        public function return_number_negative_words() {
            return $this->number_negative_words;
        }

        /**
         * Returns the amount of words that had influenced the rating
         * @return int
         */
        public function return_number_sentiment_words() {
            return $this->number_sentiment_words;
        }

        /**
         * Return the phrase proximity array with all its data
         * @return array
         */
        public function return_phrase_proximity() {
            return $this->phrase_proximity;
        }

        /**
         * Return the output mention array that houses all the word data for the sentiment analysis
         * @return array
         */
        public function return_sentiment_analysis() {
            return $this->output_mention;
        }

        /**
         * Return the identified "preferred match type"
         * @return string
         */
        public function return_preferred_match_type() {
            return $this->preferred_match_type;
        }

        /**
         * Return the min Levenshtein submit distance
         * 
         * @return floatval
         */
        public function return_levenshtein_min_submit_distance() {
            return $this->levenshtein_min_submit_distance;
        }

        /**
         * Calculate the sentiment rating
         * @return floatval     Sentiment rating
         */
        public function return_sentiment_rating() {

            /**
             * If this is a phrase match, return the phrase matches rating instead of the sentiment rating 
             *
             */
            foreach ($this->phrase_proximity as $key => $val) {
                $this->preferred_match_type = 'phrase_proximity';
                return $this->phrase_proximity[$key]['rating_modifier'];

            }
            $this->preferred_match_type = 'sentiment_analysis';

            $for_x = $this->return_positive_sentiment();
            $for_y = $this->return_negative_sentiment();
            $for_t = $this->number_words;


            $for_a = $this->number_positive_words;
            $for_b = $this->number_negative_words;

            $for_p = ($for_a / $for_t) * $for_x;
            $for_f = ($for_b / $for_t) * $for_y;

            if ($for_p > 2.5) { $for_p = 2.5; }
            if ($for_f > 2.5) { $for_f = 2.5; }

            $for_n = 2.5 + $for_p - $for_f;
            $for_n = round($for_n,2);



            return $for_n;
        }


        /**
         * Determine if the word changes the polarity to negative
         * @param  string   $text Word to analyse
         * @return bool
         */
        private function determine_negative_polarity($text) {
             foreach ($this->polarize_array as $polarized) {
                 if (trim($polarized['word']) == $text) { return true; }
             }
             return false;
        }



        /**
         * Determines if the word is an enhancer or not and if yes, return its rating modifier
         * @param  string $text Word to analyse
         * @return int          Rating modifier
         */
        private function determine_enhancer($text) {

                 foreach ($this->enhancer_array as $enhancer) {
                     if ($enhancer['word'] == $text) { return $enhancer['rating']; }
                 }




        }



        /**
         * Normalise and tokenise the string and return either an array or a modified string
         * @param  string   $text   Phrase or sentence to be tokenised
         * @param  string   $return Should this be returned as an array or string?
         * @return array/string     Tokenised array or string
         */
        private function tokenise($text,$return = 'array') {
            $text = strtolower($text);
            $matches = strip_tags(html_entity_decode($text)); // strip the rest of the HTML code
            $matches = preg_replace("/http(s)*:\/\/.+/i"," ",$matches);
            $matches = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $matches); // remove all non-utf8 characters
            $matches = preg_replace('/[,.]/', ' ', $matches); // Replace commas, hyphens etc (count them as spaces)
            $matches = preg_replace('/\<script.*?\<\/script\>/ism', '', $matches); //remove script tags
            $matches = preg_replace('/\<style.*?\<\/style\>/ism', '', $matches); // remove style tags
            $matches = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $matches); // remove square bracket tags
            $matches = strip_tags(html_entity_decode($matches)); // strip the rest of the HTML code
            $matches = preg_replace('/\s+/', ' ',$matches);

            if ($return == 'array') {
            $matches = explode(" ",$matches);
            }

            return $matches;

        }



        /**
         * Import individual lexicons and assign them to a specific array
         * @param  string   $file         File name of the lexicon
         * @param  array    $array_name   Array to assign the data to
         * @param  boolean  $store_rating Are we requiring the rating or not?
         * @return void
         */
        private function import_lexicons($file,$array_name,$store_rating = true) {
            $fh = fopen($file, 'r');
            $i = 0;
            while($line = fgets($fh)) {
                    $i++;
                    $tokens = explode("\t",$line);
                    $cnt = 0;
                    $this->{$array_name}[$i]["word"] = $tokens[0];
                    if ($store_rating) {
                        $this->{$array_name}[$i]["rating"] = $tokens[1];
                    }
            }
            fclose($fh);

        }


        /**
         * Trim unwanted characters
         * @param  string $word The word that requires trimming
         * @return string $word
         */
        private function trim_unwanted_chars($word) {
            $word = str_replace('\r','',$word);
            $word = str_replace('\n','',$word);
            $word = str_replace('\t','',$word);
            $word = trim($word);
            return $word;
        }

/**
 * Save a text to either the good, bad or neutral data file for reference later in the phrase proximity matching
 * @param  string   $text   The phrase to be recorded
 * @param  floatval $rating The sentiment rating of the phrase
 * @return void
 */
public function import_sentiment_custom($text,$rating) {

    if ($rating >= $this->min_neutral && $rating <= $this->max_neutral) {
        
        $fh = fopen(dirname(dirname(__FILE__))."/data/neutral_data.txt",'a+');
    } else if ($rating > $this->max_neutral) {
        
        $check = dirname(dirname(__FILE__))."/data/positive_data.txt";
        $fh = fopen($check,'a+');
    } else {
        
        $fh = fopen((dirname(__FILE__))."/data/negative_data.txt",'a+');
    }
    fwrite($fh,"\n\r".$text."\t".$rating);
    fclose($fh);

}
        
}
?>



