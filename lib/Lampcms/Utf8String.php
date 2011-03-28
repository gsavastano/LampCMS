<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;


/**
 * Class for working with UTF-8 encoded strings
 * Sanitization, charset guessing, converting encoding,
 * and bunch of utf-8-safe string manipulation functions
 *
 * @author Dmitri Snytkine
 *
 */
class Utf8String extends String
{

	/**
	 * This one is for str_word_count
	 * method
	 *
	 */
	const WORD_COUNT_MASK = "/\p{L}[\p{L}\p{Mn}\p{Pd}'\x{2019}]*/u";

	public function __construct($string, $returnMode = 'default'){

		if(!extension_loaded('mbstring')){
			$err = "This class required mbstring extension to be loaded.
			\nPlease check you php to make sure you have mbstring extension";
			e($err);
			throw new \RuntimeException($err);
		}

		parent::__construct($string, $returnMode);
		mb_regex_encoding("UTF-8");
		mb_internal_encoding("UTF-8");

		/**
		 * @todo if have iconv, also set its' encoding to utf-8
		 */
	}


	/**
	 * Takes in a string and a charset
	 * If charset is utf8, then validates
	 * it and if necessary recodes it to
	 * get rid of invalid chars
	 *
	 * If charset is not passed, it will try
	 * to guess charset
	 *
	 *
	 * @param $string
	 * @param $charset
	 * @param bool $isClean indicates that string is already
	 * guaranteed clean utf8 string and does not have to pass
	 * through our validation and sanitization routines
	 *
	 * @param string $className the name of child class that we
	 * actually need to instantiate. Because we don't have support
	 * for late static binding if we call this factory
	 * from a child class, the self keyword will refer to
	 * this class, even though it was called from a child class,
	 * therefore this class will be instantiated, which is not what we want.
	 *
	 * So if we want to call a child's factory, we must have a factory
	 * method in child and from there call parent::factory() (this class)
	 * and pass the name of child class (using __CLASS__ in child is OK for that)
	 *
	 * @return object of this class
	 */
	public static function factory($string, $charset = null, $isClean = false)
	{

		/**
		 * Special case:
		 * first argument can be an array
		 * with mandatory element 'string' that holds
		 * an actual string
		 * and second element 'charset' which holds
		 * the name of charset of this string
		 * the 'charset' is optional.
		 *
		 * This array is returned by our MessageParser class
		 * when email is parsed.
		 */
		if(is_array($string)){
			if(empty($string['string']) || !is_string($string['string'])){
				throw new \InvalidArgumentException('if $string param is array, it must include non-empty element with "string"');
			}

			$a = $string;
			$charset = null;
			d('$string: '.print_r($a, 1).' charset: '.gettype($a['charset']). ' '. $a['charset']);
			$string = $a['string'];

			if(!empty($a['charset'])){
				$charset = $a['charset'];
			}
		}

		/**
		 * If $charset is not supplied we will try to guess it
		 *
		 */
		$charset = (empty($charset)) ? strtolower(self::guessCharset($string)) : strtolower($charset);

		d('charset: '.$charset);
		//d('$className: '.$className);

		if(('us-ascii' === $charset) || ('ascii' === $charset) || ('utf-8' === $charset)){
			if($isClean){
				$o = new static($string);
				d('cp ');
			} else {
				$bIsAscii = (('us-ascii' === $charset) || ('ascii' === $charset));
				d('$bIsAscii: '.$bIsAscii);
				$string = static::sanitizeUtf8($string, $bIsAscii);
				d('cp ');
				$o = new static($string);
				d('cp ');
			}
		} else {
			/**
			 * Now we need to convert string to utf8
			 */
			d('cp ');
			$utf8string = static::convertToUtf8($string, $charset);
			//d('cp');
			$utf8string = static::stripLow($utf8string);

			$o = new static($utf8string);
			d('cp o: '.gettype($o).' '.get_class($o));
		}

		return $o;
	}


