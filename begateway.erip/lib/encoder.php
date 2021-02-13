<?
namespace BeGateway\Module\Erip;
use Bitrix\Main\Localization\Loc;

class Encoder {
  public static function GetEncodeMessage($message_id) {
    return self::GetEncodeText(Loc::getMessage($message_id));
  }

  public static function GetEncodeText($text) {
  	$siteEncode = SITE_CHARSET;

    if(self::isUtf8($text)) {
      $old_enc = 'UTF-8';
    } else {
      $old_enc = 'windows-1251';
    }
    if($siteEncode == $old_enc) {
      return $text;
    }

  	return mb_convert_encoding( $text, $siteEncode, $old_enc);
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
