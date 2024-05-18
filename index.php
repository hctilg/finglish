<?php

ini_set('memory_limit', '2048M');

/**
 * Finglish to Persian Converter
 */
final class Finglish {
  public function __construct() {
    // check php version
    if (version_compare(phpversion(), '5.4', '<'))
      die("It requires PHP 5.4 or higher. Your PHP version is " . phpversion() . "\n");

    foreach ([
      'persian-word-freq.txt',
      'f2p-beginning.txt',
      'f2p-middle.txt',
      'f2p-ending.txt',
      'f2p-dict.txt'
    ] as $path) {
      if (!file_exists("$path")) copy("https://raw.githubusercontent.com/hctilg/finglish/main/$path", "$path");
    }
    
    // Loading converters...
    $this->beginning = $this->load_conversion_file('f2p-beginning.txt');
    $this->middle    = $this->load_conversion_file('f2p-middle.txt');
    $this->ending    = $this->load_conversion_file('f2p-ending.txt');

    // Loading persian word list...
    $this->word_freq = [];
    $lines = file($this->get_portable_filename('persian-word-freq.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (substr($line, 0, 1) !== '#') {
        $parts = array_filter(mb_split('\s', $line));
        $this->word_freq[$parts[0]] = (int)$parts[1];
      }
    }

    // Loading dictionary...
    $lines = file($this->get_portable_filename('f2p-dict.txt'),  FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $this->dicts = [];
    foreach ($lines as $line) {
      $parts = array_filter(mb_split(' ', $line, 2));
      $this->dicts[trim($parts[0])] = trim($parts[1]);
    }
  }

  /**
   * $finglish = new Finglish();
   * $finglish($string);
   * for example: $finglish('asemane abi');
   */
  public function __invoke(string $str) {
    return $this->convert_to_persian($str);
  }

  /**
   * Convert a Finglish phrase to the most probable Persian phrase.
   * $finglish = new Finglish();
   * $finglish->convert_to_persian($string);
   * for example: $finglish->convert_to_persian('asemane abi');
   */
  public function convert_to_persian(string $phrase, int $max_word_size=15, int $cutoff=3) {
    $output = [];
    $results = $this->f2p_list($phrase, $max_word_size, $cutoff);
    foreach ($results as $result) $output[] = $result[0][0];
    return implode(' ', $output);
  }

  private function get_portable_filename(string $filename) {
    $path = __DIR__ | dirname($filename);
    return $path . DIRECTORY_SEPARATOR . basename($filename);
  }

  private function load_conversion_file(string $filename) {
    $file = $this->get_portable_filename($filename);
    $data = [];
    $file = fopen($filename, "r", "UTF-8");
    if ($file) {
      while (($line = fgets($file)) !== false) {
        $line = trim($line);
        if (!empty($line)) {
          $parts = preg_split('/\s+/', $line);
          $key = array_shift($parts);
          $data[$key] = $parts;
        }
      }
      fclose($file);
    }
    return $data;
  }

  /**
    Convert a phrase from Finglish to Persian.

    phrase: The phrase to convert.

    max_word_size: Maximum size of the words to consider. Words larger
    than this will be kept unchanged.

    cutoff: The cut-off point. For each word, there could be many
    possibilities. By default 3 of these possibilities are considered
    for each word. This number can be changed by this argument.

    Returns a list of lists, each sub-list contains a number of
    possibilities for each word as a pair of (word, confidence)
    values.
   */
  private function f2p_list(string $phrase, int $max_word_size=15, int $cutoff=3) {
    // split the phrase into words
    $sep_regex = '/[ \-_~!@#%$^&*()\[\]{}\/:;"|,.\/?`]/';
    $results = preg_split($sep_regex, $phrase, -1, PREG_SPLIT_NO_EMPTY);
    
    // return an empty list if no words
    if (empty($results)) return [];

    // convert each word separately
    foreach ($results as &$w) $w = $this->f2p_word($w, $max_word_size, $cutoff);
    
    return $results;
  }

  /**
   * Create variations of the word based on letter combinations like oo, sh, etc.
   */
  private function variations($word) {
    if ($word == 'a') {
      return [['A']];
    } elseif (strlen($word) == 1) {
      return [[$word[0]]];
    } elseif ($word == 'aa') {
      return [['A']];
    } elseif ($word == 'ee') {
      return [['i']];
    } elseif ($word == 'ei') {
      return [['ei']];
    } elseif (in_array($word, ['oo', 'ou'])) {
      return [['u']];
    } elseif ($word == 'kha') {
      return [['kha'], ['kh', 'a']];
    } elseif (in_array($word, ['kh', 'gh', 'ch', 'sh', 'zh', 'ck'])) {
      return [[$word]];
    } elseif (in_array($word, ["'ee", "'ei"])) {
      return [["'i"]];
    } elseif (in_array($word, ["'oo", "'ou"])) {
      return [["'u"]];
    } elseif (in_array($word, ["a'", "e'", "o'", "i'", "u'", "A'"])) {
      return [[$word[0] . "'"]];
    } elseif (in_array($word, ["'a", "'e", "'o", "'i", "'u", "'A"])) {
      return [["'" . $word[1]]];
    } elseif (strlen($word) == 2 && $word[0] == $word[1]) {
      return [[$word[0]]];
    }

    if (substr($word, 0, 2) == 'aa') {
      return array_map(function($i) { return array_merge(['A'], $i); }, $this->variations(substr($word, 2)));
    } elseif (substr($word, 0, 2) == 'ee') {
      return array_map(function($i) { return array_merge(['i'], $i); }, $this->variations(substr($word, 2)));
    } elseif (in_array(substr($word, 0, 2), ['oo', 'ou'])) {
      return array_map(function($i) { return array_merge(['u'], $i); }, $this->variations(substr($word, 2)));
    } elseif (substr($word, 0, 3) == 'kha') {
      return array_merge(
        array_map(function($i) { return array_merge(['kha'], $i); }, $this->variations(substr($word, 3))),
        array_map(function($i) { return array_merge(['kh', 'a'], $i); }, $this->variations(substr($word, 3))),
        array_map(function($i) { return array_merge(['k', 'h', 'a'], $i); }, $this->variations(substr($word, 3)))
      );
    } elseif (in_array(substr($word, 0, 2), ['kh', 'gh', 'ch', 'sh', 'zh', 'ck'])) {
      return array_merge(
        array_map(function($i) use ($word) { return array_merge([substr($word, 0, 2)], $i); }, $this->variations(substr($word, 2))),
        array_map(function($i) use ($word) { return array_merge([$word[0]], $i); }, $this->variations(substr($word, 1)))
      );
    } elseif (in_array(substr($word, 0, 2), ["a'", "e'", "o'", "i'", "u'", "A'"])) {
      return array_map(function($i) use ($word) { return array_merge([substr($word, 0, 2)], $i); }, $this->variations(substr($word, 2)));
    } elseif (in_array(substr($word, 0, 3), ["'ee", "'ei"])) {
      return array_map(function($i) { return array_merge(["'i"] , $i); }, $this->variations(substr($word, 3)));
    } elseif (in_array(substr($word, 0, 3), ["'oo", "'ou"])) {
      return array_map(function($i) { return array_merge(["'u"] , $i); }, $this->variations(substr($word, 3)));
    } elseif (in_array(substr($word, 0, 2), ["'a", "'e", "'o", "'i", "'u", "'A"])) {
      return array_map(function($i) use ($word) { return array_merge([substr($word, 0, 2)], $i); }, $this->variations(substr($word, 2)));
    } elseif (strlen($word) >= 2 && $word[0] == $word[1]) {
      return array_map(function($i) use ($word) { return array_merge([$word[0]], $i); }, $this->variations(substr($word, 2)));
    } else {
      return array_map(function($i) use ($word) { return array_merge([$word[0]], $i); }, $this->variations(substr($word, 1)));
    }
  }

  /**
    Convert a single word from Finglish to Persian.

    max_word_size: Maximum size of the words to consider. Words larger
    than this will be kept unchanged.

    cutoff: The cut-off point. For each word, there could be many
    possibilities. By default 3 of these possibilities are considered
    for each word. This number can be changed by this argument.
   */
  private function f2p_word(string $word, int $max_word_size=15, int $cutoff=3) {
    $original_word = $word;
    $word = strtolower($word);

    if ($this->dicts[$word] ?? null) return [[$this->dicts, 1.0]];

    if ($word == '') return [];
    elseif (mb_strlen($word, 'UTF-8') > $max_word_size) return [[$original_word, 1.0]];

    $results = [];
    $variations = $this->variations($word); // You need to define the variations function in PHP
    foreach ($variations as $w) {
      $results = array_merge($results, $this->f2p_word_internal($w, $original_word)); // Assuming f2p_word_internal function exists
    }

    // Sort results based on the confidence value
    usort($results, function ($a, $b) {
      return (is_array($a) && is_array($b)) ? ($b[1] <=> $a[1]) : 0;
    });

    // Return the top three results in order to cut down on the number of possibilities.
    return array_slice($results, 0, $cutoff);  
  }

  /**
   * this function receives the word as separate letters
   */
  private function f2p_word_internal($word, string $original_word) {
    $persian = [];
    $alternatives = [];
    
    for ($i = 0; $i < count($word); $i++) {
      $letter = $word[$i];
      
      if ($i == 0) $converter = $this->beginning;
      elseif ($i == count($word) - 1) $converter = $this->ending;
      else $converter = $this->middle;
      
      $conversions = $converter[$letter] ?? null;
      
      if ($conversions === null) {
        return [($original_word), 0.0];
      } else {
        $conversions = array_map(function ($item) {
          return ($item == 'nothing') ? '' : $item;
        }, $conversions);
    }
      
      $persian[] = $conversions;
    }

    $alternatives = $this->cartesianProduct($persian);
    $alternatives = array_map(function ($item) {
      return implode($item);
    }, $alternatives);

    $alternatives = array_map(function ($alt) {
      return isset($this->word_freq[$alt]) ? [$alt, $this->word_freq[$alt]] : [$alt, 0];
    }, $alternatives);

    if (count($alternatives) > 0) {
      $max_freq = max(array_column($alternatives, 1));
      $alternatives = array_map(function ($item) use ($max_freq) {
        return [$item[0], ($item[1] != 0) ? floatval($item[1] / $max_freq) : 0.0];
      }, $alternatives);
    } else {
      $alternatives = [implode($word), 1.0];
    }

    return $alternatives;
  }

  function cartesianProduct($arrays) {
    $result = [[]];
    foreach ($arrays as $values) {
      $append = [];
      foreach ($result as $product) {
        foreach ($values as $value) {
          $append[] = array_merge($product, [$value]);
        }
      }
      $result = $append;
    }
    return $result;
  }

  function cartesian_product($arrays) {
    $result = [];
    $arrays = array_values($arrays);
    $sizeIn = sizeof($arrays);
    $size = $sizeIn > 0 ? 1 : 0;
    foreach ($arrays as $array) $size = $size * max(1, sizeof($array));
    for ($i = 0; $i < $size; $i ++) {
        $result[$i] = [];
        for ($j = 0; $j < $sizeIn; $j ++) array_push($result[$i], current($arrays[$j]));
        for ($j = ($sizeIn - 1); $j >= 0; $j --) {
          if (next($arrays[$j])) break;
          elseif (isset ($arrays[$j])) reset($arrays[$j]);
        }
    }
    return $result;
  }
}