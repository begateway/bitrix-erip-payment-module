<?
namespace \BeGateway\Module\Erip;

class Encoder {
  public static function GetEncodeMessage($text) {
  	$siteEncode = SITE_CHARSET;
  	$message = Loc::getMessage($text);

    if(self::isUtf8($message)) {
      $old_enc = 'UTF-8';
    } else {
      $old_enc = 'windows-1251';
    }
    if($siteEncode == $old_enc) {
      return $message;
    }

  	return mb_convert_encoding( $message, $siteEncode, $old_enc);
  }

  public static function toUtf8($text, $size = 0)
  {
  	$encodedText = $text;

    if(!self::isUtf8($encodedText)) {
      $encodedText = mb_convert_encoding($encodedText, 'UTF-8', SITE_CHARSET);
    }

    if ($size > 0) {
      $encodedText = mb_substr($encodedText, 0, $size);
    }
    return $encodedText;
  }

  public static function isUtf8($text) {
    return mb_detect_encoding($text,mb_list_encodings()) == 'UTF-8';
  }

  public static function reEncode($folder, $enc) {
    $files = scandir($folder);
    foreach( $files as $file ) {
      if( $file == "." || $file == ".." ) { continue; }

      $path = $folder . DIRECTORY_SEPARATOR . $file;
      $content = file_get_contents($path);

      if( is_dir($path) ) {
        $this->reEncode( $path, $enc );
      }
      else {

        if(self::isUtf8($content)) {
          $old_enc = 'UTF-8';
        } else {
          $old_enc = 'windows-1251';
        }
        if($enc == $old_enc) {
          continue;
        }
        $content = mb_convert_encoding( $content, $enc, $old_enc );
        if( is_writable($path) ) {
          unlink($path);
          $ff = fopen($path,'w');
          fputs($ff,$content);
          fclose($ff);
        }
      }
    }
  }

  /**
   * @param string $str
   * @param int $length
   * @return array
   */
  public static function str_split(string $str, int $length = 999) {
    $tmp = preg_split('~~u', $str, -1, PREG_SPLIT_NO_EMPTY);
    if ($length > 1) {
        $chunks = array_chunk($tmp, $length);
        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = join('', (array) $chunk);
        }
        $tmp = $chunks;
    }
    return $tmp;
  }
}
