<?php




/* File-scope initializer */
namespace Wikimedia\Parsoid\ParserTests;

use Wikimedia\Parsoid\Utils\PHPUtils;


class Grammar extends \WikiPEG\PEGParserBase {
  // initializer
  
  	/*
  	 * Empty class-scope initializer.
  	 *
  	 * Needed so the initializer above doesn't
  	 * become the class-scope initializer.
  	 */
  

  // cache init
  

  // expectations
  protected $expectations = [
    0 => ["type" => "end", "description" => "end of input"],
    1 => ["type" => "literal", "value" => "!!", "description" => "\"!!\""],
    2 => ["type" => "class", "value" => "[0-9]", "description" => "[0-9]"],
    3 => ["type" => "class", "value" => "[ \\t]", "description" => "[ \\t]"],
    4 => ["type" => "class", "value" => "[vV]", "description" => "[vV]"],
    5 => ["type" => "class", "value" => "[eE]", "description" => "[eE]"],
    6 => ["type" => "class", "value" => "[rR]", "description" => "[rR]"],
    7 => ["type" => "class", "value" => "[sS]", "description" => "[sS]"],
    8 => ["type" => "class", "value" => "[iI]", "description" => "[iI]"],
    9 => ["type" => "class", "value" => "[oO]", "description" => "[oO]"],
    10 => ["type" => "class", "value" => "[nN]", "description" => "[nN]"],
    11 => ["type" => "class", "value" => "[^\\n]", "description" => "[^\\n]"],
    12 => ["type" => "literal", "value" => "#", "description" => "\"#\""],
    13 => ["type" => "literal", "value" => "\x0a", "description" => "\"\\n\""],
    14 => ["type" => "literal", "value" => "article", "description" => "\"article\""],
    15 => ["type" => "literal", "value" => "text", "description" => "\"text\""],
    16 => ["type" => "literal", "value" => "endarticle", "description" => "\"endarticle\""],
    17 => ["type" => "literal", "value" => "test", "description" => "\"test\""],
    18 => ["type" => "class", "value" => "[^ \\t\\r\\n]", "description" => "[^ \\t\\r\\n]"],
    19 => ["type" => "literal", "value" => "options", "description" => "\"options\""],
    20 => ["type" => "literal", "value" => "end", "description" => "\"end\""],
    21 => ["type" => "literal", "value" => "hooks", "description" => "\"hooks\""],
    22 => ["type" => "literal", "value" => ":", "description" => "\":\""],
    23 => ["type" => "literal", "value" => "endhooks", "description" => "\"endhooks\""],
    24 => ["type" => "literal", "value" => "functionhooks", "description" => "\"functionhooks\""],
    25 => ["type" => "literal", "value" => "endfunctionhooks", "description" => "\"endfunctionhooks\""],
    26 => ["type" => "class", "value" => "[ \\t\\n]", "description" => "[ \\t\\n]"],
    27 => ["type" => "class", "value" => "[^ \\t\\n=!]", "description" => "[^ \\t\\n=!]"],
    28 => ["type" => "literal", "value" => "=", "description" => "\"=\""],
    29 => ["type" => "literal", "value" => ",", "description" => "\",\""],
    30 => ["type" => "literal", "value" => "[[", "description" => "\"[[\""],
    31 => ["type" => "class", "value" => "[^\\]]", "description" => "[^\\]]"],
    32 => ["type" => "literal", "value" => "]]", "description" => "\"]]\""],
    33 => ["type" => "class", "value" => "[\\\"]", "description" => "[\\\"]"],
    34 => ["type" => "class", "value" => "[^\\\\\\\"]", "description" => "[^\\\\\\\"]"],
    35 => ["type" => "literal", "value" => "\\", "description" => "\"\\\\\""],
    36 => ["type" => "any", "description" => "any character"],
    37 => ["type" => "class", "value" => "[^ \\t\\n\\\"\\'\\[\\]=,!\\{]", "description" => "[^ \\t\\n\\\"\\'\\[\\]=,!\\{]"],
    38 => ["type" => "literal", "value" => "{", "description" => "\"{\""],
    39 => ["type" => "class", "value" => "[^\\\"\\{\\}]", "description" => "[^\\\"\\{\\}]"],
    40 => ["type" => "literal", "value" => "}", "description" => "\"}\""],
  ];