	/**
	 * When encoding of string is unknown this function
	 * will try to guess the encoding
	 * This is never 100% accurate, but
	 * usually pretty close to 97% accuracy
	 * this is still better than just rejecting unknown string
	 *
	 * @param string $string input string
	 * @param string $charsetHint sometimes we already have an idea
	 * of what the charset it, but instead of trusting this idea
	 * we still would rather guessCharset first, and only use
	 * the 'hint' in case we can't guess the charset
	 * this may be helpful only where this 'hint' charset is some
	 * exotic charset that our guesser does not support
	 *
	 * @return string name of charset
	 */
	public static function guessCharset($string, $charsetHint = ''){
		$cs = false;
		$charsetHint = strtoupper($charsetHint);
		$charsetHint = ('US-ASCII' === $charsetHint) ? 'ASCII' : $charsetHint;
		$charsetHint = ('LATIN1' === $charsetHint) ? 'ISO-8859-1' : $charsetHint;
		$charsetHint = ('LATIN-1' === $charsetHint) ? 'ISO-8859-1' : $charsetHint;

		$aDetectOrder = array('ASCII', 'UTF-8','ISO-8859-1', 'JIS', 'ISO-8859-15', 'EUC-JP', 'SJIS' );

		/**
		 * Charset hint is useless to us
		 * if it's in detect order array
		 * it's only usefull if we can't detect it
		 * using mb_detect_encoding
		 */
		if(in_array($charsetHint, $aDetectOrder)){
			$charsetHint = null;
		}

		if(!function_exists('mb_detect_encoding') ){

			throw new \RuntimeException('Unable to detect charset encoding because mbstring extension is not available and a string is not in UTF-8');
		}

		if(false === mb_detect_order($aDetectOrder)){
			throw new \RuntimeException('Unable to set charset detect order');
		}

		$cs = mb_detect_encoding($string);
		d('guessed charset: '.$cs);
		$cs = (false === $cs) ? $charsetHint : $cs;

		if(empty($cs)){
			throw new \RuntimeException('Unable to detect charset using mb_detect_order. Possibly string is encoded in some rare charset');
		}

		d('$cs: '.$cs);

		return $cs;

	}


	/**
	 * Validates the string,
	 * if necessary, recodes in using iconv,
	 * strips low bytes except for tab, space, \n
	 *
	 * @param string $string utf8 string
	 * @return string sanitized string which
	 * is fairly safe to use
	 */
	public static function sanitizeUtf8($utf8string, $bIsAscii = false)
	{

		d('cp $bIsAscii '.$bIsAscii);

		if(true !== self::validateUtf8($utf8string)){
			d('cp');
			/**
			 * Now that we know that string is not a valid utf-8 what do we do?
			 * We can try to detect charset and see what the guesses tells us
			 * Maybe it will detect some other charset, in which case we will
			 * know what the real charset is and recode the string.
			 *
			 * If the guesser fails, then we can recode the string
			 * with the //IGNORE option.
			 *
			 */
			try{
				d('cp');
				$charset = strtolower(self::guessCharset($utf8string));
			} catch(\Exception $e){
				d('unable to guess charset');
				/**
				 * Unable to guess charset
				 */
				throw new \RuntimeException('unable to guess charset');
			}

			if($charset){
				try{
					d('cp');

					if('utf8' !== $charset){
						d('cp');

						$utf8string = self::convertToUtf8($utf8string, $charset);
					} else {
						d('cp');
						$utf8string = self::recodeUtf8($utf8string);
					}
				} catch (\Exception $e){
					d('cp '.$e->getMessage());
					throw new \RuntimeException('unable to convert encoding');
				}
			}
		}

		if($bIsAscii){

			return self::sanitizeAscii($utf8string);
		}

		return self::stripLow($utf8string);
	}


	/**
	 * Checks that string is a valid utf8
	 *
	 * @todo test the mb_check_encoding() to see if
	 * it even works. The manual is screwy says that
	 * it inspects the byte stream, but the function
	 * says argument is a string. Must check for yourselves how
	 * this works.
	 *
	 * @param string $string utf8 string
	 * @return bool true if string is valid, false if not valid
	 */
	public static function validateUtf8($utf8string){

		if ( strlen($utf8string) == 0 ) {

			return true;
		}
		// If even just the first character can be matched, when the /u
		// modifier is used, then it's valid UTF-8. If the UTF-8 is somehow
		// invalid, nothing at all will match, even if the string contains
		// some valid sequences
		return (preg_match('/^.{1}/us',$utf8string, $ar) == 1);

		/**
		 * The uti8ToUnicode sometimes crashes the php
		 * so we not going to use it for now
		 */
		//return (false !== self::utf8ToUnicode($utf8string));
	}


