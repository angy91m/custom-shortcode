<?php
<?php

function parse_arguments($str="") {
	$args = [];
	while (true) {
		$str = trim($str);
		$end_name = strpos($str,"=");
		if( $end_name === false) {
			break;
		}
		$arg_name = trim(substr($str, 0, $end_name));
		$str = trim(substr($str, $end_name+1));
		if (str_starts_with($str, '"')) {
			$str = substr($str, 1);
			$end_arg = false;
			$offset = 0;
			while($end_arg === false) {
				$pos = strpos($str, '"', $offset);
				if (substr($str, $pos-1, 1) == '\\') {
					$offset = $pos+1;
				} else {
					$end_arg=$pos;
				}
			}
			$args[$arg_name]=substr($str,0,$pos);
			$str = substr($str, $pos+1);
		} else {
			$pos = strpos($str," ");
			if ($pos === false) {
				$args[$arg_name]=$str;
				break;
			} else {
				$args[$arg_name]=substr($str,0,$pos);
				$str=substr($str,$pos);
			}
		}
	}
	return $args;
}

function custom_shortcode($str="", $avail_blocks = [], $excluded_blocks=[], $avail_vars = []) {
	$str = str_replace("\r", "", $str);
	$regx = '/\[ *(((\/)?[A-Z])?[A-Za-z_][0-9A-Za-z_]{0,127})(( +[A-Za-z_][0-9A-Za-z_]{0,127} *= *([^ ="\]]+|"([^"]|\\\\")*"))*) *]/s';
	$toks = explode("\n",$str);
	$line_n = 0;
	$in_block = [];
	$res = "";
	$exclude_block = false;
	$tok = $toks[$line_n];
	while (true) {
		$matches = [];
		$this_block = false;
		$ibc = count($in_block);
		if($ibc) {
			$this_block = $in_block[$ibc-1];
		}
		if (preg_match($regx, $tok, $matches, PREG_OFFSET_CAPTURE)) {
			$len = strlen($matches[0][0]);
			$pos = $matches[0][1];
			if (!$exclude_block) {
				$res .= substr($tok,0, $pos);
			}
			$tok = substr($tok,$pos+$len);
			$arguments = parse_arguments(trim($matches[4][0]));
			$block_name = $matches[1][0];
			if ($matches[2][0]) {
				// BLOCK
				if (!$matches[3][0]) {
					// START BLOCK
					if (!in_array($block_name, $avail_blocks)) {
						return [false, "Invalid block name at line ". ($line_n +1) . ": ". $block_name];
					}
					if ($exclude_block) {
						continue;
					}
					if (in_array($block_name, $in_block)) {
						return [false, "Duplicated block at line " . ($line_n+1) . ": $block_name"];
					}
					$in_block[] = $block_name;
					if (in_array($block_name, $excluded_blocks)) {
						$exclude_block = true;
					}
					if (array_key_exists("ba", $arguments) && $arguments["ba"] === "1" ) {
						$line_n++;
						if ($line_n < count($toks)) {
							$tok = $toks[$line_n];
						} else {
							break;
						}
					}
				} else {
					// END BLOCK
					$block_name = substr($block_name, 1);
					if (!in_array($block_name, $avail_blocks)) {
						return [false, "Invalid block name at line ". ($line_n +1) . ": $block_name"];
					}
					if ($this_block != $block_name) {
						if ($exclude_block){
							continue;
						}
						return [false, "Invalid close of block at line " . ($line_n+1) .": $block_name"];
					}
					if (!$exclude_block && array_key_exists("ba", $arguments) && $arguments["ba"] === "1" ) {
						$line_n++;
						if ($line_n < count($toks)) {
							$tok = $toks[$line_n];
						} else {
							break;
						}
					}
					array_pop($in_block);
					$exclude_block=false;
				}
			} else {
				if ($exclude_block) {
					continue;
				}
				if (array_key_exists($block_name, $avail_vars)) {
					if (is_callable($avail_vars[$block_name])) {
						if (count($arguments)) {
							$res .= ($avail_vars[$block_name])($arguments);
						} else {
							$res .= ($avail_vars[$block_name])();
						}
					} else {
						$res .= $avail_vars[$block_name];
					}
				} else {
					$res .= $matches[0][0];
				}
				if (array_key_exists("ba", $arguments) && $arguments["ba"] === "1" ) {
					$line_n++;
					if ($line_n < count($toks)) {
						$tok = $toks[$line_n];
					} else {
						break;
					}
				}
			}
		} else {
			$line_n++;
			if ($line_n < count($toks)) {
				if (!$exclude_block) {
					$res .= $tok . "\n";
				}
				$tok = $toks[$line_n];
			} else {
				if (!$exclude_block) {
					$res .= $tok;
				}
				break;
			}
		}
	}
	$ibc = count($in_block);
	if ($ibc) {
		return [false,"Unclosed block ". $in_block[$ibc-1]];
	}
	return $res;
}

/*
  The ``ba=1`` argument indicates that if the tag is not excluded, everything in the same row is deleted (including ``\n``).
  ``$avail_vars`` is an array of values convertible to string or callables that return that type of value.
*/

$example_str = "Dear [username],

[Signed ba=1]
would you like to see our items:

[itemlist ba=1]
[/Signed][Unsigned ba=1]
you are not Signed? Sign-in to see more items:

[itemlist limited=1 ba=1]
[/Unsigned]

Regards,

IT HelpDesk";

echo custom_shortcode($example_str, ["Signed","Unsigned"], ["Unsigned"], ["username"=> "Foo Bar", "itemlist" => function($args) {
	if (isset($args['limited']) && $args['limited'] == "1") {
		return "LIMITED";
	}
	return "ALL";
}]);
echo "\n\n--\n\n";
echo custom_shortcode($example_str, ["Signed","Unsigned"], ["Signed"], ["username"=> "Foo Bar", "itemlist" => function($args) {
	if (isset($args['limited']) && $args['limited'] == "1") {
		return "LIMITED";
	}
	return "ALL";
}]);

/*
Dear Foo Bar,

would you like to see our items:

ALL

Regards,

IT HelpDesk

--

Dear Foo Bar,

you are not Signed? Sign-in to see more items:

LIMITED

Regards,

IT HelpDesk
*/