  // actions
  private function a0($v) {
  
  		return [ 'type' => 'version', 'text' => $v ];
  	
  }
  private function a1($l) {
   return [ 'type' => 'line', 'text' => $l ]; 
  }
  private function a2($c) {
  
  	return implode($c);
  
  }
  private function a3($text) {
   return [ 'type' => 'comment', 'text' => $text ]; 
  }
  private function a4($title, $text) {
  
  	return [
  		'type' => 'article',
  		'title' => $title,
  		'text' => $text
  	];
  
  }
  private function a5($title, $sections) {
  
  	$test = [
  		'type' => 'test',
  		'title' => $title
  	];
  
  	foreach ( $sections as $section ) {
  		$test[$section['name']] = $section['text'];
  	}
  	// pegjs parser handles item options as follows:
  	//   item option             value of item.options.parsoid
  	//    <none>                          undefined
  	//    parsoid                             ""
  	//    parsoid=wt2html                  "wt2html"
  	//    parsoid=wt2html,wt2wt        ["wt2html","wt2wt"]
  	//    parsoid={"modes":["wt2wt"]}    {modes:['wt2wt']}
  
  	// treat 'parsoid=xxx,yyy' in options section as shorthand for
  	// 'parsoid={modes:["xxx","yyy"]}'
  	if ( isset($test['options']['parsoid'] ) ) {
  		if ($test['options']['parsoid'] === '') {
  			$test['options']['parsoid'] = [];
  		}
  		if ( is_string( $test['options']['parsoid'] ) ) {
  			$test['options']['parsoid'] = [ $test['options']['parsoid'] ];
  		}
  		if ( is_array( $test['options']['parsoid'] ) &&
  			!isset( $test['options']['parsoid']['modes'] )
  		) {
  			$test['options']['parsoid'] = [ 'modes' => $test['options']['parsoid'] ];
  		}
  	}
  	return $test;
  
  }
  private function a6($line) {
  
  	return $line;
  
  }
  private function a7($text) {
  
  	return [ 'type' => 'hooks', 'text' => $text ];
  
  }
  private function a8($text) {
  
  	return [ 'type' => 'functionhooks', 'text' => $text ];
  
  }
  private function a9($lines) {
  
  	return implode("\n", $lines);
  
  }
  private function a10($c) {
   return implode( $c ); 
  }
  private function a11($name, $text) {
  
  	return [ 'name' => $name, 'text' => $text ];
  
  }
  private function a12($opts) {
  
  	$o = [];
  	if ( $opts && count($opts) > 0 ) {
  		foreach ( $opts as $opt ) {
  			$o[$opt['k']] = $opt['v'];
  		}
  	}
  
  	return [ 'name' => 'options', 'text' => $o ];
  
  }
  private function a13($o, $rest) {
  
  	$result = [ $o ];
  	if ( $rest && count( $rest ) > 0 ) {
  		$result = array_merge( $result, $rest );
  	}
  	return $result;
  
  }
  private function a14($k, $v) {
  
  	return [ 'k' => strtolower( $k ), 'v' => $v ?? '' ];
  
  }
  private function a15($ovl) {
  
  	return count( $ovl ) === 1 ? $ovl[0] : $ovl;
  
  }
  private function a16($v, $ovl) {
   return $ovl; 
  }
  private function a17($v, $rest) {
  
  	$result = [ $v ];
  	if ( $rest && count( $rest ) > 0 ) {
  		$result = array_merge( $result, $rest );
  	}
  	return $result;
  
  }
  private function a18($v) {
  
  	if ( $v[0] === '"' || $v[0] === '{' ) { // } is needed to make pegjs happy
  		return PHPUtils::jsonDecode( $v, false );
  	}
  	return $v;
  
  }
  private function a19($v) {
  
  	// Perhaps we should canonicalize the title?
  	// Protect with JSON.stringify just in case the link target starts with
  	// double-quote or open-brace.
  	return PHPUtils::jsonEncode( implode( $v ) );
  
  }
  private function a20($c) {
   return "\\" . $c; 
  }
  private function a21($v) {
  
  	return '"' . implode( $v ) . '"';
  
  }
  private function a22($v) {
  
  	return implode( $v );
  
  }
  private function a23($v) {
  
  	return "{" . implode( $v ) . "}";
  
  }

