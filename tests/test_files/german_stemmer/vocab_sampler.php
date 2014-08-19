<?php
    $words = file("input_vocabulary.txt");
    $stems = file("stemmed_result.txt");
    $num_words = count($words);
    $num_samples = 1000;
    $sample_words = array();
    $sample_stems = array();
    $indices = array();
    for($i = 0; $i < $num_samples; $i++) {
        do {
            $rand = rand(0, $num_words - 1);
        } while(isset($indices[$rand]));
        $indices[$rand] = true;
    }
    $indices = array_keys($indices);
    sort($indices);
    for($i = 0; $i < $num_samples; $i++) {
        $index = $indices[$i];
        $sample_words[] = trim($words[$index]);
        $sample_stems[] = trim($stems[$index]);
    }
    file_put_contents("2input_vocabulary.txt", implode("\n", $sample_words));
    file_put_contents("2stemmed_result.txt", implode("\n", $sample_stems));
?>