	/**
	 * Takes an UTF-8 string and returns an array of ints representing the
	 * Unicode characters. Astral planes are supported ie. the ints in the
	 * output can be > 0xFFFF. Occurrances of the BOM are ignored. Surrogates
	 * are not allowed.
	 *
	 * This function is primaraly used to validate
	 * the utf8 string
	 *
	 *  The Original Code is Mozilla Communicator client code.
	 *  this php function is taken from the
	 *  UTF-8 to Code Point Array Converter in PHP
	 *
	 *  http://hsivonen.iki.fi/php-utf8/
	 *
	 * @return mixed array | false if the input string
	 * isn't a valid UTF-8 octet sequence.
	 */
	public static function utf8ToUnicode(&$str)
	{

		d('cp');

		$mState = 0;     // cached expected number of octets after the current octet
		// until the beginning of the next UTF8 character sequence
		$mUcs4  = 0;     // cached Unicode character
		$mBytes = 1;     // cached expected number of octets in the current sequence

		//$out = array();

		$len = strlen($str);
		d('$len: '.$len);
		for($i = 0; $i < $len; $i++) {
			//$oLogger->log('cp $i '.$i);
			$in = ord($str[$i]);
			//$oLogger->log('cp');
			if (0 == $mState) {
				// $oLogger->log('cp');
				// When mState is zero we expect either a US-ASCII character or a
				// multi-octet sequence.
				if (0 == (0x80 & ($in))) {
					//$oLogger->log('cp');
					// US-ASCII, pass straight through.
					//$out[] = $in;
					$mBytes = 1;
				} else if (0xC0 == (0xE0 & ($in))) {
					//$oLogger->log('cp');
					// First octet of 2 octet sequence
					$mUcs4 = ($in);
					$mUcs4 = ($mUcs4 & 0x1F) << 6;
					$mState = 1;
					$mBytes = 2;
					//$oLogger->log('cp');
				} else if (0xE0 == (0xF0 & ($in))) {
					//$oLogger->log('cp');
					// First octet of 3 octet sequence
					$mUcs4 = ($in);
					$mUcs4 = ($mUcs4 & 0x0F) << 12;
					$mState = 2;
					$mBytes = 3;
					//$oLogger->log('cp');
				} else if (0xF0 == (0xF8 & ($in))) {
					//$oLogger->log('cp');
					// First octet of 4 octet sequence
					$mUcs4 = ($in);
					$mUcs4 = ($mUcs4 & 0x07) << 18;
					$mState = 3;
					$mBytes = 4;
					//$oLogger->log('cp');
				} else if (0xF8 == (0xFC & ($in))) {
					//$oLogger->log('cp');
					/* First octet of 5 octet sequence.
					 *
					 * This is illegal because the encoded codepoint must be either
					 * (a) not the shortest form or
					 * (b) outside the Unicode range of 0-0x10FFFF.
					 * Rather than trying to resynchronize, we will carry on until the end
					 * of the sequence and let the later error handling code catch it.
					 */
					$mUcs4 = ($in);
					$mUcs4 = ($mUcs4 & 0x03) << 24;
					$mState = 4;
					$mBytes = 5;
					//$oLogger->log('cp');
				} else if (0xFC == (0xFE & ($in))) {
					d('cp');
					// First octet of 6 octet sequence, see comments for 5 octet sequence.
					$mUcs4 = ($in);
					$mUcs4 = ($mUcs4 & 1) << 30;
					$mState = 5;
					$mBytes = 6;
					//$oLogger->log('cp');
				} else {
					d('cp');
					/* Current octet is neither in the US-ASCII range nor a legal first
					 * octet of a multi-octet sequence.
					 */
					return false;
				}
			} else {
				//$oLogger->log('cp');
				// When mState is non-zero, we expect a continuation of the multi-octet
				// sequence
				if (0x80 == (0xC0 & ($in))) {
					// Legal continuation.
					$shift = ($mState - 1) * 6;
					$tmp = $in;
					$tmp = ($tmp & 0x0000003F) << $shift;
					$mUcs4 |= $tmp;

					if (0 == --$mState) {
						/* End of the multi-octet sequence. mUcs4 now contains the final
						 * Unicode codepoint to be output
						 *
						 * Check for illegal sequences and codepoints.
						 */

						// From Unicode 3.1, non-shortest form is illegal
						if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
						((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
						((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
						(4 < $mBytes) ||
						// From Unicode 3.2, surrogate characters are illegal
						(($mUcs4 & 0xFFFFF800) == 0xD800) ||
						// Codepoints outside the Unicode range are illegal
						($mUcs4 > 0x10FFFF)) {
							return false;
						}
						if (0xFEFF != $mUcs4) {
							// BOM is legal but we don't want to output it
							//$out[] = $mUcs4;
						}
						//initialize UTF8 cache
						$mState = 0;
						$mUcs4  = 0;
						$mBytes = 1;
					}
				} else {
					d('cp');
					/* ((0xC0 & (*in) != 0x80) && (mState != 0))
					 *
					 * Incomplete multi-octet sequence.
					 */
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * Converts from utf8 to utf8 but with
	 * an option to ignore errors.
	 * This way illegal chars are stripped from
	 * the string
	 *
	 * @param string $string utf8 string
	 * @return string fixed utf8 string
	 *
	 * @throws RuntimeException if iconv function
	 * is not available on the server.
	 */
	public static function recodeUtf8($utf8string){

		if(!function_exists('iconv') && !function_exists('mb_convert_encoding')){
			throw new \RuntimeException('Cannot use this method because iconv OR mb_convert_encoding functions is not available');
		}
		/**
		 * IMPORTANT
		 * lower the error reporting here
		 * in order to suppress warnings and stuff like that
		 * because iconv will generate a warning if it detects
		 * illegal char
		 */

		if(function_exists('iconv')){
			$ER = error_reporting(1);
			/**
			 * We are not going to do this: setlocale(LC_ALL, 'en_US.UTF8');
			 * setlocale() is not necessary because we are not transliterating (no //TRANSLIT switch)
			 * and by the way, //TRANSLIT//IGNORE does not really work,
			 * its a myth that they work fine together
			 */
			$ret = iconv("UTF-8", "UTF-8//IGNORE", $utf8string);
			error_reporting($ER);

			return $ret;
		}

		$ER = error_reporting(1);
		/**
		 * By default the substitute string is a ? (question mark)
		 * meaning when character is not available in target charset
		 * the substitute will be used
		 */
		mb_substitute_character("none");
		$ret = mb_convert_encoding($utf8string, "UTF-8", "UTF-8");

		/**
		 * Restore error_reporting back to what id was
		 */
		error_reporting($ER);

		return $ret;
	}


	/**
	 * strips low bytes except for \r, tab and \n
	 *
	 * @param string $string utf8 string
	 * @return string string with low bytes removed
	 */
	public static function stripLow($utf8string){
		$ret = preg_replace("/[^\x9\xA\xD\x20-\xFFFFFF]/", "", $utf8string);

		return $ret;
	}


	/**
	 * Strips low and high bytes,
	 * leaving only the bytes valid
	 * for an ASCII string
	 * This is similar to stripLow() except
	 * than unlike stripLow(), high bytes are also
	 * stripped off, meaning that this function
	 * should NEVER by used to sanitize a utf8 string
	 * because it will basically remove all
	 * non english letters from it!
	 *
	 * @param $asciistring
	 * @return string
	 */
	public static function sanitizeAscii($asciistring){
		$ret = preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", $asciistring);

		return $ret;
	}


	/**
	 * Convert string to utf8
	 * IMPORTANT: MUST KNOW current charset!
	 *
	 * Will try to use mb_convert_encoding
	 * and if that fails, will use iconv() with //IGNORE option
	 * We are not going to use //TRANSLIT version here
	 * because we are converting to UTF-8 and there is
	 * very little chance that some character cannot be
	 * found in UTF-8 and need transliteration.
	 *
	 * @param string $string original string
	 * @param string $fromCharset name of charset
	 * @return string converted string in UTF8 charset
	 *
	 * @todo either wrap some conversion inside try/catch
	 * or at least switch error reporting to suppress warnings for now
	 */
	public static function convertToUtf8($string, $fromCharset)
	{

		d('cp');
		if(!is_string($string) && !is_object($string)){
			throw new \InvalidArgumentException('$string must be a string or object of type \Lampcms\String');
		}

		/**
		 * @todo
		 * if instance of this class then just return
		 * it since it's already a utf8 string
		 *
		 * otherwise it's an instance of \Lampcms\String class
		 * in which case we don't know charset,
		 * so we will call detectCharset, then convert
		 */
		if(is_object($string) && !($string instanceof \Lampcms\String)){
			throw new \InvalidArgumentException('$string is not instance of \Lampcms\String class');
		}

		$ret = false;
		$fromCharset = strtoupper($fromCharset);
		$fromCharset = ('KS_C_5601-1987' !== $fromCharset) ? $fromCharset : 'EUC-KR';

		/**
		 * US-ASCII is already a valid UTF-8
		 */
		if( ('US-ASCII' === $fromCharset) ||  ('ASCII' === $fromCharset) || ('UTF-8' === $fromCharset) ){

			return $string;
		}

		/**
		 * utf8_encode is simple and good way to encode to utf8
		 * but only works with ISO-8859-1
		 */
		if('ISO-8859-1' === $fromCharset && function_exists('utf8_encode')){

			d('cp');

			return utf8_encode($string);
		}

		if(function_exists('iconv') && ('UTF7-IMAP' !== $fromCharset) && (('UTF-7' !== $fromCharset) && function_exists('mb_convert_encoding')) ){
			/**
			 * @todo make sure that the $charset is known to imap
			 * and not some made-up uknown charset. If user puts something like
			 * 'crap-chars' as value of encoding of xml, then we sure don't want
			 * to even try to convert something that we don't know how to handle
			 */

			$ret = iconv($fromCharset, "UTF-8//IGNORE", $string);
		}

		if((false === $ret) && function_exists('mb_convert_encoding')){
			$fromCharset = ('UTF-7' !== $fromCharset) ? $fromCharset : 'UTF7-IMAP';
			$a = mb_list_encodings();
			if(in_array($fromCharset, $a)){
				mb_internal_encoding("UTF-8");
				mb_substitute_character("none");
				$ret = mb_convert_encoding($string, 'UTF-8', $fromCharset);
			}
		}

		if(false === $ret){
			throw new \UnexpectedValueException('Unable to convert string to utf-8 from charset: '.$fromCharset);
		}

		return $ret;
	}


	/**
	 * Returns number of chars in a string
	 * this is not necessaraly the number of bytes
	 * because string of this class may be multibyte
	 */
	public function length(){

		return mb_strlen($this->string);
	}

	/**
	 * Uts8-safe str_word_count
	 * Taken from one of the comments on this
	 * php manual page:
	 * http://us3.php.net/str_word_count
	 *
	 * @return int number of word in utf8 string
	 */
	public function getWordsCount(){

		return preg_match_all(self::WORD_COUNT_MASK, $this->string, $matches);
	}


	/**
	 * Cut utf8 string of text to be not more
	 * than $max chars but makes sure it cuts
	 * only on word boundary
	 *
	 * @param int $max max length after which string to be cut
	 *
	 * @param string $link optional link
	 * to be added if string is cut (like link to 'read more')
	 *
	 * @return object of this class
	 * representing a newly cut string
	 */
	public function truncate($max, $link = ''){

		$words = mb_split("\s", $this->string);

		$newstring = '';
		$numwords = 0;

		foreach ($words as $word) {
			if ((mb_strlen($newstring) + 1 + mb_strlen($word)) < $max) {
				$newstring .= ' '.$word;
				++$numwords;
			} else {
				break;
			}
		}

		if ($numwords < count($words)) {
			/**
			 * Adds utf-8 Ellipses (3 dots)
			 * This is better than manually adding 3 dots
			 * because this adds just one char!
			 *
			 *
			 */
			$newstring .= "\xE2\x80\xA6".$link;
		}

		return $this->handleReturn($newstring);
	}


	/**
	 * Utf-8 safe wordwrap
	 *
	 * @param $width
	 * @param $break
	 * @param $cut
	 * @return unknown_type
	 */
	public function wordWrap($width = 75, $break = "\n", $cut = false)
	{
		/**
		 * The wordwrap is basically already utf8 safe unless the
		 * $cut is true
		 * This means we will use wordwrap by default
		 * unless we need to cut the string
		 *
		 */
		if(!$cut){
			$ret = wordwrap($this->string, $width, $break);
		} else {
			// We first need to explode on $break, not destroying existing (intended) breaks
			$lines = explode($break, $this->string);
			$new_lines = array(0 => '');
			$index = 0;

			foreach ($lines as $line){
				$words = explode(' ', $line);

				for ($i = 0, $size = count($words); $i < $size; $i++){
					$word = $words[$i];

					// If cut is true we need to cut the word if it is > width chars
					if ($cut && (mb_strlen($word) > $width) ){
						$words[$i] = mb_substr($word, $width);
						$word = mb_substr($word, 0, $width);
						$i--;
					}

					if (mb_strlen($new_lines[$index] . $word) > $width){
						$new_lines[$index] = mb_substr($new_lines[$index], 0, -1);
						$index++;
						$new_lines[$index] = '';
					}

					$new_lines[$index] .= $word . ' ';
				}

				$new_lines[$index] = mb_substr($new_lines[$index], 0, -1);
				$index++;
				$new_lines[$index] = '';
			}

			unset($new_lines[$index]);

			$ret = implode($break, $new_lines);
		}

		return $this->handleReturn($ret);
	}


	/**
	 * UTF8 safe ucfirst
	 * I made this public static
	 * because I needed this intermediate function in this class
	 * so instead of making it a protected method
	 * I thought it might be more useful
	 * to make it public static since now
	 * it can be used as a standalone function
	 *
	 * @param string $utf8string
	 *
	 * @return string a string with first letter upercased, the rest lowercase
	 */
	public static function utf8_ucfirst($utf8string){
		$string = mb_strtolower($utf8string);

		$first = mb_strtoupper(mb_substr($string, 0, 1));

		return $first.mb_substr($string, 1, mb_strlen($string));
	}


	/**
	 * UTF-8 safe implementation of ucfirst()
	 * @return unknown_type
	 */
	public function ucfirst(){

		return $this->handleReturn(self::utf8_ucfirst($this->string));
	}


	/**
	 * UTF-8 safe ucwords
	 * @return object of this class ($this or new object)
	 */
	public function ucwords(){
		$words = explode(' ', $this->string);
		$ret = '';
		foreach($words as $word){
			$ret .= self::utf8_ucfirst($word);
		}

		return $this->handleReturn($ret);
	}


	/**
	 * Repair the string (this string should be html or
	 * this method does not make sense at all!)
	 *
	 * @param array $aTidyConfig configuration array
	 * of tidy options. IMPORTANT: boolean options
	 * must be set as true or false (not 'yes' or 'no')
	 * list of tidy config options here:
	 *
	 * @return unknown_type
	 */
	public function tidy(array $aTidyConfig = array())
	{
		$ret = $this->string;
		if($this->isHtml()){
			if (function_exists('tidy_parse_string')) {
				d('going to use tidy_parse_string');
				$aConfig = array(
                     'clean' => true,
                     'output-html' => true,
                     'show-body-only' => true,
                     'wrap' => 0,
					 'output-bom' => false, /* should always set this to no with only exception if output is utf16, which we never intent on doing */
					 'doctype' => 'omit',
					 'char-encoding' => 'utf8',
					 'drop-proprietary-attributes' => true, /* this does not affect invalid attributes, only proprietory. Invalid arrtibutes are always removed */
					 'bare' => true,
					 'add-xml-space' => true

				);

				$config = array_merge($aConfig, $aTidyConfig);

				$oTidy = tidy_parse_string($this->string, $config, 'UTF8');
				$oTidy->cleanRepair();
				$ret = (string)$oTidy;
				d('after tidy: '.$ret);
			}
		}

		return $this->handleReturn($ret);
	}


	/**
	 * Uses HTML_Safe to
	 * remove dangerous tags from html string
	 *
	 * HTML_Safe class removes body, header
	 * leaves only what is inside body tag, (unless body and
	 * html are added to allowed tags)
	 * but will also work if there is no body tag at all.

	 *
	 * @return object of this class
	 */
	public function safeHtml(array $aAllowedTags = array()){
		$ret = $this->string;
		if($this->isHtml()){
			$oHS = new HtmlSafe();
			if(!empty($aAllowedTags)){
				$oHS->setAllowedTags($aAllowedTags);
			}

			$ret = $oHS->parse($this->string);
			d('after safeHtml(): '.$ret);
		}

		return $this->handleReturn($ret);
	}


	/**
	 * Get the plaintext version of this string
	 * in case it's an html
	 *
	 * @return object of this class
	 */
	public function getPlainText(){

		if(!$this->isHtml()){

			$ret = $this->string;
		} else {

			d('looks like is HTML');

			try{
				$oHTML2TEXT = H2t::factory($this->tidy(array('show-body-only' => false))->valueOf());
				$ret =  $oHTML2TEXT->getText();
			} catch(\Lampcms\Exception $e){
				throw $e;
			} catch (\Exception $e){
				/**
				 * strip_tags seems like the best option
				 * its not going to be as nice as converting
				 * to plaintext and preserving some formatting
				 * but still its good
				 * as it at least guarantees to remove html
				 */
				e('unable to use H2t: '.$e->getMessage());
				//$ret = strip_tags($this->string);
				$ret = $this->asPlainText();
			}
		}

		return $this->handleReturn($ret);
	}


	/**
	 * If the string is NOT HTML
	 * then wrap string inside <pre></pre> tag
	 *
	 */
	public function asHtml(){
		if(!$this->isHtml()){
			return $this->wrapInTag('span');
		}

		return $this->handleReturn($this->string);
	}


	/**
	 * Converts the utf-8 string
	 * to ASCII charset using translitiration if
	 * iconv is available
	 *
	 * @return object instance of this class
	 */
	public function toASCII(){
		$ascii = false;
		if(extension_loaded('iconv')){
			setlocale(LC_ALL, 'en_US.UTF8');
			$ER = error_reporting(0);
			$ascii = iconv("UTF-8", "ASCII//TRANSLIT", $this->string);
			error_reporting($ER);
		}

		if(false === $ascii){
			mb_substitute_character("none");
			$ascii = mb_convert_encoding($this->string, 'ASCII', 'UTF-8');
		}

		return $this->handleReturn($ascii);
	}


	/**
	 * Converts this string to ISO-8859-1 charset (latin-1)
	 * If iconv is available will use translitiration,
	 * which produces better results,
	 * otherwise will use the default ut8_decode
	 * which sill just remove any char that cannot be represented
	 * in latin-1 charset
	 *
	 * @return object of this class
	 */
	public function toLatin1(){
		$ret = false;
		if(extension_loaded('iconv')){
			setlocale(LC_ALL, 'en_US.UTF8');
			$ER = error_reporting(0);
			$ret = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $this->string);
			error_reporting($ER);
		}

		if(false === $ret){
			$ret = utf8_decode($this->string);
		}

		return $this->handleReturn($ret);

	}


	/**
	 * Run this string through htmlspecialchars with ENT_NOQUOTES
	 * This will turn < and > and & into special chars
	 * will not change quotes and will not touch already
	 * existing special chars.
	 *
	 * @return object of this type
	 */
	public function htmlspecialchars(){
		$ret = htmlspecialchars($this->string, ENT_NOQUOTES, 'UTF-8', false);

		return $this->handleReturn($ret);
	}


	public function stripTags(array $aAllowed = null){
		$ret = strip_tags($this->string, $aAllowed);

		return $this->handleReturn($ret);
	}


	public function htmlentities(){
		$ret = htmlentities($this->string, ENT_NOQUOTES, 'UTF-8', false);

		return $this->handleReturn($ret);
	}


	/**
	 * This will convert our utf-8 string
	 * to ASCII but in such a way that all
	 * special non-ascii characters that can be represented
	 * as html entities will be turned into such entities
	 *
	 * This is the most lossless conversion when we need
	 * to output string as ASCII but preserve as much
	 * special chars as possible
	 *
	 * Keep in mind, these entities are not valid
	 * in XML unless specifically declared somewhere in the
	 * XML file or declaring the doctype as XHTML
	 *
	 *
	 * @return object of this class
	 */
	public function toHtmlEntities()
	{

	}

	/**
	 * Truncate HTML string while making sure
	 * not to cut in the middle of open html tag
	 *
	 * @param int $intCut desired max length of string
	 *
	 * @param string $strLink optional link to add
	 * after the string was cut like 'read more'
	 *
	 * @param bool $bUseTidy use tidy to fix string after it was
	 * cut. This is very helpful to make sure that even if
	 * the string was cut in the middle of opened html tag,
	 * tidy will repair such string. Also it is helpful to
	 * make sure only the contents of 'body' is returned
	 * without the actual <html><head><body> tags
	 * Without tidy the result may be very bad
	 *
	 * @return object of this type
	 */
	public function truncateHtml($intCut = 0, $strLink = '', $bUseTidy = true)
	{

		$strText = $this->string;

		if ( (mb_strlen($strText, 'UTF-8') < $intCut) || ('0' == $intCut)) {

			$this->handleReturn($strText);
		}

		if(!$this->isHtml()){

			return $this->truncate($intCut, $strLink);
		}

		$arrText = str_split($strText);

		$intCountOpen = 0;
		$intCountClose = 0;
		$intCount = 0;
		$openTag = false;
		$strNew = '';
		$arrExcludeChars = array(" ", "\n", "\r", "\f", "\v", "\0");
		foreach ($arrText as $intPos => $strChar) {
			$strNew .= $strChar;

			if ('<' === $strChar) {
				$openTag = true;

				$intCountOpen++;
				continue;
			}
			if ( ($openTag) && ('>' === $strChar)) {
				$openTag = false;

				$intCountClose++;
				continue;
			}
			if (!$openTag && !in_array($strChar, $arrExcludeChars)) {
				$intCount++;
				if ($intCount == $intCut) {

					break;
				}
			}
		}

		$strTmp = mb_substr($strText, 0, ($intPos + 1));
		$intLastPos = mb_strrpos($strTmp, " ");

		if (false === $intLastPos || ($intCount < $intCut)) {
			$strNew = $strTmp;
			$boolWasCut = false;
		} else {
			$strNew = trim(mb_substr($strTmp, 0, $intLastPos));
			$boolWasCut = true;
		}

		/**
		 * fix $strNew string in case it has been split in the
		 * middle of the open html tag(s)
		 * requires tidy, so we first check if tidy is available
		 */
		if ($bUseTidy && function_exists('tidy_parse_string')) {

			$aConfig = array(
                     'clean' => true,
                     'output-html' => true,
                     'show-body-only' => true,
                     'wrap' => 0,
					 'output-bom' => false, /* should always set this to no with only exception if output is utf16, which we never intent on doing */
					 'doctype' => 'omit',
					 'char-encoding' => 'utf8',
					 'drop-proprietary-attributes' => true,
					 'bare' => true,
					 'add-xml-space' => true

			);

			$oTidy = tidy_parse_string($strNew, $aConfig, 'UTF8');
			$oTidy->cleanRepair();
			$strNew = (string)$oTidy;
		}

		if (!empty($strLink) && $boolWasCut) {

			$strNew .= '... '.$strLink;
		}

		return $this->handleReturn($strNew);

	}


	/**
	 * Convert hex codepoint to actual utf8 character
	 * For example: $s = Utf8String::cp2utf8('263B');
	 * echo $s
	 * Should output the dark background smilie face
	 * You can find the list of almost all possible utf8 codepoints here:
	 * http://www.unicode.org/charts/
	 * some wingding-like chars here
	 * http://www.alanwood.net/demos/wingdings.html
	 *
	 * @param $hexcp must be in hex forat, for example : 269D (outline star)
	 *
	 * @return actual utf8 character - it can be printed
	 * on the page, as long as the page is set to utf-8
	 * Page header must be already sent like this:
	 * header('Content-Type: text/html; charset=utf-8');
	 *
	 * OR set the HTTP-EQUIV meta tag:
	 * <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	 */
	public static function cp2utf8($hexcp) {

		$ret = '';
		$n = hexdec($hexcp);
		switch(true){
			case ($n <= 0x7F):
				$ret .= chr($n);
				break;

			case ($n <= 0x7FF) :
				$ret .= chr(0xC0 | (($n>>6) & 0x1F))
				.chr(0x80 | ($n & 0x3F));
				break;

			case ($n <= 0xFFFF) :
				$ret .= chr(0xE0 | (($n>>12) & 0x0F))
				.chr(0x80 | (($n>>6) & 0x3F))
				.chr(0x80 | ($n & 0x3F));
				break;

			case ($n <= 0x10FFFF):
				$ret .= chr(0xF0 | (($n>>18) & 0x07))
				.chr(0x80 | (($n>>12) & 0x3F)).chr(0x80 | (($n>>6) & 0x3F))
				.chr(0x80 | ($n & 0x3F));
				break;

			default :
				throw new \InvalidArgumentException('Unsupported codepoing '.$hexcp);

		}

		return $ret;
	}


	/**
	 * Enter the hex code for the utf8 character
	 * and it outputs the string that can then be used
	 * in php in order to generate that unicode char
	 *
	 * It does not actually generate the char, only ouputs a string like this:
	 * enter input param: 2602 and get back: (with double quotes included) "\xE2\x98\x82"
	 *
	 * You can then just assing the value of return to some variable, like this
	 * $umbrella = Utf8String::findChar('2602');
	 * echo $umbrella will NOT output the actual utf8 char, but just the string "\xE2\x98\x82"
	 *
	 * Basically this function is created only to generate strings that can
	 * then be used to populate some type of codepoint table
	 *
	 * @param $hexcp
	 * @return unknown_type
	 */
	public static function findChar($hexcp) {
		$ret = '';
		$n = hexdec($hexcp);

		switch(true){
			case ($n <= 0x7F):
				$ret .= '\x'.strtoupper(dechex($n));
				break;

			case ($n <= 0x7FF) :
				$ret .= '\x'.strtoupper(dechex((0xC0 | (($n>>6) & 0x1F))))
				.'\x'.strtoupper(dechex((0x80 | ($n & 0x3F))));
				break;

			case ($n <= 0xFFFF):
				$ret .= '\x'.strtoupper(dechex((0xE0 | (($n>>12) & 0x0F))))
				.'\x'.strtoupper(dechex((0x80 | (($n>>6) & 0x3F))))
				.'\x'.strtoupper(dechex((0x80 | ($n & 0x3F))));
				break;

			case ($n <= 0x10FFFF) :
				$ret .= '\x'.strtoupper(dechex((0xF0 | (($n>>18) & 0x07))))
				.'\x'.strtoupper(dechex((0x80 | (($n>>12) & 0x3F))))
				.'\x'.strtoupper(dechex((0x80 | (($n>>6) & 0x3F))))
				.'\x'.strtoupper(dechex((0x80 | ($n & 0x3F))));
				break;

			default :
				throw new \RuntimeException('Unsupported codepoing '.$hexcp);
					
		}

		return '"'.$ret.'"';
	}


	public function parseMarkdown(){
		$md = new \Lampcms\Markdown();
		$ret = $md->transform($this->string);

		return $this->handleReturn($ret);
	}


	/**
	 * Parse MiniMardDown:
	 * mini mark down converts **string** to <strong>string</strong>
	 * and _string_ to <em>string</em>
	 *
	 * @return object of this class representin parsed string
	 * which may now contain html tags
	 *
	 */
	public function mmd2Html(){

		$parsed = preg_replace('/([*]{2})(.*)([*]{2})/Ui', '<strong>\\2</strong>', $this->string);
		$parsed = preg_replace('/(_)(.*)(_)/Ui', '<em>\\2</em>', $parsed);

		return $this->handleReturn($parsed);
	}


	/**
	 * UTF-8 safe strtolower()
	 *
	 * (non-PHPdoc)
	 * @see Lampcms.String::toLowerCase()
	 */
	public function toLowerCase(){
		$s = mb_strtolower($this->string);

		return $this->handleReturn($s);
	}


	/**
	 * UTF-8 safe strtoupper
	 * (non-PHPdoc)
	 * @see Lampcms.String::toUpperCase()
	 */
	public function toUpperCase(){
		$s = mb_strtoupper($this->string);

		return $this->handleReturn($s);
	}


	public function substr($start, $len = null){
		$s = mb_substr($this->string, $start, $len);

		return $this->handleReturn($s);
	}



}