  // generated
  private function parsetestfile($silence) {
    // start seq_1
    $p1 = $this->currPos;
    $r3 = $this->parseformat($silence);
    if ($r3===self::$FAILED) {
      $r3 = null;
    }
    $r4 = [];
    for (;;) {
      $r5 = $this->parsechunk($silence);
      if ($r5!==self::$FAILED) {
        $r4[] = $r5;
      } else {
        break;
      }
    }
    if (count($r4) === 0) {
      $r4 = self::$FAILED;
    }
    if ($r4===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r5
    $r2 = [$r3,$r4];
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function parseformat($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r4 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->discardwhitespace($silence);
    if ($r5===self::$FAILED) {
      $r5 = null;
    }
    $r6 = $this->discardversion_keyword($silence);
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = self::$FAILED;
    for (;;) {
      $r8 = $this->discardwhitespace($silence);
      if ($r8!==self::$FAILED) {
        $r7 = true;
      } else {
        break;
      }
    }
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    $p9 = $this->currPos;
    $r8 = self::$FAILED;
    for (;;) {
      $r10 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[0-9]/", $r10)) {
        $this->currPos++;
        $r8 = true;
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(2);}
        break;
      }
    }
    // v <- $r8
    if ($r8!==self::$FAILED) {
      $r8 = substr($this->input, $p9, $this->currPos - $p9);
    } else {
      $r8 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r10
    // free $p9
    $r10 = $this->discardrest_of_line($silence);
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a0($r8);
    }
    // free $p3
    return $r1;
  }
  private function parsechunk($silence) {
    // start choice_1
    $r1 = $this->parsecomment($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsearticle($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsetest($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    $r3 = $this->parseline($silence);
    // l <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a1($r3);
      goto choice_1;
    }
    $r1 = $this->parsehooks($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsefunctionhooks($silence);
    choice_1:
    return $r1;
  }
  private function discardwhitespace($silence) {
    $r1 = self::$FAILED;
    for (;;) {
      $r2 = $this->input[$this->currPos] ?? '';
      if ($r2 === " " || $r2 === "\x09") {
        $this->currPos++;
        $r1 = true;
      } else {
        $r2 = self::$FAILED;
        if (!$silence) {$this->fail(3);}
        break;
      }
    }
    // free $r2
    return $r1;
  }
  private function discardversion_keyword($silence) {
    // start seq_1
    $p1 = $this->currPos;
    $r3 = $this->input[$this->currPos] ?? '';
    if ($r3 === "v" || $r3 === "V") {
      $this->currPos++;
    } else {
      $r3 = self::$FAILED;
      if (!$silence) {$this->fail(4);}
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->input[$this->currPos] ?? '';
    if ($r4 === "e" || $r4 === "E") {
      $this->currPos++;
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(5);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->input[$this->currPos] ?? '';
    if ($r5 === "r" || $r5 === "R") {
      $this->currPos++;
    } else {
      $r5 = self::$FAILED;
      if (!$silence) {$this->fail(6);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->input[$this->currPos] ?? '';
    if ($r6 === "s" || $r6 === "S") {
      $this->currPos++;
    } else {
      $r6 = self::$FAILED;
      if (!$silence) {$this->fail(7);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->input[$this->currPos] ?? '';
    if ($r7 === "i" || $r7 === "I") {
      $this->currPos++;
    } else {
      $r7 = self::$FAILED;
      if (!$silence) {$this->fail(8);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r8 = $this->input[$this->currPos] ?? '';
    if ($r8 === "o" || $r8 === "O") {
      $this->currPos++;
    } else {
      $r8 = self::$FAILED;
      if (!$silence) {$this->fail(9);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r9 = $this->input[$this->currPos] ?? '';
    if ($r9 === "n" || $r9 === "N") {
      $this->currPos++;
    } else {
      $r9 = self::$FAILED;
      if (!$silence) {$this->fail(10);}
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardrest_of_line($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      $r5 = self::charAt($this->input, $this->currPos);
      if ($r5 !== '' && !($r5 === "\x0a")) {
        $this->currPos += strlen($r5);
        $r4[] = $r5;
      } else {
        $r5 = self::$FAILED;
        if (!$silence) {$this->fail(11);}
        break;
      }
    }
    // c <- $r4
    // free $r5
    $r5 = $this->discardeol($silence);
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a2($r4);
    }
    // free $p3
    return $r1;
  }
  private function parsecomment($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "#") {
      $this->currPos++;
      $r4 = "#";
    } else {
      if (!$silence) {$this->fail(12);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parserest_of_line($silence);
    // text <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a3($r5);
    }
    // free $p3
    return $r1;
  }
  private function parsearticle($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->discardstart_article($silence);
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parseline($silence);
    // title <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardstart_text($silence);
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsetext($silence);
    // text <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r8 = $this->discardend_article($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a4($r5, $r7);
    }
    // free $p3
    return $r1;
  }
  private function parsetest($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->discardstart_test($silence);
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsetext($silence);
    // title <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = [];
    for (;;) {
      // start choice_1
      $r7 = $this->parsesection($silence);
      if ($r7!==self::$FAILED) {
        goto choice_1;
      }
      $r7 = $this->parseoption_section($silence);
      choice_1:
      if ($r7!==self::$FAILED) {
        $r6[] = $r7;
      } else {
        break;
      }
    }
    // sections <- $r6
    // free $r7
    $r7 = $this->discardend_test($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a5($r5, $r6);
    }
    // free $p3
    return $r1;
  }
  private function parseline($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r5 = "!!";
      $this->currPos += 2;
    } else {
      $r5 = self::$FAILED;
    }
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parserest_of_line($silence);
    // line <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a6($r6);
    }
    // free $p3
    return $r1;
  }
  private function parsehooks($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->discardstart_hooks($silence);
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsetext($silence);
    // text <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardend_hooks($silence);
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a7($r5);
    }
    // free $p3
    return $r1;
  }
  private function parsefunctionhooks($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->discardstart_functionhooks($silence);
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsetext($silence);
    // text <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardend_functionhooks($silence);
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a8($r5);
    }
    // free $p3
    return $r1;
  }
  private function discardeol($silence) {
    if (($this->input[$this->currPos] ?? null) === "\x0a") {
      $this->currPos++;
      $r1 = "\x0a";
    } else {
      if (!$silence) {$this->fail(13);}
      $r1 = self::$FAILED;
    }
    return $r1;
  }
  private function parserest_of_line($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      $r5 = self::charAt($this->input, $this->currPos);
      if ($r5 !== '' && !($r5 === "\x0a")) {
        $this->currPos += strlen($r5);
        $r4[] = $r5;
      } else {
        $r5 = self::$FAILED;
        if (!$silence) {$this->fail(11);}
        break;
      }
    }
    // c <- $r4
    // free $r5
    $r5 = $this->discardeol($silence);
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a2($r4);
    }
    // free $p3
    return $r1;
  }
  private function discardstart_article($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "article", $this->currPos, 7, false) === 0) {
      $r5 = "article";
      $this->currPos += 7;
    } else {
      if (!$silence) {$this->fail(14);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardstart_text($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "text", $this->currPos, 4, false) === 0) {
      $r5 = "text";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(15);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function parsetext($silence) {
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      $r4 = $this->parseline($silence);
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // lines <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a9($r3);
    }
    return $r1;
  }
  private function discardend_article($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "endarticle", $this->currPos, 10, false) === 0) {
      $r5 = "endarticle";
      $this->currPos += 10;
    } else {
      if (!$silence) {$this->fail(16);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardstart_test($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "test", $this->currPos, 4, false) === 0) {
      $r5 = "test";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(17);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function parsesection($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r4 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->discardwhitespace($silence);
    if ($r5===self::$FAILED) {
      $r5 = null;
    }
    $p6 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "end", $this->currPos, 3, false) === 0) {
      $r7 = "end";
      $this->currPos += 3;
    } else {
      $r7 = self::$FAILED;
    }
    if ($r7 === self::$FAILED) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p6;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $p6 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "options", $this->currPos, 7, false) === 0) {
      $r8 = "options";
      $this->currPos += 7;
    } else {
      $r8 = self::$FAILED;
    }
    if ($r8 === self::$FAILED) {
      $r8 = false;
    } else {
      $r8 = self::$FAILED;
      $this->currPos = $p6;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $p6 = $this->currPos;
    $r10 = [];
    for (;;) {
      if (strcspn($this->input, " \x09\x0d\x0a", $this->currPos, 1) !== 0) {
        $r11 = self::consumeChar($this->input, $this->currPos);
        $r10[] = $r11;
      } else {
        $r11 = self::$FAILED;
        if (!$silence) {$this->fail(18);}
        break;
      }
    }
    if (count($r10) === 0) {
      $r10 = self::$FAILED;
    }
    // c <- $r10
    // free $r11
    $r9 = $r10;
    // name <- $r9
    if ($r9!==self::$FAILED) {
      $this->savedPos = $p6;
      $r9 = $this->a10($r10);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r11 = $this->discardrest_of_line($silence);
    if ($r11===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r12 = $this->parsetext($silence);
    // text <- $r12
    if ($r12===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a11($r9, $r12);
    }
    // free $p3
    return $r1;
  }
  private function parseoption_section($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r4 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->discardwhitespace($silence);
    if ($r5===self::$FAILED) {
      $r5 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "options", $this->currPos, 7, false) === 0) {
      $r6 = "options";
      $this->currPos += 7;
    } else {
      if (!$silence) {$this->fail(19);}
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->discardwhitespace($silence);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    $r8 = $this->discardeol($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r9 = $this->parseoption_list($silence);
    if ($r9===self::$FAILED) {
      $r9 = null;
    }
    // opts <- $r9
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a12($r9);
    }
    // free $p3
    return $r1;
  }
  private function discardend_test($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "end", $this->currPos, 3, false) === 0) {
      $r5 = "end";
      $this->currPos += 3;
    } else {
      if (!$silence) {$this->fail(20);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardstart_hooks($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "hooks", $this->currPos, 5, false) === 0) {
      $r5 = "hooks";
      $this->currPos += 5;
    } else {
      if (!$silence) {$this->fail(21);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r6 = ":";
    } else {
      if (!$silence) {$this->fail(22);}
      $r6 = self::$FAILED;
      $r6 = null;
    }
    $r7 = $this->discardwhitespace($silence);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    $r8 = $this->discardeol($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardend_hooks($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "endhooks", $this->currPos, 8, false) === 0) {
      $r5 = "endhooks";
      $this->currPos += 8;
    } else {
      if (!$silence) {$this->fail(23);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->discardeol($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardstart_functionhooks($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "functionhooks", $this->currPos, 13, false) === 0) {
      $r5 = "functionhooks";
      $this->currPos += 13;
    } else {
      if (!$silence) {$this->fail(24);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r6 = ":";
    } else {
      if (!$silence) {$this->fail(22);}
      $r6 = self::$FAILED;
      $r6 = null;
    }
    $r7 = $this->discardwhitespace($silence);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    $r8 = $this->discardeol($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function discardend_functionhooks($silence) {
    // start seq_1
    $p1 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r3 = "!!";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(1);}
      $r3 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "endfunctionhooks", $this->currPos, 16, false) === 0) {
      $r5 = "endfunctionhooks";
      $this->currPos += 16;
    } else {
      if (!$silence) {$this->fail(25);}
      $r5 = self::$FAILED;
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r6 = ":";
    } else {
      if (!$silence) {$this->fail(22);}
      $r6 = self::$FAILED;
      $r6 = null;
    }
    $r7 = $this->discardwhitespace($silence);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    $r8 = $this->discardeol($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    return $r2;
  }
  private function parseoption_list($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsean_option($silence);
    // o <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = self::$FAILED;
    for (;;) {
      if (strspn($this->input, " \x09\x0a", $this->currPos, 1) !== 0) {
        $r6 = $this->input[$this->currPos++];
        $r5 = true;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(26);}
        break;
      }
    }
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    $r6 = $this->parseoption_list($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    // rest <- $r6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a13($r4, $r6);
    }
    // free $p3
    return $r1;
  }
  private function parsean_option($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseoption_name($silence);
    // k <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parseoption_value($silence);
    if ($r5===self::$FAILED) {
      $r5 = null;
    }
    // v <- $r5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a14($r4, $r5);
    }
    // free $p3
    return $r1;
  }
  private function parseoption_name($silence) {
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      if (strcspn($this->input, " \x09\x0a=!", $this->currPos, 1) !== 0) {
        $r4 = self::consumeChar($this->input, $this->currPos);
        $r3[] = $r4;
      } else {
        $r4 = self::$FAILED;
        if (!$silence) {$this->fail(27);}
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // c <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a2($r3);
    }
    return $r1;
  }
  private function parseoption_value($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->discardwhitespace($silence);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r5 = "=";
    } else {
      if (!$silence) {$this->fail(28);}
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->discardwhitespace($silence);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    $r7 = $this->parseoption_value_list($silence);
    // ovl <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a15($r7);
    }
    // free $p3
    return $r1;
  }
  private function parseoption_value_list($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsean_option_value($silence);
    // v <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $r8 = $this->discardwhitespace($silence);
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    if (($this->input[$this->currPos] ?? null) === ",") {
      $this->currPos++;
      $r9 = ",";
    } else {
      if (!$silence) {$this->fail(29);}
      $r9 = self::$FAILED;
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r10 = $this->discardwhitespace($silence);
    if ($r10===self::$FAILED) {
      $r10 = null;
    }
    $r11 = $this->parseoption_value_list($silence);
    // ovl <- $r11
    if ($r11===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a16($r4, $r11);
    } else {
      $r5 = null;
    }
    // free $p7
    // rest <- $r5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a17($r4, $r5);
    }
    // free $p3
    return $r1;
  }
  private function parsean_option_value($silence) {
    $p2 = $this->currPos;
    // start choice_1
    $r3 = $this->parselink_target_value($silence);
    if ($r3!==self::$FAILED) {
      goto choice_1;
    }
    $r3 = $this->parsequoted_value($silence);
    if ($r3!==self::$FAILED) {
      goto choice_1;
    }
    $r3 = $this->parseplain_value($silence);
    if ($r3!==self::$FAILED) {
      goto choice_1;
    }
    $r3 = $this->parsejson_value($silence);
    choice_1:
    // v <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a18($r3);
    }
    return $r1;
  }
  private function parselink_target_value($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
      $r4 = "[[";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(30);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      $r6 = self::charAt($this->input, $this->currPos);
      if ($r6 !== '' && !($r6 === "]")) {
        $this->currPos += strlen($r6);
        $r5[] = $r6;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(31);}
        break;
      }
    }
    // v <- $r5
    // free $r6
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "]]", $this->currPos, 2, false) === 0) {
      $r6 = "]]";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(32);}
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a19($r5);
    }
    // free $p3
    return $r1;
  }
  private function parsequoted_value($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->input[$this->currPos] ?? '';
    if ($r4 === "\"") {
      $this->currPos++;
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(33);}
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      // start choice_1
      $r6 = self::charAt($this->input, $this->currPos);
      if ($r6 !== '' && !($r6 === "\\" || $r6 === "\"")) {
        $this->currPos += strlen($r6);
        goto choice_1;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(34);}
      }
      $p7 = $this->currPos;
      // start seq_2
      $p8 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "\\") {
        $this->currPos++;
        $r9 = "\\";
      } else {
        if (!$silence) {$this->fail(35);}
        $r9 = self::$FAILED;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      // c <- $r10
      if ($this->currPos < $this->inputLength) {
        $r10 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(36);}
        $this->currPos = $p8;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      $r6 = true;
      seq_2:
      if ($r6!==self::$FAILED) {
        $this->savedPos = $p7;
        $r6 = $this->a20($r10);
      }
      // free $p8
      choice_1:
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
    }
    // v <- $r5
    // free $r6
    $r6 = $this->input[$this->currPos] ?? '';
    if ($r6 === "\"") {
      $this->currPos++;
    } else {
      $r6 = self::$FAILED;
      if (!$silence) {$this->fail(33);}
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a21($r5);
    }
    // free $p3
    return $r1;
  }
  private function parseplain_value($silence) {
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      if (strcspn($this->input, " \x09\x0a\"'[]=,!{", $this->currPos, 1) !== 0) {
        $r4 = self::consumeChar($this->input, $this->currPos);
        $r3[] = $r4;
      } else {
        $r4 = self::$FAILED;
        if (!$silence) {$this->fail(37);}
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // v <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a22($r3);
    }
    return $r1;
  }
  private function parsejson_value($silence) {
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r4 = "{";
    } else {
      if (!$silence) {$this->fail(38);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      // start choice_1
      if (strcspn($this->input, "\"{}", $this->currPos, 1) !== 0) {
        $r6 = self::consumeChar($this->input, $this->currPos);
        goto choice_1;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(39);}
      }
      $r6 = $this->parsequoted_value($silence);
      if ($r6!==self::$FAILED) {
        goto choice_1;
      }
      $r6 = $this->parsejson_value($silence);
      choice_1:
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
    }
    // v <- $r5
    // free $r6
    if (($this->input[$this->currPos] ?? null) === "}") {
      $this->currPos++;
      $r6 = "}";
    } else {
      if (!$silence) {$this->fail(40);}
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a23($r5);
    }
    // free $p3
    return $r1;
  }

  public function parse($input, $options = []) {
    $this->initInternal($input, $options);
    $startRule = $options['startRule'] ?? '(DEFAULT)';
    $result = null;

    if (!empty($options['stream'])) {
      switch ($startRule) {
        
        default:
          throw new \WikiPEG\InternalError("Can't stream rule $startRule.");
      }
    } else {
      switch ($startRule) {
        case '(DEFAULT)':
        case "testfile":
          $result = $this->parsetestfile(false);
          break;
        default:
          throw new \WikiPEG\InternalError("Can't start parsing from rule $startRule.");
      }
    }

    if ($result !== self::$FAILED && $this->currPos === $this->inputLength) {
      return $result;
    } else {
      if ($result !== self::$FAILED && $this->currPos < $this->inputLength) {
        $this->fail(0);
      }
      throw $this->buildParseException();
    }
  }
}

