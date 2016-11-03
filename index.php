<?php
include ('lib/sentiment_analyser.class.php');
$sa = new SentimentAnalysis();
$sa->initialize();

if (isset($_POST['save_dats'])) {
	$rating = $_POST['rating'];
	$text = $_POST['text'];
	$sa->import_sentiment_custom($text,$rating);
	die();

} else { ?><!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">

  <title>Sentiment Analyser</title>
  <meta name="description" content="Sentiment Analyser">
  <meta name="author" content="Nick Duncan">

  <link rel="stylesheet" href="css/style.css?v=1.0">

  <!--[if lt IE 9]>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.js"></script>
  <![endif]-->
</head>

<body>

	<div class='wrapper'>

			<pre>
			<?php
			if (isset($_POST['sent_data'])){ ?>
				<div class='returned_data'>
					<h1>Returned dats</h1>
					<?php

						$sent_data = explode("\n",$_POST['sent_data']);
						$min_submit_lev_score = $sa->return_levenshtein_min_submit_distance();
						$analysed_array = array();
						$i = 0;
						foreach ($sent_data as $dataset) {
							$original_data = $dataset;
							
							$check = $sa->analyse($dataset);
							$rating = $sa->return_sentiment_rating();
							$ratings_data = $sa->return_sentiment_calculations();
							//echo $ratings_data;
							$analysed_array[$i]['dataset'] = implode(' ',$sa->return_tokenized_mention());
							$analysed_array[$i]['original_dataset'] = $original_data;
							$analysed_array[$i]['rating'] = $rating;
							$analysed_array[$i]['preferred_match_type'] = $sa->return_preferred_match_type();
							$analysed_array[$i]['sentiment_analysis'] = $sa->return_sentiment_analysis();
							$analysed_array[$i]['proximity_analysis'] = $sa->return_phrase_proximity();
							$i++;
						}
						echo "<p>Sentiment scale:</p>";
						echo "<ul>";
						echo "<li>< 2.5 : Low</li>";
						echo "<li>= 2.5 : Neutral</li>";
						echo "<li>> 2.5 : High</li>";
						echo "</ul>";
						echo "<p>Only strings that are 4 words or greater can be saved to the phrase dataset.</p>";
						echo "<table class='analysed_table'>";
						echo "<thead>";
						echo "<tr>";
						echo "<th>Action</th>";
						echo "<th>Preferred result of analysis</th>";
						echo "<th>Sentiment</th>";
						echo "<th>Dataset</th>";
						echo "</tr>";
						echo "</thead>";
						echo "<tbody>";

						foreach($analysed_array as $key => $output) {
							$allow_submission = false;
							//var_dump($output);
							if ($output['preferred_match_type'] == 'sentiment_analysis' || $output['proximity_analysis'][1]['levenshtein'] > $min_submit_lev_score) { 
								if (count(explode(" ",$output['dataset'])) < 4) {
									$allow_submission = false;
								} else {
									$allow_submission = true;
								}
							} else {
								$allow_submission = false;
							}
							
							echo "<tr id='tr_".$key."'>";
							if ($allow_submission) {  echo "<td><button class='sentiment_confirm' sid='".$key."'>Add to phrase dataset</button></td>"; } else { echo "<td>&nbsp;</td>";}
							echo "<td>".$output['preferred_match_type']."</td>";
							echo "<td><input type='text' id='sentiment_rating_".$key."' class='sentiment_rating' value='".$output['rating']."' /></td>";
							echo "<td><input type='text' id='sentiment_text_".$key."' value='".$output['dataset']."' /></td>";
							echo "</tr>";
						}
						echo "</tbody>";
						echo "</table>";
						echo "<p>&nbsp;</p>";
						echo "<hr />";
						echo "<h1>Dump</h1>";
						echo "<div class='dump'>";
						foreach ($analysed_array as $arr) {
							var_dump($arr);
						}
						echo "</div>";
					?>
				</div>
			</pre>
			<?php
				}
			?>
		

		<h1>Insert dats</h1>
		<p>Paste data here (one dataset per line)</p>
		<form action='' method='POST' name='sent_data_form'>
			<textarea name='sent_data' id='sent_data'><?php if (isset($_POST['sent_data'])) { echo $_POST['sent_data']; } ?></textarea>
			<input type='submit' name='submit_data' value='Analyse' />
		</form>

	<?php
	/*
		$check = $sa->analyse("The customer service from bestbuy was incredible and their delivery window was amazing!");
		var_dump($check);
		$scores = $sa->return_sentiment_rating();
		var_dump($scores);

		$ratings = $sa->return_sentiment_calculations();
		echo $ratings;
	*/
	?>

		
	</div>

  	<script src="js/jquery.min.js"></script>
  	<script src="js/scripts.js"></script>
</body>
</html>

<?php } ?>